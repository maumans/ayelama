<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Trace du contrôle d'une pièce avant clôture d'un dossier.
 *
 * Polymorphe : la même coche s'applique à un `DocumentFichier`, un `Courrier` ou un
 * `Recu` — voir la migration create_cloture_verifications_table pour le choix d'une
 * table dédiée plutôt que de colonnes réparties sur trois tables.
 */
class ClotureVerification extends Model
{
    protected $table = 'cloture_verifications';

    protected $fillable = ['dossier_id', 'verifiable_type', 'verifiable_id', 'verifie_par_id', 'verifie_at'];

    protected function casts(): array
    {
        return ['verifie_at' => 'datetime'];
    }

    public function verifiable()
    {
        return $this->morphTo();
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function verifiePar()
    {
        return $this->belongsTo(User::class, 'verifie_par_id');
    }
}
