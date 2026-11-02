<?php

namespace App\Models;

use App\Contracts\OperationDeSociete;
use App\Enums\TypeAssemblee;
use App\Enums\TypeModificationStatutaire;
use App\Enums\VarianteDissolution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Entrée du catalogue d'opérations sur les sociétés — addendum v1.2 § 5.
 *
 * Représente la règle en vigueur pour une opération (changement de siège, cession,
 * dissolution...), modifiable sans redéploiement (test T13 de la spécification).
 *
 * @property int $id
 * @property string $code
 * @property string $type_acte_code
 * @property string|null $variante
 * @property string $libelle
 * @property TypeAssemblee $assemblee
 * @property bool $impacte_statuts
 * @property bool $impacte_rccm
 * @property bool $publication_jal
 * @property bool $exclusive
 * @property bool $regle_validee
 * @property string|null $description_statut
 * @property bool $est_actif
 * @property int $ordre
 */
class OperationSociete extends Model
{
    protected $table = 'operations_societe';

    protected $guarded = ['id'];

    protected $casts = [
        'assemblee'       => TypeAssemblee::class,
        'impacte_statuts' => 'boolean',
        'impacte_rccm'    => 'boolean',
        'publication_jal' => 'boolean',
        'exclusive'       => 'boolean',
        'regle_validee'   => 'boolean',
        'est_actif'       => 'boolean',
        'ordre'           => 'integer',
    ];

    /** Opérations actives affichables dans les assistants et formulaires. */
    public function scopeActives(Builder $query): Builder
    {
        return $query->where('est_actif', true)->orderBy('ordre');
    }

    /** Opérations associées à un type d'acte (ex: 'SOC-MOD', 'SOC-DIS'). */
    public function scopePourTypeActe(Builder $query, string $typeActeCode): Builder
    {
        return $query->where('type_acte_code', $typeActeCode)->orderBy('ordre');
    }

    /**
     * Retrouve l'opération de référence (enum compilé) correspondant à ce code.
     */
    public function referenceEnum(): ?OperationDeSociete
    {
        foreach (TypeModificationStatutaire::cases() as $case) {
            if ($case->codeOperation() === $this->code) {
                return $case;
            }
        }

        foreach (VarianteDissolution::cases() as $case) {
            if ($case->codeOperation() === $this->code) {
                return $case;
            }
        }

        return null;
    }

    /**
     * Vérifie si la configuration en base s'écarte de la référence officielle de la spec.
     */
    public function divergeDeLaReference(?OperationDeSociete $ref = null): bool
    {
        $ref ??= $this->referenceEnum();

        if ($ref === null) {
            return false;
        }

        return $this->assemblee !== $ref->assemblee()
            || $this->impacte_statuts !== $ref->impacteStatuts()
            || $this->impacte_rccm !== $ref->impacteRccm()
            || $this->publication_jal !== $ref->publicationJal()
            || $this->exclusive !== $ref->exclusive()
            || $this->regle_validee !== $ref->regleValidee();
    }

    /**
     * Détaille les champs qui s'écartent de la référence légale, pour affichage d'audit.
     *
     * @return array<string, array{actuel: mixed, reference: mixed}>
     */
    public function differencesAvecLaReference(?OperationDeSociete $ref = null): array
    {
        $ref ??= $this->referenceEnum();

        if ($ref === null) {
            return [];
        }

        $differences = [];

        if ($this->assemblee !== $ref->assemblee()) {
            $differences['assemblee'] = [
                'actuel'    => $this->assemblee->value,
                'reference' => $ref->assemblee()->value,
            ];
        }

        if ($this->impacte_statuts !== $ref->impacteStatuts()) {
            $differences['impacte_statuts'] = [
                'actuel'    => $this->impacte_statuts,
                'reference' => $ref->impacteStatuts(),
            ];
        }

        if ($this->impacte_rccm !== $ref->impacteRccm()) {
            $differences['impacte_rccm'] = [
                'actuel'    => $this->impacte_rccm,
                'reference' => $ref->impacteRccm(),
            ];
        }

        if ($this->publication_jal !== $ref->publicationJal()) {
            $differences['publication_jal'] = [
                'actuel'    => $this->publication_jal,
                'reference' => $ref->publicationJal(),
            ];
        }

        if ($this->exclusive !== $ref->exclusive()) {
            $differences['exclusive'] = [
                'actuel'    => $this->exclusive,
                'reference' => $ref->exclusive(),
            ];
        }

        if ($this->regle_validee !== $ref->regleValidee()) {
            $differences['regle_validee'] = [
                'actuel'    => $this->regle_validee,
                'reference' => $ref->regleValidee(),
            ];
        }

        return $differences;
    }
}

