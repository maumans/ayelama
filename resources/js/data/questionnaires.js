// Configurations des questionnaires par type de dossier.
// Clé = identifiant frontend (utilisé dans Create.jsx et Show.jsx).
// TYPE_ACTE_CODE_MAP fait le lien avec les codes TypeActe stockés en base.
//
// Types de champs supportés :
//   text | textarea | number | date | checkbox | select | checkbox_required
//   repeatable — bloc répétable : { id, type:'repeatable', label, section, min, max, fields:[...] }

// ─────────────────────────────────────────────────────────────────────────────
// Blocs réutilisables (évite la duplication)
// ─────────────────────────────────────────────────────────────────────────────

const SOC_BASE = [
    { id: 'soc.denomination',             label: 'Dénomination sociale',                  type: 'text',     placeholder: 'Ex : Faya Distribution SARLU', required: true,  section: 'Société' },
    { id: 'soc.sigle',                    label: 'Sigle (facultatif)',                     type: 'text',     placeholder: 'Ex : FD',                      required: false },
    { id: 'soc.capital_chiffres',         label: 'Capital social (GNF)',                  type: 'text',     placeholder: '50000000',   required: true,  mono: true },
    { id: 'soc.nombre_parts',             label: 'Nombre de parts sociales',              type: 'text',     placeholder: '100',        required: true,  mono: true },
    { id: 'soc.valeur_nominale_chiffres', label: "Valeur nominale d'une part (GNF)",      type: 'text',     placeholder: '500000',     required: true,  mono: true },
    { id: 'soc.siege_quartier',           label: 'Quartier du siège social',              type: 'text',     placeholder: 'Almamya',    required: true  },
    { id: 'soc.siege_commune',            label: 'Commune du siège social',               type: 'text',     placeholder: 'Kaloum',     required: true  },
    { id: 'soc.siege_ville',              label: 'Ville du siège social',                 type: 'text',     placeholder: 'Conakry',    required: true  },
    { id: 'soc.objet_social',             label: 'Objet social',                          type: 'textarea', placeholder: 'Commerce général, import-export…', required: true },
    { id: 'soc.duree',                    label: 'Durée (années)',                         type: 'text',     placeholder: '99',         required: false },
    { id: 'soc.premier_exercice_annee',   label: '1er exercice — année',                  type: 'text',     placeholder: '2026',       required: false },
    { id: 'soc.email_societe',            label: 'Email de la société',                   type: 'text',     placeholder: 'contact@societe.com', required: false },
    { id: 'soc.telephone_societe',        label: 'Téléphone de la société',               type: 'text',     placeholder: '622 XX XX XX',       required: false },
];

const SOC_COMMISSAIRES = [
    { id: 'soc.commissaire_titulaire',   label: 'Commissaire aux comptes titulaire',       type: 'text', placeholder: 'Nom du cabinet ou expert',  required: false, section: 'Commissaires aux comptes' },
    { id: 'soc.commissaire_suppleant',   label: 'Commissaire aux comptes suppléant',       type: 'text', placeholder: 'Nom du commissaire suppléant', required: false },
];

// Associé unique (personne physique) — SARLU / SASU
const PP_ASSOCIE_UNIQUE = [
    { id: 'pp.civilite',          label: 'Civilité',                              type: 'select',   options: ['M.', 'Mme', 'Mlle'], required: true,  section: 'Associé unique' },
    { id: 'pp.prenom_nom',        label: 'Nom et prénoms',                        type: 'text',     placeholder: 'Ibrahima DIALLO', required: true  },
    { id: 'pp.ne_a',              label: 'Né(e) à',                               type: 'text',     placeholder: 'Conakry',         required: true  },
    { id: 'pp.date_naissance',    label: 'Date de naissance (JJ/MM/AAAA)',         type: 'text',     placeholder: '15/03/1985',      required: true  },
    { id: 'pp.nationalite',       label: 'Nationalité',                            type: 'text',     placeholder: 'Guinéenne',       required: false },
    { id: 'pp.situation_matrimoniale', label: 'Situation matrimoniale',            type: 'select',   options: ['Célibataire', 'Marié(e)', 'Divorcé(e)', 'Veuf/Veuve'], required: false },
    { id: 'pp.regime_matrimonial',     label: 'Régime matrimonial',               type: 'text',     placeholder: 'Communauté de biens / Séparation', required: false },
    { id: 'pp.quartier',          label: 'Quartier (résidence)',                   type: 'text',     placeholder: 'Almamya',         required: true  },
    { id: 'pp.commune',           label: 'Commune (résidence)',                    type: 'text',     placeholder: 'Kaloum',          required: true  },
    { id: 'pp.demeurant_ville',   label: 'Ville (résidence)',                      type: 'text',     placeholder: 'Conakry',         required: true  },
    { id: 'pp.pays',              label: 'Pays de résidence',                      type: 'text',     placeholder: 'Guinée',          required: false },
    { id: 'pp.piece_type',        label: "Type de pièce d'identité",              type: 'text',     placeholder: 'CNI CEDEAO / Passeport',           required: true  },
    { id: 'pp.piece_numero',      label: 'Numéro de pièce',                       type: 'text',     placeholder: 'GN00123456', required: true,  mono: true },
    { id: 'pp.piece_delivree_le', label: 'Pièce délivrée le (JJ/MM/AAAA)',        type: 'text',     placeholder: '01/01/2020', required: true  },
    { id: 'pp.piece_delivree_a',  label: 'Délivrée à',                            type: 'text',     placeholder: 'Conakry',    required: true  },
    { id: 'pp.piece_expire_le',   label: 'Expire le (JJ/MM/AAAA)',                type: 'text',     placeholder: '01/01/2030', required: false },
    { id: 'pp.telephone',         label: 'Téléphone',                              type: 'text',     placeholder: '622 XX XX XX', required: false },
    { id: 'pp.email',             label: 'Email',                                  type: 'text',     placeholder: 'email@exemple.com', required: false },
];

// Gérant (personne physique)
const GER_FIELDS = [
    { id: 'ger.civilite',          label: 'Civilité du gérant',                   type: 'select',   options: ['M.', 'Mme', 'Mlle'], required: true,  section: 'Gérant' },
    { id: 'ger.prenom_nom',        label: 'Nom et prénoms',                        type: 'text',     placeholder: 'Ibrahima DIALLO', required: true  },
    { id: 'ger.ne_a',              label: 'Né(e) à',                               type: 'text',     placeholder: 'Conakry',         required: true  },
    { id: 'ger.date_naissance',    label: 'Date de naissance (JJ/MM/AAAA)',         type: 'text',     placeholder: '15/03/1985',      required: true  },
    { id: 'ger.nationalite',       label: 'Nationalité',                            type: 'text',     placeholder: 'Guinéenne',       required: false },
    { id: 'ger.situation_matrimoniale', label: 'Situation matrimoniale',            type: 'select',   options: ['Célibataire', 'Marié(e)', 'Divorcé(e)', 'Veuf/Veuve'], required: false },
    { id: 'ger.quartier',          label: 'Quartier (résidence)',                   type: 'text',     placeholder: 'Almamya',         required: false },
    { id: 'ger.commune',           label: 'Commune (résidence)',                    type: 'text',     placeholder: 'Kaloum',          required: false },
    { id: 'ger.demeurant_ville',   label: 'Ville (résidence)',                      type: 'text',     placeholder: 'Conakry',         required: false },
    { id: 'ger.pays',              label: 'Pays de résidence',                      type: 'text',     placeholder: 'Guinée',          required: false },
    { id: 'ger.piece_type',        label: "Type de pièce d'identité",              type: 'text',     placeholder: 'CNI CEDEAO / Passeport', required: true },
    { id: 'ger.piece_numero',      label: 'Numéro de pièce',                       type: 'text',     placeholder: 'GN00123456', required: true,  mono: true },
    { id: 'ger.piece_delivree_le', label: 'Pièce délivrée le (JJ/MM/AAAA)',        type: 'text',     placeholder: '01/01/2020', required: true  },
    { id: 'ger.piece_delivree_a',  label: 'Délivrée à',                            type: 'text',     placeholder: 'Conakry',    required: true  },
    { id: 'ger.piece_expire_le',   label: 'Expire le (JJ/MM/AAAA)',                type: 'text',     placeholder: '01/01/2030', required: false },
    { id: 'ger.telephone',         label: 'Téléphone',                              type: 'text',     placeholder: '622 XX XX XX', required: false },
    { id: 'ger.email',             label: 'Email',                                  type: 'text',     placeholder: 'email@exemple.com', required: false },
];

// Schéma d'un associé dans un bloc répétable
const ASSOCIE_SCHEMA = [
    { id: 'nom',           label: 'Nom et prénoms / Dénomination',  type: 'text',   placeholder: 'Ibrahima DIALLO',      required: true  },
    { id: 'type_personne', label: 'Type',                           type: 'select', options: ['Personne physique', 'Personne morale'], required: true },
    { id: 'parts_chiffres',label: 'Nombre de parts',                type: 'text',   placeholder: '100', required: true,  mono: true },
    { id: 'nationalite',   label: 'Nationalité / Pays',             type: 'text',   placeholder: 'Guinéenne', required: false },
    { id: 'adresse',       label: 'Adresse',                        type: 'text',   placeholder: 'Quartier, Commune, Ville', required: false },
    { id: 'cni',           label: "Pièce d'identité / RCCM",        type: 'text',   placeholder: 'GN00123456 / GN-CON-2020-B-XXXX', required: false, mono: true },
];

// Schéma d'un gérant dans un bloc répétable (SARL multi-gérants)
const GERANT_SCHEMA = [
    { id: 'civilite',      label: 'Civilité',                       type: 'select', options: ['M.', 'Mme', 'Mlle'], required: true },
    { id: 'prenom_nom',    label: 'Nom et prénoms',                  type: 'text',   placeholder: 'Ibrahima DIALLO', required: true },
    { id: 'ne_a',          label: 'Né(e) à',                        type: 'text',   placeholder: 'Conakry', required: false },
    { id: 'date_naissance',label: 'Date de naissance (JJ/MM/AAAA)', type: 'text',   placeholder: '15/03/1985', required: false },
    { id: 'nationalite',   label: 'Nationalité',                    type: 'text',   placeholder: 'Guinéenne', required: false },
    { id: 'adresse',       label: 'Adresse',                        type: 'text',   placeholder: 'Quartier, Commune, Ville', required: false },
    { id: 'piece_numero',  label: "N° pièce d'identité",            type: 'text',   placeholder: 'GN00123456', required: false, mono: true },
];

// Schéma d'un administrateur (SA — Conseil d'Administration)
const ADMIN_SCHEMA = [
    { id: 'prenom_nom',    label: 'Nom et prénoms',                  type: 'text',   placeholder: 'Ibrahima DIALLO', required: true },
    { id: 'nationalite',   label: 'Nationalité',                    type: 'text',   placeholder: 'Guinéenne', required: false },
    { id: 'domicile',      label: 'Domicile',                       type: 'text',   placeholder: 'Conakry, Guinée', required: false },
    { id: 'fonction',      label: 'Fonction au CA',                  type: 'text',   placeholder: 'Administrateur', required: false },
];

// Bailleur (personne physique) — Bail
const PP_BAILLEUR = [
    { id: 'pp.civilite',      label: 'Civilité du bailleur',         type: 'select', options: ['M.', 'Mme', 'Mlle', 'M. et Mme'], required: true,  section: 'Bailleur' },
    { id: 'pp.prenom_nom',    label: 'Nom et prénoms',                type: 'text',   placeholder: 'Ibrahima DIALLO', required: true  },
    { id: 'pp.nationalite',   label: 'Nationalité',                   type: 'text',   placeholder: 'Guinéenne', required: false },
    { id: 'pp.adresse',       label: 'Adresse du bailleur',           type: 'text',   placeholder: 'Quartier, Commune, Ville', required: true  },
    { id: 'pp.piece_type',    label: "Type de pièce d'identité",      type: 'text',   placeholder: 'CNI CEDEAO / Passeport', required: true  },
    { id: 'pp.piece_numero',  label: 'Numéro de pièce',               type: 'text',   placeholder: 'GN00123456', required: true,  mono: true },
    { id: 'pp.telephone',     label: 'Téléphone bailleur',            type: 'text',   placeholder: '622 XX XX XX', required: false },
    { id: 'pp.email',         label: 'Email bailleur',                type: 'text',   placeholder: 'email@exemple.com', required: false },
];

// Locataire / Preneur (loc.*) — Bail
const LOC_PRENEUR = [
    { id: 'loc.civilite',     label: 'Civilité du locataire/preneur',  type: 'select', options: ['M.', 'Mme', 'Mlle', 'M. et Mme', 'Société'], required: true,  section: 'Locataire / Preneur' },
    { id: 'loc.prenom_nom',   label: 'Nom et prénoms / Dénomination',  type: 'text',   placeholder: 'Mariama SOW / Société XYZ SARL', required: true  },
    { id: 'loc.nationalite',  label: 'Nationalité / Pays',              type: 'text',   placeholder: 'Guinéenne', required: false },
    { id: 'loc.adresse',      label: 'Adresse du locataire',            type: 'text',   placeholder: 'Quartier, Commune, Ville', required: true  },
    { id: 'loc.piece_type',   label: "Type de pièce",                   type: 'text',   placeholder: 'CNI CEDEAO / RCCM', required: true  },
    { id: 'loc.piece_numero', label: 'Numéro de pièce',                 type: 'text',   placeholder: 'GN00123456', required: true,  mono: true },
    { id: 'loc.telephone',    label: 'Téléphone locataire',             type: 'text',   placeholder: '622 XX XX XX', required: false },
    { id: 'loc.email',        label: 'Email locataire',                 type: 'text',   placeholder: 'email@exemple.com', required: false },
];

// ─────────────────────────────────────────────────────────────────────────────
// QUESTIONNAIRES
// ─────────────────────────────────────────────────────────────────────────────

export const QUESTIONNAIRES = {

    // ── SARLU — Associé unique ──────────────────────────────────────────────
    creation_sarlu: [
        ...SOC_BASE,
        ...PP_ASSOCIE_UNIQUE,
        // Gérant : souvent l'associé unique lui-même → champs masqués par défaut
        { id: 'ger.est_different',         label: "Le gérant est une personne différente de l'associé unique", type: 'checkbox', section: 'Gérant', required: false },
        { id: 'ger.civilite',              label: 'Civilité du gérant',                type: 'select', options: ['M.', 'Mme', 'Mlle'], required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.prenom_nom',            label: 'Nom et prénoms',                    type: 'text',   placeholder: 'Ibrahima DIALLO',   required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.ne_a',                  label: 'Né(e) à',                           type: 'text',   placeholder: 'Conakry',           required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.date_naissance',        label: 'Date de naissance (JJ/MM/AAAA)',     type: 'text',   placeholder: '15/03/1985',        required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.nationalite',           label: 'Nationalité',                       type: 'text',   placeholder: 'Guinéenne',         required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.situation_matrimoniale',label: 'Situation matrimoniale',            type: 'select', options: ['Célibataire', 'Marié(e)', 'Divorcé(e)', 'Veuf/Veuve'], required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.quartier',              label: 'Quartier (résidence)',               type: 'text',   placeholder: 'Almamya',           required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.commune',               label: 'Commune (résidence)',                type: 'text',   placeholder: 'Kaloum',            required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.demeurant_ville',       label: 'Ville (résidence)',                  type: 'text',   placeholder: 'Conakry',           required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.pays',                  label: 'Pays de résidence',                  type: 'text',   placeholder: 'Guinée',            required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.piece_type',            label: "Type de pièce d'identité",          type: 'text',   placeholder: 'CNI CEDEAO / Passeport', required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.piece_numero',          label: 'Numéro de pièce',                   type: 'text',   placeholder: 'GN00123456',        required: false, mono: true, showIf: { field: 'ger.est_different' } },
        { id: 'ger.piece_delivree_le',     label: 'Pièce délivrée le (JJ/MM/AAAA)',    type: 'text',   placeholder: '01/01/2020',        required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.piece_delivree_a',      label: 'Délivrée à',                        type: 'text',   placeholder: 'Conakry',           required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.telephone',             label: 'Téléphone',                         type: 'text',   placeholder: '622 XX XX XX',      required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.email',                 label: 'Email',                             type: 'text',   placeholder: 'email@exemple.com', required: false, showIf: { field: 'ger.est_different' } },
        ...SOC_COMMISSAIRES,
    ],

    // ── SARL — Multi-associés ───────────────────────────────────────────────
    creation_sarl: [
        ...SOC_BASE,
        {
            id: 'associes', type: 'repeatable', label: 'Associés', section: 'Associés',
            min: 2, max: 10,
            fields: ASSOCIE_SCHEMA,
        },
        {
            id: 'gerants', type: 'repeatable', label: 'Gérant(s)', section: 'Gérant(s)',
            min: 1, max: 5,
            fields: GERANT_SCHEMA,
        },
        ...SOC_COMMISSAIRES,
    ],

    // ── SA — Société Anonyme ────────────────────────────────────────────────
    creation_sa: [
        { id: 'soc.denomination',             label: 'Dénomination sociale',                  type: 'text',     placeholder: 'Ex : Faya Holdings SA', required: true,  section: 'Société' },
        { id: 'soc.sigle',                    label: 'Sigle (facultatif)',                     type: 'text',     placeholder: 'Ex : FH',              required: false },
        { id: 'soc.capital_chiffres',         label: 'Capital social (GNF — min. 140 000 000)', type: 'text',   placeholder: '140000000', required: true,  mono: true },
        { id: 'soc.capital_libere_chiffres',  label: 'Capital libéré à la constitution (min. 35 000 000)', type: 'text', placeholder: '35000000', required: true, mono: true },
        { id: 'soc.nombre_actions',           label: "Nombre d'actions",                      type: 'text',     placeholder: '14000',     required: true,  mono: true },
        { id: 'soc.valeur_nominale_chiffres', label: "Valeur nominale d'une action (GNF)",    type: 'text',     placeholder: '10000',     required: true,  mono: true },
        { id: 'soc.siege_quartier',           label: 'Quartier du siège social',              type: 'text',     placeholder: 'Almamya',   required: true  },
        { id: 'soc.siege_commune',            label: 'Commune du siège social',               type: 'text',     placeholder: 'Kaloum',    required: true  },
        { id: 'soc.siege_ville',              label: 'Ville du siège social',                 type: 'text',     placeholder: 'Conakry',   required: true  },
        { id: 'soc.objet_social',             label: 'Objet social',                          type: 'textarea', placeholder: 'Commerce général, import-export…', required: true },
        { id: 'soc.duree',                    label: 'Durée (années)',                         type: 'text',     placeholder: '99',        required: false },
        { id: 'soc.premier_exercice_annee',   label: '1er exercice — année',                  type: 'text',     placeholder: '2026',      required: false },
        { id: 'soc.email_societe',            label: 'Email de la société',                   type: 'text',     placeholder: 'contact@societe.com', required: false },
        { id: 'soc.telephone_societe',        label: 'Téléphone de la société',               type: 'text',     placeholder: '622 XX XX XX',       required: false },
        {
            id: 'actionnaires', type: 'repeatable', label: 'Actionnaires', section: 'Actionnaires',
            min: 2, max: 20,
            fields: [
                { id: 'nom',            label: 'Nom / Dénomination',    type: 'text',   placeholder: 'Ibrahima DIALLO', required: true },
                { id: 'type_personne',  label: 'Type',                  type: 'select', options: ['Personne physique', 'Personne morale'], required: true },
                { id: 'actions_chiffres', label: "Nombre d'actions",    type: 'text',   placeholder: '1000', required: true, mono: true },
                { id: 'nationalite',    label: 'Nationalité / Pays',    type: 'text',   placeholder: 'Guinéenne', required: false },
            ],
        },
        {
            id: 'administrateurs', type: 'repeatable', label: "Membres du Conseil d'Administration", section: "Conseil d'Administration",
            min: 3, max: 12,
            fields: ADMIN_SCHEMA,
        },
        { id: 'soc.pca_nom',            label: "Président du Conseil d'Administration (PCA)", type: 'text', placeholder: 'Nom du PCA', required: true,  section: 'Direction' },
        { id: 'soc.pca_civilite',       label: 'Civilité PCA',                               type: 'select', options: ['M.', 'Mme'], required: true  },
        { id: 'soc.pca_adresse',        label: 'Adresse du PCA',                             type: 'text', placeholder: 'Quartier, Commune, Ville', required: false },
        { id: 'soc.dg_nom',             label: 'Directeur Général (DG)',                     type: 'text', placeholder: 'Nom du DG', required: false },
        { id: 'soc.dg_civilite',        label: 'Civilité DG',                                type: 'select', options: ['M.', 'Mme'], required: false },
        { id: 'soc.commissaire_titulaire',  label: 'Commissaire aux comptes titulaire',       type: 'text', placeholder: 'Nom du cabinet', required: true,  section: 'Commissaires aux comptes' },
        { id: 'soc.commissaire_suppleant',  label: 'Commissaire aux comptes suppléant',       type: 'text', placeholder: 'Nom du commissaire suppléant', required: true },
    ],

    // ── SAS — Multi-associés ────────────────────────────────────────────────
    creation_sas: [
        ...SOC_BASE,
        {
            id: 'associes', type: 'repeatable', label: 'Associés', section: 'Associés',
            min: 2, max: 20,
            fields: ASSOCIE_SCHEMA,
        },
        { id: 'soc.president_nom',      label: 'Président de la SAS',                        type: 'text',   placeholder: 'Ibrahima DIALLO', required: true,  section: 'Président' },
        { id: 'soc.president_civilite', label: 'Civilité',                                   type: 'select', options: ['M.', 'Mme', 'Mlle'], required: true  },
        { id: 'soc.president_ne_a',     label: 'Né(e) à',                                    type: 'text',   placeholder: 'Conakry', required: false },
        { id: 'soc.president_date_naissance', label: 'Date de naissance (JJ/MM/AAAA)',        type: 'text',   placeholder: '15/03/1985', required: false },
        { id: 'soc.president_nationalite',    label: 'Nationalité',                           type: 'text',   placeholder: 'Guinéenne', required: false },
        { id: 'soc.president_adresse',        label: 'Adresse',                               type: 'text',   placeholder: 'Quartier, Commune, Ville', required: false },
        { id: 'soc.president_piece_numero',   label: "N° pièce d'identité",                  type: 'text',   placeholder: 'GN00123456', required: false, mono: true },
        { id: 'soc.dg_nom',             label: 'Directeur Général (facultatif)',              type: 'text',   placeholder: 'Nom du DG', required: false, section: 'Direction' },
        ...SOC_COMMISSAIRES,
    ],

    // ── SASU — Associé unique ───────────────────────────────────────────────
    creation_sasu: [
        ...SOC_BASE,
        ...PP_ASSOCIE_UNIQUE,
        // Président : souvent l'associé unique → champs masqués par défaut
        { id: 'soc.president_est_different', label: "Le président est une personne différente de l'associé unique", type: 'checkbox', section: 'Président', required: false },
        { id: 'soc.president_civilite',      label: 'Civilité',                               type: 'select', options: ['M.', 'Mme', 'Mlle'], required: false, showIf: { field: 'soc.president_est_different' } },
        { id: 'soc.president_nom',           label: 'Nom et prénoms du président',            type: 'text',   placeholder: 'Ibrahima DIALLO',          required: false, showIf: { field: 'soc.president_est_different' } },
        { id: 'soc.president_adresse',       label: 'Adresse',                                type: 'text',   placeholder: 'Quartier, Commune, Ville', required: false, showIf: { field: 'soc.president_est_different' } },
        { id: 'soc.president_piece_numero',  label: "N° pièce d'identité",                   type: 'text',   placeholder: 'GN00123456',               required: false, mono: true, showIf: { field: 'soc.president_est_different' } },
        ...SOC_COMMISSAIRES,
    ],

    // ── SNC — Société en Nom Collectif ──────────────────────────────────────
    creation_snc: [
        ...SOC_BASE,
        {
            id: 'associes', type: 'repeatable', label: 'Associés (responsabilité illimitée)', section: 'Associés',
            min: 2, max: 10,
            fields: [
                { id: 'nom',           label: 'Nom et prénoms',         type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
                { id: 'apport_chiffres', label: 'Apport (GNF)',         type: 'text', placeholder: '25000000', required: true, mono: true },
                { id: 'nationalite',   label: 'Nationalité',            type: 'text', placeholder: 'Guinéenne', required: false },
                { id: 'adresse',       label: 'Adresse',                type: 'text', placeholder: 'Quartier, Commune, Ville', required: false },
                { id: 'cni',           label: "N° pièce d'identité",    type: 'text', placeholder: 'GN00123456', required: false, mono: true },
            ],
        },
        {
            id: 'gerants', type: 'repeatable', label: 'Gérant(s)', section: 'Gérant(s)',
            min: 1, max: 5,
            fields: GERANT_SCHEMA,
        },
    ],

    // ── GIE — Groupement d'Intérêt Économique ──────────────────────────────
    creation_gie: [
        { id: 'soc.denomination',    label: 'Dénomination du groupement',         type: 'text',     placeholder: 'Ex : GIE Agricole de Guinée', required: true,  section: 'Groupement' },
        { id: 'soc.capital_chiffres',label: 'Capital (GNF — facultatif)',          type: 'text',     placeholder: '0 si pas de capital',          required: false, mono: true },
        { id: 'soc.siege_quartier',  label: 'Quartier du siège',                  type: 'text',     placeholder: 'Almamya',  required: true  },
        { id: 'soc.siege_commune',   label: 'Commune du siège',                   type: 'text',     placeholder: 'Kaloum',   required: true  },
        { id: 'soc.siege_ville',     label: 'Ville du siège',                     type: 'text',     placeholder: 'Conakry',  required: true  },
        { id: 'soc.objet_social',    label: 'Objet du groupement',                type: 'textarea', placeholder: 'Activités communes, mutualisation…', required: true },
        { id: 'soc.duree',           label: 'Durée (années)',                      type: 'text',     placeholder: '10',       required: false },
        {
            id: 'membres', type: 'repeatable', label: 'Membres', section: 'Membres',
            min: 2, max: 20,
            fields: [
                { id: 'nom',           label: 'Nom / Dénomination',     type: 'text',   placeholder: 'Ibrahima DIALLO', required: true },
                { id: 'type_personne', label: 'Type',                   type: 'select', options: ['Personne physique', 'Personne morale'], required: true },
                { id: 'apport_chiffres', label: 'Apport (GNF)',         type: 'text',   placeholder: '5000000', required: false, mono: true },
                { id: 'adresse',       label: 'Adresse',                type: 'text',   placeholder: 'Quartier, Commune, Ville', required: false },
            ],
        },
        {
            id: 'administrateurs', type: 'repeatable', label: 'Administrateur(s)', section: 'Administration',
            min: 1, max: 5,
            fields: [
                { id: 'prenom_nom',  label: 'Nom et prénoms',  type: 'text',   placeholder: 'Ibrahima DIALLO', required: true },
                { id: 'fonction',    label: 'Fonction',        type: 'text',   placeholder: 'Président / Administrateur', required: false },
                { id: 'adresse',     label: 'Adresse',         type: 'text',   placeholder: 'Quartier, Commune, Ville', required: false },
            ],
        },
        ...SOC_COMMISSAIRES,
    ],

    // ── Dissolution ─────────────────────────────────────────────────────────
    dissolution: [
        { id: 'soc.denomination',  label: 'Dénomination de la société dissoute', type: 'text',     placeholder: 'Faya Distribution SARLU', required: true,  section: 'Société dissoute' },
        { id: 'soc.forme',         label: 'Forme juridique',                     type: 'select',   options: ['SARLU', 'SARL', 'SA', 'SAS', 'SASU', 'SNC', 'GIE'], required: true },
        { id: 'soc.rccm',          label: 'Numéro RCCM',                         type: 'text',     placeholder: 'GN-CON-2020-B-XXXX', required: true,  mono: true },
        { id: 'soc.capital_chiffres', label: 'Capital social (GNF)',             type: 'text',     placeholder: '50000000', required: true,  mono: true },
        { id: 'soc.siege_quartier',   label: 'Quartier du siège',               type: 'text',     placeholder: 'Almamya',  required: true  },
        { id: 'soc.siege_commune',    label: 'Commune du siège',                type: 'text',     placeholder: 'Kaloum',   required: true  },
        { id: 'soc.siege_ville',      label: 'Ville du siège',                  type: 'text',     placeholder: 'Conakry',  required: true  },
        { id: 'dissolution.date_assemblee', label: "Date de l'assemblée de dissolution (JJ/MM/AAAA)", type: 'text', placeholder: '01/07/2026', required: true,  section: 'Décision de dissolution' },
        { id: 'dissolution.raison',   label: 'Raison de dissolution',           type: 'textarea', placeholder: 'Décision des associés / Objet réalisé / Autres…', required: true },
        { id: 'dissolution.type',     label: 'Type de dissolution',             type: 'select',   options: ['Amiable', 'Judiciaire'], required: true },
        { id: 'liquidateur.nom',      label: 'Nom du liquidateur',              type: 'text',     placeholder: 'Ibrahima DIALLO', required: true,  section: 'Liquidateur' },
        { id: 'liquidateur.qualite',  label: 'Qualité du liquidateur',          type: 'text',     placeholder: 'Associé / Tiers désigné', required: true  },
        { id: 'liquidateur.adresse',  label: 'Adresse du liquidateur',          type: 'text',     placeholder: 'Quartier, Commune, Ville', required: false },
    ],

    // ── Vente immobilière avec titre foncier ────────────────────────────────
    vente_immeuble: [
        { id: 'pp.civilite',       label: 'Civilité du vendeur',               type: 'select',   options: ['M.', 'Mme', 'Mlle', 'M. et Mme'], required: true,  section: 'Vendeur' },
        { id: 'pp.prenom_nom',     label: 'Nom et prénoms du vendeur',         type: 'text',     placeholder: 'Ibrahima DIALLO', required: true  },
        { id: 'pp.nationalite',    label: 'Nationalité',                        type: 'text',     placeholder: 'Guinéenne', required: false },
        { id: 'pp.adresse',        label: 'Adresse du vendeur',                 type: 'text',     placeholder: 'Quartier, Commune, Ville', required: true  },
        { id: 'pp.piece_type',     label: "Type de pièce",                      type: 'text',     placeholder: 'CNI CEDEAO / Passeport', required: true  },
        { id: 'pp.piece_numero',   label: 'Numéro de pièce',                    type: 'text',     placeholder: 'GN00123456', required: true,  mono: true },
        { id: 'pp.telephone',      label: 'Téléphone vendeur',                  type: 'text',     placeholder: '622 XX XX XX', required: false },
        { id: 'pp.email',          label: 'Email vendeur',                       type: 'text',     placeholder: 'email@exemple.com', required: false },
        { id: 'acq.civilite',      label: "Civilité de l'acquéreur",            type: 'select',   options: ['M.', 'Mme', 'Mlle', 'M. et Mme'], required: true,  section: 'Acquéreur' },
        { id: 'acq.prenom_nom',    label: "Nom et prénoms de l'acquéreur",      type: 'text',     placeholder: 'Mariama SOW', required: true  },
        { id: 'acq.nationalite',   label: 'Nationalité',                        type: 'text',     placeholder: 'Guinéenne', required: false },
        { id: 'acq.adresse',       label: "Adresse de l'acquéreur",             type: 'text',     placeholder: 'Quartier, Commune, Ville', required: true  },
        { id: 'acq.piece_type',    label: "Type de pièce",                      type: 'text',     placeholder: 'CNI CEDEAO / Passeport', required: true  },
        { id: 'acq.piece_numero',  label: 'Numéro de pièce',                    type: 'text',     placeholder: 'GN00123456', required: true,  mono: true },
        { id: 'acq.telephone',     label: "Téléphone acquéreur",                type: 'text',     placeholder: '622 XX XX XX', required: false },
        { id: 'acq.email',         label: "Email acquéreur",                    type: 'text',     placeholder: 'email@exemple.com', required: false },
        { id: 'bien.parcelle_numero', label: 'Numéro de parcelle',             type: 'text',     placeholder: 'P-001', required: false, mono: true, section: 'Bien immobilier' },
        { id: 'bien.lot',             label: 'Lot',                            type: 'text',     placeholder: 'Lot 12', required: false },
        { id: 'bien.lieu_de',         label: 'Situé à',                        type: 'text',     placeholder: 'Quartier, Commune, Ville', required: true  },
        { id: 'bien.nature_terrain',  label: 'Nature du terrain',              type: 'text',     placeholder: 'Terrain nu / Immeuble bâti', required: true  },
        { id: 'bien.usage',           label: 'Usage',                          type: 'select',   options: ['Résidentiel', 'Commercial', 'Industriel', 'Mixte', 'Agricole'], required: true },
        { id: 'bien.superficie',      label: 'Superficie (m²)',                type: 'text',     placeholder: '500', required: true,  mono: true },
        { id: 'bien.pcp',             label: 'PCP (Plan Cadastral Parcellaire)', type: 'text',   placeholder: 'PCP-XXX', required: false, mono: true },
        { id: 'bien.titre_foncier_numero', label: 'Numéro du titre foncier',  type: 'text',     placeholder: 'TF-2018-KAL-004521', required: true,  mono: true },
        { id: 'bien.limite_nord',     label: 'Limite Nord',                    type: 'text',     placeholder: 'Rue de la Paix', required: false },
        { id: 'bien.limite_sud',      label: 'Limite Sud',                     type: 'text',     placeholder: 'Parcelle de M. Camara', required: false },
        { id: 'bien.limite_est',      label: 'Limite Est',                     type: 'text',     placeholder: 'Route Nationale', required: false },
        { id: 'bien.limite_ouest',    label: 'Limite Ouest',                   type: 'text',     placeholder: 'Cours d\'eau', required: false },
        { id: 'bien.origine_propriete', label: 'Origine de la propriété',      type: 'textarea', placeholder: 'Achat selon acte du…', required: false },
        { id: 'bien.prix_vente_chiffres', label: 'Prix de vente (GNF)',        type: 'text',     placeholder: '250000000', required: true,  mono: true, section: 'Transaction' },
        { id: 'transaction.taxe_plusvalue_chiffres', label: 'Taxe de plus-value (GNF)', type: 'text', placeholder: '0', required: false, mono: true },
        { id: 'transaction.provision_chiffres',      label: 'Provision réclamée (GNF)',  type: 'text', placeholder: '5000000', required: false, mono: true },
    ],

    // ── Vente immobilière sans titre foncier ────────────────────────────────
    vente_sans_titre: [
        { id: 'pp.civilite',       label: 'Civilité du vendeur',               type: 'select',   options: ['M.', 'Mme', 'Mlle', 'M. et Mme'], required: true,  section: 'Vendeur' },
        { id: 'pp.prenom_nom',     label: 'Nom et prénoms du vendeur',         type: 'text',     placeholder: 'Ibrahima DIALLO', required: true  },
        { id: 'pp.nationalite',    label: 'Nationalité',                        type: 'text',     placeholder: 'Guinéenne', required: false },
        { id: 'pp.adresse',        label: 'Adresse du vendeur',                 type: 'text',     placeholder: 'Quartier, Commune, Ville', required: true  },
        { id: 'pp.piece_type',     label: "Type de pièce",                      type: 'text',     placeholder: 'CNI CEDEAO / Passeport', required: true  },
        { id: 'pp.piece_numero',   label: 'Numéro de pièce',                    type: 'text',     placeholder: 'GN00123456', required: true,  mono: true },
        { id: 'pp.telephone',      label: 'Téléphone vendeur',                  type: 'text',     placeholder: '622 XX XX XX', required: false },
        { id: 'acq.civilite',      label: "Civilité de l'acquéreur",            type: 'select',   options: ['M.', 'Mme', 'Mlle', 'M. et Mme'], required: true,  section: 'Acquéreur' },
        { id: 'acq.prenom_nom',    label: "Nom et prénoms de l'acquéreur",      type: 'text',     placeholder: 'Mariama SOW', required: true  },
        { id: 'acq.nationalite',   label: 'Nationalité',                        type: 'text',     placeholder: 'Guinéenne', required: false },
        { id: 'acq.adresse',       label: "Adresse de l'acquéreur",             type: 'text',     placeholder: 'Quartier, Commune, Ville', required: true  },
        { id: 'acq.piece_type',    label: "Type de pièce",                      type: 'text',     placeholder: 'CNI CEDEAO / Passeport', required: true  },
        { id: 'acq.piece_numero',  label: 'Numéro de pièce',                    type: 'text',     placeholder: 'GN00123456', required: true,  mono: true },
        { id: 'bien.lieu_de',         label: 'Situé à',                        type: 'text',     placeholder: 'Quartier, Commune, Ville', required: true,  section: 'Bien immobilier' },
        { id: 'bien.nature_terrain',  label: 'Nature du terrain',              type: 'text',     placeholder: 'Terrain nu / Immeuble bâti', required: true  },
        { id: 'bien.usage',           label: 'Usage',                          type: 'select',   options: ['Résidentiel', 'Commercial', 'Industriel', 'Mixte', 'Agricole'], required: true },
        { id: 'bien.superficie',      label: 'Superficie (m²)',                type: 'text',     placeholder: '500', required: true,  mono: true },
        { id: 'bien.autorisation_occuper', label: "Autorisation d'occuper / Acte de cession", type: 'text', placeholder: 'AO-XXX / Référence', required: false, mono: true },
        { id: 'bien.origine_propriete', label: 'Origine de la propriété',      type: 'textarea', placeholder: 'Achat selon acte du…', required: false },
        { id: 'bien.prix_vente_chiffres', label: 'Prix de vente (GNF)',        type: 'text',     placeholder: '250000000', required: true,  mono: true, section: 'Transaction' },
        { id: 'transaction.taxe_plusvalue_chiffres', label: 'Taxe de plus-value (GNF)', type: 'text', placeholder: '0', required: false, mono: true },
        { id: 'transaction.provision_chiffres',      label: 'Provision réclamée (GNF)',  type: 'text', placeholder: '5000000', required: false, mono: true },
    ],

    // ── Bail d'habitation ───────────────────────────────────────────────────
    bail_habitation: [
        ...PP_BAILLEUR,
        ...LOC_PRENEUR,
        { id: 'bien.adresse',              label: 'Adresse du bien loué',                type: 'text',     placeholder: 'Quartier, Commune, Ville',       required: true,  section: 'Bien immobilier' },
        { id: 'bien.description',          label: 'Description du bien',                 type: 'textarea', placeholder: 'Villa 4 pièces / Appartement F3…', required: false },
        { id: 'bien.superficie',           label: 'Superficie (m²)',                     type: 'text',     placeholder: '120', required: false, mono: true },
        { id: 'bien.usage',                label: 'Usage',                               type: 'select',   options: ['Résidentiel', 'Usage mixte'], required: true },
        { id: 'bail.date_prise_effet',     label: "Date de prise d'effet (JJ/MM/AAAA)", type: 'text',     placeholder: '01/08/2026', required: true,  section: 'Conditions du bail' },
        { id: 'bail.duree_chiffres',       label: 'Durée du bail (années)',              type: 'text',     placeholder: '2', required: true,  mono: true },
        { id: 'bail.loyer_chiffres',       label: 'Loyer mensuel (GNF)',                 type: 'text',     placeholder: '5000000', required: true,  mono: true },
        { id: 'bail.periodicite',          label: 'Périodicité du paiement',             type: 'select',   options: ['Mensuel', 'Trimestriel', 'Semestriel', 'Annuel'], required: true },
        { id: 'bail.caution_chiffres',     label: 'Caution (GNF)',                       type: 'text',     placeholder: '10000000', required: false, mono: true },
        { id: 'bail.avance_loyer',         label: "Avance sur loyer (mois)",             type: 'text',     placeholder: '3', required: false, mono: true },
        { id: 'bail.destination',          label: 'Destination des lieux',               type: 'text',     placeholder: 'Habitation principale', required: false },
    ],

    // ── Bail commercial ─────────────────────────────────────────────────────
    bail_commercial: [
        ...PP_BAILLEUR,
        ...LOC_PRENEUR,
        { id: 'bien.adresse',              label: 'Adresse du local commercial',         type: 'text',     placeholder: 'Quartier, Commune, Ville',        required: true,  section: 'Local commercial' },
        { id: 'bien.description',          label: 'Description du local',                type: 'textarea', placeholder: 'Local rez-de-chaussée, 80 m²…',    required: false },
        { id: 'bien.superficie',           label: 'Superficie (m²)',                     type: 'text',     placeholder: '80', required: false, mono: true },
        { id: 'bail.date_prise_effet',     label: "Date de prise d'effet (JJ/MM/AAAA)", type: 'text',     placeholder: '01/08/2026', required: true,  section: 'Conditions du bail' },
        { id: 'bail.duree_chiffres',       label: 'Durée du bail (années)',              type: 'text',     placeholder: '3', required: true,  mono: true },
        { id: 'bail.loyer_chiffres',       label: 'Loyer mensuel (GNF)',                 type: 'text',     placeholder: '20000000', required: true,  mono: true },
        { id: 'bail.periodicite',          label: 'Périodicité du paiement',             type: 'select',   options: ['Mensuel', 'Trimestriel', 'Semestriel', 'Annuel'], required: true },
        { id: 'bail.caution_chiffres',     label: 'Caution (GNF)',                       type: 'text',     placeholder: '60000000', required: false, mono: true },
        { id: 'bail.droit_entree_chiffres',label: "Droit d'entrée / Pas-de-porte (GNF)",type: 'text',     placeholder: '0', required: false, mono: true },
        { id: 'bail.destination',          label: 'Activité commerciale autorisée',      type: 'text',     placeholder: 'Commerce général, import-export…', required: true },
        { id: 'bail.clause_renouvellement',label: 'Clause de renouvellement',            type: 'text',     placeholder: 'Tacite reconduction / Non renouvelable', required: false },
    ],

    // ── Bail à construction ─────────────────────────────────────────────────
    bail_construction: [
        ...PP_BAILLEUR,
        ...LOC_PRENEUR,
        { id: 'bien.superficie',           label: 'Superficie du terrain (m²)',          type: 'text',     placeholder: '2000', required: true,  mono: true, section: 'Terrain' },
        { id: 'bien.lieu_de',              label: 'Situé à',                             type: 'text',     placeholder: 'Quartier, Commune, Ville',        required: true  },
        { id: 'bien.titre_foncier_numero', label: 'Numéro du titre foncier',             type: 'text',     placeholder: 'TF-2018-KAL-004521', required: false, mono: true },
        { id: 'bien.description',          label: 'Description du terrain',              type: 'textarea', placeholder: 'Terrain nu, non construit, clôturé…', required: false },
        { id: 'bail.date_prise_effet',     label: "Date de prise d'effet (JJ/MM/AAAA)", type: 'text',     placeholder: '01/08/2026', required: true,  section: 'Conditions du bail' },
        { id: 'bail.duree_chiffres',       label: 'Durée du bail à construction (années)', type: 'text',  placeholder: '30', required: true,  mono: true },
        { id: 'bail.loyer_chiffres',       label: 'Redevance annuelle (GNF)',            type: 'text',     placeholder: '10000000', required: true,  mono: true },
        { id: 'bail.engagement_construction', label: 'Engagement de construction',       type: 'textarea', placeholder: 'Nature des constructions prévues, délai de réalisation…', required: true },
        { id: 'bail.valeur_constructions_chiffres', label: 'Valeur estimée des constructions (GNF)', type: 'text', placeholder: '500000000', required: false, mono: true },
        { id: 'bail.destination',          label: 'Destination des constructions',       type: 'text',     placeholder: 'Résidentiel / Commercial / Industriel', required: false },
    ],

    // ── Hypothèque conventionnelle ──────────────────────────────────────────
    hypotheque_conv: [
        { id: 'pp.civilite',               label: 'Civilité du débiteur',                   type: 'select',   options: ['M.', 'Mme', 'Mlle'], required: true,  section: 'Débiteur / Emprunteur' },
        { id: 'pp.prenom_nom',             label: 'Nom et prénoms',                          type: 'text',     placeholder: 'Ibrahima DIALLO', required: true  },
        { id: 'pp.ne_a',                   label: 'Né(e) à',                                 type: 'text',     placeholder: 'Conakry', required: false },
        { id: 'pp.date_naissance',         label: 'Date de naissance (JJ/MM/AAAA)',           type: 'text',     placeholder: '15/03/1985', required: false },
        { id: 'pp.nationalite',            label: 'Nationalité',                             type: 'text',     placeholder: 'Guinéenne', required: false },
        { id: 'pp.situation_matrimoniale', label: 'Situation matrimoniale',                  type: 'select',   options: ['Célibataire', 'Marié(e)', 'Divorcé(e)', 'Veuf/Veuve'], required: false },
        { id: 'pp.regime_matrimonial',     label: 'Régime matrimonial',                      type: 'text',     placeholder: 'Communauté de biens / Séparation de biens', required: false },
        { id: 'pp.adresse',               label: 'Adresse complète',                        type: 'text',     placeholder: 'Quartier, Commune, Ville', required: true  },
        { id: 'pp.piece_type',             label: "Type de pièce d'identité",                type: 'text',     placeholder: 'CNI CEDEAO / Passeport', required: true  },
        { id: 'pp.piece_numero',           label: 'Numéro de pièce',                         type: 'text',     placeholder: 'GN00123456', required: true,  mono: true },
        { id: 'pp.piece_delivree_le',      label: 'Pièce délivrée le (JJ/MM/AAAA)',          type: 'text',     placeholder: '01/01/2020', required: false },
        { id: 'pp.piece_delivree_a',       label: 'Délivrée à',                              type: 'text',     placeholder: 'Conakry', required: false },
        { id: 'pp.telephone',             label: 'Téléphone',                               type: 'text',     placeholder: '622 XX XX XX', required: false },
        { id: 'pp.email',                  label: 'Email',                                   type: 'text',     placeholder: 'email@exemple.com', required: false },
        { id: 'bq.denomination',           label: 'Dénomination de la banque',               type: 'text',     placeholder: 'Ecobank Guinée SA', required: true,  section: 'Banque / Créancier' },
        { id: 'bq.forme',                  label: 'Forme juridique',                         type: 'text',     placeholder: 'SA', required: false },
        { id: 'bq.siege_quartier',         label: 'Quartier du siège',                      type: 'text',     placeholder: 'Kaloum', required: false },
        { id: 'bq.siege_commune',          label: 'Commune du siège',                       type: 'text',     placeholder: 'Conakry', required: false },
        { id: 'bq.siege_ville',            label: 'Ville',                                  type: 'text',     placeholder: 'Conakry', required: false },
        { id: 'bq.representant_nom',       label: 'Représentant légal de la banque',         type: 'text',     placeholder: 'Nom du Directeur Général', required: true  },
        { id: 'bq.representant_qualite',   label: 'Qualité du représentant',                 type: 'text',     placeholder: 'Directeur Général / Fondé de pouvoir', required: false },
        { id: 'bq.montant_credit_chiffres',label: 'Montant du crédit accordé (GNF)',         type: 'text',     placeholder: '500000000', required: true,  mono: true },
        { id: 'bq.taux_interet',           label: "Taux d'intérêt annuel (%)",               type: 'text',     placeholder: '18', required: false, mono: true },
        { id: 'bq.duree_credit_chiffres',  label: 'Durée du crédit (mois)',                  type: 'text',     placeholder: '60', required: false, mono: true },
        { id: 'bq.type_garantie',          label: 'Type de garantie',                        type: 'text',     placeholder: 'Affectation hypothécaire de 1er rang', required: false },
        { id: 'bq.rang_hypothecaire',      label: "Rang de l'hypothèque",                    type: 'text',     placeholder: '1er rang', required: false },
        { id: 'bien.titre_foncier_numero', label: 'Numéro du titre foncier hypothéqué',      type: 'text',     placeholder: 'TF-2018-KAL-004521', required: true,  mono: true, section: 'Bien hypothéqué' },
        { id: 'bien.superficie',           label: 'Superficie (m²)',                         type: 'text',     placeholder: '500', required: false, mono: true },
        { id: 'bien.lieu_de',              label: 'Situé à',                                 type: 'text',     placeholder: 'Quartier, Commune, Ville', required: true  },
        { id: 'bien.nature_terrain',       label: 'Nature du terrain / bien',                type: 'text',     placeholder: 'Terrain bâti / Immeuble à usage résidentiel', required: false },
        { id: 'bien.limite_nord',          label: 'Limite Nord',                             type: 'text',     placeholder: 'Rue de la Paix', required: false },
        { id: 'bien.limite_sud',           label: 'Limite Sud',                              type: 'text',     placeholder: 'Parcelle de M. Camara', required: false },
        { id: 'bien.limite_est',           label: 'Limite Est',                              type: 'text',     placeholder: 'Route Nationale', required: false },
        { id: 'bien.limite_ouest',         label: 'Limite Ouest',                            type: 'text',     placeholder: "Cours d'eau", required: false },
    ],

    // ── Mainlevée d'hypothèque ──────────────────────────────────────────────
    mainlevee: [
        { id: 'pp.civilite',               label: 'Civilité du débiteur',                   type: 'select',   options: ['M.', 'Mme', 'Mlle'], required: true,  section: 'Débiteur' },
        { id: 'pp.prenom_nom',             label: 'Nom et prénoms',                          type: 'text',     placeholder: 'Ibrahima DIALLO', required: true  },
        { id: 'pp.adresse',               label: 'Adresse',                                type: 'text',     placeholder: 'Quartier, Commune, Ville', required: true  },
        { id: 'pp.piece_type',             label: "Type de pièce",                           type: 'text',     placeholder: 'CNI CEDEAO / Passeport', required: false },
        { id: 'pp.piece_numero',           label: 'Numéro de pièce',                         type: 'text',     placeholder: 'GN00123456', required: false, mono: true },
        { id: 'pp.telephone',             label: 'Téléphone',                               type: 'text',     placeholder: '622 XX XX XX', required: false },
        { id: 'bq.denomination',           label: 'Dénomination de la banque créancière',    type: 'text',     placeholder: 'Ecobank Guinée SA', required: true,  section: 'Banque créancière' },
        { id: 'bq.representant_nom',       label: 'Représentant légal',                      type: 'text',     placeholder: 'Nom du Directeur Général', required: true  },
        { id: 'bq.representant_qualite',   label: 'Qualité du représentant',                 type: 'text',     placeholder: 'Directeur Général / Fondé de pouvoir', required: false },
        { id: 'hypotheque.reference_acte', label: "Référence de l'acte d'hypothèque",        type: 'text',     placeholder: 'HYP-2023-0045', required: true,  mono: true, section: 'Hypothèque à radier' },
        { id: 'hypotheque.date_acte',      label: "Date de l'acte (JJ/MM/AAAA)",             type: 'text',     placeholder: '15/03/2023', required: true  },
        { id: 'hypotheque.notaire_acte',   label: 'Notaire instrumentaire',                  type: 'text',     placeholder: 'Maître Ayelama BAH', required: false },
        { id: 'hypotheque.montant_chiffres',label: 'Montant garanti à l\'origine (GNF)',     type: 'text',     placeholder: '500000000', required: false, mono: true },
        { id: 'bien.titre_foncier_numero', label: 'Numéro du titre foncier concerné',        type: 'text',     placeholder: 'TF-2018-KAL-004521', required: true,  mono: true },
        { id: 'hypotheque.rang',           label: "Rang de l'hypothèque",                    type: 'text',     placeholder: '1er rang', required: false },
    ],

    // ── Modification de société ─────────────────────────────────────────────
    modification: [
        { id: 'soc.denomination',       label: 'Dénomination de la société',  type: 'text',     placeholder: 'Raison sociale exacte',                              required: true  },
        { id: 'soc.rccm',               label: 'Numéro RCCM actuel',          type: 'text',     placeholder: 'GN-CON-2020-B-XXXX', required: true, mono: true },
        { id: 'objet_modification', label: 'Objet de la modification',    type: 'textarea', placeholder: 'Décrire les changements apportés (capital, gérant…)', required: true },
        { id: 'fiche_modification', label: 'Fiche de modification',       type: 'checkbox_required', placeholder: '', required: true, note: "Obligatoire — la procédure écrite l'exige" },
    ],
};

// ─────────────────────────────────────────────────────────────────────────────
// ─────────────────────────────────────────────────────────────────────────────
// Filtre les champs selon showIf et les valeurs actuelles du formulaire.
// showIf: { field: 'fieldId' }           → visible si values[fieldId] est truthy
// showIf: { field: 'fieldId', not: true } → visible si values[fieldId] est falsy
// ─────────────────────────────────────────────────────────────────────────────
export function getVisibleFields(fields, values) {
    return fields.filter(field => {
        const { showIf } = field;
        if (!showIf) return true;
        const current = values[showIf.field];
        return showIf.not ? !current : !!current;
    });
}

// Lien TypeActe.code (BD) → clé QUESTIONNAIRES (frontend)
// ─────────────────────────────────────────────────────────────────────────────
export const TYPE_ACTE_CODE_MAP = {
    // Société
    'SOC-SARLU': 'creation_sarlu',
    'SOC-SARL':  'creation_sarl',
    'SOC-SA':    'creation_sa',
    'SOC-SAS':   'creation_sas',
    'SOC-SASU':  'creation_sasu',
    'SOC-SNC':   'creation_snc',
    'SOC-GIE':   'creation_gie',
    'SOC-DIS':   'dissolution',
    // Vente
    'VTE-IMM':   'vente_immeuble',
    'VTE-SAN':   'vente_sans_titre',
    'VTE-FDS':   'vente_immeuble',
    'VTE-VEH':   'vente_immeuble',
    // Bail
    'BAI-HAB':   'bail_habitation',
    'BAI-COM':   'bail_commercial',
    'BAI-CON':   'bail_construction',
    // Hypothèque
    'HYP-CON':   'hypotheque_conv',
    'HYP-MAI':   'mainlevee',
    // Modification
    'SOC-MOD':   'modification',
};
