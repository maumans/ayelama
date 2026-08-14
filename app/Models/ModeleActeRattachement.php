<?php

namespace App\Models;

use App\Support\VariantesTypeActe;
use Illuminate\Database\Eloquent\Model;

/**
 * « Ce gabarit sert cette procédure. »
 *
 * Le rattachement d'un modèle d'acte à un type d'acte, éventuellement restreint à une **variante**
 * de ce type — `variante = null` signifiant « toutes ». C'est ce qui permet de déclarer qu'un acte de
 * cession ne sert qu'aux cessions de parts, tandis qu'un procès-verbal sert les sept résolutions.
 *
 * Entité et non simple pivot : la variante est une donnée porteuse de sens, affichée et éditée dans
 * la vue Processus, et `sync()` sur un `belongsToMany` s'y prête mal.
 */
class ModeleActeRattachement extends Model
{
    protected $table = 'modele_acte_rattachements';

    protected $fillable = ['modele_acte_id', 'type_acte_id', 'variante'];

    public function modeleActe()
    {
        return $this->belongsTo(ModeleActe::class);
    }

    public function typeActe()
    {
        return $this->belongsTo(TypeActe::class);
    }

    /** Libellé de la variante, ou « toutes les variantes » quand elle n'est pas restreinte. */
    public function varianteLabel(): string
    {
        if ($this->variante === null) {
            return 'Toutes les variantes';
        }

        return VariantesTypeActe::resoudre($this->typeActe?->code, $this->variante)?->label()
            ?? $this->variante;
    }

    /**
     * Ce rattachement couvre-t-il cette variante ?
     *
     * Un rattachement sans variante couvre tout le type d'acte — c'est le cas de tous les
     * rattachements repris de l'ancien pivot, d'où l'absence de changement de comportement.
     */
    public function couvre(?string $variante): bool
    {
        return $this->variante === null || $this->variante === $variante;
    }
}
