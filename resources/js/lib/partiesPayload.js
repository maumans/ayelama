import { buildPartieFields } from '@/lib/clientFields';

// Regroupe les champs d'un questionnaire par section. Une nouvelle section démarre
// à chaque champ portant `section`. Partagé entre la création et l'édition d'un
// dossier pour garder un seul rendu/une seule logique de payload `parties`.
export function groupFieldsBySection(fields) {
    const groups = [];
    let current = null;
    for (const field of fields) {
        if (field.section || !current) {
            current = { name: field.section ?? null, clientRole: field.clientRole ?? null, fields: [] };
            groups.push(current);
        }
        current.fields.push(field);
    }
    return groups;
}

// Construit le payload `parties` (dossiers.store / dossiers.questionnaire) à partir
// des sections client-liables (liées à un client existant ou saisies en texte libre)
// et des blocs répétables (associés, gérants, actionnaires…).
export function buildPartiesPayload(questionnaire, formValues, clientLinks) {
    const parties = [];

    for (const group of groupFieldsBySection(questionnaire)) {
        if (!group.clientRole) continue;
        const prefix = group.fields[0].id.split('.')[0];
        const client = clientLinks[group.clientRole] ?? null;
        const fields = buildPartieFields(client, formValues, prefix);
        if (!fields.nom) continue;
        parties.push({ ...fields, role: group.clientRole, client_id: client?.id ?? undefined });
    }

    for (const field of questionnaire) {
        if (field.type !== 'repeatable' || !field.clientRole) continue;
        const items = formValues[field.id] ?? [];
        items.forEach(item => {
            const nom = item.nom || item.prenom_nom;
            if (!nom) return;
            parties.push({
                nom,
                role: field.clientRole,
                client_id: item.client_id ?? undefined,
                cni: item.cni ?? item.piece_numero ?? null,
                telephone: item.telephone ?? null,
                adresse: item.adresse ?? item.domicile ?? null,
                email: item.email ?? null,
            });
        });
    }

    return parties;
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

    const extraFields = questionnaire.filter(f => f.publicIntake && f.type !== 'repeatable');

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
    return Array.from(roles);
}
