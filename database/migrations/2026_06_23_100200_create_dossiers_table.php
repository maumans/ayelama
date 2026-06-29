<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossiers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('type_acte_id')->constrained('types_actes')->restrictOnDelete();
            $table->string('etape', 30)->default('initialisation');
            $table->foreignId('redacteur_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviseur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('notaire_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('formaliste_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('objet');
            $table->unsignedBigInteger('valeur')->nullable();
            $table->date('echeance')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('etape_changed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('etape');
            $table->index('reference');
            $table->index('echeance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossiers');
    }
};
