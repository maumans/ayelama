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
        return $this->points->where('etat', 'conforme')->count();
    }

    public function nombreNonConformes(): int
    {
        return $this->points->where('etat', 'non_conforme')->count();
    }

    public function nombreEvalues(): int
    {
        return $this->points->whereNotNull('etat')->count();
    }

    public function estValidable(): bool
    {
        return $this->nombreNonConformes() === 0;
    }

    public function valider(User $reviseur): void
    {
        $this->update([
            'statut'     => StatutRevision::Valide,
            'reviseur_id' => $reviseur->id,
            'valide_at'  => now(),
        ]);
    }

    public function renvoyer(User $reviseur, ?string $commentaire = null): void
    {
        $this->update([
            'statut'             => StatutRevision::Renvoye,
            'reviseur_id'        => $reviseur->id,
            'commentaire' => $commentaire,
            'renvoye_at'         => now(),
        ]);
    }
}
