<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banque extends Model
{
    protected $fillable = [
        'dossier_id',
        'denomination', 'forme',
        'quartier', 'commune', 'ville',
        'montant_credit_chiffres', 'type_garantie', 'rang_hypothecaire',
    ];

    protected function casts(): array
    {
        return [
            'montant_credit_chiffres' => 'decimal:2',
        ];
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }
}
