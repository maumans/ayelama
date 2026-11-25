<?php

namespace App\Models;

use App\Enums\OrganismeFormalite;
use Illuminate\Database\Eloquent\Model;

class Bareme extends Model
{
    protected $fillable = [
        'type_acte_id', 'organisme', 'nature', 'libelle',
        'taux', 'montant_fixe', 'tranches', 'quantite_defaut', 'par_page', 'base_calcul',
        'condition_modification',
        'description', 'source', 'a_confirmer', 'actif', 'ordre',
        'genere_formalite', 'depend_de_bareme_id', 'type_impot',
        'remise_autorisee',
        'retour_attendu', 'delai_heures', 'pieces_requises', 'donnees_au_retour',
    ];

    protected function casts(): array
    {
        return [
            'taux'             => 'decimal:4',
            'montant_fixe'     => 'decimal:2',
            // Montant par tranche de l'assiette (le capital, pour une société) : [{a_partir_de, montant}].
            'tranches'         => 'array',
            'quantite_defaut'  => 'integer',
            'par_page'         => 'boolean',
            'nature'           => \App\Enums\NatureLigne::class,
            'actif'            => 'boolean',
            'a_confirmer'      => 'boolean',
            'genere_formalite' => 'boolean',
            // Les honoraires de l'étude se remisent, un débours non : elle verse au tiers
            // l'intégralité de ce qu'elle facture. Copié sur la ligne à la génération.
            'remise_autorisee' => 'boolean',
            'pieces_requises'  => 'array',
            // Ce que l'autorité **délivre** au retour, par opposition à `retour_attendu` qui
            // nomme le **papier**. Voir App\Enums\DonneeAuRetour.
            'donnees_au_retour' => 'array',
        ];
    }

    /**
     * Un barème créé sans nature la reçoit de son organisme : « Notaire » est l'étude elle-même,
     * tout autre organisme reçoit un débours. Ainsi les seeders et l'écran Barèmes n'ont rien à
     * savoir pour qu'aucun tarif ne naisse sans nature (2026-11-23).
     */
    protected static function booted(): void
    {
        static::saving(function (self $bareme) {
            // ⚠️ La migration `2026_11_23_100000` (débours) enregistre des barèmes **avant** celle
            // qui crée la colonne `nature` : en rejouant les migrations dans l'ordre, poser
            // l'attribut ferait échouer l'INSERT. On ne la pose que si la colonne existe.
            // Seul le « oui » est mémorisé : un « non » lu pendant les migrations deviendrait faux
            // dès la suivante, dans le même processus (`migrate --seed`).
            static $colonne = false;
            $colonne = $colonne || \Illuminate\Support\Facades\Schema::hasColumn('baremes', 'nature');

            if ($colonne && $bareme->nature === null) {
                $bareme->nature = \App\Enums\NatureLigne::pourOrganisme($bareme->organisme);
            }
        });
    }

    public function typeActe()
    {
        return $this->belongsTo(TypeActe::class);
    }

    public function dependDe()
    {
        return $this->belongsTo(Bareme::class, 'depend_de_bareme_id');
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function scopeGenereFormalite($query)
    {
        return $query->where('genere_formalite', true);
    }

    /**
     * Ce barème s'applique-t-il à ce dossier ?
     *
     * Un barème sans `condition_modification` s'applique toujours — c'est le cas de tous les
     * barèmes historiques, dont le comportement est donc inchangé. Sinon, il n'est retenu que
     * si le dossier porte effectivement le type de modification statutaire visé : facturer
     * une DNSV sur un transfert de siège, ou l'enregistrement de statuts mis à jour sur une
     * constitution, était le défaut que cette condition corrige.
     *
     * Prédicat **partagé** par {@see \App\Services\FacturationService} et
     * {@see \App\Services\FormaliteGenerationService} : le devbook signale déjà que ces deux
     * services ont des conventions de calcul distinctes (taux en pourcentage contre fraction),
     * on n'y ajoute pas une divergence de périmètre.
     */
    public function estApplicableA(Dossier $dossier): bool
    {
        if (blank($this->condition_modification)) {
            return true;
        }

        // ⚠️ **La condition se résout par le type d'acte, plus par un seul enum.** Elle passait
        // par `TypeModificationStatutaire::tryFrom()` en dur : une condition portant sur une
        // variante de **dissolution** (`dissolution`, `cloture_liquidation`) était donc jugée
        // illisible, et le barème écarté — sans message. Un tarif ou une formalité propre à la
        // dissolution ne pouvait pas exister, et rien ne disait pourquoi.
        //
        // `VariantesTypeActe::resoudre()` interroge l'enum du type d'acte du dossier, quel
        // qu'il soit. C'est déjà la résolution employée par `DocumentAttendu::reference()` :
        // les deux posaient la même question, une seule savait la poser.
        $condition = \App\Support\VariantesTypeActe::resoudre(
            $dossier->typeActe?->code,
            $this->condition_modification,
        );

        if (! $condition) {
            // Condition illisible (valeur saisie à la main, cas retiré de l'enum, ou condition
            // d'un autre type d'acte) : on n'applique pas le barème. Le sens de
            // `condition_modification` est de restreindre ; en cas de doute, la restriction
            // l'emporte — mieux vaut une ligne manquante, visible et corrigeable, qu'un droit
            // facturé à tort.
            return false;
        }

        return in_array(
            $condition,
            \App\Support\VariantesTypeActe::duDossier($dossier),
            true,
        );
    }

    public function calculerMontant(float $valeurActe): float
    {
        // Le débours des constitutions (2026-11-20) dépend du capital, par tranche : 10 M → 500 000,
        // 100 M → 1 000 000. La grille est portée par la ligne, pas par le code : c'est l'étude qui
        // la fixe, et elle n'en a donné que deux points.
        if (! empty($this->tranches)) {
            return $this->montantParTranche($valeurActe);
        }

        if ($this->base_calcul === 'montant_fixe') {
            return (float) ($this->montant_fixe ?? 0);
        }

        return round($valeurActe * ((float) $this->taux / 100), 2);
    }

    /**
     * Le montant de la tranche la plus haute que l'assiette atteint. Sous la première tranche (une
     * assiette inconnue, donc à 0), c'est la première qui s'applique : un débours n'est jamais nul.
     */
    public function montantParTranche(float $assiette): float
    {
        $tranches = collect($this->tranches)->sortBy('a_partir_de')->values();
        $retenue = $tranches->last(fn ($t) => $assiette >= (float) $t['a_partir_de']) ?? $tranches->first();

        return (float) ($retenue['montant'] ?? 0);
    }

    public function organismeLabel(): string
    {
        return match ($this->organisme) {
            'APIP'         => 'APIP',
            'Impots'       => 'Impôts',
            'Conservation' => 'Conservation foncière',
            'CNSS'         => 'CNSS',
            'Notaire'      => 'Honoraires notaire',
            'Greffe'       => 'Greffe',
            default        => $this->organisme,
        };
    }

    /**
     * Convertit l'organisme (nomenclature Bareme, PascalCase libre) vers la nomenclature
     * attendue par Formalite::organisme (snake_case) — utilisé uniquement par
     * FormaliteGenerationService au moment de générer la Formalite correspondante.
     */
    /**
     * La colonne `baremes.organisme` s'écrit en capitales initiales, `formalites.organisme` en
     * minuscules : {@see \App\Enums\OrganismeFormalite::libelleBareme()} porte les deux
     * écritures, et cette conversion n'a plus de table à elle.
     *
     * ⚠️ Le repli `Str::slug()` est supprimé. Il transformait n'importe quel libellé en un
     * organisme que ni la validation de `FormaliteController` ni les onglets de l'écran
     * Formalités ne connaissaient : une formalité posée sous « Autre » existait en base sans
     * être affichable nulle part. Un organisme inconnu retombe désormais sur `autre`, qui est
     * un cas déclaré, validé et affiché.
     */
    public function organismeFormalite(): string
    {
        return OrganismeFormalite::depuisLibelleBareme($this->organisme)->value;
    }
}
