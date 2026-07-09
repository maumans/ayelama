<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formalite_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_acte_id')->constrained('types_actes')->cascadeOnDelete();
            // organismes : apip | impots | conservation_fonciere | cnss — même nomenclature que Formalite.organisme
            $table->string('organisme', 30);
            $table->string('libelle', 200)->nullable();
            $table->boolean('obligatoire')->default(true);
            $table->string('base_calcul', 20)->default('montant_fixe'); // valeur_acte | montant_fixe
            // taux en fraction 0-1 (convention Formalite::calculerMontant(), PAS celle de Bareme qui divise par 100)
            $table->decimal('taux', 6, 4)->nullable();
            $table->decimal('montant_fixe', 15, 2)->nullable();
            $table->string('type_impot')->nullable();
            $table->string('retour_attendu')->nullable();
            $table->unsignedInteger('delai_heures')->nullable();
            // json : ["Copie CNI vendeur", ...] — mappé vers FormalitePiece à la génération
            $table->json('pieces_requises')->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['type_acte_id', 'organisme']);
            $table->index(['type_acte_id', 'actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formalite_types');
    }
};
