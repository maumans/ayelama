<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            // Quantité appliquée par défaut à la ligne de facture générée automatiquement
            // à partir de ce barème (ex. nombre d'exemplaires, de pages) — ajustable ensuite
            // au cas par cas sur chaque dossier (voir FactureController::updateLigne()).
            $table->unsignedSmallInteger('quantite_defaut')->default(1)->after('montant_fixe');
        });
    }

    public function down(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            $table->dropColumn('quantite_defaut');
        });
    }
};
