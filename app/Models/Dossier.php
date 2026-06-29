<?php

namespace App\Models;

use App\Enums\EtapeDossier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dossier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 'type_acte_id',
        'etape', 'redacteur_id', 'reviseur_id',
        'notaire_id', 'formaliste_id',
        'objet', 'valeur', 'echeance', 'notes',
        'etape_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'etape'            => EtapeDossier::class,
            'echeance'         => 'date',
            'etape_changed_at' => 'datetime',
            'valeur'           => 'integer',
        ];
    }

    // Relations
    public function typeActe()
    {
        return $this->belongsTo(TypeActe::class);
    }

    public function redacteur()
    {
        return $this->belongsTo(User::class, 'redacteur_id');
    }

    public function reviseur()
    {
        return $this->belongsTo(User::class, 'reviseur_id');
    }

    public function notaire()
    {
        return $this->belongsTo(User::class, 'notaire_id');
    }

    public function formaliste()
    {
        return $this->belongsTo(User::class, 'formaliste_id');
    }

    public function questionnaire()
    {
        return $this->hasOne(Questionnaire::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class)->orderBy('id');
    }

    public function revision()
    {
        return $this->hasOne(Revision::class);
    }

    public function formalites()
    {
        return $this->hasMany(Formalite::class)->orderBy('organisme');
    }

    public function parties()
    {
        return $this->hasMany(Partie::class)->orderBy('role');
    }

    public function journal()
    {
        return $this->hasMany(JournalActivite::class)->orderByDesc('created_at');
    }

    // Scopes
    public function scopeEnCours($query)
    {
        return $query->whereNotIn('etape', [EtapeDossier::Cloture->value]);
    }

    public function scopeEnRevision($query)
    {
        return $query->where('etape', EtapeDossier::Revision->value);
    }

    public function scopeEcheanceUrgente($query, int $heures = 72)
    {
        return $query->whereNotNull('echeance')
            ->where('echeance', '<=', now()->addHours($heures))
            ->whereNotIn('etape', [EtapeDossier::Cloture->value]);
    }

    // Helpers
    public function etapeOrdre(): int
    {
        return $this->etape?->ordre() ?? 0;
    }

    public function peutAvancer(): bool
    {
        return $this->etape !== EtapeDossier::Cloture;
    }

    public function revisionValidee(): bool
    {
        return $this->revision?->statut?->value === 'valide';
    }

    public function estEnRetard(): bool
    {
        return $this->echeance
            && $this->echeance->toDateString() < now()->toDateString()
            && $this->etape !== EtapeDossier::Cloture;
    }
}
