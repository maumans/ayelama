<?php

namespace App\Models;

use App\Concerns\HasTypeDocumentLabel;
use Illuminate\Database\Eloquent\Model;

class ModeleActe extends Model
{
    use HasTypeDocumentLabel;

    protected $table = 'modeles_actes';

    protected $fillable = [
        'type_acte_id', 'nom', 'type_document', 'chemin_fichier',
        'version', 'est_actif', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'est_actif' => 'boolean',
        ];
    }

    public function typeActe()
    {
        return $this->belongsTo(TypeActe::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActif($query)
    {
        return $query->where('est_actif', true);
    }
}
