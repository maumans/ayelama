<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('document_fichiers', function (Blueprint $table) {
            $table->unsignedInteger('numero_acte')->nullable()->after('statut');
            $table->string('reference_acte')->nullable()->after('numero_acte');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_fichiers', function (Blueprint $table) {
            $table->dropColumn(['numero_acte', 'reference_acte']);
        });
    }
};
