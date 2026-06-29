<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courriers', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('dossier_id')->nullable()->constrained('dossiers')->nullOnDelete();
            $table->foreignId('redacteur_id')->constrained('users');
            $table->string('destinataire', 300);
            $table->string('adresse', 500)->nullable();
            $table->string('objet', 500);
            $table->string('type', 50)->default('divers'); // transmission, convocation, relance, divers
            $table->string('statut', 30)->default('brouillon'); // brouillon, envoye
            $table->text('contenu')->nullable();
            $table->timestamp('envoye_at')->nullable();
            $table->timestamps();

            $table->index(['statut', 'created_at']);
            $table->index('dossier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courriers');
    }
};
