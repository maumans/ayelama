<?php

namespace App\Enums;

/**
 * Donnée **typée** qu'une autorité délivre au retour d'une formalité.
 *
 * Jusqu'au 2026-09-29, le retour d'une formalité ne captait qu'une date et des fichiers : le
 * numéro RCCM et le NIF figuraient sur l'extrait reçu, et **rien ne les enregistrait**. Mesuré
 * ce jour-là : 27 formalités sur 29 portaient bien leurs pièces — le versant fichier marchait —
 * mais **12 données sur 14** étaient absentes du registre, et 5 des 6 sociétés dont le dossier
 * avait dépassé les formalités n'avaient ni RCCM ni NIF.
 *
 * Le piège était structurel. Les sept questionnaires de constitution ne demandent ni `soc.rccm`
 * ni `soc.nif` — à juste titre, le numéro n'existe pas encore. Mais `modification` et
 * `dissolution` les exigent. Le seul moment où l'application pouvait apprendre le numéro n'était
 * pas capté ; le seul moment où elle le réclamait était le dossier suivant. La boucle ne se
 * fermait jamais.
 *
 * ⚠️ **À ne pas confondre avec `baremes.retour_attendu`**, qui nomme le **papier** attendu
 * (« Extrait RCCM définitif », « Bordereau d'inscription »). Ce sont deux axes : le document
 * reçu, et la donnée qu'il porte. Certains retours n'ont que du papier — d'où une `colonne()`
 * qui peut valoir `null`.
 *
 * ⚠️ `match` exhaustifs sans branche par défaut : ajouter un cas doit échouer bruyamment ici.
 * Le miroir JS (`tools/generer-balises-resolvables.mjs`) n'a, lui, aucun filet — un test de
 * parité le tient.
 */
enum DonneeAuRetour: string
{
    case RccmNumero                    = 'rccm_numero';
    case RccmDate                      = 'rccm_date';
    case Nif                           = 'nif';
    case JalJournal                    = 'jal_journal';
    case JalDateParution               = 'jal_date_parution';
    case DepotGreffeNumero             = 'depot_greffe_numero';
    case QuittanceNumero               = 'quittance_numero';
    case DeclarationModificativeNumero = 'declaration_modificative_numero';

    public function label(): string
    {
        return match ($this) {
            self::RccmNumero                    => 'Numéro RCCM',
            self::RccmDate                      => "Date d'immatriculation au RCCM",
            self::Nif                           => 'NIF attribué',
            self::JalJournal                    => "Journal d'annonces légales",
            self::JalDateParution               => 'Date de parution',
            self::DepotGreffeNumero             => 'Numéro de dépôt au greffe',
            self::QuittanceNumero               => 'Numéro de quittance',
            self::DeclarationModificativeNumero => 'N° de déclaration modificative RCCM',
        };
    }

    /** Ce que le formaliste doit lire sur le document reçu — placeholder du champ. */
    public function exemple(): string
    {
        return match ($this) {
            self::RccmNumero                    => 'GN.TCC.2021.B09995',
            self::RccmDate                      => '',
            self::Nif                           => '000123456',
            self::JalJournal                    => 'Le Lynx',
            self::JalDateParution               => '',
            self::DepotGreffeNumero             => 'DEP-2026-0123',
            self::QuittanceNumero               => 'QT-2026-4567',
            self::DeclarationModificativeNumero => 'RCCM-GN-TCC.2026-XXXX',
        };
    }

    /** `date` ou `texte` — décide du contrôle de saisie et du format de stockage. */
    public function type(): string
    {
        return match ($this) {
            self::RccmDate,
            self::JalDateParution               => 'date',
            self::RccmNumero,
            self::Nif,
            self::JalJournal,
            self::DepotGreffeNumero,
            self::QuittanceNumero,
            self::DeclarationModificativeNumero => 'texte',
        };
    }

    /**
     * Colonne de `societes` que cette donnée renseigne — `null` si elle n'en renseigne aucune.
     *
     * **`null` est un cas de plein droit, pas un trou.** Une vente ou une hypothèque n'a pas de
     * fiche société (4 formalités sur 53), et un numéro de quittance n'a nulle part où aller :
     * la donnée reste sur la formalité, consultable et reprise dans les actes par une balise.
     *
     * ⚠️ `RccmDate` vise **`date_constitution`** et non une colonne nouvelle : le balisage du
     * procès-verbal emploie déjà `${soc.date_constitution}` pour « immatriculée … en date du … »,
     * et `date_acte` existe à côté pour la date de l'acte lui-même. Le commentaire de la
     * migration d'origine (« depuis quand la société existe ») est ambigu — il a été corrigé.
     *
     * ⚠️ `DeclarationModificativeNumero` ne vise **aucune** colonne, et c'est essentiel : y
     * écrire `rccm_numero` effacerait l'identité légale de la société. Une modification reçoit
     * un numéro de déclaration, pas un nouveau RCCM.
     */
    public function colonne(): ?string
    {
        return match ($this) {
            self::RccmNumero                    => 'rccm_numero',
            self::RccmDate                      => 'date_constitution',
            self::Nif                           => 'nif',
            self::JalJournal                    => 'jal_journal',
            self::JalDateParution,
            self::DepotGreffeNumero,
            self::QuittanceNumero,
            self::DeclarationModificativeNumero => null,
        };
    }

    /**
     * Règles de validation de la valeur saisie.
     *
     * ⚠️ **Aucun format de RCCM n'est imposé.** Trois formes coexistent dans le dépôt : le
     * procès-verbal réel de l'étude écrit `GN.TCC.2021.B09995`, le placeholder du questionnaire
     * `GN-CON-2020-B-XXXX`, et la base contient `TR45645`. Inventer une expression régulière
     * refuserait des numéros authentiques — une contrainte inventée est pire qu'une contrainte
     * absente. Seule la longueur est bornée.
     *
     * @return array<int, string>
     */
    public function regles(): array
    {
        return match ($this->type()) {
            'date'  => ['nullable', 'date'],
            'texte' => ['nullable', 'string', 'max:120'],
        };
    }

    /**
     * Balise produite dans les actes — `${retour.rccm_numero}`, etc.
     *
     * Espace de noms distinct de `soc.*` : ces valeurs ne viennent pas du questionnaire mais du
     * retour d'une autorité, et un modèle Word doit pouvoir citer l'un ou l'autre.
     */
    public function baliseCle(): string
    {
        return 'retour.' . $this->value;
    }

    /** @return array<int, array{valeur: string, label: string, type: string, exemple: string}> */
    public static function toutes(): array
    {
        return array_map(fn (self $c) => [
            'valeur'  => $c->value,
            'label'   => $c->label(),
            'type'    => $c->type(),
            'exemple' => $c->exemple(),
        ], self::cases());
    }

    /**
     * Résout une liste de valeurs techniques en cas, **en ignorant les inconnues**.
     *
     * Une donnée retirée d'un barème après coup reste dans `formalites.donnees_au_retour` des
     * formalités déjà générées : la refuser ferait échouer l'enregistrement d'un retour sur un
     * dossier ancien. On l'ignore, et la valeur déjà saisie reste lisible dans `donnees_recues`.
     *
     * @param  mixed $valeurs
     * @return array<int, self>
     */
    public static function depuis(mixed $valeurs): array
    {
        if (!is_array($valeurs)) {
            return [];
        }

        $cas = [];

        foreach ($valeurs as $valeur) {
            if (is_string($valeur) && $trouve = self::tryFrom($valeur)) {
                $cas[$trouve->value] = $trouve;
            }
        }

        // Ordre de déclaration de l'enum, pas celui de la configuration : le formulaire de
        // retour doit présenter ses champs dans un ordre stable d'un barème à l'autre.
        return array_values(array_filter(self::cases(), fn (self $c) => isset($cas[$c->value])));
    }
}
