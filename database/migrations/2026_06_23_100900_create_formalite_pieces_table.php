<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formalite_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formalite_id')->constrained('formalites')->cascadeOnDelete();
            $table->string('label');
            $table->boolean('est_fourni')->default(false);
            $table->timestamp('fourni_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formalite_pieces');
    }
};
