<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BienImmobilier extends Model
{
    protected $fillable = [
        'dossier_id',
        'parcelle_numero', 'lot_numero', 'lieu_de',
        'nature_terrain', 'usage', 'superficie_m2', 'pcp',
        'titre_foncier_numero', 'tf_date',
        'livre_foncier_ville', 'tf_volume', 'tf_folio', 'tf_annee',
        'limites_ne', 'limites_so', 'limites_se', 'limites_no',
        'origine_propriete', 'prix_vente_chiffres',
        // Bail
        'type_bail', 'duree_bail', 'date_prise_effet',
        'loyer_chiffres', 'periodicite_loyer',
        'destination_bien', 'engagement_construction',
    ];

    protected function casts(): array
    {
        return [
            'superficie_m2'      => 'decimal:2',
            'prix_vente_chiffres' => 'decimal:2',
            'loyer_chiffres'     => 'decimal:2',
            'duree_bail'         => 'integer',
            'tf_date'            => 'date',
            'date_prise_effet'   => 'date',
        ];
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function loyerTotalBail(): float
    {
        if (!$this->loyer_chiffres || !$this->duree_bail) {
            return 0;
        }

        $facteur = match ($this->periodicite_loyer) {
            'mensuel'    => 12,
            'trimestriel' => 4,
            'annuel'     => 1,
            default      => 12,
        };

        return (float) $this->loyer_chiffres * $facteur * $this->duree_bail;
    }
}
