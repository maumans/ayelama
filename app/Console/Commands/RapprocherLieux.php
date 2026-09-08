<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Lieu;
use App\Models\Questionnaire;
use App\Models\Societe;
use App\Support\Normalisation;
use Illuminate\Console\Command;

/**
 * Rapproche du référentiel les lieux déjà saisis en texte libre.
 *
 * Les fiches clients, les fiches sociétés et les questionnaires portent des valeurs entrées avant
 * l'existence du référentiel, et certaines sont fausses : « Forecariah » enregistré comme commune
 * alors que c'est une préfecture, « Kountia » comme quartier de Conakry alors qu'il est à Dubréka.
 *
 * ⚠️ **Dry-run par défaut.** Cette commande touche à de l'identité : réécrire en silence la commune
 * d'un client, c'est modifier une donnée qui figure dans des actes. Elle **rapporte** donc, et
 * `--appliquer` ne corrige que le cas sûr — un nom introuvable au niveau déclaré mais trouvé à un
 * autre niveau. Tout le reste est laissé à l'arbitrage de l'étude.
 *
 * Même parti que `ayelema:societes-backfill` et `ayelema:brouillons-purger`.
 */
class RapprocherLieux extends Command
{
    protected $signature = 'ayelema:lieux-rapprocher {--appliquer : Écrit les corrections sûres}';

    protected $description = 'Rapproche du référentiel les lieux saisis en texte libre (dry-run par défaut)';

    /** Champ porteur d'un lieu → niveau attendu. */
    private const CHAMPS_CLIENT = [
        'demeurant_ville' => Lieu::NIVEAU_VILLE,
        'commune'         => Lieu::NIVEAU_COMMUNE,
        'quartier'        => Lieu::NIVEAU_QUARTIER,
    ];

    private const CHAMPS_SOCIETE = [
        'siege_ville'    => Lieu::NIVEAU_VILLE,
        'siege_commune'  => Lieu::NIVEAU_COMMUNE,
        'siege_quartier' => Lieu::NIVEAU_QUARTIER,
    ];

    public function handle(): int
    {
        $applique = (bool) $this->option('appliquer');

        if (! $applique) {
            $this->components->info('Lecture seule — relancez avec --appliquer pour écrire les corrections sûres.');
        }

        $conformes = 0;
        $ecarts    = [];
        $corriges  = 0;

        foreach ([
            'Client'  => [Client::query(), self::CHAMPS_CLIENT],
            'Societe' => [Societe::query(), self::CHAMPS_SOCIETE],
        ] as $libelle => [$requete, $champs]) {
            foreach ($requete->get() as $fiche) {
                foreach ($champs as $champ => $niveau) {
                    $valeur = $fiche->{$champ};

                    if (blank($valeur)) {
                        continue;
                    }

                    $verdict = $this->examiner($valeur, $niveau);

                    if ($verdict['statut'] === 'conforme') {
                        $conformes++;
                        continue;
                    }

                    $ecarts[] = [
                        $libelle . ' #' . $fiche->id,
                        $champ,
                        $valeur,
                        $verdict['message'],
                    ];

                    // Seul cas corrigé : le nom existe au référentiel, mais à un autre niveau. On
                    // vide alors le champ mal placé plutôt que de deviner où déplacer la valeur —
                    // c'est au clerc de la reposer au bon endroit, en la voyant manquer.
                    if ($applique && $verdict['statut'] === 'mauvais_niveau') {
                        $fiche->update([$champ => null]);
                        $corriges++;
                    }
                }
            }
        }

        $this->rapporter($conformes, $ecarts, $corriges, $applique);

        // Les questionnaires ne sont **pas** réécrits : leur `donnees` alimente des actes déjà
        // produits. Ils sont seulement dénombrés, pour dire l'ampleur du travail restant.
        $this->signalerQuestionnaires();

        return self::SUCCESS;
    }

    /** @return array{statut: string, message: string} */
    private function examiner(string $valeur, string $niveau): array
    {
        if (Lieu::parNom($valeur, $niveau) !== null) {
            return ['statut' => 'conforme', 'message' => ''];
        }

        $ailleurs = Lieu::where('nom_normalise', Normalisation::comparable($valeur))->first();

        if ($ailleurs) {
            return [
                'statut'  => 'mauvais_niveau',
                'message' => "au référentiel comme {$ailleurs->niveau} ({$ailleurs->chemin()}), pas comme {$niveau}",
            ];
        }

        $proche = $this->plusProche($valeur);

        return [
            'statut'  => 'inconnu',
            'message' => "absent du référentiel au niveau {$niveau}"
                . ($proche ? " — vouliez-vous dire « {$proche->nom} » ({$proche->chemin()}) ?" : ''),
        ];
    }

    /**
     * Lieu du même niveau au nom le plus proche — **suggestion, jamais correction**.
     *
     * Les écarts relevés sur la base réelle sont pour partie des fautes de frappe (« Almanya » pour
     * Almamya, « Lambagni » pour Lambanyi) que la normalisation ne rapproche pas : elle ne retire
     * que les accents, pas les lettres qui diffèrent. Un rapprochement automatique serait dangereux
     * — deux lieux voisins peuvent avoir des noms proches et être distincts — d'où une suggestion
     * lue par un humain, et rien de plus.
     *
     * Seuil **relatif à la longueur** — un quart du nom, au moins 1. Un seuil fixe à 2 rapprochait
     * « Kountia » de « Koubia », deux lieux sans rapport ; le seuil relatif l'écarte tout en gardant
     * « Almanya » → Almamya (1 écart sur 7) et « Lambagni » → Lambanyi (2 sur 8).
     *
     * La recherche porte sur **tous les niveaux**, pas seulement celui attendu : « Lambagni » saisi
     * en commune correspond à « Lambanyi », qui est un quartier. Le chemin affiché dit alors les
     * deux choses à la fois — l'orthographe et le niveau réel.
     */
    private function plusProche(string $valeur): ?Lieu
    {
        $cible   = Normalisation::comparable($valeur);
        $tolere  = max(1, intdiv(mb_strlen($cible), 4));

        return Lieu::query()->get()
            ->map(fn (Lieu $l) => ['lieu' => $l, 'distance' => levenshtein($cible, $l->nom_normalise)])
            ->filter(fn (array $c) => $c['distance'] > 0 && $c['distance'] <= $tolere)
            ->sortBy('distance')
            ->first()['lieu'] ?? null;
    }

    /** @param array<int, array<int, string>> $ecarts */
    private function rapporter(int $conformes, array $ecarts, int $corriges, bool $applique): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('Valeurs conformes au référentiel', (string) $conformes);
        $this->components->twoColumnDetail('Écarts relevés', (string) count($ecarts));

        if ($ecarts !== []) {
            $this->newLine();
            $this->table(['Fiche', 'Champ', 'Valeur', 'Écart'], $ecarts);
        }

        if ($applique) {
            $this->newLine();
            $this->components->info("{$corriges} champ(s) mal placés vidés — à ressaisir depuis la cascade.");
        }
    }

    private function signalerQuestionnaires(): void
    {
        $aRevoir = 0;

        foreach (Questionnaire::all() as $questionnaire) {
            foreach ((array) $questionnaire->donnees as $cle => $valeur) {
                if (! is_string($valeur) || blank($valeur)) {
                    continue;
                }

                $niveau = match (true) {
                    str_ends_with($cle, 'quartier')                              => Lieu::NIVEAU_QUARTIER,
                    str_ends_with($cle, 'commune')                               => Lieu::NIVEAU_COMMUNE,
                    str_ends_with($cle, '_ville') || str_ends_with($cle, 'ville') => Lieu::NIVEAU_VILLE,
                    default                                                       => null,
                };

                if ($niveau && Lieu::parNom($valeur, $niveau) === null) {
                    $aRevoir++;
                }
            }
        }

        $this->newLine();
        $this->components->twoColumnDetail(
            'Valeurs de questionnaires hors référentiel',
            $aRevoir . ' (non modifiées — elles alimentent des actes déjà produits)',
        );
    }
}
