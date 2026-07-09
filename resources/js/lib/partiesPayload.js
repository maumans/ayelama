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
