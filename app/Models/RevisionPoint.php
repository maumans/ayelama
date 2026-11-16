<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionPoint extends Model
{
    protected $table = 'revision_points';

    protected $fillable = ['revision_id', 'point_id', 'etat', 'commentaire', 'perime'];

    protected function casts(): array
    {
        return [
            'perime' => 'boolean',
        ];
    }

    public function revision()
    {
        return $this->belongsTo(Revision::class);
    }

    /**
     * Le contenu d'un document vient de changer : la vérification déjà enregistrée n'est plus
     * fiable. On la marque périmée plutôt que de la supprimer — le verdict et le commentaire du
     * certificateur restent visibles (contexte pour le rédacteur) jusqu'au prochain envoi en
     * certification, qui les purgera (`Revision::resetPoints()`).
     *
     * Partagé par la régénération (`ActesGeneratorService::regenererDocument()`) et le dépôt d'une
     * version corrigée (`DocumentController::corriger()`) : deux façons de changer un acte, une
     * seule règle.
     */
    public static function perimerPour(DocumentFichier $document, Dossier $dossier): void
    {
        static::where('point_id', (string) $document->id)
            ->whereHas('revision', fn ($q) => $q->where('dossier_id', $dossier->id))
            ->update(['perime' => true]);
    }
}
