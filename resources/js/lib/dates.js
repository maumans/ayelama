// Deux formats de date coexistent dans l'application, chacun légitime à sa place. Les confondre a
// produit cinq défauts distincts (dont une inversion silencieuse du jour et du mois en base) : la
// règle est donc énoncée ici, une fois.
//
//   ┌──────────────────────────────────────────┬──────────────┬───────────────────────────────────┐
//   │ Destination                              │ Format       │ Pourquoi                          │
//   ├──────────────────────────────────────────┼──────────────┼───────────────────────────────────┤
//   │ questionnaires.donnees  (→ actes .docx)  │ JJ/MM/AAAA   │ injecté tel quel dans le document │
//   │ colonnes castées `date` (clients, …)     │ AAAA-MM-JJ   │ c'est ce que Carbon/MySQL lisent  │
//   └──────────────────────────────────────────┴──────────────┴───────────────────────────────────┘
//
// Conséquence pour un formulaire : **l'état porte le format de sa destination**, et ces helpers ne
// servent qu'à l'affichage. Un <input type="date"> natif exige un `value` ISO et rend de l'ISO ; une
// modale qui écrit dans une colonne castée garde donc de l'ISO dans son état (voir
// ModalDepotFormalite, ModalEnregistrerPaiement, Dossiers/Show), tandis qu'un questionnaire garde du
// français. Poster du français vers une colonne castée est le défaut à ne pas refaire : PHP y lit un
// mois/jour américain, et `01/04/1985` devient le 4 janvier sans la moindre erreur.

export function frDateToISO(value) {
    if (!value) return '';
    const m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(value).trim());
    if (!m) return '';
    const [, d, mo, y] = m;
    return `${y}-${mo}-${d}`;
}

/**
 * Accepte `AAAA-MM-JJ` **et** l'horodatage complet que Laravel sérialise pour une colonne castée
 * (`1985-01-04T00:00:00.000000Z`).
 *
 * Cette tolérance n'est pas un confort : sans elle, une fiche client relue depuis l'API affichait
 * ses trois champs de date **vides** alors qu'elle portait des valeurs — le format exact ne
 * correspondait pas, et la fonction rendait ''.
 */
export function isoDateToFR(value) {
    if (!value) return '';
    const m = /^(\d{4})-(\d{2})-(\d{2})(?:[T ]|$)/.exec(String(value).trim());
    if (!m) return '';
    const [, y, mo, d] = m;
    return `${d}/${mo}/${y}`;
}

/**
 * Ramène à `AAAA-MM-JJ` une valeur déjà ISO, horodatage compris — pour homogénéiser l'état d'un
 * formulaire alimenté par l'API, dont les comparaisons de dates supposent une longueur fixe.
 */
export function isoDateSeule(value) {
    if (!value) return '';
    const m = /^(\d{4}-\d{2}-\d{2})(?:[T ]|$)/.exec(String(value).trim());
    return m ? m[1] : '';
}

/**
 * Calcule l'année de clôture du 1er exercice social selon la date de création :
 * - Si créée entre le 01/01 et le 30/06 de l'année N (1er semestre) : clôture au 31 décembre de l'année N (ex. 2026).
 * - Sinon (créée entre le 01/07 et le 31/12 de l'année N) : clôture au 31 décembre de l'année N+1 (ex. 2027).
 *
 * @param {string|Date} [date] Date en JJ/MM/AAAA, AAAA-MM-JJ ou objet Date (défaut : date courante)
 * @returns {number} Année de clôture du premier exercice
 */
export function anneePremierExercice(date) {
    let mois = null;
    let annee = null;

    if (!date) {
        const now = new Date();
        mois = now.getMonth() + 1;
        annee = now.getFullYear();
    } else if (date instanceof Date) {
        mois = date.getMonth() + 1;
        annee = date.getFullYear();
    } else if (typeof date === 'string') {
        const trimmed = date.trim();
        const mFr = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(trimmed);
        if (mFr) {
            mois = parseInt(mFr[2], 10);
            annee = parseInt(mFr[3], 10);
        } else {
            const mIso = /^(\d{4})-(\d{2})-(\d{2})/.exec(trimmed);
            if (mIso) {
                annee = parseInt(mIso[1], 10);
                mois = parseInt(mIso[2], 10);
            }
        }
    }

    if (!mois || !annee) {
        const now = new Date();
        mois = now.getMonth() + 1;
        annee = now.getFullYear();
    }

    return mois <= 6 ? annee : annee + 1;
}

