<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D'où vient un tarif, et l'étude l'a-t-elle confirmé ?
 *
 * **Pourquoi une colonne plutôt qu'une mention dans `description`.** La description est un texte
 * libre que l'écran d'administration laisse réécrire : une convention d'écriture y disparaît à la
 * première correction, sans bruit. La provenance d'un montant qui part sur une facture
 * d'étude mérite mieux qu'une convention — c'est la règle 2 du dépôt, un garde-fou exécutable
 * plutôt qu'un commentaire.
 *
 * ⚠️ **Mesuré avant d'être posé, et c'est ce qui a motivé la colonne.** Les notes de frais que
 * l'étude a fournies (`Documents reçus`, une note de frais par forme) donnent une grille que la
 * base ne reflétait pas :
 *
 *  - honoraires de constitution : 4 500 000 GNF en SARL et SARLU, **8 500 000** en SAS et SASU —
 *    la base appliquait 4 500 000 aux sept formes ;
 *  - frais APIP et RCCM : **390 000** en SARL et SARLU, 490 000 en SAS et SASU — la base
 *    appliquait 490 000 partout ;
 *  - timbres et rôles : facturés **à la page** (2 000 et 5 000 GNF, relevés sur une note réelle
 *    de 76 pages) — la base posait un forfait de 50 000 GNF qui n'apparaît sur aucune note ;
 *  - vente : la note de l'étude ne porte **aucun** enregistrement APIP, que la base facturait
 *    150 000 GNF sur les quatre types de vente ;
 *  - vente sans titre foncier : pas de frais de mutation à la Conservation Foncière, que la base
 *    facturait quand même — il n'y a pas de titre à muter.
 *
 * Trois états, et la distinction compte :
 *
 *  - `source` renseignée et `a_confirmer` faux → le montant vient d'un document de l'étude ;
 *  - `a_confirmer` vrai → aucun document ne le donne, **et aucun ne le contredit**. Le tarif
 *    reste facturable, mais l'écran et la note de frais le signalent ;
 *  - ligne désactivée → un document de l'étude la **contredit**. On ne facture pas contre une
 *    note de frais fournie par l'étude elle-même.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            // `text` et non `string` : le motif d'une désactivation explique pourquoi un
            // montant a cessé d'être facturé, en citant la note de frais qui le contredit.
            // 255 caractères tronquaient la moitié des motifs — MySQL l'a refusé, ce qui
            // valait mieux qu'une explication coupée au milieu.
            $table->text('source')->nullable()->after('description');
            $table->boolean('a_confirmer')->default(false)->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            $table->dropColumn(['source', 'a_confirmer']);
        });
    }
};
