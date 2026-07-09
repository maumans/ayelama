// Pont entre une fiche Client (personne physique/morale réutilisable, table `clients`)
// et les champs texte libres du questionnaire (préfixés pp./ger./acq./loc./bq./liquidateur.,
// ou non préfixés dans les blocs répétables). ActesGeneratorService reste 100% générique
// clé/valeur (voir app/Services/ActesGeneratorService.php) : tant que ces fonctions déposent
// les bonnes clés dans `donnees`, la génération de documents fonctionne sans aucun changement
// côté backend.

function adresseComposite(client) {
    return [client.quartier, client.commune, client.demeurant_ville].filter(Boolean).join(', ');
}

// Remplit les champs d'un bloc scalaire préfixé (ex. prefix='pp' → pp.civilite, pp.prenom_nom…)
// à partir d'un client. Ne renseigne que les champs qui existent réellement dans ce bloc
// (fieldIds) et pour lesquels le client a une valeur — pas d'écrasement avec du vide.
export function mapClientToPrefixedFields(client, prefix, fieldIds) {
    const idSet = new Set(fieldIds);
    const values = {};
    const set = (suffix, value) => {
        const id = `${prefix}.${suffix}`;
        if (idSet.has(id) && value !== null && value !== undefined && value !== '') {
            values[id] = value;
        }
    };

    if (client.type === 'physique') {
        set('civilite', client.civilite);
        set('prenom_nom', client.prenom_nom);
        set('nom', client.prenom_nom);
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
        set('denomination', client.denomination);
        set('forme', client.forme);
        set('representant_nom', client.representant_legal);
        set('nationalite', client.pays);
        set('rccm', client.rccm);
    }

    set('quartier', client.quartier);
    set('commune', client.commune);
    set('demeurant_ville', client.demeurant_ville);
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
export function mapClientToRepeatableItem(client, fieldIds) {
    const idSet = new Set(fieldIds);
    const item = {};
    const set = (id, value) => {
        if (idSet.has(id) && value !== null && value !== undefined && value !== '') item[id] = value;
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
    set('ne_a', client.ne_a);
    set('date_naissance', client.date_naissance);

    return item;
}

export function clientDisplayName(client) {
    if (!client) return '';
    return client.type === 'physique'
        ? [client.civilite, client.prenom_nom].filter(Boolean).join(' ')
        : (client.denomination || '');
}

export function clientSubtitle(client) {
    if (!client) return '';
    return client.type === 'physique'
        ? [client.piece_numero, client.telephone].filter(Boolean).join(' · ')
        : [client.forme, client.rccm].filter(Boolean).join(' · ');
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
