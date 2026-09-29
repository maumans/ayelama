<?php

namespace App\Enums;

/**
 * Ce que l'application exige de la **pièce d'accord** avant de quitter l'Initialisation.
 *
 * Jusqu'au 2026-09-28, l'exigence était uniforme : les 24 types d'acte devaient tous téléverser
 * une pièce d'accord signée. {@see \App\Models\Dossier::pieceAccordAttendue()} savait déjà en
 * changer le **nom** selon le type — « Accord client — questionnaire signé » pour une
 * constitution, « Décision d'assemblée des associés » pour une modification — mais jamais son
 * caractère obligatoire.
 *
 * Mesuré ce jour-là : le blocage a été introduit le 2026-08-14, et **13 des 20 dossiers** ayant
 * dépassé l'Initialisation n'ont aucune pièce d'accord — tous antérieurs à cette date. La règle
 * n'a donc jamais été confrontée aux types d'acte apparus depuis, à commencer par la dissolution.
 *
 * **Trois cas, et `Attendue` est celui qui manquait.** Le dépôt ne connaissait que le tout ou
 * rien : une pièce bloque, ou elle n'existe pas. Une exigence non validée par l'étude n'entre
 * dans aucune des deux cases — elle doit se **montrer** sans s'imposer, faute de quoi on choisit
 * entre inventer une contrainte et taire une attente. C'est le même registre que
 * `Partie::PIECES_A_CONFIRMER`, qui affiche `acte_tutelle` sans bloquer dessus.
 *
 * ⚠️ `match` exhaustifs sans branche par défaut : ajouter un cas doit échouer bruyamment ici.
 */
enum ExigenceAccord: string
{
    case Bloquante = 'bloquante';
    case Attendue  = 'attendue';
    case SansObjet = 'sans_objet';

    public function label(): string
    {
        return match ($this) {
            self::Bloquante => 'Obligatoire',
            self::Attendue  => 'Recommandée',
            self::SansObjet => 'Sans objet',
        };
    }

    /**
     * Phrase destinée à l'administrateur, sous le sélecteur de l'écran Types d'actes.
     *
     * Elle dit l'**effet**, pas le nom du cas : « recommandée » ne signifie rien tant qu'on ne
     * sait pas si le dossier avance quand même.
     */
    public function explication(): string
    {
        return match ($this) {
            self::Bloquante => "Le dossier ne peut pas quitter l'Initialisation tant que la pièce n'est pas téléversée.",
            self::Attendue  => "La pièce est annoncée et signalée manquante, mais elle n'empêche pas d'avancer.",
            self::SansObjet => "Aucune pièce d'accord n'est attendue : la carte ne s'affiche pas sur le dossier.",
        };
    }

    /**
     * Phrase de conséquence, **ajoutée** aux instructions de la pièce.
     *
     * ⚠️ Elle ne vit pas dans le registre, et c'est délibéré. Les deux libellés historiques se
     * terminaient par « Le dossier ne pourra pas passer en certification sans elle » : la
     * conséquence était **encodée dans le texte**. Dès qu'un administrateur bascule un type sur
     * « Recommandée » depuis Paramètres, cette phrase devient un mensonge — et rien ne le
     * signalerait. Composée ici, elle ne peut plus survivre à un changement de niveau.
     */
    public function consequence(): string
    {
        return match ($this) {
            self::Bloquante => "Le dossier ne pourra pas passer à l'étape suivante sans cette pièce.",
            self::Attendue  => "Cette pièce n'est pas exigée pour avancer : le dossier peut passer à l'étape suivante sans elle.",
            self::SansObjet => '',
        };
    }

    /** Empêche-t-elle la sortie de l'Initialisation ? */
    public function bloque(): bool
    {
        return match ($this) {
            self::Bloquante            => true,
            self::Attendue,
            self::SansObjet            => false,
        };
    }

    /**
     * La carte d'accord doit-elle s'afficher sur le dossier ?
     *
     * `Attendue` s'affiche : c'est tout son intérêt. Le dépôt préfère « un blocage énuméré à un
     * bouton grisé », et par extension une attente annoncée à une attente tue — l'étude peut
     * déposer la pièce si elle l'a, sans y être contrainte.
     */
    public function saffiche(): bool
    {
        return match ($this) {
            self::Bloquante,
            self::Attendue  => true,
            self::SansObjet => false,
        };
    }

    /** Couleur du bandeau de la carte — miroir de la convention de `StatutSociete::couleur()`. */
    public function couleur(): string
    {
        return match ($this) {
            self::Bloquante => 'warning',
            self::Attendue  => 'slate',
            self::SansObjet => 'slate',
        };
    }

    /** @return array<int, array{valeur: string, label: string, explication: string}> */
    public static function toutes(): array
    {
        return array_map(fn (self $c) => [
            'valeur'      => $c->value,
            'label'       => $c->label(),
            'explication' => $c->explication(),
        ], self::cases());
    }
}
