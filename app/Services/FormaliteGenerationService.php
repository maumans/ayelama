<?php

namespace App\Services;

use App\Models\Dossier;
use App\Models\Formalite;
use App\Models\FormalitePiece;

/**
 * Génère automatiquement les formalités administratives d'un dossier à partir des
 * Bareme marqués `genere_formalite` pour son TypeActe, afin d'éviter la saisie
 * manuelle une par une des démarches déjà connues à l'avance.
 *
 * Un même Bareme sert donc à la fois à la facturation (FacturationService) et,
 * optionnellement, à la génération de la Formalite correspondante — les deux
 * mécanismes de calcul restent distincts (conventions de taux différentes,
 * voir Bareme::calculerMontant() vs Formalite::calculerMontant()).
 */
class FormaliteGenerationService
{
    public function genererFormalites(Dossier $dossier): void
    {
        $dossier->loadMissing('typeActe.baremes');

        $baremes = $dossier->typeActe->baremes()->actif()->genereFormalite()->get();

        // Passe 1 : créer/mettre à jour chaque Formalite, indexée par bareme_id afin
        // de pouvoir résoudre les dépendances (bareme->depend_de_bareme_id) ensuite.
        $formaliteIdParBaremeId = [];

        foreach ($baremes as $bareme) {
            $montantBase = $bareme->base_calcul === 'montant_fixe'
                ? ($bareme->montant_fixe !== null ? (float) $bareme->montant_fixe : null)
                : ($dossier->valeur !== null ? (float) $dossier->valeur : null);

            $formalite = Formalite::updateOrCreate(
                ['dossier_id' => $dossier->id, 'bareme_id' => $bareme->id],
                [
                    'organisme'      => $bareme->organismeFormalite(),
                    'libelle'        => $bareme->libelle,
                    'ordre'          => $bareme->ordre,
                    'statut'         => 'a_deposer',
                    // Bareme::taux est en pourcentage (0-100), Formalite::calculerMontant()
                    // attend une fraction (0-1) — conversion faite ici, jamais visible à l'admin.
                    'taux'           => $bareme->base_calcul === 'valeur_acte' && $bareme->taux !== null
                        ? (float) $bareme->taux / 100
                        : null,
                    'montant_base'   => $montantBase,
                    'type_impot'     => $bareme->type_impot,
                    'retour_attendu' => $bareme->retour_attendu,
                    'delai_heures'   => $bareme->delai_heures,
                    'echeance_at'    => $bareme->delai_heures ? now()->addHours($bareme->delai_heures) : null,
                ]
            );

            if ($bareme->base_calcul === 'montant_fixe') {
                // Pas de taux à appliquer : le montant calculé est le montant fixe lui-même.
                $formalite->update(['montant_calcule' => $montantBase]);
            } elseif ($formalite->taux && $formalite->montant_base) {
                $formalite->calculerMontant();
            }

            if ($formalite->wasRecentlyCreated) {
                foreach ($bareme->pieces_requises ?? [] as $label) {
                    FormalitePiece::create([
                        'formalite_id' => $formalite->id,
                        'label'        => $label,
                        'est_fourni'   => false,
                    ]);
                }
            }

            $formaliteIdParBaremeId[$bareme->id] = $formalite->id;
        }

        // Passe 2 : résoudre les dépendances entre démarches du même dossier.
        foreach ($baremes as $bareme) {
            if (!$bareme->depend_de_bareme_id) {
                continue;
            }

            $formaliteId = $formaliteIdParBaremeId[$bareme->id] ?? null;
            $dependFormaliteId = $formaliteIdParBaremeId[$bareme->depend_de_bareme_id] ?? null;

            if ($formaliteId && $dependFormaliteId) {
                Formalite::whereKey($formaliteId)->update(['depend_de_formalite_id' => $dependFormaliteId]);
            }
        }
    }
}
