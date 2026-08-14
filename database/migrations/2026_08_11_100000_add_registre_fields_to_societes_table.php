<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La table `societes` passe de sous-objet d'un dossier à **référentiel réutilisable**.
 *
 * Elle existait depuis le 2026-06-29 mais n'a jamais été alimentée : ni controller, ni
 * route, ni seeder (devbook §826). Les dénominations ne vivaient que dans
 * `questionnaires.donnees`, ce qui rendait impossible d'ouvrir un dossier de modification
 * sur une société déjà connue de l'étude — il fallait retaper son nom.
 *
 * `dossier_id` **garde son sens**, précisé : le dossier de **constitution** (origine) de la
 * société. Le lien inverse « ce dossier porte sur cette société » est porté par
 * `dossiers.societe_id` (migration suivante), valable pour une constitution comme pour une
 * modification ou une dissolution. La colonne n'est pas renommée : la table est vide,
 * aucune donnée à migrer, et le renommage casserait la relation existante sans gain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('societes', function (Blueprint $table) {
            // Éléments d'identification que le questionnaire de modification doit préremplir
            // et qui n'existaient pas : sans eux, une fiche société ne dit pas depuis quand
            // la société existe ni qui l'a constituée.
            $table->date('date_constitution')->nullable()->after('date_acte');
            $table->string('notaire_origine')->nullable()->after('date_constitution');

            // Dernière modification statutaire effectivement appliquée à la fiche (voir
            // SocieteMutationService) — distinct de `updated_at`, qui bouge à la moindre
            // correction de saisie.
            $table->timestamp('derniere_modification_at')->nullable()->after('jal_journal');

            // Une société dissoute ne doit plus être proposée pour une modification, sans
            // pour autant disparaître des dossiers qui la référencent.
            $table->boolean('actif')->default(true)->after('derniere_modification_at');

            $table->index('denomination');
            $table->index('rccm_numero');
        });
    }

    public function down(): void
    {
        Schema::table('societes', function (Blueprint $table) {
            $table->dropIndex(['denomination']);
            $table->dropIndex(['rccm_numero']);
            $table->dropColumn([
                'date_constitution',
                'notaire_origine',
                'derniere_modification_at',
                'actif',
            ]);
        });
    }
};
