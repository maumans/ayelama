<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentVersion extends Model
{
    protected $fillable = [
        'document_fichier_id', 'numero', 'chemin_fichier',
        'nom_original', 'mime_type', 'taille_octets', 'hash_sha256',
        'source', 'cree_par_id',
    ];

    protected function casts(): array
    {
        return [
            'taille_octets' => 'integer',
        ];
    }

    public function documentFichier()
    {
        return $this->belongsTo(DocumentFichier::class);
    }

    public function creePar()
    {
        return $this->belongsTo(User::class, 'cree_par_id');
    }
}
