<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Partie extends Model
{
    protected $fillable = [
        'dossier_id', 'client_id', 'nom', 'role', 'type_personne', 'cni',
        'telephone', 'adresse', 'email',
        // Emplacement de cette partie dans le questionnaire — renseigné par le
        // frontend, seul détenteur du schéma (voir la migration
        // add_donnees_mapping_to_parties_table et ClientProjectionService).
        'donnees_prefixe', 'donnees_bloc', 'donnees_index',
    ];

    protected function casts(): array
    {
        return ['donnees_index' => 'integer'];
    }

    /**
     * Cette partie est-elle projetable dans le questionnaire, c'est-à-dire liée à
     * une fiche client ET localisée dans le schéma ?
     *
     * Deux localisations possibles : un préfixe pour une section scalaire
     * (`pp.`, `ger.`…), ou un bloc répétable + index (`associes[1]`). Les items de
     * bloc n'ont pas de préfixe — leurs clés sont à plat dans l'item.
     */
    public function estProjetable(): bool
    {
        return $this->client_id !== null
            && (filled($this->donnees_prefixe) || filled($this->donnees_bloc));
    }

    /**
     * Restreint aux parties projetables — même condition que estProjetable(),
     * exprimée en SQL pour filtrer les dossiers d'un client sans les charger tous.
     */
    public function scopeProjetables($query)
    {
        return $query->whereNotNull('client_id')
            ->where(fn ($q) => $q->whereNotNull('donnees_prefixe')->orWhereNotNull('donnees_bloc'));
    }

    /**
     * Pièces requises par rôle (+ type de personne pour un associé) — reflète le
     * questionnaire papier officiel (constitution SARL/SARLU). Toutes les catégories
     * listées ici doivent rester exclues du tableau générique `pieces` exposé au
     * frontend (voir DossierController::dossierDetailToArray()) pour éviter un doublon
     * d'affichage avec la checklist typée.
     */
    private const PIECES_REQUISES = [
        'associe_physique' => [
            'cni'                  => 'CNI / Passeport',
            'certificat_residence' => 'Certificat de résidence',
            'photo_secondaire'     => 'Deuxième photo d\'identité',
        ],
        // Règle 6 du CR de juillet 2026 : quand une personne morale est associée, « le PV
        // de l'assemblée générale de la société associée est OBLIGATOIRE ». L'entrée
        // unique « Déclaration RCCM **ou** PV de délibération » rendait de fait le PV
        // facultatif — l'alternative suffisait à cocher la pièce. Les deux sont désormais
        // distinctes et toutes deux exigées.
        'associe_morale' => [
            'statuts'          => 'Statuts',
            'declaration_rccm' => 'Déclaration RCCM',
            'pv_ag'            => "PV de l'assemblée générale autorisant la participation",
            'cni_representant' => 'CNI/passeport du représentant légal',
        ],
        'gerant' => [
            'cni'                  => 'CNI / Passeport',
            'certificat_residence' => 'Certificat de résidence',
            'photo_secondaire'     => 'Deuxième photo d\'identité',
        ],
    ];

    /**
     * Rôle → jeu de pièces. Table de correspondance plutôt qu'une cascade de `in_array`,
     * pour que l'ajout d'un rôle (les modifications statutaires en ont apporté quatre) soit
     * une ligne de données et non une branche de logique supplémentaire.
     *
     * Ne figurent pas ici, volontairement : `gerant_sortant`, `president_seance`,
     * `secretaire_seance`. Ces personnes sont **mentionnées** à l'acte, elles n'y apportent
     * rien — exiger leur CNI bloquerait le dossier pour une pièce que l'étude n'a aucune
     * raison de réclamer (un gérant révoqué, a fortiori décédé, ne fournit plus de
     * justificatif).
     */
    private const JEU_PAR_ROLE = [
        'associe'        => 'associe_physique',
        'associe_unique' => 'associe_physique',
        'gerant'         => 'gerant',
        // Modification statutaire (2026-08-11) : ces rôles apportent les mêmes pièces
        // d'identité qu'un associé à la constitution — c'est la même vérification
        // d'identité, sur un acte différent.
        'cedant'         => 'associe_physique',
        'cessionnaire'   => 'associe_physique',
        'souscripteur'   => 'associe_physique',
        'gerant_entrant' => 'gerant',
    ];

    /**
     * Rôles dont une **personne morale** doit produire le jeu de pièces d'une société
     * associée (statuts, déclaration RCCM, PV d'AG autorisant l'opération, CNI du
     * représentant légal — règle 6 du CR de juillet 2026).
     *
     * `gerant_entrant` en est exclu : la gérance d'une SARL est exercée par une personne
     * physique.
     */
    private const ROLES_ADMETTANT_PERSONNE_MORALE = [
        'associe', 'associe_unique', 'cedant', 'cessionnaire', 'souscripteur',
    ];

    public static function categoriesPiecesRequises(): array
    {
        return array_values(array_unique(array_merge(...array_values(array_map('array_keys', self::PIECES_REQUISES)))));
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function pieces()
    {
        return $this->morphMany(DocumentFichier::class, 'documentable')->orderBy('id');
    }

    public function getInitialesAttribute(): string
    {
        return strtoupper(
            collect(explode(' ', $this->nom))
                ->map(fn($w) => $w[0] ?? '')
                ->take(2)
                ->join('')
        );
    }

    public static function piecesRequisesPour(string $role, string $typePersonne = 'physique'): array
    {
        if ($typePersonne === 'morale' && in_array($role, self::ROLES_ADMETTANT_PERSONNE_MORALE, true)) {
            return self::PIECES_REQUISES['associe_morale'] ?? [];
        }

        $jeu = self::JEU_PAR_ROLE[$role] ?? null;

        return $jeu ? (self::PIECES_REQUISES[$jeu] ?? []) : [];
    }

    public static function piecesRequisesParCle(): array
    {
        return self::PIECES_REQUISES;
    }

    public function piecesRequisesDefinition(): array
    {
        return self::piecesRequisesPour($this->role ?? '', $this->type_personne ?? 'physique');
    }

    public function piecesChecklist(): array
    {
        $requis = $this->piecesRequisesDefinition();
        if (!$requis) {
            return [];
        }

        $existantes  = $this->pieces->whereIn('categorie', array_keys($requis))->keyBy('categorie');
        $reprenables = $this->piecesReprenables();

        return collect($requis)->map(fn ($label, $slug) => [
            'id'             => $existantes->get($slug)?->id,
            'categorie'      => $slug,
            'label'          => $label,
            'est_fourni'     => (bool) $existantes->get($slug)?->est_fourni,
            'aUnFichier'     => (bool) $existantes->get($slug)?->versionActuelle,
            'chemin_fichier' => $existantes->get($slug)?->versionActuelle?->chemin_fichier,
            'version'        => $existantes->get($slug)?->versionActuelle?->numero,
            // Pièce que cette même personne a déjà fournie dans un autre dossier — proposée à la
            // reprise plutôt que redemandée. `null` dès qu'elle est fournie ici.
            'reprise'        => $reprenables[$slug] ?? null,
        ])->values()->all();
    }

    /**
     * Pièces requises **manquantes ici** que cette même personne a déjà fournies ailleurs.
     *
     * Signalé à l'usage : un dossier de modification réclamait CNI, certificat de résidence et
     * deuxième photo à un souscripteur qui les avait déjà déposées lors de la constitution de la
     * même société. Une pièce d'identité est un attribut de la **personne**, pas du dossier.
     *
     * Le rapprochement se fait sur `client_id` **exclusivement** : c'est le seul lien fiable entre
     * deux `Partie` (la fiche client est la source de vérité de l'identité — décision #33). Deux
     * homonymes ne sont pas la même personne, et une partie sans fiche client ne propose donc rien.
     *
     * La **date** accompagne chaque proposition : une pièce d'identité a une durée de validité, et
     * reprendre un scan de trois ans sans le voir serait pire que de le redemander.
     *
     * @return array<string, array{piece_id: int, dossier: string, date: ?string}> indexé par catégorie
     */
    public function piecesReprenables(): array
    {
        if (!$this->client_id) {
            return [];
        }

        $requis = $this->piecesRequisesDefinition();
        if (!$requis) {
            return [];
        }

        $dejaFournies = $this->pieces
            ->filter(fn (DocumentFichier $p) => $p->versionActuelle !== null)
            ->pluck('categorie')
            ->all();

        $manquantes = array_diff(array_keys($requis), $dejaFournies);
        if ($manquantes === []) {
            return [];
        }

        $autresParties = self::where('client_id', $this->client_id)
            ->where('id', '!=', $this->id)
            ->with(['dossier:id,reference', 'pieces.versionActuelle'])
            ->get();

        $reprenables = [];

        foreach ($autresParties as $partie) {
            foreach ($partie->pieces as $piece) {
                if (!in_array($piece->categorie, $manquantes, true) || !$piece->versionActuelle) {
                    continue;
                }

                $date = $piece->versionActuelle->created_at;

                // La plus récente l'emporte : une pièce d'identité renouvelée doit primer sur
                // l'ancienne, sans quoi on proposerait de reprendre un document périmé.
                $connue = $reprenables[$piece->categorie] ?? null;
                if ($connue && $connue['_date'] !== null && $date !== null && $connue['_date']->gte($date)) {
                    continue;
                }

                $reprenables[$piece->categorie] = [
                    'piece_id' => $piece->id,
                    'dossier'  => $partie->dossier?->reference,
                    'date'     => $date?->format('d/m/Y'),
                    '_date'    => $date,
                ];
            }
        }

        return array_map(
            fn (array $r) => collect($r)->except('_date')->all(),
            $reprenables,
        );
    }

    /**
     * Reprend une pièce fournie ailleurs par la même personne, **en la copiant**.
     *
     * ⚠️ Copie et non référence. Un dossier notarial doit être physiquement complet — c'est ce que
     * l'inventaire de clôture atteste et ce que l'archive conserve. Et deux `DocumentFichier`
     * pointant sur le même chemin seraient un piège : `supprimerAvecFichiers()` effacerait le
     * fichier sous les pieds de l'autre dossier. `nouvelleVersion()` accepte pourtant une chaîne
     * (chemin déjà stocké), ce qui rendrait l'erreur facile à commettre.
     *
     * La version porte `source = 'reprise'`, aux côtés de `upload`, `genere`, `restauration` et
     * `signe_cachete` : l'historique doit dire d'où vient chaque fichier.
     */
    public function reprendrePiece(string $categorie, DocumentFichier $source): DocumentFichier
    {
        $version = $source->versionActuelle;
        $requis  = $this->piecesRequisesDefinition();

        if (!$version || !array_key_exists($categorie, $requis)) {
            throw new \InvalidArgumentException("Pièce « {$categorie} » non reprenable pour cette personne.");
        }

        $piece = $this->pieces()->firstOrCreate(
            ['categorie' => $categorie],
            ['nom' => $requis[$categorie], 'est_requis' => true],
        );

        $extension = pathinfo($version->chemin_fichier, PATHINFO_EXTENSION);
        $numero    = ($piece->versions()->max('numero') ?? 0) + 1;
        $cible     = 'parties/' . $this->dossier->reference . '/'
            . Str::slug($this->nom . '-' . $categorie) . '_v' . $numero
            . ($extension ? '.' . $extension : '');

        Storage::disk('public')->copy($version->chemin_fichier, $cible);

        $piece->nouvelleVersion($cible, 'parties/' . $this->dossier->reference, [
            'source'        => 'reprise',
            'nom_original'  => $version->nom_original,
            'mime_type'     => $version->mime_type,
            'taille_octets' => $version->taille_octets,
        ]);

        return $piece->fresh();
    }
}
