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
        Schema::create('lignes_factures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facture_id')->constrained('factures')->cascadeOnDelete();
            
            $table->string('designation');
            $table->integer('quantite')->default(1);
            $table->decimal('montant', 15, 2);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lignes_factures');
    }
};
