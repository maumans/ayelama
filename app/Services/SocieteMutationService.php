<?php

namespace App\Services;

use App\Enums\TypeModificationStatutaire;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\Societe;
use App\Models\User;

/**
 * Applique à la fiche du registre les modifications qu'un dossier `SOC-MOD` a décidées.
 *
 * Sans ce service, le registre se figerait sur l'état de constitution : un dossier de
 * transfert de siège aboutirait, les actes seraient signés et enregistrés, et la fiche
 * continuerait d'afficher l'ancien siège — donc le dossier de modification **suivant** se
 * préremplirait avec une adresse périmée. C'est précisément la régression que le registre
 * était censé éviter.
 *
 * **Quand** : à l'entrée du dossier en étape **Expédition** (voir
 * {@see DossierStepService::avancer()}). Ni avant ni après, et le choix se défend :
 *   - avant les Formalités, la modification n'est pas opposable — inscrire au registre un
 *     changement non enregistré au RCCM serait faux ;
 *   - sortir des Formalités signifie que tous les retours sont reçus, donc que le greffe et
 *     la DGI ont enregistré ;
 *   - à la Clôture, ce serait trop tard : le dossier y est figé, et un dossier peut rester
 *     des semaines en Expédition pendant lesquelles la fiche mentirait.
 *
 * **Traçabilité** : un `JournalActivite` par application, détaillant chaque champ modifié.
 * Une fiche de référence notariale qui change en silence est inacceptable — l'équipe doit
 * pouvoir constater, depuis le dossier, ce qui a été porté au registre.
 */
class SocieteMutationService
{
    /**
     * @return array<string, array{avant: mixed, apres: mixed}> Champs effectivement modifiés
     */
    public function appliquer(Dossier $dossier, ?User $user = null): array
    {
        $dossier->loadMissing('typeActe', 'questionnaire', 'societe');

        if ($dossier->typeActe?->code !== 'SOC-MOD' || !$dossier->societe) {
            return [];
        }

        $donnees = $dossier->questionnaire?->donnees ?? [];
        $types   = TypeModificationStatutaire::depuisLibelles(
            $donnees['modif.types'] ?? $donnees['modif.type'] ?? null,
        );

        if ($types === []) {
            return [];
        }

        $societe = $dossier->societe;

        [$nouveau, $colonnesEnConflit] = $this->fusionnerChangements($types, $donnees, $societe);

        // Ne retenir que les champs dont la valeur change réellement : sans ce filtre, le
        // journal annoncerait des modifications là où le questionnaire ne fait que recopier
        // l'état existant (cas fréquent — les champs `soc.*` sont projetés depuis la fiche).
        $modifications = [];
        foreach ($nouveau as $colonne => $valeur) {
            if (blank($valeur)) {
                continue;
            }
            $avant = $societe->{$colonne};
            if ($this->identiques($avant, $valeur)) {
                continue;
            }
            $modifications[$colonne] = ['avant' => $avant, 'apres' => $valeur];
        }

        // Le refus est journalisé même si rien d'autre ne change : une colonne écartée doit laisser
        // une trace, sinon l'équipe croirait la fiche à jour.
        if ($colonnesEnConflit !== []) {
            $this->journaliserRefus($dossier, $societe, $colonnesEnConflit, $user);
        }

        if ($modifications === []) {
            return [];
        }

        $societe->update([
            ...array_map(fn (array $d) => $d['apres'], $modifications),
            'derniere_modification_at' => now(),
        ]);

        JournalActivite::enregistrer(
            $dossier,
            sprintf(
                'Fiche société « %s » mise à jour au registre : %s',
                $societe->denomination,
                implode(', ', array_map(
                    fn (string $c) => self::LIBELLES_CHAMPS[$c] ?? $c,
                    array_keys($modifications),
                )),
            ),
            'modification',
            ['societe_id' => $societe->id, 'champs' => $modifications],
            $user,
        );

        return $modifications;
    }

    /**
     * Fusionne les changements apportés par chaque type, en **détectant les collisions** plutôt
     * qu'en laissant le dernier écraser le précédent.
     *
     * Défense en profondeur : `ReglesSocieteService` refuse déjà d'avancer un dossier portant deux
     * modifications incompatibles, et l'assistant grise les cases exclues. Mais un dossier ouvert
     * avant ces garde-fous peut déjà être en cours, et la version précédente de cette méthode
     * retenait alors « la dernière valeur fusionnée » — c'est-à-dire que **l'ordre de déclaration
     * de l'enum décidait du capital de la société**, en silence.
     *
     * Une colonne réclamée avec deux valeurs différentes est **écartée**, pas arbitrée. Les autres
     * s'appliquent normalement : un conflit sur le capital ne doit pas empêcher d'enregistrer le
     * transfert de siège décidé par la même assemblée.
     *
     * Deux types proposant la **même** valeur ne sont pas en conflit — cas des deux changements de
     * gérant, qui écrivent le même bloc `direction`.
     *
     * @param  array<int, TypeModificationStatutaire> $types
     * @return array{0: array<string, mixed>, 1: array<int, string>} [valeurs retenues, colonnes écartées]
     */
    private function fusionnerChangements(array $types, array $donnees, Societe $societe): array
    {
        $retenues = [];
        $conflits = [];

        foreach ($types as $type) {
            foreach ($this->changementsPour($type, $donnees, $societe) as $colonne => $valeur) {
                if (blank($valeur)) {
                    continue;
                }

                if (array_key_exists($colonne, $retenues) && !$this->identiques($retenues[$colonne], $valeur)) {
                    $conflits[$colonne] = true;
                    continue;
                }

                $retenues[$colonne] = $valeur;
            }
        }

        // Une colonne en conflit ne doit surtout pas garder la première valeur rencontrée : ce
        // serait le même arbitraire, déplacé du dernier vers le premier.
        foreach (array_keys($conflits) as $colonne) {
            unset($retenues[$colonne]);
        }

        return [$retenues, array_keys($conflits)];
    }

    /**
     * Trace le refus d'appliquer une colonne — une fiche de référence notariale qui reste inchangée
     * sans explication est aussi problématique qu'une fiche modifiée en silence.
     *
     * @param array<int, string> $colonnes
     */
    private function journaliserRefus(Dossier $dossier, Societe $societe, array $colonnes, ?User $user): void
    {
        JournalActivite::enregistrer(
            $dossier,
            sprintf(
                'Fiche société « %s » — %s non porté(s) au registre : deux modifications décidées en donnent des valeurs contradictoires. À arbitrer avant clôture.',
                $societe->denomination,
                implode(', ', array_map(fn (string $c) => self::LIBELLES_CHAMPS[$c] ?? $c, $colonnes)),
            ),
            'modification',
            ['societe_id' => $societe->id, 'champs_ecartes' => $colonnes],
            $user,
        );
    }

    /** Libellés métier des colonnes, pour un journal lisible par l'équipe et non par un développeur. */
    private const LIBELLES_CHAMPS = [
        'siege_quartier'           => 'quartier du siège',
        'siege_commune'            => 'commune du siège',
        'siege_ville'              => 'ville du siège',
        'capital_chiffres'         => 'capital social',
        'nombre_parts'             => 'nombre de parts',
        'valeur_nominale_chiffres' => 'valeur nominale',
        'objet_social'             => 'objet social',
        'direction'                => 'gérance',
    ];

    /**
     * Nouvelles valeurs de fiche apportées par un type de modification.
     *
     * Un `match` exhaustif : ajouter un cas à l'enum sans décider de son effet sur le registre
     * fera échouer PHP, plutôt que de laisser la fiche se désynchroniser en silence.
     *
     * @return array<string, mixed>
     */
    private function changementsPour(TypeModificationStatutaire $type, array $donnees, Societe $societe): array
    {
        return match ($type) {
            TypeModificationStatutaire::SiegeSocial => [
                'siege_quartier' => $donnees['modif.siege_nouveau_quartier'] ?? null,
                'siege_commune'  => $donnees['modif.siege_nouveau_commune'] ?? null,
                'siege_ville'    => $donnees['modif.siege_nouveau_ville'] ?? null,
            ],

            TypeModificationStatutaire::CapitalAugmentation => [
                'capital_chiffres' => $donnees['modif.augmentation_capital_apres'] ?? null,
                'nombre_parts'     => $this->partsApres(
                    $societe->nombre_parts,
                    $donnees['modif.augmentation_parts_nouvelles'] ?? null,
                    1,
                ),
            ],

            TypeModificationStatutaire::CapitalDiminution => [
                'capital_chiffres' => $donnees['modif.diminution_capital_apres'] ?? null,
                'nombre_parts'     => $this->partsApres(
                    $societe->nombre_parts,
                    $donnees['modif.diminution_parts_annulees'] ?? null,
                    -1,
                ),
            ],

            TypeModificationStatutaire::ObjetSocial => [
                'objet_social' => $donnees['modif.objet_nouveau'] ?? null,
            ],

            // Statutaire ou non, le gérant change au registre : la distinction porte sur les
            // statuts (impacteStatuts()), pas sur l'identité du dirigeant en exercice.
            TypeModificationStatutaire::GerantStatutaire,
            TypeModificationStatutaire::GerantNonStatutaire => [
                'direction' => $this->direction($donnees, $societe),
            ],

            // Une cession de parts change les **associés**, pas les caractéristiques de la
            // société : ni son capital, ni son siège, ni son objet. La nouvelle répartition
            // vit dans le questionnaire du dossier et dans les statuts mis à jour ; le
            // registre ne tient pas de table des associés, et lui en inventer une ici
            // dupliquerait ce que `Partie` porte déjà.
            TypeModificationStatutaire::CapitalCession => [],
        };
    }

    /**
     * Nombre de parts après émission ou annulation.
     *
     * `null` si l'une des deux valeurs manque : le nombre de parts est facultatif au
     * questionnaire, et écrire un total faux serait pire que ne rien écrire.
     */
    private function partsApres(?int $actuelles, mixed $delta, int $signe): ?int
    {
        if ($actuelles === null || !is_numeric($delta)) {
            return null;
        }

        $apres = $actuelles + $signe * (int) $delta;

        return $apres > 0 ? $apres : null;
    }

    /**
     * Bloc `direction` (JSON) après changement de gérant, en conservant les clés déjà
     * présentes — la fiche peut porter d'autres dirigeants (président, DG) qu'un changement
     * de gérance ne concerne pas.
     */
    private function direction(array $donnees, Societe $societe): ?array
    {
        $nom = $donnees['gerant_entrant.prenom_nom'] ?? null;

        if (blank($nom)) {
            return null;
        }

        return [
            ...($societe->direction ?? []),
            'gerant'          => $nom,
            'gerant_civilite' => $donnees['gerant_entrant.civilite'] ?? null,
            'gerant_mandat'   => $donnees['gerant_entrant.duree_mandat'] ?? null,
        ];
    }

    /**
     * Comparaison tolérante aux types : `capital_chiffres` est casté en `decimal:2` et revient
     * en « 50000000.00 », alors que le questionnaire fournit la chaîne « 50000000 ». Sans
     * cela, chaque passage en Expédition signalerait une modification du capital.
     */
    private function identiques(mixed $avant, mixed $apres): bool
    {
        if (is_array($avant) || is_array($apres)) {
            return $avant == $apres;
        }

        if (is_numeric($avant) && is_numeric($apres)) {
            return (float) $avant === (float) $apres;
        }

        return trim((string) $avant) === trim((string) $apres);
    }
}
