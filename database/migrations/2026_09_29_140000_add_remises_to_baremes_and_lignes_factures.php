<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remises sur les lignes de facture — voir {@see \App\Enums\TypeRemise}.
 *
 * Trois ajouts, trois raisons distinctes :
 *
 *   - **`baremes.remise_autorisee`** : toutes les lignes ne sont pas remisables. Mesuré le
 *     2026-09-29, la grille compte 16 barèmes d'organisme « Notaire » — les honoraires de
 *     l'étude — contre **74 débours** versés à des tiers (impôts, greffe, APIP, conservation,
 *     journal). Remiser un débours n'entame pas une marge, cela fait perdre de l'argent réel :
 *     l'étude paie 180 000 GNF au greffe quoi qu'il arrive. Le défaut est donc « non
 *     remisable », levé par la migration de données sur les seuls honoraires.
 *
 *   - **`lignes_factures.bareme_id`** : une ligne ne savait pas d'où elle venait. Il le faut
 *     pour deux choses — savoir si elle est remisable, et **retrouver sa remise après une
 *     régénération**. `FacturationService::genererFacture()` fait `$dossier->factures()
 *     ->delete()` puis reconstruit : sans cette clé, une remise saisie à la main disparaîtrait
 *     à la première régénération, en silence.
 *
 *   - **`remise_type` + `remise_valeur`** : la remise telle que l'utilisateur l'a exprimée, et
 *     non son résultat. Une remise en pourcentage suit un tarif qui change, une remise en
 *     montant non — stocker le montant calculé perdrait cette distinction.
 *
 * ⚠️ Le montant de la remise n'est **pas** stocké : il se calcule depuis le type et la valeur.
 * Une seule source de vérité, sinon les deux divergent au premier changement de tarif.
 *
 * MySQL en développement, SQLite en test : un `Schema::table()` par table, et la clé étrangère
 * en `nullOnDelete` — supprimer un barème ne doit pas emporter une ligne de facture émise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            $table->boolean('remise_autorisee')->default(false)->after('quantite_defaut');
        });

        Schema::table('lignes_factures', function (Blueprint $table) {
            $table->foreignId('bareme_id')->nullable()->after('facture_id')
                ->constrained('baremes')->nullOnDelete();
            // ⚠️ Le droit de remiser est **figé sur la ligne**, pas relu du barème à chaque
            // affichage. Deux raisons : une facture émise ne doit pas changer de règle parce
            // qu'un tarif a été reconfiguré depuis (même doctrine que `donnees_au_retour`), et
            // une ligne ajoutée à la main n'a aucun barème dont hériter. Sans ce drapeau, une
            // ligne sans barème serait soit toujours remisable — y compris les débours des
            // factures anciennes —, soit jamais, ce qui bloquerait les ajouts manuels.
            $table->boolean('remise_autorisee')->default(false)->after('montant');
            $table->string('remise_type', 20)->nullable()->after('remise_autorisee');
            $table->decimal('remise_valeur', 15, 2)->nullable()->after('remise_type');
        });
    }

    public function down(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            $table->dropColumn('remise_autorisee');
        });

        Schema::table('lignes_factures', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bareme_id');
        });

        Schema::table('lignes_factures', function (Blueprint $table) {
            $table->dropColumn(['remise_autorisee', 'remise_type', 'remise_valeur']);
        });
    }
};
