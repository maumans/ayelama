// ─────────────────────────────────────────────────────────────────────────────
// Les valeurs d'un questionnaire de société qui se déduisent des autres.
//
// ⚠️ **Ce module existe parce que ces calculs vivaient dans `Dossiers/Create.jsx`, et
// nulle part ailleurs.** Le dépôt rend le même questionnaire par **trois** moteurs — la
// création, la modale d'édition de `Dossiers/Show.jsx`, et le formulaire public
// `Intake/Show.jsx`. Un dossier corrigé depuis la modale ne recalculait donc pas son capital
// après modification : les statuts mis à jour, le procès-verbal et la DNSV pouvaient porter
// trois chiffres différents, et rien ne le signalait.
//
// C'est la famille de défauts que `CLAUDE.md` nomme en premier, et elle était déjà installée.
//
// Fonctions **pures**, sans React : les trois moteurs les appellent, et
// `tools/verifier-sections-questionnaire.mjs` les charge hors navigateur pour les vérifier.
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Lit un nombre saisi au questionnaire.
 *
 * Les montants arrivent avec des espaces de groupement — insécables compris, que `Number()`
 * ne sait pas lire — et parfois une virgule décimale. Même nettoyage que `EtatSocieteService::nombre()`
 * côté PHP, pour que les deux lisent « 25 000 000 » de la même façon.
 */
export function nombre(valeur) {
    if (valeur === null || valeur === undefined || valeur === '') return 0;
    if (typeof valeur === 'number') return Number.isFinite(valeur) ? valeur : 0;

    const nettoye = String(valeur)
        .replace(/[\s  ]/g, '')
        .replace(',', '.');

    const n = parseFloat(nettoye);

    return Number.isFinite(n) ? n : 0;
}

/**
 * Le capital après une augmentation ou une diminution.
 *
 * Il n'est **jamais** saisi : il se déduit du capital actuel de la société et du montant de
 * l'opération. Le laisser en saisie libre laisserait passer une incohérence entre les statuts
 * mis à jour, le procès-verbal et la DNSV — trois documents qui doivent porter le même chiffre.
 *
 * Rend un objet ne contenant **que** les clés à écrire : un montant nul ou absent ne produit
 * rien, plutôt qu'un zéro qui effacerait une valeur déjà posée.
 *
 * @param {Record<string, unknown>} valeurs
 * @returns {Record<string, string>}
 */
export function capitauxApresModification(valeurs = {}) {
    const capital = nombre(valeurs['soc.capital_chiffres']);

    if (capital <= 0) return {};

    const calculs = [
        ['modif.augmentation_montant', 'modif.augmentation_capital_apres', 1],
        ['modif.diminution_montant', 'modif.diminution_capital_apres', -1],
    ];

    const resultat = {};

    for (const [champMontant, champApres, signe] of calculs) {
        const montant = nombre(valeurs[champMontant]);

        if (montant <= 0) continue;

        resultat[champApres] = String(Math.round(capital + signe * montant));
    }

    return resultat;
}

/**
 * Les clés que `capitauxApresModification` produit — pour que l'appelant sache quoi surveiller.
 *
 * Évite de recopier la liste des dépendances d'un `useEffect` dans trois fichiers, et surtout
 * d'en oublier une dans l'un des trois.
 */
export const CHAMPS_SOURCES_CAPITAL = [
    'soc.capital_chiffres',
    'modif.augmentation_montant',
    'modif.diminution_montant',
];

/**
 * Le nombre de parts **après** l'opération — le dénominateur des pourcentages.
 *
 * Ce n'est pas `soc.nombre_parts` : une augmentation en crée, une diminution en annule. Prendre
 * le nombre actuel ferait afficher des pourcentages qui ne totalisent pas 100 % dès qu'il y a
 * création de parts, et le clerc chercherait l'erreur dans sa saisie.
 *
 * Miroir de ce que fait `EtatSocieteService::projete()` côté PHP, aux mêmes clés.
 */
export function partsProjetees(valeurs = {}) {
    const actuelles = nombre(valeurs['soc.nombre_parts']);
    const creees    = nombre(valeurs['modif.augmentation_parts_nouvelles']);
    const annulees  = nombre(valeurs['modif.diminution_parts_annulees']);

    return Math.max(0, actuelles + creees - annulees);
}

/**
 * Renseigne le pourcentage de chaque ligne de répartition.
 *
 * ⚠️ **Le pourcentage n'est pas une donnée, c'est un quotient.** Le laisser saisir permettait à
 * l'acte d'annoncer 60 % quand les parts en disaient 55 — deux chiffres qui partent dans les
 * statuts mis à jour et le procès-verbal, sans que rien ne les confronte.
 *
 * ⚠️ **Rend le tableau d'origine si rien ne change.** L'effet qui appelle cette fonction écrit
 * dans le tableau qu'il lit : sans égalité par référence, il boucle indéfiniment.
 *
 * Une ligne sans parts saisies reste vide — afficher « 0 % » sur une ligne qu'on vient
 * d'ajouter laisserait croire à une valeur décidée.
 */
export function pourcentagesRepartition(lignes, partsTotales) {
    if (!Array.isArray(lignes) || lignes.length === 0) return lignes;

    let change = false;

    const suivant = lignes.map(ligne => {
        const parts = ligne?.parts_chiffres;
        const vide  = parts === '' || parts === null || parts === undefined;

        const voulu = vide || partsTotales <= 0
            ? ''
            : String(Math.round((nombre(parts) * 100 / partsTotales) * 100) / 100);

        if ((ligne?.pourcentage ?? '') === voulu) return ligne;

        change = true;

        return { ...ligne, pourcentage: voulu };
    });

    return change ? suivant : lignes;
}

/**
 * Applique un calcul à un jeu de valeurs, sans rien réécrire d'identique.
 *
 * ⚠️ La garde « ne rendre un nouvel objet que si quelque chose change » n'est pas une
 * optimisation : sans elle, un `setState` dans un `useEffect` qui dépend de ce même état
 * boucle indéfiniment.
 *
 * @returns {Record<string, unknown>} le même objet si rien ne bouge — l'appelant peut donc
 *                                    comparer par référence.
 */
export function appliquerCalculs(valeurs, calculees) {
    let change = false;
    const suivant = { ...valeurs };

    for (const [cle, valeur] of Object.entries(calculees)) {
        if (suivant[cle] !== valeur) {
            suivant[cle] = valeur;
            change = true;
        }
    }

    return change ? suivant : valeurs;
}
