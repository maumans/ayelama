<?php

namespace App\Enums;

/**
 * Pourquoi une partie à l'acte ne comparaît pas elle-même.
 *
 * Trois représentations juridiquement distinctes, que le langage courant confond et que
 * l'application confondait aussi : `clients.representant_legal` / `representant_qualite`,
 * deux textes libres conçus pour les personnes morales, servaient **déjà** à loger le tuteur
 * d'un associé mineur (règle 7, ReglesSocieteService::verifierCapaciteJuridique()). Y ajouter
 * le mandataire sur procuration aurait fait trois notions dans le même champ, sans plus aucun
 * moyen de savoir laquelle est laquelle — ni quelles pièces exiger.
 *
 * ⚠️ Le motif est porté par la **partie représentée**, pas par le représentant : un même
 * mandataire porte couramment les pouvoirs de plusieurs mandants du même acte, et chaque
 * mandat a son propre titre. Voir la migration add_representation_to_parties_table.
 */
enum MotifRepresentation: string
{
    /** Le mandant est capable mais empêché : il donne procuration. */
    case Procuration = 'procuration';

    /** Mineur ou majeur protégé : tuteur, curateur, administrateur légal. */
    case Legale = 'legale';

    /** Personne morale : son représentant légal comparaît pour elle. */
    case Organique = 'organique';

    /**
     * Rôle de `Partie` porté par un représentant, quel que soit le motif.
     *
     * Un seul rôle et non trois : le même homme peut être mandataire d'un associé et tuteur
     * d'un autre dans le même dossier — un rôle dérivé du motif n'aurait alors aucune valeur
     * cohérente. Le motif vit sur le lien, pas sur la personne. C'est aussi ce que
     * `DossierController::updateQuestionnaire()` interroge pour son ramasse-miettes.
     */
    public const ROLE = 'mandataire';

    public function label(): string
    {
        return match ($this) {
            self::Procuration => 'Procuration',
            self::Legale      => 'Représentation légale (tutelle, curatelle)',
            self::Organique   => "Représentant légal d'une personne morale",
        };
    }

    /**
     * Comment nommer le représentant à l'écran.
     *
     * Le rôle en base reste `mandataire` pour les trois motifs ; l'afficher tel quel devant
     * un tuteur serait faux. Le libellé se dérive donc du motif au moment du rendu.
     */
    public function labelRepresentant(): string
    {
        return match ($this) {
            self::Procuration => 'Mandataire',
            self::Legale      => 'Tuteur / curateur',
            self::Organique   => 'Représentant légal',
        };
    }

    /**
     * Jeu de pièces que ce motif ajoute au **représenté** — c'est son titre, pas celui du
     * représentant. Trois mandants représentés par le même homme, c'est trois procurations
     * et une seule carte d'identité.
     *
     * `null` pour l'organique : le PV d'assemblée et la déclaration RCCM sont déjà collectés
     * par le jeu `associe_morale` de la personne morale. Rien à déplacer — voir la note de
     * `Partie::piecesRequisesDefinition()`.
     */
    public function jeuDePiecesDuTitre(): ?string
    {
        return match ($this) {
            self::Procuration => 'titre_procuration',
            self::Legale      => 'titre_legale',
            self::Organique   => null,
        };
    }

    /**
     * La date du titre est-elle exigible ?
     *
     * Vrai pour la seule procuration : un acte authentique vise la procuration « en date du… »,
     * et une procuration sans date n'est pas visable.
     *
     * Faux pour la représentation légale, **délibérément** : le document guinéen qui établit
     * une tutelle n'a pas été arbitré avec l'étude (jugement du tribunal de première instance ?
     * ordonnance du juge des tutelles ? conseil de famille homologué ?). Exiger une date de
     * référence que l'on ne sait pas nommer reviendrait à inventer une contrainte — pire
     * qu'une contrainte absente. À rebasculer le jour où l'étude tranchera.
     *
     * Faux pour l'organique : le gérant tient ses pouvoirs des statuts, qui n'ont pas de date
     * à viser dans la comparution.
     */
    public function exigeTitreDate(): bool
    {
        return match ($this) {
            self::Procuration => true,
            self::Legale      => false,
            self::Organique   => false,
        };
    }

    /** @return array<int, array{value: string, label: string}> Pour le frontend. */
    public static function toutes(): array
    {
        return array_map(
            fn (self $m) => ['value' => $m->value, 'label' => $m->label()],
            self::cases(),
        );
    }
}
