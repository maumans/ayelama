<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revision_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_id')->constrained('revisions')->cascadeOnDelete();
            $table->string('point_id', 20);
            // etats : conforme | non_conforme | na | null
            $table->string('etat', 20)->nullable();
            $table->text('commentaire')->nullable();
            $table->timestamps();

            $table->unique(['revision_id', 'point_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revision_points');
    }
};
