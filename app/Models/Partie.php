<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Partie extends Model
{
    protected $fillable = [
        'dossier_id', 'client_id', 'nom', 'role', 'cni',
        'telephone', 'adresse', 'email',
        'photo_chemin', 'pieces',
    ];

    protected function casts(): array
    {
        return ['pieces' => 'array'];
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function getInitialesAttribute(): string
    {
        return strtoupper(
            collect(explode(' ', $this->nom))
                ->map(fn($w) => $w[0] ?? '')
                ->take(2)
                ->join('')
        );
    }
}
