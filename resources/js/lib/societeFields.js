// Pont entre une fiche Societe (registre réutilisable, table `societes`) et les clés
// `soc.*` du questionnaire. Exactement le rôle que `clientFields.js` tient pour les
// personnes physiques et morales.
//
// ⚠️ Miroir de `Societe::CHAMPS_QUESTIONNAIRE` (PHP) — les deux doivent évoluer ensemble.
// Une clé oubliée ici affiche la bonne fiche à l'écran mais laisse la balise `${soc.*}`
// vide dans les actes générés ; oubliée côté PHP, la fiche du registre ne se met plus à
// jour quand la modification devient effective.

import { isoDateToFR } from '@/lib/dates';

const CHAMPS = {
    'soc.denomination': 'denomination',
    'soc.forme': 'forme',
    'soc.sigle': 'sigle',
    'soc.capital_chiffres': 'capital_chiffres',
    'soc.nombre_parts': 'nombre_parts',
    'soc.valeur_nominale_chiffres': 'valeur_nominale_chiffres',
    'soc.siege_quartier': 'siege_quartier',
    'soc.siege_commune': 'siege_commune',
    'soc.siege_ville': 'siege_ville',
    'soc.email_societe': 'email_societe',
    'soc.telephone_societe': 'telephone_societe',
    'soc.objet_social': 'objet_social',
    'soc.duree': 'duree',
    'soc.rccm': 'rccm_numero',
    'soc.nif': 'nif',
    'soc.date_constitution': 'date_constitution',
};

/** Suffixes portés par la fiche société — masqués en saisie dès qu'une fiche est rattachée. */
const SUFFIXES_SOCIETE = new Set(Object.keys(CHAMPS).map(k => k.slice('soc.'.length)));

/**
 * Ce champ `soc.*` est-il porté par la fiche du registre, donc **masqué** en saisie dès qu'une
 * société est rattachée ?
 *
 * `soc.gerant_actuel` ne l'est pas, et c'est voulu : il est bien **prérempli** depuis le registre
 * (voir `mapSocieteToQuestionnaire`), mais il reste **visible et modifiable**. Deux raisons — le
 * dirigeant est calculé (json `direction`, ou parties du dossier d'origine) et non lu dans une
 * colonne ; et l'information peut être périmée ou simplement inconnue pour une société que l'étude
 * n'a pas constituée. Même traitement que le nombre de parts d'un associé, qui reste saisissable
 * alors que sa fiche client est liée (voir estChampIdentite dans clientFields.js).
 */
export function estChampSociete(fieldId) {
    if (!fieldId.startsWith('soc.')) return false;
    return SUFFIXES_SOCIETE.has(fieldId.slice('soc.'.length));
}

/**
 * La fiche rattachée renseigne-t-elle **effectivement** ce champ ?
 *
 * Distinction essentielle, et absente de la première version : « champ porté par la fiche » et
 * « champ renseigné dans la fiche » ne sont pas la même chose. `MICH SARL` est au registre sans
 * numéro RCCM ; masquer `soc.rccm` au motif que la fiche *pourrait* le porter rendait un champ
 * **obligatoire** impossible à saisir — le seul recours affiché étant de détacher la société, donc
 * de perdre tout le préremplissage pour une donnée manquante.
 *
 * Un champ que la fiche ne renseigne pas reste donc saisissable, et la saisie complète la fiche au
 * registre (voir `Societe::completerDepuisQuestionnaire()`) : sans quoi le dossier suivant
 * redemanderait la même information et le registre resterait indéfiniment incomplet.
 */
export function ficheRenseigneChamp(societe, fieldId) {
    if (!societe || !estChampSociete(fieldId)) return false;

    const valeur = societe[CHAMPS[fieldId]];

    return valeur !== null && valeur !== undefined && String(valeur).trim() !== '';
}

/**
 * Valeurs `soc.*` déduites d'une fiche société, prêtes à fusionner dans formValues.
 *
 * Les champs vides de la fiche ne sont pas projetés : écraser une saisie existante avec du
 * vide ferait perdre une information que l'utilisateur vient d'ajouter — même précaution que
 * `mapClientToPrefixedFields`. Les montants sont convertis en chaîne : `capital_chiffres`
 * arrive de l'API en `decimal:2` (« 50000000.00 »), forme que NumberField n'affiche pas
 * correctement.
 */
export function mapSocieteToQuestionnaire(societe) {
    if (!societe) return {};

    const valeurs = {};
    for (const [cle, colonne] of Object.entries(CHAMPS)) {
        const brute = societe[colonne];
        if (brute === null || brute === undefined || brute === '') continue;

        if (MONTANTS.has(colonne)) {
            valeurs[cle] = String(Math.round(Number(brute)));
        } else if (DATES.has(colonne)) {
            // La fiche vient de l'API, où une colonne castée est sérialisée en horodatage
            // (`2019-07-25T00:00:00.000000Z`). Recopiée telle quelle, cette valeur partait dans
            // l'acte authentique et laissait `${..._jma}` / `${..._lettres}` non dérivées, faute
            // d'être reconnue comme une date. `donnees` attend du JJ/MM/AAAA — voir lib/dates.js.
            valeurs[cle] = isoDateToFR(brute) || brute;
        } else {
            valeurs[cle] = brute;
        }
    }

    // Hors de CHAMPS, donc prérempli **sans** être masqué (voir estChampSociete). Le serveur le
    // calcule dans `Societe::gerantActuel()` : json `direction` s'il a été mis à jour par une
    // modification, sinon la partie dirigeante du dossier de constitution — gérant, président d'une
    // SAS, PCA d'une SA… Sans cette projection, le nom s'affichait dans « Personnes connues de cette
    // société » juste au-dessus, mais le champ restait à ressaisir à la main.
    if (societe.gerant_actuel) {
        valeurs['soc.gerant_actuel'] = societe.gerant_actuel;
    }

    return valeurs;
}

const MONTANTS = new Set(['capital_chiffres', 'valeur_nominale_chiffres']);

/** Miroir de `Societe::COLONNES_DATE` (PHP) — les deux doivent évoluer ensemble. */
const DATES = new Set(['date_constitution']);

/** Libellé court d'une société, pour un récapitulatif ou une puce. */
export function societeDisplayName(societe) {
    if (!societe) return '';
    return societe.nom_complet || societe.denomination || '';
}
