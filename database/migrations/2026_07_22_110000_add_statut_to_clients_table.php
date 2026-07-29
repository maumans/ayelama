<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Un client ajouté pendant la création d'un dossier n'est qu'un prospect tant
            // que le dossier n'a pas abouti — il devient client confirmé une fois le dossier
            // clôturé (voir DossierStepService::avancer()).
            $table->string('statut', 20)->default('prospect')->after('type');
        });

        // Backfill : les clients déjà liés à un dossier déjà clôturé avant ce correctif ne
        // doivent pas rester "prospect" pour toujours — la promotion automatique ajoutée dans
        // DossierStepService ne s'applique qu'aux transitions futures.
        DB::table('clients')
            ->whereIn('id', function ($query) {
                $query->select('parties.client_id')
                    ->from('parties')
                    ->join('dossiers', 'dossiers.id', '=', 'parties.dossier_id')
                    ->whereNotNull('parties.client_id')
                    ->where('dossiers.etape', 'cloture');
            })
            ->update(['statut' => 'client']);
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('statut');
        });
    }
};
