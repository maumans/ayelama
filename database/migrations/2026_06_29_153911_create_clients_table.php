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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['physique', 'morale']);
            
            // Personne physique
            $table->string('civilite')->nullable();
            $table->string('prenom_nom')->nullable();
            $table->string('ne_a')->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('nationalite')->default('Guinéenne');
            $table->string('piece_type')->nullable(); // Passeport / CNI CEDEAO
            $table->string('piece_numero')->nullable();
            $table->date('piece_delivree_le')->nullable();
            $table->string('piece_delivree_a')->nullable();
            $table->date('piece_expire_le')->nullable();
            $table->string('situation_matrimoniale')->nullable();
            $table->string('regime_matrimonial')->nullable();
            
            // Personne morale
            $table->string('denomination')->nullable();
            $table->string('forme')->nullable();
            $table->string('rccm')->nullable();
            $table->string('representant_legal')->nullable();
            
            // Communs
            $table->string('demeurant_ville')->nullable();
            $table->string('quartier')->nullable();
            $table->string('commune')->nullable();
            $table->string('pays')->default('République de Guinée');
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('siege')->nullable(); // pour pm

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
