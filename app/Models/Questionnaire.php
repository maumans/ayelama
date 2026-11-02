<?php

namespace App\Models;

use App\Enums\ModeTraitement;
use Illuminate\Database\Eloquent\Model;

class Questionnaire extends Model
{
    protected $fillable = ['dossier_id', 'donnees'];

    protected function casts(): array
    {
        return ['donnees' => 'array'];
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get($this->donnees, $key, $default);
    }

    /**
     * Qui rédige l'acte — addendum v1.2 § 5.2.
     *
     * Un seul endroit où lire ce choix, parce qu'il en aura plusieurs à le consommer : les
     * actes produits (§ 5.2), les pièces réclamées (§ 5.3) et la checklist (§ 7.2). Répartir la
     * lecture, c'est répartir l'oubli — le dépôt a déjà payé cela cinq fois sur les dates.
     *
     * Le questionnaire enregistre le **libellé**, comme pour `modif.types` : les trois moteurs
     * de rendu écrivent ce que le clerc a lu. Un libellé inconnu — questionnaire antérieur au
     * champ, ou saisie héritée — retombe sur {@see ModeTraitement::defaut()}, qui reprend ce
     * que l'étude a toujours fait plutôt que d'inventer.
     */
    public function modeTraitement(): ModeTraitement
    {
        return ModeTraitement::depuisLibelle($this->get('mode_traitement'))
            ?? ModeTraitement::defaut();
    }
}
