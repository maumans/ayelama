<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Répare les dates dont **le jour et le mois avaient été inversés** à l'enregistrement, et ramène
 * les dates de questionnaire au format des actes.
 *
 * Deux modales (fiche client, fiche société) postaient leurs dates en `JJ/MM/AAAA` vers des
 * colonnes castées `date`. PHP y lit du **mois/jour américain** :
 *   - jour > 12 → `strtotime` échoue → la règle `date` refusait une date pourtant valide ;
 *   - jour ≤ 12 → **inversion silencieuse** : `01/04/1985` enregistré comme le 4 janvier 1985.
 *
 * Le mécanisme est certain — le champ est un calendrier natif, la valeur postée était donc
 * toujours une date française bien formée. Mais **une ligne prise isolément reste ambiguë** :
 * « 04/01/1985 » stocké peut être un vrai 4 janvier. D'où le choix retenu avec l'étude :
 * **corriger, et signaler pour recontrôle** sur la pièce d'identité.
 *
 * ⚠️ Une valeur dont le **jour est supérieur à 12 n'est pas touchée** : l'inversion aurait été
 * impossible (le mois n'existe pas), donc cette valeur ne vient pas de ce chemin.
 */
return new class extends Migration
{
    /** Les trois colonnes castées `date` alimentées par la modale fautive. */
    private const COLONNES = ['date_naissance', 'piece_delivree_le', 'piece_expire_le'];

    public function up(): void
    {
        $this->ajouterTemoin();
        $this->corrigerFichesClients();
        $this->normaliserDatesDesQuestionnaires();
    }

    /**
     * Témoin de reprise, lu par `Client::avertissements()` pour afficher la demande de recontrôle.
     *
     * C'est aussi ce qui rend la migration **idempotente** : une date réinversée porte de nouveau
     * un jour ≤ 12, la rejouer sans témoin la réinverserait une seconde fois.
     */
    private function ajouterTemoin(): void
    {
        if (Schema::hasColumn('clients', 'dates_a_confirmer')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->json('dates_a_confirmer')->nullable()->after('piece_expire_le');
        });
    }

    private function corrigerFichesClients(): void
    {
        $journal = [];

        $fiches = DB::table('clients')
            ->whereNull('dates_a_confirmer')
            ->get(array_merge(['id', 'nom_famille', 'prenoms'], self::COLONNES));

        foreach ($fiches as $fiche) {
            $corrections = [];
            $nouvelles   = [];

            foreach (self::COLONNES as $colonne) {
                $stockee = $fiche->{$colonne};

                if (!$stockee || !preg_match('#^(\d{4})-(\d{2})-(\d{2})#', (string) $stockee, $p)) {
                    continue;
                }

                [, $annee, $mois, $jour] = $p;

                // Jour > 12 : l'inversion n'a pas pu avoir lieu, la valeur est d'origine.
                if ((int) $jour > 12) {
                    continue;
                }

                $nouvelles[$colonne]   = "{$annee}-{$jour}-{$mois}";
                $corrections[$colonne] = [
                    'retenu' => "{$annee}-{$jour}-{$mois}",
                    'avant'  => "{$annee}-{$mois}-{$jour}",
                ];
            }

            if ($corrections === []) {
                continue;
            }

            DB::table('clients')->where('id', $fiche->id)->update(
                $nouvelles + ['dates_a_confirmer' => json_encode($corrections)],
            );

            $nom = trim(($fiche->prenoms ?? '') . ' ' . ($fiche->nom_famille ?? ''));
            foreach ($corrections as $colonne => $lectures) {
                $journal[] = sprintf(
                    '#%d %s — %s : %s (était %s)',
                    $fiche->id,
                    $nom !== '' ? $nom : 'sans nom',
                    $colonne,
                    $lectures['retenu'],
                    $lectures['avant'],
                );
            }
        }

        if ($journal !== []) {
            Log::warning(
                "Dates de fiches clients réinversées (jour/mois), à recontrôler sur la pièce "
                . "d'identité : " . implode(' · ', $journal),
            );
        }
    }

    /**
     * Ramène en `JJ/MM/AAAA` les dates de `questionnaires.donnees`.
     *
     * La projection depuis la fiche client y déposait un horodatage brut
     * (`1970-02-05T00:00:00.000000Z`) : c'est **cette chaîne que le modèle Word imprimait** dans
     * l'acte authentique, et `ActesGeneratorService` ne dérivait alors ni `${..._jma}` ni
     * `${..._lettres}`, qui ne se déduisent que d'une date française.
     *
     * ⚠️ Réécriture **de forme, jamais de fond** — la date désignée est identique. C'est ce qui la
     * distingue de la reprise des régimes matrimoniaux, où l'on aurait corrigé la saisie d'une
     * personne : un questionnaire reste un instantané, on ne répare ici qu'une sérialisation.
     * Le critère est la forme complète de la valeur, jamais le nom de la clé.
     *
     * Idempotente : une date déjà française ne correspond pas au motif ISO.
     */
    private function normaliserDatesDesQuestionnaires(): void
    {
        $reprises = 0;

        foreach (DB::table('questionnaires')->whereNotNull('donnees')->get(['id', 'donnees']) as $questionnaire) {
            $donnees = json_decode($questionnaire->donnees, true);

            if (!is_array($donnees)) {
                continue;
            }

            $converties = $this->convertirRecursivement($donnees, $reprises);

            if ($converties !== $donnees) {
                DB::table('questionnaires')->where('id', $questionnaire->id)->update([
                    'donnees' => json_encode($converties, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        if ($reprises > 0) {
            Log::info("Dates de questionnaires ramenées en JJ/MM/AAAA : {$reprises} valeur(s).");
        }
    }

    /** Les blocs répétables (associés, gérants…) sont des tableaux de tableaux : on descend. */
    private function convertirRecursivement(array $donnees, int &$reprises): array
    {
        foreach ($donnees as $cle => $valeur) {
            if (is_array($valeur)) {
                $donnees[$cle] = $this->convertirRecursivement($valeur, $reprises);
                continue;
            }

            if (!is_string($valeur) || !preg_match('#^(\d{4})-(\d{2})-(\d{2})(?:[T ].*)?$#', trim($valeur), $p)) {
                continue;
            }

            $donnees[$cle] = "{$p[3]}/{$p[2]}/{$p[1]}";
            $reprises++;
        }

        return $donnees;
    }

    /**
     * Le témoin est retiré, mais **les dates ne sont pas réinversées** : les remettre à l'envers
     * restaurerait une erreur, jamais une information.
     */
    public function down(): void
    {
        if (Schema::hasColumn('clients', 'dates_a_confirmer')) {
            Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('dates_a_confirmer'));
        }
    }
};
