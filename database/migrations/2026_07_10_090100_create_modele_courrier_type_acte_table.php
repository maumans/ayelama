<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modele_courrier_type_acte', function (Blueprint $table) {
            $table->foreignId('modele_courrier_id')->constrained('modeles_courriers')->cascadeOnDelete();
            $table->foreignId('type_acte_id')->constrained('types_actes')->cascadeOnDelete();
            $table->unique(['modele_courrier_id', 'type_acte_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modele_courrier_type_acte');
    }
};
