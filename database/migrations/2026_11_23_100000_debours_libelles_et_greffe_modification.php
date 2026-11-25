<?php

use App\Models\Bareme;
use App\Models\TypeActe;
use Database\Seeders\ReglesGestionHonorairesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trois décisions de l'étude du 2026-11-23, après confrontation à ses notes de frais réelles
 * (KOUMI 325/MAB/2026, NEXIUM 324/MAB/26 — `docs/ANALYSE-NOTES-DE-FRAIS-2026-11.md`) :
 *
 *  1. **Débours** sur les sept constitutions, selon le capital, par tranche — colonne `tranches`
 *     et ligne posée par `ReglesGestionHonorairesSeeder::poserDebours()` (une seule définition).
 *     La grille n'est connue qu'en deux points : la ligne porte `a_confirmer`.
 *  2. **Libellés des notes de l'étude** : « Honoraires Forfaitaires », « Insertion dans un Journal
 *     d'Annonces Légales », « Timbres fiscaux », « Rôles ». Renommer un barème ne touche aucune
 *     facture : ses lignes gardent leur désignation, et leur lien passe par `bareme_id`.
 *  3. « **Enregistrement au Tribunal de Commerce (RCCM)** » retiré aussi des **modifications**.
 *     ⚠️ Il y générait la démarche de greffe : elle ne s'ouvre plus d'office. Confirmé par l'étude
 *     en connaissance de cause.
 *
 * N'applique que ces changements : relancer le seeder réinitialiserait toute la grille et
 * effacerait les réglages faits à l'écran Barèmes. Idempotente.
 */
return new class extends Migration
{
    private const RENOMMAGES = [
        'Honoraires forfaitaires'                  => 'Honoraires Forfaitaires',
        'Insertion JAL (Journal Annonces Légales)' => "Insertion dans un Journal d'Annonces Légales",
        'Timbres fiscaux (à la page)'              => 'Timbres fiscaux',
        'Rôles (à la page)'                        => 'Rôles',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('baremes', 'tranches')) {
            Schema::table('baremes', function (Blueprint $table) {
                $table->json('tranches')->nullable()->after('montant_fixe');
            });
        }

        foreach (self::RENOMMAGES as $ancien => $nouveau) {
            Bareme::where('libelle', $ancien)->update(['libelle' => $nouveau]);
        }

        $types = TypeActe::pluck('id', 'code');

        foreach (ReglesGestionHonorairesSeeder::TYPES_CONSTITUTION as $code) {
            if (isset($types[$code])) {
                ReglesGestionHonorairesSeeder::poserDebours($types[$code]);
            }
        }

        if (isset($types['SOC-MOD'])) {
            Bareme::where('type_acte_id', $types['SOC-MOD'])
                ->where('libelle', 'Enregistrement au Tribunal de Commerce (RCCM)')
                ->where('actif', true)
                ->update([
                    'actif'       => false,
                    'a_confirmer' => false,
                    'source'      => "Désactivé le 2026-11-23 à la demande de l'étude, comme pour les constitutions. "
                        . "Il générait aussi la démarche de greffe de la modification : elle ne s'ouvre plus d'office.",
                ]);
        }
    }

    public function down(): void
    {
        foreach (self::RENOMMAGES as $ancien => $nouveau) {
            Bareme::where('libelle', $nouveau)->update(['libelle' => $ancien]);
        }

        // Désactivé et non supprimé : des factures peuvent citer la ligne. La colonne `tranches`
        // reste, pour la même raison — sans elle, ces lignes n'auraient plus de montant explicable.
        Bareme::where('libelle', 'Débours')->whereNotNull('tranches')->update(['actif' => false]);
    }
};
