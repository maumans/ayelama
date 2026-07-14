<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modeles_courriers', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('type_document');
            $table->string('chemin_fichier');
            $table->string('version')->default('1.0');
            $table->boolean('est_actif')->default(false);
            $table->boolean('applicable_tous')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modeles_courriers');
    }
};
