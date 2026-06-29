<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formalites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained('dossiers')->cascadeOnDelete();
            // organismes : apip | impots | conservation_fonciere | cnss
            $table->string('organisme', 30);
            // statuts : a_deposer | depose | en_attente | retour_recu | cloture
            $table->string('statut', 20)->default('a_deposer');
            $table->decimal('taux', 6, 4)->nullable();
            $table->decimal('montant_base', 15, 2)->nullable();
            $table->decimal('montant_calcule', 15, 2)->nullable();
            $table->string('type_impot')->nullable();
            $table->string('retour_attendu')->nullable();
            $table->unsignedInteger('delai_heures')->nullable();
            $table->timestamp('depose_at')->nullable();
            $table->timestamp('retour_at')->nullable();
            $table->timestamp('echeance_at')->nullable();
            $table->timestamps();

            $table->unique(['dossier_id', 'organisme']);
            $table->index(['statut', 'echeance_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formalites');
    }
};
