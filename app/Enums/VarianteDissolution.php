<?php

namespace App\Enums;

use App\Contracts\OperationDeSociete;

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
enum VarianteDissolution: string implements OperationDeSociete
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
     * Le code d'opération selon l'addendum v1.2 § 5 — implémentation de {@see OperationDeSociete}.
     */
    public function codeOperation(): string
    {
        return match ($this) {
            self::Dissolution        => 'DISSOLUTION',
            self::ClotureLiquidation => 'CLOTURE_LIQUIDATION',
        };
    }

    /**
     * L'assemblée exigée par cette phase — addendum v1.2 § 2 & § 5.
     */
    public function assemblee(): TypeAssemblee
    {
        return match ($this) {
            self::Dissolution        => TypeAssemblee::Age,
            self::ClotureLiquidation => TypeAssemblee::AgCloture,
        };
    }

    /**
     * Modifie-t-elle les statuts ?
     *
     * Une dissolution ou clôture de liquidation ne produit pas de statuts mis à jour.
     */
    public function impacteStatuts(): bool
    {
        return false;
    }

    /**
     * Modifie-t-elle l'inscription au RCCM ?
     *
     * La dissolution comme la radiation font l'objet d'un dépôt au greffe.
     */
    public function impacteRccm(): bool
    {
        return true;
    }

    /**
     * Impose-t-elle une publication au JAL ? — addendum v1.2 § 5.
     *
     * La dissolution impose un avis de dissolution au JAL. La clôture, non — et ce n'est plus
     * un défaut prudent : la ligne 17 du tableau de validation du 2026-10-28, cochée « Oui »,
     * énumère les quatre étapes de la liquidation sans en citer aucune publication, là où la
     * ligne 15 la cite pour la dissolution.
     */
    public function publicationJal(): bool
    {
        return match ($this) {
            self::Dissolution        => true,
            self::ClotureLiquidation => false,
        };
    }

    /**
     * Ne se combine avec aucune autre opération dans le même dossier — règle C1.
     */
    public function exclusive(): bool
    {
        return true;
    }

    /**
     * La règle est-elle validée par l'étude (✅) ou à confirmer (⚠) ? — addendum v1.2 § 5.
     *
     * **Les deux le sont désormais**, par le tableau de validation signé par Maître Bah le
     * 28 octobre 2026 (`docs/Addvalis_Tableau_Validation_Modif_Dissol_Liquid_OK (1).docx`) :
     *
     *  - ligne 15, cochée « Oui » : la dissolution passe par un PV d'AGE, sa publication au
     *    journal d'annonces légales, le dépôt au greffe, puis un RCCM de dissolution ;
     *  - ligne 17, cochée « Oui » : la clôture statue sur les comptes en assemblée de clôture,
     *    le greffe délivre un RCCM de liquidation, la société est radiée. **Aucune publication
     *    au JAL n'y figure**, alors que la ligne 15 la mentionne pour la dissolution et que la
     *    ligne 5 a été annotée pour l'ajouter là où elle manquait. Cette absence-là est donc
     *    une réponse, pas un oubli : {@see publicationJal()} reste faux, et il est validé.
     */
    public function regleValidee(): bool
    {
        return true;
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
            // Lignes 15 et 17 du tableau de validation signé par Maître Bah le 2026-10-28.
            //
            // ⚠️ **Ces deux listes ont longtemps rendu `null`, et c'était juste** : le compte
            // rendu de juillet 2026 ne disait rien de la dissolution, et fabriquer une
            // « référence légale » aurait fait afficher une invention comme faisant foi, que
            // `divergeDeLaReference()` aurait ensuite défendue contre les corrections de
            // l'étude. Le tableau signé a changé cela — la source existe, datée et cochée.
            //
            // Ligne 15 : « PV d'AGE qui constate la dissolution et nomme un liquidateur ·
            // publication du PV dans un journal d'annonces légales · dépôt du PV et du journal
            // au greffe · le greffe délivre un RCCM de dissolution ». Les deux premiers points
            // sont des actes que l'étude établit, le troisième une formalité, le quatrième un
            // retour — seuls les actes figurent ici.
            self::Dissolution => [
                'acte_principal'   => 'Procès-verbal de dissolution et nomination du liquidateur',
                'insertion'        => "Insertion au journal d'annonces légales",
                'declaration_rccm' => 'Déclaration RCCM de dissolution',
            ],
            // Ligne 17 : « clôturée dans les 3 ans · l'assemblée de clôture statue sur les
            // comptes · le greffe délivre un RCCM de liquidation · la société est radiée ».
            // **Aucune publication n'y figure**, là où la ligne 15 la cite : l'absence est une
            // réponse, pas un oubli — voir {@see publicationJal()}.
            self::ClotureLiquidation => [
                'acte_principal'   => 'Procès-verbal de clôture de liquidation',
                'declaration_rccm' => 'Déclaration de radiation RCCM',
            ],
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
