import { buildPartieFields, estChampRepresentation } from '@/lib/clientFields';
import { typePersonneCanonique } from '@/lib/piecesRequises';
import { MOTIF_REPRESENTATION_PAR_LIBELLE, FORME_TITRE_PAR_LIBELLE } from '@/data/questionnaires';
import { frDateToISO } from '@/lib/dates';

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

// Construit le payload `parties` (dossiers.store / dossiers.questionnaire) à partir
// des sections client-liables (liées à un client existant ou saisies en texte libre)
// et des blocs répétables (associés, gérants, actionnaires…).
export function buildPartiesPayload(questionnaire, formValues, clientLinks, stagedPieces = {}, piecesBrouillon = {}) {
    const parties = [];
    // Compteur de clés locales. Le représentant n'a pas d'identifiant au moment où le
    // représenté est envoyé : la corrélation passe par une clé propre à cette soumission, que
    // le serveur résout en seconde passe. Un compteur et non l'index du rôle — celui-ci se
    // décale au réordonnancement d'un bloc répétable.
    let n = 0;
    const cle = () => `p${++n}`;

    for (const group of groupFieldsBySection(questionnaire)) {
        if (!group.clientRole) continue;
        const prefix = group.fields[0].id.split('.')[0];
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
            role: group.clientRole,
            client_id: client?.id ?? undefined,
            // Emplacement de ce rôle dans le questionnaire : c'est ce qui permet au
            // serveur de reprojeter l'identité depuis la fiche client sans connaître
            // le schéma (qui vit uniquement ici, en JS). Voir ClientProjectionService.
            donnees_prefixe: prefix,
            pieces: Object.keys(itemPieces).length > 0 ? itemPieces : undefined,
            pieces_brouillon: Object.keys(itemPiecesBrouillon).length > 0 ? itemPiecesBrouillon : undefined,
            ...representationDuRepresente(formValues, prefix, cle, parties, {
                client: clientLinks[roleRepresentant(group.clientRole)] ?? null,
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
            const piecesRepr = stagedPieces[`${field.id}#repr`] ?? {};
            const reprPieces = {};
            for (const key of Object.keys(piecesRepr)) {
                if (key.startsWith(`${idx}:`) && piecesRepr[key]) reprPieces[key.split(':')[1]] = piecesRepr[key];
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
    //  1. `Intake/Show.jsx` rend `roleFields` brut, sans évaluer `showIf`, et exige tous les
    //     champs `required`. Le bloc y apparaîtrait donc **déployé en entier**, et ses champs
    //     conditionnellement obligatoires seraient exigés **sans condition** : un client qui ne
    //     se fait pas représenter ne pourrait plus envoyer sa demande.
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
