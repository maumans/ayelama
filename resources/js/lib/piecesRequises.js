// Quelles pièces justificatives une personne du dossier doit-elle fournir ?
//
// La règle est **servie par le serveur** (`Partie::reglesPiecesRequises()`, prop `piecesRequises`)
// et seulement *lue* ici. Ce module ne connaît aucun rôle, aucun nom de jeu, aucune catégorie :
// trois lectures de dictionnaire, zéro connaissance métier. C'est ce qui permet à
// `PiecesRequisesParRoleTest` d'affirmer qu'aucune règle n'est réécrite côté JavaScript.
//
// Pourquoi ce module existe : la règle était recopiée à quatre endroits — RepeatableGroup.jsx,
// Create.jsx, Show.jsx, plus la normalisation physique/morale de partiesPayload.js — et les
// quatre copies avaient divergé. Mesuré le 2026-09-24 :
//   • RepeatableGroup ne couvrait que 3 des 7 rôles : cédant, cessionnaire, souscripteur et
//     gérant entrant n'affichaient aucune pièce avant création, alors que le serveur les exige
//     ensuite et bloque la sortie d'Initialisation ;
//   • Create.jsx et Show.jsx portaient une liste blanche de sept rôles (bailleur, locataire,
//     vendeur, acheteur, liquidateur, créancier, débiteur) dont aucun n'a de jeu déclaré côté
//     PHP — la checklist y était donc toujours vide, sans que rien ne le dise ;
//   • `show()` ne passait même pas la prop, si bien qu'ajouter une personne depuis la modale
//     d'édition n'offrait jamais de déposer ses pièces.
//
// ⚠️ Ce module n'est pas couvert par le contrôle de zone morte temporelle de
// `ListesMatrimonialesTest`, qui ne lit que `questionnaires.js`. Déclarer avant d'utiliser.

/**
 * Le questionnaire dit « Personne morale », la base dit `morale`.
 *
 * Seul point de traduction entre les deux vocabulaires. Il en existait deux, et rien ne
 * garantissait qu'ils restent d'accord.
 *
 * @param {string|null|undefined} valeur
 * @returns {'physique'|'morale'}
 */
export function typePersonneCanonique(valeur) {
    return valeur === 'Personne morale' || valeur === 'morale' ? 'morale' : 'physique';
}

/**
 * Les pièces exigées d'une personne, d'après les règles servies par le serveur.
 *
 * Renvoie `{}` — et non une erreur — pour un rôle sans jeu déclaré : c'est le cas légitime
 * des rôles de `Partie::ROLES_SANS_PIECES`. Le garde-fou contre l'oubli n'est pas ici mais
 * dans `PiecesRequisesParRoleTest`, qui exige que tout rôle du schéma soit classé côté serveur,
 * dans un jeu ou dans « sans pièces ». Un module de lecture ne peut pas distinguer les deux.
 *
 * @param {{jeux?: object, parRole?: object, moraleParRole?: object}} regles  prop `piecesRequises`
 * @param {{role?: string, typePersonne?: string}} personne
 * @returns {Record<string, string>} catégorie → libellé
 */
export function piecesRequisesPour(regles, { role, typePersonne } = {}) {
    if (!regles || !role) return {};

    const jeu = (typePersonneCanonique(typePersonne) === 'morale'
        ? regles.moraleParRole?.[role]
        : null) ?? regles.parRole?.[role];

    return jeu ? (regles.jeux?.[jeu] ?? {}) : {};
}
