<?php

namespace App\Policies;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\User;

class DossierPolicy
{
    /**
     * Un dossier clôturé est FIGÉ : plus aucune modification de son contenu.
     *
     * Vérifié **avant** le raccourci administrateur de chaque ability, contrairement aux
     * contrôles de rôle : c'est une propriété de l'état du dossier, pas une question de
     * permission. En notarial, altérer un dossier clôturé — retirer la vérification
     * d'une pièce, déposer une nouvelle version signée, ajouter un paiement — n'a aucun
     * usage légitime et ruinerait la valeur probatoire de la clôture.
     *
     * La consultation (`view`, téléchargements, GED) reste évidemment ouverte : figer
     * n'est pas masquer.
     *
     * Réouverture : `DossierStepService::reculer()` existe mais n'est atteignable que
     * depuis le renvoi en correction d'une certification (Révision → Édition). Aucune
     * route ne permet de sortir un dossier de Clôturé — c'est volontaire.
     */
    private function estFige(Dossier $dossier): bool
    {
        return $dossier->etape === EtapeDossier::Cloture;
    }

    public function viewAny(User $user): bool
    {
        return $user->actif;
    }

    public function view(User $user, Dossier $dossier): bool
    {
        return $user->actif && $this->estAssigne($user, $dossier);
    }

    public function create(User $user): bool
    {
        return $user->actif && $user->hasAnyRole(RoleUtilisateur::peuventOuvrir());
    }

    /**
     * Modification des informations générales du dossier (objet, valeur, échéance,
     * assignations…).
     *
     * Le raccourci administrateur reste **avant** le `match`, contrairement aux abilities
     * restreintes à certaines étapes : `update` est légitime à toutes les étapes ouvertes
     * — chacune a ses champs modifiables — et le `match` ne porte que sur l'assignation
     * selon l'étape. Seul le gel s'y applique.
     *
     * Ce qui devait en sortir l'a été : le questionnaire et les parties relèvent de
     * `modifierQuestionnaire()`, les dates de signature de `enregistrerSignatures()`.
     */
    public function update(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($this->estFige($dossier)) return false;
        if ($user->hasRole(RoleUtilisateur::Administrateur)) return true;

        return match ($dossier->etape) {
            EtapeDossier::Initialisation,
            EtapeDossier::Edition        => $dossier->redacteur_id === $user->id
                || $dossier->notaire_id === $user->id,
            EtapeDossier::Revision       => $dossier->reviseur_id === $user->id
                || $dossier->notaire_id === $user->id,
            EtapeDossier::Signature      => $dossier->redacteur_id === $user->id
                || $dossier->notaire_id === $user->id,
            EtapeDossier::Formalites,
            EtapeDossier::Expedition     => $dossier->formaliste_id === $user->id
                || $dossier->notaire_id === $user->id,
            default                      => false,
        };
    }

    public function reassigner(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;

        return $user->hasRole(RoleUtilisateur::Administrateur)
            || $dossier->notaire_id === $user->id;
    }

    /**
     * Suppression d'un dossier — uniquement avant qu'il ne soit certifié, donc en
     * Initialisation ou en Édition.
     *
     * Le contrôle d'étape précède le raccourci administrateur : il l'ignorait, ce qui
     * permettait de supprimer un dossier en cours de formalités alors que la règle
     * appliquée au notaire ne l'autorisait qu'en amont. Un dossier engagé auprès d'un
     * organisme ne se supprime pas, quel que soit le rôle.
     */
    public function delete(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($this->estFige($dossier)) return false;
        // Initialisation incluse : un dossier peut être abandonné avant d'avoir produit
        // ses actes. Au-delà, il est engagé — voir estFige() pour un dossier clôturé.
        if (!in_array($dossier->etape, [EtapeDossier::Initialisation, EtapeDossier::Edition], true)) {
            return false;
        }
        if ($user->hasRole(RoleUtilisateur::Administrateur)) return true;

        return $dossier->notaire_id === $user->id;
    }

    public function avancer(User $user, Dossier $dossier): bool
    {
        return $this->update($user, $dossier);
    }

    public function reviser(User $user, Dossier $dossier): bool
    {
        if (!$user->actif || $dossier->etape !== EtapeDossier::Revision) return false;

        return $user->hasRole(RoleUtilisateur::Administrateur)
            || $dossier->reviseur_id === $user->id
            || $dossier->notaire_id === $user->id;
    }

    /**
     * Générer/régénérer les actes et déposer les pièces des parties — Édition et
     * Certification. La certification est incluse à dessein : une pièce manquante ou un
     * acte à corriger doit pouvoir être traité pendant le contrôle.
     *
     * Le contrôle d'étape précède le raccourci administrateur : il l'ignorait, ce qui
     * permettait de régénérer un acte à l'étape Signature — donc après validation de la
     * certification, sur un contenu qu'elle n'a pas vu.
     */
    /**
     * Déposer les pièces justificatives des personnes au dossier — **Initialisation et Édition**.
     *
     * Ces actions passaient par `genererDocuments()`, qui exclut l'Initialisation. Or c'est
     * précisément l'étape qui **exige** ces pièces pour être franchie
     * ({@see \App\Services\DossierStepService::erreursDeConstitution()}) : un dossier neuf réclamait
     * donc des pièces que personne ne pouvait y déposer, et l'interface n'affichait même pas le
     * bouton de téléversement. Blocage complet, constaté sur `SOC-2026-0013`.
     *
     * Déposer un justificatif n'est pas produire un acte : les deux abilities n'avaient aucune
     * raison d'être confondues. L'Édition reste couverte — une pièce peut manquer et être ajoutée
     * après un renvoi en correction.
     */
    public function gererPieces(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($this->estFige($dossier)) return false;
        if (!in_array($dossier->etape, [EtapeDossier::Initialisation, EtapeDossier::Edition], true)) {
            return false;
        }
        if ($user->hasRole(RoleUtilisateur::Administrateur)) return true;

        return $dossier->redacteur_id === $user->id
            || $dossier->notaire_id === $user->id;
    }

    public function genererDocuments(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($this->estFige($dossier)) return false;
        if (!in_array($dossier->etape, [EtapeDossier::Edition, EtapeDossier::Revision], true)) {
            return false;
        }
        if ($user->hasRole(RoleUtilisateur::Administrateur)) return true;

        return $dossier->redacteur_id === $user->id
            || $dossier->notaire_id === $user->id;
    }

    /**
     * Modifier le questionnaire, la liste des parties et l'accord signé du client —
     * **Initialisation seule** (étape réintroduite le 2026-08-04).
     *
     * Ces trois actions étaient gardées par `update()`, légitime à toutes les étapes
     * ouvertes : le questionnaire restait donc modifiable en Signature, Formalités et
     * Expédition, et `updateQuestionnaire()` **régénère les actes**. Une certification
     * validée pouvait ainsi porter sur un contenu réécrit après coup — seuls les
     * documents déjà signés/cachetés y échappaient.
     *
     * Constituer le dossier est le métier de l'Initialisation ; l'Édition ne porte plus
     * que les actes. Pour corriger ensuite une identité ou une pièce, on recule le
     * dossier — geste explicite et tracé au journal.
     */
    public function modifierQuestionnaire(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($dossier->etape !== EtapeDossier::Initialisation) return false;
        if ($user->hasRole(RoleUtilisateur::Administrateur)) return true;

        return $dossier->redacteur_id === $user->id
            || $dossier->notaire_id === $user->id;
    }

    /**
     * Renseigner les dates de signature du client et du notaire — **étape Signature
     * seule**.
     *
     * Ces deux champs vivaient dans `UpdateDossierRequest` en `sometimes|nullable`, sans
     * aucun contrôle d'étape : renseignables dès l'Édition (avant toute certification) et
     * **effaçables** par le formaliste une fois le dossier passé en Formalités. Une date
     * de signature est un fait à constater au moment où il a lieu, pas un champ de
     * formulaire modifiable à volonté.
     */
    public function enregistrerSignatures(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($dossier->etape !== EtapeDossier::Signature) return false;
        if ($user->hasRole(RoleUtilisateur::Administrateur)) return true;

        return $dossier->redacteur_id === $user->id
            || $dossier->notaire_id === $user->id;
    }

    public function gererFormalites(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($this->estFige($dossier)) return false;
        if (!in_array($dossier->etape, [EtapeDossier::Formalites, EtapeDossier::Expedition], true)) return false;

        return $user->hasRole(RoleUtilisateur::Administrateur)
            || $dossier->formaliste_id === $user->id
            || $dossier->notaire_id === $user->id;
    }

    public function gererFacturation(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        // Cette ability n'avait aucun contrôle d'étape : un paiement pouvait être
        // enregistré sur un dossier déjà clôturé, ce qui est une anomalie comptable.
        if ($this->estFige($dossier)) return false;

        return $user->hasAnyRole(RoleUtilisateur::peuventGererFacturation());
    }

    /**
     * Contrôle de l'inventaire de clôture : cocher/décocher une pièce comme vérifiée, et
     * déposer la version signée/cachetée d'un document (retour du circuit papier réel).
     * Restreint au Notaire (certifie l'acte) et au Formaliste (gère matériellement
     * l'aller-retour physique), pas au Rédacteur/Réviseur.
     *
     * Deux corrections apportées le 2026-08-04 :
     *
     * 1. L'étape `Cloture` était autorisée : une fois le dossier clôturé, on pouvait
     *    encore retirer la vérification d'une pièce ou déposer une nouvelle version
     *    signée — exactement l'altération silencieuse qu'une clôture doit interdire.
     *    Le contrôle a lieu à l'Expédition, pas après.
     *
     * 2. Le raccourci administrateur précédait le contrôle d'étape : un administrateur
     *    pouvait donc vérifier des pièces sur un dossier encore en Édition. L'ordre est
     *    désormais celui de `gererFormalites()` et `genererCourriers()` — l'étape
     *    d'abord, le rôle ensuite.
     */
    public function cloturerDocuments(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($dossier->etape !== EtapeDossier::Expedition) return false;
        if ($user->hasRole(RoleUtilisateur::Administrateur)) return true;

        return $dossier->notaire_id === $user->id || $dossier->formaliste_id === $user->id;
    }

    public function genererCourriers(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($dossier->etape !== EtapeDossier::Expedition) return false;

        return $user->hasRole(RoleUtilisateur::Administrateur)
            || $dossier->formaliste_id === $user->id
            || $dossier->notaire_id === $user->id;
    }

    private function estAssigne(User $user, Dossier $dossier): bool
    {
        return $user->hasRole(RoleUtilisateur::Administrateur)
            || $user->hasRole(RoleUtilisateur::Comptable)
            || $dossier->redacteur_id === $user->id
            || $dossier->reviseur_id === $user->id
            || $dossier->notaire_id === $user->id
            || $dossier->formaliste_id === $user->id;
    }
}
