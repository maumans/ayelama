<?php

namespace App\Enums;

use App\Models\Courrier;
use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Models\Formalite;
use App\Models\Partie;
use App\Models\Recu;
use App\Models\Societe;

/**
 * Rubriques d'un dossier notarial, dans l'ordre de classement.
 *
 * Remplace la clôture « configurée par type d'acte » : la rubrique d'une pièce se
 * DÉDUIT de son origine dans le workflow, elle ne se déclare pas. Chaque étape produit
 * ses pièces, et chaque pièce sait donc où elle se range.
 *
 * Sert à deux endroits qui doivent rester identiques : l'onglet Clôture d'un dossier et
 * la GED transversale — tous deux alimentés par InventaireClotureService.
 */
enum RubriqueCloture: string
{
    case Actes             = 'actes';
    case AccordClient      = 'accord_client';
    case PiecesParties     = 'pieces_parties';
    case PiecesSociete     = 'pieces_societe';
    case PiecesFormalites  = 'pieces_formalites';
    case Courriers         = 'courriers';
    case Facturation       = 'facturation';

    public function label(): string
    {
        return match($this) {
            self::Actes            => 'Actes',
            self::AccordClient     => 'Accord du client',
            self::PiecesParties    => 'Pièces des parties',
            self::PiecesSociete    => 'Pièces de la société',
            self::PiecesFormalites => 'Pièces des formalités',
            self::Courriers        => 'Courriers',
            self::Facturation      => 'Facturation',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::Actes            => 'Actes générés depuis les modèles et documents ajoutés au dossier',
            self::AccordClient     => 'Fiche de recueil signée par le client',
            self::PiecesParties    => 'Pièces d\'identité et justificatifs des personnes',
            self::PiecesSociete    => 'Dossier constitutif de la société, versé au registre',
            self::PiecesFormalites => 'Justificatifs déposés et retours des organismes',
            self::Courriers        => 'Lettres de transmission',
            self::Facturation      => 'Reçus de paiement',
        };
    }

    /**
     * Rang de classement, de 1 à 7.
     *
     * Les pièces de la société suivent celles des parties : ce sont, comme elles, des pièces
     * **fournies** au dossier et non produites par lui.
     */
    public function ordre(): int
    {
        return match($this) {
            self::Actes            => 1,
            self::AccordClient     => 2,
            self::PiecesParties    => 3,
            self::PiecesSociete    => 4,
            self::PiecesFormalites => 5,
            self::Courriers        => 6,
            self::Facturation      => 7,
        };
    }

    /** @return self[] Dans l'ordre de classement. */
    public static function ordonnees(): array
    {
        $cases = self::cases();
        usort($cases, fn (self $a, self $b) => $a->ordre() <=> $b->ordre());

        return $cases;
    }

    /**
     * Rubrique d'un document de la GED.
     *
     * Le `match` est exhaustif sur le **type de rattachement** (`documentable_type`),
     * et non sur `categorie` : la catégorie d'un acte vient de
     * `ModeleActe.type_document`, saisi librement par l'administrateur
     * (`attestation`, `dnsv`, `rccm`…). Un `match` sur categorie serait ingérable et
     * laisserait échapper toute nouvelle valeur. Le type de rattachement, lui, est un
     * ensemble fermé de trois classes — ajouter un quatrième documentable lèvera une
     * erreur ici tant qu'il n'aura pas été classé.
     *
     * `accord_client` est le seul cas particulier : c'est un document du dossier, mais
     * il ne relève pas des actes — c'est la pièce qui prouve l'accord du client sur le
     * questionnaire, et elle a sa propre rubrique.
     */
    public static function pourDocument(DocumentFichier $document): self
    {
        return match ($document->documentable_type) {
            Dossier::class   => $document->categorie === 'accord_client'
                ? self::AccordClient
                : self::Actes,
            Partie::class    => self::PiecesParties,
            // Quatrième documentable (2026-08-11) : le dossier constitutif d'une société que
            // l'étude n'a pas constituée. Seule famille de pièces qui n'appartienne pas au dossier
            // mais au registre — elle figure donc à l'inventaire de **chacun** de ses dossiers, et
            // s'y vérifie indépendamment (voir la migration corriger_unicite_cloture_verifications).
            Societe::class   => self::PiecesSociete,
            Formalite::class => self::PiecesFormalites,
        };
    }

    /**
     * Rubrique d'une pièce qui n'est pas un DocumentFichier (Courrier, Recu) — ces deux
     * familles ont leur propre table avec leur propre `chemin_fichier`, héritage
     * antérieur au module GED unifié.
     */
    public static function pourModele(object $modele): self
    {
        return match ($modele::class) {
            Courrier::class => self::Courriers,
            Recu::class     => self::Facturation,
        };
    }
}
