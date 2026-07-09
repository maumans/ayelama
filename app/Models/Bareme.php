<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Bareme extends Model
{
    protected $fillable = [
        'type_acte_id', 'organisme', 'libelle',
        'taux', 'montant_fixe', 'base_calcul',
        'description', 'actif', 'ordre',
        'genere_formalite', 'obligatoire', 'type_impot',
        'retour_attendu', 'delai_heures', 'pieces_requises',
    ];

    protected function casts(): array
    {
        return [
            'taux'             => 'decimal:4',
            'montant_fixe'     => 'decimal:2',
            'actif'            => 'boolean',
            'genere_formalite' => 'boolean',
            'obligatoire'      => 'boolean',
            'pieces_requises'  => 'array',
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

    public function scopeGenereFormalite($query)
    {
        return $query->where('genere_formalite', true);
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

    /**
     * Convertit l'organisme (nomenclature Bareme, PascalCase libre) vers la nomenclature
     * attendue par Formalite::organisme (snake_case) — utilisé uniquement par
     * FormaliteGenerationService au moment de générer la Formalite correspondante.
     */
    public function organismeFormalite(): string
    {
        return match ($this->organisme) {
            'APIP'         => 'apip',
            'Impots'       => 'impots',
            'Conservation' => 'conservation_fonciere',
            'CNSS'         => 'cnss',
            default        => Str::slug($this->organisme, '_'),
        };
    }
}
