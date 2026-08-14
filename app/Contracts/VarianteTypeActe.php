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
 * Implémenté par des enums : {@see \App\Enums\TypeModificationStatutaire}. Le lien code de type
 * d'acte → enum vit dans {@see \App\Support\VariantesTypeActe}, seul endroit à modifier pour
 * déclarer une nouvelle catégorie déclinée.
 */
interface VarianteTypeActe
{
    /** Valeur technique stockée en base (colonne `variante`). */
    public function valeur(): string;

    /** Libellé affiché à l'administrateur. */
    public function label(): string;
}
