<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surcharge par l'étude du niveau d'exigence de la pièce d'accord — voir
 * {@see \App\Support\AccordsInitialisation} et {@see \App\Enums\ExigenceAccord}.
 *
 * **Nullable, et c'est le point.** `null` signifie « suivre la référence déclarée en code », pas
 * « aucune exigence ». Sans cette distinction, on ne saurait pas départager une étude qui n'a
 * rien décidé d'une étude qui a décidé la même chose — donc on ne pourrait pas signaler un écart
 * à la référence, ce que fait déjà `DocumentAttendu::divergeDeLaReference()` pour les documents
 * attendus.
 *
 * ⚠️ Cette table compte déjà **deux colonnes mortes** : `fiche_modification_obligatoire`
 * (déclarée, castée, seedée à `true` pour SOC-MOD — aucun lecteur) et `actes_requis` (dans
 * `$fillable` et `$casts`, mais **la colonne n'existe pas**). `societes.actif` vient d'être
 * supprimée pour la même raison. Celle-ci ne doit pas les rejoindre : elle a un lecteur
 * (`AccordsInitialisation::pour()`), un écran (Paramètres → Types d'actes) et un test qui
 * vérifie qu'une surcharge change effectivement le comportement du service.
 *
 * Pas d'index : la table compte 24 lignes, toutes chargées d'un bloc par l'écran de paramètres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('types_actes', function (Blueprint $table) {
            $table->string('exigence_accord', 20)
                ->nullable()
                ->after('fiche_modification_obligatoire');
        });
    }

    public function down(): void
    {
        Schema::table('types_actes', function (Blueprint $table) {
            $table->dropColumn('exigence_accord');
        });
    }
};
