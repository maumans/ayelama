<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes mortes : jamais alimentées par aucun contrôleur (voir devbook). Leur
 * suppression est nécessaire dès maintenant, pas seulement en nettoyage final : la
 * colonne `pieces` (JSON) entre en collision avec la nouvelle relation Eloquent
 * Partie::pieces() (morphMany vers document_fichiers) — sans ce drop, l'accès à
 * $partie->pieces renvoie l'attribut brut (toujours null) au lieu de la relation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['photo_chemin', 'pieces']);
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->string('photo_chemin')->nullable();
            $table->json('pieces')->nullable();
        });
    }
};
