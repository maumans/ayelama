<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modeles_courriers', function (Blueprint $table) {
            $table->boolean('obligatoire_cloture')->default(false)->after('applicable_tous');
        });
    }

    public function down(): void
    {
        Schema::table('modeles_courriers', function (Blueprint $table) {
            $table->dropColumn('obligatoire_cloture');
        });
    }
};
