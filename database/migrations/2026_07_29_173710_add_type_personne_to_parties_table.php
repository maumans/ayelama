<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            // 'physique' | 'morale' — distingue un associé personne physique d'un associé
            // personne morale (SARL), pour piloter la checklist de pièces requises
            // (Partie::piecesRequisesDefinition()). Nullable : non pertinent pour les rôles
            // toujours personne physique (gérant, associé unique, bailleur, etc.).
            $table->string('type_personne', 20)->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn('type_personne');
        });
    }
};
