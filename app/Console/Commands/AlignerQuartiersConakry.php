<?php

namespace App\Console\Commands;

use App\Models\Lieu;
use App\Support\Normalisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Aligne les communes et quartiers de Conakry sur la liste de l'étude (2026-11-25).
 *
 * ## Le désordre traité
 *
 * Mesuré sur la base locale avant d'écrire une ligne : les **13 communes** de la loi L/2024/003/CNT
 * existaient, mais **8 n'avaient aucun quartier** (Gbessia, Kagbelen, Kassa, Lambanyi, Manéah,
 * Sanoyah, Sonfonia, et Tombolia hormis « Dapompa » saisi par l'étude), et les 53 quartiers
 * amorcés pendaient toujours des cinq communes d'avant la réforme — « Nongo », « Kobaya »,
 * « Wanindara » sous Ratoma, « Yimbaya », « Dabondy » sous Matoto. Ce qui manquait pour les
 * répartir, l'annexe de la loi, l'étude l'a fourni sous forme de liste.
 *
 * ## Ce que la commande fait — et ne fait jamais
 *
 * 1. **Ajoute** les quartiers manquants, chacun sous sa commune, avec la fiabilité de sa source
 *    (`database/data/quartiers-conakry-2024.json`). Rien ne se crée en double : l'existant est
 *    retrouvé par `Normalisation::cleLieu()`, qui rapproche « Sans-fil » de « Sans-Fil »,
 *    « Belle-vue École » de « Belle-Vue École » et « Tanènè I » de « Tanènè 1 ».
 * 2. **Ne renomme jamais.** Une fiche ou un questionnaire porte le **nom** du lieu, et la cascade
 *    le compare à l'identique (`lieu-select.jsx`) : renommer « Kagbelen » en « Kagbélen » ferait
 *    passer chaque valeur saisie en « hors référentiel ». Les écarts d'orthographe sont rapportés.
 * 3. **Ne supprime jamais**, ne réactive jamais un lieu désactivé, ne déplace rien.
 * 4. Avec `--desactiver-obsoletes` seulement : désactive les quartiers de l'ancien découpage
 *    absents de la liste — **sauf** s'ils sont employés (fiche ou questionnaire), saisis par
 *    l'étude, ou validés par elle. Ceux-là sont rapportés pour qu'elle arbitre depuis
 *    *Paramètres > Lieux* : c'est elle qui connaît le terrain, pas un fichier.
 *
 * Dry-run par défaut, comme `ayelema:lieux-importer` et `ayelema:lieux-rapprocher`.
 * Ré-exécutable : un second passage ne crée ni ne modifie rien.
 */
class AlignerQuartiersConakry extends Command
{
    protected $signature = 'ayelema:lieux-conakry
                            {--appliquer : écrire réellement (sinon simulation)}
                            {--desactiver-obsoletes : désactive les quartiers de l\'ancien découpage ni employés, ni saisis, ni validés par l\'étude}
                            {--fichier= : chemin du fichier de données (défaut : database/data/quartiers-conakry-2024.json)}';

    protected $description = 'Aligne les 13 communes de Conakry et leurs quartiers sur la liste de l\'étude (dry-run par défaut)';

    /** Préfixe de la colonne `source` : dit d'où vient un lieu, et permet de le retrouver. */
    public const SOURCE = 'conakry-2024';

    /** Motifs de protection d'un lieu obsolète — rapportés, jamais désactivés. */
    public const SAISI_PAR_L_ETUDE  = 'saisi par l\'étude';
    public const VALIDE_PAR_L_ETUDE = 'validé par l\'étude';
    public const EMPLOYE            = 'employé dans une fiche ou un questionnaire';

    private bool $appliquer = false;

    /** @var array<string, mixed> */
    private array $bilan = [];

    public function handle(): int
    {
        $chemin  = $this->option('fichier') ?: database_path('data/quartiers-conakry-2024.json');
        $donnees = is_file($chemin) ? json_decode((string) file_get_contents($chemin), true) : null;

        if (! is_array($donnees)) {
            $this->error("Fichier de données introuvable ou illisible : {$chemin}");

            return self::FAILURE;
        }

        if ($erreurs = self::erreursDeStructure($donnees)) {
            foreach ($erreurs as $erreur) {
                $this->error($erreur);
            }

            return self::FAILURE;
        }

        $this->appliquer = (bool) $this->option('appliquer');
        $desactiver      = (bool) $this->option('desactiver-obsoletes');

        $this->bilan = [
            'communes_creees'  => [], 'quartiers_crees' => [], 'inchanges' => 0,
            'orthographes'     => [], 'inactifs_declares' => [],
            'a_desactiver'     => [], 'proteges' => [], 'homonymes' => [],
        ];

        $this->info($this->appliquer ? 'Écriture réelle.' : 'Simulation — rien ne sera écrit (--appliquer pour écrire).');
        $this->newLine();

        $traiter = function () use ($donnees, $desactiver) {
            $ville = $this->ville($donnees['ville']);
            $declares = $this->ajouter($ville, $donnees);
            $this->examinerObsoletes($ville, $donnees, $declares, $desactiver);
        };

        $this->appliquer ? DB::transaction($traiter) : $traiter();

        if ($this->appliquer) {
            Lieu::oublierReferentiel();
        }

        $this->rapporter($desactiver);

        return self::SUCCESS;
    }

    /**
     * Les défauts de structure du fichier — vide s'il est sain.
     *
     * Public et statique : le test du fichier livré l'appelle directement. Un quartier déclaré deux
     * fois sous la même commune, ou une source inconnue, ne doivent pas attendre l'exécution.
     *
     * @param  array<string, mixed>  $donnees
     * @return list<string>
     */
    public static function erreursDeStructure(array $donnees): array
    {
        $erreurs = [];

        if (blank($donnees['ville'] ?? null) || ! is_array($donnees['communes'] ?? null)) {
            return ['Clés « ville » et « communes » attendues.'];
        }

        $sources = $donnees['sources'] ?? [];
        $communesVues = [];

        foreach ($donnees['communes'] as $commune) {
            $nom = $commune['nom'] ?? '?';

            if (! isset($sources[$commune['source'] ?? ''])) {
                $erreurs[] = "« {$nom} » : source « " . ($commune['source'] ?? '') . ' » non déclarée.';
            }

            $cle = Normalisation::cleLieu($nom);
            if (isset($communesVues[$cle])) {
                $erreurs[] = "Commune « {$nom} » déclarée deux fois.";
            }
            $communesVues[$cle] = true;

            $vus = [];
            foreach ($commune['quartiers'] ?? [] as $quartier) {
                $nomQuartier = is_array($quartier) ? ($quartier['nom'] ?? '') : $quartier;

                if (blank($nomQuartier)) {
                    $erreurs[] = "« {$nom} » : quartier sans nom.";
                    continue;
                }

                $cleQuartier = Normalisation::cleLieu($nomQuartier);
                if (isset($vus[$cleQuartier])) {
                    $erreurs[] = "« {$nom} » : « {$nomQuartier} » rapproché de « {$vus[$cleQuartier]} », déjà déclaré.";
                }
                $vus[$cleQuartier] = $nomQuartier;

                if (is_array($quartier) && ($quartier['a_verifier'] ?? false) && blank($quartier['motif'] ?? null)) {
                    $erreurs[] = "« {$nomQuartier} » ({$nom}) est à vérifier sans motif : dire pourquoi.";
                }
            }
        }

        return $erreurs;
    }

    /**
     * Les quartiers déclarés d'une commune, sous une forme uniforme.
     *
     * Une entrée chaîne hérite de la fiabilité de la source de sa commune ; une entrée objet porte la
     * sienne. Un doute **ponctuel** (Yattaya Centre cité sous deux communes) ne peut donc pas être
     * effacé par une source fiable.
     *
     * @param  array<string, mixed>  $commune
     * @param  array<string, mixed>  $sources
     * @return list<array{nom: string, a_verifier: bool}>
     */
    public static function quartiersDeclares(array $commune, array $sources): array
    {
        $parDefaut = (bool) ($sources[$commune['source']]['a_verifier'] ?? true);

        return array_map(fn ($q) => is_array($q)
            ? ['nom' => $q['nom'], 'a_verifier' => (bool) ($q['a_verifier'] ?? $parDefaut)]
            : ['nom' => $q, 'a_verifier' => $parDefaut],
            $commune['quartiers'] ?? [],
        );
    }

    private function ville(string $nom): Lieu
    {
        $ville = Lieu::whereNull('parent_id')
            ->where('niveau', Lieu::NIVEAU_VILLE)
            ->get()
            ->first(fn (Lieu $l) => Normalisation::cleLieu($l->nom) === Normalisation::cleLieu($nom));

        return $ville ?? $this->creer(null, Lieu::NIVEAU_VILLE, $nom, false, self::SOURCE);
    }

    /**
     * Crée ce qui manque, retrouve ce qui existe.
     *
     * @param  array<string, mixed>  $donnees
     * @return array<string, array<string, string>> clé de commune → (clé de quartier → « Nom (Commune) »)
     */
    private function ajouter(Lieu $ville, array $donnees): array
    {
        $communesExistantes = $this->enfantsParCle($ville, Lieu::NIVEAU_COMMUNE);
        $declares = [];

        foreach ($donnees['communes'] as $communeDonnee) {
            $source = self::SOURCE . ':' . $communeDonnee['source'];
            $cleCommune = Normalisation::cleLieu($communeDonnee['nom']);
            $commune = $communesExistantes[$cleCommune] ?? null;

            if ($commune === null) {
                $commune = $this->creer($ville, Lieu::NIVEAU_COMMUNE, $communeDonnee['nom'], false, $source);
                $this->bilan['communes_creees'][] = $communeDonnee['nom'];
            } else {
                $this->constaterExistant($commune, $communeDonnee['nom']);
            }

            $quartiersExistants = $this->enfantsParCle($commune, Lieu::NIVEAU_QUARTIER);

            foreach (self::quartiersDeclares($communeDonnee, $donnees['sources']) as $quartier) {
                $cle = Normalisation::cleLieu($quartier['nom']);
                $declares[$cleCommune][$cle] = "{$quartier['nom']} ({$communeDonnee['nom']})";

                if ($existant = $quartiersExistants[$cle] ?? null) {
                    $this->constaterExistant($existant, $quartier['nom']);
                    continue;
                }

                $this->creer($commune, Lieu::NIVEAU_QUARTIER, $quartier['nom'], $quartier['a_verifier'], $source);
                $this->bilan['quartiers_crees'][] = "{$quartier['nom']} ({$communeDonnee['nom']})"
                    . ($quartier['a_verifier'] ? ' ⚑' : '');
            }
        }

        return $declares;
    }

    /**
     * Un lieu déjà présent est laissé **strictement intact** — on constate, on ne corrige pas.
     *
     * Ni son nom, ni son `a_verifier`, ni son état actif : ce sont peut-être les choix de l'étude.
     */
    private function constaterExistant(Lieu $existant, string $nomDeclare): void
    {
        $this->bilan['inchanges']++;

        if ($existant->nom !== $nomDeclare) {
            $this->bilan['orthographes'][] = "« {$existant->nom} » conservé (liste : « {$nomDeclare} »)";
        }

        if (! $existant->actif) {
            $this->bilan['inactifs_declares'][] = $existant->chemin();
        }
    }

    /**
     * Les lieux de l'ancien découpage : quartiers absents de la liste de leur commune, et communes
     * homonymes d'une commune de Conakry sous une autre ville.
     *
     * ⚠️ Ces homonymes ne sont pas une curiosité : la cascade filtre les quartiers par le **nom** de
     * leur commune (`referentielLieux.js`, `lieuxDe()`). « Manéah » existant aussi sous Dubréka
     * (import GeoNames, antérieur au rattachement de 2024), choisir Dubréka → Manéah proposerait les
     * douze quartiers de Manéah-Conakry.
     *
     * @param  array<string, mixed>  $donnees
     * @param  array<string, array<string, string>>  $declares
     */
    private function examinerObsoletes(Lieu $ville, array $donnees, array $declares, bool $desactiver): void
    {
        if (! $ville->exists) {
            return;
        }

        $employes = Lieu::nomsEmployes();
        $homologues = $this->homologues($declares);

        foreach ($this->enfantsParCle($ville, Lieu::NIVEAU_COMMUNE) as $cleCommune => $commune) {
            foreach ($this->enfantsParCle($commune, Lieu::NIVEAU_QUARTIER) as $cle => $quartier) {
                if (isset($declares[$cleCommune][$cle]) || ! $quartier->actif) {
                    continue;
                }

                $this->classer($quartier, $employes, $desactiver, $homologues[$cle] ?? null);
            }
        }

        $clesConakry = array_keys($declares);

        $homonymes = Lieu::where('niveau', Lieu::NIVEAU_COMMUNE)
            ->where('parent_id', '!=', $ville->id)
            ->where('actif', true)
            ->get()
            ->filter(fn (Lieu $c) => in_array(Normalisation::cleLieu($c->nom), $clesConakry, true));

        foreach ($homonymes as $commune) {
            // Une commune qui porte ses propres quartiers n'est pas un résidu : elle vit.
            if ($commune->enfants()->exists()) {
                $this->bilan['proteges'][] = [$commune->chemin(), 'porte ses propres quartiers', ''];
                continue;
            }

            $this->bilan['homonymes'][] = $commune->chemin();
            $this->classer($commune, $employes, $desactiver, 'Conakry · ' . $commune->nom . ' (loi L/2024/003)');
        }
    }

    /** @param array<string, true> $employes */
    private function classer(Lieu $lieu, array $employes, bool $desactiver, ?string $homologue): void
    {
        $motif = match (true) {
            $lieu->created_by_id !== null                => self::SAISI_PAR_L_ETUDE,
            $lieu->source === null && ! $lieu->a_verifier => self::VALIDE_PAR_L_ETUDE,
            isset($employes[$lieu->nom_normalise])        => self::EMPLOYE,
            default                                       => null,
        };

        if ($motif !== null) {
            $this->bilan['proteges'][] = [$lieu->chemin(), $motif, $homologue ?? ''];

            return;
        }

        $this->bilan['a_desactiver'][] = [$lieu->chemin(), $homologue ?? ''];

        if ($desactiver && $this->appliquer) {
            $lieu->update(['actif' => false]);
        }
    }

    /**
     * Pour chaque clé de l'ancien découpage, où retrouver ce lieu dans la liste — pour le rapport.
     *
     * « Nongo » → « Nongo Centre, Nongo Taady (Lambanyi) » ; « Kobaya » → « Kobaya (Sonfonia) ».
     * Une **indication**, jamais un rattachement : c'est à l'étude de reprendre les fiches.
     *
     * @param  array<string, array<string, string>>  $declares
     * @return array<string, string>
     */
    private function homologues(array $declares): array
    {
        $parCle = [];

        foreach (Lieu::where('niveau', Lieu::NIVEAU_QUARTIER)->pluck('nom') as $nom) {
            $cle = Normalisation::cleLieu($nom);
            $trouves = [];

            foreach ($declares as $quartiers) {
                foreach ($quartiers as $cleQ => $nomQ) {
                    // Même clé (« Kobaya ») ou clé prolongée (« Hafia » → « Hafia I », « Hafia Mosquée »).
                    if (str_starts_with($cleQ, $cle)) {
                        $trouves[] = $nomQ;
                    }
                }
            }

            if ($trouves !== []) {
                $parCle[$cle] = implode(', ', array_unique($trouves));
            }
        }

        return $parCle;
    }

    /** @return array<string, Lieu> */
    private function enfantsParCle(Lieu $parent, string $niveau): array
    {
        if (! $parent->exists) {
            return [];
        }

        return Lieu::where('parent_id', $parent->id)
            ->where('niveau', $niveau)
            ->get()
            ->keyBy(fn (Lieu $l) => Normalisation::cleLieu($l->nom))
            ->all();
    }

    private function creer(?Lieu $parent, string $niveau, string $nom, bool $aVerifier, string $source): Lieu
    {
        $attributs = [
            'parent_id'  => $parent?->id,
            'niveau'     => $niveau,
            'nom'        => $nom,
            'a_verifier' => $aVerifier,
            'actif'      => true,
            'source'     => $source,
        ];

        // En simulation, une instance non persistée : `exists` dit à l'appelant qu'il ne peut ni y
        // rattacher d'enfants ni y chercher d'existant — mais tout est compté.
        if (! $this->appliquer || ($parent !== null && ! $parent->exists)) {
            return new Lieu($attributs);
        }

        return Lieu::create($attributs);
    }

    private function rapporter(bool $desactiver): void
    {
        $b = $this->bilan;

        $this->components->twoColumnDetail('Communes à créer', (string) count($b['communes_creees']));
        $this->components->twoColumnDetail('Quartiers à créer (⚑ = à vérifier)', (string) count($b['quartiers_crees']));
        $this->components->twoColumnDetail('Déjà présents, laissés intacts', (string) $b['inchanges']);

        if ($b['communes_creees'] !== []) {
            $this->line('  Communes : ' . implode(', ', $b['communes_creees']));
        }

        if ($b['quartiers_crees'] !== [] && $this->output->isVerbose()) {
            $this->line('  Quartiers : ' . implode(' · ', $b['quartiers_crees']));
        }

        if ($b['orthographes'] !== []) {
            $this->newLine();
            $this->comment('Orthographes différentes — l\'existant est conservé (les fiches portent son nom) :');
            $this->line('  ' . implode("\n  ", $b['orthographes']));
        }

        if ($b['inactifs_declares'] !== []) {
            $this->newLine();
            $this->warn('Déclarés par la liste mais désactivés en base — non réactivés, à arbitrer :');
            $this->line('  ' . implode("\n  ", $b['inactifs_declares']));
        }

        $this->newLine();

        if ($b['a_desactiver'] !== []) {
            $verbe = $desactiver ? ($this->appliquer ? 'désactivé(s)' : 'à désactiver') : 'obsolète(s), laissé(s) actif(s) sans --desactiver-obsoletes';
            $this->warn(sprintf('%d lieu(x) de l\'ancien découpage %s :', count($b['a_desactiver']), $verbe));
            $this->table(['Lieu', 'Dans la liste'], $b['a_desactiver']);
        }

        if ($b['proteges'] !== []) {
            $this->comment(sprintf('%d lieu(x) de l\'ancien découpage protégés — jamais touchés, à arbitrer par l\'étude :', count($b['proteges'])));
            $this->table(['Lieu', 'Motif', 'Dans la liste'], $b['proteges']);
        }

        if ($b['homonymes'] !== [] && ! ($desactiver && $this->appliquer)) {
            $this->warn('⚠️ Commune(s) homonyme(s) d\'une commune de Conakry : tant qu\'elles restent actives, la cascade y '
                . 'propose les quartiers de Conakry — ' . implode(', ', $b['homonymes']));
        }

        if (! $this->appliquer) {
            $this->newLine();
            $this->comment('Relancer avec --appliquer pour écrire.');
        }
    }
}
