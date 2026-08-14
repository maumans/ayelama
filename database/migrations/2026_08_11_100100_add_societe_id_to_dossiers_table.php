<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Ce dossier porte sur cette société. »
 *
 * Vrai dans les trois procédures de la catégorie Société :
 *   - constitution : la fiche est créée à l'issue de la création du dossier ;
 *   - modification : la fiche est **choisie** dans le registre à l'entrée de l'assistant ;
 *   - dissolution  : idem.
 *
 * `nullOnDelete` et non `cascadeOnDelete` : supprimer une fiche société ne doit jamais
 * emporter les dossiers qui la référencent — un dossier notarial garde sa valeur propre.
 * (C'est l'inverse du choix fait sur `societes.dossier_id`, où la société créée *par* un
 * dossier de constitution disparaît avec lui.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->foreignId('societe_id')
                ->nullable()
                ->after('type_acte_id')
                ->constrained('societes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropForeign(['societe_id']);
            $table->dropColumn('societe_id');
        });
    }
};
