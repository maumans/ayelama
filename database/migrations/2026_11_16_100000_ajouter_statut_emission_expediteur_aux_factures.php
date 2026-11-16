<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Proforma, émission et expéditeur d'une facture — retours R20 et R11 (2026-11-16).
 *
 * - `statut` : une facture naît **proforma**, sans numéro ; « Émettre » lui attribue
 *   `NNN/MAB/AA`. Les 24 factures existantes (mesuré) sont toutes numérotées, et 8 ont reçu un
 *   paiement : elles sont rangées **émises**, datées de leur création.
 * - `expediteur` : la personne qui expédie la facture, texte libre prérempli avec l'utilisateur
 *   connecté.
 * - **Unicité du numéro** : rien ne la garantissait (mesuré : aucun doublon aujourd'hui). Un index
 *   unique sur une colonne nullable admet plusieurs NULL — les proformas — sous MySQL comme SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->string('statut', 12)->default('proforma')->after('dossier_id');
            $table->timestamp('emise_at')->nullable()->after('statut');
            $table->string('expediteur', 150)->nullable()->after('note_date');
        });

        DB::table('factures')->whereNotNull('note_numero')->update([
            'statut'   => 'emise',
            'emise_at' => DB::raw('created_at'),
        ]);

        Schema::table('factures', function (Blueprint $table) {
            $table->unique('note_numero');
        });
    }

    public function down(): void
    {
        Schema::table('factures', function (Blueprint $table) {
            $table->dropUnique(['note_numero']);
            $table->dropColumn(['statut', 'emise_at', 'expediteur']);
        });
    }
};
