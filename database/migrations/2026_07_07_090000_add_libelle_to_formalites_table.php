<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formalites', function (Blueprint $table) {
            $table->string('libelle')->nullable()->after('organisme');
        });
    }

    public function down(): void
    {
        Schema::table('formalites', function (Blueprint $table) {
            $table->dropColumn('libelle');
        });
    }
};
