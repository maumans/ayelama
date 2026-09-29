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
        'exigence_accord',
    ];

    /**
     * ⚠️ `actes_requis` et `fiche_modification_obligatoire` sont **morts** (constaté 2026-09-28).
     *
     * `actes_requis` est déclaré ci-dessus et casté ci-dessous alors que **la colonne n'existe
     * pas** dans la table : y passer une valeur lèverait une erreur SQL. `fiche_modification_obligatoire`
     * existe, est seedée à `true` pour SOC-MOD, et n'est lue nulle part.
     *
     * Ils sont laissés en l'état — les retirer est une passe de nettoyage à part, qui demande de
     * vérifier qu'aucun import ni seeder externe ne les pose. Mais leur sort est la raison pour
     * laquelle `exigence_accord` arrive avec un lecteur, un écran et un test : voir
     * {@see \App\Support\AccordsInitialisation}.
     */

    protected function casts(): array
    {
        return [
            'categorie'                     => \App\Enums\CategorieActe::class,
            'actes_requis'                  => 'array',
            'fiche_modification_obligatoire' => 'boolean',
            'exigence_accord'               => \App\Enums\ExigenceAccord::class,
            'actif'                         => 'boolean',
        ];
    }

    // Relations
    public function dossiers()
    {
        return $this->hasMany(Dossier::class);
    }

    // `modeles()` a été supprimée avec la colonne `modeles_actes.type_acte_id` (2026-08-11) : un
    // `hasMany` ne pouvait voir ni les gabarits partagés, ni `applicable_tous`, ni les variantes.
    // `ModeleActe::pourTypeActe()` fait ce travail correctement.

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
