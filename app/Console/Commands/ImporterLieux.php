<?php

namespace App\Console\Commands;

use App\Models\Lieu;
use App\Support\Normalisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Aligne le référentiel des lieux sur le découpage administratif réel de la Guinée.
 *
 * ## Pourquoi
 *
 * Le référentiel amorcé portait **34 préfectures et 39 communes** — une seule commune par
 * préfecture, son chef-lieu. Or chaque préfecture compte aussi ses communes rurales, et la réforme
 * de 2024 en a créé de nouvelles. Conséquence mesurée : **33 des 39 communes n'avaient aucun
 * quartier**, et surtout la commune de l'étude elle-même — Lambanyi — ne figurait pas au
 * référentiel, alors que l'adresse de l'office s'imprime « Commune de Ratoma/Lambanyi ».
 *
 * ## Sources, et comment régénérer les données
 *
 * `database/data/lieux-guinee.json` est **versionné** et non téléchargé à l'exécution : un import
 * doit être reproductible et ne pas dépendre du réseau au déploiement. Pour le régénérer :
 *
 * ```
 * curl -O https://download.geonames.org/export/dump/GN.zip
 * python database/data/generer-lieux.py GN.zip database/data/lieux-guinee.json villes-en-base.json
 * ```
 *
 * - **GeoNames** `GN.zip` (CC-BY 4.0) — hiérarchie ADM2 (préfectures) et ADM3 (sous-préfectures,
 *   c'est-à-dire les communes rurales). GeoNames nomme ses ADM2 en anglais non accentué (« Boke »,
 *   « Kerouane ») : le générateur les réaligne sur les orthographes françaises déjà en base.
 * - **Loi L2024/003/CNT du 18 janvier 2024** — les communes créées, dont les **13 communes de
 *   Conakry**, que GeoNames ignore encore (il n'en porte que 5).
 * - **fr.wikipedia.org/wiki/Subdivision_de_la_Guinée** — les préfectures créées après le dump.
 *
 * ## Les quartiers : Conakry seulement
 *
 * La Guinée compte **4 142 districts/quartiers**, et aucune source exploitable ne les couvre au
 * niveau national : les données administratives officielles (OCHA/HDX) ne descendent à ce niveau que
 * pour Conakry, et GeoNames n'a **aucun** `PPLX` pour la Guinée. En inventer aurait mis des noms faux
 * dans des actes authentiques.
 *
 * Le fichier déclare donc les quartiers de **Conakry seulement**, et ailleurs le référentiel se
 * peuple par l'usage, via le bouton d'ajout de la cascade.
 *
 * ⚠️ **La répartition des quartiers de Conakry est celle d'avant le découpage de 2024** : les huit
 * communes créées (Gbessia, Lambanyi, Sonfonia, Tombolia, Kagbelen, Sanoyah, Manéah, Kassa) n'ont
 * pas encore leurs quartiers, faute de disposer de l'annexe de la loi L2024/003. Répartir au hasard
 * mettrait une fausse adresse dans un acte.
 *
 * ## `--corriger`
 *
 * Retire ou désactive les quartiers restés accrochés à l'ancien découpage — voir `corriger()`.
 * Ce geste est **distinct de l'import** parce qu'il *modifie* des lignes existantes, ce que l'import
 * s'interdit.
 *
 * Dry-run par défaut, comme `ayelema:brouillons-purger` et `ayelema:lieux-rapprocher`.
 */
class ImporterLieux extends Command
{
    protected $signature = 'ayelema:lieux-importer
                            {--appliquer : écrire réellement (sinon simulation)}
                            {--corriger : retire ou désactive les quartiers mal placés (voir corriger())}
                            {--fichier= : chemin du fichier de données (défaut : database/data/lieux-guinee.json)}';

    protected $description = 'Complète le référentiel des lieux depuis le découpage administratif réel de la Guinée';

    public function handle(): int
    {
        $chemin = $this->option('fichier') ?: database_path('data/lieux-guinee.json');

        if (! is_file($chemin)) {
            $this->error("Fichier de données introuvable : {$chemin}");

            return self::FAILURE;
        }

        $donnees = json_decode((string) file_get_contents($chemin), true);

        if (! is_array($donnees) || ! isset($donnees['villes'])) {
            $this->error('Fichier de données illisible : clé « villes » attendue.');

            return self::FAILURE;
        }

        $appliquer = (bool) $this->option('appliquer');
        $source    = 'import:' . ($donnees['genere_le'] ?? 'inconnu');

        $this->info($appliquer ? 'Import réel.' : 'Simulation — rien ne sera écrit (--appliquer pour écrire).');
        $this->line("Source : {$donnees['source']}");
        $this->newLine();

        $bilan = ['villes' => 0, 'communes' => 0, 'quartiers' => 0, 'inchanges' => 0, 'preserves' => []];

        $traiter = function () use ($donnees, $appliquer, $source, &$bilan) {
            // Index par forme comparable : « Forécariah » ne doit pas créer un doublon de
            // « Forecariah ». Même normalisation que l'unicité du référentiel.
            $villesExistantes = Lieu::where('niveau', Lieu::NIVEAU_VILLE)
                ->get()
                ->keyBy('nom_normalise');

            foreach ($donnees['villes'] as $villeDonnee) {
                $ville = $this->trouverOuCreer(
                    $villesExistantes,
                    $villeDonnee['nom'],
                    Lieu::NIVEAU_VILLE,
                    null,
                    $appliquer,
                    $source,
                    $bilan,
                    'villes',
                );

                if ($ville === null) {
                    continue;
                }

                $communesExistantes = $ville->exists
                    ? Lieu::where('parent_id', $ville->id)->where('niveau', Lieu::NIVEAU_COMMUNE)->get()->keyBy('nom_normalise')
                    : collect();

                foreach ($villeDonnee['communes'] ?? [] as $nomCommune) {
                    $commune = $this->trouverOuCreer(
                        $communesExistantes,
                        $nomCommune,
                        Lieu::NIVEAU_COMMUNE,
                        $ville->exists ? $ville->id : null,
                        $appliquer,
                        $source,
                        $bilan,
                        'communes',
                    );

                    // Troisième niveau — renseigné pour Conakry seulement : aucune source
                    // exploitable ne couvre les 4 142 quartiers du pays (voir le docblock).
                    $quartiersDeclares = $villeDonnee['quartiers'][$nomCommune] ?? [];

                    if ($commune === null || $quartiersDeclares === []) {
                        continue;
                    }

                    $quartiersExistants = $commune->exists
                        ? Lieu::where('parent_id', $commune->id)->where('niveau', Lieu::NIVEAU_QUARTIER)->get()->keyBy('nom_normalise')
                        : collect();

                    foreach ($quartiersDeclares as $nomQuartier) {
                        $this->trouverOuCreer(
                            $quartiersExistants,
                            $nomQuartier,
                            Lieu::NIVEAU_QUARTIER,
                            $commune->exists ? $commune->id : null,
                            $appliquer,
                            $source,
                            $bilan,
                            'quartiers',
                        );
                    }
                }
            }
        };

        if ($appliquer) {
            DB::transaction($traiter);
            Lieu::oublierReferentiel();
        } else {
            $traiter();
        }

        if ($this->option('corriger')) {
            $this->corriger($donnees, $appliquer);
        }

        $this->newLine();
        $this->line(sprintf('Villes à créer      : %d', $bilan['villes']));
        $this->line(sprintf('Communes à créer    : %d', $bilan['communes']));
        $this->line(sprintf('Quartiers à créer   : %d', $bilan['quartiers']));
        $this->line(sprintf('Déjà présentes      : %d (inchangées)', $bilan['inchanges']));

        if ($bilan['preserves'] !== []) {
            $this->newLine();
            $this->warn(sprintf(
                '%d lieu(x) saisis ou corrigés par l\'étude : laissés intacts — %s',
                count($bilan['preserves']),
                implode(', ', array_slice($bilan['preserves'], 0, 8)),
            ));
        }

        if (! $appliquer) {
            $this->newLine();
            $this->comment('Relancer avec --appliquer pour écrire.');
        }

        return self::SUCCESS;
    }

    /**
     * Retire ou désactive les quartiers mal placés d'une ville dont les quartiers sont **déclarés**.
     *
     * ## Le désordre traité
     *
     * La réforme de 2024 a découpé **Ratoma** en Ratoma + Lambanyi + Sonfonia, et **Matoto** en
     * Matoto + Gbessia + Tombolia. Les quartiers amorcés pendaient toujours des six communes
     * d'avant, si bien que « Lambanyi » et « Sonfonia » existaient **à la fois comme communes de
     * Conakry et comme quartiers de Ratoma** — un même nom désignant deux niveaux. S'y ajoutait
     * « Dapompa » sous Tombolia, graphie fautive de « Dabompa » sous Matoto.
     *
     * ## La règle
     *
     * Le fichier de données est **l'autorité** pour les quartiers d'une ville qui en déclare : tout
     * quartier présent en base mais absent de la liste déclarée de sa commune est un résidu.
     *
     * - **non employé** dans une fiche ni un questionnaire → retiré ;
     * - **employé** → seulement **désactivé**. Son nom figure peut-être déjà dans un acte produit,
     *   et le référentiel ne porte aucune clé étrangère vers eux : c'est la règle de
     *   `Lieu::estSupprimable()`, on ne la contourne pas.
     *
     * ⚠️ **Un lieu saisi par l'étude n'est jamais touché** (`created_by_id` non nul) : elle connaît
     * le terrain, pas un fichier. Et rien n'est **déplacé** d'une commune à l'autre — répartir les
     * quartiers entre les six communes issues du découpage exige l'annexe de la loi L2024/003.
     * Rattacher au hasard mettrait une fausse adresse dans un acte authentique.
     *
     * @param  array<string, mixed>  $donnees
     */
    private function corriger(array $donnees, bool $appliquer): void
    {
        $employes = Lieu::nomsEmployes();
        $retires = $desactives = $preserves = [];

        foreach ($donnees['villes'] as $villeDonnee) {
            if (($villeDonnee['quartiers'] ?? []) === []) {
                continue;
            }

            $ville = Lieu::where('niveau', Lieu::NIVEAU_VILLE)
                ->where('nom_normalise', Normalisation::comparable($villeDonnee['nom']))
                ->first();

            if (! $ville) {
                continue;
            }

            foreach (Lieu::where('parent_id', $ville->id)->where('niveau', Lieu::NIVEAU_COMMUNE)->get() as $commune) {
                $declares = collect($villeDonnee['quartiers'][$commune->nom] ?? [])
                    ->map(fn (string $q) => Normalisation::comparable($q))
                    ->all();

                foreach (Lieu::where('parent_id', $commune->id)->where('niveau', Lieu::NIVEAU_QUARTIER)->get() as $quartier) {
                    if (in_array($quartier->nom_normalise, $declares, true)) {
                        continue;
                    }

                    if ($quartier->created_by_id !== null) {
                        $preserves[] = "{$quartier->nom} (sous {$commune->nom})";
                        continue;
                    }

                    $employe = isset($employes[$quartier->nom_normalise]);
                    $motif   = "{$quartier->nom} sous {$commune->nom}";

                    if ($employe) {
                        $desactives[] = $motif;

                        if ($appliquer && $quartier->actif) {
                            $quartier->update(['actif' => false]);
                        }
                    } else {
                        $retires[] = $motif;

                        if ($appliquer) {
                            $quartier->delete();
                        }
                    }
                }
            }
        }

        $this->newLine();

        if ($retires === [] && $desactives === [] && $preserves === []) {
            $this->info('Correction : aucun quartier mal placé.');

            return;
        }

        if ($retires !== []) {
            $this->warn(sprintf('%d quartier(s) retiré(s) — non employés : %s', count($retires), implode(' · ', $retires)));
        }

        if ($desactives !== []) {
            $this->warn(sprintf(
                '%d quartier(s) désactivé(s) plutôt que supprimé(s) — leur nom est employé dans une fiche ou un acte : %s',
                count($desactives),
                implode(' · ', $desactives),
            ));
        }

        if ($preserves !== []) {
            $this->line(sprintf('%d quartier(s) saisis par l\'étude : laissés intacts — %s', count($preserves), implode(' · ', $preserves)));
        }
    }

    /**
     * Crée le lieu s'il manque, le laisse **strictement intact** s'il existe.
     *
     * ⚠️ L'import **complète**, il ne réécrit jamais : un lieu que l'étude a saisi ou renommé porte
     * sa propre vérité — c'est elle qui connaît le terrain, pas un dump. Un lieu déjà présent n'est
     * donc jamais touché, pas même pour lui poser une provenance.
     *
     * @param  \Illuminate\Support\Collection<string, Lieu>  $existants
     * @param  array{villes: int, communes: int, inchanges: int, preserves: list<string>}  $bilan
     */
    private function trouverOuCreer(
        $existants,
        string $nom,
        string $niveau,
        ?int $parentId,
        bool $appliquer,
        string $source,
        array &$bilan,
        string $compteur,
    ): ?Lieu {
        $comparable = Normalisation::comparable($nom);
        $existant   = $existants->get($comparable);

        if ($existant) {
            $bilan['inchanges']++;

            // Saisi par un utilisateur : on le signale, pour que l'étude sache que sa version
            // prime sur celle de la source.
            if ($existant->created_by_id !== null) {
                $bilan['preserves'][] = $existant->nom;
            }

            return $existant;
        }

        $bilan[$compteur]++;

        if (! $appliquer) {
            // En simulation, on rend une instance non persistée : la boucle appelante sait, par
            // `exists`, qu'elle ne peut pas rattacher d'enfants — et les compte tout de même.
            return new Lieu(['nom' => $nom, 'niveau' => $niveau]);
        }

        $cree = Lieu::create([
            'parent_id'  => $parentId,
            'niveau'     => $niveau,
            'nom'        => $nom,
            // Décision de l'étude : les données importées d'une source documentée arrivent
            // **validées**. `a_verifier` retrouve son sens d'origine — ce que l'étude ajoute à la
            // volée et qui reste à confirmer.
            'a_verifier' => false,
            'actif'      => true,
            'source'     => $source,
        ]);

        $existants->put($comparable, $cree);

        return $cree;
    }
}
