<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Courrier extends Model
{
    protected $fillable = [
        'reference', 'dossier_id', 'redacteur_id',
        'destinataire', 'adresse', 'objet',
        'type', 'statut', 'contenu', 'chemin_fichier', 'envoye_at',
        'est_requis', 'est_signe_cachete', 'signe_cachete_at', 'signe_cachete_par_id',
    ];

    protected function casts(): array
    {
        return [
            'envoye_at'         => 'datetime',
            'est_requis'        => 'boolean',
            'est_signe_cachete' => 'boolean',
            'signe_cachete_at'  => 'datetime',
        ];
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function redacteur()
    {
        return $this->belongsTo(User::class, 'redacteur_id');
    }

    public function signeCachetePar()
    {
        return $this->belongsTo(User::class, 'signe_cachete_par_id');
    }

    public function estEnvoye(): bool
    {
        return $this->statut === 'envoye';
    }

    public function scopeBrouillon($query)
    {
        return $query->where('statut', 'brouillon');
    }

    public function scopeEnvoye($query)
    {
        return $query->where('statut', 'envoye');
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'transmission' => 'Lettre de transmission',
            'convocation'  => 'Convocation',
            'relance'      => 'Relance',
            'divers'       => 'Divers',
            default        => $this->type,
        };
    }
}
