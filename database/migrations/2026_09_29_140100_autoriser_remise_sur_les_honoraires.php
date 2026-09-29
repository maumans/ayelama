<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ouvre la remise sur les **honoraires**, et rattache les lignes existantes à leur barème.
 *
 * Deux opérations, toutes deux nécessaires pour que la fonctionnalité serve dès le premier
 * jour plutôt que d'attendre une configuration manuelle.
 *
 * **1. `remise_autorisee` sur les honoraires.** Le critère est `organisme = 'Notaire'` — 16
 * barèmes — et il n'est pas inventé pour l'occasion : c'est celui que `Bareme::organismeLabel()`
 * traduit déjà par « Honoraires notaire », le seul organisme de la liste qui ne soit pas un
 * tiers à payer. Tout le reste est un débours.
 *
 * **2. `bareme_id` sur les 111 lignes existantes.** Le rattachement se fait par le **libellé**,
 * que `FacturationService::formaterDesignation()` place en tête de la désignation avant une
 * éventuelle parenthèse — la même règle que `FactureGeneratorService::detailPrestations()`
 * emploie pour l'extraire. ⚠️ Rattachement **uniquement si la correspondance est unique** pour
 * le type d'acte du dossier : une ligne ambiguë reste sans barème, ce qui la rend simplement
 * non remisable jusqu'à la prochaine régénération. Deviner vaudrait moins que ne rien faire.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('baremes')->where('organisme', 'Notaire')->update(['remise_autorisee' => true]);

        $ambigues = 0;
        $rattachees = 0;

        $lignes = DB::table('lignes_factures as l')
            ->join('factures as f', 'f.id', '=', 'l.facture_id')
            ->join('dossiers as d', 'd.id', '=', 'f.dossier_id')
            ->whereNull('l.bareme_id')
            ->get(['l.id', 'l.designation', 'd.type_acte_id']);

        foreach ($lignes as $ligne) {
            // « Honoraires forfaitaires (2 % de 50 000 000) » → « Honoraires forfaitaires ».
            $libelle = trim(explode(' (', (string) $ligne->designation)[0]);

            $candidats = DB::table('baremes')
                ->where('type_acte_id', $ligne->type_acte_id)
                ->where('libelle', $libelle)
                ->pluck('id');

            if ($candidats->count() !== 1) {
                $ambigues++;
                continue;
            }

            $bareme = DB::table('baremes')->where('id', $candidats->first())->first(['id', 'remise_autorisee']);

            DB::table('lignes_factures')->where('id', $ligne->id)->update([
                'bareme_id'        => $bareme->id,
                // Le drapeau est **copié** sur la ligne, pas relu ensuite : une facture émise
                // garde la règle qui valait au moment où elle a été établie.
                'remise_autorisee' => $bareme->remise_autorisee,
            ]);
            $rattachees++;
        }

        Log::info('Rattachement des lignes de facture à leur barème', [
            'rattachees' => $rattachees,
            'ambigues'   => $ambigues,
        ]);
    }

    public function down(): void
    {
        DB::table('baremes')->update(['remise_autorisee' => false]);
        DB::table('lignes_factures')->update(['bareme_id' => null, 'remise_autorisee' => false]);
    }
};
