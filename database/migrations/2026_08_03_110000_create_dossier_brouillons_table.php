<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Brouillons de l'assistant de création de dossier.
     *
     * Volontairement une table dédiée plutôt qu'un `Dossier` en étape « brouillon » :
     * la référence notariale ({PREFIXE}-{ANNÉE}-{XXXX}) est attribuée à la création
     * du dossier, et un brouillon abandonné laisserait un trou définitif dans la
     * numérotation. Un brouillon n'est donc pas un dossier — c'est un formulaire en
     * cours de saisie.
     */
    public function up(): void
    {
        Schema::create('dossier_brouillons', function (Blueprint $table) {
            $table->id();
            // Un brouillon appartient à son auteur : c'est sa saisie inachevée, pas
            // une information du cabinet. Supprimé avec le compte.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Nullable : l'assistant permet d'enregistrer avant d'avoir arrêté le
            // type d'acte. nullOnDelete pour ne pas perdre la saisie si un type
            // d'acte est retiré du paramétrage entre-temps.
            $table->foreignId('type_acte_id')->nullable()->constrained('types_actes')->nullOnDelete();
            // Libellé d'affichage dans la liste de reprise (objet saisi, ou à défaut
            // le label du type d'acte) — évité en calcul pour rester lisible même si
            // le type d'acte a disparu.
            $table->string('libelle')->nullable();
            // État complet de l'assistant : étape courante, valeurs du questionnaire,
            // clients rattachés, assignations, et chemins des pièces déjà téléversées.
            $table->json('etat');
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossier_brouillons');
    }
};
