<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->foreignId('type_acte_id')->constrained('types_actes');
            $table->string('client_role')->nullable();
            $table->enum('statut', ['en_attente', 'soumise', 'traitee', 'expiree'])->default('en_attente');
            $table->timestamp('expire_at');
            $table->foreignId('cree_par_id')->constrained('users');
            $table->text('objet')->nullable();
            $table->json('donnees')->nullable();
            $table->json('parties')->nullable();
            $table->enum('source', ['manuel', 'ocr'])->nullable();
            $table->string('fichier_scan')->nullable();
            $table->timestamp('soumise_at')->nullable();
            $table->foreignId('traitee_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traitee_at')->nullable();
            $table->foreignId('dossier_id')->nullable()->constrained('dossiers')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes');
    }
};
