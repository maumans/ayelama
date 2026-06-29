<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modeles_actes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_acte_id')->constrained('types_actes')->cascadeOnDelete();
            $table->string('nom');
            $table->string('type_document', 50);
            $table->string('chemin_fichier');
            $table->string('version', 10)->default('1.0');
            $table->boolean('est_actif')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type_acte_id', 'est_actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modeles_actes');
    }
};
