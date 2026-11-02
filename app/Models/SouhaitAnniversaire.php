<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un souhait d'anniversaire adressé à un client, pour une occurrence donnée.
 *
 * ⚠️ **L'année est celle de l'anniversaire fêté, pas celle du geste.** Souhaiter le
 * 31 décembre pour un anniversaire du 2 janvier coche l'année suivante. `souhaite_at` porte
 * le moment réel ; `annee` porte l'occurrence. Les confondre ferait réapparaître le client
 * dans la liste le lendemain.
 *
 * L'application n'envoie rien : elle enregistre ce que l'étude déclare avoir fait
 * ({@see \App\Http\Controllers\AnniversaireController}).
 */
class SouhaitAnniversaire extends Model
{
    protected $table = 'souhaits_anniversaire';

    protected $fillable = ['client_id', 'annee', 'souhaite_at', 'user_id', 'canal', 'note'];

    protected function casts(): array
    {
        return [
            'annee'       => 'integer',
            'souhaite_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Qui a souhaité — `null` si le collaborateur a depuis quitté l'étude. */
    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Libellé du canal déclaré, pour l'affichage.
     *
     * `match` sans branche par défaut volontaire sur les valeurs connues, mais un repli
     * explicite : `canal` est une chaîne libre en base (la liste n'est pas arrêtée), donc une
     * valeur inattendue doit s'afficher telle quelle plutôt que faire échouer la page.
     */
    public function canalLabel(): ?string
    {
        return match ($this->canal) {
            null, ''        => null,
            'email'         => 'par email',
            'telephone'     => 'par téléphone',
            'whatsapp'      => 'par WhatsApp',
            'de_vive_voix'  => 'de vive voix',
            default         => $this->canal,
        };
    }
}
