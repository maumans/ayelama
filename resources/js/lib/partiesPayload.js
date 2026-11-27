import { buildPartieFields, estChampRepresentation, estChampIdentite, personneDuChamp, mapClientToPrefixedFields, mapClientToRepeatableItem } from '@/lib/clientFields';
import { typePersonneCanonique } from '@/lib/piecesRequises';
import { MOTIF_REPRESENTATION_PAR_LIBELLE, FORME_TITRE_PAR_LIBELLE, getVisibleFields } from '@/data/questionnaires';
import { frDateToISO } from '@/lib/dates';
import { groupeRepresentant } from '@/lib/piecesStagees';

// Regroupe les champs d'un questionnaire par section. Une nouvelle section démarre
// à chaque champ portant `section`. Partagé entre la création et l'édition d'un
// dossier pour garder un seul rendu/une seule logique de payload `parties`.
//
// `clientRole` et `societePicker` sont remontés du premier champ au groupe : ils disent
// respectivement que la section désigne une fiche client (et produit une `Partie`) ou une
// fiche société du registre (et renseigne `dossiers.societe_id`).
export function groupFieldsBySection(fields) {
    const groups = [];
    let current = null;
    for (const field of fields) {
        if (field.section || !current) {
            current = {
                name: field.section ?? null,
                clientRole: field.clientRole ?? null,
                societePicker: field.societePicker ?? false,
                fields: [],
            };
            groups.push(current);
        }
        current.fields.push(field);
    }
    return groups;
}

/**
 * Champs qui **déplient** une section : ceux dont la visibilité de *tous* les autres champs
 * du groupe dépend. En pratique la case « Le gérant est une personne différente de l'associé
 * unique » (`ger.est_different`) et ses semblables.
 *
 * Ils doivent être rendus **hors** de `ClientRoleSection` : ce composant ne rend ses enfants
 * qu'une fois une fiche rattachée (ou en cours de changement). Cochée puis laissée sans
 * personne désignée, la case partait donc avec le reste de la section — plus moyen de la
 * décocher sans d'abord rattacher quelqu'un, c'est-à-dire sans créer la partie qu'on voulait
 * justement annuler.
 *
 * Critère volontairement étroit : « tous les autres », et non « au moins un ». La case
 * « se fait représenter à l'acte » n'ouvre que les champs `repr_*` du groupe, pas la section
 * entière ; la décocher ne la replie pas, et elle reste là où elle est.
 *
 * @param {object[]} fields  champs **visibles** d'une section (après `getVisibleFields`)
 * @returns {object[]}       sous-ensemble de `fields`, dans l'ordre d'origine
 */
export function champsDepliantLaSection(fields) {
    if (fields.length <= 1) return [];

    const propres = new Set(fields.map(f => f.id));
    const cites = (showIf, id) => {
        if (!showIf) return false;
        if (showIf.all !== undefined) return showIf.all.some(c => cites(c, id));
        return showIf.field === id;
    };

    return fields.filter(f =>
        propres.has(f.id)
        && fields.every(autre => autre.id === f.id || cites(autre.showIf, f.id)),
    );
}

// Construit le payload `parties` (dossiers.store / dossiers.questionnaire) à partir
// des sections client-liables (liées à un client existant ou saisies en texte libre)
// et des blocs répétables (associés, gérants, actionnaires…).
//
// `partiesExistantes` — les `Partie` déjà enregistrées du dossier (vide à la création).
// ⚠️ **Sans elles, enregistrer le questionnaire détruisait les personnes des sections
// scalaires.** `DossierController::updateQuestionnaire()` resynchronise rôle par rôle et
// apparie sur `partie_id` : un item qui n'en porte pas est *créé*, puis toute Partie du rôle
// non référencée est supprimée par `supprimerAvecPieces()` — qui emporte ses pièces, leurs
// versions et les fichiers sur le disque. Or `partie_id` n'était émis que par la branche des
// **blocs répétables**. Chaque enregistrement recréait donc l'associé unique, le gérant, le
// commissaire… et leurs justificatifs disparaissaient en silence : le dossier restait
// cohérent, les personnes réapparaissaient, seules les pièces manquaient — et le blocage ne
// reparle qu'en Initialisation et en Édition (retour de l'étude, 2026-11-27).
export function buildPartiesPayload(questionnaire, formValues, clientLinks, stagedPieces = {}, piecesBrouillon = {}, partiesExistantes = []) {
    const parties = [];
    // Compteur de clés locales. Le représentant n'a pas d'identifiant au moment où le
    // représenté est envoyé : la corrélation passe par une clé propre à cette soumission, que
    // le serveur résout en seconde passe. Un compteur et non l'index du rôle — celui-ci se
    // décale au réordonnancement d'un bloc répétable.
    let n = 0;
    const cle = () => `p${++n}`;

    // Une section scalaire n'a qu'une Partie par rôle — c'est l'unité que le serveur
    // synchronise. Les blocs répétables, eux, apparient ligne par ligne sur `donnees_index`
    // (voir attachPartieIds() dans Dossiers/Show.jsx) et portent déjà leur `partie_id`.
    const existanteDuRole = (role) => partiesExistantes.find(p => p.role === role) ?? null;

    for (const group of groupFieldsBySection(questionnaire)) {
        if (!group.clientRole) continue;
        const prefix = group.fields[0].id.split('.')[0];
        const existante = existanteDuRole(group.clientRole);
        const client = clientLinks[group.clientRole] ?? null;
        const fields = buildPartieFields(client, formValues, prefix);
        if (!fields.nom) continue;

        const piecesGroup = stagedPieces[group.clientRole] ?? {};
        const itemPieces = {};
        for (const cat of Object.keys(piecesGroup)) {
             if (piecesGroup[cat]) itemPieces[cat] = piecesGroup[cat];
        }

        // Pièces déjà téléversées à un brouillon : transmises par emplacement, pas
        // en fichier — le serveur les relit sur le disque privé.
        const brouillonGroup = piecesBrouillon[group.clientRole] ?? {};
        const itemPiecesBrouillon = {};
        for (const cat of Object.keys(brouillonGroup)) {
            if (brouillonGroup[cat]?.chemin) itemPiecesBrouillon[cat] = brouillonGroup[cat].chemin;
        }
        
        const cleRepresente = cle();

        parties.push({
            ...fields,
            cle_locale: cleRepresente,
            // Conserver l'identité de la Partie, donc ses pièces (voir l'en-tête).
            partie_id: existante?.id ?? undefined,
            role: group.clientRole,
            client_id: client?.id ?? undefined,
            // ⚠️ **La nature de la personne, comme la branche répétable le fait déjà** (voir
            // plus bas). Son absence ici était un trou silencieux : mesuré le 2026-09-30,
            // **39 parties sur 41 avaient `type_personne` à NULL**, et rien ne le dérive côté
            // serveur. Conséquence, `Partie::piecesRequisesPour()` retombait sur « physique » —
            // l'écran affichait le jeu de pièces d'une société (il lit `formValues`), le
            // serveur en exigeait un autre. Cocher « Personne morale » n'avait donc aucun effet
            // au-delà de l'affichage.
            //
            // La fiche liée prime sur la saisie : c'est elle qui fait foi sur la nature d'une
            // personne, et le questionnaire n'en est qu'une projection (décision #33).
            type_personne: client
                ? client.type
                : typePersonneCanonique(formValues[`${prefix}.type_personne`]),
            // Emplacement de ce rôle dans le questionnaire : c'est ce qui permet au
            // serveur de reprojeter l'identité depuis la fiche client sans connaître
            // le schéma (qui vit uniquement ici, en JS). Voir ClientProjectionService.
            donnees_prefixe: prefix,
            pieces: Object.keys(itemPieces).length > 0 ? itemPieces : undefined,
            pieces_brouillon: Object.keys(itemPiecesBrouillon).length > 0 ? itemPiecesBrouillon : undefined,
            ...representationDuRepresente(formValues, prefix, cle, parties, {
                client: clientLinks[roleRepresentant(group.clientRole)] ?? null,
                // Même raison, pour le mandataire : `synchroniserRepresentants()` crée une
                // nouvelle Partie quand `partie_id` manque, et le ramasse-miettes du graphe
                // supprime ensuite l'ancienne, orpheline — avec sa procuration.
                partieId: existante?.representation?.representant_id ?? undefined,
                stagedPieces: stagedPieces[roleRepresentant(group.clientRole)] ?? {},
                piecesBrouillon: piecesBrouillon[roleRepresentant(group.clientRole)] ?? {},
            }),
        });
    }

    for (const field of questionnaire) {
        if (field.type !== 'repeatable' || !field.clientRole) continue;
        const items = formValues[field.id] ?? [];
        items.forEach((item, idx) => {
            const nom = item.nom || item.prenom_nom;
            if (!nom) return;

            const piecesGroup = stagedPieces[field.id] ?? {};
            const itemPieces = {};
            // Extract pieces for this specific index from the stagedPieces map
            for (const key of Object.keys(piecesGroup)) {
                if (key.startsWith(`${idx}:`)) {
                    const cat = key.split(':')[1];
                    if (piecesGroup[key]) {
                        itemPieces[cat] = piecesGroup[key];
                    }
                }
            }

            // Idem pour les pièces héritées d'un brouillon, dont les clés portent le
            // même préfixe d'index (`{idx}:{categorie}`).
            const brouillonGroup = piecesBrouillon[field.id] ?? {};
            const itemPiecesBrouillon = {};
            for (const key of Object.keys(brouillonGroup)) {
                if (key.startsWith(`${idx}:`) && brouillonGroup[key]?.chemin) {
                    itemPiecesBrouillon[key.split(':')[1]] = brouillonGroup[key].chemin;
                }
            }

            // Pièces du représentant de CET item. Groupe distinct — `{fieldId}#repr` — plutôt
            // qu'une troisième section dans la clé : `key.split(':')[1]` est déjà porteur, et
            // une clé à trois parties le casserait en silence. Le parseur ci-dessus est ainsi
            // réutilisé tel quel, comme construireFormDataBrouillon() qui itère les groupes
            // en aveugle.
            //
            // ⚠️ Jusqu'au 2026-11-16, aucun écran n'écrivait dans ce groupe : la lecture ci-dessous
            // tombait toujours sur `{}`, et le représentant d'une ligne de bloc naissait sans pièce
            // (retour R06). RepeatableGroup les met désormais en attente sous `groupeRepresentant()`.
            const piecesRepr = stagedPieces[groupeRepresentant(field.id)] ?? {};
            const reprPieces = {};
            for (const key of Object.keys(piecesRepr)) {
                if (key.startsWith(`${idx}:`) && piecesRepr[key]) reprPieces[key.split(':')[1]] = piecesRepr[key];
            }
            // Idem pour celles héritées d'un brouillon — omises jusqu'ici dans cette branche, alors
            // que la branche scalaire les transmettait.
            const brouillonRepr = piecesBrouillon[groupeRepresentant(field.id)] ?? {};
            const reprBrouillon = {};
            for (const key of Object.keys(brouillonRepr)) {
                if (key.startsWith(`${idx}:`) && brouillonRepr[key]?.chemin) reprBrouillon[key.split(':')[1]] = brouillonRepr[key];
            }

            parties.push({
                nom,
                cle_locale: cle(),
                role: field.clientRole,
                partie_id: item.partie_id ?? undefined,
                client_id: item.client?.id ?? item.client_id ?? undefined,
                // Bloc + index : le serveur fusionnera l'identité dans donnees[bloc][index]
                // sans écraser les données propres à l'acte de cette ligne (nombre de
                // parts, fonction…). Voir ClientProjectionService::reprojeter().
                donnees_bloc: field.id,
                donnees_index: idx,
                type_personne: typePersonneCanonique(item.type_personne),
                cni: item.cni ?? item.piece_numero ?? null,
                telephone: item.telephone ?? null,
                adresse: item.adresse ?? item.domicile ?? null,
                email: item.email ?? null,
                pieces: Object.keys(itemPieces).length > 0 ? itemPieces : undefined,
                pieces_brouillon: Object.keys(itemPiecesBrouillon).length > 0 ? itemPiecesBrouillon : undefined,
                ...representationDuRepresente(item, null, cle, parties, {
                    client: item.representant_client ?? null,
                    partieId: item.representant_partie_id ?? undefined,
                    stagedPieces: reprPieces,
                    piecesBrouillon: reprBrouillon,
                }),
            });
        });
    }

    return parties;
}

/**
 * Le rôle d'un représentant. Un seul pour les trois motifs — voir MotifRepresentation::ROLE.
 *
 * Détenteur unique, consommé par le payload, les écrans et `getManagedClientRoles()` : un rôle
 * recopié finirait par diverger d'un endroit à l'autre.
 */
export const ROLE_REPRESENTANT = 'mandataire';

/** Clé sous laquelle une section scalaire range la fiche client de son représentant. */
export function roleRepresentant(clientRole) {
    return `${ROLE_REPRESENTANT}:${clientRole}`;
}

/**
 * Émet la `Partie` du représentant, et rend les champs de représentation du **représenté**.
 *
 * Le représentant est poussé dans `parties` par effet de bord — il est une personne à part
 * entière, avec sa fiche et ses pièces — tandis que le représenté ne reçoit que le lien et les
 * caractéristiques du mandat. C'est le sens de la clé étrangère : *N représentés → 1 représentant*.
 *
 * ⚠️ Le représentant ne porte **aucune** localisation (`donnees_prefixe` / `donnees_bloc`) : il
 * n'occupe pas d'emplacement du questionnaire, et ne peut donc pas écraser la projection de la
 * personne qu'il représente. Son identité est projetée dans le sous-espace `repr_*`.
 *
 * @param {object}  valeurs  `formValues` pour une section scalaire, l'item pour un bloc
 * @param {?string} prefixe  `'pp'`… pour une section scalaire, `null` dans un bloc
 */
function representationDuRepresente(valeurs, prefixe, cle, parties, { client = null, partieId, stagedPieces = {}, piecesBrouillon = {} } = {}) {
    const lire = (suffixe) => valeurs[prefixe ? `${prefixe}.${suffixe}` : suffixe];

    if (!lire('est_represente')) return {};

    const nom = client
        ? (client.type === 'physique' ? client.prenom_nom : client.denomination)
        : lire('repr_prenom_nom');

    // Même règle que pour une partie sans nom : on n'émet pas une personne vide. Le manque est
    // rattrapé par blocantsEtape, qui sait le dire, pas par un payload silencieusement amputé.
    if (!nom) return {};

    const cleMandataire = cle();

    const brouillon = {};
    for (const cat of Object.keys(piecesBrouillon)) {
        if (piecesBrouillon[cat]?.chemin) brouillon[cat] = piecesBrouillon[cat].chemin;
    }

    parties.push({
        cle_locale: cleMandataire,
        nom,
        role: ROLE_REPRESENTANT,
        partie_id: partieId,
        client_id: client?.id ?? undefined,
        cni: lire('repr_piece_numero') ?? client?.piece_numero ?? null,
        telephone: lire('repr_telephone') ?? null,
        email: lire('repr_email') ?? null,
        adresse: [lire('repr_quartier'), lire('repr_commune'), lire('repr_demeurant_ville')]
            .filter(Boolean).join(', ') || null,
        pieces: Object.keys(stagedPieces).length > 0 ? stagedPieces : undefined,
        pieces_brouillon: Object.keys(brouillon).length > 0 ? brouillon : undefined,
    });

    return {
        represente_par_cle: cleMandataire,
        representation_motif: MOTIF_REPRESENTATION_PAR_LIBELLE[lire('representation_motif')] ?? undefined,
        representation_qualite: lire('repr_qualite') ?? undefined,
        representation_titre_forme: FORME_TITRE_PAR_LIBELLE[lire('repr_titre_forme')] ?? undefined,
        // ⚠️ La colonne est castée : elle attend de l'**ISO**. Le questionnaire porte du
        // JJ/MM/AAAA — « poster du français vers une colonne castée est le défaut à ne pas
        // refaire » (lib/dates.js). La projection réécrira cette date en français dans `donnees`.
        representation_titre_date: frDateToISO(lire('repr_titre_date')) || undefined,
        representation_titre_autorite: lire('repr_titre_autorite') ?? undefined,
        representation_titre_reference: lire('repr_titre_reference') ?? undefined,
    };
}

// Champs exposables au client via un lien de demande externe : les champs de la
// section `clientRole` demandée (une seule entrée même si la section est un bloc
// répétable — le client ne remplit que sa propre part, le personnel complète le
// reste manuellement), plus tout champ marqué `publicIntake: true` ailleurs dans
// le questionnaire (infos dossier non-identité que le client connaît aussi —
// dénomination, objet social, description du bien… jamais les champs calculés
// ou juridiques comme le RCCM d'une société en cours de création, la taxe de
// plus-value ou le rang hypothécaire).
/**
 * Ne garde d'une condition `showIf` que ce qui dépend des champs du bloc lui-même.
 *
 * Le formulaire public doit évaluer les `showIf` — sans quoi les branches « personne physique »
 * et « personne morale » s'affichent ensemble — mais il ne peut pas évaluer **toutes** les
 * conditions : certaines désignent des champs que le client ne voit pas.
 *
 * Le cas qui l'impose : les blocs de la dissolution sont conditionnés par
 * `dissolution.phase`, qui n'est **pas** `publicIntake` — qualifier la phase d'une procédure est
 * une décision de l'étude, pas du client, exactement comme `modif.types`. Évaluer cette
 * condition rendrait tout le bloc invisible et le formulaire inutilisable.
 *
 * Or l'étude a **déjà** tranché en créant la demande pour ce rôle. La règle est donc :
 * **la visibilité d'un champ de rôle ne peut dépendre que d'un autre champ du même rôle.**
 * Ce qui dépend d'ailleurs est une décision déjà prise, pas une question à poser.
 *
 * @param showIf  condition d'origine
 * @param ids     identifiants des champs du bloc
 */
function conditionsInternes(showIf, ids) {
    if (!showIf) return null;

    if (showIf.all !== undefined) {
        const restantes = showIf.all.map(c => conditionsInternes(c, ids)).filter(Boolean);
        if (restantes.length === 0) return null;
        return restantes.length === 1 ? restantes[0] : { all: restantes };
    }

    return ids.has(showIf.field) ? showIf : null;
}

/**
 * Tous les champs d'un rôle, **schéma complet** — pas seulement ceux visibles à l'instant T.
 *
 * ⚠️ Rattacher une fiche doit remplir la section **entière**, et non la partie qui se trouve
 * affichée au moment du clic. Le cas qui l'impose : dans un bloc bimodal, l'état civil est
 * conditionné à `type_personne`. Tant que la nature n'est pas connue, ces champs sont invisibles
 * — donc absents du groupe rendu. Or c'est le rattachement lui-même qui pose la nature : les
 * huit champs d'état civil apparaissent **juste après**, et resteraient **vides** s'ils
 * n'étaient pas dans la liste passée à la projection. Vides et masqués, puisque ce sont des
 * champs d'identité : la famille de défauts de la décision 69.
 *
 * Projeter une clé actuellement invisible est sans risque — `purgerChampsInvisibles()` nettoie
 * à l'envoi ce qui l'est resté. L'oublier, si.
 *
 * `RepeatableGroup.applyClient()` n'a jamais eu ce défaut : il passe `fields`, le schéma de
 * l'item, jamais un sous-ensemble filtré.
 */
/**
 * Quels rôles le registre désigne-t-il **sans ambiguïté** ?
 *
 * Le chemin existait en entier — `societes.dossier_id` → les `parties` du dossier constitutif →
 * `Societe::associesConnus()` → `personnesConnues` → les puces du sélecteur — et s'arrêtait au
 * vivier : désigner l'associé unique d'une SARLU demandait **trois clics dans deux endroits**
 * pour une information que la base porte déjà.
 *
 * ⚠️ **Un seul candidat, sinon rien.** L'objection qui a fait écrire `importerPersonneConnue()`
 * sans affectation de rôle — un associé d'origine peut avoir cédé toutes ses parts — reste
 * entière dès qu'il y a plusieurs associés : on ne choisit pas à la place du clerc. Elle ne dit
 * rien, en revanche, du cas où le registre ne connaît **qu'une** personne pour ce rôle.
 *
 * Fonction **pure**, hors du composant, pour deux raisons : la règle est vérifiable sans rendre
 * de React (contrôle E de `tools/verifier-sections-questionnaire.mjs`), et elle est ainsi
 * énoncée à un seul endroit pour les écrans qui voudront la partager.
 *
 * @param personnesConnues  tel que `SocieteController::show()` le renvoie : `{role, client}`
 * @param clientLinks       rôles déjà pourvus, qu'on ne touche jamais
 * @returns {Array<{role: string, group: object, client: object}>}
 */
/**
 * Les personnes que le registre connaît pour ce rôle, et qui sont **rattachables**.
 *
 * Une personne sans fiche client n'en est pas : la rattacher créerait une partie coquille, sans
 * identité à projeter. Le sélecteur la grise déjà dans la carte de la société ; ici on l'écarte.
 *
 * Une seule définition pour deux usages qui doivent rester d'accord — le rattachement d'office
 * ({@see rolesARattacherDoffice}) et les propositions affichées dans la carte du rôle. Qu'ils
 * divergent, et la carte proposerait quelqu'un que la règle refuse, ou l'inverse.
 */
export function candidatsDuRegistre(personnesConnues = [], clientRole = null) {
    if (!clientRole) return [];

    return personnesConnues.filter(p => p?.role === clientRole && p?.client);
}

export function rolesARattacherDoffice(questionnaire, personnesConnues = [], clientLinks = {}) {
    const retenus = [];
    for (const group of groupFieldsBySection(questionnaire)) {
        const role = group.clientRole;
        if (!role || clientLinks[role]) continue;

        // ⚠️ **Jamais un bloc répétable.** `applyClientToSection()` déduit le préfixe de
        // `fields[0].id` : sur un répétable, ce serait le nom du bloc (`associes`), et la
        // projection écrirait des clés bâtardes du genre `associes.nom` à côté du vrai tableau.
        // Une ligne se rattache par `RepeatableGroup.applyClient()`, qui sait où l'écrire.
        //
        // Le cas ne peut pas se produire aujourd'hui — `associesConnus()` ne rend que
        // `associe`, `associe_unique` et `gerant`, et aucun questionnaire à sélecteur de
        // société ne porte ces rôles en répétable — mais la garde coûte une ligne et le
        // dégât serait silencieux.
        if (group.fields[0]?.type === 'repeatable') continue;

        // **Un seul candidat, sinon rien** — deux associés d'origine, et c'est au clerc de
        // dire lequel intervient. La carte du rôle les proposera tous les deux.
        const candidats = candidatsDuRegistre(personnesConnues, role);
        if (candidats.length !== 1) continue;

        retenus.push({ role, group, client: candidats[0].client });
    }

    return retenus;
}

/**
 * Les lignes de « Nouvelle répartition » reprises du registre.
 *
 * ⚠️ **Le clerc retapait ce que le registre savait.** `modif.repartition_apres` partait d'une
 * carte vierge : il fallait ressaisir chaque associé au clavier, puis recalculer les
 * pourcentages de tête — alors que `societe_associes` porte les noms, les parts et les fiches,
 * et que `EtatSocieteService::courant()` calcule déjà les pourcentages.
 *
 * ⚠️ **Ce n'est pas `rolesARattacherDoffice()`, et ce ne pouvait pas l'être.** Celui-ci rattache
 * **une fiche à une carte** et s'interdit explicitement les blocs répétables, parce qu'il
 * déduit un préfixe de `fields[0].id`. Ici on ne rattache rien : on **écrit des lignes** dans
 * `formValues[bloc]`, ce qu'un bloc répétable rend directement. Deux gestes différents, deux
 * fonctions — les confondre aurait fait écrire des clés bâtardes du genre `associes.nom`.
 *
 * La règle « un seul candidat, sinon rien » ne s'applique pas non plus : il n'y a aucune
 * ambiguïté à lever, les associés du registre **sont** la répartition de départ.
 *
 * @param {{associes?: Array<{nom: string, client_id: ?number, nb_parts: number, pourcentage: ?number}>}} etat
 * @returns {Array<Record<string, unknown>>} prêt pour `formValues['modif.repartition_apres']`
 */
export function lignesDeRepartitionDepuisRegistre(etat) {
    return (etat?.associes ?? [])
        .filter(a => a?.nom)
        .map(a => ({
            associe:        a.nom,
            parts_chiffres: a.nb_parts === null || a.nb_parts === undefined ? '' : String(a.nb_parts),
            pourcentage:    a.pourcentage === null || a.pourcentage === undefined ? '' : String(a.pourcentage),
            // ⚠️ **Un instantané, pas un booléen.** `__depuisRegistre: true` disait seulement
            // d'où venait la ligne ; il ne permettait pas de voir que le clerc avait ensuite
            // changé les parts. En gardant ce que le registre disait, la ligne affiche
            // « depuis le registre » tant que rien ne bouge, et « modifié — 100 au registre »
            // dès que l'écart apparaît. La même information sert au clerc et à la relecture.
            //
            // ⚠️ Cette clé est **enregistrée dans `donnees`**, comme `__saisieLibre`, `client`
            // et `partie_id` le sont déjà sur les autres blocs : rien ne filtre les clés
            // préfixées de `__` avant l'envoi. C'est assumé — la provenance d'une ligne de
            // répartition a sa place dans un instantané.
            __registre: {
                nb_parts:    a.nb_parts ?? null,
                pourcentage: a.pourcentage ?? null,
            },
        }));
}

/**
 * Une carte de répartition est-elle encore vierge ?
 *
 * On ne reprend le registre que dans ce cas. Écraser une saisie en cours parce que le clerc
 * vient de rattacher une société serait le genre d'« aide » qui fait perdre du travail —
 * même prudence que `rolesARattacherDoffice`, qui s'abstient dès qu'un rôle est déjà pourvu.
 *
 * `initRepeatableFields()` crée `min` lignes vides au démarrage : une carte intacte est donc
 * une carte dont **aucune** ligne ne porte de valeur, et non une carte sans ligne.
 */
export function repartitionEstVierge(lignes) {
    if (!Array.isArray(lignes) || lignes.length === 0) return true;

    return lignes.every(ligne => Object.entries(ligne ?? {})
        .filter(([cle]) => !cle.startsWith('__'))
        .every(([, valeur]) => valeur === '' || valeur === null || valeur === undefined));
}

/**
 * Les personnes que ce dossier connaît déjà, pour les proposer au lieu de les faire retaper.
 *
 * ⚠️ **Le nom d'un associé n'est pas un libellé, c'est une clé.** C'est par lui que
 * `SocieteMutationService::appliquerRepartition()` retrouve qui perd ses parts. La casse et
 * les espaces sont normalisés depuis le 2026-11-02, mais une faute de frappe ne l'est pas :
 * « Maurice MANSARE » au lieu de « Maurice Mansaré » fait sortir l'associé réel et entrer un
 * fantôme avec ses parts, sans un mot. Un champ de texte libre pour cette valeur-là est donc
 * un défaut en attente, pas une commodité.
 *
 * Trois origines, dans cet ordre de priorité :
 *
 *  1. le **registre** — les associés actuels de la société rattachée ;
 *  2. le **dossier** — cessionnaires, souscripteurs et cédants déjà déclarés dans ce
 *     questionnaire, qui n'existent pas encore au registre ;
 *  3. le **gérant entrant**, qui devient souvent associé dans la foulée.
 *
 * Le dédoublonnage se fait sur le nom normalisé, et la **première** origine l'emporte : une
 * personne déjà au registre y reste rattachée même si elle est aussi cessionnaire.
 *
 * @returns {Array<{valeur: string, label: string, origine: 'registre'|'dossier'}>}
 */
export function personnesDuDossier({ etatSociete = null, formValues = {} } = {}) {
    const vues = new Map();

    const ajouter = (nom, origine) => {
        const propre = String(nom ?? '').trim();

        if (propre === '') return;

        const cle = propre.toLowerCase().replace(/\s+/g, ' ');

        if (vues.has(cle)) return;

        vues.set(cle, { valeur: propre, label: propre, origine });
    };

    for (const associe of etatSociete?.associes ?? []) {
        ajouter(associe?.nom, 'registre');
    }

    for (const bloc of ['modif.cessionnaires', 'modif.souscripteurs', 'modif.cedants']) {
        const lignes = Array.isArray(formValues[bloc]) ? formValues[bloc] : [];

        for (const ligne of lignes) {
            ajouter(ligne?.nom ?? ligne?.prenom_nom, 'dossier');
        }
    }

    ajouter(formValues['gerant_entrant.prenom_nom'], 'dossier');

    return [...vues.values()];
}

/**
 * Les listes d'options qu'un champ peut demander par `optionsDe`.
 *
 * ⚠️ **Nommer les sources ici, et nulle part ailleurs.** Un `optionsDe` mal orthographié
 * rendrait une liste vide : le clerc verrait un sélecteur sans aucun choix et n'aurait aucun
 * moyen de comprendre pourquoi. Le contrôle H de `verifier-sections-questionnaire.mjs`
 * vérifie que chaque `optionsDe` déclaré figure dans cette liste.
 */
export const SOURCES_OPTIONS_DYNAMIQUES = ['personnesDuDossier', 'gerantsEnFonction'];

/**
 * Les gérants que le registre dit en fonction.
 *
 * ⚠️ **Le nom d'un gérant sortant est une clé, lui aussi.** `appliquerGerance()` s'en sert pour
 * clôturer son mandat ; mal orthographié, le mandat n'est **pas** clôturé et le nouveau gérant
 * s'ajoute quand même — la société se retrouve avec deux gérants en fonction, deux lignes
 * parfaitement valides que rien ne signale.
 *
 * Liste séparée de `personnesDuDossier` : on ne propose pas un associé comme gérant sortant.
 */
export function gerantsEnFonction({ etatSociete = null } = {}) {
    return (etatSociete?.gerants ?? [])
        .filter(g => g?.nom)
        .map(g => ({
            valeur: g.nom,
            label: g.nom,
            origine: 'registre',
        }));
}

/**
 * Résout les options dynamiques d'un questionnaire, prêtes pour le rendu.
 *
 * Les moteurs appellent ceci et passent le résultat à `RepeatableGroup` : la **décision**
 * reste une fonction pure, les composants n'en portent que l'affichage.
 */
export function optionsDynamiques(contexte) {
    return {
        personnesDuDossier: personnesDuDossier(contexte),
        gerantsEnFonction:  gerantsEnFonction(contexte),
    };
}

export function champsDuRole(questionnaire, clientRole) {
    const groupe = groupFieldsBySection(questionnaire).find(g => g.clientRole === clientRole);

    return (groupe?.fields ?? []).map(f => f.id);
}

export function getPublicIntakeFields(questionnaire, clientRole) {
    const groups = groupFieldsBySection(questionnaire);
    const roleGroup = clientRole ? groups.find(g => g.clientRole === clientRole) : null;

    let roleFields = [];
    let roleLabel = null;
    let repeatableFieldId = null;
    if (roleGroup) {
        const first = roleGroup.fields[0];
        if (first?.type === 'repeatable') {
            roleFields = first.fields;
            repeatableFieldId = first.id;
        } else {
            roleFields = roleGroup.fields;
        }
        roleLabel = roleGroup.name;
    }

    // Le formulaire public **n'expose jamais** la représentation. Trois raisons, dont deux
    // structurelles :
    //
    //  1. ~~`Intake/Show.jsx` rend `roleFields` brut, sans évaluer `showIf`~~ — **corrigé le
    //     2026-09-30** : l'écran évalue désormais les conditions, comme les deux autres moteurs
    //     de rendu. Cette raison-là est donc tombée ; les deux suivantes, qui sont les vraies,
    //     tiennent seules.
    //  2. Qualifier un mandat est un acte juridique — un pouvoir est-il valable, une tutelle
    //     existe-t-elle, un gérant peut-il engager la société. Ce n'est pas au client de le
    //     dire. Même parti que `modif.types`, dont le commentaire porte déjà cette règle.
    //  3. L'intake ne produit qu'une seule `Partie` et n'accepte aucun téléversement : un
    //     représentant sans fiche et sans procuration déposée n'apporterait rien d'exploitable.
    //
    // Exclusion par construction, et non par omission d'un champ dans le schéma : celui-ci est
    // partagé, et une édition ultérieure le réexposerait en silence. Un test l'affirme pour les
    // 16 questionnaires et les 19 rôles.
    roleFields = roleFields.filter(f => !estChampRepresentation(f.id));

    // Les conditions résiduelles sont celles que le client peut réellement satisfaire —
    // typiquement « cette personne est-elle physique ou morale ». Voir conditionsInternes().
    {
        const ids = new Set(roleFields.map(f => f.id));
        roleFields = roleFields.map(f => ({ ...f, showIf: conditionsInternes(f.showIf, ids) }));
    }

    const extraFields = questionnaire
        .filter(f => f.publicIntake && f.type !== 'repeatable')
        .filter(f => !estChampRepresentation(f.id));

    // `repeatableFieldId` non nul : le rôle correspond à un bloc répétable
    // (ex. associés) — les valeurs saisies doivent être imbriquées dans un
    // tableau à une entrée sous cette clé (donnees[repeatableFieldId] = [...]),
    // pas posées à plat, sinon la Grille répétable du questionnaire interne ne
    // les affichera pas après conversion en dossier.
    return { roleFields, roleLabel, extraFields, repeatableFieldId };
}

// Liste des `clientRole` déclarés dans le schéma d'un questionnaire (sections
// non-répétables + champs repeatable), qu'ils soient peuplés ou non. Sert au
// backend à savoir quelles `Partie` remplacer sans toucher aux autres rôles.
export function getManagedClientRoles(questionnaire) {
    const roles = new Set();
    for (const group of groupFieldsBySection(questionnaire)) {
        if (group.clientRole) roles.add(group.clientRole);
    }
    for (const field of questionnaire) {
        if (field.type === 'repeatable' && field.clientRole) roles.add(field.clientRole);
    }
    // ⚠️ `mandataire` n'y figure **jamais**, et c'est voulu : la synchronisation par rôle
    // supprime toute Partie non référencée, or plusieurs représentants partagent ce rôle et le
    // front peut légitimement n'en renvoyer aucun (rôle non géré dans cette passe). Leur cycle
    // de vie passe par le ramasse-miettes du graphe côté serveur —
    // DossierController::supprimerRepresentantsOrphelins(). La règle est d'ailleurs verrouillée
    // côté serveur par une règle de validation : un payload forgé ne peut pas la contourner.
    return Array.from(roles);
}

/**
 * Les personnes déjà désignées dans ce dossier, avec la place qu'elles y tiennent.
 *
 * Remplace, depuis le 2026-11-26, la carte « Clients du dossier » et son état `dossierClients`.
 * Cette carte tenait une **seconde** liste des personnes, à côté des rôles : on y ajoutait un
 * client, puis on le retrouvait en petite puce dix cartes plus bas, sans savoir s'il tenait déjà
 * un rôle — et un client ajouté là sans être désigné nulle part était abandonné à la soumission,
 * alors que le récapitulatif l'annonçait « réutilisé ». La liste est désormais **dérivée** de ce
 * qui a un effet (les rôles) : elle ne peut plus dire autre chose qu'eux.
 *
 * `extras` : fiches proposées sans rôle dans l'acte. Deux formes acceptées — une fiche nue (les
 * personnes connues de la société, importées depuis sa fiche : cédant, gérant sortant… que le
 * registre ne sait pas attribuer seul) ou `{ client, role }` pour nommer sa place quand elle en a
 * une hors de l'acte. C'est le cas du **client du dossier** (`dossiers.client_principal_id`) :
 * il commande l'acte sans forcément y comparaître, et doit malgré tout être proposé aux rôles —
 * le plus souvent, c'est bien lui.
 *
 * @returns {Array<{client: object, roles: string[]}>}
 */
export function personnesDesignees(questionnaire = [], formValues = {}, clientLinks = {}, extras = []) {
    const parId = new Map();
    const noter = (client, role) => {
        if (!client?.id) return;
        const entree = parId.get(client.id) ?? { client, roles: [] };
        if (role && !entree.roles.includes(role)) entree.roles.push(role);
        parId.set(client.id, entree);
    };

    for (const group of groupFieldsBySection(questionnaire)) {
        if (!group.clientRole) continue;
        noter(clientLinks[group.clientRole], group.name);
        noter(clientLinks[roleRepresentant(group.clientRole)], group.name ? `Représentant — ${group.name.toLowerCase()}` : 'Représentant');
    }

    for (const field of questionnaire) {
        if (field.type !== 'repeatable' || !field.clientRole) continue;
        (formValues[field.id] ?? []).forEach((item, idx) => {
            // Même libellé que la carte de la ligne (RepeatableGroup) : on doit reconnaître
            // l'endroit d'un coup d'œil.
            noter(item?.client, `${field.label} ${idx + 1}`);
            noter(item?.representant_client, `Représentant — ${field.label.toLowerCase()} ${idx + 1}`);
        });
    }

    for (const extra of extras) {
        // Une fiche nue, ou { client, role } quand l'appelant sait nommer sa place.
        if (extra?.client) noter(extra.client, extra.role ?? null);
        else noter(extra, null);
    }

    return [...parId.values()];
}

/**
 * Reprojette une fiche client **corrigée** partout où le formulaire la cite.
 *
 * Jusqu'au 2026-11-26, corriger une fiche depuis une section scalaire ne mettait à jour que
 * `clientLinks` : les champs d'identité, masqués, gardaient l'ancienne valeur dans `formValues`.
 * Or c'est `formValues` que lisent l'avertissement « Fiche incomplète » et la liste des blocants.
 * Après « Compléter la fiche », l'écran continuait donc d'exiger la date de naissance qu'on venait
 * de saisir — jusqu'à détacher puis rattacher la fiche. Le serveur reprojetait bien à
 * l'enregistrement : seul l'écran mentait, mais il bloquait.
 *
 * Quatre emplacements, et c'est pourquoi la fonction est ici plutôt que dans un écran : section
 * scalaire, représentant d'une section scalaire, ligne de bloc, représentant d'une ligne. La même
 * personne peut occuper plusieurs d'entre eux — associé unique et gérant, par exemple.
 *
 * Pure : rend `formValues` inchangé (même référence) si la fiche n'est citée nulle part.
 * Le rattachement (`clientLinks`) se met à jour à part, par l'appelant.
 */
export function reappliquerFiche(questionnaire = [], formValues = {}, clientLinks = {}, client = null) {
    if (!client?.id) return formValues;

    const patch = {};

    for (const group of groupFieldsBySection(questionnaire)) {
        if (!group.clientRole || group.fields[0]?.type === 'repeatable') continue;
        const prefixe = group.fields[0].id.split('.')[0];
        // Le schéma **complet** du rôle — même règle que le rattachement (contrôle D).
        const ids = champsDuRole(questionnaire, group.clientRole);

        if (clientLinks[group.clientRole]?.id === client.id) {
            Object.assign(patch, mapClientToPrefixedFields(client, prefixe, ids));
        }
        if (clientLinks[roleRepresentant(group.clientRole)]?.id === client.id) {
            Object.assign(patch, mapClientToPrefixedFields(client, prefixe, ids, 'repr_'));
        }
    }

    for (const field of questionnaire) {
        if (field.type !== 'repeatable' || !field.clientRole) continue;
        const lignes = formValues[field.id];
        if (!Array.isArray(lignes)) continue;

        const ids = (field.fields ?? []).map(f => f.id);
        let change = false;
        const suivantes = lignes.map(item => {
            let suivant = item;
            if (item?.client?.id === client.id) {
                suivant = { ...suivant, ...mapClientToRepeatableItem(client, ids), client };
                change = true;
            }
            if (item?.representant_client?.id === client.id) {
                suivant = { ...suivant, ...mapClientToRepeatableItem(client, ids, 'repr_'), representant_client: client };
                change = true;
            }
            return suivant;
        });
        if (change) patch[field.id] = suivantes;
    }

    return Object.keys(patch).length > 0 ? { ...formValues, ...patch } : formValues;
}

/**
 * Remplace une fiche corrigée dans les rattachements (`clientLinks`). Pendant de
 * {@see reappliquerFiche}, pour la même raison : les deux écrans qui corrigent une fiche
 * (Create, modal de Show) le faisaient chacun à la main.
 */
export function remplacerFicheDansLiens(clientLinks = {}, client = null) {
    if (!client?.id) return clientLinks;

    return Object.fromEntries(
        Object.entries(clientLinks).map(([role, c]) => [role, c?.id === client.id ? client : c]),
    );
}

/**
 * Ce qu'une section exigera encore de cette fiche, si on la rattachait telle quelle.
 *
 * Sert au modal de création/correction de fiche, pour **signaler** — jamais bloquer — les
 * informations que l'acte reprendra : sans cela, on ne découvrait qu'après coup, par « Fiche
 * incomplète — il manque… », que l'associé unique demandait onze champs alors que la fiche n'en
 * exige qu'un (le nom). Signaler et non bloquer, par choix de l'étude (2026-11-26) : une fiche
 * client est une référence qu'on crée sans rôle et qu'on complète au fil de ses usages.
 *
 * Le calcul **simule le rattachement** avec les fonctions de projection elles-mêmes
 * (`mapClientToPrefixedFields` / `mapClientToRepeatableItem`) plutôt qu'avec une table « champ du
 * modal → champ de section » qui serait un troisième miroir à tenir. Même règle que
 * `champsIdentiteManquants()` de Create.jsx : champ requis, d'identité, de cette personne, visible
 * une fois la fiche projetée (une société ne se voit pas réclamer de date de naissance).
 *
 * @param {object}  args
 * @param {Array}   args.champs     schéma **complet** de la section (scalaire) ou de la ligne (bloc)
 * @param {object}  args.client     la fiche en cours de saisie
 * @param {object}  args.valeurs    `formValues` (scalaire) ou l'item de la ligne (bloc)
 * @param {?string} args.prefixe    `'pp'`… pour une section scalaire, `null` pour une ligne de bloc
 * @param {string}  args.personne   `'partie'` ou `'representant'`
 * @returns {string[]} libellés des champs manquants
 */
export function manquantsPourFiche({ champs = [], client = null, valeurs = {}, prefixe = null, personne = 'partie' } = {}) {
    if (!client) return [];

    const sousPrefixe = personne === 'representant' ? 'repr_' : '';
    const ids = champs.map(f => f.id);
    const projete = prefixe
        ? mapClientToPrefixedFields(client, prefixe, ids, sousPrefixe)
        : mapClientToRepeatableItem(client, ids, sousPrefixe);
    const simule = { ...valeurs, ...projete };

    return getVisibleFields(champs, simule)
        .filter(f => f.required
            && estChampIdentite(f.id)
            && personneDuChamp(f.id) === personne
            && !simule[f.id])
        .map(f => f.label);
}
