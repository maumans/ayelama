<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Valeurs par défaut
        DB::table('settings')->insert([
            ['key' => 'office_nom',        'value' => 'Maître Ayelama Bah',  'created_at' => now(), 'updated_at' => now()],
            ['key' => 'office_sous_titre', 'value' => 'Notaire',              'created_at' => now(), 'updated_at' => now()],
            ['key' => 'couleur_primaire',  'value' => '#0F2D60',              'created_at' => now(), 'updated_at' => now()],
            ['key' => 'couleur_accent',    'value' => '#E8A520',              'created_at' => now(), 'updated_at' => now()],
            ['key' => 'couleur_fond',      'value' => '#F5F5F3',              'created_at' => now(), 'updated_at' => now()],
            ['key' => 'logo_path',         'value' => null,                   'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
