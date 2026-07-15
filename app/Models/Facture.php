<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Facture extends Model
{
    protected $fillable = [
        'dossier_id',
        'note_numero',
        'note_date',
        'compte_numero',
        'objet',
        'assiette_chiffres',
        'total_chiffres',
    ];

    protected function casts(): array
    {
        return [
            'note_date'          => 'date',
            'assiette_chiffres'  => 'decimal:2',
            'total_chiffres'     => 'decimal:2',
        ];
    }

    // ── Relations ────────────────────────────────────────────────────────

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function lignes()
    {
        return $this->hasMany(LigneFacture::class);
    }

    public function paiements()
    {
        return $this->hasMany(Paiement::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function totalPaye(): float
    {
        // Utilise la relation déjà chargée si possible (évite le N+1 dans les listes),
        // sinon retombe sur une requête directe.
        return $this->relationLoaded('paiements')
            ? (float) $this->paiements->sum('montant')
            : (float) $this->paiements()->sum('montant');
    }

    public function soldeRestant(): float
    {
        return round((float) $this->total_chiffres - $this->totalPaye(), 2);
    }

    public function statutPaiement(): string
    {
        if ((float) $this->total_chiffres <= 0) return 'impaye';
        if ($this->soldeRestant() <= 0) return 'paye';
        return $this->totalPaye() > 0 ? 'partiel' : 'impaye';
    }

    /**
     * Sérialisation partagée entre DossierController::dossierDetailToArray()
     * (onglet Facturation d'un dossier) et FactureController::index() (liste
     * globale), pour éviter que les deux se désynchronisent sur les champs exposés.
     */
    public function versArray(): array
    {
        $this->loadMissing(['lignes', 'paiements.recu', 'paiements.enregistrePar', 'dossier.typeActe']);

        return [
            'id'                => $this->id,
            'note_numero'       => $this->note_numero,
            'note_date'         => $this->note_date?->format('d/m/Y'),
            'objet'             => $this->objet,
            'assiette_chiffres' => (float) ($this->assiette_chiffres ?? 0),
            'total_chiffres'    => (float) ($this->total_chiffres ?? 0),
            'totalPaye'         => $this->totalPaye(),
            'soldeRestant'      => $this->soldeRestant(),
            'statut'            => $this->statutPaiement(),
            'lignes'            => $this->lignes->map(fn ($l) => [
                'id'          => $l->id,
                'designation' => $l->designation,
                'quantite'    => $l->quantite,
                'montant'     => (float) $l->montant,
                'total'       => $l->total(),
            ]),
            'paiements' => $this->paiements->map(fn ($p) => [
                'id'             => $p->id,
                'date_paiement'  => $p->date_paiement?->format('d/m/Y'),
                'montant'        => (float) $p->montant,
                'moyen_paiement' => $p->moyen_paiement,
                'notes'          => $p->notes,
                'enregistrePar'  => $p->enregistrePar?->name,
                'recu'           => $p->recu ? [
                    'id'                 => $p->recu->id,
                    'numero'             => $p->recu->numero,
                    'date_emission'      => $p->recu->date_emission?->format('d/m/Y'),
                    'url_telechargement' => route('recus.telecharger', $p->recu),
                ] : null,
            ])->values(),
            'dossier' => [
                'reference'       => $this->dossier?->reference,
                'objet'           => $this->dossier?->objet,
                'typeActe'        => $this->dossier?->typeActe?->label,
                'clientPrincipal' => $this->dossier?->clientPrincipalLabel(),
            ],
        ];
    }

    /**
     * Recalcule le total à partir des lignes.
     */
    public function recalculerTotal(): self
    {
        $total = $this->lignes()->sum(\DB::raw('quantite * montant'));
        $this->update(['total_chiffres' => $total]);

        return $this;
    }

    /**
     * Génère un numéro de note automatique au format "N°XXX/MAB/AA"
     */
    public static function genererNumero(): string
    {
        $annee = date('y'); // "26"
        $last  = self::where('note_numero', 'like', "%/MAB/{$annee}")
            ->orderByDesc('id')
            ->value('note_numero');

        $seq = 1;
        if ($last && preg_match('/^(\d+)\//', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }

        return sprintf('%03d/MAB/%s', $seq, $annee);
    }
}
