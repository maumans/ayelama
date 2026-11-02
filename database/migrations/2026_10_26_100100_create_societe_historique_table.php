<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal **immuable** des opérations appliquées à une société — addendum v1.2 § 4.1.
 *
 * **Pourquoi `journal_activites` ne suffisait pas.** Il porte déjà un avant/après par champ,
 * mais trois choses l'empêchent de tenir lieu d'historique de société :
 *
 *  1. il est indexé par **dossier** (`dossier_id` NOT NULL), sans `societe_id` — retrouver
 *     l'histoire d'une société demanderait de fouiller le JSON `meta` de toutes les lignes ;
 *  2. il est en `cascadeOnDelete` sur le dossier. ⚠️ **Nuance mesurée** : `Dossier` utilise la
 *     suppression logique, donc un effacement ordinaire ne déclenche rien — mais une
 *     suppression définitive emporte le journal. Un historique dont la survie dépend de la
 *     façon dont on efface un dossier n'en est pas un ;
 *  3. il ne porte que les **champs modifiés**, jamais l'état complet — impossible de répondre
 *     à « quel était le capital en mars 2027 ».
 *
 * Les deux coexistent et ne font pas le même travail : le journal raconte ce qui s'est passé
 * dans un dossier, cette table dit ce qu'était la société avant et après.
 *
 * ⚠️ **`dossier_id` est nullable**, contre la spec. Une radiation est constatée à la main, sur
 * la pièce du greffe, sans dossier ouvert ({@see SocieteController::radier()}) — et c'est
 * justement l'événement qui clôt le cycle de vie. L'exiger aurait rendu la dernière ligne de
 * l'histoire impossible à écrire.
 *
 * ⚠️ **`nullOnDelete` et non `cascade`** : voir le point 2 ci-dessus. C'est le défaut qu'on
 * corrige, pas un oubli.
 *
 * L'immuabilité est tenue par le modèle {@see \App\Models\SocieteHistorique}, qui refuse
 * `update()` et `delete()`, et par son test — un garde-fou exécutable plutôt qu'un déclencheur
 * SQL que SQLite et MySQL n'écriraient pas pareil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('societe_historique', function (Blueprint $table) {
            $table->id();

            $table->foreignId('societe_id')->constrained('societes')->cascadeOnDelete();
            $table->foreignId('dossier_id')->nullable()->constrained('dossiers')->nullOnDelete();

            // Codes des opérations appliquées — plusieurs par PV (règle C7 de la spec).
            $table->json('operations');

            // Date à laquelle l'opération prend effet : celle de l'assemblée, pas celle du
            // traitement. Les deux diffèrent de semaines, et c'est la première que l'acte cite.
            $table->date('date_effet');

            // Instantanés complets. `etat_avant` est NULL pour la toute première ligne — la
            // création, ou l'état initial posé par le backfill.
            $table->json('etat_avant')->nullable();
            $table->json('etat_apres');

            $table->timestamp('appliquee_le');
            $table->foreignId('appliquee_par')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['societe_id', 'date_effet']);

            // ⚠️ Garde d'idempotence au niveau de la base, et pas seulement du service : un
            // dossier ne peut poser qu'**une** ligne. Rejouer un avancement après un recul
            // (cas T12 de la spec) ne doit pas doubler l'histoire. `dossier_id` étant nullable,
            // les radiations manuelles échappent à la contrainte — elles n'ont pas de dossier
            // à rejouer.
            $table->unique(['societe_id', 'dossier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('societe_historique');
    }
};
