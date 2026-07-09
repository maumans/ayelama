<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            // Un barème peut, en plus de servir à la facturation, générer automatiquement
            // une Formalite à la création du dossier (fusion avec l'ancien FormaliteType).
            $table->boolean('genere_formalite')->default(false)->after('actif');
            $table->boolean('obligatoire')->default(true)->after('genere_formalite');
            $table->string('type_impot')->nullable()->after('obligatoire');
            $table->string('retour_attendu')->nullable()->after('type_impot');
            $table->unsignedInteger('delai_heures')->nullable()->after('retour_attendu');
            $table->json('pieces_requises')->nullable()->after('delai_heures');
        });
    }

    public function down(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            $table->dropColumn([
                'genere_formalite', 'obligatoire', 'type_impot',
                'retour_attendu', 'delai_heures', 'pieces_requises',
            ]);
        });
    }
};
