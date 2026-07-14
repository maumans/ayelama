<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Demande extends Model
{
    protected $fillable = [
        'token', 'type_acte_id', 'client_role', 'statut', 'expire_at',
        'cree_par_id', 'objet', 'donnees', 'parties', 'source', 'fichier_scan',
        'soumise_at', 'traitee_par_id', 'traitee_at', 'dossier_id',
    ];

    protected function casts(): array
    {
        return [
            'expire_at'  => 'datetime',
            'donnees'    => 'array',
            'parties'    => 'array',
            'soumise_at' => 'datetime',
            'traitee_at' => 'datetime',
        ];
    }

    public function typeActe()
    {
        return $this->belongsTo(TypeActe::class);
    }

    public function creePar()
    {
        return $this->belongsTo(User::class, 'cree_par_id');
    }

    public function traiteePar()
    {
        return $this->belongsTo(User::class, 'traitee_par_id');
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function scopeActif($query)
    {
        return $query->whereIn('statut', ['en_attente', 'soumise']);
    }

    public function estExpiree(): bool
    {
        return $this->expire_at->isPast();
    }

    public function estUtilisable(): bool
    {
        return in_array($this->statut, ['en_attente', 'soumise'], true) && !$this->estExpiree();
    }
}
