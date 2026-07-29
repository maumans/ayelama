<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verrouillage définitif d'un document une fois sa version signée/cachetée déposée
 * (retour du circuit papier réel : impression → envoi → signature/cachet → retour) —
 * voir DocumentController::televerserSigne() et les gardes dans update()/regenerer()/destroy().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_fichiers', function (Blueprint $table) {
            $table->boolean('est_signe_cachete')->default(false)->after('est_requis');
            $table->timestamp('signe_cachete_at')->nullable()->after('est_signe_cachete');
            $table->foreignId('signe_cachete_par_id')->nullable()->after('signe_cachete_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('document_fichiers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signe_cachete_par_id');
            $table->dropColumn(['est_signe_cachete', 'signe_cachete_at']);
        });
    }
};
