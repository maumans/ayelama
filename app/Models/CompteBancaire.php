<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un compte bancaire de l'étude — celui sur lequel le client règle une facture.
 *
 * L'étude choisit la banque facture par facture ; le numéro suit. La facture en garde une copie
 * (`Facture::banque` / `compte_numero`) : corriger un numéro ici ne réécrit pas une facture déjà
 * remise. Voir la migration `2026_11_20_120000_create_comptes_bancaires_table`.
 */
class CompteBancaire extends Model
{
    protected $table = 'comptes_bancaires';

    protected $fillable = ['banque', 'numero', 'actif', 'a_verifier', 'ordre'];

    protected function casts(): array
    {
        return [
            'actif'      => 'boolean',
            'a_verifier' => 'boolean',
            'ordre'      => 'integer',
        ];
    }

    public function factures()
    {
        return $this->hasMany(Facture::class);
    }

    public function scopeActifs($query)
    {
        return $query->where('actif', true)->orderBy('ordre')->orderBy('banque');
    }

    /** @return array<string, mixed> */
    public function versArray(): array
    {
        return [
            'id'         => $this->id,
            'banque'     => $this->banque,
            'numero'     => $this->numero,
            'actif'      => $this->actif,
            'a_verifier' => $this->a_verifier,
            'ordre'      => $this->ordre,
            'nbFactures' => $this->factures_count ?? null,
        ];
    }
}
