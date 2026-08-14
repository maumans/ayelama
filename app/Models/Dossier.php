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
        'reference', 'type_acte_id', 'societe_id',
        'etape', 'redacteur_id', 'reviseur_id',
        'notaire_id', 'formaliste_id',
        'objet', 'valeur', 'echeance', 'urgent', 'notes',
        'etape_changed_at',
        'date_signature_client', 'date_signature_notaire',
    ];

    protected function casts(): array
    {
        return [
            'etape'            => EtapeDossier::class,
            'echeance'         => 'date',
            'etape_changed_at' => 'datetime',
            'valeur'           => 'integer',
            'urgent'           => 'boolean',
            'date_signature_client'  => 'date',
            'date_signature_notaire' => 'date',
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

    /**
     * Tous les utilisateurs ayant un rôle assigné sur ce dossier (rédacteur,
     * réviseur, notaire, formaliste), dédupliqués — les destinataires légitimes
     * d'une notification transversale au dossier (échéance, formalité urgente).
     *
     * Les comptes désactivés sont exclus : ils ne doivent plus recevoir de mail
     * même s'ils restent assignés à d'anciens dossiers. Pour une notification
     * ciblée sur un rôle précis, passer par NotificationService::destinataires().
     */
    public function ayantsDroit(): \Illuminate\Support\Collection
    {
        $this->loadMissing(['redacteur', 'reviseur', 'notaire', 'formaliste']);

        return collect([$this->redacteur, $this->reviseur, $this->notaire, $this->formaliste])
            ->filter()
            ->filter(fn (User $u) => $u->actif)
            ->unique('id')
            ->values();
    }

    /**
     * Solde restant sur l'ensemble des factures du dossier.
     *
     * Prérequis de clôture depuis le 2026-08-05 : un dossier clos est un dossier soldé —
     * c'est le moment où l'office perd son levier de recouvrement. Aucun contrôle
     * n'existait, et un dossier avait été clôturé avec plus de 5 000 000 GNF impayés.
     */
    public function soldeRestant(): float
    {
        $this->loadMissing('factures.paiements');

        return round($this->factures->sum(fn (Facture $f) => $f->soldeRestant()), 2);
    }

    public function estSolde(): bool
    {
        // Tolérance au centime : les montants sont saisis en GNF entiers, mais
        // soldeRestant() passe par des flottants.
        return $this->soldeRestant() <= 0.01;
    }

    public function questionnaire()
    {
        return $this->hasOne(Questionnaire::class);
    }

    public function documents()
    {
        return $this->morphMany(DocumentFichier::class, 'documentable')->orderBy('id');
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
        // Modification statutaire : celui qui acquiert ou entre en fonction avant celui qui
        // sort, par cohérence avec `acheteur` avant `vendeur` ci-dessus. Sans ces rôles, un
        // dossier de modification s'affichait sans nom de client dans la liste.
        'cessionnaire', 'gerant_entrant', 'cedant', 'souscripteur',
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

    /**
     * Société sur laquelle porte ce dossier.
     *
     * Renseignée dans les trois procédures de la catégorie Société : la fiche est créée à
     * l'issue d'une constitution, et **choisie dans le registre** à l'entrée d'une
     * modification ou d'une dissolution.
     *
     * ⚠️ Ne pas confondre avec {@see societeConstituee()}, qui suit la relation inverse
     * (`societes.dossier_id`) et ne vaut que pour le dossier qui a *créé* la société.
     */
    public function societe()
    {
        return $this->belongsTo(Societe::class);
    }

    /**
     * Pièce écrite qui atteste l'accord du client et conditionne la sortie de l'Initialisation.
     *
     * Elle **dépend du type de dossier**. Pour une constitution, l'étude imprime la fiche du
     * dossier, la fait signer et la téléverse. Pour une **modification de statuts**, cela n'a pas
     * de sens : ce qui engage l'opération est la **décision des associés**, que le client apporte.
     * Le procès-verbal que l'étude rédige, lui, n'arrive qu'à l'Édition — trop tard pour garder
     * l'Initialisation.
     *
     * ⚠️ La `categorie` reste `accord_client` dans les deux cas, délibérément : c'est le *créneau
     * technique* de cette pièce. En introduire une seconde obligerait à la classer dans
     * {@see \App\Enums\RubriqueCloture::pourDocument()} (elle tomberait sinon dans « Actes », alors
     * qu'elle est fournie et non produite), à l'exclure de l'onglet Actes et à doubler le contrôle
     * bloquant. C'est le **nom** du document qui porte le sens, et il est déjà propre à chaque
     * dossier.
     *
     * @return array{categorie: string, nom: string, titre: string, instructions: string, imprimable: bool}
     */
    public function pieceAccordAttendue(): array
    {
        $this->loadMissing('typeActe');

        if ($this->typeActe?->code === 'SOC-MOD') {
            return [
                'categorie'    => 'accord_client',
                'nom'          => "Décision d'assemblée des associés",
                'titre'        => "Décision d'assemblée des associés",
                'instructions' => "Téléversez la décision écrite des associés qui engage cette modification — convocation, projet de résolution, ou procès-verbal remis par le client. Le dossier ne pourra pas passer en certification sans elle.",
                'imprimable'   => false,
            ];
        }

        return [
            'categorie'    => 'accord_client',
            'nom'          => 'Accord client — questionnaire signé',
            'titre'        => 'Accord client sur le questionnaire',
            'instructions' => "Imprimez la fiche dossier, faites-la signer par le client, puis téléversez ici le document signé. Le dossier ne pourra pas passer en certification sans cet accord.",
            'imprimable'   => true,
        ];
    }

    /**
     * Société dont ce dossier est l'acte de constitution — `null` pour un dossier de
     * modification ou de dissolution, qui porte sur une société préexistante.
     *
     * C'est ce lien qui permet à une fiche de retrouver les associés et gérants d'origine
     * (voir {@see Societe::associesConnus()}).
     */
    public function societeConstituee()
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
        // Comparaison sur le cas d'enum et non sur sa valeur littérale : renommer ou
        // retirer un cas de StatutRevision casse alors la compilation au lieu de
        // rendre cette condition silencieusement toujours fausse.
        return $this->revision?->statut === \App\Enums\StatutRevision::Valide;
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
