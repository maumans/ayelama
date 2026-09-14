// Le référentiel des lieux, chargé **une seule fois** et partagé par tous les champs de la page.
//
// ⚠️ **Le défaut corrigé n'était pas un volume, mais une multiplication.** `LieuSelect` interrogeait
// le serveur *par champ et par changement de parent*. Sur le questionnaire de modification, qui
// porte **18 champs géographiques**, cela faisait jusqu'à 18 requêtes à l'ouverture, puis une de
// plus à chaque choix de ville ou de commune — d'où le « — Choisir — » suivi d'une attente, ressenti
// comme une lenteur de l'application.
//
// Le référentiel entier pèse 5 Ko aujourd'hui, et moins de 15 Ko compressé même à 2 400 lieux : le
// transférer une fois coûte moins que dix-huit allers-retours. Et le gain sera **plus** net en
// production qu'en local, puisque c'est la latence par requête qui domine, et qu'il y en avait dix-huit.
//
// Une promesse au niveau du module, pas un état React : le premier champ monté déclenche l'appel,
// tous les autres attendent la même promesse. Après résolution, chaque niveau de la cascade se
// résout **de mémoire, synchronement**.

import axios from 'axios';

let promesse = null;
let referentiel = null;

/**
 * Charge le référentiel si ce n'est pas déjà fait, et rend la même promesse à tous les appelants.
 *
 * @returns {Promise<Record<string, Array<{nom: string, parent: ?string}>>>}
 */
export function chargerReferentiel() {
    if (referentiel) return Promise.resolve(referentiel);
    if (promesse) return promesse;

    promesse = axios
        .get('/lieux/referentiel')
        .then(({ data }) => {
            referentiel = data ?? {};
            return referentiel;
        })
        .catch((err) => {
            // La promesse est libérée pour qu'un champ monté plus tard puisse réessayer : la
            // mémoriser en échec condamnerait la cascade pour toute la durée de la page.
            promesse = null;
            throw err;
        });

    return promesse;
}

/**
 * Les lieux d'un niveau sous un parent donné, **déjà chargés**.
 *
 * `null` tant que le référentiel n'est pas arrivé — l'appelant distingue ainsi « pas encore chargé »
 * de « chargé, et vide », deux états que le champ n'annonce pas de la même façon : le second dit
 * « aucun quartier au référentiel » et met le bouton d'ajout en évidence.
 *
 * @returns {Array<{nom: string, a_verifier?: boolean}>|null}
 */
export function lieuxDe(niveau, parentNom = null) {
    if (!referentiel) return null;

    const tous = referentiel[niveau] ?? [];

    // Un niveau racine (`ville`) n'a pas de parent : on ne filtre pas, sinon la liste serait vide.
    if (!parentNom) return niveau === 'ville' ? tous : [];

    return tous.filter(l => l.parent === parentNom);
}

/**
 * Périme le référentiel en mémoire — appelé après l'ajout d'un lieu.
 *
 * Sans cela, un quartier créé depuis un champ n'apparaissait que dans **ce** champ : les dix-sept
 * autres du même formulaire continuaient de servir la liste d'avant.
 */
export function invaliderReferentiel() {
    promesse = null;
    referentiel = null;
}

/** Le référentiel est-il déjà en mémoire ? (tests et affichage de l'état de chargement) */
export function referentielCharge() {
    return referentiel !== null;
}
