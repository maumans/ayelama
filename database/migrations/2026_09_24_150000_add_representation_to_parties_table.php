<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La représentation d'une partie à l'acte : procuration, tutelle, représentant légal.
     *
     * Le représenté **reste la partie** — c'est lui l'associé, c'est lui qui souscrit. Le
     * représentant comparaît « ès qualités ». Ces colonnes décrivent donc un *mode de
     * comparution*, pas une personne de plus.
     *
     * ⚠️ **Le lien est porté par le représenté, et c'est le choix structurant.** Un même
     * mandataire porte couramment les pouvoirs de plusieurs mandants du même acte (trois
     * associés à l'étranger, une seule personne qui comparaît) : la relation est *N représentés
     * → 1 représentant*. Sur le représentant, représenter trois personnes exigerait trois
     * lignes `parties` pour le même homme — trois jeux de pièces, trois fois la même CNI en
     * GED, et un `piecesReprenables()` qui se proposerait à lui-même. Et le titre (date, forme,
     * autorité) est propre à **chaque** mandat : une ligne, un mandat.
     *
     * Le représentant, lui, n'occupe **aucun** emplacement du questionnaire
     * (`donnees_prefixe`/`donnees_bloc` restent nuls) : il ne peut donc pas écraser la
     * projection du représenté. Son identité est projetée dans un sous-espace dérivé de
     * l'emplacement de celui-ci — voir ClientProjectionService.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            // Le représentant. `nullOnDelete` et non `cascade` : supprimer le mandataire ne
            // doit jamais emporter l'associé. Le dossier dégrade vers un état **bruyant**
            // (« représentation déclarée sans représentant »), que ReglesRepresentationService
            // signale, plutôt que de perdre une partie à l'acte en silence.
            $table->foreignId('represente_par_partie_id')
                ->nullable()
                ->after('donnees_index')
                ->constrained('parties')
                ->nullOnDelete();

            // App\Enums\MotifRepresentation
            $table->string('representation_motif', 20)->nullable()->after('represente_par_partie_id');

            // « tuteur », « curateur », « Directeur Général »… Qualité **dans ce mandat**, et
            // non de la personne : le même homme est gérant ici et tuteur là. C'est ce que
            // `clients.representant_qualite` — un attribut de fiche — ne pouvait pas exprimer.
            $table->string('representation_qualite', 100)->nullable()->after('representation_motif');

            // App\Enums\FormeTitreRepresentation — commande la rédaction de la comparution.
            $table->string('representation_titre_forme', 30)->nullable()->after('representation_qualite');
            $table->date('representation_titre_date')->nullable()->after('representation_titre_forme');
            // Notaire instrumentaire, autorité de légalisation, poste consulaire, juridiction.
            $table->string('representation_titre_autorite', 200)->nullable()->after('representation_titre_date');
            // N° de répertoire ou de jugement. Facultatif, jamais bloquant.
            $table->string('representation_titre_reference', 100)->nullable()->after('representation_titre_autorite');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table) {
            // La contrainte d'abord, la colonne ensuite : MySQL refuse de supprimer une colonne
            // encore référencée par une clé étrangère, là où SQLite reconstruit la table et s'en
            // moque. L'ordre inverse passerait donc les tests et casserait en développement.
            $table->dropForeign(['represente_par_partie_id']);
            $table->dropColumn([
                'represente_par_partie_id',
                'representation_motif',
                'representation_qualite',
                'representation_titre_forme',
                'representation_titre_date',
                'representation_titre_autorite',
                'representation_titre_reference',
            ]);
        });
    }
};
