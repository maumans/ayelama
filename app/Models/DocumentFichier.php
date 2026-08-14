<?php

namespace App\Models;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

/**
 * Document/pièce polymorphe (Dossier, Formalite, Partie ou Societe) — remplace les anciens
 * modèles Document et FormalitePiece, unifiés pour partager le même mécanisme de
 * versionnage (document_versions) plutôt que d'écraser silencieusement un fichier
 * à chaque régénération/ré-upload. Voir le plan GED.
 *
 * ⚠️ `Societe` (2026-08-11) est le seul rattachement qui **n'appartienne pas à un dossier** : les
 * pièces constitutives d'une société vivent au registre et servent à toutes ses modifications
 * successives. D'où `sujetAutorisation()` et `estPieceDeRegistre()` ci-dessous.
 */
class DocumentFichier extends Model
{
    use \App\Concerns\HasTypeDocumentLabel;

    /** Ce modèle nomme `categorie` ce que les modèles d'actes nomment `type_document`. */
    protected function champTypeDocument(): string
    {
        return 'categorie';
    }

    protected $fillable = [
        'documentable_type', 'documentable_id', 'nom', 'categorie', 'statut',
        'est_requis', 'est_fourni', 'version_actuelle_id',
        'edite_par_id', 'edite_at', 'created_by_id',
        'est_signe_cachete', 'signe_cachete_at', 'signe_cachete_par_id',
    ];

    protected function casts(): array
    {
        return [
            'est_requis'        => 'boolean',
            'est_fourni'        => 'boolean',
            'edite_at'          => 'datetime',
            'est_signe_cachete' => 'boolean',
            'signe_cachete_at'  => 'datetime',
        ];
    }

    public function documentable()
    {
        return $this->morphTo();
    }

    /**
     * Le Dossier dont dépend ce document, quel que soit son documentable réel — utilisé
     * pour autoriser les actions génériques (téléchargement, suppression, historique)
     * de la même façon pour un acte de dossier, une pièce de formalité ou une pièce
     * de partie, sans dupliquer trois fois la logique d'autorisation.
     */
    public function dossierGouvernant(): ?Dossier
    {
        return match (true) {
            $this->documentable instanceof Dossier   => $this->documentable,
            $this->documentable instanceof Formalite => $this->documentable->dossier,
            $this->documentable instanceof Partie    => $this->documentable->dossier,
            // Une pièce de registre (Societe) n'a pas de dossier gouvernant : elle en sert
            // plusieurs. Voir sujetAutorisation().
            default => null,
        };
    }

    /**
     * Objet sur lequel s'autorise l'accès à ce document.
     *
     * Le dossier gouvernant dans le cas général — mais une **pièce constitutive de société**
     * (statuts d'origine, RCCM d'une société que l'étude n'a pas constituée) n'est gouvernée par
     * aucun dossier : elle appartient au registre et sert à tous les dossiers de cette société.
     * `dossierGouvernant()` retourne donc `null` pour elle, et autoriser sur `null` produirait une
     * erreur opaque.
     *
     * `DossierPolicy::view` et `SocietePolicy::view` existent tous deux : les méthodes de lecture de
     * DocumentController passent par ici et fonctionnent pour les deux familles sans branche.
     */
    public function sujetAutorisation(): Dossier|Societe|null
    {
        return $this->dossierGouvernant()
            ?? ($this->documentable instanceof Societe ? $this->documentable : null);
    }

    /**
     * Cette pièce appartient-elle au registre des sociétés plutôt qu'à un dossier ?
     *
     * Les actions mutantes de DocumentController (édition, régénération, suppression, dépôt d'une
     * version signée) sont conçues pour des documents de dossier ; une pièce de registre se gère
     * depuis la société, avec l'autorisation correspondante.
     */
    public function estPieceDeRegistre(): bool
    {
        return $this->documentable instanceof Societe;
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class)->orderBy('numero');
    }

    public function versionActuelle()
    {
        return $this->belongsTo(DocumentVersion::class, 'version_actuelle_id');
    }

    public function editePar()
    {
        return $this->belongsTo(User::class, 'edite_par_id');
    }

    public function creePar()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function signeCachetePar()
    {
        return $this->belongsTo(User::class, 'signe_cachete_par_id');
    }

    /**
     * Ajoute une nouvelle version (upload manuel ou fichier déjà généré/stocké) sans
     * jamais supprimer les précédentes — élimine à la fois la duplication qui existait
     * entre DocumentController et FormaliteController, et le problème des fichiers
     * orphelins laissés par chaque régénération avant cette unification.
     *
     * @param UploadedFile|string $fichier Fichier téléversé, ou chemin déjà stocké sur le disque 'public'
     * @param string $dossierStockage Sous-dossier cible sur le disque 'public' (ex. "documents/{reference}")
     */
    public function nouvelleVersion(UploadedFile|string $fichier, string $dossierStockage, array $meta = []): DocumentVersion
    {
        $numero = ($this->versions()->max('numero') ?? 0) + 1;
        $userId = $meta['cree_par_id'] ?? auth()->id();

        if ($fichier instanceof UploadedFile) {
            $ext        = $fichier->extension();
            $nomFichier = Str::slug(pathinfo($this->nom, PATHINFO_FILENAME)) . '_v' . $numero . ($ext ? '.' . $ext : '');
            $chemin     = $fichier->storeAs($dossierStockage, $nomFichier, 'public');

            $donneesVersion = [
                'chemin_fichier' => $chemin,
                'nom_original'   => $fichier->getClientOriginalName(),
                'mime_type'      => $fichier->getClientMimeType(),
                'taille_octets'  => $fichier->getSize(),
                'source'         => $meta['source'] ?? 'upload',
            ];
        } else {
            // Chemin déjà stocké sur le disque 'public' (ex: document généré par ActesGeneratorService)
            $donneesVersion = [
                'chemin_fichier' => $fichier,
                'nom_original'   => $meta['nom_original'] ?? basename($fichier),
                'mime_type'      => $meta['mime_type'] ?? null,
                'taille_octets'  => $meta['taille_octets'] ?? (Storage::disk('public')->exists($fichier) ? Storage::disk('public')->size($fichier) : null),
                'source'         => $meta['source'] ?? 'genere',
            ];
        }

        $donneesVersion['hash_sha256'] = self::hasherFichier($donneesVersion['chemin_fichier']);

        $version = $this->versions()->create([...$donneesVersion, 'numero' => $numero, 'cree_par_id' => $userId]);

        $this->update([
            'version_actuelle_id' => $version->id,
            'est_fourni'          => true,
            'edite_par_id'        => $userId,
            'edite_at'            => now(),
        ]);

        return $version;
    }

    /**
     * Restaure une ancienne version en la recopiant comme nouvelle version courante —
     * jamais de rollback destructif : l'historique reste strictement croissant et
     * compréhensible (voir plan GED, §3).
     */
    public function restaurerVersion(DocumentVersion $ancienne): DocumentVersion
    {
        $numero          = ($this->versions()->max('numero') ?? 0) + 1;
        $ext             = pathinfo($ancienne->chemin_fichier, PATHINFO_EXTENSION);
        $dossierStockage = pathinfo($ancienne->chemin_fichier, PATHINFO_DIRNAME);
        $nomFichier      = Str::slug(pathinfo($this->nom, PATHINFO_FILENAME)) . '_v' . $numero . ($ext ? '.' . $ext : '');
        $nouveauChemin   = $dossierStockage . '/' . $nomFichier;

        Storage::disk('public')->copy($ancienne->chemin_fichier, $nouveauChemin);

        $version = $this->versions()->create([
            'numero'         => $numero,
            'chemin_fichier' => $nouveauChemin,
            'nom_original'   => $ancienne->nom_original,
            'mime_type'      => $ancienne->mime_type,
            'taille_octets'  => $ancienne->taille_octets,
            'hash_sha256'    => self::hasherFichier($nouveauChemin),
            'source'         => 'restauration',
            'cree_par_id'    => auth()->id(),
        ]);

        $this->update([
            'version_actuelle_id' => $version->id,
            'est_fourni'          => true,
            'edite_par_id'        => auth()->id(),
            'edite_at'            => now(),
        ]);

        return $version;
    }

    /**
     * Empreinte SHA-256 du fichier stocké — permet de vérifier après coup qu'un
     * téléchargement correspond bien à ce qui a été enregistré (valeur probatoire,
     * utile en particulier pour les versions signées/cachetées).
     */
    private static function hasherFichier(string $chemin): ?string
    {
        return Storage::disk('public')->exists($chemin)
            ? hash_file('sha256', Storage::disk('public')->path($chemin))
            : null;
    }

    public function supprimerAvecFichiers(): void
    {
        foreach ($this->versions as $version) {
            if ($version->chemin_fichier) {
                Storage::disk('public')->delete($version->chemin_fichier);
            }
        }

        $this->delete();
    }
}
