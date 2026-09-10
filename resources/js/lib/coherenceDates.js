// Cohérence entre dates — un seul détenteur, appelé par la fiche client et par les questionnaires.
//
// Ces contrôles n'existaient que dans `ModalNouveauClient` : les 25 champs de date des
// questionnaires n'en avaient aucun, ni côté navigateur ni côté serveur. Un questionnaire pouvait
// donc porter une pièce expirant avant d'avoir été délivrée, ou une naissance dans le futur — et
// l'acte authentique le reprenait tel quel.
//
// ⚠️ **Accepte les deux formats du projet** (voir le contrat dans lib/dates.js) : `donnees` porte du
// `JJ/MM/AAAA`, les modales de fiche de l'ISO. Normaliser ici évite d'imposer un format à
// l'appelant, et évite surtout la comparaison de chaînes hétérogènes — c'est elle qui annonçait
// « pièce expirée » sur une pièce valable jusqu'en 2027.

import { frDateToISO, isoDateSeule } from '@/lib/dates';

/** Ramène une date à `AAAA-MM-JJ`, qu'elle arrive en français ou en ISO. `''` si illisible. */
export function versISO(valeur) {
    if (!valeur) return '';
    return isoDateSeule(valeur) || frDateToISO(valeur);
}

/** Aujourd'hui en `AAAA-MM-JJ`, pour des comparaisons de chaînes de longueur fixe. */
function aujourdhui() {
    const maintenant = new Date();
    const mois = String(maintenant.getMonth() + 1).padStart(2, '0');
    const jour = String(maintenant.getDate()).padStart(2, '0');

    return `${maintenant.getFullYear()}-${mois}-${jour}`;
}

/**
 * Les quatre contrôles d'un triplet d'état civil — naissance, délivrance, expiration.
 *
 * Chaque incohérence décrit une saisie **impossible**, jamais un simple avertissement : une pièce
 * expirée reste enregistrable (l'étude consigne la situation réelle du client, quitte à demander un
 * renouvellement), mais une pièce délivrée avant la naissance de son porteur est une erreur de
 * saisie.
 *
 * @returns {Array<{champ: string, message: string}>} vide si tout est cohérent
 */
export function incoherencesTriplet({ naissance, delivree, expire }, ids = {}) {
    const anomalies = [];
    const n = versISO(naissance);
    const d = versISO(delivree);
    const e = versISO(expire);
    const now = aujourdhui();

    if (n && n >= now) {
        anomalies.push({ champ: ids.naissance, message: 'La date de naissance ne peut pas être dans le futur.' });
    }

    if (n && n < '1900-01-01') {
        anomalies.push({ champ: ids.naissance, message: 'La date de naissance semble erronée (avant 1900).' });
    }

    if (d && d > now) {
        anomalies.push({ champ: ids.delivree, message: "La pièce ne peut pas avoir été délivrée dans le futur." });
    }

    if (n && d && d < n) {
        anomalies.push({ champ: ids.delivree, message: "La pièce ne peut pas avoir été délivrée avant la naissance du titulaire." });
    }

    if (d && e && e <= d) {
        anomalies.push({ champ: ids.expire, message: "L'expiration doit être postérieure à la délivrance." });
    }

    return anomalies;
}

/** Une date qui ne peut pas être dans le futur (constitution d'une société, par exemple). */
export function incoherencePassee(valeur, message) {
    const v = versISO(valeur);

    return v && v > aujourdhui() ? message : null;
}

/** Une date qui ne peut pas précéder une autre (un effet ne précède pas la décision). */
export function incoherenceOrdre(anterieure, posterieure, message) {
    const a = versISO(anterieure);
    const b = versISO(posterieure);

    return a && b && b < a ? message : null;
}
