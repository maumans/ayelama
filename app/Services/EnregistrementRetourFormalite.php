<?php

namespace App\Services;

use App\Enums\DonneeAuRetour;
use App\Models\Formalite;
use App\Models\JournalActivite;
use App\Models\Societe;
use App\Models\User;

/**
 * Porte au registre les données qu'une autorité a délivrées au retour d'une formalité.
 *
 * **Pourquoi dès le retour, et non à l'Expédition comme les autres écritures de fiche.** Un
 * numéro RCCM est un **fait** : il est vrai dès que le greffe le délivre. Une modification
 * statutaire, elle, n'est portée au registre qu'à l'Expédition parce qu'elle doit d'abord
 * devenir *opposable* — voir {@see SocieteMutationService}. Les deux moments diffèrent parce
 * que les deux natures diffèrent, pas par inconséquence.
 *
 * **Trois branches, et la troisième est celle qui compte.** « Ne jamais écraser » suffit pour
 * remplir une fiche vide, mais rendrait une faute de frappe **définitive** : le formaliste
 * saisit un numéro erroné, le corrige, et la fiche garderait le premier pour toujours. Une
 * valeur divergente n'est donc ni écrasée ni ignorée — elle est **signalée**, et l'arbitrage se
 * fait depuis la fiche du registre.
 *
 * C'est aussi ce qui rend un double enregistrement inoffensif : la seconde passe voit des
 * valeurs identiques et n'écrit rien.
 */
class EnregistrementRetourFormalite
{
    /**
     * @param  array<string, mixed> $valeurs  clés = valeurs de {@see DonneeAuRetour}
     * @return array{appliquees: array<string, mixed>, divergentes: array<string, array{fiche: mixed, recue: mixed}>, sansDestination: array<int, string>}
     */
    public function appliquer(Formalite $formalite, array $valeurs, ?User $user = null): array
    {
        $formalite->loadMissing('dossier.societe');
        $societe = $formalite->dossier?->societe;

        $appliquees      = [];
        $divergentes     = [];
        $sansDestination = [];

        foreach (DonneeAuRetour::depuis(array_keys($valeurs)) as $donnee) {
            $valeur = $valeurs[$donnee->value] ?? null;

            if (blank($valeur)) {
                continue;
            }

            $colonne = $donnee->colonne();

            // Donnée de traçabilité pure (quittance, dépôt au greffe, JAL) : elle reste sur la
            // formalité, où elle est consultable et reprise par une balise. Ce n'est pas un
            // trou — voir DonneeAuRetour::colonne().
            if ($colonne === null) {
                $sansDestination[] = $donnee->value;
                continue;
            }

            // Une vente ou une hypothèque n'a pas de fiche société (4 formalités sur 53
            // mesurées) : la donnée est captée, elle n'a simplement nulle part où aller.
            if (! $societe) {
                $sansDestination[] = $donnee->value;
                continue;
            }

            // ⚠️ **Seule l'immatriculation délivre un numéro RCCM.** Une modification ou une
            // dissolution rend une *déclaration modificative*, qui a son propre cas
            // ({@see DonneeAuRetour::DeclarationModificativeNumero}) et, délibérément, aucune
            // destination — précisément pour ne pas écraser l'identité légale de la société.
            //
            // Le garde-fou n'est pas théorique : mesuré le 2026-09-30, les barèmes
            // « Immatriculation RCCM » et « Obtention NIF » existaient **aussi** sur `SOC-MOD`
            // et `SOC-DIS` — la migration qui a semé `donnees_au_retour` les a servis par
            // libellé — et y déclaraient `rccm_numero` / `rccm_date`. Sans effet tant qu'ils
            // étaient inactifs ; le jour où l'étude en activait un, le numéro rendu pour une
            // modification entrait dans `societes.rccm_numero` d'une fiche encore vide.
            // La migration `2026_09_30_100000` les a nettoyés, celle-ci empêche la rechute.
            //
            // Le critère est **la donnée, pas une liste de codes** : la fiche porte-t-elle ce
            // dossier comme dossier constitutif ? `societes.dossier_id` ne vaut `$dossier->id`
            // que pour la constitution qui a créé la fiche
            // ({@see DossierController::enregistrerAuRegistreDesSocietes}). Un `match` sur des
            // codes de type d'acte aurait fallu maintenir à chaque forme ajoutée — or les
            // types d'acte sont des **données**, créables en exécution.
            if ($this->reserveALimmatriculation($donnee) && ! $this->estLeDossierConstitutif($formalite, $societe)) {
                $sansDestination[] = $donnee->value;
                continue;
            }

            $existante = $societe->{$colonne};

            if (blank($existante)) {
                $appliquees[$colonne] = $valeur;
                continue;
            }

            if ($this->identiques($existante, $valeur, $donnee)) {
                continue;
            }

            $divergentes[$donnee->value] = [
                'fiche' => $this->enChaine($existante),
                'recue' => $this->enChaine($valeur),
            ];
        }

        if ($appliquees !== [] && $societe) {
            $societe->update($appliquees);
            $this->comblerQuestionnairesOuverts($societe->fresh(), array_keys($appliquees), $user);
        }

        $this->journaliser($formalite, $societe, $appliquees, $divergentes, $user);
        $this->signalerActesAnterieurs($formalite, [...array_keys($appliquees), ...$sansDestination], $user);

        return [
            'appliquees'      => $appliquees,
            'divergentes'     => $divergentes,
            'sansDestination' => $sansDestination,
        ];
    }

    /**
     * Données que seule l'immatriculation d'origine délivre.
     *
     * `nif` n'en est pas : l'administration fiscale peut en attribuer un après coup à une
     * société déjà immatriculée, et rien dans les mesures ne dit le contraire. Une contrainte
     * inventée est pire qu'une contrainte absente.
     */
    private function reserveALimmatriculation(DonneeAuRetour $donnee): bool
    {
        return in_array($donnee, [DonneeAuRetour::RccmNumero, DonneeAuRetour::RccmDate], true);
    }

    /** Cette formalité relève-t-elle du dossier qui a fait naître la fiche ? */
    private function estLeDossierConstitutif(Formalite $formalite, Societe $societe): bool
    {
        return $societe->dossier_id !== null
            && $societe->dossier_id === $formalite->dossier_id;
    }

    /**
     * Porte la donnée nouvellement connue dans les questionnaires **déjà ouverts** de la société.
     *
     * Sans cela la boucle restait ouverte d'un cran : le numéro entrait bien au registre, mais un
     * dossier de modification ouvert la veille continuait de le réclamer — sa projection est
     * figée au rattachement, et rien ne la rejoue.
     *
     * ⚠️ **On comble, on ne corrige jamais.** Le devbook pose comme décision assumée que corriger
     * une fiche société ne reprojette rien : réaligner en silence contournerait le circuit du PV,
     * de la certification et des formalités. Cette décision reste entière — une clé **déjà
     * renseignée** n'est pas touchée, même divergente. Remplir un trou n'est pas corriger : la
     * clé vide ne portait aucune vérité concurrente.
     *
     * Ce sont les trois branches de la capture au retour, appliquées dans l'autre sens :
     * vide → écrire · identique → ne rien faire · **différent → laisser tel quel**.
     *
     * ⚠️ **Seules les colonnes que ce retour vient d'écrire voyagent.** Projeter toute la fiche
     * à cette occasion aurait rempli des clés sans rapport : mesuré sur la base réelle, un
     * retour de NIF aurait comblé `soc.objet_social`, `soc.duree` et neuf autres dans les
     * dossiers ouverts — correct sur le fond, mais impossible à relier à l'événement pour qui
     * lit le journal six mois plus tard. Un effet doit se déduire de sa cause.
     *
     * Un dossier **clôturé** n'est jamais touché : son questionnaire est l'instantané qui a servi
     * à produire des actes archivés.
     *
     * Premier appelant de production de {@see Societe::versQuestionnaire()} — la méthode
     * existait, testée et miroir de `resources/js/lib/societeFields.js`, sans aucun usage : en
     * écrire une seconde aurait créé la divergence que le miroir existe pour éviter.
     *
     * @param array<int, string> $colonnes colonnes que ce retour vient de porter à la fiche
     */
    private function comblerQuestionnairesOuverts(Societe $societe, array $colonnes, ?User $user): void
    {
        $projection = $societe->versQuestionnairePourColonnes($colonnes);

        if ($projection === []) {
            return;
        }

        $dossiers = $societe->dossiers()->enCours()->with('questionnaire')->get();

        foreach ($dossiers as $dossier) {
            $questionnaire = $dossier->questionnaire;

            if (! $questionnaire) {
                continue;
            }

            $donnees = $questionnaire->donnees ?? [];
            $combles = [];

            foreach ($projection as $cle => $valeur) {
                // Clé absente et clé vide sont le même cas : le questionnaire ne dit rien.
                // `blank()` couvre les deux, plus la chaîne d'espaces.
                if (! blank($donnees[$cle] ?? null)) {
                    continue;
                }

                $donnees[$cle] = $valeur;
                $combles[$cle] = $valeur;
            }

            if ($combles === []) {
                continue;
            }

            $questionnaire->update(['donnees' => $donnees]);

            JournalActivite::enregistrer(
                $dossier,
                sprintf(
                    'Complété depuis le registre après le retour d\'une formalité : %s. '
                    . 'Les valeurs déjà saisies n\'ont pas été modifiées.',
                    implode(', ', array_keys($combles)),
                ),
                'societe',
                ['societe_id' => $societe->id, 'champs' => $combles],
                $user,
            );
        }
    }

    /**
     * Prévient que les actes déjà produits ne portent pas ce qui vient d'arriver.
     *
     * ⚠️ **Le décalage est structurel, pas accidentel** : les actes sont générés à l'entrée en
     * **Édition**, la formalité revient à l'étape **Formalités**, deux étapes plus tard. Une
     * insertion au journal produite en Édition ne peut donc pas porter un numéro RCCM qui
     * n'existait pas encore.
     *
     * On **signale**, on ne régénère pas : `regenererDocument()` écrase un acte corrigé à la
     * main et périme la certification. Un blocage énuméré plutôt qu'une action silencieuse —
     * le bouton « Régénérer » existe déjà, c'est au rédacteur de décider.
     *
     * @param array<int, string> $captees
     */
    private function signalerActesAnterieurs(Formalite $formalite, array $captees, ?User $user): void
    {
        if ($captees === []) {
            return;
        }

        $formalite->loadMissing('dossier.documents');

        $anterieurs = $formalite->dossier?->documents
            ->where('categorie', '!=', 'accord_client')
            ->count() ?? 0;

        if ($anterieurs === 0) {
            return;
        }

        JournalActivite::enregistrer(
            $formalite->dossier,
            sprintf(
                'Les %d acte(s) déjà produits ne portent pas les informations reçues au retour de « %s » '
                . '— régénérez-les si un modèle les cite.',
                $anterieurs,
                $formalite->labelAffiche(),
            ),
            'formalite',
            ['formalite_id' => $formalite->id, 'donnees' => $captees],
            $user,
        );
    }

    /**
     * Comparaison tolérante aux types.
     *
     * Une date castée rend un `Carbon`, la valeur reçue une chaîne ISO : les comparer
     * directement ferait diverger une fiche identique à elle-même, et l'écran crierait au
     * conflit à chaque ré-enregistrement.
     */
    private function identiques(mixed $existante, mixed $recue, DonneeAuRetour $donnee): bool
    {
        if ($donnee->type() === 'date') {
            return $this->enChaine($existante) === $this->enChaine($recue);
        }

        return mb_strtolower(trim((string) $existante)) === mb_strtolower(trim((string) $recue));
    }

    /** Forme comparable et affichable — ISO pour une date, la chaîne nue sinon. */
    private function enChaine(mixed $valeur): string
    {
        if ($valeur instanceof \DateTimeInterface) {
            return $valeur->format('Y-m-d');
        }

        $texte = trim((string) $valeur);

        // Une date reçue du formulaire arrive en ISO ; on la normalise au cas où elle porterait
        // une heure.
        return preg_match('/^\d{4}-\d{2}-\d{2}/', $texte) ? substr($texte, 0, 10) : $texte;
    }

    /**
     * @param  array<string, mixed> $appliquees
     * @param  array<string, array> $divergentes
     */
    private function journaliser(
        Formalite $formalite,
        ?Societe $societe,
        array $appliquees,
        array $divergentes,
        ?User $user,
    ): void {
        if ($appliquees === [] && $divergentes === []) {
            return;
        }

        // `societe_id` dans le méta : la chaîne d'accès est `formalite → dossier → societe`, et
        // `dossiers.societe_id` est modifiable. Sans cette trace, on ne saurait pas dans six
        // mois quelle fiche a été touchée.
        $meta = ['societe_id' => $societe?->id, 'formalite_id' => $formalite->id];

        if ($appliquees !== []) {
            JournalActivite::enregistrer(
                $formalite->dossier,
                sprintf(
                    'Retour de « %s » : %s porté(s) à la fiche société « %s »',
                    $formalite->labelAffiche(),
                    implode(', ', array_keys($appliquees)),
                    $societe?->denomination ?? '—',
                ),
                'societe',
                [...$meta, 'champs' => $appliquees],
                $user,
            );
        }

        foreach ($divergentes as $cle => $ecart) {
            JournalActivite::enregistrer(
                $formalite->dossier,
                sprintf(
                    '⚠️ Valeur divergente au retour de « %s » : la fiche porte « %s », le retour indique « %s ». '
                    . 'La fiche n\'a pas été modifiée — arbitrez depuis le registre des sociétés.',
                    $formalite->labelAffiche(),
                    $ecart['fiche'],
                    $ecart['recue'],
                ),
                'societe',
                [...$meta, 'donnee' => $cle, 'ecart' => $ecart],
                $user,
            );
        }
    }
}
