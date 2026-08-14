// Logique d'exclusion mutuelle d'un champ à choix multiple (`checkbox_group`).
//
// Extraite du composant pour être vérifiable sans rendu React : ce sont trois fonctions pures,
// et c'est cette logique — pas la mise en forme — qui empêche de saisir deux modifications
// statutaires contradictoires.
//
// Forme de `exclusions` : { [libellé]: { [libellé exclu]: 'motif' } }

/**
 * Motif pour lequel une option est indisponible, ou `null`.
 *
 * ⚠️ **Une option déjà cochée n'est jamais bloquée.** Sans cette exception, une sélection portant
 * déjà deux options qui s'excluent — un dossier ou un brouillon antérieur à cette règle — serait un
 * cul-de-sac : chacune désactivant l'autre, aucune ne pourrait être décochée pour en sortir.
 */
export function motifBlocage(option, selection, exclusions = {}) {
    if (selection.includes(option)) return null;

    for (const choisie of selection) {
        const motif = exclusions[choisie]?.[option];
        if (motif) return motif;
    }
    return null;
}

/**
 * Conflits présents dans une sélection, dédupliqués (une paire, pas deux fois la même dans les deux
 * sens). Impossibles à créer depuis l'interface désormais — mais une sélection héritée peut en
 * porter, et l'utilisateur doit savoir laquelle décocher.
 *
 * @returns {Array<{paire: [string, string], motif: string}>}
 */
export function conflitsDansSelection(selection, exclusions = {}) {
    const conflits = [];

    for (const a of selection) {
        for (const b of selection) {
            if (a === b) continue;
            const motif = exclusions[a]?.[b];
            if (!motif) continue;
            if (conflits.some(c => c.paire.includes(a) && c.paire.includes(b))) continue;
            conflits.push({ paire: [a, b], motif });
        }
    }

    return conflits;
}

/**
 * Construit la table d'exclusions à partir de la prop `typesModification`
 * (`TypeModificationStatutaire::toutes()`).
 *
 * Les incompatibilités arrivent en **valeurs techniques** (`capital_diminution`) alors que le champ
 * stocke des **libellés** : la conversion se fait ici, depuis la même prop. La règle elle-même
 * n'est jamais redéclarée en JavaScript — elle vit dans l'enum, avec `impacteStatuts()` et
 * `exigeDnsv()`.
 */
export function tableExclusionsModification(typesModification) {
    const libelleParValeur = Object.fromEntries((typesModification ?? []).map(t => [t.valeur, t.label]));

    return Object.fromEntries((typesModification ?? []).map(t => [
        t.label,
        Object.fromEntries(
            (t.incompatibles ?? [])
                .filter(valeur => libelleParValeur[valeur])
                .map(valeur => [
                    libelleParValeur[valeur],
                    t.motifs?.[valeur] ?? 'Ces deux modifications sont incompatibles.',
                ]),
        ),
    ]));
}
