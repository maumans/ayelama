<?php

namespace App\Contracts;

use App\Models\Dossier;
use App\Models\User;

/**
 * Effet qu'un dossier produit sur la fiche société du registre, à son entrée en **Expédition**.
 *
 * Un seul effet existait jusqu'au 2026-09-28 — {@see \App\Services\SocieteMutationService},
 * appelé en dur depuis `DossierStepService`. Le second (le cycle de vie d'une dissolution)
 * posait la question de savoir comment les départager.
 *
 * **Pourquoi un registre et non un `match`.** Un `match` sur `$dossier->typeActe->code` porte
 * sur une chaîne de base de données, pas sur un enum : il exigerait une branche par défaut,
 * exactement ce que la doctrine du dépôt refuse (« un `match` exhaustif sans branche par
 * défaut, qui échoue à l'ajout d'un cas »). Un registre, lui, se lit d'un coup d'œil et chaque
 * effet porte sa propre condition d'application — celle-ci reste donc à côté du code qui
 * l'exploite, jamais dans un aiguillage distant.
 *
 * **Le moment** n'est pas un détail : ni avant les Formalités (la décision n'est pas opposable,
 * inscrire au registre un changement non enregistré au RCCM serait faux), ni à la Clôture (le
 * dossier y est figé, et il peut rester des semaines en Expédition pendant lesquelles la fiche
 * mentirait).
 *
 * **Idempotence obligatoire.** Un dossier renvoyé en correction puis ré-avancé repasse en
 * Expédition : `appliquer()` doit pouvoir tourner deux fois sans doubler son effet.
 */
interface EffetSurLaFicheSociete
{
    /**
     * Ce dossier est-il concerné par cet effet ?
     *
     * Chaque effet juge lui-même. Une condition fausse doit rendre `false` plutôt que laisser
     * `appliquer()` ne rien faire : le registre peut alors dire ce qui s'est appliqué et ce qui
     * ne s'est pas appliqué, au lieu de constater un tableau vide sans savoir pourquoi.
     */
    public function concerne(Dossier $dossier): bool;

    /**
     * Porte l'effet à la fiche. **Idempotent.**
     *
     * @return array<string, array{avant: mixed, apres: mixed}> champs effectivement modifiés,
     *                                                          pour la journalisation
     */
    public function appliquer(Dossier $dossier, ?User $user = null): array;

    /**
     * Défait l'effet, si c'est possible **sans risque d'écraser une correction**.
     *
     * Le retour arrière est volontairement asymétrique d'un effet à l'autre, et ce n'est pas
     * une inconséquence : pour un cycle de vie, l'état précédent est déductible et le revert
     * est sûr ; pour une modification statutaire, une correction faite entre-temps au registre
     * serait écrasée sans trace. Dans ce second cas l'effet ne revert pas, il **journalise un
     * avertissement** — un défaut bruyant vaut mieux qu'un revert hasardeux.
     *
     * @return array<string, array{avant: mixed, apres: mixed}>
     */
    public function annuler(Dossier $dossier, ?User $user = null): array;

    /** Nom court de l'effet, pour la journalisation. */
    public function nom(): string;
}
