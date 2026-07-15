<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Paiement extends Model
{
    protected $fillable = [
        'facture_id', 'date_paiement', 'montant',
        'moyen_paiement', 'notes', 'enregistre_par_id',
    ];

    protected function casts(): array
    {
        return [
            'date_paiement' => 'date',
            'montant'       => 'decimal:2',
        ];
    }

    public function facture()
    {
        return $this->belongsTo(Facture::class);
    }

    public function enregistrePar()
    {
        return $this->belongsTo(User::class, 'enregistre_par_id');
    }

    public function recu()
    {
        return $this->hasOne(Recu::class);
    }
}
