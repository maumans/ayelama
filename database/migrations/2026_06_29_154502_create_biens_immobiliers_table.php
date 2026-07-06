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
        Schema::create('biens_immobiliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->nullable()->constrained('dossiers')->cascadeOnDelete();
            
            $table->string('parcelle_numero')->nullable();
            $table->string('lot_numero')->nullable();
            $table->string('lieu_de')->nullable();
            $table->string('nature_terrain')->nullable(); // urbain bâti / nu
            $table->string('usage')->nullable(); // habitation / commercial / mixte
            $table->decimal('superficie_m2', 10, 2)->nullable();
            $table->string('pcp')->nullable(); // plan de codification parcellaire
            
            $table->string('titre_foncier_numero')->nullable();
            $table->date('tf_date')->nullable();
            $table->string('livre_foncier_ville')->nullable();
            $table->string('tf_volume')->nullable();
            $table->string('tf_folio')->nullable();
            $table->string('tf_annee')->nullable();
            
            $table->string('limites_ne')->nullable();
            $table->string('limites_so')->nullable();
            $table->string('limites_se')->nullable();
            $table->string('limites_no')->nullable();
            
            $table->text('origine_propriete')->nullable();
            $table->decimal('prix_vente_chiffres', 15, 2)->nullable();
            
            // champs spécifiques bail
            $table->string('type_bail')->nullable(); // habitation / professionnel / à construire
            $table->integer('duree_bail')->nullable();
            $table->date('date_prise_effet')->nullable();
            $table->decimal('loyer_chiffres', 15, 2)->nullable();
            $table->string('periodicite_loyer')->nullable();
            $table->string('destination_bien')->nullable();
            $table->text('engagement_construction')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biens_immobiliers');
    }
};
