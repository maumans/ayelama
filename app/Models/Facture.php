<?php

namespace App\Models;

use App\Enums\NatureFacture;
use App\Enums\NatureLigne;
use App\Enums\StatutFacture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Facture extends Model
{
    protected $fillable = [
        'dossier_id',
        'statut',
        // Principale ou complémentaire (droits d'enregistrement connus au retour, 2026-11-23).
        'nature',
        'facture_origine_id',
        'formalite_id',
        'emise_at',
        'note_numero',
        'note_date',
        'expediteur',
        'compte_numero',
        // Banque choisie et sa copie : la facture garde ce qu'elle a imprimé (2026-11-20).
        'compte_bancaire_id',
        'banque',
        // Pages des statuts et de la DNSV, sans pages de garde : quantité des timbres et des rôles.
        'nb_pages',
        'objet',
        'assiette_chiffres',
        'total_chiffres',
    ];

    protected function casts(): array
    {
        return [
            'note_date'          => 'date',
            'statut'             => StatutFacture::class,
            'nature'             => NatureFacture::class,
            'emise_at'           => 'datetime',
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

    public function compteBancaire()
    {
        return $this->belongsTo(CompteBancaire::class);
    }

    /** La facture principale qu'une complémentaire complète. */
    public function origine()
    {
        return $this->belongsTo(self::class, 'facture_origine_id');
    }

    /** Le retour de formalité qui a établi une complémentaire. */
    public function formalite()
    {
        return $this->belongsTo(Formalite::class);
    }

    /**
     * La facture calculée sur les barèmes — la seule que `FacturationService::genererFacture()`
     * a le droit de supprimer et de recréer. Une complémentaire n'est jamais régénérée.
     *
     * `nature` nulle comprise : défaut de colonne, mais la prudence ne coûte rien ici.
     */
    public function scopePrincipales($query)
    {
        return $query->where(fn ($q) => $q->where('nature', NatureFacture::Principale)->orWhereNull('nature'));
    }

    public function estComplementaire(): bool
    {
        return $this->nature === NatureFacture::Complementaire;
    }

    /**
     * Choisit la banque sur laquelle le client réglera : le numéro suit la banque. La facture en
     * garde une **copie** — corriger plus tard un numéro de compte ne réécrit pas une facture
     * remise. `null` retire le choix.
     */
    public function choisirCompte(?CompteBancaire $compte): void
    {
        $this->forceFill([
            'compte_bancaire_id' => $compte?->id,
            'banque'             => $compte?->banque,
            'compte_numero'      => $compte?->numero,
        ]);
    }

    /**
     * Total net des lignes d'une nature — remises déduites. Une ligne sans nature (antérieure à la
     * migration du 2026-11-23, qui les a toutes classées) compterait comme débours : c'est la
     * lecture prudente, l'étude ne s'attribuant pas un argent qu'elle reverse peut-être.
     */
    public function totalParNature(NatureLigne $nature): float
    {
        return round($this->lignes->filter(fn (LigneFacture $l) => ($l->nature ?? NatureLigne::Debours) === $nature)
            ->sum(fn (LigneFacture $l) => $l->total()), 2);
    }

    /** La facture porte-t-elle des lignes comptées à la page (timbres fiscaux, rôles) ? */
    public function comptePages(): bool
    {
        // Lu sur les lignes déjà chargées quand elles le sont : `versArray()` sert aussi la liste
        // de toutes les factures, et une requête par facture y ferait un N+1.
        if ($this->relationLoaded('lignes')) {
            $parPage = Bareme::where('par_page', true)->pluck('id')->all();

            return $this->lignes->contains(fn (LigneFacture $l) => in_array($l->bareme_id, $parPage, true));
        }

        return $this->lignes()->whereHas('bareme', fn ($q) => $q->where('par_page', true))->exists();
    }

    /**
     * Pose le nombre de pages et l'applique aux lignes comptées à la page.
     *
     * Règle de l'étude (2026-11-20) : pages des **statuts et de la DNSV**, sans les pages de
     * garde — × 2 000 GNF de timbres et × 5 000 GNF de rôles. Une seule donnée pour les deux
     * lignes, qu'on tapait jusque-là deux fois.
     */
    public function definirPages(?int $pages): self
    {
        DB::transaction(function () use ($pages) {
            $this->update(['nb_pages' => $pages]);

            $this->lignes()
                ->whereHas('bareme', fn ($q) => $q->where('par_page', true))
                ->update(['quantite' => $pages ?? 0]);

            $this->recalculerTotal();
        });

        return $this;
    }

    /** « 011406700100 ORABANK GUINEE SA » — la forme des notes de l'étude. */
    public function compteImprime(): ?string
    {
        $texte = trim(($this->compte_numero ?? '') . ' ' . ($this->banque ?? ''));

        return $texte !== '' ? $texte : null;
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

    /**
     * Somme des paiements relue en base, en ignorant éventuellement un paiement.
     *
     * Volontairement distincte de totalPaye() : cette dernière peut servir une
     * relation déjà chargée (donc potentiellement obsolète), ce qui est très bien
     * pour de l'affichage mais inacceptable pour contrôler l'invariant « les
     * paiements ne dépassent pas le total facturé ».
     */
    public function totalPayeEnBase(?int $saufPaiementId = null): float
    {
        return round((float) $this->paiements()
            ->when($saufPaiementId, fn ($q) => $q->whereKeyNot($saufPaiementId))
            ->sum('montant'), 2);
    }

    /**
     * Montant maximum qu'un paiement peut encore porter sans dépasser le total
     * facturé. Passer $saufPaiementId lors d'une modification : le paiement en
     * cours d'édition libère son propre montant.
     */
    public function soldeDisponible(?int $saufPaiementId = null): float
    {
        return round((float) $this->total_chiffres - $this->totalPayeEnBase($saufPaiementId), 2);
    }

    public function estProforma(): bool
    {
        return $this->statut === StatutFacture::Proforma;
    }

    /**
     * Émettre la facture : lui attribuer son numéro, la dater du jour, nommer qui l'expédie
     * (retours R20 et R11, 2026-11-16).
     *
     * ⚠️ Sous verrou, dans une transaction : deux émissions simultanées liraient sinon la même
     * séquence. L'index unique sur `note_numero` est le second filet — une course perdue échoue
     * bruyamment au lieu de dupliquer un numéro de facture.
     */
    public function emettre(?string $expediteur = null): self
    {
        if (! $this->estProforma()) {
            throw ValidationException::withMessages(['facture' => ['Cette facture est déjà émise.']]);
        }
        if ((float) $this->total_chiffres <= 0) {
            throw ValidationException::withMessages([
                'facture' => ["Une facture sans montant ne s'émet pas : ajoutez d'abord une ligne."],
            ]);
        }
        // Timbres et rôles à 0 page partiraient à 0 GNF chez le client, sans que rien ne le dise.
        if ($this->comptePages() && ! ($this->nb_pages > 0)) {
            throw ValidationException::withMessages([
                'facture' => ["Indiquez le nombre de pages dans l'en-tête de la facture (pour une constitution : "
                    . 'statuts et DNSV, sans les pages de garde) — les timbres fiscaux et les rôles en dépendent.'],
            ]);
        }

        DB::transaction(function () use ($expediteur) {
            $facture = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
            if (! $facture->estProforma()) {
                throw ValidationException::withMessages(['facture' => ['Cette facture vient d\'être émise par quelqu\'un d\'autre.']]);
            }

            $facture->update([
                'statut'      => StatutFacture::Emise,
                'emise_at'    => now(),
                'note_numero' => self::genererNumero(verrouiller: true),
                'note_date'   => now(),
                'expediteur'  => filled($facture->expediteur) ? $facture->expediteur : $expediteur,
            ]);

            $this->setRawAttributes($facture->getAttributes(), true);
        });

        return $this;
    }

    public function peutRecevoirPaiement(): bool
    {
        // Une proforma n'encaisse rien : on paie une facture émise (question Q5 du suivi,
        // règle provisoire — la sortie d'Expédition exige de toute façon une facture soldée).
        if ($this->estProforma()) return false;

        // Une facture à 0 (aucun barème applicable) n'a rien à encaisser.
        return (float) $this->total_chiffres > 0 && $this->soldeRestant() > 0;
    }

    /**
     * Détecte les factures encaissées au-delà du total — impossible depuis
     * l'invariant posé le 2026-08-03, mais des données antérieures peuvent
     * exister : on les signale plutôt que de les corriger silencieusement (ce
     * sont des écritures financières).
     */
    public function estTropPercue(): bool
    {
        return $this->soldeRestant() < 0;
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
        $this->loadMissing(['lignes.bareme', 'paiements.recu', 'paiements.enregistrePar', 'dossier.typeActe']);

        return [
            'id'                => $this->id,
            'statutFacture'     => $this->statut?->value,
            'statutFactureLabel' => $this->statut?->label(),
            'estProforma'       => $this->estProforma(),
            'emise_at'          => $this->emise_at?->format('d/m/Y'),
            'note_numero'       => $this->note_numero,
            'note_date'         => $this->note_date?->format('d/m/Y'),
            // ISO pour le champ date de la modale d'en-tête (R11).
            'note_date_iso'     => $this->note_date?->toDateString(),
            'expediteur'        => $this->expediteur,
            'objet'             => $this->objet,
            'nature'            => $this->nature?->value ?? NatureFacture::Principale->value,
            'natureLabel'       => ($this->nature ?? NatureFacture::Principale)->label(),
            'estComplementaire' => $this->estComplementaire(),
            // Le numéro de la principale complétée, pour que l'écran et le PDF disent laquelle.
            'origineNumero'     => $this->facture_origine_id ? $this->origine?->note_numero : null,
            'compte_bancaire_id' => $this->compte_bancaire_id,
            'banque'            => $this->banque,
            'compte_numero'     => $this->compte_numero,
            'nb_pages'          => $this->nb_pages,
            'comptePages'       => $this->comptePages(),
            'assiette_chiffres' => (float) ($this->assiette_chiffres ?? 0),
            'total_chiffres'    => (float) ($this->total_chiffres ?? 0),
            'totalPaye'         => $this->totalPaye(),
            'soldeRestant'      => $this->soldeRestant(),
            'statut'            => $this->statutPaiement(),
            // Le front s'appuie dessus pour plafonner la saisie et désactiver le
            // bouton d'encaissement — même règle que le contrôle serveur.
            'peutRecevoirPaiement' => $this->peutRecevoirPaiement(),
            'estTropPercue'        => $this->estTropPercue(),
            'totalBrut'         => $this->totalBrut(),
            'totalRemises'      => $this->totalRemises(),
            // Ce qui revient à l'étude, et ce qui passe aux organismes (comptabilité des dossiers).
            'totalHonoraires'   => $this->totalParNature(NatureLigne::Honoraire),
            'totalDebours'      => $this->totalParNature(NatureLigne::Debours),
            'lignes'            => $this->lignes->map(fn (LigneFacture $l) => [
                'id'          => $l->id,
                'designation' => $l->designation,
                'nature'      => $l->nature?->value,
                'natureLabel' => $l->nature?->label(),
                'quantite'    => $l->quantite,
                'montant'     => (float) $l->montant,
                'montantBrut' => $l->montantBrut(),
                'total'       => $l->total(),
                // La remise sous ses deux formes : celle qui a été saisie, et l'autre —
                // l'écran les lie, l'utilisateur en saisit une et voit la seconde.
                'remiseType'        => $l->remise_type?->value,
                'remiseValeur'      => $l->remise_valeur !== null ? (float) $l->remise_valeur : null,
                'remiseMontant'     => $l->remiseMontant(),
                'remisePourcentage' => $l->remisePourcentage(),
                'remiseAutorisee'   => $l->remiseAutorisee(),
                'remiseDepasse'     => $l->remiseDepasseLaLigne(),
                // Le montant vient-il d'une note de frais de l'étude, ou de nulle part ?
                // Porté jusqu'ici parce que c'est **sur la note** que la question se pose :
                // la relire à l'écran d'administration suppose d'y penser, la voir sur la
                // ligne qu'on s'apprête à envoyer ne le suppose pas.
                //
                // Une ligne saisie à la main n'a pas de barème : elle n'est pas « à
                // confirmer » pour autant — quelqu'un l'a voulue.
                'montantAConfirmer' => (bool) $l->bareme?->a_confirmer,
            ]),
            'paiements' => $this->paiements->map(fn ($p) => [
                'id'             => $p->id,
                'date_paiement'  => $p->date_paiement?->format('d/m/Y'),
                'montant'        => (float) $p->montant,
                'moyen_paiement' => $p->moyen_paiement,
                // « Espèces », et non « especes » brut à l'écran.
                'moyenLabel'     => \App\Enums\MoyenPaiement::tryFrom((string) $p->moyen_paiement)?->label(),
                'notes'          => $p->notes,
                'enregistrePar'  => $p->enregistrePar?->name,
                'recu'           => $p->recu ? [
                    'id'                 => $p->recu->id,
                    'numero'             => $p->recu->numero,
                    'date_emission'      => $p->recu->date_emission?->format('d/m/Y'),
                    'url_telechargement' => route('recus.telecharger', $p->recu),
                    'url_apercu'         => route('recus.apercu', $p->recu),
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

    /** Somme des lignes **avant** remise. */
    public function totalBrut(): float
    {
        $this->loadMissing('lignes');

        return round($this->lignes->sum(fn (LigneFacture $l) => $l->montantBrut()), 2);
    }

    /** Total des remises accordées, toutes lignes confondues. */
    public function totalRemises(): float
    {
        $this->loadMissing('lignes');

        return round($this->lignes->sum(fn (LigneFacture $l) => $l->remiseMontant()), 2);
    }

    /**
     * Recalcule le total à partir des lignes, **remises déduites**.
     *
     * ⚠️ Calculé en PHP et non plus par `SUM(quantite * montant)` en SQL : une remise peut être
     * un montant ou un pourcentage, avec un plafonnement au brut, et reproduire cette logique
     * en expression SQL l'aurait dupliquée — deux calculs du même total finissent par diverger,
     * et celui-ci décide de ce que le client doit. Une facture compte sept lignes en moyenne :
     * le coût est nul.
     */
    public function recalculerTotal(): self
    {
        $this->load('lignes');

        $this->update(['total_chiffres' => round($this->lignes->sum(fn (LigneFacture $l) => $l->total()), 2)]);

        return $this;
    }

    /**
     * Premier numéro que l'application attribue, par année (« AA »), quand elle prend la suite d'un
     * registre tenu ailleurs.
     *
     * L'étude numérotait ses notes de frais dans des classeurs Excel : la dernière connue est la
     * **325/MAB/2026** (KOUMI INVESTMENTS LTD, 19 novembre 2026, `docs/ACTES/FACTURE.xls`). Décidé le
     * 2026-11-20 : l'application devient le **registre unique** et reprend à 326. Les 25 factures
     * qu'elle avait numérotées 001 à 025 étaient des **essais** : elles portent désormais
     * « TEST-NNN/MAB/26 », hors séquence, pour ne pas doublonner le registre papier.
     *
     * Une seule année concernée : en 2027 la séquence repart à 1, sans entrée ici.
     *
     * @var array<string, int>
     */
    public const REPRISE_REGISTRE = ['26' => 326];

    /**
     * Prochain numéro « NNN/MAB/AA » de l'année.
     *
     * ⚠️ **La séquence la plus haute, et non la dernière ligne par id** (corrigé le 2026-11-16).
     * Depuis la proforma, les factures sont émises dans un autre ordre que celui de leur création :
     * la dernière par id peut porter un numéro plus petit qu'une autre, et l'ancienne lecture aurait
     * redonné un numéro déjà attribué.
     */
    public static function genererNumero(bool $verrouiller = false): string
    {
        $annee = date('y'); // "26"

        $numeros = self::where('note_numero', 'like', "%/MAB/{$annee}")
            ->when($verrouiller, fn ($q) => $q->lockForUpdate())
            ->pluck('note_numero');

        // `^(\d+)\/` : un numéro hors séquence (« TEST-001/MAB/26 ») ne compte pas.
        $seq = 1 + (int) $numeros
            ->map(fn (string $n) => preg_match('/^(\d+)\//', $n, $m) ? (int) $m[1] : 0)
            ->max();

        // Reprise du registre papier : jamais en dessous du premier numéro réservé à l'application.
        $seq = max($seq, self::REPRISE_REGISTRE[$annee] ?? 1);

        return sprintf('%03d/MAB/%s', $seq, $annee);
    }
}
