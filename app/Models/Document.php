<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'dossier_id', 'nom', 'type_document', 'statut',
        'chemin_fichier', 'version', 'signature_client_requise',
        'edite_at', 'signe_client_at', 'signe_notaire_at', 'edite_par',
    ];

    protected function casts(): array
    {
        return [
            'signature_client_requise' => 'boolean',
            'edite_at'                => 'datetime',
            'signe_client_at'         => 'datetime',
            'signe_notaire_at'        => 'datetime',
        ];
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function editePar()
    {
        return $this->belongsTo(User::class, 'edite_par');
    }

    public function estEdite(): bool       { return $this->statut !== 'a_editer'; }
    public function estSigneClient(): bool { return $this->signe_client_at !== null; }
    public function estSigneNotaire(): bool { return $this->signe_notaire_at !== null; }
}
