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
        Schema::create('societes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->nullable()->constrained('dossiers')->cascadeOnDelete();
            
            $table->string('denomination')->nullable();
            $table->string('forme')->nullable(); // SARL/SARLU/SAS/SASU/SA/GIE
            $table->string('sigle')->nullable();
            $table->decimal('capital_chiffres', 15, 2)->nullable();
            $table->integer('nombre_parts')->nullable();
            $table->decimal('valeur_nominale_chiffres', 15, 2)->nullable();
            
            $table->string('siege_quartier')->nullable();
            $table->string('siege_commune')->nullable();
            $table->string('siege_ville')->nullable();
            $table->string('email_societe')->nullable();
            $table->string('telephone_societe')->nullable();
            
            $table->text('objet_social')->nullable();
            $table->integer('duree')->default(99);
            $table->string('exercice_social')->default('1er janvier au 31 décembre');
            
            $table->date('date_acte')->nullable();
            
            $table->string('rccm_numero')->nullable();
            $table->string('nif')->nullable();
            $table->string('jal_journal')->nullable();
            
            // Direction (can be stored as json for flexibility or separate columns)
            $table->json('direction')->nullable();
            
            $table->string('commissaire_titulaire')->nullable();
            $table->string('commissaire_suppleant')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('societes');
    }
};
