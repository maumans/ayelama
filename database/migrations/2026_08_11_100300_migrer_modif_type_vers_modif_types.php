<?php

use App\Models\Questionnaire;
use Illuminate\Database\Migrations\Migration;

/**
 * `modif.type` (choix unique) devient `modif.types` (tableau).
 *
 * Une même assemblée générale décide couramment plusieurs modifications — cession de parts,
 * nouveau gérant, transfert de siège — constatées par un **seul** procès-verbal. Le choix
 * unique obligeait à ouvrir un dossier par changement, donc à produire plusieurs PV pour une
 * seule assemblée.
 *
 * Aucune modification de schéma : `questionnaires.donnees` est une colonne JSON. Le filtrage
 * s'effectue en PHP et non en SQL, parce que la clé contient un point, que la notation
 * `donnees->modif.type` de Laravel interpréterait comme un chemin imbriqué — même contrainte
 * que ReglesSocieteService::verifierDenominationUnique(), et même volume attendu (quelques
 * centaines de dossiers).
 *
 * **Idempotente** : un questionnaire déjà porteur de `modif.types` n'est pas retouché.
 *
 * Pas de `down()` destructif : `TypeModificationStatutaire::depuisLibelles()` accepte les
 * deux formes, donc un retour arrière du code fonctionne sans retour arrière des données.
 * Reconvertir un tableau de trois types en champ unique perdrait deux d'entre eux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Questionnaire::query()->chunkById(200, function ($questionnaires) {
            foreach ($questionnaires as $questionnaire) {
                $donnees = $questionnaire->donnees ?? [];

                if (!array_key_exists('modif.type', $donnees)) {
                    continue;
                }

                $ancien = $donnees['modif.type'];
                unset($donnees['modif.type']);

                // Ne pas écraser une valeur déjà migrée, et ne pas créer un tableau
                // contenant une chaîne vide pour un champ jamais rempli.
                if (!array_key_exists('modif.types', $donnees) && filled($ancien)) {
                    $donnees['modif.types'] = [$ancien];
                }

                $questionnaire->update(['donnees' => $donnees]);
            }
        });
    }

    public function down(): void
    {
        // Volontairement vide — voir le commentaire de classe.
    }
};
