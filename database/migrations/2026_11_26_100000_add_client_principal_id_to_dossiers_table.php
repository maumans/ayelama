<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * « Qui commande ce dossier. »
 *
 * Jusqu'ici, aucune notion de client principal n'existait en base : `Dossier::partiePrincipale()`
 * la **devinait** en parcourant une liste de rôles par priorité (associé unique, puis associé,
 * puis gérant, puis acheteur…) et prenait la première partie trouvée. L'heuristique tombe juste
 * tant que le donneur d'ordre est lui-même partie à l'acte — mais l'étude reçoit des dossiers
 * commandés par quelqu'un qui n'y tient aucun rôle. Le dossier portait alors le nom d'un autre
 * partout où `clientPrincipalLabel()` est lu : le reçu de paiement (PDF), la liste des
 * certifications, les balises des factures et des formalités, et le signataire proposé de
 * l'accord client. (La liste des dossiers, elle, n'affiche pas de client aujourd'hui.)
 *
 * C'est une donnée du **dossier**, pas de l'acte : le donneur d'ordre ne comparaît pas, n'a pas
 * de pièces à fournir et n'a pas de balise dans les actes. D'où une colonne sur `dossiers`
 * plutôt qu'une `Partie` de plus (option B écartée avec l'étude le 2026-11-26).
 *
 * Nullable, et sans reprise des dossiers existants : vide, `clientPrincipalLabel()` retombe sur
 * l'heuristique d'avant. Les 53 parties en base tiennent toutes un rôle d'acte — il n'y avait
 * donc rien à migrer, et rien à deviner.
 *
 * `nullOnDelete` comme `societe_id` : retirer une fiche du répertoire ne doit jamais emporter
 * les dossiers qui la citent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->foreignId('client_principal_id')
                ->nullable()
                ->after('societe_id')
                ->constrained('clients')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropForeign(['client_principal_id']);
            $table->dropColumn('client_principal_id');
        });
    }
};
