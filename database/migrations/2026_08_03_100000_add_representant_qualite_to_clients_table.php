<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Seul champ d'identité attendu par les modèles Word (${bq.representant_qualite})
            // qui manquait à la fiche Client — « Directeur Général », « Fondé de pouvoir »…
            // Complète `representant_legal` (le nom) pour les personnes morales.
            $table->string('representant_qualite', 150)->nullable()->after('representant_legal');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('representant_qualite');
        });
    }
};
