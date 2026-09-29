<?php

namespace App\Services;

use App\Enums\MotifRepresentation;
use App\Models\Dossier;
use App\Models\Partie;

/**
 * Ce qu'une représentation déclarée doit comporter pour qu'un acte puisse la viser.
 *
 * Service distinct de `ReglesSocieteService`, qui renvoie `[]` dès que le dossier n'est pas de
 * catégorie Société : la représentation porte sur **tout** type d'acte — un vendeur, un
 * bailleur ou un constituant d'hypothèque se font représenter aussi couramment qu'un associé.
 * L'y loger l'aurait rendue muette partout ailleurs.
 *
 * Les **pièces** n'ont besoin d'aucun contrôle ici : `erreursDeConstitution()` parcourt déjà
 * toutes les parties du dossier, et le représentant **est** une partie. Sa CNI et la procuration
 * du mandant entrent donc dans le blocage existant dès que `piecesRequisesDefinition()` les
 * renvoie.
 */
class ReglesRepresentationService
{
    /** @return array<string, string[]> clé d'erreur → messages */
    public function anomalies(Dossier $dossier): array
    {
        $dossier->loadMissing('parties.representant');

        $sansRepresentant = [];
        $sansMotif        = [];
        $titreIncomplet   = [];
        $chaines          = [];

        foreach ($dossier->parties as $partie) {
            $motif = $partie->representation_motif;
            $lien  = $partie->represente_par_partie_id;

            // Le mandataire a été supprimé : `nullOnDelete` a fait son office et le dossier est
            // dans un état volontairement **bruyant** plutôt que silencieusement amputé. Sans ce
            // contrôle, l'acte imprimerait « ici représenté par » suivi de rien.
            if ($motif !== null && $lien === null) {
                $sansRepresentant[] = $partie->nom;
                continue;
            }

            if ($motif === null && $lien !== null) {
                // Impossible par le formulaire — la validation l'exige. Mais la conversion d'une
                // demande externe, un brouillon antérieur et un PATCH direct écrivent aussi :
                // une règle ne peut pas ne vivre que dans le formulaire.
                $sansMotif[] = $partie->nom;
                continue;
            }

            if ($motif === null) {
                continue;
            }

            if ($motif->exigeTitreDate() && $partie->representation_titre_date === null) {
                $titreIncomplet[] = $partie->nom;
            }

            // Substitution de mandat : hors périmètre arbitré, et refusée explicitement plutôt
            // que laissée produire une comparution récursive dans un acte authentique.
            if ($partie->representant?->representation_motif !== null) {
                $chaines[] = $partie->nom;
            }
        }

        $erreurs = [];

        if ($sansRepresentant) {
            $erreurs['representation_sans_representant'] = [sprintf(
                'Représentation déclarée sans représentant désigné : %s. Désignez la personne qui '
                . 'comparaîtra, ou retirez la représentation.',
                implode(', ', $sansRepresentant),
            )];
        }

        if ($sansMotif) {
            $erreurs['representation_sans_motif'] = [sprintf(
                'Représentant désigné sans motif : %s. Le motif détermine les pièces à fournir et '
                . "la clause d'habilitation de l'acte.",
                implode(', ', $sansMotif),
            )];
        }

        if ($titreIncomplet) {
            $erreurs['representation_titre'] = [sprintf(
                'Procuration sans date : %s. L\'acte la vise « en date du… » — une procuration non '
                . 'datée n\'est pas visable.',
                implode(', ', $titreIncomplet),
            )];
        }

        if ($chaines) {
            $erreurs['representation_chaine'] = [sprintf(
                'Le représentant est lui-même représenté : %s. Une substitution de mandat n\'est pas '
                . 'gérée — désignez une personne qui comparaît en personne.',
                implode(', ', $chaines),
            )];
        }

        return $erreurs;
    }
}
