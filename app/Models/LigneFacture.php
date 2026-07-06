<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LigneFacture extends Model
{
    protected $table = 'lignes_factures';

    protected $fillable = [
        'facture_id',
        'designation',
        'quantite',
        'montant',
    ];

    protected function casts(): array
    {
        return [
            'quantite' => 'integer',
            'montant'  => 'decimal:2',
        ];
    }

    public function facture()
    {
        return $this->belongsTo(Facture::class);
    }

    /**
     * Montant total de cette ligne (quantité × montant unitaire).
     */
    public function total(): float
    {
        return $this->quantite * (float) $this->montant;
    }
}
