<?php

namespace App\Enums;

/**
 * Qui rédige l'acte : l'étude, ou le client qui apporte le sien.
 *
 * Addendum v1.2 § 5.2. Deux dossiers portant la même opération ne produisent pas les mêmes
 * actes selon ce choix : en rédaction, l'étude établit le procès-verbal, les statuts mis à jour
 * et l'acte de cession ; en dépôt au rang des minutes, elle reçoit le procès-verbal sous seing
 * privé du client et n'établit qu'un **acte de dépôt**. Les formalités, elles, sont les mêmes —
 * c'est l'acte authentique qui change de nature, pas la vie de la société.
 *
 * ⚠️ **Enregistré au questionnaire, pas encore consommé.** Ce lot pose le choix et le met sous
 * les yeux du clerc ; le § 5.2 (gabarit `ACTE_DEPOT`, suppression de `STATUTS_MAJ`) et le § 5.3
 * (pièces `PV_CLIENT` / `STATUTS_CLIENT`) attendent le lot 5, faute de modèles Word numérisés —
 * ceux de dissolution et de liquidation ont été remis sur papier. Le champ porte la mention
 * dans sa `note` : un choix qui n'a pas encore d'effet doit le dire, sinon il ment.
 *
 * Le pendant JS est le `select` `mode_traitement` de `resources/js/data/questionnaires.js`,
 * verrouillé par {@see \Tests\Feature\ParitesVariantesSocieteTest}.
 */
enum ModeTraitement: string
{
    case Redaction          = 'REDACTION_ETUDE';
    case DepotRangDesMinutes = 'DEPOT_RANG_MINUTES';

    public function label(): string
    {
        return match ($this) {
            self::Redaction           => "Rédaction par l'étude",
            self::DepotRangDesMinutes => 'Dépôt au rang des minutes',
        };
    }

    /**
     * Ce que le mode change, en une phrase — affiché sous le sélecteur.
     *
     * Formulé du point de vue du clerc, qui décide d'après ce que le client apporte, et non
     * d'après un code de la spécification.
     */
    public function explication(): string
    {
        return match ($this) {
            self::Redaction => "L'étude établit le procès-verbal, les statuts mis à jour et, "
                . "le cas échéant, l'acte de cession.",
            self::DepotRangDesMinutes => 'Le client apporte son procès-verbal sous seing privé : '
                . "l'étude n'établit qu'un acte de dépôt, et réclame le PV et les statuts signés.",
        };
    }

    /**
     * Le mode par défaut, quand rien n'est renseigné.
     *
     * ⚠️ **Mesuré avant d'être posé** : les dossiers de modification déjà en base n'ont aucun
     * `mode_traitement`, et tous ont vu l'étude rédiger le procès-verbal. Le défaut reprend donc
     * ce qui s'est fait, il ne choisit rien de neuf — un défaut « dépôt » réécrirait l'histoire
     * de ces dossiers.
     */
    public static function defaut(): self
    {
        return self::Redaction;
    }

    public static function depuisLibelle(?string $libelle): ?self
    {
        if ($libelle === null || $libelle === '') {
            return null;
        }

        foreach (self::cases() as $mode) {
            if ($mode->label() === $libelle) {
                return $mode;
            }
        }

        return null;
    }

    /** @return array<int, array{valeur: string, label: string, explication: string}> */
    public static function toutes(): array
    {
        return array_map(
            fn (self $m) => [
                'valeur'      => $m->value,
                'label'       => $m->label(),
                'explication' => $m->explication(),
            ],
            self::cases(),
        );
    }
}
