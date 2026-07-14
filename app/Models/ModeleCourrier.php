<?php

namespace App\Models;

use App\Concerns\HasTypeDocumentLabel;
use Illuminate\Database\Eloquent\Model;

class ModeleCourrier extends Model
{
    use HasTypeDocumentLabel;

    protected $table = 'modeles_courriers';

    protected $fillable = [
        'nom', 'type_document', 'chemin_fichier', 'version',
        'est_actif', 'applicable_tous', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'est_actif'       => 'boolean',
            'applicable_tous' => 'boolean',
        ];
    }

    public function typesActes()
    {
        return $this->belongsToMany(TypeActe::class, 'modele_courrier_type_acte');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActif($query)
    {
        return $query->where('est_actif', true);
    }

    /**
     * true si cette lettre s'applique au type d'acte donné — soit parce
     * qu'elle est marquée « applicable à tous », soit par liaison explicite.
     */
    public function applicablePour(?TypeActe $typeActe): bool
    {
        if (!$typeActe) {
            return false;
        }

        return $this->applicable_tous || $this->typesActes->contains('id', $typeActe->id);
    }
}
