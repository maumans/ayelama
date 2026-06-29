<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained('dossiers')->cascadeOnDelete();
            $table->string('nom');
            $table->string('type_document', 50);
            // statuts : a_editer | edite | signe_client | signe_notaire
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
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
