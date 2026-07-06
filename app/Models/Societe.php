<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Societe extends Model
{
    protected $fillable = [
        'dossier_id',
        'denomination', 'forme', 'sigle',
        'capital_chiffres', 'nombre_parts', 'valeur_nominale_chiffres',
        'siege_quartier', 'siege_commune', 'siege_ville',
        'email_societe', 'telephone_societe',
        'objet_social', 'duree', 'exercice_social',
        'date_acte',
        'rccm_numero', 'nif', 'jal_journal',
        'direction',
        'commissaire_titulaire', 'commissaire_suppleant',
    ];

    protected function casts(): array
    {
        return [
            'capital_chiffres'         => 'decimal:2',
            'valeur_nominale_chiffres' => 'decimal:2',
            'nombre_parts'             => 'integer',
            'duree'                    => 'integer',
            'date_acte'                => 'date',
            'direction'                => 'array',
        ];
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function formeLabel(): string
    {
        return match ($this->forme) {
            'SARL'  => 'Société à Responsabilité Limitée',
            'SARLU' => 'Société à Responsabilité Limitée Unipersonnelle',
            'SAS'   => 'Société par Actions Simplifiée',
            'SASU'  => 'Société par Actions Simplifiée Unipersonnelle',
            'SA'    => 'Société Anonyme',
            'GIE'   => 'Groupement d\'Intérêt Économique',
            default => $this->forme ?? '',
        };
    }
}
