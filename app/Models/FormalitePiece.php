<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormalitePiece extends Model
{
    protected $table = 'formalite_pieces';

    protected $fillable = ['formalite_id', 'label', 'est_fourni', 'fourni_at'];

    protected function casts(): array
    {
        return [
            'est_fourni' => 'boolean',
            'fourni_at'  => 'datetime',
        ];
    }

    public function formalite()
    {
        return $this->belongsTo(Formalite::class);
    }
}
