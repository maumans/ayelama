<?php

namespace App\Contracts;

use App\Enums\TypeAssemblee;

/**
 * Une opération du **catalogue** des interventions sur une société — addendum v1.2 § 5.
 *
 * Les onze opérations de la spec vivent dans deux enums déjà existants : les neuf
 * modifications statutaires ({@see \App\Enums\TypeModificationStatutaire}) et les deux phases
 * de dissolution ({@see \App\Enums\VarianteDissolution}). Ce contrat dit ce qu'elles ont en
 * commun, pour que le catalogue se construise sans savoir de laquelle il parle.
 *
 * ⚠️ **Séparé de {@see VarianteTypeActe}, et pas ajouté dedans.** Une vente ou un bail peuvent
 * un jour avoir des variantes ; elles n'auront jamais d'assemblée générale ni de publication au
 * journal d'annonces légales. Charger l'interface générique de notions propres aux sociétés
 * aurait forcé ces variantes-là à répondre à des questions qui ne les concernent pas.
 *
 * ⚠️ **Ces méthodes sont la référence, pas la configuration.** La spec veut que toute règle
 * métier soit modifiable sans redéploiement (test T13) ; le dépôt veut des `match` exhaustifs
 * qui échouent à l'ajout d'un cas. Les deux tiennent : l'enum porte la règle **sourcée et
 * datée**, `ReglesGestionOperationsSeeder` en dépose une copie éditable dans
 * `operations_societe`, et {@see \App\Models\OperationSociete::divergeDeLaReference()} signale
 * l'écart. C'est le motif déjà éprouvé par `documents_attendus`.
 */
interface OperationDeSociete extends VarianteTypeActe
{
    /**
     * Le code de la spécification — `MOD_SIEGE`, `AUG_CAPITAL`, `DISSOLUTION`…
     *
     * Distinct de `valeur()`, qui reste la valeur technique du dépôt (`siege_social`). Les deux
     * cohabitent délibérément : la base et les écrans continuent de parler français, et le
     * code de la spec sert aux échanges avec Addvalis et à la relecture lot par lot. Les
     * confondre aurait imposé une migration de données pour une question de vocabulaire.
     */
    public function codeOperation(): string;

    /** L'assemblée que cette opération exige. */
    public function assemblee(): TypeAssemblee;

    /** Modifie-t-elle les statuts — donc faut-il produire des statuts mis à jour ? */
    public function impacteStatuts(): bool;

    /** Modifie-t-elle l'inscription au RCCM — donc faut-il une formalité au greffe ? */
    public function impacteRccm(): bool;

    /** Impose-t-elle une publication au journal d'annonces légales ? */
    public function publicationJal(): bool;

    /**
     * Ne se combine avec aucune autre opération dans le même dossier — règle C1.
     *
     * À distinguer de `incompatiblesAvec()`, qui désigne des **paires** interdites (augmenter
     * et diminuer le capital). L'exclusivité est absolue : une dissolution ne se décide pas en
     * même temps qu'un transfert de siège.
     */
    public function exclusive(): bool;

    /**
     * La règle est-elle validée par l'étude, ou déduite en attendant sa confirmation ?
     *
     * La spec marque chaque ligne ✅ ou ⚠. Une règle déduite doit se voir à l'écran
     * d'administration : l'étude saura qu'elle peut la corriger sans crainte de contredire une
     * décision. Reprendre le marqueur plutôt que l'oublier, c'est la même exigence que
     * `JalonLiquidation::aVerifier()`.
     */
    public function regleValidee(): bool;
}
