<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un barème peut désormais ne s'appliquer qu'à **certaines** modifications statutaires.
 *
 * Le besoin est né d'un défaut constaté : `ReglesGestionBaremeSeeder` posait les quatre
 * tarifs de modification (statuts mis à jour, procès-verbal, DNSV, RCCM) sur **tous** les
 * types d'acte de société. Une constitution de SARLU se voyait donc facturer
 * « Enregistrement des statuts *mis à jour* », et un dossier de modification portant un
 * simple transfert de siège se voyait facturer une DNSV — alors qu'aucune souscription n'a
 * lieu (voir TypeModificationStatutaire::exigeDnsv()).
 *
 * `null` (le défaut) = barème inconditionnel, comportement actuel de tous les barèmes
 * existants : cette migration ne change donc aucune facturation en place. Sinon, la valeur
 * est un `TypeModificationStatutaire` (valeur technique, ex. `capital_augmentation`) et le
 * barème n'est retenu que si le dossier porte ce type de modification.
 *
 * Chaîne libre plutôt que colonne contrainte : un enum SQL obligerait à une migration à
 * chaque cas ajouté, et la valeur est déjà validée à la lecture par
 * `TypeModificationStatutaire::tryFrom()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            $table->string('condition_modification', 50)
                ->nullable()
                ->after('base_calcul');
        });
    }

    public function down(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            $table->dropColumn('condition_modification');
        });
    }
};
