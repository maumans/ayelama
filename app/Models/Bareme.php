<?php

namespace App\Models;

use App\Enums\TypeModificationStatutaire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Bareme extends Model
{
    protected $fillable = [
        'type_acte_id', 'organisme', 'libelle',
        'taux', 'montant_fixe', 'quantite_defaut', 'base_calcul',
        'condition_modification',
        'description', 'actif', 'ordre',
        'genere_formalite', 'depend_de_bareme_id', 'type_impot',
        'retour_attendu', 'delai_heures', 'pieces_requises',
    ];

    protected function casts(): array
    {
        return [
            'taux'             => 'decimal:4',
            'montant_fixe'     => 'decimal:2',
            'quantite_defaut'  => 'integer',
            'actif'            => 'boolean',
            'genere_formalite' => 'boolean',
            'pieces_requises'  => 'array',
        ];
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

        $condition = TypeModificationStatutaire::tryFrom($this->condition_modification);

        if (!$condition) {
            // Condition illisible (valeur saisie à la main, cas retiré de l'enum) : on
            // n'applique pas le barème. Le sens de `condition_modification` est de restreindre ;
            // en cas de doute, la restriction l'emporte — mieux vaut une ligne manquante,
            // visible et corrigeable, qu'un droit facturé à tort.
            return false;
        }

        $dossier->loadMissing('questionnaire');

        return in_array(
            $condition,
            TypeModificationStatutaire::depuisLibelles(
                $dossier->questionnaire?->donnees['modif.types']
                    ?? $dossier->questionnaire?->donnees['modif.type']
                    ?? null,
            ),
            true,
        );
    }

    public function calculerMontant(float $valeurActe): float
    {
        if ($this->base_calcul === 'montant_fixe') {
            return (float) ($this->montant_fixe ?? 0);
        }

        return round($valeurActe * ((float) $this->taux / 100), 2);
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
    public function organismeFormalite(): string
    {
        return match ($this->organisme) {
            'APIP'         => 'apip',
            'Impots'       => 'impots',
            'Conservation' => 'conservation_fonciere',
            'CNSS'         => 'cnss',
            default        => Str::slug($this->organisme, '_'),
        };
    }
}
