<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abandon de la clôture « configurée par type d'acte ».
 *
 * `obligatoire_cloture` déclarait à l'avance, modèle par modèle, ce qui devrait
 * exister à la clôture. Le workflow produit désormais lui-même toutes les pièces du
 * dossier (pièces d'identité à la création, actes à l'édition, accord client,
 * justificatifs de formalités, courriers d'expédition, reçus de paiement) : cette
 * déclaration dupliquait une vérité déjà connue, et pouvait la contredire — un modèle
 * coché obligatoire mais jamais généré bloquait la clôture sans recours.
 *
 * Remplacée par l'inventaire dérivé + vérification pièce par pièce (voir
 * InventaireClotureService et la table cloture_verifications).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modeles_actes', function (Blueprint $table) {
            $table->dropColumn('obligatoire_cloture');
        });

        Schema::table('modeles_courriers', function (Blueprint $table) {
            $table->dropColumn('obligatoire_cloture');
        });
    }

    public function down(): void
    {
        // Recréée à false : la notion n'existe plus côté application, la valeur
        // d'origine n'aurait aucun sens à restaurer.
        Schema::table('modeles_actes', function (Blueprint $table) {
            $table->boolean('obligatoire_cloture')->default(false);
        });

        Schema::table('modeles_courriers', function (Blueprint $table) {
            $table->boolean('obligatoire_cloture')->default(false);
        });
    }
};
