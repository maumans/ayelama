<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration des actes à la granularité de la **procédure**, et non du type d'acte.
 *
 * Le pivot `modele_acte_type_acte` (créé la veille) rattachait un gabarit à plusieurs types
 * d'actes. Insuffisant pour la société : `SOC-MOD` se décline en sept résolutions, et les actes se
 * recoupent partiellement — un procès-verbal sert toutes les modifications, un acte de cession ne
 * sert qu'aux cessions. Trois manques comblés ici :
 *
 *   1. `modele_acte_rattachements` — le pivot devient une **entité** portant une `variante`
 *      (nullable = toutes les variantes du type). Un `hasMany` plutôt qu'un `belongsToMany` : une
 *      colonne de pivot porteuse de sens se manipule mal en `sync()`, et le rattachement est
 *      désormais un objet qu'on affiche et qu'on édite.
 *   2. `modele_acte_roles` — un gabarit peut remplir **plusieurs rôles**. Le vocabulaire
 *      `type_document` diffère entre procédures pour un même document (`acte_principal` en création,
 *      `statuts_maj` en modification), si bien que partager un modèle ne suffisait pas : constaté
 *      sur le RCCM, dont le partage n'avait rien produit.
 *   3. `documents_attendus` — la liste des documents exigés par procédure, **éditable**. L'enum
 *      `TypeModificationStatutaire` reste la référence datée du CR de juillet 2026 et sert de seed ;
 *      cette table en est la configuration effective.
 *
 * ⚠️ **Aucun changement de comportement au déploiement** : les rattachements existants sont repris
 * avec `variante = null`, les rôles avec le `type_document` courant, et `documents_attendus` reste
 * vide — les services replient sur l'enum tant qu'aucune ligne n'existe.
 *
 * `ModeleCourrier` garde son pivot simple : une lettre de transmission ne se décline pas par
 * résolution. Asymétrie voulue, ne pas « corriger ».
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Rattachements avec variante ───────────────────────────────────
        Schema::create('modele_acte_rattachements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modele_acte_id')->constrained('modeles_actes')->cascadeOnDelete();
            $table->foreignId('type_acte_id')->constrained('types_actes')->cascadeOnDelete();
            // null = applicable à toutes les variantes du type d'acte (ou type sans variante).
            $table->string('variante')->nullable();
            $table->timestamps();

            $table->unique(['modele_acte_id', 'type_acte_id', 'variante'], 'rattachement_unique');
        });

        if (Schema::hasTable('modele_acte_type_acte')) {
            DB::table('modele_acte_type_acte')->orderBy('modele_acte_id')->chunk(200, function ($lignes) {
                DB::table('modele_acte_rattachements')->insertOrIgnore(
                    $lignes->map(fn ($l) => [
                        'modele_acte_id' => $l->modele_acte_id,
                        'type_acte_id'   => $l->type_acte_id,
                        'variante'       => null,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ])->all(),
                );
            });

            Schema::drop('modele_acte_type_acte');
        }

        // ── 2. Rôles remplis par un gabarit ──────────────────────────────────
        Schema::create('modele_acte_roles', function (Blueprint $table) {
            $table->foreignId('modele_acte_id')->constrained('modeles_actes')->cascadeOnDelete();
            $table->string('type_document');

            $table->unique(['modele_acte_id', 'type_document']);
        });

        // `type_document` reste le rôle principal (affichage, tri, seeders) : on le reprend comme
        // premier rôle, sans quoi tous les modèles cesseraient d'être retenus.
        DB::table('modeles_actes')
            ->whereNotNull('type_document')
            ->orderBy('id')
            ->select('id', 'type_document')
            ->chunk(200, function ($modeles) {
                DB::table('modele_acte_roles')->insertOrIgnore(
                    $modeles->map(fn ($m) => [
                        'modele_acte_id' => $m->id,
                        'type_document'  => $m->type_document,
                    ])->all(),
                );
            });

        // ── 3. Documents attendus, par procédure ─────────────────────────────
        Schema::create('documents_attendus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('type_acte_id')->constrained('types_actes')->cascadeOnDelete();
            $table->string('variante')->nullable();
            $table->string('type_document');
            $table->boolean('obligatoire')->default(true);
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->unique(['type_acte_id', 'variante', 'type_document'], 'document_attendu_unique');
        });

        // ── 4. Colonne morte ─────────────────────────────────────────────────
        // `actes_requis` n'était lue nulle part et vide sur les 30 types : vestige de l'approche
        // « documents obligatoires configurés par type d'acte », abandonnée pour la clôture le
        // 2026-08-04. La laisser invitait à la recâbler un jour en concurrence de
        // `documents_attendus`.
        if (Schema::hasColumn('types_actes', 'actes_requis')) {
            Schema::table('types_actes', function (Blueprint $table) {
                $table->dropColumn('actes_requis');
            });
        }
    }

    public function down(): void
    {
        Schema::table('types_actes', function (Blueprint $table) {
            $table->json('actes_requis')->nullable();
        });

        Schema::dropIfExists('documents_attendus');
        Schema::dropIfExists('modele_acte_roles');

        // Restauration du pivot simple, en perdant les variantes : elles n'y ont pas de place.
        Schema::create('modele_acte_type_acte', function (Blueprint $table) {
            $table->foreignId('modele_acte_id')->constrained('modeles_actes')->cascadeOnDelete();
            $table->foreignId('type_acte_id')->constrained('types_actes')->cascadeOnDelete();
            $table->unique(['modele_acte_id', 'type_acte_id']);
        });

        DB::table('modele_acte_rattachements')->orderBy('id')->chunk(200, function ($lignes) {
            DB::table('modele_acte_type_acte')->insertOrIgnore(
                $lignes->map(fn ($l) => [
                    'modele_acte_id' => $l->modele_acte_id,
                    'type_acte_id'   => $l->type_acte_id,
                ])->all(),
            );
        });

        Schema::dropIfExists('modele_acte_rattachements');
    }
};
