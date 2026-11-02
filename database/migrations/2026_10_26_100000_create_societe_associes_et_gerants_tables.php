<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les associés et les gérants d'une société, datés — l'état courant de sa composition.
 *
 * **Le trou que ces tables comblent.** Le registre savait tout d'une société sauf qui la
 * détient : `Societe::associesConnus()` relisait les `parties` du dossier de **constitution**,
 * donc rendait les associés d'origine pour toujours, et
 * `SocieteMutationService::changementsPour(CapitalCession)` rendait **un tableau vide** —
 * une cession de parts ne portait strictement rien au registre. Le commentaire l'assumait :
 * « le registre ne tient pas de table des associés ». Il en tient une.
 *
 * Addendum v1.2 § 4.1 (`societe_associes`, `societe_gerants`), adapté :
 *
 * ⚠️ **`client_id` est nullable**, là où la spec l'exige. Mesuré avant d'écrire : des `parties`
 * existent sans fiche client, et c'est précisément d'elles que le backfill doit partir. La table
 * `parties` fait déjà ce choix — `nom` porté en clair, `client_id` facultatif — et s'en écarter
 * ici aurait rendu les 15 fiches existantes inreprenables.
 *
 * ⚠️ Pas de `tenant_id` : l'application est mono-étude. Pas d'UUID : le dépôt est en entiers.
 * La spec écrit son schéma en PostgreSQL et prévoit elle-même la traduction (§ 1, ligne « Stack »).
 *
 * **Une ligne par passage**, pas une par personne : `date_sortie` nulle signifie « associé
 * actuel ». Un associé qui revient a deux lignes, et c'est voulu — l'historique de détention se
 * lit dans la table, pas dans un journal à côté.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('societe_associes', function (Blueprint $table) {
            $table->id();

            // La fiche disparaît, sa composition n'a plus d'objet.
            $table->foreignId('societe_id')->constrained('societes')->cascadeOnDelete();

            // Voir le docblock : facultatif, et le nom reste porté en clair.
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('nom', 200);

            $table->integer('nb_parts');

            $table->date('date_entree');
            // NULL = associé actuel. C'est ce qui distingue l'état courant de l'historique.
            $table->date('date_sortie')->nullable();

            // ⚠️ `nullOnDelete` et non `cascade` : supprimer un dossier ne doit pas effacer la
            // trace de qui détenait quoi — c'est le défaut de `journal_activites`, dont la
            // cascade empêche de parler d'historique.
            $table->foreignId('dossier_entree_id')->nullable()->constrained('dossiers')->nullOnDelete();
            $table->foreignId('dossier_sortie_id')->nullable()->constrained('dossiers')->nullOnDelete();

            $table->timestamps();

            // Pas d'unicité (client_id, date_entree) comme la spec : `client_id` étant nullable,
            // MySQL laisserait passer autant de NULL qu'on veut — la contrainte mentirait. Un
            // index pour la lecture, et la règle « un associé actuel par personne » tenue par
            // le service, qui sait aussi fermer la ligne précédente.
            $table->index(['societe_id', 'date_sortie']);
        });

        Schema::create('societe_gerants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('societe_id')->constrained('societes')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('nom', 200);

            // Statutaire = nommé dans les statuts. Le changer modifie les statuts (AGE), là où
            // nommer un gérant non statutaire relève d'une AGO (§ 2 du glossaire de la spec).
            $table->boolean('statutaire')->default(true);

            $table->date('date_nomination');
            $table->date('date_fin')->nullable();

            // Chaîne libre plutôt qu'énumérée : la spec propose cinq motifs, mais la liste n'est
            // pas arrêtée et une contrainte inventée est pire qu'une contrainte absente.
            $table->string('motif_fin', 30)->nullable();

            $table->foreignId('dossier_nomination_id')->nullable()->constrained('dossiers')->nullOnDelete();
            $table->foreignId('dossier_fin_id')->nullable()->constrained('dossiers')->nullOnDelete();

            $table->timestamps();

            $table->index(['societe_id', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('societe_gerants');
        Schema::dropIfExists('societe_associes');
    }
};
