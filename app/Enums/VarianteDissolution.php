<?php

namespace App\Enums;

use App\Contracts\VarianteTypeActe;

/**
 * Les deux phases d'une dissolution-liquidation, variantes du type d'acte `SOC-DIS`.
 *
 * **Deux dossiers, un seul type d'acte.** Une dissolution se décide en assemblée ; la
 * liquidation se déroule ensuite, sur des mois ou des années ; une seconde assemblée en
 * approuve les comptes et donne quitus au liquidateur. Ce sont deux prestations distinctes,
 * facturées séparément, avec leurs propres formalités — donc deux dossiers. Mais c'est une
 * seule procédure : en faire deux types d'acte aurait doublé la nomenclature, les
 * questionnaires et les grilles tarifaires pour une distinction que la variante porte déjà.
 *
 * Même parti pris que {@see TypeModificationStatutaire}, à une différence près : une assemblée
 * décide couramment plusieurs modifications à la fois, jamais deux phases de liquidation à la
 * fois. La sélection est donc **unique** — le questionnaire est un `select`, pas un
 * `checkbox_group`. La plomberie continue de parler en tableau (`actesPrevus()`,
 * `sertUneVariante()`) : c'est le contrat qui porte la cardinalité, pas la tuyauterie.
 *
 * ⚠️ **Aucun délai n'est déclaré ici.** Durée du mandat de liquidateur, clôture sous trois ans,
 * radiation sous un mois : ces chiffres circulent mais ne viennent d'aucune source validée que
 * le dépôt puisse citer — le compte rendu de juillet 2026 ne mentionne pas la dissolution. Ils
 * vivent dans {@see JalonLiquidation}, marqués à vérifier, et **alertent sans jamais bloquer**.
 * Une contrainte inventée est pire qu'une contrainte absente.
 */
enum VarianteDissolution: string implements VarianteTypeActe
{
    case Dissolution        = 'dissolution';
    case ClotureLiquidation = 'cloture_liquidation';

    public function valeur(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Dissolution        => 'Dissolution anticipée',
            self::ClotureLiquidation => 'Clôture de la liquidation',
        };
    }

    /**
     * Clés de `questionnaires.donnees` portant la sélection — implémentation de
     * {@see VarianteTypeActe}.
     *
     * Une seule clé, là où `SOC-MOD` en a deux (`modif.types`, plus `modif.type` conservée pour
     * les brouillons antérieurs à la migration du 2026-08-11). `dissolution.phase` naît avec la
     * bonne forme : il n'y a rien à replier.
     *
     * @return array<int, string>
     */
    public static function clesQuestionnaire(): array
    {
        return ['dissolution.phase'];
    }

    /**
     * Phase choisie, depuis la valeur brute de `donnees`.
     *
     * Rend un tableau de 0 ou 1 élément : le contrat est collectif pour rester commun avec le
     * multi-modifications, la cardinalité réelle est portée ici. Un tableau est accepté en
     * entrée par tolérance (un import, une saisie API), mais seule la première valeur reconnue
     * est retenue — deux phases dans un même dossier n'ont pas de sens et
     * {@see \App\Services\ReglesSocieteService} le signale plutôt que de le deviner.
     *
     * @return array<int, self>
     */
    public static function depuisDonnees(mixed $valeur): array
    {
        $valeurs = match (true) {
            is_array($valeur)  => $valeur,
            is_string($valeur) => [$valeur],
            default            => [],
        };

        foreach ($valeurs as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);

            foreach (self::cases() as $cas) {
                if ($cas->label() === $item || $cas->value === $item) {
                    return [$cas];
                }
            }
        }

        return [];
    }

    /**
     * Documents imposés par une **référence écrite** — `null` : il n'en existe pas.
     *
     * C'est un refus délibéré, pas un trou à combler. `TypeModificationStatutaire` rend une
     * liste parce que la règle 9 du compte rendu de juillet 2026 l'énonce noir sur blanc. Ce
     * compte rendu ne dit **rien** de la dissolution : zéro occurrence de dissolution, de
     * liquidation ou de radiation.
     *
     * Inventer une liste ici la ferait afficher par l'écran Processus comme faisant foi, et
     * `DocumentAttendu::divergeDeLaReference()` défendrait cette fabrication contre les
     * corrections de l'étude. Les actes d'une dissolution sont donc ceux de ses gabarits —
     * exactement le parti pris déjà retenu pour les créations, les ventes et les baux.
     *
     * @return array<string, string>|null slug => libellé
     */
    public function documentsReference(): ?array
    {
        return match ($this) {
            self::Dissolution,
            self::ClotureLiquidation => null,
        };
    }

    /**
     * Statut que la fiche société doit porter **avant** ce dossier.
     *
     * Ce n'est pas une règle de droit inventée : c'est la cohérence interne de données que
     * l'application produit elle-même. On ne clôture pas la liquidation d'une société qui n'a
     * jamais été dissoute, et on ne dissout pas deux fois.
     */
    public function statutRequis(): StatutSociete
    {
        return match ($this) {
            self::Dissolution        => StatutSociete::Active,
            self::ClotureLiquidation => StatutSociete::EnLiquidation,
        };
    }

    /**
     * Statut posé sur la fiche à l'entrée en **Expédition**.
     *
     * Même seuil que {@see \App\Services\SocieteMutationService} pour une modification
     * statutaire : c'est l'instant où les formalités sont revenues, donc où l'acte est
     * opposable aux tiers. Avant, la décision peut encore être corrigée ou abandonnée.
     *
     * `Radiee` n'est jamais posé ici : la radiation n'est prouvée que par la pièce du greffe,
     * et aucune étape de dossier ne la constate — le dossier de clôture est terminé bien avant.
     * Elle reste une action humaine explicite sur la fiche.
     */
    public function statutApres(): StatutSociete
    {
        return match ($this) {
            self::Dissolution        => StatutSociete::EnLiquidation,
            self::ClotureLiquidation => StatutSociete::LiquidationCloturee,
        };
    }

    /** Colonne de date du cycle de vie que cette phase renseigne. */
    public function colonneDate(): string
    {
        return match ($this) {
            self::Dissolution        => 'dissolution_at',
            self::ClotureLiquidation => 'cloture_liquidation_at',
        };
    }

    /**
     * Clé de `donnees` portant la date de l'assemblée de cette phase.
     *
     * Deux clés distinctes plutôt qu'une seule réutilisée : les deux assemblées sont séparées
     * de mois ou d'années, et chacune est datée dans son propre dossier.
     */
    public function cleDateAssemblee(): string
    {
        return match ($this) {
            self::Dissolution        => 'dissolution.date_assemblee',
            self::ClotureLiquidation => 'cloture.date_assemblee',
        };
    }

    /** Bloc de champs du questionnaire que cette phase ouvre — miroir de `showIf`. */
    public function blocQuestionnaire(): string
    {
        return match ($this) {
            self::Dissolution        => 'dissolution',
            self::ClotureLiquidation => 'cloture_liquidation',
        };
    }

    /** Retrouve le cas depuis le libellé stocké au questionnaire, ou sa valeur technique. */
    public static function depuisLibelle(?string $valeur): ?self
    {
        return self::depuisDonnees($valeur)[0] ?? null;
    }

    public function resume(): array
    {
        return [
            'valeur'        => $this->value,
            'label'         => $this->label(),
            'statutRequis'  => $this->statutRequis()->value,
            'statutApres'   => $this->statutApres()->value,
            'bloc'          => $this->blocQuestionnaire(),
        ];
    }

    /** @return array<int, array> Pour restitution au frontend. */
    public static function toutes(): array
    {
        return array_map(fn (self $c) => $c->resume(), self::cases());
    }
}
