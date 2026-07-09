<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('formalite_types');
    }

    public function down(): void
    {
        // Recréation du schéma d'origine possible (voir migration create_formalite_types_table),
        // mais les données ont été portées vers `baremes` par la migration de backfill précédente
        // et ne sont pas reconstituables ici.
        Schema::create('formalite_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_acte_id')->constrained('types_actes')->cascadeOnDelete();
            $table->string('organisme', 30);
            $table->string('libelle', 200)->nullable();
            $table->boolean('obligatoire')->default(true);
            $table->string('base_calcul', 20)->default('montant_fixe');
            $table->decimal('taux', 6, 4)->nullable();
            $table->decimal('montant_fixe', 15, 2)->nullable();
            $table->string('type_impot')->nullable();
            $table->string('retour_attendu')->nullable();
            $table->unsignedInteger('delai_heures')->nullable();
            $table->json('pieces_requises')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['type_acte_id', 'organisme']);
            $table->index(['type_acte_id', 'actif']);
        });
    }
};
