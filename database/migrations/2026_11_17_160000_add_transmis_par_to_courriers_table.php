<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qui a transmis un courrier (2026-11-17). Jusqu'ici un clic sur « Marquer envoyé » suffisait :
 * la date était celle du clic, et personne n'était nommé. L'étude veut la date réelle de
 * transmission et la personne qui l'a faite — texte libre, car ce peut être un coursier ou un
 * tiers, pas forcément un utilisateur de l'application.
 *
 * Les 22 courriers déjà marqués envoyés (mesuré le 2026-11-17) gardent `transmis_par` à NULL :
 * on ne l'invente pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->string('transmis_par', 150)->nullable()->after('envoye_at');
        });
    }

    public function down(): void
    {
        Schema::table('courriers', function (Blueprint $table) {
            $table->dropColumn('transmis_par');
        });
    }
};
