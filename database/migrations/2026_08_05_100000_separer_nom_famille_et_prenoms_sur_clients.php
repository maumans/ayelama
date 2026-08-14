<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sépare `clients.prenom_nom` en `nom_famille` + `prenoms` (règle 4 du CR de juillet 2026).
 *
 * La règle exige le **nom de famille en MAJUSCULE** dans les actes. Avec un champ unique
 * « nom et prénoms », impossible de savoir quelle partie est le nom de famille : on ne
 * pouvait ni le mettre en capitales ni composer « DIALLO Ibrahima ».
 *
 * **Découpe automatique et sa limite** : le dernier mot est pris pour le nom de famille,
 * le reste pour les prénoms. C'est la convention la plus fréquente en Guinée, mais elle
 * échoue sur les noms composés (« Tafsir Camara ») et sur les saisies qui inversent
 * l'ordre. Les lignes converties sont donc journalisées pour relecture — le volume est
 * trivial (4 clients au moment de la migration).
 *
 * `prenom_nom` est supprimée de la table mais **survit en accessor/mutateur** sur le
 * modèle : les 63 modèles Word non normalisés référencent `${pp.prenom_nom}`, et de
 * nombreux appels écrivent encore ce champ. Voir App\Models\Client.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('nom_famille')->nullable()->after('civilite');
            $table->string('prenoms')->nullable()->after('nom_famille');
        });

        $convertis = [];
        foreach (DB::table('clients')->whereNotNull('prenom_nom')->get() as $client) {
            $decoupe = self::decouper($client->prenom_nom);
            if (!$decoupe) {
                continue;
            }

            DB::table('clients')->where('id', $client->id)->update($decoupe);
            $convertis[] = "#{$client->id} « {$client->prenom_nom} » → nom_famille=« {$decoupe['nom_famille']} », prenoms=« {$decoupe['prenoms']} »";
        }

        if ($convertis) {
            // Journalisé et non silencieux : la découpe est faillible, ces lignes doivent
            // pouvoir être relues.
            logger()->info(
                'Migration separer_nom_famille_et_prenoms : ' . count($convertis) . " client(s) converti(s).\n"
                . implode("\n", $convertis)
            );
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('prenom_nom');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('prenom_nom')->nullable()->after('civilite');
        });

        foreach (DB::table('clients')->get() as $client) {
            DB::table('clients')->where('id', $client->id)->update([
                'prenom_nom' => trim(($client->prenoms ?? '') . ' ' . ($client->nom_famille ?? '')),
            ]);
        }

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['nom_famille', 'prenoms']);
        });
    }

    /**
     * Dernier mot = nom de famille, le reste = prénoms.
     *
     * @return array{nom_famille: string, prenoms: string}|null
     */
    private static function decouper(?string $valeur): ?array
    {
        $mots = preg_split('/\s+/u', trim((string) $valeur), -1, PREG_SPLIT_NO_EMPTY);

        if (!$mots) {
            return null;
        }

        // Un seul mot : c'est le nom de famille, sans prénom identifiable.
        if (count($mots) === 1) {
            return ['nom_famille' => $mots[0], 'prenoms' => ''];
        }

        $nom = array_pop($mots);

        return ['nom_famille' => $nom, 'prenoms' => implode(' ', $mots)];
    }
};
