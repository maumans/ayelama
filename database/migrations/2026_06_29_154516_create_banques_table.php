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
        Schema::create('banques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->nullable()->constrained('dossiers')->cascadeOnDelete();
            
            $table->string('denomination')->nullable();
            $table->string('forme')->nullable();
            $table->string('quartier')->nullable();
            $table->string('commune')->nullable();
            $table->string('ville')->nullable();
            
            $table->decimal('montant_credit_chiffres', 15, 2)->nullable();
            $table->string('type_garantie')->nullable(); // affectation hypothécaire…
            $table->string('rang_hypothecaire')->nullable(); // 1er…

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banques');
    }
};
