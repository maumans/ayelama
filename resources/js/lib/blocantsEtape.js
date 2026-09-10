// Ce qui empêche de passer à l'étape suivante de l'assistant de création — **énuméré**, pas résumé
// en un booléen.
//
// L'ancien `canNext()` de Create.jsx agrégeait quatre familles de blocages hétérogènes en un seul
// `&&` et n'en restituait aucune : le bouton « Suivant » se grisait sans qu'on puisse savoir lequel
// des trente champs d'un questionnaire de modification, réparti sur dix sections, était en cause.
//
// Même retournement que `getStepBlockers()` a opéré pour le workflow du dossier, dont le panneau
// « conditions requises » énumère déjà ce qui bloque l'avancement. L'assistant parle donc le même
// langage que la fiche.

import { groupFieldsBySection } from '@/lib/partiesPayload';
import { CONTRAINTES_DATES, PAIRES_DATES, roleGeo } from '@/data/questionnaires';
import { incoherenceOrdre, incoherencePassee, incoherencesTriplet } from '@/lib/coherenceDates';

export const OBJET_LONGUEUR_MIN = 10;

/**
 * Un champ requis est-il satisfait ?
 *
 * ⚠️ Un tableau vide est *truthy* en JavaScript : sans ce cas, un `checkbox_group` obligatoire dont
 * aucune case n'est cochée passerait pour rempli.
 */
function estRempli(valeur) {
    if (Array.isArray(valeur)) return valeur.length > 0;
    if (typeof valeur === 'string') return valeur.trim().length > 0;
    return valeur !== null && valeur !== undefined && valeur !== false && valeur !== '';
}

/**
 * Raison rédigée pour un champ requis vide — le type du champ dit ce qu'on attend.
 *
 * `nbOptionsGeo` distingue les deux situations d'un champ de lieu, que « Choisissez une valeur »
 * confondait : *aucune option n'existe* et *aucune n'a été choisie*. Le premier cas est majoritaire
 * — 33 des 39 communes n'ont aucun quartier au référentiel — et le message renvoyait alors l'étude
 * vers une liste vide, sans dire que le recours (ajouter le lieu) était juste en dessous.
 */
function raisonChampVide(field, nbOptionsGeo = undefined) {
    const geo = roleGeo(field.id);

    if (geo) {
        if (nbOptionsGeo === null) {
            return geo.niveau === 'commune'
                ? "Choisissez d'abord la ville"
                : "Choisissez d'abord la commune";
        }

        if (nbOptionsGeo === 0) {
            return `Aucun${geo.niveau === 'commune' ? 'e' : ''} ${geo.niveau} au référentiel — ajoutez-${geo.niveau === 'commune' ? 'la' : 'le'} depuis le champ`;
        }
    }

    switch (field.type) {
        case 'checkbox_group':    return 'Cochez au moins une option';
        case 'checkbox_required': return 'Cette confirmation est obligatoire';
        case 'select':            return 'Choisissez une valeur';
        case 'date':              return 'Renseignez une date';
        default:                  return 'Champ obligatoire';
    }
}

/**
 * Incohérences de dates du questionnaire — les mêmes contrôles que la fiche client, appliqués aux
 * champs déclarés dans `PAIRES_DATES` et `CONTRAINTES_DATES`.
 *
 * Ne portent que sur des champs **présents dans le questionnaire courant** : une paire déclarée dont
 * aucun champ n'existe ici ne doit rien produire.
 */
function blocantsDates(visibleFields, formValues) {
    const parId = new Map(visibleFields.map(f => [f.id, f]));
    const blocants = [];

    const ajouter = (champ, message) => {
        const field = parId.get(champ);
        if (!field) return;

        blocants.push({
            cle: champ,
            section: field.section || 'Informations générales',
            label: field.label,
            raison: message,
            ancre: champ,
        });
    };

    for (const groupe of PAIRES_DATES) {
        const present = [groupe.naissance, groupe.delivree, groupe.expire].some(c => c && parId.has(c));
        if (!present) continue;

        const anomalies = incoherencesTriplet(
            {
                naissance: groupe.naissance ? formValues[groupe.naissance] : null,
                delivree:  groupe.delivree  ? formValues[groupe.delivree]  : null,
                expire:    groupe.expire    ? formValues[groupe.expire]    : null,
            },
            groupe,
        );

        for (const { champ, message } of anomalies) ajouter(champ, message);
    }

    for (const contrainte of CONTRAINTES_DATES) {
        if (!parId.has(contrainte.champ)) continue;

        const anomalie = contrainte.genre === 'passee'
            ? incoherencePassee(formValues[contrainte.champ], contrainte.message)
            : incoherenceOrdre(formValues[contrainte.apres], formValues[contrainte.champ], contrainte.message);

        if (anomalie) ajouter(contrainte.champ, anomalie);
    }

    return blocants;
}

/**
 * Ancre DOM d'une carte de section — partagée par le rendu et par la liste des blocants, pour
 * qu'un champ **masqué** (porté par une fiche liée) puisse renvoyer vers sa section à défaut de
 * pouvoir renvoyer vers lui-même.
 */
export function ancreSection(nom) {
    if (!nom) return null;
    return 'section-' + nom.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}

/**
 * Tout ce qui manque à l'étape courante.
 *
 * @param {object}   ctx
 * @param {number}   ctx.step             Étape courante (0 = catégorie, 1 = détails, 2 = récap)
 * @param {?string}  ctx.categorie
 * @param {?object}  ctx.typeActe
 * @param {object[]} ctx.visibleFields    Champs du questionnaire après filtrage par `showIf`
 * @param {object}   ctx.formValues
 * @param {string}   ctx.objet
 * @param {string}   ctx.notaireId
 * @param {object}   [ctx.optionsGeo]     id de champ géo → nombre d'options chargées (`null` si le
 *                                        parent n'est pas encore choisi). Remonté par `LieuSelect`,
 *                                        pour que le blocant ne réponde pas « Choisissez une
 *                                        valeur » devant une liste vide.
 * @param {Function} [ctx.estMasque]      (field, groupe) => bool — le champ est-il retiré de la
 *                                        saisie parce qu'une fiche liée le porte ? Il bloque
 *                                        toujours, mais on ne peut pas y renvoyer : on renvoie
 *                                        alors vers la fiche à compléter.
 * @returns {Array<{cle: string, section: string, label: string, raison: string, ancre: string}>}
 *          Dans l'ordre d'apparition à l'écran — c'est cet ordre qui décide vers quel champ on
 *          défile en premier.
 */
export function blocantsEtape({ step, categorie, typeActe, visibleFields = [], formValues = {}, objet = '', notaireId = '', estMasque = null, optionsGeo = {} }) {
    if (step === 0) {
        // L'étape « catégorie » souffrait du même défaut, en moins visible : « Suivant » y était
        // grisé sans rien dire non plus.
        return categorie
            ? []
            : [{ cle: 'categorie', section: "Type d'acte", label: "Catégorie d'acte", raison: 'Choisissez une catégorie', ancre: null }];
    }

    if (step !== 1) return [];

    const blocants = [];

    if (!typeActe) {
        return [{ cle: 'type_acte', section: "Type d'acte", label: "Type d'acte", raison: 'Choisissez la procédure', ancre: null }];
    }

    // ── Champs du questionnaire, dans leur ordre d'affichage ─────────────────
    //
    // Passage par `groupFieldsBySection` et non par une boucle sur `visibleFields` : dans
    // `questionnaires.js`, **seul le premier champ d'une section porte `section`**, les suivants
    // l'héritent. Lire `field.section` directement rangerait la quasi-totalité des champs sous une
    // section fantôme — et les badges des cartes, qui se calent sur `group.name`, ne
    // correspondraient à rien. Une seule implémentation du regroupement, donc aucune divergence
    // possible avec le rendu.
    for (const groupe of groupFieldsBySection(visibleFields)) {
        const section = groupe.name || 'Informations générales';

        for (const field of groupe.fields) {
            if (field.type === 'repeatable') {
                const min = field.min ?? 1;
                const nb = formValues[field.id]?.length ?? 0;

                if (nb < min) {
                    blocants.push({
                        cle: field.id,
                        section,
                        label: field.label,
                        raison: nb === 0
                            ? `Ajoutez au moins ${min === 1 ? '1 entrée' : `${min} entrées`}`
                            : `Au moins ${min} entrées — ${nb} saisie${nb > 1 ? 's' : ''}`,
                        ancre: field.id,
                    });
                }
                continue;
            }

            if (field.required && !estRempli(formValues[field.id])) {
                // Champ retiré de la saisie parce qu'une fiche liée (client ou société) le porte :
                // il bloque toujours, mais aucun contrôle ne l'affiche. C'était le blocage
                // silencieux par excellence — le bouton se grisait sans qu'aucun champ visible ne
                // soit en défaut. On dit alors quoi faire, et on renvoie vers la fiche.
                const masque = estMasque ? estMasque(field, groupe) : false;

                blocants.push({
                    cle: field.id,
                    section,
                    label: field.label,
                    raison: masque
                        ? 'Absent de la fiche liée — complétez la fiche, ou détachez-la pour saisir ici'
                        : raisonChampVide(field, optionsGeo[field.id]),
                    ancre: masque ? ancreSection(groupe.name) : field.id,
                });
            }
        }
    }

    // ── Cohérence des dates ──────────────────────────────────────────────────
    // Distincte des champs requis : ici la valeur **est** saisie, mais décrit une situation
    // impossible. Ces contrôles n'existaient que sur la fiche client, et aucun des 25 champs de date
    // des questionnaires n'était vérifié — ni ici, ni côté serveur.
    blocants.push(...blocantsDates(visibleFields, formValues));

    // ── Informations générales du dossier ────────────────────────────────────
    const objetSaisi = (objet ?? '').trim();

    if (objetSaisi.length < OBJET_LONGUEUR_MIN) {
        blocants.push({
            cle: 'objet',
            section: 'Dossier',
            label: 'Objet du dossier',
            // Règle jusqu'ici invisible : le placeholder qui l'énonce disparaît à la première frappe.
            raison: objetSaisi.length === 0
                ? `Champ obligatoire — ${OBJET_LONGUEUR_MIN} caractères minimum`
                : `${OBJET_LONGUEUR_MIN} caractères minimum — ${objetSaisi.length} saisi${objetSaisi.length > 1 ? 's' : ''}`,
            ancre: 'objet',
        });
    }

    if (!notaireId) {
        blocants.push({
            cle: 'notaire_id',
            section: 'Intervenants',
            label: 'Notaire en charge',
            raison: 'Choisissez le notaire en charge',
            ancre: 'notaire_id',
        });
    }

    return blocants;
}

/**
 * Blocants regroupés par section, dans l'ordre d'apparition — forme attendue par le panneau.
 *
 * @returns {Array<{section: string, lignes: object[]}>}
 */
export function blocantsParSection(blocants) {
    const groupes = [];

    for (const blocant of blocants) {
        const dernier = groupes.find(g => g.section === blocant.section);
        if (dernier) dernier.lignes.push(blocant);
        else groupes.push({ section: blocant.section, lignes: [blocant] });
    }

    return groupes;
}

/**
 * Nombre de blocants par nom de section — alimente les badges des en-têtes de cartes.
 *
 * @returns {Record<string, number>}
 */
export function compterParSection(blocants) {
    return blocants.reduce((acc, b) => ({ ...acc, [b.section]: (acc[b.section] ?? 0) + 1 }), {});
}

/** Clés des champs en défaut — pour l'état d'erreur des champs après une tentative de validation. */
export function clesEnDefaut(blocants) {
    return new Set(blocants.map(b => b.cle));
}

/**
 * Champ → motif rédigé, pour afficher la raison **sous le champ concerné**.
 *
 * Une `Map` plutôt qu'un `Set` doublé d'un `find` dans la liste : le rendu interroge un champ à la
 * fois, et le premier blocant d'un champ est celui qu'on montre.
 *
 * @returns {Map<string, string>}
 */
export function motifsParChamp(blocants) {
    const motifs = new Map();

    for (const blocant of blocants) {
        if (!motifs.has(blocant.cle)) motifs.set(blocant.cle, blocant.raison);
    }

    return motifs;
}

/**
 * Fait défiler jusqu'au champ concerné et lui donne le focus quand c'est possible.
 *
 * Les champs portent déjà un `id` égal à leur identifiant de questionnaire : l'ancre existe, rien à
 * inventer. `block: 'center'` plutôt que `'start'` — un champ collé sous l'en-tête collant serait à
 * moitié masqué.
 */
export function allerAuBlocant(ancre) {
    if (!ancre) return;

    const cible = document.getElementById(ancre);
    if (!cible) return;

    cible.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Le focus après le défilement : le donner avant ferait sauter la page au lieu de l'animer.
    window.setTimeout(() => {
        if (typeof cible.focus === 'function') cible.focus({ preventScroll: true });
    }, 350);
}
