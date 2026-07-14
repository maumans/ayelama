<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('revision_grilles');
    }

    public function down(): void
    {
        Schema::create('revision_grilles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_acte_id')->constrained('types_actes')->cascadeOnDelete();
            $table->string('version', 10)->default('1.0');
            $table->boolean('est_active')->default(true);
            $table->json('points');
            $table->timestamps();

            $table->index(['type_acte_id', 'est_active']);
        });
    }
};
