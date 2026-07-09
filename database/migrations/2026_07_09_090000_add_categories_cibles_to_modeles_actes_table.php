<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modeles_actes', function (Blueprint $table) {
            $table->json('categories_cibles')->nullable()->after('type_document');
        });
    }

    public function down(): void
    {
        Schema::table('modeles_actes', function (Blueprint $table) {
            $table->dropColumn('categories_cibles');
        });
    }
};
