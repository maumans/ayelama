<?php

namespace App\Models;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dossier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference', 'type_acte_id',
        'etape', 'redacteur_id', 'reviseur_id',
        'notaire_id', 'formaliste_id',
        'objet', 'valeur', 'echeance', 'urgent', 'notes',
        'etape_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'etape'            => EtapeDossier::class,
            'echeance'         => 'date',
            'etape_changed_at' => 'datetime',
            'valeur'           => 'integer',
            'urgent'           => 'boolean',
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

    /**
     * Rôles (valeurs `clientRole` de resources/js/data/questionnaires.js, stockées
     * telles quelles dans Partie::role) considérés comme "le client demandeur" de
     * l'office, par ordre de priorité décroissante — heuristique, pas un flag en
     * base : aucune notion de "partie principale" n'existe dans le schéma actuel.
     */
    private const ROLES_CLIENT_PRIORITAIRES = [
        'associe_unique', 'associe', 'gerant',
        'acheteur', 'debiteur', 'bailleur', 'liquidateur',
        'vendeur', 'locataire', 'creancier',
        'actionnaire', 'administrateur', 'membre',
    ];

    public function journal()
    {
        return $this->hasMany(JournalActivite::class)->orderByDesc('created_at');
    }

    public function factures()
    {
        return $this->hasMany(Facture::class);
    }

    public function courriers()
    {
        return $this->hasMany(Courrier::class)->orderByDesc('created_at');
    }

    public function societe()
    {
        return $this->hasOne(Societe::class);
    }

    public function bienImmobilier()
    {
        return $this->hasOne(BienImmobilier::class);
    }

    public function banque()
    {
        return $this->hasOne(Banque::class);
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

    public function scopeVisiblePar($query, User $user)
    {
        // Le Comptable n'est jamais assigné à un dossier (pas de champ comptable_id) —
        // sa mission (facturation/encaissements) est par nature transversale à tous les
        // dossiers, comme l'Administrateur.
        if ($user->hasRole(RoleUtilisateur::Administrateur) || $user->hasRole(RoleUtilisateur::Comptable)) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('redacteur_id', $user->id)
                ->orWhere('reviseur_id', $user->id)
                ->orWhere('notaire_id', $user->id)
                ->orWhere('formaliste_id', $user->id);
        });
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

    public function partiePrincipale(): ?Partie
    {
        $this->loadMissing('parties');

        foreach (self::ROLES_CLIENT_PRIORITAIRES as $role) {
            $partie = $this->parties->firstWhere('role', $role);
            if ($partie) {
                return $partie;
            }
        }

        return $this->parties->first();
    }

    public function clientPrincipalLabel(): ?string
    {
        $partie = $this->partiePrincipale();
        if (!$partie) {
            return null;
        }

        $this->loadMissing('parties.client');

        return $partie->client?->nomComplet() ?: $partie->nom;
    }
}
