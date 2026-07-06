<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $fillable = [
        'type',
        // Personne physique
        'civilite', 'prenom_nom', 'ne_a', 'date_naissance',
        'nationalite', 'piece_type', 'piece_numero',
        'piece_delivree_le', 'piece_delivree_a', 'piece_expire_le',
        'situation_matrimoniale', 'regime_matrimonial',
        // Personne morale
        'denomination', 'forme', 'rccm', 'representant_legal',
        // Communs
        'demeurant_ville', 'quartier', 'commune', 'pays',
        'telephone', 'email', 'siege',
    ];

    protected function casts(): array
    {
        return [
            'date_naissance'     => 'date',
            'piece_delivree_le'  => 'date',
            'piece_expire_le'    => 'date',
        ];
    }

    public function parties()
    {
        return $this->hasMany(Partie::class);
    }

    public function estPersonnePhysique(): bool
    {
        return $this->type === 'physique';
    }

    public function nomComplet(): string
    {
        return $this->type === 'physique'
            ? trim(($this->civilite ? $this->civilite . ' ' : '') . $this->prenom_nom)
            : $this->denomination ?? '';
    }
}
