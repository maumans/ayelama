<?php

namespace App\Models;

use App\Enums\CategorieActe;
use Illuminate\Database\Eloquent\Model;

class TypeActe extends Model
{
    protected $table = 'types_actes';

    protected $fillable = [
        'code', 'label', 'categorie',
        'prefixe_reference', 'delai_jours', 'description',
        'actes_requis', 'fiche_modification_obligatoire', 'actif', 'ordre',
    ];

    protected function casts(): array
    {
        return [
            'categorie'                     => \App\Enums\CategorieActe::class,
            'actes_requis'                  => 'array',
            'fiche_modification_obligatoire' => 'boolean',
            'actif'                         => 'boolean',
        ];
    }

    // Relations
    public function dossiers()
    {
        return $this->hasMany(Dossier::class);
    }

    public function modeles()
    {
        return $this->hasMany(ModeleActe::class)->where('est_actif', true);
    }

    public function modelesCourriers()
    {
        return $this->belongsToMany(ModeleCourrier::class, 'modele_courrier_type_acte');
    }

    public function baremes()
    {
        return $this->hasMany(Bareme::class)->orderBy('ordre');
    }

    // Scopes
    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function scopeParCategorie($query, string $categorie)
    {
        return $query->where('categorie', $categorie)->orderBy('ordre');
    }
}
