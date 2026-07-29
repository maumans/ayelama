<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nettoyage final du plan GED : `documents` et `formalite_pieces` ont été backfillées
 * dans document_fichiers/document_versions (migration 2026_07_24_120000) et plus aucun
 * code ne les lit (DocumentController, FormaliteController, Formalite::versArray et
 * DossierController opèrent tous désormais sur DocumentFichier). Le `down()` recrée le
 * schéma mais ne restaure pas les données — la sauvegarde de la base avant cette
 * migration reste le seul filet de récupération si besoin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('formalite_pieces');
        Schema::dropIfExists('documents');
    }

    public function down(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained('dossiers')->cascadeOnDelete();
            $table->string('nom');
            $table->string('type_document', 50);
            $table->string('statut', 20)->default('a_editer');
            $table->string('chemin_fichier')->nullable();
            $table->string('version', 10)->nullable();
            $table->boolean('signature_client_requise')->default(true);
            $table->timestamp('edite_at')->nullable();
            $table->timestamp('signe_client_at')->nullable();
            $table->timestamp('signe_notaire_at')->nullable();
            $table->foreignId('edite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('formalite_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formalite_id')->constrained('formalites')->cascadeOnDelete();
            $table->string('label');
            $table->string('chemin_fichier')->nullable();
            $table->string('nom_original')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('taille_octets')->nullable();
            $table->foreignId('televerse_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('televerse_at')->nullable();
            $table->boolean('est_fourni')->default(false);
            $table->timestamp('fourni_at')->nullable();
            $table->timestamps();
        });
    }
};
