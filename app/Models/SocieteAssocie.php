<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un associé d'une société, sur la période où il l'a été.
 *
 * **Une ligne par passage, pas une par personne.** `date_sortie` nulle signifie « associé
 * actuel » ; un associé qui revient après avoir tout cédé a deux lignes. C'est ce qui permet de
 * répondre à « qui détenait quoi en mars 2027 » sans journal parallèle.
 *
 * ⚠️ `client_id` est facultatif et `nom` porté en clair : la table `parties` fait déjà ce choix,
 * et des parties existent sans fiche client — c'est précisément d'elles que part le backfill.
 */
class SocieteAssocie extends Model
{
    protected $table = 'societe_associes';

    protected $fillable = [
        'societe_id', 'client_id', 'nom', 'nb_parts',
        'date_entree', 'date_sortie', 'dossier_entree_id', 'dossier_sortie_id',
    ];

    protected function casts(): array
    {
        return [
            'nb_parts'     => 'integer',
            'date_entree'  => 'date',
            'date_sortie'  => 'date',
        ];
    }

    public function societe(): BelongsTo
    {
        return $this->belongsTo(Societe::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Les associés d'aujourd'hui — ceux qui ne sont pas sortis. */
    public function scopeActuels(Builder $query): Builder
    {
        return $query->whereNull('date_sortie');
    }

    public function estActuel(): bool
    {
        return $this->date_sortie === null;
    }

    /**
     * Part du capital, en pourcentage.
     *
     * Calculée à la demande et jamais stockée : elle dépend du nombre total de parts, qui change
     * à chaque augmentation. Une colonne figée aurait menti dès la première opération.
     */
    public function pourcentage(int $partsTotales): ?float
    {
        return $partsTotales > 0 ? round($this->nb_parts * 100 / $partsTotales, 2) : null;
    }
}
