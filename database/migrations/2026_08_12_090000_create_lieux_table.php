<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel des lieux — ville → commune → quartier.
 *
 * Le triplet ville/commune/quartier était en **saisie libre** à dix endroits (fiche client, fiche
 * société, sept blocs de questionnaire), et les valeurs déjà en base étaient incohérentes :
 * « Forecariah » enregistré comme commune alors que c'est une préfecture, « Kountia » comme quartier
 * de Conakry alors qu'il est à Dubréka.
 *
 * **Table auto-référencée** plutôt que trois tables : un seul écran d'administration, un seul point
 * d'entrée, un seul composant de cascade. Et la hiérarchie guinéenne est à profondeur variable —
 * Conakry → commune → quartier, mais préfecture → commune urbaine → district : un niveau
 * supplémentaire ne coûtera rien.
 *
 * ⚠️ Les questionnaires continuent de stocker le **nom** et non l'identifiant. C'est délibéré : les
 * balises `${soc.siege_quartier}` des modèles Word restent inchangées, aucune migration de
 * `questionnaires.donnees` n'est nécessaire, et un acte signé garde le nom qui était le bon ce
 * jour-là. Le référentiel sert à proposer les valeurs, pas à les référencer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lieux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('lieux')->cascadeOnDelete();
            $table->string('niveau', 20);
            $table->string('nom', 120);
            // Forme comparable (accents retirés, casse et espaces normalisés) — c'est elle qui porte
            // l'unicité, pour que « Forécariah » et « Forecariah » ne coexistent pas.
            $table->string('nom_normalise', 120);
            // Les quartiers amorcés au seeder ne peuvent pas être garantis exhaustifs ni à jour :
            // ils sont marqués pour que l'étude les valide, plutôt que présentés comme sûrs.
            $table->boolean('a_verifier')->default(false);
            $table->boolean('actif')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Un même nom peut exister sous deux parents distincts — « Matam » est une commune de
            // Conakry **et** une préfecture. L'unicité est donc relative au parent et au niveau.
            $table->unique(['parent_id', 'niveau', 'nom_normalise'], 'lieux_unicite_par_parent');
            $table->index(['niveau', 'actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lieux');
    }
};
