<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bareme extends Model
{
    protected $fillable = [
        'type_acte_id', 'organisme', 'libelle',
        'taux', 'montant_fixe', 'base_calcul',
        'description', 'actif', 'ordre',
    ];

    protected function casts(): array
    {
        return [
            'taux'         => 'decimal:4',
            'montant_fixe' => 'decimal:2',
            'actif'        => 'boolean',
        ];
    }

    public function typeActe()
    {
        return $this->belongsTo(TypeActe::class);
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function calculerMontant(float $valeurActe): float
    {
        if ($this->base_calcul === 'montant_fixe') {
            return (float) ($this->montant_fixe ?? 0);
        }

        return round($valeurActe * ((float) $this->taux / 100), 2);
    }

    public function organismeLabel(): string
    {
        return match ($this->organisme) {
            'APIP'         => 'APIP',
            'Impots'       => 'Impôts',
            'Conservation' => 'Conservation foncière',
            'CNSS'         => 'CNSS',
            'Notaire'      => 'Honoraires notaire',
            default        => $this->organisme,
        };
    }
}
