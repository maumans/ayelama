// Pont entre une fiche Client (personne physique/morale réutilisable, table `clients`)
// et les champs texte libres du questionnaire (préfixés pp./ger./acq./loc./bq./liquidateur.,
// ou non préfixés dans les blocs répétables). ActesGeneratorService reste 100% générique
// clé/valeur (voir app/Services/ActesGeneratorService.php) : tant que ces fonctions déposent
// les bonnes clés dans `donnees`, la génération de documents fonctionne sans aucun changement
// côté backend.

import { isoDateToFR } from '@/lib/dates';

function adresseComposite(client) {
    return [client.quartier, client.commune, client.demeurant_ville].filter(Boolean).join(', ');
}

export { adresseComposite };

// Suffixes de champ dont la valeur provient de la fiche client. Quand un rôle est
// rattaché à une fiche, ces champs sont masqués dans le formulaire : la fiche est
// la source de vérité, les réafficher en saisie libre recréerait la double vérité
// que cette refonte supprime.
//
// Miroir de ClientProjectionService::SUFFIXES_* (PHP) — les deux doivent évoluer
// ensemble ; ClientProjectionTest verrouille la correspondance côté serveur.
const SUFFIXES_IDENTITE = new Set([
    'civilite', 'prenom_nom', 'nom', 'ne_a', 'date_naissance', 'nationalite',
    // Règle 4 du CR juillet 2026 : nom de famille et prénoms séparés, pour pouvoir mettre
    // le nom en capitales dans les actes. `prenom_nom` reste projeté (accessor côté PHP)
    // car les 63 modèles Word non normalisés l'utilisent encore.
    'nom_famille', 'prenoms', 'identite_notariale',
    'situation_matrimoniale', 'regime_matrimonial',
    'piece_type', 'piece_numero', 'cni', 'piece_delivree_le', 'piece_delivree_a', 'piece_expire_le',
    'denomination', 'forme', 'rccm', 'representant_legal', 'representant_nom', 'representant_qualite',
    'quartier', 'commune', 'demeurant_ville', 'ville',
    'siege_quartier', 'siege_commune', 'siege_ville', 'siege',
    'pays', 'telephone', 'email', 'adresse', 'domicile', 'type_personne',
]);

/**
 * Ce champ est-il une donnée d'identité (portée par la fiche client) ou une donnée
 * propre à l'acte (qui doit rester saisissable même quand un client est lié) ?
 *
 * Restent saisissables : parts_chiffres, actions_chiffres, apport_chiffres,
 * fonction (au conseil d'administration), qualite (du liquidateur dans cet acte),
 * et tous les bq.* de crédit — la même personne peut détenir 100 parts dans une
 * société et 5 dans une autre.
 */
/**
 * Suffixes dont la valeur est une date, et qui doivent donc entrer dans `donnees` au format
 * **JJ/MM/AAAA** (voir le contrat dans lib/dates.js).
 *
 * La fiche client vient de l'API, où une colonne castée est sérialisée en horodatage
 * (`1970-02-05T00:00:00.000000Z`). Recopiée telle quelle, cette valeur partait **dans l'acte
 * authentique** — et comme `ActesGeneratorService` ne dérive `${..._jma}` et `${..._lettres}` que
 * depuis une date française, ces deux balises restaient en outre non produites.
 *
 * La conversion vit dans les helpers `set()` ci-dessous plutôt qu'à chaque appel : un suffixe de
 * date ajouté ensuite est ainsi couvert sans qu'on y pense.
 */
const SUFFIXES_DATE = new Set(['date_naissance', 'piece_delivree_le', 'piece_expire_le']);

const valeurQuestionnaire = (suffixe, valeur) =>
    SUFFIXES_DATE.has(suffixe) ? (isoDateToFR(valeur) || valeur) : valeur;

/**
 * Sous-espaces d'une section de personne — aujourd'hui le seul est celui du représentant.
 *
 * Déclaré une fois, et retiré avant toute consultation de `SUFFIXES_IDENTITE` : ajouter les
 * seize `repr_*` au Set serait la même duplication, en plus petit et plus facile à laisser
 * diverger. Miroir de ClientProjectionService::PREFIXE_REPRESENTANT.
 */
const SOUS_PREFIXES_PERSONNE = ['repr_'];

/** Le sous-préfixe d'un champ, et le suffixe qui reste une fois celui-ci retiré. */
function decomposer(fieldId) {
    const brut = fieldId.includes('.') ? fieldId.split('.').slice(1).join('.') : fieldId;
    const sousPrefixe = SOUS_PREFIXES_PERSONNE.find(p => brut.startsWith(p)) ?? '';

    return { sousPrefixe, suffixe: brut.slice(sousPrefixe.length) };
}

export function estChampIdentite(fieldId) {
    return SUFFIXES_IDENTITE.has(decomposer(fieldId).suffixe);
}

/**
 * Les suffixes portés par une fiche client, exposés en lecture.
 *
 * Consommé par `tools/generer-balises-resolvables.mjs`, qui doit déclarer le sous-espace du
 * représentant (`${pp.repr_*}`) comme résolvable : la projection y écrit **plus** de clés que le
 * schéma n'en déclare de champs — `repr_nom`, `repr_adresse`, `repr_identite_notariale`… —
 * et `verifier-balises.php` les dirait sinon inconnues, laissant croire à l'étude qu'elles
 * n'existent pas.
 *
 * Aucune duplication nouvelle : ce Set est déjà le miroir JS assumé de
 * ClientProjectionService::SUFFIXES_*, et ClientProjectionTest verrouille la correspondance.
 */
export function suffixesIdentite() {
    return [...SUFFIXES_IDENTITE];
}

/**
 * De quelle personne ce champ décrit-il l'identité ?
 *
 * `'partie'` ou `'representant'`. Nécessaire parce que masquer les champs d'identité « dès qu'un
 * client est lié » ne peut plus se décider globalement : rattacher une fiche au **représenté** ne
 * doit pas masquer les champs du **représentant**, qui n'en a pas encore.
 */
export function personneDuChamp(fieldId) {
    return decomposer(fieldId).sousPrefixe === 'repr_' ? 'representant' : 'partie';
}

/**
 * Ce champ appartient-il au mécanisme de représentation ?
 *
 * La case, le motif, le sous-espace du représentant et les caractéristiques du titre. Sert au
 * formulaire client public, qui doit les exclure en bloc — voir getPublicIntakeFields().
 */
export function estChampRepresentation(fieldId) {
    const brut = fieldId.includes('.') ? fieldId.split('.').slice(1).join('.') : fieldId;

    return brut === 'est_represente'
        || brut === 'representation_motif'
        || SOUS_PREFIXES_PERSONNE.some(p => brut.startsWith(p));
}

// Remplit les champs d'un bloc scalaire préfixé (ex. prefix='pp' → pp.civilite, pp.prenom_nom…)
// à partir d'un client. Ne renseigne que les champs qui existent réellement dans ce bloc
// (fieldIds) et pour lesquels le client a une valeur — pas d'écrasement avec du vide.
export function mapClientToPrefixedFields(client, prefix, fieldIds, sousPrefixe = '') {
    const idSet = new Set(fieldIds);
    const values = {};
    const set = (suffix, value) => {
        const id = `${prefix}.${sousPrefixe}${suffix}`;
        // ⚠️ `valeurQuestionnaire` reçoit le suffixe **nu**, pas la clé complète : elle
        // consulte SUFFIXES_DATE, où `repr_date_naissance` ne figure pas. Avec la clé
        // préfixée, la date repartirait en ISO dans un acte authentique — le défaut que
        // lib/dates.js documente en tête.
        if (idSet.has(id) && value !== null && value !== undefined && value !== '') {
            values[id] = valeurQuestionnaire(suffix, value);
        }
    };

    if (client.type === 'physique') {
        set('civilite', client.civilite);
        set('prenom_nom', client.prenom_nom);
        set('nom', client.prenom_nom);
        // Règle 4 : nom de famille en capitales, prénoms à part. Le serveur projette les
        // mêmes suffixes (ClientProjectionService) — ClientProjectionTest verrouille
        // l'équivalence entre les deux.
        set('nom_famille', (client.nom_famille ?? '').toUpperCase());
        set('prenoms', client.prenoms);
        set('identite_notariale', [(client.nom_famille ?? '').toUpperCase(), client.prenoms ?? ''].join(' ').trim());
        set('ne_a', client.ne_a);
        set('date_naissance', client.date_naissance);
        set('nationalite', client.nationalite);
        set('situation_matrimoniale', client.situation_matrimoniale);
        set('regime_matrimonial', client.regime_matrimonial);
        set('piece_type', client.piece_type);
        set('piece_numero', client.piece_numero);
        set('piece_delivree_le', client.piece_delivree_le);
        set('piece_delivree_a', client.piece_delivree_a);
        set('piece_expire_le', client.piece_expire_le);
    } else {
        set('civilite', 'Société');
        set('prenom_nom', client.denomination);
        set('nom', client.denomination);
        // Une personne morale n'a pas de nom de famille : la dénomination en tient lieu,
        // pour que ces suffixes ne restent pas en saisie libre si un schéma les contient.
        set('nom_famille', (client.denomination ?? '').toUpperCase());
        set('prenoms', '');
        set('identite_notariale', client.denomination);
        set('denomination', client.denomination);
        set('forme', client.forme);
        set('representant_nom', client.representant_legal);
        set('representant_legal', client.representant_legal);
        set('representant_qualite', client.representant_qualite);
        set('nationalite', client.pays);
        set('rccm', client.rccm);
    }

    set('quartier', client.quartier);
    set('commune', client.commune);
    set('demeurant_ville', client.demeurant_ville);
    set('ville', client.demeurant_ville);
    set('siege_quartier', client.quartier);
    set('siege_commune', client.commune);
    set('siege_ville', client.demeurant_ville);
    set('pays', client.pays);
    set('telephone', client.telephone);
    set('email', client.email);
    set('adresse', adresseComposite(client));
    set('siege', client.siege || adresseComposite(client));

    return values;
}

// Idem, mais pour un item de bloc répétable (associé, gérant, actionnaire…) dont les
// clés ne sont PAS préfixées (ex. { nom, nationalite, adresse, cni }).
export function mapClientToRepeatableItem(client, fieldIds, sousPrefixe = '') {
    const idSet = new Set(fieldIds);
    const item = {};
    const set = (suffixe, value) => {
        const id = `${sousPrefixe}${suffixe}`;
        // Même remarque que ci-dessus : le suffixe nu pour la conversion de date.
        if (idSet.has(id) && value !== null && value !== undefined && value !== '') {
            item[id] = valeurQuestionnaire(suffixe, value);
        }
    };

    const nom = client.type === 'physique' ? client.prenom_nom : client.denomination;
    const adresse = adresseComposite(client);

    set('nom', nom);
    set('prenom_nom', nom);
    set('civilite', client.type === 'physique' ? (client.civilite || '') : 'Société');
    set('type_personne', client.type === 'physique' ? 'Personne physique' : 'Personne morale');
    set('nationalite', client.type === 'physique' ? client.nationalite : client.pays);
    set('adresse', adresse);
    set('domicile', adresse);
    set('cni', client.type === 'physique' ? client.piece_numero : client.rccm);
    set('piece_numero', client.type === 'physique' ? client.piece_numero : client.rccm);
    set('piece_type', client.piece_type);
    set('forme', client.forme);
    set('rccm', client.rccm);
    set('representant_legal', client.representant_legal);
    set('representant_qualite', client.representant_qualite);
    set('situation_matrimoniale', client.situation_matrimoniale);
    set('regime_matrimonial', client.regime_matrimonial);
    set('quartier', client.quartier);
    set('commune', client.commune);
    set('demeurant_ville', client.demeurant_ville);
    set('pays', client.pays);
    set('telephone', client.telephone);
    set('email', client.email);
    set('piece_delivree_le', client.piece_delivree_le);
    set('piece_delivree_a', client.piece_delivree_a);
    set('piece_expire_le', client.piece_expire_le);
    set('ne_a', client.ne_a);
    set('date_naissance', client.date_naissance);

    return item;
}

// Se base sur `client.type` quand il est fiable, mais se rabat toujours sur les
// champs réellement renseignés (physique ou morale) si `type` est absent/inattendu
// ou si les champs habituels du type déclaré sont vides — pour ne jamais afficher
// un client "lié" sans aucun nom visible, quelle qu'en soit la cause en amont.
export function clientDisplayName(client) {
    if (!client) return '';
    const nomPhysique = [client.civilite, client.prenom_nom].filter(Boolean).join(' ');
    const nomMorale = client.denomination || '';
    if (client.type === 'morale') return nomMorale || nomPhysique;
    if (client.type === 'physique') return nomPhysique || nomMorale;
    return nomPhysique || nomMorale;
}

export function clientSubtitle(client) {
    if (!client) return '';
    const subPhysique = [client.piece_numero, client.telephone].filter(Boolean).join(' · ');
    const subMorale = [client.forme, client.rccm].filter(Boolean).join(' · ');
    if (client.type === 'morale') return subMorale || subPhysique;
    if (client.type === 'physique') return subPhysique || subMorale;
    return subPhysique || subMorale;
}

// Miroir inverse de mapClientToPrefixedFields/mapClientToRepeatableItem : à partir
// des valeurs déjà soumises pour UN rôle (préfixées ex. pp.civilite, ou aplaties
// pour un item de bloc répétable ex. civilite), reconstruit un brouillon façon
// Client pour pré-remplir ModalNouveauClient lors du rattachement d'une Demande
// convertie en dossier (Demandes/Show.jsx). `roleValues` doit déjà être la portion
// aplatie/résolue pour ce rôle (voir getPublicIntakeFields) — pas le `donnees`
// complet du dossier. Retourne null si aucune donnée exploitable n'est trouvée.
export function buildClientDraftFromDonnees(roleValues, roleFields) {
    if (!roleFields?.length || !roleValues) return null;

    const hasPrefix = roleFields[0].id.includes('.');
    const prefix = hasPrefix ? roleFields[0].id.split('.')[0] : null;
    const get = (suffix) => {
        const key = hasPrefix ? `${prefix}.${suffix}` : suffix;
        const v = roleValues[key];
        return v === undefined || v === null || v === '' ? null : v;
    };

    // Seul ASSOCIE_SCHEMA a un vrai sélecteur "Personne physique/morale" — ailleurs,
    // la civilité "Société" est le seul indice disponible ; à défaut, on suppose
    // une personne physique (l'admin peut corriger le type dans la modale).
    const civilite = get('civilite');
    const isMorale = get('type_personne') === 'Personne morale' || civilite === 'Société';

    let quartier = get('quartier');
    let commune = get('commune');
    let demeurantVille = get('demeurant_ville');
    if (!quartier && !commune && !demeurantVille) {
        // Schéma pas encore éclaté (adresse en texte libre) — tentative de découpage
        // sur la convention "Quartier, Commune, Ville" utilisée dans tous les placeholders.
        const adresseLibre = get('adresse') || get('domicile');
        if (adresseLibre) {
            const parts = adresseLibre.split(',').map(s => s.trim()).filter(Boolean);
            [quartier, commune, demeurantVille] = parts;
        }
    }

    const draft = {
        type: isMorale ? 'morale' : 'physique',
        civilite: isMorale ? '' : (civilite || ''),
        prenom_nom: isMorale ? '' : (get('prenom_nom') || get('nom') || ''),
        denomination: isMorale ? (get('prenom_nom') || get('nom') || '') : '',
        ne_a: get('ne_a') || '',
        date_naissance: get('date_naissance') || '',
        nationalite: get('nationalite') || '',
        situation_matrimoniale: get('situation_matrimoniale') || '',
        regime_matrimonial: get('regime_matrimonial') || '',
        piece_type: get('piece_type') || '',
        piece_numero: get('piece_numero') || get('cni') || '',
        piece_delivree_le: get('piece_delivree_le') || '',
        piece_delivree_a: get('piece_delivree_a') || '',
        piece_expire_le: get('piece_expire_le') || '',
        forme: isMorale ? (get('forme') || '') : '',
        rccm: isMorale ? (get('rccm') || '') : '',
        representant_legal: isMorale ? (get('representant_legal') || get('representant_nom') || '') : '',
        representant_qualite: isMorale ? (get('representant_qualite') || '') : '',
        quartier: quartier || '',
        commune: commune || '',
        demeurant_ville: demeurantVille || '',
        pays: get('pays') || '',
        telephone: get('telephone') || '',
        email: get('email') || '',
    };

    const aDesDonnees = Object.entries(draft).some(([k, v]) => k !== 'type' && v);
    return aDesDonnees ? draft : null;
}

// Construit les champs "Partie" (nom, cni, telephone, adresse, email) à partir soit
// d'un client lié, soit des valeurs texte libres saisies dans le bloc.
export function buildPartieFields(client, fallbackValues, prefix) {
    if (client) {
        return {
            nom: clientDisplayName(client),
            cni: client.type === 'physique' ? client.piece_numero : client.rccm,
            telephone: client.telephone,
            adresse: client.siege || adresseComposite(client),
            email: client.email,
        };
    }
    const v = (suffix) => fallbackValues[`${prefix}.${suffix}`];
    return {
        nom: v('prenom_nom') || v('denomination') || v('nom') || '',
        cni: v('piece_numero') || v('rccm') || null,
        telephone: v('telephone') || null,
        adresse: v('adresse') || [v('quartier'), v('commune'), v('demeurant_ville')].filter(Boolean).join(', ') || null,
        email: v('email') || null,
    };
}
