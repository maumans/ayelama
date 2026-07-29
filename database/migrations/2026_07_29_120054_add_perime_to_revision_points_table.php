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
        Schema::table('revision_points', function (Blueprint $table) {
            // Le document a été régénéré depuis son verdict (ex. questionnaire modifié) —
            // le verdict/commentaire est conservé pour affichage (contexte pour le
            // rédacteur) mais n'est plus compté comme évalué : le certificateur doit
            // le réexaminer au prochain envoi en certification.
            $table->boolean('perime')->default(false)->after('commentaire');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('revision_points', function (Blueprint $table) {
            $table->dropColumn('perime');
        });
    }
};
