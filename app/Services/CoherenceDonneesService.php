<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Cohérence des dates d'un questionnaire, côté serveur.
 *
 * `questionnaires.donnees` était validé `['required', 'array']` : **toute** la validation du
 * questionnaire vivait dans le navigateur. Un appel direct, un import, ou simplement un écran dont
 * les contrôles avaient été oubliés (c'est arrivé quatre fois) laissait entrer une pièce expirant
 * avant d'avoir été délivrée ou une naissance dans le futur — et l'acte authentique le reprenait.
 *
 * ⚠️ **Ce service ne vérifie pas les champs obligatoires, et c'est délibéré.** La visibilité d'un
 * champ dépend des `showIf` du schéma, qui vit en JavaScript ([décision #33](devbook.md)) : l'exiger
 * ici imposerait de dupliquer ce schéma en PHP, donc de le faire diverger. La cohérence **entre deux
 * dates**, elle, ne demande aucune connaissance du schéma — d'où ce périmètre.
 *
 * ⚠️ **Ne s'applique pas aux brouillons** : un brouillon est un formulaire en cours de saisie
 * ([décision #34](devbook.md)), il doit rester enregistrable incomplet et incohérent.
 *
 * Miroir de `PAIRES_DATES` et `CONTRAINTES_DATES` dans `resources/js/data/questionnaires.js` —
 * `CoherenceDonneesTest` verrouille la correspondance.
 */
class CoherenceDonneesService
{
    /**
     * Groupes d'état civil — naissance, délivrance et expiration d'une même personne.
     *
     * Le dernier groupe est **sans préfixe** : c'est celui des blocs répétables (associés, gérants,
     * souscripteurs), appliqué à chaque entrée du bloc.
     *
     * @var list<array{naissance: string, delivree: ?string, expire: ?string}>
     */
    public const GROUPES_DATES = [
        ['naissance' => 'pp.date_naissance',             'delivree' => 'pp.piece_delivree_le',             'expire' => 'pp.piece_expire_le'],
        ['naissance' => 'ger.date_naissance',            'delivree' => 'ger.piece_delivree_le',            'expire' => 'ger.piece_expire_le'],
        ['naissance' => 'acq.date_naissance',            'delivree' => 'acq.piece_delivree_le',            'expire' => 'acq.piece_expire_le'],
        ['naissance' => 'gerant_entrant.date_naissance', 'delivree' => 'gerant_entrant.piece_delivree_le', 'expire' => 'gerant_entrant.piece_expire_le'],
        ['naissance' => 'date_naissance',                'delivree' => 'piece_delivree_le',                'expire' => 'piece_expire_le'],
        ['naissance' => 'soc.president_date_naissance',  'delivree' => null,                               'expire' => null],
    ];

    /**
     * Dates isolées dont on peut affirmer une borne, **et elles seules**.
     *
     * Ce qui n'y figure pas est délibéré : `bail.date_prise_effet` et
     * `modif.augmentation_date_versement` sont légitimement futures ; `dissolution.date_assemblee`,
     * `hypotheque.date_acte`, `modif.date_cession` et `gerant_sortant.date_cessation` restent libres
     * faute de savoir quelle borne leur donner. Une contrainte inventée est pire qu'une absente.
     *
     * @var list<array{champ: string, genre: string, apres?: string, message: string}>
     */
    public const CONTRAINTES = [
        [
            'champ'   => 'soc.date_constitution',
            'genre'   => 'passee',
            'message' => 'La date de constitution ne peut pas être dans le futur.',
        ],
        [
            'champ'   => 'ag.date_effet',
            'genre'   => 'ordre',
            'apres'   => 'ag.date',
            'message' => "La date d'effet ne peut pas précéder l'assemblée qui l'a décidée.",
        ],
    ];

    /**
     * Incohérences trouvées, sous la forme attendue par `ValidationException`.
     *
     * @param  array<string, mixed> $donnees
     * @return array<string, string> clé de champ (préfixée `donnees.` pour la réponse 422) → message
     */
    public function erreurs(array $donnees): array
    {
        $erreurs = [];

        foreach (self::GROUPES_DATES as $groupe) {
            // Le groupe sans préfixe s'applique **dans** les blocs répétables, pas à la racine.
            if (!str_contains($groupe['naissance'], '.')) {
                continue;
            }

            $erreurs += $this->erreursDuGroupe($donnees, $groupe, '');
        }

        // Blocs répétables : chaque entrée porte les champs sans préfixe.
        $sansPrefixe = collect(self::GROUPES_DATES)->firstWhere(fn (array $g) => !str_contains($g['naissance'], '.'));

        foreach ($donnees as $cle => $valeur) {
            if (!is_array($valeur) || !array_is_list($valeur)) {
                continue;
            }

            foreach ($valeur as $index => $entree) {
                if (is_array($entree) && $sansPrefixe) {
                    $erreurs += $this->erreursDuGroupe($entree, $sansPrefixe, "{$cle}.{$index}.");
                }
            }
        }

        foreach (self::CONTRAINTES as $contrainte) {
            $message = $contrainte['genre'] === 'passee'
                ? $this->siFuture($donnees[$contrainte['champ']] ?? null, $contrainte['message'])
                : $this->siAnterieure(
                    $donnees[$contrainte['champ']] ?? null,
                    $donnees[$contrainte['apres']] ?? null,
                    $contrainte['message'],
                );

            if ($message !== null) {
                $erreurs["donnees.{$contrainte['champ']}"] = $message;
            }
        }

        return $erreurs;
    }

    /**
     * Les quatre contrôles d'un groupe d'état civil.
     *
     * Chaque incohérence décrit une saisie **impossible**, jamais un simple avertissement : une pièce
     * expirée reste enregistrable — l'étude consigne la situation réelle du client — mais une pièce
     * délivrée avant la naissance de son porteur est une erreur de saisie.
     *
     * @param  array<string, mixed> $source
     * @param  array{naissance: string, delivree: ?string, expire: ?string} $groupe
     * @return array<string, string>
     */
    private function erreursDuGroupe(array $source, array $groupe, string $prefixeErreur): array
    {
        $erreurs = [];

        $naissance = $this->date($source[$groupe['naissance']] ?? null);
        $delivree  = $groupe['delivree'] ? $this->date($source[$groupe['delivree']] ?? null) : null;
        $expire    = $groupe['expire']   ? $this->date($source[$groupe['expire']]   ?? null) : null;

        $cle = fn (string $champ) => "donnees.{$prefixeErreur}{$champ}";

        if ($naissance && $naissance->startOfDay()->gte(Carbon::today())) {
            $erreurs[$cle($groupe['naissance'])] = 'La date de naissance ne peut pas être dans le futur.';
        }

        if ($naissance && $naissance->year < 1900) {
            $erreurs[$cle($groupe['naissance'])] = 'La date de naissance semble erronée (avant 1900).';
        }

        if ($delivree && $delivree->startOfDay()->gt(Carbon::today())) {
            $erreurs[$cle($groupe['delivree'])] = "La pièce ne peut pas avoir été délivrée dans le futur.";
        }

        if ($naissance && $delivree && $delivree->lt($naissance)) {
            $erreurs[$cle($groupe['delivree'])] = "La pièce ne peut pas avoir été délivrée avant la naissance du titulaire.";
        }

        if ($delivree && $expire && $expire->lte($delivree)) {
            $erreurs[$cle($groupe['expire'])] = "L'expiration doit être postérieure à la délivrance.";
        }

        return $erreurs;
    }

    private function siFuture(mixed $valeur, string $message): ?string
    {
        $date = $this->date($valeur);

        return $date && $date->startOfDay()->gt(Carbon::today()) ? $message : null;
    }

    private function siAnterieure(mixed $valeur, mixed $reference, string $message): ?string
    {
        $date = $this->date($valeur);
        $ref  = $this->date($reference);

        return $date && $ref && $date->lt($ref) ? $message : null;
    }

    /**
     * Lit une date de questionnaire — `JJ/MM/AAAA`, le format de `donnees` (voir le contrat en tête
     * de `resources/js/lib/dates.js`). L'ISO est toléré, un import pouvant en porter.
     *
     * ⚠️ `Carbon::parse()` est proscrit ici : il lit `JJ/MM/AAAA` comme du **mois/jour américain** et
     * `01/04/1985` y devient le 4 janvier. C'est le défaut qui a inversé sept dates en base.
     */
    private function date(mixed $valeur): ?Carbon
    {
        if (!is_string($valeur) || trim($valeur) === '') {
            return null;
        }

        $valeur = trim($valeur);

        try {
            if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $valeur)) {
                return Carbon::createFromFormat('d/m/Y', $valeur);
            }

            if (preg_match('#^(\d{4}-\d{2}-\d{2})#', $valeur, $p)) {
                return Carbon::createFromFormat('Y-m-d', $p[1]);
            }
        } catch (\Exception) {
            // Saisie illisible : ce n'est pas une incohérence entre dates, et l'inventer ici
            // masquerait le vrai problème. On laisse passer plutôt que de deviner.
        }

        return null;
    }
}
