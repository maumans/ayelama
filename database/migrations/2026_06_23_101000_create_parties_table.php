<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained('dossiers')->cascadeOnDelete();
            $table->string('nom');
            $table->string('role');
            $table->string('cni')->nullable();
            $table->string('telephone', 25)->nullable();
            $table->string('adresse')->nullable();
            $table->string('email')->nullable();
            $table->string('photo_chemin')->nullable();
            $table->json('pieces')->nullable();
            $table->timestamps();

            $table->index('dossier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
