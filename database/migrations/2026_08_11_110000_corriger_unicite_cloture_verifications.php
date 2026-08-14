<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La vérification de clôture devient unique **par dossier**, et non plus par pièce.
 *
 * L'index d'origine portait sur `(verifiable_type, verifiable_id)` seuls, avec ce commentaire :
 * « Une pièce n'est vérifiée qu'une fois : la coche est un état, pas un journal. » C'était juste
 * tant qu'une pièce n'appartenait qu'à **un** dossier — ce qui était le cas de toutes les familles
 * alors inventoriées (documents du dossier, pièces des parties, pièces des formalités, courriers,
 * reçus).
 *
 * Les **pièces constitutives d'une société** (2026-08-11) rompent cette prémisse : elles vivent au
 * registre et sont partagées par tous les dossiers de la société. Or
 * `InventaireClotureService::verificationsIndexees()` filtre déjà `where('dossier_id', …)` : la
 * vérification est donc bien conçue comme étant par dossier. Sans cette migration, les statuts
 * vérifiés lors d'une première modification apparaîtraient non vérifiés lors de la deuxième, et
 * cliquer « vérifier » violerait l'index — clôture impossible, sur une erreur SQL opaque.
 *
 * `dossier_id`, dénormalisée dès l'origine « pour compter les pièces vérifiées d'un dossier sans
 * résoudre chaque morph », prend ici son second sens : elle fait partie de l'identité de la coche.
 *
 * Aucune donnée à migrer : les lignes existantes respectent la contrainte élargie, qui est
 * strictement plus permissive.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cloture_verifications', function (Blueprint $table) {
            $table->dropUnique('cloture_verif_unique');
            $table->unique(['dossier_id', 'verifiable_type', 'verifiable_id'], 'cloture_verif_unique');
        });
    }

    public function down(): void
    {
        // Retour arrière possible tant qu'aucune pièce partagée n'a été vérifiée dans deux
        // dossiers ; sinon l'ancien index ne peut pas être recréé. On ne tente pas de choisir
        // laquelle des lignes sacrifier — ce serait perdre la trace d'un contrôle notarial.
        Schema::table('cloture_verifications', function (Blueprint $table) {
            $table->dropUnique('cloture_verif_unique');
            $table->unique(['verifiable_type', 'verifiable_id'], 'cloture_verif_unique');
        });
    }
};
