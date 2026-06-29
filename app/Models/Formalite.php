<?php

namespace App\Models;

use App\Enums\StatutFormalite;
use Illuminate\Database\Eloquent\Model;

class Formalite extends Model
{
    protected $fillable = [
        'dossier_id', 'organisme', 'statut',
        'taux', 'montant_base', 'montant_calcule', 'type_impot',
        'retour_attendu', 'delai_heures',
        'depose_at', 'retour_at', 'echeance_at',
    ];

    protected function casts(): array
    {
        return [
            'statut'          => StatutFormalite::class,
            'taux'            => 'decimal:4',
            'montant_base'    => 'decimal:2',
            'montant_calcule' => 'decimal:2',
            'depose_at'       => 'datetime',
            'retour_at'       => 'datetime',
            'echeance_at'     => 'datetime',
        ];
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function pieces()
    {
        return $this->hasMany(FormalitePiece::class);
    }

    public function estUrgente(): bool
    {
        if (!$this->echeance_at) return false;
        if (in_array($this->statut?->value, ['retour_recu', 'cloture'])) return false;
        $dateStr = $this->echeance_at->toDateString();
        $today   = now()->toDateString();
        if ($dateStr < $today) return false;
        if ($dateStr === $today) return true;
        return now()->diffInHours($this->echeance_at->copy()->endOfDay(), false) <= 8;
    }

    public function estDepassee(): bool
    {
        return $this->echeance_at
            && $this->echeance_at->toDateString() < now()->toDateString()
            && !in_array($this->statut?->value, ['retour_recu', 'cloture']);
    }

    public function heuresRestantes(): ?int
    {
        if (!$this->echeance_at) return null;
        return max(0, (int) now()->diffInHours($this->echeance_at, false));
    }

    public function calculerMontant(): void
    {
        if ($this->taux && $this->montant_base) {
            $this->montant_calcule = round($this->montant_base * $this->taux, 2);
            $this->save();
        }
    }

    public function labelOrganisme(): string
    {
        return match($this->organisme) {
            'apip'                => 'APIP',
            'impots'              => 'Direction des Impôts',
            'conservation_fonciere' => 'Conservation foncière',
            'cnss'                => 'CNSS',
            default               => ucfirst($this->organisme),
        };
    }

    public function scopeUrgentes($query)
    {
        return $query->whereNotNull('echeance_at')
            ->where('echeance_at', '<=', now()->addHours(8))
            ->whereNotIn('statut', ['retour_recu', 'cloture']);
    }
}
