<?php

namespace App\Models;

use App\Enums\StatutFormalite;
use Illuminate\Database\Eloquent\Model;

class Formalite extends Model
{
    protected $fillable = [
        'dossier_id', 'bareme_id', 'depend_de_formalite_id', 'ordre',
        'organisme', 'libelle', 'statut',
        'taux', 'montant_base', 'montant_calcule', 'montant_paye', 'type_impot',
        'retour_attendu', 'delai_heures',
        'numero_recepisse', 'reference_document_recu',
        'depose_at', 'retour_at', 'echeance_at',
    ];

    protected function casts(): array
    {
        return [
            'statut'          => StatutFormalite::class,
            'taux'            => 'decimal:4',
            'montant_base'    => 'decimal:2',
            'montant_calcule' => 'decimal:2',
            'montant_paye'    => 'decimal:2',
            'depose_at'       => 'datetime',
            'retour_at'       => 'datetime',
            'echeance_at'     => 'datetime',
        ];
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function bareme()
    {
        return $this->belongsTo(Bareme::class);
    }

    public function dependDe()
    {
        return $this->belongsTo(Formalite::class, 'depend_de_formalite_id');
    }

    public function dependants()
    {
        return $this->hasMany(Formalite::class, 'depend_de_formalite_id');
    }

    public function pieces()
    {
        return $this->morphMany(DocumentFichier::class, 'documentable')->orderBy('id');
    }

    /**
     * La démarche est-elle achevée ? Délègue à StatutFormalite::estTerminee(), seul
     * endroit où l'état terminal est défini — voir cet enum pour l'historique de la
     * suppression du statut `Cloture`.
     */
    public function estTerminee(): bool
    {
        return (bool) $this->statut?->estTerminee();
    }

    public function estBloquee(): bool
    {
        if (!$this->relationLoaded('dependDe')) {
            $this->load('dependDe');
        }

        return $this->dependDe !== null && !$this->dependDe->estTerminee();
    }

    public function estUrgente(): bool
    {
        if (!$this->echeance_at) return false;
        if ($this->estTerminee()) return false;
        $dateStr = $this->echeance_at->toDateString();
        $today   = now()->toDateString();
        if ($dateStr < $today) return false;
        if ($dateStr === $today) return true;
        return now()->diffInHours($this->echeance_at->copy()->endOfDay(), false) <= 8;
    }

    public function estDepassee(): bool
    {
        return $this->echeance_at
            && $this->echeance_at->toDateString() < now()->toDateString()
            && !$this->estTerminee();
    }

    public function heuresRestantes(): ?int
    {
        if (!$this->echeance_at) return null;
        return max(0, (int) now()->diffInHours($this->echeance_at, false));
    }

    /**
     * Jours signés par rapport à l'échéance : positif = retard, négatif = jours
     * restants (ou marge, si la démarche a déjà un retour enregistré).
     */
    public function joursRetardOuAvance(): ?int
    {
        if (!$this->echeance_at) {
            return null;
        }

        $reference = $this->retour_at ?? now();

        return (int) $this->echeance_at->diffInDays($reference, false);
    }

    public function calculerMontant(): void
    {
        if ($this->taux && $this->montant_base) {
            $this->montant_calcule = round($this->montant_base * $this->taux, 2);
            $this->save();
        }
    }

    public function labelOrganisme(): string
    {
        return match($this->organisme) {
            'apip'                => 'APIP',
            'impots'              => 'Direction des Impôts',
            'conservation_fonciere' => 'Conservation foncière',
            'cnss'                => 'CNSS',
            'greffe'              => 'Greffe',
            default               => ucfirst($this->organisme),
        };
    }

    public function labelAffiche(): string
    {
        return $this->libelle ?: $this->labelOrganisme();
    }

    public function scopeUrgentes($query)
    {
        return $query->whereNotNull('echeance_at')
            ->where('echeance_at', '<=', now()->addHours(8))
            ->whereNotIn('statut', StatutFormalite::valeursTerminees());
    }

    /**
     * Démarches non achevées — celles qui bloquent le passage à l'expédition.
     */
    public function scopeNonTerminees($query)
    {
        return $query->whereNotIn('statut', StatutFormalite::valeursTerminees());
    }

    /**
     * Sérialisation partagée entre FormaliteController::index() (liste globale) et
     * DossierController::dossierDetailToArray() (onglet Formalités d'un dossier),
     * pour éviter que les deux se désynchronisent sur les champs exposés au front.
     */
    public function versArray(?User $user = null): array
    {
        $this->loadMissing(['pieces.versionActuelle', 'dependDe', 'dependants']);

        return [
            'id'                      => $this->id,
            'bareme_id'               => $this->bareme_id,
            'ordre'                   => $this->ordre,
            'organisme'               => $this->organisme,
            'organismeLabel'          => $this->labelOrganisme(),
            'libelle'                 => $this->labelAffiche(),
            'statut'                  => $this->statut?->value,
            'montant_base'            => (float) ($this->montant_base ?? 0),
            'montant_calcule'         => (float) ($this->montant_calcule ?? 0),
            'montant_paye'            => $this->montant_paye !== null ? (float) $this->montant_paye : null,
            'numero_recepisse'        => $this->numero_recepisse,
            'reference_document_recu' => $this->reference_document_recu,
            'delai_heures'            => $this->delai_heures,
            'echeance_at'             => $this->echeance_at?->toDateTimeString(),
            'depose_at'               => $this->depose_at?->format('d/m/Y'),
            'retour_at'               => $this->retour_at?->format('d/m/Y'),
            'estUrgente'              => $this->estUrgente(),
            'estDepassee'             => $this->estDepassee(),
            'estBloquee'              => $this->estBloquee(),
            'heuresRestantes'         => $this->heuresRestantes(),
            'joursRetardOuAvance'     => $this->joursRetardOuAvance(),
            'dependDeLabel'           => $this->dependDe?->labelAffiche(),
            'aDesDependants'          => $this->dependants->isNotEmpty(),
            'dependantsLabels'       => $this->dependants->map(fn ($f) => $f->labelAffiche())->all(),
            // Reflète exactement DossierPolicy::gererFormalites() (étape Formalites/Expedition
            // requise, même pour un administrateur) — sinon l'UI affiche des actions qui
            // se font ensuite rejeter par un 403 côté serveur.
            'peutGerer'               => $this->dossier && $user ? $user->can('gererFormalites', $this->dossier) : null,
            'dossier' => [
                'reference'       => $this->dossier?->reference,
                'objet'           => $this->dossier?->objet,
                'typeActe'        => $this->dossier?->typeActe?->label,
                'clientPrincipal' => $this->dossier?->clientPrincipalLabel(),
            ],
            'pieces' => $this->pieces->map(fn ($p) => [
                'id'             => $p->id,
                'label'          => $p->nom,
                'est_fourni'     => (bool) $p->est_fourni,
                'nom_original'   => $p->versionActuelle?->nom_original,
                'televerse_at'   => $p->versionActuelle?->created_at?->format('d/m/Y H:i'),
                'aUnFichier'     => (bool) $p->versionActuelle,
                // Nécessaires à DocumentInlinePreview/PreviewBody pour choisir le rendu
                // (PDF/DOCX/Excel) selon l'extension du fichier téléversé.
                'chemin_fichier' => $p->versionActuelle?->chemin_fichier,
                'version'        => $p->versionActuelle?->numero,
            ]),
        ];
    }
}
