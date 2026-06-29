<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_actes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('label');
            $table->string('categorie', 30);
            $table->string('prefixe_reference', 10)->nullable();
            $table->integer('delai_jours')->default(30);
            $table->text('description')->nullable();
            $table->json('actes_requis')->nullable();
            $table->boolean('fiche_modification_obligatoire')->default(false);
            $table->boolean('actif')->default(true);
            $table->integer('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_actes');
    }
};
