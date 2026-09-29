<?php

namespace App\Contracts;

/**
 * Variante d'un type d'acte — une déclinaison du même processus.
 *
 * La société est le cas qui l'a rendu nécessaire : sa catégorie porte **trois** procédures
 * (création, modification, dissolution), et la modification se décline elle-même en sept
 * résolutions. Les actes se recoupent partiellement — un procès-verbal sert toutes les
 * modifications, un acte de cession ne sert qu'aux cessions —, et rien ne permettait de le
 * déclarer : un gabarit ne se rattachait qu'à un type d'acte entier.
 *
 * Une hypothèque, une vente ou un bail n'ont pas de variante ; ils n'implémentent donc rien et
 * conservent exactement le comportement d'avant.
 *
 * ⚠️ Ce n'est **pas** un type d'acte de plus. Éclater `SOC-MOD` en sept entrées de nomenclature
 * aurait contredit une décision déjà validée (« la variante est une donnée du dossier ») et cassé
 * le multi-modifications : une assemblée qui décide une cession *et* un transfert de siège doit
 * tenir dans un seul dossier.
 *
 * Implémenté par des enums : {@see \App\Enums\TypeModificationStatutaire},
 * {@see \App\Enums\VarianteDissolution}. Le lien code de type d'acte → enum vit dans
 * {@see \App\Support\VariantesTypeActe}, seul endroit à modifier pour déclarer une nouvelle
 * catégorie déclinée.
 *
 * Les trois méthodes ajoutées le 2026-09-28 (`clesQuestionnaire`, `depuisDonnees`,
 * `documentsReference`) ont supprimé une duplication mesurée : `donnees['modif.types'] ??
 * donnees['modif.type']` était recopié dans **cinq** lecteurs — ActesGeneratorService,
 * DossierController, Bareme, ReglesSocieteService, SocieteMutationService — et deux d'entre eux
 * portaient en plus un `!== 'SOC-MOD'` codé en dur. Ajouter une seconde catégorie déclinée
 * revenait donc à retrouver sept endroits, dont aucun n'échouait si on en oubliait un.
 */
interface VarianteTypeActe
{
    /** Valeur technique stockée en base (colonne `variante`). */
    public function valeur(): string;

    /** Libellé affiché à l'administrateur. */
    public function label(): string;

    /**
     * Clés de `questionnaires.donnees` portant la sélection, **par ordre de repli**.
     *
     * Plusieurs clés quand une migration a renommé le champ et que les brouillons antérieurs
     * doivent rester lisibles : refuser l'ancienne forme bloquerait le dossier sans recours.
     *
     * @return array<int, string>
     */
    public static function clesQuestionnaire(): array;

    /**
     * Variantes sélectionnées, depuis la valeur brute lue dans `donnees`.
     *
     * Toujours un tableau, y compris pour un type à sélection unique : la plomberie appelante
     * est commune. Les valeurs non reconnues sont **ignorées**, jamais fatales — un libellé mal
     * orthographié doit remonter comme « variante manquante » par
     * {@see \App\Services\ReglesSocieteService}, message actionnable, et non comme une erreur
     * d'enum au milieu d'une génération d'actes.
     *
     * @return array<int, static>
     */
    public static function depuisDonnees(mixed $valeur): array;

    /**
     * Documents imposés par une **référence écrite** de l'étude — `null` s'il n'en existe pas.
     *
     * La distinction est structurante. `null` ne veut pas dire « aucun document » : il veut
     * dire « aucune règle écrite ne dit lesquels », et l'application retombe alors sur les
     * gabarits effectivement rattachés — le comportement des créations, ventes et baux.
     *
     * Rendre une liste inventée serait pire que rendre `null` : l'écran Processus l'afficherait
     * comme faisant foi et `DocumentAttendu::divergeDeLaReference()` la défendrait contre les
     * corrections de l'étude.
     *
     * @return array<string, string>|null slug de `modeles_actes.type_document` => libellé
     */
    public function documentsReference(): ?array;
}
