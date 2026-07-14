<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormalitePiece extends Model
{
    protected $table = 'formalite_pieces';

    protected $fillable = [
        'formalite_id', 'label', 'est_fourni', 'fourni_at',
        'chemin_fichier', 'nom_original', 'mime_type', 'taille_octets',
        'televerse_par_id', 'televerse_at',
    ];

    protected function casts(): array
    {
        return [
            'est_fourni'    => 'boolean',
            'fourni_at'     => 'datetime',
            'taille_octets' => 'integer',
            'televerse_at'  => 'datetime',
        ];
    }

    public function formalite()
    {
        return $this->belongsTo(Formalite::class);
    }

    public function televersePar()
    {
        return $this->belongsTo(User::class, 'televerse_par_id');
    }
}
