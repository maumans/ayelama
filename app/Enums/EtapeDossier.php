<?php

namespace App\Enums;

/**
 * Étapes du cycle de vie d'un dossier, dans l'ordre.
 *
 * `Initialisation` a été réintroduite le 2026-08-04 : la création déposait le dossier
 * directement en Édition, et `verifierEdition()` y mélangeait deux métiers — constituer
 * le dossier (questionnaire, pièces d'identité des parties, accord signé du client)
 * d'un côté, produire les actes de l'autre. Les actes étaient d'ailleurs générés dès la
 * création, donc sur des données que le client n'avait pas encore validées.
 *
 * ⚠️ Ajouter une étape ici oblige à traiter les `match` exhaustifs côté PHP — ils
 * échouent bruyamment, c'est voulu — MAIS aussi `ETAPE_ORDER` / `ETAPE_TAB` /
 * `getStepBlockers()` côté JavaScript, qui échouent en silence. Voir décision #38.
 */
enum EtapeDossier: string
{
    case Initialisation   = 'initialisation';
    case Edition          = 'edition';
    case Revision         = 'revision';
    case Signature        = 'signature';
    case Formalites       = 'formalites';
    case Expedition       = 'expedition';
    case Cloture          = 'cloture';

    public function label(): string
    {
        return match($this) {
            self::Initialisation   => 'Initialisation',
            self::Edition          => 'Édition actes',
            self::Revision         => 'Certification des actes',
            self::Signature        => 'Signature',
            self::Formalites       => 'Formalités',
            self::Expedition       => 'Expédition',
            self::Cloture          => 'Clôturé',
        };
    }

    public function suivante(): ?self
    {
        return match($this) {
            self::Initialisation   => self::Edition,
            self::Edition          => self::Revision,
            self::Revision         => self::Signature,
            self::Signature        => self::Formalites,
            self::Formalites       => self::Expedition,
            self::Expedition       => self::Cloture,
            self::Cloture          => null,
        };
    }

    public function precedente(): ?self
    {
        return match($this) {
            self::Initialisation   => null,
            self::Edition          => self::Initialisation,
            self::Revision         => self::Edition,
            self::Signature        => self::Revision,
            self::Formalites       => self::Signature,
            self::Expedition       => self::Formalites,
            self::Cloture          => self::Expedition,
        };
    }

    public function ordre(): int
    {
        return match($this) {
            self::Initialisation   => 0,
            self::Edition          => 1,
            self::Revision         => 2,
            self::Signature        => 3,
            self::Formalites       => 4,
            self::Expedition       => 5,
            self::Cloture          => 6,
        };
    }

    public static function ordered(): array
    {
        return [
            self::Initialisation,
            self::Edition,
            self::Revision,
            self::Signature,
            self::Formalites,
            self::Expedition,
            self::Cloture,
        ];
    }
}
