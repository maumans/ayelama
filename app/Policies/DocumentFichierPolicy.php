<?php

namespace App\Policies;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Models\Formalite;
use App\Models\Partie;
use App\Models\User;

/**
 * Ce qu'on peut faire d'**un** document : le supprimer, restaurer une version, le corriger.
 *
 * Retour R07 du 2026-11-16 : « retirer les boutons de suppression pour ceux qui n'y ont pas
 * droit ». Le droit était déduit, côté écran, d'un `can.genererDocuments` **global au dossier**,
 * alors que `DocumentController::destroy` autorisait sur `genererDocuments` quel que soit le
 * document. Deux conséquences mesurées :
 *
 *  - une **pièce de partie** affichait « Supprimer » sous `gererPieces` (Initialisation comprise)
 *    et le serveur répondait 403 à l'Initialisation — bouton visible, action refusée ;
 *  - « Restaurer une version » n'avait **aucun** contrôle côté écran.
 *
 * La règle vit désormais ici, par document, et l'écran reçoit le résultat (`peut_supprimer`,
 * `peut_restaurer`, `peut_corriger`) au lieu de le recalculer : il ne peut plus diverger.
 *
 * Le `match` sur la famille du document n'a pas de branche « sinon » permissive : une nouvelle
 * famille de documents est refusée tant qu'on n'a pas décidé de ses droits.
 */
class DocumentFichierPolicy
{
    public function __construct(private readonly DossierPolicy $dossiers) {}

    public function supprimer(User $user, DocumentFichier $document): bool
    {
        if ($document->est_signe_cachete) return false;

        $dossier = $document->dossierGouvernant();
        if (! $dossier) return false; // pièce de registre : se gère depuis la société

        return match (true) {
            // Un justificatif de personne suit le droit de déposer les pièces — c'est le même geste
            // à l'envers, et c'est à l'Initialisation qu'on en a besoin.
            $document->documentable instanceof Partie    => $this->dossiers->gererPieces($user, $dossier),
            $document->documentable instanceof Formalite => $this->dossiers->gererFormalites($user, $dossier),
            // L'accord signé du client est déposé verrouillé ; s'il ne l'était pas, il reste un
            // élément de constitution, pas un acte produit.
            $document->categorie === 'accord_client'     => $this->dossiers->modifierQuestionnaire($user, $dossier),
            $document->documentable instanceof Dossier   => $this->dossiers->genererDocuments($user, $dossier),
            default                                      => false,
        };
    }

    /** Restaurer une version, c'est remplacer le contenu : mêmes droits que la suppression. */
    public function restaurer(User $user, DocumentFichier $document): bool
    {
        return $this->supprimer($user, $document);
    }

    /**
     * Déposer une **version corrigée** d'un acte (retour R19, 2026-11-16).
     *
     * Ouvert au **rédacteur** — c'est le créateur du dossier : `redacteur_id` est posé à
     * `auth()->id()` à la création, il n'existe pas de colonne `created_by` — au **certificateur**
     * (`reviseur_id`), et à l'administrateur. L'étude a nommé ces deux rôles ; le notaire n'est
     * pas ajouté d'office.
     *
     * Étapes : Édition et Certification. Au-delà, l'acte est certifié ; le corriger contournerait
     * la certification — c'est la règle de `genererDocuments`. Une correction **périme** le point
     * de certification du document (voir `RevisionPoint::perimerPour()`) : le certificateur se
     * prononce de nouveau, y compris sur sa propre correction.
     */
    public function corriger(User $user, DocumentFichier $document): bool
    {
        if (! $user->actif) return false;
        if ($document->est_signe_cachete) return false;
        if (! $document->documentable instanceof Dossier) return false;
        if ($document->categorie === 'accord_client') return false;

        $dossier = $document->documentable;
        if (! in_array($dossier->etape, [EtapeDossier::Edition, EtapeDossier::Revision], true)) {
            return false;
        }
        if ($user->hasRole(RoleUtilisateur::Administrateur)) return true;

        return $dossier->redacteur_id === $user->id
            || $dossier->reviseur_id === $user->id;
    }
}
