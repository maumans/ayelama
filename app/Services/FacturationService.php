<?php

namespace App\Services;

use App\Models\Bareme;
use App\Models\Dossier;
use App\Models\Facture;
use App\Models\LigneFacture;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service de facturation automatique.
 *
 * Calcule et génère les lignes de facture d'un dossier en se basant
 * sur les barèmes paramétrés en base de données (table `baremes`).
 *
 * Principe :
 * - Chaque type d'acte possède N barèmes (taux % ou montant fixe).
 * - La "valeur de l'acte" (assiette) dépend de la catégorie :
 *     • Vente   → prix de vente
 *     • Bail    → total des loyers sur la durée
 *     • Société → capital social
 *     • Hypothèque → montant du crédit
 * - Cette assiette est passée en paramètre ou extraite du questionnaire.
 */
class FacturationService
{
    /**
     * Génère (ou regénère) la facture d'un dossier.
     *
     * @param Dossier    $dossier      Le dossier concerné
     * @param float|null $assiette     La valeur de base pour le calcul (prix, capital, loyers…)
     * @param string     $objet        L'objet de la facture
     * @return Facture   La facture créée avec ses lignes
     */
    public function genererFacture(Dossier $dossier, ?float $assiette = null, string $objet = ''): Facture
    {
        // La régénération supprime la facture existante (voir plus bas) — refusée dès
        // qu'un paiement a déjà été enregistré dessus, pour ne jamais perdre
        // l'historique des encaissements du dossier.
        if ($dossier->factures()->whereHas('paiements')->exists()) {
            throw ValidationException::withMessages([
                'facture' => ["Impossible de régénérer la facture : des paiements ont déjà été enregistrés sur ce dossier."],
            ]);
        }

        // Essayer de déduire l'assiette du questionnaire si non fournie
        if ($assiette === null) {
            $assiette = $this->deduireAssiette($dossier);
        }

        // Récupérer les barèmes actifs pour ce type d'acte
        $baremes = Bareme::where('type_acte_id', $dossier->type_acte_id)
            ->where('actif', true)
            ->orderBy('ordre')
            ->get();

        if ($baremes->isEmpty()) {
            // Pas de barème configuré : on crée une facture vide
            return $this->creerFactureVide($dossier, $assiette, $objet);
        }

        return DB::transaction(function () use ($dossier, $baremes, $assiette, $objet) {
            // Supprimer l'ancienne facture si elle existe (regénération)
            $dossier->factures()->delete();

            // Créer la facture
            $facture = Facture::create([
                'dossier_id'       => $dossier->id,
                'note_numero'      => Facture::genererNumero(),
                'note_date'        => now(),
                'objet'            => $objet ?: $dossier->objet,
                'assiette_chiffres' => $assiette ?? 0,
                'total_chiffres'   => 0,
            ]);

            // Calculer et créer chaque ligne
            $total = 0;

            foreach ($baremes as $bareme) {
                $montant = $bareme->calculerMontant((float) ($assiette ?? 0));

                if ($montant <= 0) {
                    continue;
                }

                LigneFacture::create([
                    'facture_id'  => $facture->id,
                    'designation' => $this->formaterDesignation($bareme, $assiette),
                    'quantite'    => 1,
                    'montant'     => $montant,
                ]);

                $total += $montant;
            }

            // Mettre à jour le total
            $facture->update(['total_chiffres' => $total]);

            return $facture->load('lignes');
        });
    }

    /**
     * Simule la facturation (preview) sans rien persister.
     * Utile pour afficher un aperçu au client avant validation.
     *
     * @return array{lignes: array, total: float, assiette: float}
     */
    public function simuler(int $typeActeId, float $assiette): array
    {
        $baremes = Bareme::where('type_acte_id', $typeActeId)
            ->where('actif', true)
            ->orderBy('ordre')
            ->get();

        $lignes = [];
        $total  = 0;

        foreach ($baremes as $bareme) {
            $montant = $bareme->calculerMontant($assiette);

            if ($montant <= 0) {
                continue;
            }

            $lignes[] = [
                'organisme'   => $bareme->organisme,
                'libelle'     => $bareme->libelle,
                'designation' => $this->formaterDesignation($bareme, $assiette),
                'taux'        => $bareme->taux,
                'montant_fixe' => $bareme->montant_fixe,
                'base_calcul' => $bareme->base_calcul,
                'montant'     => $montant,
            ];

            $total += $montant;
        }

        return [
            'lignes'   => $lignes,
            'total'    => $total,
            'assiette' => $assiette,
        ];
    }

    /**
     * Tente de déduire l'assiette (valeur de base) depuis le questionnaire du dossier.
     */
    private function deduireAssiette(Dossier $dossier): ?float
    {
        $donnees = $dossier->questionnaire?->donnees ?? [];

        // Chercher dans les clés connues du questionnaire
        $champsAssiette = [
            'prix',                    // Vente
            'prix_vente',              // Vente variante
            'capital_chiffres',        // Société
            'capital',                 // Société variante
            'montant_credit_chiffres', // Hypothèque
            'loyer_total',             // Bail
        ];

        foreach ($champsAssiette as $champ) {
            if (isset($donnees[$champ]) && is_numeric($donnees[$champ])) {
                return (float) $donnees[$champ];
            }
        }

        // Pour les baux, tenter de calculer loyer × durée
        if (isset($donnees['loyer_chiffres'], $donnees['duree_bail'])) {
            $loyer = (float) $donnees['loyer_chiffres'];
            $duree = (int)   $donnees['duree_bail'];
            if ($loyer > 0 && $duree > 0) {
                return $loyer * 12 * $duree; // total des loyers mensuels sur la durée
            }
        }

        // Utiliser la valeur du dossier si disponible
        if ($dossier->valeur) {
            return (float) $dossier->valeur;
        }

        return null;
    }

    /**
     * Formate la désignation d'une ligne de facture
     * en incluant le taux ou le montant fixe pour plus de clarté.
     */
    private function formaterDesignation(Bareme $bareme, ?float $assiette): string
    {
        if ($bareme->base_calcul === 'montant_fixe') {
            return $bareme->libelle;
        }

        $tauxStr = rtrim(rtrim(number_format((float) $bareme->taux, 4, ',', ''), '0'), ',');

        if ($assiette && $assiette > 0) {
            $assietteFormatee = number_format($assiette, 0, ',', ' ');
            return "{$bareme->libelle} ({$tauxStr}% de {$assietteFormatee} GNF)";
        }

        return "{$bareme->libelle} ({$tauxStr}%)";
    }

    /**
     * Crée une facture vide (pas de barème trouvé, mais on trace l'existence).
     */
    private function creerFactureVide(Dossier $dossier, ?float $assiette, string $objet): Facture
    {
        return Facture::create([
            'dossier_id'        => $dossier->id,
            'note_numero'       => Facture::genererNumero(),
            'note_date'         => now(),
            'objet'             => $objet ?: $dossier->objet,
            'assiette_chiffres' => $assiette ?? 0,
            'total_chiffres'    => 0,
        ]);
    }
}
