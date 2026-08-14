<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un modèle d'acte peut servir **plusieurs** types d'actes.
 *
 * `modeles_actes.type_acte_id` limitait chaque gabarit à un seul type. Conséquence constatée sur
 * `SOC-2026-0013` : les statuts, la DNSV, l'attestation et le RCCM chargés pour `SOC-SARLU` — fichiers
 * présents et actifs — étaient **invisibles** depuis un dossier de modification, qui n'avait donc
 * aucun acte à produire. L'étude devait recharger les mêmes fichiers sous un autre type, puis
 * maintenir les deux copies en parallèle.
 *
 * Calqué sur `modele_courrier_type_acte` (2026-07-10), qui résout déjà exactement ce problème pour
 * les lettres de transmission : pivot + `applicable_tous`. Un second mécanisme aurait divergé.
 *
 * ⚠️ `type_acte_id` **est conservée** : elle reste le « type d'origine » d'un modèle (affichage,
 * groupage, seeders). C'est le pivot qui décide de l'applicabilité — voir
 * {@see \App\Models\ModeleActe::applicablePour()}. Le backfill garantit qu'aucun comportement ne
 * change tant que personne n'ajoute de type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modele_acte_type_acte', function (Blueprint $table) {
            $table->foreignId('modele_acte_id')->constrained('modeles_actes')->cascadeOnDelete();
            $table->foreignId('type_acte_id')->constrained('types_actes')->cascadeOnDelete();
            $table->unique(['modele_acte_id', 'type_acte_id']);
        });

        Schema::table('modeles_actes', function (Blueprint $table) {
            $table->boolean('applicable_tous')->default(false)->after('type_acte_id');
        });

        // Backfill : chaque modèle reste applicable exactement à son type actuel. Sans cela, la
        // migration couperait la génération d'actes de tous les dossiers en cours.
        DB::table('modeles_actes')
            ->whereNotNull('type_acte_id')
            ->orderBy('id')
            ->select('id', 'type_acte_id')
            ->chunk(200, function ($modeles) {
                DB::table('modele_acte_type_acte')->insertOrIgnore(
                    $modeles->map(fn ($m) => [
                        'modele_acte_id' => $m->id,
                        'type_acte_id'   => $m->type_acte_id,
                    ])->all(),
                );
            });
    }

    public function down(): void
    {
        Schema::table('modeles_actes', function (Blueprint $table) {
            $table->dropColumn('applicable_tous');
        });

        Schema::dropIfExists('modele_acte_type_acte');
    }
};
