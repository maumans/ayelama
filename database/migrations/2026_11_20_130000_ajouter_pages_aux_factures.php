<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le nombre de pages facturées porté **par la facture**, et les barèmes qui se comptent à la page.
 *
 * Règle de l'étude (2026-11-20) : timbres fiscaux = pages des **statuts et de la DNSV**, sans les
 * pages de garde, × 2 000 GNF ; rôles = les mêmes pages × 5 000 GNF. Deux lignes, une seule
 * donnée.
 *
 * Jusqu'ici la quantité se tapait ligne par ligne, deux fois, et `FacturationService` la remettait
 * à 0 à chaque régénération de la proforma (il supprime et recrée la facture) : une saisie perdue
 * en silence. Portée par la facture, elle survit à la régénération et alimente les deux lignes.
 *
 * `par_page` est une colonne et non un test sur le libellé : le libellé se modifie à l'écran
 * Barèmes, la nature d'un tarif non.
 */
return new class extends Migration
{
    private const LIBELLES_A_LA_PAGE = ['Timbres fiscaux (à la page)', 'Rôles (à la page)'];

    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->unsignedSmallInteger('nb_pages')->nullable()->after('banque');
        });

        Schema::table('baremes', function (Blueprint $table) {
            $table->boolean('par_page')->default(false)->after('quantite_defaut');
        });

        DB::table('baremes')->whereIn('libelle', self::LIBELLES_A_LA_PAGE)->update(['par_page' => true]);
    }

    public function down(): void
    {
        Schema::table('baremes', fn (Blueprint $table) => $table->dropColumn('par_page'));
        Schema::table('factures', fn (Blueprint $table) => $table->dropColumn('nb_pages'));
    }
};
