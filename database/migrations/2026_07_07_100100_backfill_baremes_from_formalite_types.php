<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Formalite/FormaliteType utilisent une nomenclature d'organisme différente de Bareme
    // (snake_case fermé vs PascalCase libre) — voir devbook, décision de fusion Barèmes/Formalités.
    private const ORGANISME_MAP = [
        'apip'                  => 'APIP',
        'impots'                => 'Impots',
        'conservation_fonciere' => 'Conservation',
        'cnss'                  => 'CNSS',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('formalite_types')) {
            return;
        }

        $now = now();
        $rows = DB::table('formalite_types')->get();

        foreach ($rows as $ft) {
            DB::table('baremes')->insert([
                'type_acte_id'     => $ft->type_acte_id,
                'organisme'        => self::ORGANISME_MAP[$ft->organisme] ?? ucfirst($ft->organisme),
                'libelle'          => $ft->libelle ?: ucfirst($ft->organisme),
                // Formalite::calculerMontant() attend une fraction 0-1, Bareme::calculerMontant()
                // attend un pourcentage 0-100 : on multiplie par 100 en portant la donnée.
                'taux'             => $ft->taux !== null ? $ft->taux * 100 : null,
                'montant_fixe'     => $ft->montant_fixe,
                'base_calcul'      => $ft->base_calcul,
                'description'      => null,
                'actif'            => $ft->actif,
                'ordre'            => $ft->ordre,
                'genere_formalite' => true,
                'obligatoire'      => $ft->obligatoire,
                'type_impot'       => $ft->type_impot,
                'retour_attendu'   => $ft->retour_attendu,
                'delai_heures'     => $ft->delai_heures,
                'pieces_requises'  => $ft->pieces_requises,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Irréversible sans connaître l'état des baremes avant la fusion (même convention
        // que la migration migrate_signature_etapes_to_formalites déjà présente au projet).
    }
};
