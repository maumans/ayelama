<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Les étapes signature_client et signature_notaire sont supprimées du workflow.
        // Les dossiers qui s'y trouvaient passent directement en formalites.
        DB::table('dossiers')
            ->whereIn('etape', ['signature_client', 'signature_notaire'])
            ->update(['etape' => 'formalites']);
    }

    public function down(): void
    {
        // Pas de rollback sans connaître l'état initial de chaque dossier.
    }
};
