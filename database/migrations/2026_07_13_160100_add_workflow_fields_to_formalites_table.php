<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formalites', function (Blueprint $table) {
            $table->foreignId('bareme_id')->nullable()->after('dossier_id')
                ->constrained('baremes')->nullOnDelete();
            $table->foreignId('depend_de_formalite_id')->nullable()->after('bareme_id')
                ->constrained('formalites')->nullOnDelete();
            $table->unsignedInteger('ordre')->nullable()->after('depend_de_formalite_id');
            $table->decimal('montant_paye', 15, 2)->nullable()->after('montant_calcule');
            $table->string('numero_recepisse')->nullable()->after('depose_at');
            $table->string('reference_document_recu')->nullable()->after('retour_at');

            // Ajoutée AVANT de supprimer l'ancien unique(dossier_id, organisme) : MySQL a
            // besoin qu'un index couvrant dossier_id existe en permanence (contrainte FK).
            $table->unique(['dossier_id', 'bareme_id']);
        });

        Schema::table('formalites', function (Blueprint $table) {
            $table->dropUnique(['dossier_id', 'organisme']);
        });
    }

    public function down(): void
    {
        Schema::table('formalites', function (Blueprint $table) {
            $table->unique(['dossier_id', 'organisme']);
        });

        Schema::table('formalites', function (Blueprint $table) {
            $table->dropUnique(['dossier_id', 'bareme_id']);
            $table->dropConstrainedForeignId('depend_de_formalite_id');
            $table->dropConstrainedForeignId('bareme_id');
            $table->dropColumn(['ordre', 'montant_paye', 'numero_recepisse', 'reference_document_recu']);
        });
    }
};
