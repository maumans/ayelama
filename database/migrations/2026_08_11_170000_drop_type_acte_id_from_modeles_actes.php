<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `modeles_actes.type_acte_id` disparaît : les rattachements sont la seule vérité.
 *
 * La colonne était le « type d'acte d'origine » d'un gabarit, à l'époque où chacun n'en servait
 * qu'un. Depuis que `modele_acte_rattachements` porte l'applicabilité — variantes comprises —, elle
 * ne décidait plus de rien : ni `ModeleActe::applicablePour()` ni `scopePourTypeActe()` ne la
 * lisent. Elle ne survivait que par une saisie en double dans la modale, un `synchroniserOrigine()`
 * qui la recopiait depuis le premier rattachement, et une clé de seeder.
 *
 * Son **seul** consommateur métier était `TypeActe::modeles()`, qui alimentait la carte « Actes à
 * produire » de l'assistant — et l'alimentait faux : un gabarit partagé n'y figurait pas, un
 * gabarit détaché y restait, et les variantes étaient ignorées. Cette carte passe désormais par
 * `ActesGeneratorService::actesPrevus()`.
 *
 * Aucune donnée perdue : la migration `create_modele_acte_type_acte_table` avait créé un
 * rattachement pour chaque modèle depuis cette colonne. Un garde-fou le vérifie avant de supprimer,
 * plutôt que de faire confiance à une migration antérieure.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orphelins = DB::table('modeles_actes')
            ->whereNotNull('type_acte_id')
            ->whereNotExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('modele_acte_rattachements')
                ->whereColumn('modele_acte_rattachements.modele_acte_id', 'modeles_actes.id')
                ->whereColumn('modele_acte_rattachements.type_acte_id', 'modeles_actes.type_acte_id'))
            ->where('applicable_tous', false)
            ->count();

        // Ne jamais supprimer une colonne dont l'information n'a pas été reprise ailleurs : mieux
        // vaut une migration qui échoue lisiblement qu'un gabarit devenu inapplicable en silence.
        if ($orphelins > 0) {
            throw new RuntimeException(
                "{$orphelins} modèle(s) n'ont pas de rattachement correspondant à leur type d'origine. "
                . "Relancez la migration create_modele_acte_type_acte_table avant celle-ci."
            );
        }

        // Trois temps distincts, imposés par les deux moteurs à la fois :
        //   - MySQL refuse de supprimer l'index composite `[type_acte_id, est_actif]` tant que la
        //     clé étrangère s'appuie dessus ;
        //   - SQLite (base de test) refuse de supprimer une colonne encore référencée par un index
        //     ou par une contrainte.
        // Contrainte, puis index, puis colonne : le seul ordre qui satisfasse les deux.
        Schema::table('modeles_actes', function (Blueprint $table) {
            $table->dropForeign(['type_acte_id']);
            $table->dropIndex(['type_acte_id', 'est_actif']);
        });

        Schema::table('modeles_actes', function (Blueprint $table) {
            $table->dropColumn('type_acte_id');
        });

        Schema::table('modeles_actes', function (Blueprint $table) {
            $table->index('est_actif');
        });
    }

    public function down(): void
    {
        Schema::table('modeles_actes', function (Blueprint $table) {
            $table->dropIndex(['est_actif']);
            $table->foreignId('type_acte_id')->nullable()->after('id')->constrained('types_actes')->cascadeOnDelete();
            $table->index(['type_acte_id', 'est_actif']);
        });

        // Restaurée depuis le premier rattachement — la notion d'« origine » n'ayant jamais eu
        // d'autre définition que celle-là.
        foreach (DB::table('modele_acte_rattachements')->orderBy('id')->get() as $r) {
            DB::table('modeles_actes')
                ->where('id', $r->modele_acte_id)
                ->whereNull('type_acte_id')
                ->update(['type_acte_id' => $r->type_acte_id]);
        }
    }
};
