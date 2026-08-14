<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->date('date_signature_client')->nullable()->after('echeance');
            $table->date('date_signature_notaire')->nullable()->after('date_signature_client');
        });

        DB::table('dossiers')
            ->whereIn('etape', ['signature_client', 'signature_notaire'])
            ->update(['etape' => 'signature']);
            
        DB::table('dossiers')
            ->where('etape', 'initialisation')
            ->update(['etape' => 'edition']);
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropColumn(['date_signature_client', 'date_signature_notaire']);
        });

        DB::table('dossiers')
            ->where('etape', 'signature')
            ->update(['etape' => 'signature_client']); // Best effort fallback
    }
};
