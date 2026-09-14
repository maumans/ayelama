<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Provenance d'un lieu du référentiel.
 *
 * Le référentiel mêle désormais deux origines : un import documenté (GeoNames, loi L2024/003) et
 * les ajouts de l'étude en pleine saisie. Sans cette colonne, un ré-import ne saurait plus
 * distinguer ce qu'il a lui-même écrit de ce qu'un clerc a corrigé à la main — et l'écraserait.
 *
 * C'est aussi ce qui rend le référentiel **auditable** : savoir d'où vient « Kanfarandé » et à
 * quelle date, sur une donnée qui figure ensuite dans des actes authentiques.
 *
 * `null` sur les lieux existants : ils viennent du seeder d'amorçage, antérieur à cette notion.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('lieux', 'source')) {
            return;
        }

        Schema::table('lieux', function (Blueprint $table) {
            $table->string('source', 60)->nullable()->after('a_verifier');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('lieux', 'source')) {
            return;
        }

        Schema::table('lieux', fn (Blueprint $table) => $table->dropColumn('source'));
    }
};
