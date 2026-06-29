<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('clerc')->after('email');
            $table->string('initiales', 4)->nullable()->after('role');
            $table->string('telephone', 25)->nullable()->after('initiales');
            $table->string('avatar')->nullable()->after('telephone');
            $table->boolean('actif')->default(true)->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'initiales', 'telephone', 'avatar', 'actif']);
        });
    }
};
