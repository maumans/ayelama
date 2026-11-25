<?php

use App\Models\Bareme;
use App\Models\Formalite;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La facture **complémentaire** des droits d'enregistrement (décision de l'étude, 2026-11-23).
 *
 * Les droits d'enregistrement des statuts et de la DNSV ne sont connus qu'au retour des Impôts ;
 * la facture principale, elle, est émise bien avant — et une facture émise ne se modifie plus.
 * Le montant se saisit donc **au retour de la formalité** (`DonneeAuRetour::MontantDroits`), et
 * ce retour établit une facture complémentaire proforma, rattachée à la principale.
 *
 *  - `factures.nature` : principale ou complémentaire (`App\Enums\NatureFacture`) ;
 *  - `factures.facture_origine_id` : la principale qu'elle complète ;
 *  - `factures.formalite_id` : le retour qui l'a établie — unique : un retour enregistré deux
 *    fois met à jour la même complémentaire, il n'en crée pas une seconde.
 *
 * Les 7 barèmes « Droits d'enregistrement des Statuts et de la DNSV » déclarent désormais les
 * données au retour `quittance_numero` (que `BaremeSeeder` prévoyait déjà, mais que la base ne
 * portait pas : mesuré `null` sur les 7) et `montant_droits`. Les formalités déjà générées et non
 * revenues reçoivent la même déclaration — mesuré le 2026-11-23 : aucune (les 3 existantes sont
 * revenues). Idempotente.
 */
return new class extends Migration
{
    private const LIBELLE = "Droits d'enregistrement des Statuts et de la DNSV";
    private const DONNEES = ['quittance_numero', 'montant_droits'];

    public function up(): void
    {
        if (! Schema::hasColumn('factures', 'nature')) {
            Schema::table('factures', function (Blueprint $table) {
                $table->string('nature', 20)->default('principale')->after('statut');
                $table->foreignId('facture_origine_id')->nullable()->after('nature')
                    ->constrained('factures')->nullOnDelete();
                $table->foreignId('formalite_id')->nullable()->unique()->after('facture_origine_id')
                    ->constrained('formalites')->nullOnDelete();
            });
        }

        $baremes = Bareme::where('libelle', self::LIBELLE)->get();

        foreach ($baremes as $bareme) {
            $bareme->update(['donnees_au_retour' => array_values(array_unique([
                ...($bareme->donnees_au_retour ?? []), ...self::DONNEES,
            ]))]);
        }

        Formalite::whereIn('bareme_id', $baremes->pluck('id'))
            ->where('statut', '!=', \App\Enums\StatutFormalite::RetourRecu->value)
            ->get()
            ->each(fn (Formalite $f) => $f->update(['donnees_au_retour' => array_values(array_unique([
                ...($f->donnees_au_retour ?? []), ...self::DONNEES,
            ]))]));
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('formalite_id');
            $table->dropConstrainedForeignId('facture_origine_id');
            $table->dropColumn('nature');
        });
    }
};
