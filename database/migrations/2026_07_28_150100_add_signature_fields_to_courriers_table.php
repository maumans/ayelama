<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mêmes 4 colonnes que document_fichiers (voir migration
 * 2026_07_24_130100_add_signature_fields_to_document_fichiers_table) — Courrier reste
 * un système séparé de la GED unifiée (décision actée), mais reproduit le même
 * mécanisme de clôture (obligatoire / signé-cacheté / verrouillé) que les documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->boolean('est_requis')->default(false)->after('chemin_fichier');
            $table->boolean('est_signe_cachete')->default(false)->after('est_requis');
            $table->timestamp('signe_cachete_at')->nullable()->after('est_signe_cachete');
            $table->foreignId('signe_cachete_par_id')->nullable()->after('signe_cachete_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signe_cachete_par_id');
            $table->dropColumn(['est_requis', 'est_signe_cachete', 'signe_cachete_at']);
        });
    }
};
