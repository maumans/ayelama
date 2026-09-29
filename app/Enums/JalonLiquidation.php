<?php

namespace App\Enums;

use App\Models\Setting;

/**
 * Échéances d'une liquidation — **aucune n'est garantie**.
 *
 * ⚠️ **Lire ceci avant d'utiliser ces chiffres.** Trois délais circulent dans les échanges avec
 * l'étude : la durée du mandat du liquidateur, la clôture de la liquidation sous trois ans, la
 * radiation sous un mois. Aucun ne vient d'une source que le dépôt puisse citer. Le compte rendu
 * de juillet 2026 — le seul document de référence versé au projet — ne contient **zéro**
 * occurrence de « dissolution », « liquidation » ou « radiation ». Les valeurs ci-dessous sont
 * donc des hypothèses de travail, à confirmer avec Maître Bah.
 *
 * Trois conséquences, et elles sont le cœur de cette classe :
 *
 *   1. **Chaque jalon alerte, aucun ne bloque.** Un délai inventé qui empêcherait d'avancer un
 *      dossier régulier coûterait plus que l'absence totale de suivi. `ReglesSocieteService` ne
 *      lit pas cette classe, et `ReglesSocieteTest` le vérifie.
 *   2. **Les seuils sont des paramètres**, lus par {@see Setting::get()} au patron de
 *      `FormeSociete::CLE_CAPITAL_MINIMUM_SA`. Corriger un délai est une ligne dans les
 *      paramètres, pas un déploiement.
 *   3. **Chaque jalon porte `aVerifier()`** et une `source()` qui dit d'où vient le chiffre.
 *      Le message d'alerte le répète à l'utilisateur : une alerte fondée sur une hypothèse qui
 *      ne le dirait pas serait pire que pas d'alerte.
 *
 * Un test affirme que tous les jalons sont `aVerifier()`. Il échouera le jour où l'étude en
 * validera un — c'est le rappel voulu de lever le marqueur plutôt que de l'oublier.
 */
enum JalonLiquidation: string
{
    case FinMandatLiquidateur = 'fin_mandat_liquidateur';
    case ClotureLiquidation   = 'cloture_liquidation';
    case RadiationRccm        = 'radiation_rccm';

    public function label(): string
    {
        return match ($this) {
            self::FinMandatLiquidateur => 'Fin du mandat du liquidateur',
            self::ClotureLiquidation   => 'Clôture de la liquidation',
            self::RadiationRccm        => 'Radiation au RCCM',
        };
    }

    /** Clé de paramètre du délai, en mois. */
    public function cleParametre(): string
    {
        return match ($this) {
            self::FinMandatLiquidateur => 'liquidation_mandat_mois',
            self::ClotureLiquidation   => 'liquidation_cloture_mois',
            self::RadiationRccm        => 'liquidation_radiation_mois',
        };
    }

    /** Valeur d'hypothèse, en mois, si le paramètre n'a pas été renseigné. */
    public function delaiDefautMois(): int
    {
        return match ($this) {
            self::FinMandatLiquidateur => 36,
            self::ClotureLiquidation   => 36,
            self::RadiationRccm        => 1,
        };
    }

    public function delaiMois(): int
    {
        return (int) Setting::get($this->cleParametre(), $this->delaiDefautMois());
    }

    /**
     * Clé de `questionnaires.donnees` où l'**acte** fixe ce délai — `null` s'il n'y en a pas.
     *
     * ⚠️ Correction de fond du 2026-09-28. Le procès-verbal réel de l'étude écrit : « nommé pour
     * une durée de **trois (03) mois** à compter de la dissolution ». La durée du mandat n'est
     * donc pas un seuil d'étude, c'est une **décision de l'assemblée**, prise acte par acte — et
     * le paramètre global de 36 mois posé ici la contredisait dans son principe même, pas
     * seulement dans sa valeur.
     *
     * Le paramètre subsiste comme **repli** : une fiche entrée au registre à la main peut être
     * en liquidation sans qu'aucun dossier n'en porte la durée.
     *
     * Les deux autres jalons n'ont pas d'équivalent : aucun acte disponible ne fixe de délai de
     * clôture ni de radiation, et en inventer une clé laisserait croire qu'elle se remplit.
     */
    public function cleDonneesDelai(): ?string
    {
        return match ($this) {
            self::FinMandatLiquidateur => 'liquidateur.duree_mandat_chiffres',
            self::ClotureLiquidation,
            self::RadiationRccm        => null,
        };
    }

    /**
     * Ce délai est-il confirmé par une source ?
     *
     * `true` partout aujourd'hui, ce qui veut dire « à vérifier ». Le jour où l'étude en
     * validera un, ce `match` devra distinguer les cas — et le test de doctrine échouera pour
     * le rappeler.
     */
    public function aVerifier(): bool
    {
        return match ($this) {
            self::FinMandatLiquidateur,
            self::ClotureLiquidation,
            self::RadiationRccm => true,
        };
    }

    /** D'où vient le chiffre — phrase affichée avec l'alerte et à l'écran du registre. */
    public function source(): string
    {
        return match ($this) {
            self::FinMandatLiquidateur => 'Hypothèse de travail : durée usuelle évoquée par l\'étude, non confirmée par un texte versé au projet.',
            self::ClotureLiquidation   => 'Hypothèse de travail : « clôture sous trois ans » évoquée en réunion, absente du compte rendu de juillet 2026.',
            self::RadiationRccm        => 'Hypothèse de travail : « radiation sous un mois » évoquée en réunion, absente du compte rendu de juillet 2026.',
        };
    }

    /**
     * Colonne de `societes` depuis laquelle ce délai court.
     *
     * Le mandat et la clôture partent de la dissolution ; la radiation part de la clôture — ce
     * qui est la raison d'être du quatrième cas de {@see StatutSociete}.
     */
    public function colonneDepart(): string
    {
        return match ($this) {
            self::FinMandatLiquidateur,
            self::ClotureLiquidation => 'dissolution_at',
            self::RadiationRccm      => 'cloture_liquidation_at',
        };
    }

    /**
     * Statut dans lequel ce jalon est pertinent.
     *
     * Une société déjà radiée n'a plus d'échéance ; une société dont la liquidation est
     * clôturée n'a plus à surveiller le mandat de son liquidateur.
     */
    public function statutConcerne(): StatutSociete
    {
        return match ($this) {
            self::FinMandatLiquidateur,
            self::ClotureLiquidation => StatutSociete::EnLiquidation,
            self::RadiationRccm      => StatutSociete::LiquidationCloturee,
        };
    }

    /** Jalons pertinents pour un statut donné, dans l'ordre de déclaration. */
    public static function pourStatut(StatutSociete $statut): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $j) => $j->statutConcerne() === $statut,
        ));
    }
}
