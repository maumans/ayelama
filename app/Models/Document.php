<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $fillable = [
        'dossier_id', 'nom', 'type_document', 'statut',
        'chemin_fichier', 'version',
        'edite_at', 'edite_par',
    ];

    protected function casts(): array
    {
        return [
            'edite_at' => 'datetime',
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

    public function estEdite(): bool { return $this->statut !== 'a_editer'; }
}
