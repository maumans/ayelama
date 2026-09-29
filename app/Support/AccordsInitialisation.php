<?php

namespace App\Support;

use App\Enums\ExigenceAccord;
use App\Models\TypeActe;

/**
 * Registre de la **pièce d'accord** attendue à l'Initialisation, par type d'acte.
 *
 * **Seul endroit à modifier** pour déclarer qu'un type d'acte attend autre chose — ou n'attend
 * rien. L'écran du dossier, le contrôle bloquant et l'indicateur de la liste suivent sans code
 * supplémentaire.
 *
 * ⚠️ **Un registre à repli, pas un `match` exhaustif.** Les types d'acte sont des *données* :
 * ils se créent depuis Paramètres et dans les tests (`TypeActe::create(['code' => 'TST-1234'])`).
 * Un `match` sans branche par défaut exploserait au premier type créé, ce qui serait bruyant au
 * mauvais endroit — l'administrateur n'a pas à toucher du code pour ouvrir une nomenclature.
 * Même forme que {@see VariantesTypeActe::REGISTRE}, où un type absent n'a simplement pas de
 * variantes.
 *
 * Le repli est **exactement le comportement d'avant le 2026-09-28** : accord client bloquant,
 * fiche imprimable. Un type non déclaré ici ne change donc pas de comportement, et c'est ce qui
 * rend cette passe sûre pour les 22 types qu'elle ne nomme pas.
 *
 * **Deux sources, dans cet ordre** : la colonne `types_actes.exigence_accord` si l'étude a pris
 * la main depuis Paramètres, puis la référence déclarée ici. `null` en base signifie « suivre la
 * référence » — et non « pas d'exigence » —, ce qui permet de distinguer une étude qui n'a rien
 * décidé d'une étude qui a décidé la même chose, donc de signaler un écart.
 */
final class AccordsInitialisation
{
    /**
     * Ce que l'application attend quand rien n'est déclaré : le comportement historique.
     *
     * @return array<string, mixed>
     */
    private static function defaut(): array
    {
        return [
            'categorie'    => 'accord_client',
            'nom'          => 'Accord client — questionnaire signé',
            'titre'        => 'Accord client sur le questionnaire',
            'instructions' => 'Imprimez la fiche dossier, faites-la signer par le client, puis téléversez ici le document signé.',
            'imprimable'   => true,
            'exigence'     => ExigenceAccord::Bloquante,
            'aVerifier'    => false,
            // D'où vient l'exigence, quand elle n'est pas garantie — patron de
            // `JalonLiquidation::source()`. Vide tant que rien n'est à signaler.
            'source'       => '',
        ];
    }

    /**
     * Dérogations déclarées, par code de type d'acte.
     *
     * ⚠️ La `categorie` reste `accord_client` dans **tous** les cas, délibérément : c'est le
     * créneau technique de cette pièce. En introduire une seconde obligerait à la classer dans
     * {@see \App\Enums\RubriqueCloture::pourDocument()}, à l'exclure de l'onglet Actes et à
     * doubler le contrôle bloquant. C'est le **nom** qui porte le sens.
     *
     * @return array<string, array<string, mixed>>
     */
    private static function derogations(): array
    {
        return [
            // Modification de statuts — comportement inchangé depuis son introduction.
            //
            // Ce qui engage l'opération est la décision des associés, que le client apporte ; le
            // procès-verbal que l'étude rédige n'arrive qu'à l'Édition, trop tard pour garder
            // l'Initialisation. Faire imprimer une fiche de recueil n'aurait donc pas de sens.
            'SOC-MOD' => [
                'nom'          => "Décision d'assemblée des associés",
                'titre'        => "Décision d'assemblée des associés",
                'instructions' => 'Téléversez la décision écrite des associés qui engage cette modification — convocation, projet de résolution, ou procès-verbal remis par le client.',
                'imprimable'   => false,
                'exigence'     => ExigenceAccord::Bloquante,
            ],

            // Dissolution-liquidation — **provisoire, à confirmer avec l'étude** (2026-09-28).
            //
            // Le cadrage est celui de la modification, et le raisonnement se transpose mot pour
            // mot : une dissolution est décidée en assemblée, et le PV que l'étude rédige est
            // l'un de ses quatre gabarits attendus — l'exiger avant l'Édition serait circulaire.
            //
            // Mais **non bloquant**, et c'est le point. Aucune source versée au projet ne dit ce
            // qu'exige une dissolution à l'Initialisation : le compte rendu de juillet 2026 ne
            // mentionne ni dissolution, ni liquidation, ni radiation. Bloquer sur une pièce dont
            // personne n'a établi la nécessité empêcherait l'étude de traiter un dossier
            // parfaitement régulier — une contrainte inventée est pire qu'une contrainte absente.
            //
            // `aVerifier` fait dire à l'écran que l'attente n'est pas arbitrée, et un test exige
            // que ce marqueur reste posé tant que l'étude n'a pas tranché.
            'SOC-DIS' => [
                'nom'          => "Décision d'assemblée des associés",
                'titre'        => "Décision de dissolution",
                'instructions' => "Si le client apporte la décision écrite des associés prononçant la dissolution, téléversez-la ici : elle sera reprise dans l'acte.",
                'source'       => "Cadrage repris de la modification de statuts, faute de source propre à la dissolution : le compte rendu de juillet 2026 ne mentionne ni dissolution, ni liquidation, ni radiation. À confirmer avec l'étude.",
                'imprimable'   => false,
                'exigence'     => ExigenceAccord::Attendue,
                'aVerifier'    => true,
            ],
        ];
    }

    /**
     * Pièce d'accord de référence pour ce code de type d'acte, **hors surcharge**.
     *
     * @return array<string, mixed>
     */
    public static function reference(?string $codeTypeActe): array
    {
        return self::composer([...self::defaut(), ...(self::derogations()[$codeTypeActe] ?? [])]);
    }

    /**
     * Ajoute aux instructions la conséquence du niveau d'exigence.
     *
     * ⚠️ La conséquence **n'est jamais écrite dans le registre**. Les deux libellés historiques
     * se terminaient par « Le dossier ne pourra pas passer en certification sans elle » : la
     * conséquence y était encodée en dur, si bien qu'un basculement sur « Recommandée » depuis
     * Paramètres aurait laissé une phrase fausse à l'écran, sans que rien ne le signale.
     * Composée ici, elle ne peut plus survivre à un changement de niveau.
     *
     * @param  array<string, mixed> $attendue
     * @return array<string, mixed>
     */
    private static function composer(array $attendue): array
    {
        $consequence = $attendue['exigence']->consequence();

        $attendue['instructions'] = trim($attendue['instructions'] . ' ' . $consequence);

        return $attendue;
    }

    /**
     * Pièce d'accord effective : la référence, corrigée par la surcharge de l'étude s'il y en a.
     *
     * @return array<string, mixed>
     */
    public static function pour(?TypeActe $typeActe): array
    {
        $attendue = self::reference($typeActe?->code);
        $surcharge = self::surchargeDe($typeActe);

        if ($surcharge !== null) {
            // ⚠️ On repart de la référence **non composée** : recomposer par-dessus une phrase de
            // conséquence déjà ajoutée la concaténerait à la nouvelle. La composition n'est donc
            // appliquée qu'une fois, ici, après avoir fixé le niveau définitif.
            $attendue = [...self::defaut(), ...(self::derogations()[$typeActe?->code] ?? [])];
            $attendue['exigence'] = $surcharge;
            // Une exigence posée à la main par l'étude n'est plus une hypothèse : le marqueur
            // « à vérifier » tombe et sa justification avec, sans quoi l'écran continuerait
            // d'excuser une décision prise.
            $attendue['aVerifier'] = false;
            $attendue['source']    = '';

            return self::composer($attendue);
        }

        return $attendue;
    }

    /** Surcharge déclarée par l'étude pour ce type d'acte, ou `null` si elle suit la référence. */
    public static function surchargeDe(?TypeActe $typeActe): ?ExigenceAccord
    {
        return $typeActe?->exigence_accord;
    }

    /**
     * La configuration de l'étude s'écarte-t-elle de la référence ?
     *
     * Même parti que {@see \App\Models\DocumentAttendu::divergeDeLaReference()} : un écart n'est
     * pas une faute, mais il doit se voir — c'est cette ligne qui décide si un dossier avance.
     */
    public static function divergeDeLaReference(?TypeActe $typeActe): bool
    {
        $surcharge = self::surchargeDe($typeActe);

        return $surcharge !== null
            && $surcharge !== self::reference($typeActe?->code)['exigence'];
    }

    /** Codes de type d'acte qui dérogent au défaut — pour les tests et l'écran de configuration. */
    public static function codesDerogeants(): array
    {
        return array_keys(self::derogations());
    }
}
