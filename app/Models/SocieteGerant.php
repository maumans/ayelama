<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un gérant d'une société, sur la durée de son mandat.
 *
 * `statutaire` dit s'il est nommé dans les statuts : le remplacer relève alors d'une AGE, là où
 * nommer un gérant non statutaire relève d'une AGO. C'est la distinction que
 * {@see \App\Enums\TypeModificationStatutaire} porte déjà entre `GerantStatutaire` et
 * `GerantNonStatutaire`, et la seule dont dépend `impacteStatuts()`.
 */
class SocieteGerant extends Model
{
    protected $table = 'societe_gerants';

    protected $fillable = [
        'societe_id', 'client_id', 'nom', 'statutaire',
        'date_nomination', 'date_fin', 'motif_fin',
        'dossier_nomination_id', 'dossier_fin_id',
    ];

    protected function casts(): array
    {
        return [
            'statutaire'      => 'boolean',
            'date_nomination' => 'date',
            'date_fin'        => 'date',
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

    /** Les gérants en fonction. */
    public function scopeEnFonction(Builder $query): Builder
    {
        return $query->whereNull('date_fin');
    }

    public function estEnFonction(): bool
    {
        return $this->date_fin === null;
    }

    /**
     * Libellé du motif de fin.
     *
     * Repli explicite sur la valeur brute : `motif_fin` est une chaîne libre en base — la liste
     * de la spec n'est pas arrêtée — donc une valeur inattendue doit s'afficher telle quelle
     * plutôt que faire échouer la fiche.
     */
    public function motifFinLabel(): ?string
    {
        return match ($this->motif_fin) {
            null, ''      => null,
            'demission'   => 'démission',
            'revocation'  => 'révocation',
            'deces'       => 'décès',
            'fin_mandat'  => 'fin de mandat',
            default       => $this->motif_fin,
        };
    }
}
