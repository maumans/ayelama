<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            $table->dropColumn('obligatoire');
        });
    }

    public function down(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            $table->boolean('obligatoire')->default(true)->after('genere_formalite');
        });
    }
};
