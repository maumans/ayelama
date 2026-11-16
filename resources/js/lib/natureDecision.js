// « Nature de la décision » d'un PV de modification, déduite pour être **préremplie** (retour R05,
// 2026-11-16). Miroir de `App\Enums\TypeAssemblee::natureDeduite()`, qui compose à partir des
// mêmes données servies (`reglesNatureDecision()`, prop `natureDecision`) : ce module ne porte
// aucun libellé, aucune forme, aucun type d'opération en dur.
//
// Règle arbitrée par l'étude : forme unipersonnelle → décision de l'associé unique ; sinon AGE
// dès qu'une opération l'exige, AGO sinon.

const ORDRE = ['AGE', 'AGO'];

/**
 * @param {{libelles: object, formesAssocieUnique: string[], assembleeParType: object}} regles
 * @param {?string} forme        valeur de `soc.forme` (« SARLU »…)
 * @param {string[]} typesChoisis libellés de `modif.types`
 * @returns {?string} libellé d'option de `ag.type`, ou null s'il n'y a rien à déduire
 */
export function natureDecisionDeduite(regles, forme, typesChoisis = []) {
    if (!regles) return null;

    if (forme && regles.formesAssocieUnique?.includes(forme)) {
        return regles.libelles?.associe_unique ?? null;
    }

    const assemblees = (typesChoisis ?? []).map(t => regles.assembleeParType?.[t]).filter(Boolean);
    const retenue = ORDRE.find(a => assemblees.includes(a));

    return retenue ? (regles.libelles?.[retenue] ?? null) : null;
}

/**
 * Préremplir sans écraser : on n'écrit que si le champ est vide, ou s'il porte encore la dernière
 * valeur proposée — la personne qui a choisi autre chose garde son choix.
 *
 * @param {{current: ?string}} memoire  réf. React portant la dernière valeur proposée
 * @returns {?string} la valeur à écrire, ou null s'il n'y a rien à faire
 */
export function valeurAPreremplir(memoire, actuelle, proposee) {
    if (proposee === null || proposee === undefined || proposee === '') return null;
    const libre = actuelle === undefined || actuelle === null || actuelle === '' || actuelle === memoire.current;
    if (!libre) return null;
    memoire.current = proposee;

    return actuelle === proposee ? null : proposee;
}
