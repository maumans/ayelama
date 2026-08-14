<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rend le lien Partie → questionnaire auto-descriptif.
     *
     * Le schéma des questionnaires (et donc la correspondance rôle → préfixe :
     * associe_unique → `pp.`, gerant → `ger.`, creancier → `bq.`…) vit uniquement
     * dans resources/js/data/questionnaires.js. Le serveur ne peut donc pas
     * deviner où écrire les données d'un client dans `donnees` — or il doit le
     * faire quand une fiche client est modifiée depuis le Répertoire, hors du
     * wizard de création.
     *
     * Plutôt que de dupliquer ce schéma en PHP (dérive garantie entre les deux
     * copies), le frontend — qui connaît déjà le préfixe au moment de construire
     * le payload — le transmet ici. ClientProjectionService n'a alors plus besoin
     * du schéma : la Partie lui dit exactement où projeter.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            // 'pp', 'ger', 'acq', 'loc', 'bq'… — null pour un rôle non projeté dans
            // le questionnaire (ex. client du dossier avec une simple qualité libre).
            $table->string('donnees_prefixe', 30)->nullable()->after('client_id');
            // Clé du bloc répétable ('associes', 'gerants'…) — null pour une section scalaire.
            $table->string('donnees_bloc', 60)->nullable()->after('donnees_prefixe');
            // Position dans le bloc répétable — null hors bloc.
            $table->unsignedSmallInteger('donnees_index')->nullable()->after('donnees_bloc');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn(['donnees_prefixe', 'donnees_bloc', 'donnees_index']);
        });
    }
};
