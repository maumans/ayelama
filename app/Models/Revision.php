<?php

namespace App\Models;

use App\Enums\StatutRevision;
use Illuminate\Database\Eloquent\Model;

class Revision extends Model
{
    protected $fillable = [
        'dossier_id', 'reviseur_id', 'statut',
        'commentaire', 'valide_at', 'renvoye_at',
    ];

    protected function casts(): array
    {
        return [
            'statut'     => StatutRevision::class,
            'valide_at'  => 'datetime',
            'renvoye_at' => 'datetime',
        ];
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function reviseur()
    {
        return $this->belongsTo(User::class, 'reviseur_id');
    }

    public function points()
    {
        return $this->hasMany(RevisionPoint::class);
    }

    // Calculs
    public function nombreConformes(): int
    {
        return $this->points->where('etat', 'ok')->count();
    }

    public function nombreNonConformes(): int
    {
        return $this->points->where('etat', 'a_corriger')->count();
    }

    public function nombreEvalues(): int
    {
        return $this->points->whereNotNull('etat')->count();
    }

    public function estValidable(): bool
    {
        $totalDocuments = $this->dossier->documents()->count();

        return $totalDocuments > 0
            && $this->nombreNonConformes() === 0
            && $this->nombreEvalues() === $totalDocuments;
    }

    public function resetPoints(): void
    {
        $this->points()->where('etat', 'a_corriger')->delete();
        $this->update([
            'statut'     => StatutRevision::EnAttente,
            'commentaire' => null,
            'valide_at'  => null,
            'renvoye_at' => null,
        ]);
    }

    public function valider(User $reviseur): void
    {
        $this->update([
            'statut'     => StatutRevision::Valide,
            'valide_at'  => now(),
        ]);
    }

    public function renvoyer(User $reviseur, ?string $commentaire = null): void
    {
        $this->update([
            'statut'      => StatutRevision::Renvoye,
            'commentaire' => $commentaire,
            'renvoye_at'  => now(),
        ]);
    }
}
