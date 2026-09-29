<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les données typées qu'une autorité délivre au retour d'une formalité —
 * voir {@see \App\Enums\DonneeAuRetour}.
 *
 * Deux colonnes, deux rôles :
 *
 *   - `baremes.donnees_au_retour`   : ce que l'étude **déclare** attendre de cette démarche ;
 *   - `formalites.donnees_au_retour`: la copie figée à la génération, pour qu'un changement de
 *     barème ne réécrive pas l'histoire d'un dossier en cours — même parti que `retour_attendu` ;
 *   - `formalites.donnees_recues`   : ce qui a été **effectivement saisi** au retour.
 *
 * La troisième est la preuve de provenance : sans elle, on saurait qu'une société a un RCCM,
 * pas de quelle démarche il vient.
 *
 * ⚠️ Les dates y sont stockées en **ISO**, jamais en JJ/MM/AAAA : c'est du stockage structuré,
 * pas une projection de document. La conversion au format français se fait au moment de
 * produire une balise. Confondre les deux a produit cinq défauts dans ce dépôt — le contrat est
 * en tête de `resources/js/lib/dates.js`.
 *
 * ⚠️ Cette table compte déjà des colonnes mortes (`societes.jal_journal`,
 * `types_actes.fiche_modification_obligatoire`). Celles-ci ne les rejoindront pas : elles ont un
 * lecteur (`EnregistrementRetourFormalite`), un écran (Paramètres → Barèmes, et la modale de
 * retour) et un test qui vérifie qu'une donnée déclarée arrive bien sur la fiche société.
 *
 * MySQL en développement, SQLite en test : un `Schema::table()` par table, colonnes json
 * nullables, aucun index (25 barèmes concernés sur 104, 53 formalités au total).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            $table->json('donnees_au_retour')->nullable()->after('retour_attendu');
        });

        Schema::table('formalites', function (Blueprint $table) {
            $table->json('donnees_au_retour')->nullable()->after('retour_attendu');
            $table->json('donnees_recues')->nullable()->after('reference_document_recu');
            // Motif de rejet — le retour rejeté redevient enregistrable (il l'était avant le
            // 2026-08-14, et `StatutFormalite::Rejete` était depuis inatteignable alors que
            // `DossierStepService` gardait son message « À corriger et redéposer »).
            $table->string('motif_rejet', 500)->nullable()->after('donnees_recues');
        });
    }

    public function down(): void
    {
        Schema::table('baremes', function (Blueprint $table) {
            $table->dropColumn('donnees_au_retour');
        });

        Schema::table('formalites', function (Blueprint $table) {
            $table->dropColumn(['donnees_au_retour', 'donnees_recues', 'motif_rejet']);
        });
    }
};
