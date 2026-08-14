<?php

namespace App\Enums;

use App\Models\Setting;

/**
 * Formes juridiques de sociétés et leurs obligations légales (règles 1, 2, 3 et 5 du CR
 * de juillet 2026, `Regles_Gestion_Plateforme_Notariale.docx`).
 *
 * Avant cet enum, `soc.forme` n'était qu'une liste de sept choix sans conséquence :
 * rien ne reliait une forme à son capital minimum, à son droit d'avoir un associé
 * unique, ni à son obligation de commissaire aux comptes. `SCS` et `SAU` manquaient
 * d'ailleurs à la liste.
 *
 * **Ce qui est en dur ici et ce qui est paramétrable** : les règles structurelles
 * (« une SARLU admet un associé unique », « une SA exige un commissaire aux comptes »)
 * ne changent pas sans réécrire le droit des sociétés — c'est du code. Les **montants**,
 * eux, évoluent par voie légale : le capital minimum vit dans `Setting`, éditable sans
 * déploiement.
 *
 * ⚠️ Les `match` sont exhaustifs, sans branche par défaut : ajouter une forme sans
 * décider de sa nature ni de ses obligations fera échouer PHP au lieu de la laisser
 * passer sans règle.
 */
enum FormeSociete: string
{
    case SA     = 'SA';
    case SAU    = 'SAU';
    case SARL   = 'SARL';
    case SARLU  = 'SARLU';
    case SAS    = 'SAS';
    case SASU   = 'SASU';
    case SNC    = 'SNC';
    case SCS    = 'SCS';
    case GIE    = 'GIE';

    /** Clé de paramètre du capital minimum d'une SA (règle 1). */
    public const CLE_CAPITAL_MINIMUM_SA = 'capital_minimum_sa';

    /** Valeur au 1ᵉʳ juillet 2026 — 140 000 000 GNF. */
    public const CAPITAL_MINIMUM_SA_DEFAUT = 140_000_000;

    public function label(): string
    {
        return match($this) {
            self::SA    => 'SA — Société Anonyme',
            self::SAU   => 'SAU — SA à associé unique',
            self::SARL  => 'SARL — Société à Responsabilité Limitée',
            self::SARLU => 'SARLU — SARL à associé unique',
            self::SAS   => 'SAS — Société par Actions Simplifiée',
            self::SASU  => 'SASU — SAS à associé unique',
            self::SNC   => 'SNC — Société en Nom Collectif',
            self::SCS   => 'SCS — Société en Commandite Simple',
            self::GIE   => "GIE — Groupement d'Intérêt Économique",
        };
    }

    /** Règle 3 — classification capitaux / personnes. */
    public function nature(): NatureSociete
    {
        return match($this) {
            self::SA, self::SAU,
            self::SARL, self::SARLU,
            self::SAS, self::SASU => NatureSociete::Capitaux,
            self::SNC, self::SCS  => NatureSociete::Personnes,
            self::GIE             => NatureSociete::Groupement,
        };
    }

    /** Règle 2 — formes constituables avec un seul associé. */
    public function admetAssocieUnique(): bool
    {
        return match($this) {
            self::SASU, self::SARLU, self::SAU => true,
            self::SA, self::SARL, self::SAS,
            self::SNC, self::SCS, self::GIE    => false,
        };
    }

    /**
     * Règle 2, en creux : une forme « à associé unique » n'admet **qu'un** associé.
     * Le document ne le dit pas explicitement, mais c'est la définition même de ces
     * formes — une SARLU à deux associés est une SARL.
     */
    public function exigeAssocieUnique(): bool
    {
        return $this->admetAssocieUnique();
    }

    /** Règle 5 — commissaire aux comptes obligatoire à la création. */
    public function exigeCommissaireAuxComptes(): bool
    {
        return match($this) {
            self::SA, self::SAU => true,
            self::SARL, self::SARLU, self::SAS, self::SASU,
            self::SNC, self::SCS, self::GIE => false,
        };
    }

    /**
     * Règle 1 — capital minimum, ou `null` si aucun n'est requis.
     *
     * Lu depuis `Setting` pour la SA : ce montant est fixé par la loi et changera.
     */
    public function capitalMinimum(): ?int
    {
        return match($this) {
            self::SA, self::SAU => (int) Setting::get(
                self::CLE_CAPITAL_MINIMUM_SA,
                self::CAPITAL_MINIMUM_SA_DEFAUT,
            ),
            // Le document est explicite : « pas de capital minimum requis » pour
            // SARL/SARLU/SAS, et le GIE « peut être créé SANS capital ».
            self::SARL, self::SARLU, self::SAS, self::SASU,
            self::SNC, self::SCS, self::GIE => null,
        };
    }

    public function responsabilite(): string
    {
        return $this->nature()->responsabilite();
    }

    /**
     * Restitution des règles applicables à cette forme, pour l'assistant de création :
     * elles doivent guider la saisie et non se découvrir au moment du blocage.
     */
    public function reglesApplicables(): array
    {
        return [
            'valeur'                => $this->value,
            'label'                 => $this->label(),
            'nature'                => $this->nature()->value,
            'natureLabel'           => $this->nature()->label(),
            'responsabilite'        => $this->responsabilite(),
            'capitalMinimum'        => $this->capitalMinimum(),
            'admetAssocieUnique'    => $this->admetAssocieUnique(),
            'exigeCommissaire'      => $this->exigeCommissaireAuxComptes(),
            'exigeMajoriteAssocies' => $this->nature()->exigeMajoritePourEtreAssocie(),
        ];
    }

    /** @return array<int, array> Toutes les formes et leurs règles, pour le frontend. */
    public static function toutesLesRegles(): array
    {
        return array_map(fn (self $f) => $f->reglesApplicables(), self::cases());
    }

    /**
     * Forme déduite du code du type d'acte (`SOC-SARLU` → SARLU).
     *
     * **Source la plus fiable, et prioritaire sur le questionnaire** : le champ
     * `soc.forme` n'existe que dans un seul des questionnaires de société — constaté sur
     * les 10 dossiers réels, tous dépourvus de cette clé. Le type d'acte, lui, est
     * toujours choisi dans l'assistant, et « Constitution SARLU » ne peut pas désigner
     * une SA.
     *
     * Retourne null pour les types de société qui ne constituent pas une forme précise
     * (`SOC-MOD` modification de statuts, `SOC-DIS` dissolution) : les règles de
     * constitution ne s'y appliquent pas.
     */
    public static function depuisCodeTypeActe(?string $code): ?self
    {
        return match ($code) {
            'SOC-SA'    => self::SA,
            'SOC-SARL'  => self::SARL,
            'SOC-SARLU' => self::SARLU,
            'SOC-SAS'   => self::SAS,
            'SOC-SASU'  => self::SASU,
            'SOC-SNC'   => self::SNC,
            'SOC-GIE'   => self::GIE,
            default     => null,
        };
    }
}
