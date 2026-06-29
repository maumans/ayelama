<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->unique()->constrained('dossiers')->cascadeOnDelete();
            $table->foreignId('reviseur_id')->nullable()->constrained('users')->nullOnDelete();
            // statuts : en_attente | en_cours | valide | renvoye
            $table->string('statut', 20)->default('en_attente');
            $table->text('commentaire')->nullable();
            $table->timestamp('valide_at')->nullable();
            $table->timestamp('renvoye_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};
