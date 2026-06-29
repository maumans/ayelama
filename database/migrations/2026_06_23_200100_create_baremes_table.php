<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('baremes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_acte_id')->constrained('types_actes')->cascadeOnDelete();
            $table->string('organisme', 100);   // APIP, Impots, Conservation, CNSS, Notaire, Autre
            $table->string('libelle', 200);
            $table->decimal('taux', 8, 4)->nullable();        // ex: 2.5000 = 2.5 %
            $table->decimal('montant_fixe', 15, 2)->nullable();
            $table->string('base_calcul', 50)->default('valeur_acte'); // valeur_acte | montant_fixe
            $table->text('description')->nullable();
            $table->boolean('actif')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['type_acte_id', 'actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baremes');
    }
};
