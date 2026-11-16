<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signature du questionnaire par le client — retour R23 (arbitré le 2026-11-16).
 *
 * - `mode_signature` : `papier` (fiche imprimée, signée, scannée) ou `ecran` (signée sur l'écran
 *   de l'étude). Les deux restent possibles.
 * - `signataire` : le nom de la personne qui a signé à l'écran (le client principal).
 * - `empreinte_questionnaire` : SHA-256 du questionnaire au moment de l'accord. Une modification
 *   ultérieure rend l'accord **caduc** et exige une nouvelle signature. NULL pour les accords
 *   antérieurs : on ne sait pas sur quel contenu ils portaient, ils restent valables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_fichiers', function (Blueprint $table) {
            $table->string('mode_signature', 12)->nullable();
            $table->string('signataire', 200)->nullable();
            $table->string('empreinte_questionnaire', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('document_fichiers', function (Blueprint $table) {
            $table->dropColumn(['mode_signature', 'signataire', 'empreinte_questionnaire']);
        });
    }
};
