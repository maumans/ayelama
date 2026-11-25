<?php

use App\Models\Bareme;
use App\Models\TypeActe;
use Illuminate\Database\Migrations\Migration;

/**
 * Retire de toutes les constitutions de société deux tarifs qu'aucune note de l'étude ne porte :
 * « Enregistrement fiscal acte » (70 000 GNF) et « Enregistrement au Tribunal de Commerce
 * (RCCM) » (180 000 GNF).
 *
 * Constat du 2026-11-20 sur les deux notes réelles (KOUMI 325/MAB/2026, NEXIUM 324/MAB/26) : la
 * plateforme facturait 250 000 GNF de plus que l'étude, sur des lignes déjà marquées
 * `a_confirmer`. Retrait confirmé par l'étude. Analyse : `docs/ANALYSE-NOTES-DE-FRAIS-2026-11.md`.
 *
 * Même règle que `ReglesGestionHonorairesSeeder::CONTREDITS`, qui en porte la version pour une
 * installation neuve — cette migration n'applique **que** ces deux retraits, là où relancer le
 * seeder réinitialiserait toute la grille et effacerait les réglages faits à l'écran Barèmes.
 *
 * **Désactivés, jamais supprimés** : 14 factures et 14 formalités de SARLU les référencent.
 * Celles-ci gardent leurs lignes : une facture émise est un instantané.
 *
 * ⚠️ `SOC-MOD` n'est **pas** touché : en modification, le 180 000 génère la formalité de greffe.
 *
 * Idempotente.
 */
return new class extends Migration
{
    private const TYPES = ['SOC-SARLU', 'SOC-SARL', 'SOC-SA', 'SOC-SAS', 'SOC-SASU', 'SOC-SNC', 'SOC-GIE'];

    private const RETRAITS = [
        'Enregistrement fiscal acte' => "Désactivé le 2026-11-20 à la demande de l'étude : absent de ses notes de "
            . 'constitution (KOUMI 325, NEXIUM 324). Sa formalité doublait celle des « Droits '
            . "d'enregistrement des Statuts et de la DNSV » (mêmes pièces, même impôt).",
        'Enregistrement au Tribunal de Commerce (RCCM)' => "Désactivé le 2026-11-20 à la demande de l'étude : absent de ses notes de "
            . 'constitution (KOUMI 325, NEXIUM 324), qui ne facturent que « Frais APIP et RCCM ».',
    ];

    public function up(): void
    {
        $ids = TypeActe::whereIn('code', self::TYPES)->pluck('id');

        foreach (self::RETRAITS as $libelle => $motif) {
            Bareme::whereIn('type_acte_id', $ids)
                ->where('libelle', $libelle)
                ->where('actif', true)
                ->update(['actif' => false, 'source' => $motif, 'a_confirmer' => false]);
        }
    }

    public function down(): void
    {
        // Pas de retour automatique : réactiver un tarif que l'étude a retiré se fait à l'écran
        // Barèmes, en connaissance de cause.
    }
};
