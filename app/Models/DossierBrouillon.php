<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Saisie inachevée de l'assistant de création de dossier.
 *
 * Pas un `Dossier` : la référence notariale est attribuée à la création, et un
 * brouillon abandonné y laisserait un trou définitif dans la numérotation.
 */
class DossierBrouillon extends Model
{
    protected $table = 'dossier_brouillons';

    protected $fillable = ['user_id', 'type_acte_id', 'libelle', 'etat'];

    protected function casts(): array
    {
        return ['etat' => 'array'];
    }

    /**
     * Répertoire privé des pièces téléversées depuis l'assistant avant que le
     * dossier — et donc les `Partie` auxquelles ces pièces se rattacheront —
     * n'existe. Disque `local` : ce sont des pièces d'identité, elles n'ont rien à
     * faire sur le disque public tant qu'elles ne sont pas rattachées.
     */
    public function repertoire(): string
    {
        return "brouillons/{$this->id}";
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function typeActe()
    {
        return $this->belongsTo(TypeActe::class);
    }

    /**
     * Chemins des pièces téléversées, tels que stockés dans l'état.
     *
     * Forme : ['associe_unique' => ['cni' => ['chemin' => …, 'nom' => …]], …]
     * La clé de groupe est le rôle (section scalaire) ou l'id du bloc répétable ;
     * la clé de pièce est la catégorie, ou "{index}:{catégorie}" dans un bloc.
     *
     * @return array<string, array<string, array{chemin: string, nom: string}>>
     */
    public function piecesTeleversees(): array
    {
        return $this->etat['piecesBrouillon'] ?? [];
    }

    /**
     * Reconstruit un `UploadedFile` à partir d'une pièce du brouillon, pour la
     * faire passer par le même chemin que n'importe quel téléversement
     * (DocumentFichier::nouvelleVersion) — nommage, hash et versionnage inclus.
     *
     * Retourne null si le fichier a disparu du disque : un brouillon dont les
     * fichiers ont été purgés doit rester finalisable, quitte à ce que la pièce
     * soit simplement manquante.
     */
    public function fichierPourPiece(string $cheminRelatif, ?string $nomOriginal = null): ?UploadedFile
    {
        if (!Storage::disk('local')->exists($cheminRelatif)) {
            return null;
        }

        return new UploadedFile(
            Storage::disk('local')->path($cheminRelatif),
            $nomOriginal ?: basename($cheminRelatif),
            null,
            null,
            true, // test mode : le fichier vient du disque, pas d'un upload HTTP
        );
    }

    /**
     * Supprime le brouillon et les fichiers qu'il détenait — appelé à la
     * finalisation comme à l'abandon, pour ne jamais accumuler d'orphelins (le
     * projet en traîne déjà 117 hérités d'avant la GED unifiée).
     */
    public function supprimerAvecFichiers(): void
    {
        Storage::disk('local')->deleteDirectory($this->repertoire());
        $this->delete();
    }
}
