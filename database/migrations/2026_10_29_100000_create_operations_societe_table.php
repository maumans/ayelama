<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue des types d'opération sur les sociétés — addendum v1.2 § 5 (Tableaux 9 et 10).
 *
 * Porte les onze opérations de référence du cycle de vie des sociétés (neuf modifications
 * statutaires et deux phases de dissolution), ainsi que les opérations hors périmètre V1.
 *
 * ⚠️ **Règles configurables sans redéploiement.** Les enums {@see \App\Enums\TypeModificationStatutaire}
 * et {@see \App\Enums\VarianteDissolution} portent les règles de référence compilées avec des `match`
 * exhaustifs. Cette table permet à l'étude d'ajuster les règles (ex: assemblée, RCCM, JAL)
 * directement en base, et {@see \App\Models\OperationSociete::divergeDeLaReference()} signale
 * tout écart par rapport à la référence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_societe', function (Blueprint $table) {
            $table->id();

            // Code officiel de la spec (ex: 'MOD_DENOMINATION', 'CESSION_PARTS', 'DISSOLUTION')
            $table->string('code', 32)->unique();

            // Type d'acte associé ('SOC-MOD' ou 'SOC-DIS')
            $table->string('type_acte_code', 16);

            // Variante technique interne si applicable (ex: 'denomination_sociale', 'dissolution_anticipee')
            $table->string('variante', 64)->nullable();

            // Libellé officiel affiché à l'utilisateur
            $table->string('libelle', 150);

            // Type d'assemblée requise : 'AGO', 'AGE', 'AG_CLOTURE'
            $table->string('assemblee', 16);

            // Impacts juridiques et formalités
            $table->boolean('impacte_statuts')->default(false);
            $table->boolean('impacte_rccm')->default(false);
            $table->boolean('publication_jal')->default(false);

            // Règle C1 : Ne se combine avec aucune autre opération dans le même dossier
            $table->boolean('exclusive')->default(false);

            // Statut de la règle : validée par l'étude (true) ou déduite / à confirmer (false)
            $table->boolean('regle_validee')->default(true);

            // Précision documentaire (ex: 'règles de majorité inconnues', 'RCCM et JAL à confirmer')
            $table->text('description_statut')->nullable();

            // Actif en V1 ou réservé pour évolutions futures (Tableau 10)
            $table->boolean('est_actif')->default(true);

            // Ordre d'affichage
            $table->smallInteger('ordre')->default(0);

            $table->timestamps();

            $table->index(['type_acte_code', 'est_actif']);
            $table->index('ordre');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_societe');
    }
};

