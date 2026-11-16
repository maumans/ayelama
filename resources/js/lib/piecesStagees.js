// Pièces choisies avant la création d'une personne : rester attachées à la **bonne** personne.
//
// Les pièces mises en attente (`stagedPieces`, fichiers de la session) et celles héritées d'un
// brouillon (`piecesBrouillon`) sont rangées par groupe :
//   • section scalaire : groupe = rôle (`gerant`), clé = catégorie (`cni`) ;
//   • bloc répétable   : groupe = id du bloc (`modif.souscripteurs`), clé = `{ligne}:{catégorie}` ;
//   • représentant d'une ligne de bloc : groupe = `{bloc}#repr`, même forme de clé.
//
// ⚠️ Défaut corrigé le 2026-11-16 (retour R06, « parfois ça passe pas ») : la clé d'un bloc porte
// l'**index** de la ligne, et rien ne la décalait quand une ligne était retirée. Retirer le
// premier souscripteur donnait la CNI de A à B, celle de B à C, et perdait celle de C — reproduit
// en rejouant `buildPartiesPayload()` dans la page. Une pièce d'identité rattachée à la mauvaise
// personne, sans aucun message.
//
// Ce module ne connaît ni rôle ni catégorie : il ne fait que de la tenue de clés, partagée par
// l'assistant de création et la modale « Modifier le questionnaire ».

/** Le groupe des pièces du représentant d'une ligne de bloc — détenteur unique du suffixe. */
export function groupeRepresentant(blocId) {
    return `${blocId}#repr`;
}

const ligneDe = (cle) => Number.parseInt(String(cle).split(':')[0], 10);

/**
 * Le groupe d'un bloc après le retrait de la ligne `idx` : ses pièces disparaissent, celles des
 * lignes suivantes remontent d'un cran — comme les lignes elles-mêmes.
 */
export function apresRetraitLigne(groupe = {}, idx) {
    const suivant = {};
    for (const [cle, valeur] of Object.entries(groupe)) {
        const ligne = ligneDe(cle);
        if (Number.isNaN(ligne) || ligne < idx) suivant[cle] = valeur;
        else if (ligne > idx) suivant[`${ligne - 1}:${String(cle).split(':').slice(1).join(':')}`] = valeur;
    }
    return suivant;
}

/** Le groupe d'un bloc sans les pièces de la ligne `idx` — la personne de cette ligne a changé. */
export function sansLigne(groupe = {}, idx) {
    return Object.fromEntries(Object.entries(groupe).filter(([cle]) => ligneDe(cle) !== idx));
}

/**
 * Applique une transformation aux groupes nommés d'un état `{ groupe: { clé: … } }`, en
 * supprimant les groupes devenus vides. Rend l'état inchangé (même référence) si rien ne bouge.
 */
export function transformerGroupes(etat, groupes, transformer) {
    let change = false;
    const suivant = { ...etat };
    for (const nom of groupes) {
        if (!etat[nom]) continue;
        const groupe = transformer(etat[nom]);
        change = true;
        if (Object.keys(groupe).length === 0) delete suivant[nom];
        else suivant[nom] = groupe;
    }
    return change ? suivant : etat;
}

/** Retire un groupe entier — la personne d'une section scalaire a changé. */
export function sansGroupe(etat, nom) {
    if (!etat[nom]) return etat;
    const suivant = { ...etat };
    delete suivant[nom];
    return suivant;
}
