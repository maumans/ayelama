<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cycle de vie de la fiche société — voir {@see \App\Enums\StatutSociete}.
 *
 * Remplace `actif`, booléen jamais écrit : mesuré le 2026-09-28, 14 fiches sur 14 à `true`,
 * un seul lecteur (`SocieteController::autocomplete`). Son intention — « une société dissoute
 * ne doit plus être proposée » — est en outre contredite par la conception retenue : la clôture
 * de liquidation doit précisément proposer une société dissoute. Garder les deux colonnes,
 * c'était garantir deux vérités dont l'une est fausse.
 *
 * Aucune migration de données n'accompagne celle-ci, et c'est une conclusion de mesure, pas un
 * oubli : il n'existe aucun dossier `SOC-DIS` en base. Le défaut `active` est donc exact pour
 * les 14 fiches.
 *
 * Les dates restent ISO — ce sont des colonnes castées (contrat en tête de
 * `resources/js/lib/dates.js`). Le `JJ/MM/AAAA` ne vaut que dans `questionnaires.donnees`.
 *
 * ⚠️ MySQL en développement, SQLite en test : colonnes, index et suppression sont dans trois
 * `Schema::table()` distincts. SQLite reconstruit la table à chaque `dropColumn`, et mêler
 * l'ajout d'un index à une suppression de colonne dans le même `Blueprint` y produit un ordre
 * d'opérations différent de MySQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('societes', function (Blueprint $table) {
            $table->string('statut', 30)
                ->default(\App\Enums\StatutSociete::Active->value)
                ->after('derniere_modification_at');

            // Dates des trois jalons du cycle. Nullables : une société active n'en a aucune, et
            // une société entrée au registre à la main peut avoir été dissoute sans que l'étude
            // connaisse la date — un `0000-00-00` serait pire qu'un trou déclaré.
            $table->date('dissolution_at')->nullable()->after('statut');
            $table->date('cloture_liquidation_at')->nullable()->after('dissolution_at');
            $table->date('radiation_at')->nullable()->after('cloture_liquidation_at');
        });

        Schema::table('societes', function (Blueprint $table) {
            // La page registre filtre par statut, et la commande d'alerte balaie les sociétés
            // en liquidation chaque matin.
            $table->index('statut');
        });

        Schema::table('societes', function (Blueprint $table) {
            $table->dropColumn('actif');
        });
    }

    public function down(): void
    {
        Schema::table('societes', function (Blueprint $table) {
            $table->boolean('actif')->default(true)->after('derniere_modification_at');
        });

        Schema::table('societes', function (Blueprint $table) {
            $table->dropIndex(['statut']);
        });

        Schema::table('societes', function (Blueprint $table) {
            $table->dropColumn([
                'statut',
                'dissolution_at',
                'cloture_liquidation_at',
                'radiation_at',
            ]);
        });
    }
};
