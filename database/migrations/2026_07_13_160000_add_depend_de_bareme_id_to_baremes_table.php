<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            // Permet de définir, pour un même type d'acte, qu'une démarche (ex. Greffe)
            // ne peut être déposée qu'après le retour d'une autre démarche (ex. APIP RCCM).
            $table->foreignId('depend_de_bareme_id')->nullable()->after('genere_formalite')
                ->constrained('baremes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('depend_de_bareme_id');
        });
    }
};
