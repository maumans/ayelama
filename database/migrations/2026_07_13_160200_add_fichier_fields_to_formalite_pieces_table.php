<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formalite_pieces', function (Blueprint $table) {
            $table->string('chemin_fichier')->nullable()->after('label');
            $table->string('nom_original')->nullable()->after('chemin_fichier');
            $table->string('mime_type')->nullable()->after('nom_original');
            $table->unsignedBigInteger('taille_octets')->nullable()->after('mime_type');
            $table->foreignId('televerse_par_id')->nullable()->after('taille_octets')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('televerse_at')->nullable()->after('televerse_par_id');
        });
    }

    public function down(): void
    {
        Schema::table('formalite_pieces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('televerse_par_id');
            $table->dropColumn(['chemin_fichier', 'nom_original', 'mime_type', 'taille_octets', 'televerse_at']);
        });
    }
};
