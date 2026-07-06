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
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained('dossiers')->cascadeOnDelete();
            
            $table->string('note_numero')->nullable();
            $table->date('note_date')->nullable();
            $table->string('compte_numero')->nullable();
            $table->string('objet')->nullable();
            $table->decimal('assiette_chiffres', 15, 2)->nullable();
            $table->decimal('total_chiffres', 15, 2)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};
