<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vérification pièce par pièce avant clôture d'un dossier.
 *
 * Table polymorphe plutôt que deux colonnes ajoutées à chaque table concernée :
 * l'inventaire de clôture traverse trois modèles de stockage (`document_fichiers`,
 * `courriers`, `recus`). Poser des colonnes de clôture sur `recus` — qui n'a par
 * ailleurs rien à voir avec la clôture — serait déplacé, et une quatrième famille de
 * pièces exigerait une nouvelle migration.
 *
 * Enregistre `verifie_par_id` et `verifie_at` : en notarial, savoir QUI a contrôlé une
 * pièce et QUAND fait partie de la valeur probatoire du dossier. Dévérifier revient à
 * supprimer la ligne — réversible sans état intermédiaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cloture_verifications', function (Blueprint $table) {
            $table->id();
            // Dénormalisé volontairement : permet de compter les pièces vérifiées d'un
            // dossier sans résoudre chaque morph (un document peut appartenir au
            // dossier via une Partie ou une Formalite — voir dossierGouvernant()).
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->morphs('verifiable');
            $table->foreignId('verifie_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verifie_at');
            $table->timestamps();

            // Une pièce n'est vérifiée qu'une fois : la coche est un état, pas un journal.
            $table->unique(['verifiable_type', 'verifiable_id'], 'cloture_verif_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloture_verifications');
    }
};
