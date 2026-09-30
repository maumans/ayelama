import { anneePremierExercice } from '@/lib/dates';

// Configurations des questionnaires par type de dossier.
// Clé = identifiant frontend (utilisé dans Create.jsx et Show.jsx).
// TYPE_ACTE_CODE_MAP fait le lien avec les codes TypeActe stockés en base.
//
// Types de champs supportés :
//   text | textarea | number | date | checkbox | select | checkbox_required
//   checkbox_group — choix multiple : { options:[...] }, valeur = tableau de libellés
//   repeatable — bloc répétable : { id, type:'repeatable', label, section, min, max, fields:[...] }
//
// Marqueurs de section (portés par le premier champ de la section) :
//   section        — démarre une nouvelle section (voir groupFieldsBySection)
//   clientRole     — la section est rattachable à une fiche Client, et produit une `Partie`
//   societePicker  — la section est rattachable à une fiche Societe du registre

// ─────────────────────────────────────────────────────────────────────────────
// Blocs réutilisables (évite la duplication)
// ─────────────────────────────────────────────────────────────────────────────

// ⚠️ **Déclarées avant les schémas, et non plus à côté de FORMES_SOCIETE.** Les littéraux de
// schéma ci-dessous s'évaluent à l'initialisation du module : une constante `const` référencée
// depuis eux mais déclarée plus bas est dans sa zone morte temporelle, et le module échoue à
// l'exécution — « Cannot access 'SITUATIONS_MATRIMONIALES' before initialization ». Un bundler
// ne le signale pas, et la page reste blanche. FORMES_SOCIETE n'a pas ce problème : elle n'est
// utilisée qu'après sa propre déclaration.

// Régimes matrimoniaux — miroir de App\Models\Client::REGIMES_MATRIMONIAUX. Le champ était en
// saisie libre : la base en portait **quatre orthographes pour deux régimes**, dont « Communaté de
// bien », que les modèles Word reprenaient telle quelle dans les actes.
export const REGIMES_MATRIMONIAUX = [
    'Communauté de biens',
    'Séparation de biens',
    'Communauté réduite aux acquêts',
    'Communauté universelle',
];

// Situations matrimoniales — miroir de App\Models\Client::SITUATIONS_MATRIMONIALES. Déjà un
// `select` partout, mais dont les dix déclarations recopiaient chacune leurs options : même défaut
// que celui corrigé sur FORMES_SOCIETE.
export const SITUATIONS_MATRIMONIALES = ['Célibataire', 'Marié(e)', 'Divorcé(e)', 'Veuf/Veuve'];

export const PIECES_TYPES = [
    'CNI CEDEAO',
    'Passeport',
    'Extrait de naissance',
    'Carte Consulaire',
    'Permis de conduire'
];

// ─────────────────────────────────────────────────────────────────────────────
// Représentation d'une partie à l'acte
// ─────────────────────────────────────────────────────────────────────────────
//
// ⚠️ Déclaré **avant** les schémas qui le consomment : les littéraux ci-dessous s'évaluent à
// l'initialisation du module, et une constante référencée depuis eux mais déclarée plus bas est
// dans sa zone morte temporelle. `vite build` ne le signale pas, et la page devient blanche.
//
// Le représenté reste la partie à l'acte ; le représentant comparaît « ès qualités ». Ces champs
// décrivent donc un **mode de comparution**, pas une personne de plus — c'est pourquoi ils vivent
// dans la section de la personne représentée, et non dans une section à part.

// Miroir de App\Enums\MotifRepresentation — verrouillé par PiecesRequisesParRoleTest.
// Les questionnaires étant statiques, la liste ne peut pas venir de PHP : la duplication est
// inévitable, donc gardée.
export const MOTIFS_REPRESENTATION = [
    'Procuration',
    'Représentation légale (tutelle, curatelle)',
    "Représentant légal d'une personne morale",
];

/**
 * Comment nommer le représentant à l'écran, selon le motif.
 *
 * Miroir de `MotifRepresentation::labelRepresentant()`. Le rôle en base reste `mandataire` pour
 * les trois motifs — un seul rôle, la distinction vivant sur le lien — mais l'afficher tel quel
 * devant un tuteur serait faux. Le libellé se dérive donc du motif au moment du rendu.
 */
export const LIBELLE_REPRESENTANT_PAR_MOTIF = {
    'Procuration': 'Mandataire',
    'Représentation légale (tutelle, curatelle)': 'Tuteur / curateur',
    "Représentant légal d'une personne morale": 'Représentant légal',
};

/** Le libellé du représentant, avec un repli tant que le motif n'est pas choisi. */
export function libelleRepresentant(motif) {
    return LIBELLE_REPRESENTANT_PAR_MOTIF[motif] ?? 'Représentant';
}

/** Libellé affiché → valeur stockée en base. Converti au moment du payload, nulle part ailleurs. */
export const MOTIF_REPRESENTATION_PAR_LIBELLE = {
    'Procuration': 'procuration',
    'Représentation légale (tutelle, curatelle)': 'legale',
    "Représentant légal d'une personne morale": 'organique',
};

// Miroir de App\Enums\FormeTitreRepresentation.
export const FORMES_TITRE_REPRESENTATION = [
    'Sous seing privé',
    'Notariée',
    'Sous seing privé, signature légalisée',
    'Consulaire',
];

export const FORME_TITRE_PAR_LIBELLE = {
    'Sous seing privé': 'sous_seing_prive',
    'Notariée': 'notariee',
    'Sous seing privé, signature légalisée': 'legalisee',
    'Consulaire': 'consulaire',
};

// Préfixes des sections de personne qui reçoivent le bloc. Déclaré ici et consommé par
// `blocRepresentation()`, `TRIPLETS_GEO` et `PAIRES_DATES` : le devbook enregistre déjà que
// `gerant_entrant.*`, bloc dérivé, n'avait ni cascade géo ni contrôle de dates parce que personne
// ne l'avait inscrit dans ces deux tables. Elles sont donc **générées**, pas tapées.
// `null` = item de bloc répétable, dont les clés sont à plat.
export const PREFIXES_REPRESENTATION = ['pp', 'ger', 'acq', 'loc', null];

const EST_REPRESENTE = (prefixe) => ({
    field: prefixe ? `${prefixe}.est_represente` : 'est_represente',
    equals: true,
});

const MOTIF_EST = (prefixe, libelle) => ({
    all: [
        EST_REPRESENTE(prefixe),
        { field: prefixe ? `${prefixe}.representation_motif` : 'representation_motif', equals: libelle },
    ],
});

/**
 * Les champs de représentation, pour un préfixe donné.
 *
 * `prefixe = null` produit les clés à plat d'un item de bloc répétable (`repr_prenom_nom`), avec
 * un point pour une section scalaire (`pp.repr_prenom_nom`) : le sous-espace suit la forme de son
 * hôte, comme le reste du schéma.
 *
 * ⚠️ `repr_*` et non `representant_*` : ces derniers existent déjà dans `SUFFIXES_MORALE` de
 * ClientProjectionService, où ils désignent le représentant **statutaire** d'une fiche personne
 * morale. Réemployer le nom ferait porter deux sens à la même clé, dans un fichier qui alimente
 * des actes authentiques.
 */
export function blocRepresentation(prefixe = null, { section = null, showIfSection = null } = {}) {
    const cle = (id) => (prefixe ? `${prefixe}.${id}` : id);
    const conditionner = (showIf) => {
        if (!showIfSection) return showIf;
        // Le bloc lui-même peut être conditionnel (gerant_entrant.*) : la condition de section
        // s'**ajoute**, elle ne remplace pas.
        const existantes = showIf?.all ?? (showIf ? [showIf] : []);
        return { all: [showIfSection, ...existantes] };
    };

    const champs = [
        { id: cle('est_represente'), label: 'Se fait représenter à l\'acte', type: 'checkbox',
          note: 'La personne ne comparaît pas elle-même : un mandataire signe pour elle.' },

        { id: cle('representation_motif'), label: 'À quel titre', type: 'select',
          options: MOTIFS_REPRESENTATION, required: true, showIf: EST_REPRESENTE(prefixe) },

        // Identité du représentant. Masquée dès qu'une fiche client lui est rattachée — voir
        // estChampIdentite() et SOUS_PREFIXES_PERSONNE dans lib/clientFields.js.
        { id: cle('repr_civilite'), label: 'Civilité du représentant', type: 'select',
          options: ['M.', 'Mme', 'Mlle'], showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_prenom_nom'), label: 'Nom et prénoms du représentant', type: 'text',
          placeholder: 'Mamadou BAH', required: true, showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_ne_a'), label: 'Né(e) à', type: 'text', placeholder: 'Conakry', showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_date_naissance'), label: 'Date de naissance', type: 'date', showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_nationalite'), label: 'Nationalité', type: 'text', placeholder: 'Guinéenne', showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_demeurant_ville'), label: 'Ville (résidence)', type: 'text', placeholder: 'Conakry', showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_commune'), label: 'Commune (résidence)', type: 'text', placeholder: 'Kaloum', showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_quartier'), label: 'Quartier (résidence)', type: 'text', placeholder: 'Almamya', showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_pays'), label: 'Pays de résidence', type: 'text', placeholder: 'Guinée', readonly: true, showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_piece_type'), label: "Type de pièce d'identité", type: 'text',
          datalist: PIECES_TYPES, placeholder: 'CNI CEDEAO / Passeport', showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_piece_numero'), label: 'Numéro de pièce', type: 'text',
          placeholder: 'GN00123456', required: true, mono: true, showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_piece_delivree_le'), label: 'Pièce délivrée le', type: 'date', showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_piece_delivree_a'), label: 'Délivrée à', type: 'text', placeholder: 'Conakry', showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_piece_expire_le'), label: 'Expire le', type: 'date', showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_telephone'), label: 'Téléphone', type: 'tel', placeholder: '622 XX XX XX', showIf: EST_REPRESENTE(prefixe) },
        { id: cle('repr_email'), label: 'Email', type: 'email', placeholder: 'email@exemple.com', showIf: EST_REPRESENTE(prefixe) },

        // Le titre. Ce qui suit dépend du motif : une procuration se vise par sa forme et sa
        // date, une tutelle par la décision qui l'institue, un gérant par les statuts.
        { id: cle('repr_titre_forme'), label: 'Forme de la procuration', type: 'select',
          options: FORMES_TITRE_REPRESENTATION, required: true,
          showIf: MOTIF_EST(prefixe, 'Procuration') },
        { id: cle('repr_titre_date'), label: 'Date de la procuration', type: 'date', required: true,
          raisonSiVide: "Renseignez la date : l'acte vise la procuration « en date du… »",
          showIf: MOTIF_EST(prefixe, 'Procuration') },
        { id: cle('repr_titre_autorite'), label: 'Établie par', type: 'text',
          placeholder: 'Maître / mairie / poste consulaire',
          note: 'Le notaire, l\'autorité de légalisation ou le poste consulaire qui l\'a reçue.',
          showIf: MOTIF_EST(prefixe, 'Procuration') },

        // Une seule qualité, une seule colonne (`parties.representation_qualite`). Deux champs
        // — un par motif — auraient obligé le payload à choisir lequel lire, et les deux
        // auraient fini par diverger. La condition porte donc sur « pas une procuration »
        // plutôt que sur chacun des deux autres motifs : un mandataire sur procuration agit au
        // nom du mandant, sans qualité propre.
        { id: cle('repr_qualite'), label: 'Qualité du représentant', type: 'text',
          placeholder: 'Tuteur / Curateur / Gérant / Directeur général', required: true,
          raisonSiVide: 'Précisez la qualité — elle est reprise telle quelle dans l\'acte',
          showIf: { all: [
              EST_REPRESENTE(prefixe),
              // Le motif doit être choisi : sans cette condition, `undefined !== 'Procuration'`
              // suffirait à afficher un champ obligatoire avant même qu'on sache s'il
              // s'applique — et il bloquerait l'étape sans raison lisible.
              { field: prefixe ? `${prefixe}.representation_motif` : 'representation_motif' },
              { field: prefixe ? `${prefixe}.representation_motif` : 'representation_motif',
                equals: 'Procuration', not: true },
          ] } },

        { id: cle('repr_titre_reference'), label: 'Décision qui institue la mesure', type: 'text',
          placeholder: 'Référence du jugement ou de l\'ordonnance',
          // ⚠️ Facultatif tant que l'étude n'a pas dit quel document guinéen établit une tutelle.
          // Voir Partie::PIECES_A_CONFIRMER : la pièce correspondante ne bloque pas non plus.
          note: 'Facultatif : la forme exacte reste à arbitrer avec l\'étude.',
          showIf: MOTIF_EST(prefixe, 'Représentation légale (tutelle, curatelle)') },
    ];

    return champs.map((champ, i) => ({
        ...champ,
        ...(champ.showIf ? { showIf: conditionner(champ.showIf) } : {}),
        ...(i === 0 && section ? { section } : {}),
    }));
}


const SOC_BASE = [
    { id: 'soc.denomination', label: 'Dénomination sociale', type: 'text', placeholder: 'Ex : Faya Distribution SARLU', required: true, section: 'Société', publicIntake: true },
    { id: 'soc.sigle', label: 'Sigle (facultatif)', type: 'text', placeholder: 'Ex : FD', required: false, publicIntake: true },
    { id: 'soc.capital_chiffres', label: 'Capital social (GNF)', type: 'number', placeholder: '50 000 000', required: true, mono: true, publicIntake: true },
    { id: 'soc.nombre_parts', label: 'Nombre de parts sociales', type: 'number', placeholder: '100', required: true, mono: true, publicIntake: true },
    { id: 'soc.valeur_nominale_chiffres', label: "Valeur nominale d'une part (GNF)", type: 'number', placeholder: '500 000', required: true, mono: true, readonly: true, publicIntake: true },
    { id: 'soc.siege_ville', label: 'Ville du siège social', type: 'text', placeholder: 'Conakry', required: true, publicIntake: true },
    { id: 'soc.siege_commune', label: 'Commune du siège social', type: 'text', placeholder: 'Kaloum', required: true, publicIntake: true },
    { id: 'soc.siege_quartier', label: 'Quartier du siège social', type: 'text', placeholder: 'Almamya', required: true, publicIntake: true },
    { id: 'soc.objet_social', label: 'Objet social', type: 'textarea', placeholder: 'Commerce général, import-export…', required: true, publicIntake: true },
    { id: 'soc.duree', label: 'Durée (années)', type: 'number', placeholder: '99', required: false, publicIntake: true },
    { id: 'soc.premier_exercice_annee', label: '1er exercice — année', type: 'year', placeholder: String(anneePremierExercice()), required: false, publicIntake: true },
    { id: 'soc.email_societe', label: 'Email de la société', type: 'text', placeholder: 'contact@societe.com', required: false, publicIntake: true },
    { id: 'soc.telephone_societe', label: 'Téléphone de la société', type: 'tel', placeholder: '622 XX XX XX', required: false, publicIntake: true },
    { id: 'soc.regime_fiscal_faveur', label: 'Bénéficie d\'un régime fiscal de faveur', type: 'checkbox', required: false, publicIntake: true },
    { id: 'soc.regime_fiscal_reference', label: 'Référence du décret/arrêté d\'agrément', type: 'text', placeholder: 'Décret n° ... du ...', required: false, showIf: { field: 'soc.regime_fiscal_faveur' }, publicIntake: true },
];

const SOC_COMMISSAIRES = [
    { id: 'cac_titulaire.civilite', label: 'Civilité / Type', type: 'select', options: ['M.', 'Mme', 'Cabinet'], required: false, section: 'Commissaire aux comptes titulaire', clientRole: 'commissaire_titulaire' },
    { id: 'cac_titulaire.prenom_nom', label: 'Nom du cabinet ou expert', type: 'text', placeholder: 'Cabinet Diallo & Associés', required: false },
    { id: 'cac_titulaire.agrement', label: "N° d'agrément", type: 'text', placeholder: 'N° 001/OECA', required: false },
    { id: 'cac_titulaire.adresse', label: 'Adresse complète', type: 'text', placeholder: 'Quartier, Commune, Ville', required: false },

    { id: 'cac_suppleant.civilite', label: 'Civilité / Type', type: 'select', options: ['M.', 'Mme', 'Cabinet'], required: false, section: 'Commissaire aux comptes suppléant', clientRole: 'commissaire_suppleant' },
    { id: 'cac_suppleant.prenom_nom', label: 'Nom du cabinet ou expert', type: 'text', placeholder: 'Cabinet Sow & Co', required: false },
    { id: 'cac_suppleant.agrement', label: "N° d'agrément", type: 'text', placeholder: 'N° 002/OECA', required: false },
    { id: 'cac_suppleant.adresse', label: 'Adresse complète', type: 'text', placeholder: 'Quartier, Commune, Ville', required: false },
];

const SOC_COMMISSAIRES_REQUIS = SOC_COMMISSAIRES.map(f => 
    f.id.startsWith('cac_titulaire') ? { ...f, required: true } : f
);

/**
 * Mentions qu'une personne morale n'a pas : elles passent sous condition dans un bloc bimodal.
 *
 * ⚠️ **Déclarée ici, et non près de `rendreBimodal()` qui l'utilise.** `PP_ASSOCIE_UNIQUE`, juste
 * dessous, appelle `rendreBimodal()` à l'évaluation du module : une `const` déclarée plus bas
 * serait lue dans sa **zone morte temporelle** et lèverait « Cannot access before
 * initialization » — une page blanche que `vite build` laisse passer sans un mot. C'est
 * exactement ce que `ListesMatrimonialesTest` interdit ailleurs dans ce fichier.
 */
const PHYSIQUE_SEULEMENT = [
    'ne_a', 'date_naissance', 'situation_matrimoniale', 'regime_matrimonial',
    'piece_type', 'piece_numero', 'piece_delivree_le', 'piece_delivree_a', 'piece_expire_le',
];

/**
 * Associé unique — SARLU / SASU. **Bimodal depuis le 2026-09-30.**
 *
 * Il ne l'était pas, et c'était une impasse mesurable : `Partie::ROLES_ADMETTANT_PERSONNE_MORALE`
 * admet `associe_unique`, le serveur sait donc exiger les pièces d'un associé société — mais le
 * formulaire n'avait ni champ « Nature », ni civilité « Société », et réclamait lieu de
 * naissance, date de naissance et quatre champs de pièce d'identité. **Une SARLU détenue par une
 * société était insaisissable**, alors que c'est un montage courant.
 *
 * ⚠️ `physiqueParDefaut: true`, et ce n'est pas un détail de confort. Les huit `required`
 * ci-dessous sont **conservés** — une SARLU à associé physique reste aussi exigeante
 * qu'avant — mais ils passent sous condition, et la condition doit être **tolérante** : les 12
 * dossiers SARLU/SASU du registre n'ont pas la clé `pp.type_personne`, puisque le champ vient
 * d'être créé. Une condition stricte y ferait disparaître tout l'état civil déjà saisi.
 * Voir {@see rendreBimodal}.
 */
const PP_ASSOCIE_UNIQUE = [
    ...rendreBimodal([
        { id: 'pp.civilite', label: 'Civilité', type: 'select', options: ['M.', 'Mme', 'Mlle'], required: true },
        { id: 'pp.prenom_nom', label: 'Nom et prénoms / Dénomination', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
        { id: 'pp.ne_a', label: 'Né(e) à', type: 'text', placeholder: 'Conakry', required: true },
        { id: 'pp.date_naissance', label: 'Date de naissance', type: 'date', placeholder: '15/03/1985', required: true },
        { id: 'pp.nationalite', label: 'Nationalité / Pays', type: 'text', placeholder: 'Guinéenne', required: false },
        { id: 'pp.situation_matrimoniale', label: 'Situation matrimoniale', type: 'select', options: SITUATIONS_MATRIMONIALES, required: false },
        { id: 'pp.regime_matrimonial', label: 'Régime matrimonial', type: 'select', options: REGIMES_MATRIMONIAUX, required: false },
        { id: 'pp.demeurant_ville', label: 'Ville (résidence / siège)', type: 'text', placeholder: 'Conakry', required: true },
        { id: 'pp.commune', label: 'Commune (résidence / siège)', type: 'text', placeholder: 'Kaloum', required: true },
        { id: 'pp.quartier', label: 'Quartier (résidence / siège)', type: 'text', placeholder: 'Almamya', required: true },
        { id: 'pp.pays', label: 'Pays de résidence', type: 'text', placeholder: 'Guinée', required: false, readonly: true },
        { id: 'pp.piece_type', label: "Type de pièce d'identité", type: 'text', placeholder: 'CNI CEDEAO / Passeport', required: true, datalist: PIECES_TYPES },
        { id: 'pp.piece_numero', label: 'Numéro de pièce', type: 'text', placeholder: 'GN00123456', required: true, mono: true },
        { id: 'pp.piece_delivree_le', label: 'Pièce délivrée le', type: 'date', placeholder: '01/01/2020', required: true },
        { id: 'pp.piece_delivree_a', label: 'Délivrée à', type: 'text', placeholder: 'Conakry', required: true },
        { id: 'pp.piece_expire_le', label: 'Expire le', type: 'date', placeholder: '01/01/2030', required: false },
        { id: 'pp.telephone', label: 'Téléphone', type: 'tel', placeholder: '622 XX XX XX', required: false },
        { id: 'pp.email', label: 'Email', type: 'email', placeholder: 'email@exemple.com', required: false },
    ], { prefixe: 'pp', section: 'Associé unique', clientRole: 'associe_unique', physiqueParDefaut: true }),

    // En queue de section : la représentation est un mode de comparution de cette personne,
    // pas une personne de plus. Elle reste repliée tant que la case n'est pas cochée.
    // Hors de `rendreBimodal()` à dessein — le sous-espace `repr_*` décrit le mandataire, qui
    // n'a rien à voir avec la nature de la personne représentée.
    ...blocRepresentation('pp'),
];

// Gérant (personne physique)
const GER_FIELDS = [
    { id: 'ger.civilite', label: 'Civilité du gérant', type: 'select', options: ['M.', 'Mme', 'Mlle'], required: true, section: 'Gérant' },
    { id: 'ger.prenom_nom', label: 'Nom et prénoms', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
    { id: 'ger.ne_a', label: 'Né(e) à', type: 'text', placeholder: 'Conakry', required: true },
    { id: 'ger.date_naissance', label: 'Date de naissance', type: 'date', placeholder: '15/03/1985', required: true },
    { id: 'ger.nationalite', label: 'Nationalité', type: 'text', placeholder: 'Guinéenne', required: false },
    { id: 'ger.situation_matrimoniale', label: 'Situation matrimoniale', type: 'select', options: SITUATIONS_MATRIMONIALES, required: false },
    { id: 'ger.demeurant_ville', label: 'Ville (résidence)', type: 'text', placeholder: 'Conakry', required: false },
    { id: 'ger.commune', label: 'Commune (résidence)', type: 'text', placeholder: 'Kaloum', required: false },
    { id: 'ger.quartier', label: 'Quartier (résidence)', type: 'text', placeholder: 'Almamya', required: false },
    { id: 'ger.pays', label: 'Pays de résidence', type: 'text', placeholder: 'Guinée', required: false, readonly: true },
    { id: 'ger.piece_type', label: "Type de pièce d'identité", type: 'text', placeholder: 'CNI CEDEAO / Passeport', required: true, datalist: PIECES_TYPES },
    { id: 'ger.piece_numero', label: 'Numéro de pièce', type: 'text', placeholder: 'GN00123456', required: true, mono: true },
    { id: 'ger.piece_delivree_le', label: 'Pièce délivrée le', type: 'date', placeholder: '01/01/2020', required: true },
    { id: 'ger.piece_delivree_a', label: 'Délivrée à', type: 'text', placeholder: 'Conakry', required: true },
    { id: 'ger.piece_expire_le', label: 'Expire le', type: 'date', placeholder: '01/01/2030', required: false },
    { id: 'ger.telephone', label: 'Téléphone', type: 'tel', placeholder: '622 XX XX XX', required: false },
    { id: 'ger.email', label: 'Email', type: 'email', placeholder: 'email@exemple.com', required: false },
];

// Schéma d'un associé dans un bloc répétable — aligné sur les mentions exigées par les actes
// notariés réels (DNSV, statuts) : civilité, état civil complet et pièce d'identité détaillée,
// pas seulement un nom et une adresse sommaire.
const ASSOCIE_SCHEMA = [
    { id: 'civilite', label: 'Civilité', type: 'select', options: ['M.', 'Mme', 'Mlle', 'Société'], required: true },
    { id: 'nom', label: 'Nom et prénoms / Dénomination', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
    { id: 'type_personne', label: 'Type', type: 'select', options: ['Personne physique', 'Personne morale'], required: true },
    { id: 'parts_chiffres', label: 'Nombre de parts', type: 'number', placeholder: '100', required: true, mono: true },
    { id: 'forme', label: 'Forme juridique', type: 'text', placeholder: 'SARL, SA…', required: false, showIf: { field: 'type_personne', equals: 'Personne morale' } },
    { id: 'rccm', label: 'Numéro RCCM', type: 'text', placeholder: 'GN-CON-2020-B-XXXX', required: false, mono: true, showIf: { field: 'type_personne', equals: 'Personne morale' } },
    { id: 'representant_legal', label: 'Représentant légal', type: 'text', placeholder: 'Ibrahima DIALLO', required: false, showIf: { field: 'type_personne', equals: 'Personne morale' } },
    { id: 'ne_a', label: 'Né(e) à', type: 'text', placeholder: 'Conakry', required: false, showIf: { field: 'type_personne', equals: 'Personne physique' } },
    { id: 'date_naissance', label: 'Date de naissance', type: 'date', placeholder: '15/03/1985', required: false, showIf: { field: 'type_personne', equals: 'Personne physique' } },
    { id: 'nationalite', label: 'Nationalité / Pays', type: 'text', placeholder: 'Guinéenne', required: false, showIf: { field: 'type_personne', equals: 'Personne physique' } },
    { id: 'situation_matrimoniale', label: 'Situation matrimoniale', type: 'select', options: SITUATIONS_MATRIMONIALES, required: false, showIf: { field: 'type_personne', equals: 'Personne physique' } },
    { id: 'regime_matrimonial', label: 'Régime matrimonial', type: 'select', options: REGIMES_MATRIMONIAUX, required: false, showIf: { field: 'type_personne', equals: 'Personne physique' } },
    { id: 'demeurant_ville', label: 'Ville (résidence)', type: 'text', placeholder: 'Conakry', required: false },
    { id: 'commune', label: 'Commune (résidence)', type: 'text', placeholder: 'Kaloum', required: false },
    { id: 'quartier', label: 'Quartier (résidence)', type: 'text', placeholder: 'Almamya', required: false },
    { id: 'pays', label: 'Pays de résidence', type: 'text', placeholder: 'Guinée', required: false, readonly: true },
    { id: 'piece_type', label: "Type de pièce d'identité", type: 'text', placeholder: 'CNI CEDEAO / Passeport', required: false, datalist: PIECES_TYPES },
    { id: 'cni', label: "N° pièce d'identité", type: 'text', placeholder: 'GN00123456', required: false, mono: true, showIf: { field: 'type_personne', equals: 'Personne physique' } },
    { id: 'piece_delivree_le', label: 'Pièce délivrée le', type: 'date', placeholder: '01/01/2020', required: false },
    { id: 'piece_delivree_a', label: 'Délivrée à', type: 'text', placeholder: 'Conakry', required: false },
    { id: 'piece_expire_le', label: 'Expire le', type: 'date', placeholder: '01/01/2030', required: false },
    // Clés à plat : dans un item de bloc, le sous-espace s'écrit `repr_*` sans point.
    // PERSONNE_REPEATABLE en hérite par son `filter`, donc CEDANT_SCHEMA, CESSIONNAIRE_SCHEMA et
    // SOUSCRIPTEUR_SCHEMA l'obtiennent via schemaPersonne() — sans double insertion.
    ...blocRepresentation(null),
];

// Schéma d'un gérant dans un bloc répétable (SARL multi-gérants)
const GERANT_SCHEMA = [
    { id: 'civilite', label: 'Civilité', type: 'select', options: ['M.', 'Mme', 'Mlle'], required: true },
    { id: 'prenom_nom', label: 'Nom et prénoms', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
    { id: 'ne_a', label: 'Né(e) à', type: 'text', placeholder: 'Conakry', required: false },
    { id: 'date_naissance', label: 'Date de naissance', type: 'date', placeholder: '15/03/1985', required: false },
    { id: 'nationalite', label: 'Nationalité', type: 'text', placeholder: 'Guinéenne', required: false },
    { id: 'situation_matrimoniale', label: 'Situation matrimoniale', type: 'select', options: SITUATIONS_MATRIMONIALES, required: false },
    { id: 'regime_matrimonial', label: 'Régime matrimonial', type: 'select', options: REGIMES_MATRIMONIAUX, required: false },
    { id: 'demeurant_ville', label: 'Ville (résidence)', type: 'text', placeholder: 'Conakry', required: false },
    { id: 'commune', label: 'Commune (résidence)', type: 'text', placeholder: 'Kaloum', required: false },
    { id: 'quartier', label: 'Quartier (résidence)', type: 'text', placeholder: 'Almamya', required: false },
    { id: 'pays', label: 'Pays de résidence', type: 'text', placeholder: 'Guinée', required: false, readonly: true },
    { id: 'piece_type', label: "Type de pièce d'identité", type: 'text', placeholder: 'CNI CEDEAO / Passeport', required: false, datalist: PIECES_TYPES },
    { id: 'piece_numero', label: "N° pièce d'identité", type: 'text', placeholder: 'GN00123456', required: false, mono: true },
    { id: 'piece_delivree_le', label: 'Pièce délivrée le', type: 'date', placeholder: '01/01/2020', required: false },
    { id: 'piece_delivree_a', label: 'Délivrée à', type: 'text', placeholder: 'Conakry', required: false },
    { id: 'piece_expire_le', label: 'Expire le', type: 'date', placeholder: '01/01/2030', required: false },
];

// Schéma d'un administrateur (SA — Conseil d'Administration)
const ADMIN_SCHEMA = [
    { id: 'prenom_nom', label: 'Nom et prénoms', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
    { id: 'nationalite', label: 'Nationalité', type: 'text', placeholder: 'Guinéenne', required: false },
    { id: 'domicile', label: 'Domicile', type: 'text', placeholder: 'Conakry, Guinée', required: false },
    { id: 'fonction', label: 'Fonction au CA', type: 'text', placeholder: 'Administrateur', required: false },
];

// Formes juridiques — miroir de App\Enums\FormeSociete (9 cas depuis le CR de juillet
// 2026, qui a ajouté SCS et SAU). Une seule liste : les questionnaires en portaient
// chacun leur copie de sept valeurs, et les deux formes ajoutées n'y sont jamais entrées.
export const FORMES_SOCIETE = ['SA', 'SAU', 'SARL', 'SARLU', 'SAS', 'SASU', 'SNC', 'SCS', 'GIE'];

// ─── Blocs de la modification de société ─────────────────────────────────────

// Identité d'une personne dans un bloc répétable, sans donnée propre à l'acte.
// Dérivé d'ASSOCIE_SCHEMA (mêmes mentions que celles exigées par les actes réels) privé
// de `parts_chiffres`, qui est justement la donnée d'acte : chaque rôle apporte la sienne
// (parts cédées, parts acquises, montant souscrit…).
const PERSONNE_REPEATABLE = [
    ...ASSOCIE_SCHEMA.filter(f => f.id !== 'parts_chiffres'),
    { id: 'telephone', label: 'Téléphone', type: 'tel', placeholder: '622 XX XX XX', required: false },
    { id: 'email', label: 'Email', type: 'email', placeholder: 'email@exemple.com', required: false },
];

// Compose le schéma d'un rôle : ses champs propres à l'acte sont insérés juste après le
// type de personne, avant l'état civil — pour que la donnée qui distingue le rôle soit
// lue en premier, et non noyée en fin de formulaire.
function schemaPersonne(champsActe) {
    const i = PERSONNE_REPEATABLE.findIndex(f => f.id === 'type_personne');
    return [
        ...PERSONNE_REPEATABLE.slice(0, i + 1),
        ...champsActe,
        ...PERSONNE_REPEATABLE.slice(i + 1),
    ];
}

const CEDANT_SCHEMA = schemaPersonne([
    { id: 'parts_detenues', label: 'Parts détenues avant cession', type: 'number', placeholder: '100', required: false, mono: true },
    { id: 'parts_cedees', label: 'Parts cédées', type: 'number', placeholder: '40', required: true, mono: true },
    { id: 'prix_cession', label: 'Prix de cession (GNF)', type: 'number', placeholder: '20 000 000', required: false, mono: true },
]);

const CESSIONNAIRE_SCHEMA = schemaPersonne([
    { id: 'parts_acquises', label: 'Parts acquises', type: 'number', placeholder: '40', required: true, mono: true },
    { id: 'prix_paye', label: 'Prix payé (GNF)', type: 'number', placeholder: '20 000 000', required: false, mono: true },
]);

const SOUSCRIPTEUR_SCHEMA = schemaPersonne([
    { id: 'parts_souscrites', label: 'Parts souscrites', type: 'number', placeholder: '100', required: true, mono: true },
    { id: 'montant_souscrit', label: 'Montant souscrit (GNF)', type: 'number', placeholder: '50 000 000', required: true, mono: true },
]);

// Le bloc « gérant » couvre les deux types de changement — statutaire et non statutaire :
// les champs sont identiques, seul l'impact sur les statuts diffère, et c'est
// TypeModificationStatutaire::impacteStatuts() qui le porte, pas le formulaire.
const SHOW_IF_GERANT = {
    field: 'modif.types',
    includesAny: ['Changement de gérant statutaire', 'Changement de gérant non statutaire'],
};

// Gérant entrant — bloc scalaire préfixé, rattachable à une fiche client (c'est lui dont
// les pièces d'identité sont exigées). Dérivé de GER_FIELDS pour ne pas redéclarer les
// dix-sept mentions d'état civil et de pièce d'identité une troisième fois.
const GERANT_ENTRANT_FIELDS = [
    ...GER_FIELDS.map((f, i) => ({
        ...f,
        id: f.id.replace(/^ger\./, 'gerant_entrant.'),
        showIf: SHOW_IF_GERANT,
        ...(i === 0
            ? { label: 'Civilité du gérant entrant', section: 'Gérant entrant', clientRole: 'gerant_entrant' }
            : {}),
    })),
    { id: 'gerant_entrant.duree_mandat', label: 'Durée du mandat', type: 'text', placeholder: 'Indéterminée / 4 ans', required: false, showIf: SHOW_IF_GERANT },
    { id: 'gerant_entrant.pouvoirs', label: 'Pouvoirs conférés', type: 'textarea', placeholder: 'Pouvoirs les plus étendus pour agir au nom de la société…', required: false, showIf: SHOW_IF_GERANT },
];

// ── Dissolution : conditions de phase et bloc liquidateur ──────────────────
//
// `dissolution.phase` porte la variante du type d'acte `SOC-DIS` (miroir de
// `App\Enums\VarianteDissolution`). Les deux conditions ci-dessous sont les seules à la lire :
// déclarées une fois, elles ne peuvent pas diverger entre les champs qu'elles gouvernent.
const SHOW_IF_DISSOLUTION = { field: 'dissolution.phase', equals: 'Dissolution anticipée' };
const SHOW_IF_CLOTURE     = { field: 'dissolution.phase', equals: 'Clôture de la liquidation' };

/**
 * Bloc de personne **bimodale** — physique ou morale — dérivé de GER_FIELDS.
 *
 * Écrit le 2026-09-28 après lecture de deux actes réels de l'étude (`PV DECISION ASSOCIE.docx`
 * et `MY_INSERTION.doc`, dossier L'OR D'AFRIQUE) : le liquidateur nommé y est **le Cabinet
 * TEDSOM SARLU**, une société, « représenté par Monsieur … ». Le bloc liquidateur, dérivé du
 * gérant, ne savait décrire qu'une personne physique — il ne pouvait donc pas produire cet acte.
 *
 * Un seul helper pour deux emplacements (le requérant et le liquidateur) : redéclarer les
 * dix-sept mentions d'état civil une quatrième fois était le défaut que la dérivation de
 * `GERANT_ENTRANT_FIELDS` avait déjà évité.
 *
 * Convention reprise d'`ASSOCIE_SCHEMA`, qui résout le même problème dans un bloc répétable :
 * une civilité qui inclut « Société », **un** champ de nom qui sert de dénomination, et les
 * mentions propres à chaque nature sous condition. Les champs d'état civil passent tous en
 * facultatif — les exiger bloquerait une personne morale, qui n'en a aucun.
 *
 * ⚠️ N'inscrit **pas** le rôle dans `Partie::ROLES_ADMETTANT_PERSONNE_MORALE` : cette constante
 * ne gouverne que le **jeu de pièces**, et le liquidateur est délibérément sans pièces. La
 * projection, elle, bascule sur le type du client, pas sur le rôle — elle écrit donc déjà
 * `denomination`, `forme`, `rccm` et `representant_legal` dès qu'une fiche morale est liée.
 */
/**
 * Rend bimodal un bloc d'identité déjà préfixé : la même personne peut être physique ou morale.
 *
 * Trois gestes, et c'est tout :
 *
 *  1. un champ **Nature** est inséré en tête, et c'est **lui** qui porte `section` / `clientRole` ;
 *  2. les mentions propres aux personnes physiques passent sous condition ;
 *  3. « Société » rejoint les civilités, et les mentions propres aux personnes morales
 *     (forme, RCCM, représentant légal et sa qualité) sont ajoutées en queue, sous condition.
 *
 * ⚠️ **`section` et `clientRole` sont retirés des champs reçus et portés par « Nature ».**
 * `groupFieldsBySection()` (lib/partiesPayload.js) ouvre une carte sur le **premier** champ qui
 * porte `section` : laisser la marque sur la civilité ferait tomber « Nature » — déclaré avant
 * elle — dans la carte **précédente**. Constaté à l'écran le 2026-09-30, sur la dissolution : le
 * select « Nature » du requérant s'affichait sous « Décision de dissolution », celui du
 * liquidateur dans la carte du requérant. Le nettoyage porte sur **tous** les champs reçus, et
 * pas seulement le premier : `GER_FIELDS[0]` porte `section: 'Gérant'` pour son usage d'origine,
 * et le spread le recopiait — une carte « Gérant » rouvrait au milieu de la personne.
 *
 * Le `prefix` du groupe reste celui du bloc (`partiesPayload.js` lit `fields[0].id`), donc le
 * payload `parties` n'est pas affecté par ce déplacement.
 *
 * ⚠️ `physiqueParDefaut` n'est pas une commodité, c'est une question de **données existantes**.
 * Avec la condition stricte (`equals: 'Personne physique'`), un bloc dont la clé `type_personne`
 * n'a jamais existé voit tout son état civil **disparaître** — c'est le cas des 12 dossiers
 * SARLU/SASU du registre. La forme tolérante (`equals: 'Personne morale', not: true`, supportée
 * par `estVisible()`) montre l'état civil tant qu'on n'a pas déclaré l'inverse, et rend « Nature »
 * facultatif : on ne le renseigne que pour dire « c'est une société ». Les blocs répétables et la
 * dissolution gardent la forme stricte — mesuré, leurs items portent tous la valeur.
 *
 * @param champs   champs d'identité, **déjà préfixés**
 * @param options  prefixe, section, clientRole, showIfSection, physiqueParDefaut
 */
function rendreBimodal(champs, { prefixe, section, clientRole, showIfSection = null, physiqueParDefaut = false }) {
    const cle = (id) => `${prefixe}.${id}`;
    const et = (condition) => (showIfSection
        ? { all: [showIfSection, ...(condition ? [condition] : [])] }
        : condition);

    const EST_PHYSIQUE = physiqueParDefaut
        ? { field: cle('type_personne'), equals: 'Personne morale', not: true }
        : { field: cle('type_personne'), equals: 'Personne physique' };
    const EST_MORALE = { field: cle('type_personne'), equals: 'Personne morale' };

    const identite = champs.map((f) => {
        const suffixe = f.id.slice(prefixe.length + 1);

        return {
            ...f,
            section: null,
            clientRole: null,
            showIf: et(PHYSIQUE_SEULEMENT.includes(suffixe) ? EST_PHYSIQUE : f.showIf ?? null),
            // Une société comparaît sous sa dénomination : la civilité doit pouvoir le dire.
            ...(suffixe === 'civilite' ? { options: [...(f.options ?? []), 'Société'] } : {}),
        };
    });

    return [
        { id: cle('type_personne'), label: 'Nature', type: 'select', options: ['Personne physique', 'Personne morale'], required: !physiqueParDefaut, showIf: et(null), section, clientRole },
        ...identite,
        { id: cle('forme'), label: 'Forme juridique', type: 'text', placeholder: 'SARL, SA…', required: false, showIf: et(EST_MORALE) },
        { id: cle('rccm'), label: 'Numéro RCCM', type: 'text', placeholder: 'GN-CON-2020-B-XXXX', required: false, mono: true, showIf: et(EST_MORALE) },
        // « Le Cabinet TEDSOM SARLU **représenté par Monsieur …** » : c'est le représentant
        // légal de la société nommée, pas un mandataire sous procuration — à ne pas confondre
        // avec le sous-espace `repr_*`, qui décrit une représentation à l'acte.
        { id: cle('representant_legal'), label: 'Représentant légal', type: 'text', placeholder: 'Ibrahima DIALLO', required: false, showIf: et(EST_MORALE) },
        { id: cle('representant_qualite'), label: 'Qualité du représentant légal', type: 'text', placeholder: 'Gérant', required: false, showIf: et(EST_MORALE) },
    ];
}

/**
 * Bloc de personne **dérivé du gérant**, avec une politique propre : tout l'état civil devient
 * facultatif au-delà du nom.
 *
 * C'est justifié ici et nulle part ailleurs : le liquidateur et le requérant d'une dissolution
 * peuvent être des sociétés, et exiger une date de naissance les rendrait insaisissables. Là où
 * l'étude a besoin d'un état civil complet — la constitution d'une SARLU — on **conditionne**
 * sans relâcher, en appelant `rendreBimodal()` directement.
 */
function personneBimodale(prefixe, { section, clientRole, libelleCivilite, libelleNom, showIfSection = null }) {
    const identite = GER_FIELDS.map((f, i) => ({
        ...f,
        id: f.id.replace(/^ger\./, `${prefixe}.`),
        required: i <= 1 ? f.required : false,
        ...(i === 0 ? { label: libelleCivilite } : {}),
        ...(i === 1 ? { label: libelleNom } : {}),
    }));

    return rendreBimodal(identite, { prefixe, section, clientRole, showIfSection });
}

// Liquidateur — dérivé de GER_FIELDS par substitution de préfixe, comme GERANT_ENTRANT_FIELDS.
//
// Le bloc ne comptait auparavant que trois champs libres : `liquidateur.nom`,
// `liquidateur.qualite` et une `liquidateur.adresse` en texte libre. C'est pourtant le
// dirigeant qui signe les actes de liquidation et représente la société : il lui faut le même
// état civil et la même pièce d'identité qu'à un gérant, sans quoi l'acte ne peut pas
// l'identifier.
//
// Cela corrige au passage une dérivation morte d'`ActesGeneratorService`, qui recomposait
// `liquidateur.adresse` depuis un quartier, une commune et une ville qui **n'existaient pas**
// dans ce questionnaire. Les trois champs géo existent désormais, et avec eux la cascade du
// référentiel de lieux — à condition de les inscrire en clair dans TRIPLETS_GEO, ce que la
// dérivation ne fait pas d'elle-même (c'est le défaut qu'a connu `gerant_entrant.*`).
//
// ⚠️ `liquidateur.nom` devient `liquidateur.prenom_nom`, par cohérence avec tous les autres
// blocs de personne. Aucune donnée n'est perdue : mesuré le 2026-09-28, la base ne contient
// aucun dossier SOC-DIS, et les deux gabarits Word du type sont des marque-places sans fichier.
const LIQUIDATEUR_FIELDS = [
    ...personneBimodale('liquidateur', {
        section: 'Liquidateur',
        clientRole: 'liquidateur',
        libelleCivilite: 'Civilité du liquidateur',
        libelleNom: 'Nom et prénoms / Dénomination du liquidateur',
        showIfSection: SHOW_IF_DISSOLUTION,
    }),
    { id: 'liquidateur.qualite', label: 'Qualité du liquidateur', type: 'text', placeholder: 'Associé / Tiers désigné / Cabinet', required: true, showIf: SHOW_IF_DISSOLUTION },
    // « nommé pour une durée de trois (03) mois à compter de la dissolution » (PV du 05/12/2023).
    //
    // ⚠️ Saisi, et non paramétré globalement : cette durée est **décidée par l'assemblée**, acte
    // par acte. C'est ce que le seul acte réel disponible établit, et c'est ce qui invalidait le
    // premier modèle — un seuil unique dans les paramètres aurait figé une décision variable.
    // Les trois mois sont l'usage observé sur ce dossier, pas une règle vérifiée.
    // ⚠️ Suffixe `_chiffres` délibéré : c'est lui qui fait dériver `_lettres` et `_formate` par
    // le moteur. L'acte écrit « une durée de trois (03) mois » — il lui faut les deux formes, et
    // un nom en `_mois` n'en aurait produit aucune.
    { id: 'liquidateur.duree_mandat_chiffres', label: 'Durée du mandat (mois)', type: 'number', placeholder: '3', required: false, mono: true, showIf: SHOW_IF_DISSOLUTION,
      note: "À compter de la dissolution. Laisser vide si le mandat court « pour la durée de la liquidation »." },
    { id: 'liquidateur.remuneration', label: 'Rémunération du liquidateur', type: 'text', placeholder: 'Prévue ultérieurement', required: false, showIf: SHOW_IF_DISSOLUTION },
    { id: 'liquidateur.pouvoirs', label: 'Pouvoirs conférés', type: 'textarea', placeholder: "Pouvoirs les plus étendus pour réaliser l'actif et apurer le passif…", required: false, showIf: SHOW_IF_DISSOLUTION },
];

/**
 * Requérant de l'acte — l'associé unique, ou son mandataire.
 *
 * Absent jusqu'au 2026-09-28, et c'était un manque de fond : le PV réel comparaît « A LA REQUETE
 * DE : Monsieur …, Administrateur de Société, agissant en qualité de gérant de la Société », puis
 * « **A ce, non présent, mais représenté par** Monsieur …, en vertu des pouvoirs qui lui ont été
 * conférés […] aux termes d'une procuration établie en date du 04 Décembre 2023 ». Sans partie
 * déclarée, ni la comparution ni la procuration ne pouvaient s'accrocher à quoi que ce soit.
 *
 * Préfixe `pp` — celui de la première personne physique d'un acte, déjà employé par la vente, le
 * bail et la donation. Il est déjà inscrit dans `PREFIXES_REPRESENTATION`, `TRIPLETS_GEO` et
 * `PAIRES_DATES` : aucune table dérivée à compléter, et la cascade géo comme les contrôles de
 * dates fonctionnent d'emblée.
 *
 * Rôle `associe_unique` : il existe déjà, admet la personne morale et porte le jeu de pièces des
 * associés. ⚠️ **Limite assumée** — une dissolution décidée par **plusieurs** associés demanderait
 * un bloc répétable. Aucun acte de ce type n'a été fourni, et le PV disponible est écrit d'un bout
 * à l'autre pour un associé unique : inventer la forme plurielle serait deviner.
 */
const REQUERANT_DISSOLUTION_FIELDS = [
    ...personneBimodale('pp', {
        section: 'Associé unique (requérant)',
        clientRole: 'associe_unique',
        libelleCivilite: "Civilité de l'associé unique",
        libelleNom: 'Nom et prénoms / Dénomination',
        showIfSection: SHOW_IF_DISSOLUTION,
    }),
    { id: 'pp.qualite', label: "Qualité à l'acte", type: 'text', placeholder: 'Gérant de la société / Associé unique', required: false, showIf: SHOW_IF_DISSOLUTION },
    // La procuration du PV : « non présent, mais représenté par … ». Le sous-espace `pp.repr_*`
    // et `${pp.comparution}` en découlent.
    ...blocRepresentation('pp', { showIfSection: SHOW_IF_DISSOLUTION }),
];

// Bailleur (personne physique) — Bail
const PP_BAILLEUR = [
    { id: 'pp.civilite', label: 'Civilité du bailleur', type: 'select', options: ['M.', 'Mme', 'Mlle', 'M. et Mme'], required: true, section: 'Bailleur', clientRole: 'bailleur' },
    { id: 'pp.prenom_nom', label: 'Nom et prénoms', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
    { id: 'pp.nationalite', label: 'Nationalité', type: 'text', placeholder: 'Guinéenne', required: false },
    { id: 'pp.adresse', label: 'Adresse du bailleur', type: 'text', placeholder: 'Quartier, Commune, Ville', required: true },
    { id: 'pp.piece_type', label: "Type de pièce d'identité", type: 'text', placeholder: 'CNI CEDEAO / Passeport', required: true, datalist: PIECES_TYPES },
    { id: 'pp.piece_numero', label: 'Numéro de pièce', type: 'text', placeholder: 'GN00123456', required: true, mono: true },
    { id: 'pp.telephone', label: 'Téléphone bailleur', type: 'tel', placeholder: '622 XX XX XX', required: false },
    { id: 'pp.email', label: 'Email bailleur', type: 'email', placeholder: 'email@exemple.com', required: false },
];

// Locataire / Preneur (loc.*) — Bail
const LOC_PRENEUR = [
    { id: 'loc.civilite', label: 'Civilité du locataire/preneur', type: 'select', options: ['M.', 'Mme', 'Mlle', 'M. et Mme', 'Société'], required: true, section: 'Locataire / Preneur', clientRole: 'locataire' },
    { id: 'loc.prenom_nom', label: 'Nom et prénoms / Dénomination', type: 'text', placeholder: 'Mariama SOW / Société XYZ SARL', required: true },
    { id: 'loc.nationalite', label: 'Nationalité / Pays', type: 'text', placeholder: 'Guinéenne', required: false },
    { id: 'loc.adresse', label: 'Adresse du locataire', type: 'text', placeholder: 'Quartier, Commune, Ville', required: true },
    { id: 'loc.piece_type', label: "Type de pièce", type: 'text', placeholder: 'CNI CEDEAO / RCCM', required: true, datalist: PIECES_TYPES },
    { id: 'loc.piece_numero', label: 'Numéro de pièce', type: 'text', placeholder: 'GN00123456', required: true, mono: true },
    { id: 'loc.telephone', label: 'Téléphone locataire', type: 'tel', placeholder: '622 XX XX XX', required: false },
    { id: 'loc.email', label: 'Email locataire', type: 'email', placeholder: 'email@exemple.com', required: false },
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
        { id: 'ger.est_different', label: "Le gérant est une personne différente de l'associé unique", type: 'checkbox', section: 'Gérant', clientRole: 'gerant', required: false },
        { id: 'ger.civilite', label: 'Civilité du gérant', type: 'select', options: ['M.', 'Mme', 'Mlle'], required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.prenom_nom', label: 'Nom et prénoms', type: 'text', placeholder: 'Ibrahima DIALLO', required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.ne_a', label: 'Né(e) à', type: 'text', placeholder: 'Conakry', required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.date_naissance', label: 'Date de naissance', type: 'date', placeholder: '15/03/1985', required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.nationalite', label: 'Nationalité', type: 'text', placeholder: 'Guinéenne', required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.situation_matrimoniale', label: 'Situation matrimoniale', type: 'select', options: SITUATIONS_MATRIMONIALES, required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.demeurant_ville', label: 'Ville (résidence)', type: 'text', placeholder: 'Conakry', required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.commune', label: 'Commune (résidence)', type: 'text', placeholder: 'Kaloum', required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.quartier', label: 'Quartier (résidence)', type: 'text', placeholder: 'Almamya', required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.pays', label: 'Pays de résidence', type: 'text', placeholder: 'Guinée', required: false, readonly: true, showIf: { field: 'ger.est_different' } },
        { id: 'ger.piece_type', label: "Type de pièce d'identité", type: 'text', placeholder: 'CNI CEDEAO / Passeport', required: false, showIf: { field: 'ger.est_different' }, datalist: PIECES_TYPES },
        { id: 'ger.piece_numero', label: 'Numéro de pièce', type: 'text', placeholder: 'GN00123456', required: false, mono: true, showIf: { field: 'ger.est_different' } },
        { id: 'ger.piece_delivree_le', label: 'Pièce délivrée le', type: 'date', placeholder: '01/01/2020', required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.piece_delivree_a', label: 'Délivrée à', type: 'text', placeholder: 'Conakry', required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.piece_expire_le', label: 'Expire le', type: 'date', placeholder: '01/01/2030', required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.telephone', label: 'Téléphone', type: 'tel', placeholder: '622 XX XX XX', required: false, showIf: { field: 'ger.est_different' } },
        { id: 'ger.email', label: 'Email', type: 'email', placeholder: 'email@exemple.com', required: false, showIf: { field: 'ger.est_different' } },
        ...SOC_COMMISSAIRES,
    ],

    // ── SARL — Multi-associés ───────────────────────────────────────────────
    creation_sarl: [
        ...SOC_BASE,
        {
            id: 'associes', type: 'repeatable', label: 'Associés', section: 'Associés', clientRole: 'associe',
            min: 2, max: 10,
            fields: ASSOCIE_SCHEMA,
        },
        {
            id: 'gerants', type: 'repeatable', label: 'Gérant(s)', section: 'Gérant(s)', clientRole: 'gerant',
            min: 1, max: 5,
            fields: GERANT_SCHEMA,
        },
        ...SOC_COMMISSAIRES,
    ],

    // ── SA — Société Anonyme ────────────────────────────────────────────────
    creation_sa: [
        { id: 'soc.denomination', label: 'Dénomination sociale', type: 'text', placeholder: 'Ex : Faya Holdings SA', required: true, section: 'Société', publicIntake: true },
        { id: 'soc.sigle', label: 'Sigle (facultatif)', type: 'text', placeholder: 'Ex : FH', required: false, publicIntake: true },
        { id: 'soc.capital_chiffres', label: 'Capital social (GNF — min. 140 000 000)', type: 'number', placeholder: '140000000', required: true, mono: true, publicIntake: true },
        { id: 'soc.capital_libere_chiffres', label: 'Capital libéré à la constitution (min. 35 000 000)', type: 'number', placeholder: '35000000', required: true, mono: true, publicIntake: true },
        { id: 'soc.nombre_actions', label: "Nombre d'actions", type: 'number', placeholder: '14000', required: true, mono: true, publicIntake: true },
        { id: 'soc.valeur_nominale_chiffres', label: "Valeur nominale d'une action (GNF)", type: 'number', placeholder: '10000', required: true, mono: true, readonly: true, publicIntake: true },
        { id: 'soc.siege_ville', label: 'Ville du siège social', type: 'text', placeholder: 'Conakry', required: true, publicIntake: true },
        { id: 'soc.siege_commune', label: 'Commune du siège social', type: 'text', placeholder: 'Kaloum', required: true, publicIntake: true },
        { id: 'soc.siege_quartier', label: 'Quartier du siège social', type: 'text', placeholder: 'Almamya', required: true, publicIntake: true },
        { id: 'soc.objet_social', label: 'Objet social', type: 'textarea', placeholder: 'Commerce général, import-export…', required: true, publicIntake: true },
        { id: 'soc.duree', label: 'Durée (années)', type: 'number', placeholder: '99', required: false, publicIntake: true },
        { id: 'soc.premier_exercice_annee', label: '1er exercice — année', type: 'year', placeholder: String(anneePremierExercice()), required: false, publicIntake: true },
        { id: 'soc.email_societe', label: 'Email de la société', type: 'text', placeholder: 'contact@societe.com', required: false, publicIntake: true },
        { id: 'soc.telephone_societe', label: 'Téléphone de la société', type: 'tel', placeholder: '622 XX XX XX', required: false, publicIntake: true },
        {
            id: 'actionnaires', type: 'repeatable', label: 'Actionnaires', section: 'Actionnaires', clientRole: 'actionnaire',
            min: 2, max: 20,
            fields: [
                { id: 'nom', label: 'Nom / Dénomination', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
                // `required: false` depuis le 2026-09-30, et c'est une décision mesurée : ici le
                // type de personne **ne commande rien**. Aucun `showIf` du bloc ne le consulte,
                // le rôle n'est pas dans `Partie::ROLES_ADMETTANT_PERSONNE_MORALE` (donc aucun
                // jeu de pièces n'en dépend) et **aucun des 37 gabarits .docx ne cite la
                // balise**. Un champ obligatoire sans conséquence est de la friction pure.
                //
                // Il n'est pas supprimé pour autant : l'information est réelle — un actionnaire peut être une société — et sa
                // balise est documentée. Le jour où un gabarit ou un jeu de pièces l'emploie,
                // il redeviendra exigible. C'est l'inverse des blocs d'associés, où il pilote
                // neuf champs conditionnels.
                { id: 'type_personne', label: 'Type', type: 'select', options: ['Personne physique', 'Personne morale'], required: false },
                { id: 'actions_chiffres', label: "Nombre d'actions", type: 'number', placeholder: '1000', required: true, mono: true },
                { id: 'nationalite', label: 'Nationalité / Pays', type: 'text', placeholder: 'Guinéenne', required: false },
            ],
        },
        {
            id: 'administrateurs', type: 'repeatable', label: "Membres du Conseil d'Administration", section: "Conseil d'Administration", clientRole: 'administrateur',
            min: 3, max: 12,
            fields: ADMIN_SCHEMA,
        },
        { id: 'soc.pca_nom', label: "Président du Conseil d'Administration (PCA)", type: 'text', placeholder: 'Nom du PCA', required: true, section: 'Direction' },
        { id: 'soc.pca_civilite', label: 'Civilité PCA', type: 'select', options: ['M.', 'Mme'], required: true },
        { id: 'soc.pca_adresse', label: 'Adresse du PCA', type: 'text', placeholder: 'Quartier, Commune, Ville', required: false },
        { id: 'soc.dg_nom', label: 'Directeur Général (DG)', type: 'text', placeholder: 'Nom du DG', required: false },
        { id: 'soc.dg_civilite', label: 'Civilité DG', type: 'select', options: ['M.', 'Mme'], required: false },
        ...SOC_COMMISSAIRES_REQUIS,
    ],

    // ── SAS — Multi-associés ────────────────────────────────────────────────
    creation_sas: [
        ...SOC_BASE,
        {
            id: 'associes', type: 'repeatable', label: 'Associés', section: 'Associés', clientRole: 'associe',
            min: 2, max: 20,
            fields: ASSOCIE_SCHEMA,
        },
        { id: 'soc.president_nom', label: 'Président de la SAS', type: 'text', placeholder: 'Ibrahima DIALLO', required: true, section: 'Président' },
        { id: 'soc.president_civilite', label: 'Civilité', type: 'select', options: ['M.', 'Mme', 'Mlle'], required: true },
        { id: 'soc.president_ne_a', label: 'Né(e) à', type: 'text', placeholder: 'Conakry', required: false },
        { id: 'soc.president_date_naissance', label: 'Date de naissance', type: 'date', placeholder: '15/03/1985', required: false },
        { id: 'soc.president_nationalite', label: 'Nationalité', type: 'text', placeholder: 'Guinéenne', required: false },
        { id: 'soc.president_adresse', label: 'Adresse', type: 'text', placeholder: 'Quartier, Commune, Ville', required: false },
        { id: 'soc.president_piece_numero', label: "N° pièce d'identité", type: 'text', placeholder: 'GN00123456', required: false, mono: true },
        { id: 'soc.dg_nom', label: 'Directeur Général (facultatif)', type: 'text', placeholder: 'Nom du DG', required: false, section: 'Direction' },
        ...SOC_COMMISSAIRES,
    ],

    // ── SASU — Associé unique ───────────────────────────────────────────────
    creation_sasu: [
        ...SOC_BASE,
        ...PP_ASSOCIE_UNIQUE,
        // Président : souvent l'associé unique → champs masqués par défaut
        { id: 'soc.president_est_different', label: "Le président est une personne différente de l'associé unique", type: 'checkbox', section: 'Président', required: false },
        { id: 'soc.president_civilite', label: 'Civilité', type: 'select', options: ['M.', 'Mme', 'Mlle'], required: false, showIf: { field: 'soc.president_est_different' } },
        { id: 'soc.president_nom', label: 'Nom et prénoms du président', type: 'text', placeholder: 'Ibrahima DIALLO', required: false, showIf: { field: 'soc.president_est_different' } },
        { id: 'soc.president_adresse', label: 'Adresse', type: 'text', placeholder: 'Quartier, Commune, Ville', required: false, showIf: { field: 'soc.president_est_different' } },
        { id: 'soc.president_piece_numero', label: "N° pièce d'identité", type: 'text', placeholder: 'GN00123456', required: false, mono: true, showIf: { field: 'soc.president_est_different' } },
        ...SOC_COMMISSAIRES,
    ],

    // ── SNC — Société en Nom Collectif ──────────────────────────────────────
    creation_snc: [
        ...SOC_BASE,
        {
            id: 'associes', type: 'repeatable', label: 'Associés (responsabilité illimitée)', section: 'Associés', clientRole: 'associe',
            min: 2, max: 10,
            // `schemaPersonne()` plutôt qu'un schéma de cinq champs écrit à la main : le bloc
            // était le seul des quatre formes à associés qui ne savait pas décrire une personne
            // morale, alors que `Partie::ROLES_ADMETTANT_PERSONNE_MORALE` admet bien `associe`
            // — un associé de SNC peut être une société. L'utilitaire apporte « Nature », la
            // forme, le RCCM et le représentant légal, et aligne le bloc sur SARL, SAS et les
            // trois schémas de cession. Mesuré avant de le faire : **aucun dossier SNC en
            // base**, donc pas de migration de données à prévoir.
            fields: schemaPersonne([
                { id: 'apport_chiffres', label: 'Apport (GNF)', type: 'number', placeholder: '25000000', required: true, mono: true },
            ]),
        },
        {
            id: 'gerants', type: 'repeatable', label: 'Gérant(s)', section: 'Gérant(s)', clientRole: 'gerant',
            min: 1, max: 5,
            fields: GERANT_SCHEMA,
        },
    ],

    // ── GIE — Groupement d'Intérêt Économique ──────────────────────────────
    creation_gie: [
        { id: 'soc.denomination', label: 'Dénomination du groupement', type: 'text', placeholder: 'Ex : GIE Agricole de Guinée', required: true, section: 'Groupement', publicIntake: true },
        { id: 'soc.capital_chiffres', label: 'Capital (GNF — facultatif)', type: 'number', placeholder: '0 si pas de capital', required: false, mono: true, publicIntake: true },
        { id: 'soc.siege_ville', label: 'Ville du siège', type: 'text', placeholder: 'Conakry', required: true, publicIntake: true },
        { id: 'soc.siege_commune', label: 'Commune du siège', type: 'text', placeholder: 'Kaloum', required: true, publicIntake: true },
        { id: 'soc.siege_quartier', label: 'Quartier du siège', type: 'text', placeholder: 'Almamya', required: true, publicIntake: true },
        { id: 'soc.objet_social', label: 'Objet du groupement', type: 'textarea', placeholder: 'Activités communes, mutualisation…', required: true, publicIntake: true },
        { id: 'soc.duree', label: 'Durée (années)', type: 'number', placeholder: '10', required: false, publicIntake: true },
        {
            id: 'membres', type: 'repeatable', label: 'Membres', section: 'Membres', clientRole: 'membre',
            min: 2, max: 20,
            fields: [
                { id: 'nom', label: 'Nom / Dénomination', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
                // `required: false` depuis le 2026-09-30, et c'est une décision mesurée : ici le
                // type de personne **ne commande rien**. Aucun `showIf` du bloc ne le consulte,
                // le rôle n'est pas dans `Partie::ROLES_ADMETTANT_PERSONNE_MORALE` (donc aucun
                // jeu de pièces n'en dépend) et **aucun des 37 gabarits .docx ne cite la
                // balise**. Un champ obligatoire sans conséquence est de la friction pure.
                //
                // Il n'est pas supprimé pour autant : l'information est réelle — un membre de GIE est souvent une entreprise — et sa
                // balise est documentée. Le jour où un gabarit ou un jeu de pièces l'emploie,
                // il redeviendra exigible. C'est l'inverse des blocs d'associés, où il pilote
                // neuf champs conditionnels.
                { id: 'type_personne', label: 'Type', type: 'select', options: ['Personne physique', 'Personne morale'], required: false },
                { id: 'apport_chiffres', label: 'Apport (GNF)', type: 'number', placeholder: '5000000', required: false, mono: true },
                { id: 'adresse', label: 'Adresse', type: 'text', placeholder: 'Quartier, Commune, Ville', required: false },
            ],
        },
        {
            id: 'administrateurs', type: 'repeatable', label: 'Administrateur(s)', section: 'Administration', clientRole: 'administrateur',
            min: 1, max: 5,
            fields: [
                { id: 'prenom_nom', label: 'Nom et prénoms', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
                { id: 'fonction', label: 'Fonction', type: 'text', placeholder: 'Président / Administrateur', required: false },
                { id: 'adresse', label: 'Adresse', type: 'text', placeholder: 'Quartier, Commune, Ville', required: false },
            ],
        },
        ...SOC_COMMISSAIRES,
    ],

    // ── Dissolution ─────────────────────────────────────────────────────────
    dissolution: [

        // ── 1. Société dissoute — rattachée au registre ──────────────────────
        // `societePicker` ajouté le 2026-09-28. Son absence était la **cause directe** des
        // incohérences constatées sur L'OR D'AFRIQUE : les sept champs `soc.*` étaient
        // ressaisis à la main d'un dossier à l'autre, d'où un capital à 23,9 millions sur une
        // pièce et 23,9 milliards sur la suivante. La fiche est la source de vérité
        // (décision #33) ; le questionnaire n'en est qu'une projection.
        { id: 'soc.denomination', label: 'Dénomination de la société dissoute', type: 'text', placeholder: 'Faya Distribution SARLU', required: true, section: 'Société dissoute', societePicker: true, publicIntake: true },
        { id: 'soc.forme', label: 'Forme juridique', type: 'select', options: FORMES_SOCIETE, required: true, publicIntake: true },
        { id: 'soc.rccm', label: 'Numéro RCCM', type: 'text', placeholder: 'GN-CON-2020-B-XXXX', required: true, mono: true, publicIntake: true },
        // Ces deux champs ne sont pas décoratifs : `dissolution.docx` les consomme dans la phrase
        // qui **porte la décision** — « la durée de la société qui était initialement fixée à
        // ${soc.duree_lettres} (${soc.duree}) années, à dater du ${soc.date_constitution}, est
        // réduite à ${dissolution.duree_reduite} » — et `${soc.date_constitution}` sert aussi à
        // « immatriculée au RCCM sous le numéro … en date du … ». Non déclarés, les trois
        // emplacements sortaient **vides** du PV : mesuré sur le gabarit réel le 2026-09-30.
        //
        // Aucune table de correspondance à toucher : les deux clés sont déjà dans `CHAMPS`
        // (societeFields.js) et `Societe::CHAMPS_QUESTIONNAIRE`, donc préremplies et masquées
        // dès que la fiche les porte. `societes.duree` étant non nulle (défaut 99), le premier
        // est **toujours** masqué sur une société du registre — c'est l'effet voulu.
        { id: 'soc.duree', label: 'Durée initiale de la société (années)', type: 'number', placeholder: '99', required: false, mono: true, publicIntake: true },
        { id: 'soc.date_constitution', label: "Date d'immatriculation au RCCM", type: 'date', required: false, publicIntake: true },
        { id: 'soc.capital_chiffres', label: 'Capital social (GNF)', type: 'number', placeholder: '50 000 000', required: true, mono: true, publicIntake: true },
        { id: 'soc.siege_ville', label: 'Ville du siège', type: 'text', placeholder: 'Conakry', required: true, publicIntake: true },
        { id: 'soc.siege_commune', label: 'Commune du siège', type: 'text', placeholder: 'Kaloum', required: true, publicIntake: true },
        { id: 'soc.siege_quartier', label: 'Quartier du siège', type: 'text', placeholder: 'Almamya', required: true, publicIntake: true },

        // ── 2. Phase — la variante du type d'acte ────────────────────────────
        // Une dissolution-liquidation se traite en **deux dossiers** : l'assemblée qui dissout
        // et nomme le liquidateur, puis, des mois ou des années plus tard, celle qui approuve
        // les comptes et donne quitus. Ce champ dit lequel des deux on ouvre — donc quels actes
        // produire et quel statut poser sur la fiche société.
        //
        // ⚠️ Pas de `publicIntake`, comme `modif.types` : qualifier juridiquement une opération
        // n'appartient pas au client. Le miroir PHP est `VarianteDissolution`, et
        // `ParitesVariantesSocieteTest` vérifie que les deux libellés coïncident — sans quoi la
        // phase choisie ne déclencherait strictement rien, en silence.
        { id: 'dissolution.phase', label: 'Phase de la procédure', type: 'select', required: true, section: 'Phase de la procédure',
          options: ['Dissolution anticipée', 'Clôture de la liquidation'],
          note: "Deux assemblées, deux dossiers : la dissolution nomme le liquidateur, la clôture approuve ses comptes." },

        // ── 3. Décision de dissolution (phase 1) ─────────────────────────────
        { id: 'dissolution.date_assemblee', label: "Date de l'assemblée de dissolution", type: 'date', placeholder: '01/07/2026', required: true, section: 'Décision de dissolution', showIf: SHOW_IF_DISSOLUTION, publicIntake: true },
        // « prononce, par anticipation, la dissolution […] **à compter de ce jour** » : l'effet
        // peut être différé de la date d'assemblée, et c'est lui qui fait courir la liquidation
        // et le mandat du liquidateur. Laissé facultatif — vide, l'effet est celui du jour de
        // l'assemblée, ce que l'acte réel écrit.
        { id: 'dissolution.date_effet', label: "Date d'effet de la dissolution", type: 'date', placeholder: '01/07/2026', required: false, showIf: SHOW_IF_DISSOLUTION,
          note: "Vide : la dissolution prend effet le jour de l'assemblée." },
        { id: 'dissolution.raison', label: 'Raison de dissolution', type: 'textarea', placeholder: 'Décision des associés / Objet réalisé / Autres…', required: true, showIf: SHOW_IF_DISSOLUTION, publicIntake: true },
        { id: 'dissolution.type', label: 'Type de dissolution', type: 'select', options: ['Amiable', 'Judiciaire'], required: true, showIf: SHOW_IF_DISSOLUTION, publicIntake: true },

        // « L'associé unique fixe le siège de la liquidation au siège social du Cabinet …,
        // situé au quartier Kipé Mosquée, Commune de Ratoma, Conakry » : une adresse **distincte
        // du siège social** de la société dissoute, celle où la liquidation se tient. Triplet
        // géo, donc inscrit en clair dans TRIPLETS_GEO pour bénéficier du référentiel de lieux.
        { id: 'dissolution.siege_ville', label: 'Ville du siège de liquidation', type: 'text', placeholder: 'Conakry', required: false, showIf: SHOW_IF_DISSOLUTION },
        { id: 'dissolution.siege_commune', label: 'Commune du siège de liquidation', type: 'text', placeholder: 'Ratoma', required: false, showIf: SHOW_IF_DISSOLUTION },
        { id: 'dissolution.siege_quartier', label: 'Quartier du siège de liquidation', type: 'text', placeholder: 'Kipé Mosquée', required: false, showIf: SHOW_IF_DISSOLUTION },

        // ── 4. Associé unique requérant (phase 1) ────────────────────────────
        ...REQUERANT_DISSOLUTION_FIELDS,

        // ── 5. Liquidateur (phase 1) ─────────────────────────────────────────
        ...LIQUIDATEUR_FIELDS,

        // ── 6. Formalités (renseigné au retour du greffe) ────────────────────
        // L'insertion attend « La déclaration modificative au Registre du Commerce et du Crédit
        // Mobilier a été faite sous le numéro RCCM-GN-TCC.2023……… ». Ce numéro n'existe qu'une
        // fois la formalité revenue : le champ reste donc vide à l'Édition, et se complète
        // ensuite. Facultatif par construction — l'exiger bloquerait avant même le dépôt.
        { id: 'dissolution.rccm_modificatif', label: 'N° de déclaration modificative RCCM', type: 'text', placeholder: 'RCCM-GN-TCC.2026-XXXX', required: false, mono: true, section: 'Formalités',
          note: 'Renseigné au retour du greffe. Repris dans l\'insertion au journal.' },

        // ── 7. Clôture de la liquidation (phase 2) ───────────────────────────
        // Contenu **délibérément minimal**. Rapport du liquidateur, comptes détaillés, mali de
        // liquidation : ces mentions viendront du gabarit `.docx` quand l'étude le fournira,
        // c'est lui qui dicte les balises. Déclarer ici des champs qu'aucun acte ne consomme
        // ferait saisir pour rien — et laisserait croire que la procédure est couverte.
        { id: 'cloture.date_assemblee', label: "Date de l'assemblée de clôture", type: 'date', placeholder: '01/07/2029', required: true, section: 'Clôture de la liquidation', showIf: SHOW_IF_CLOTURE },
        { id: 'cloture.quitus', label: 'Quitus donné au liquidateur', type: 'select', options: ['Oui', 'Non'], required: true, showIf: SHOW_IF_CLOTURE },
        { id: 'cloture.boni_chiffres', label: 'Boni de liquidation (GNF)', type: 'number', placeholder: '0', required: false, mono: true, showIf: SHOW_IF_CLOTURE,
          note: "Laisser vide ou à zéro s'il n'y a ni boni ni mali." },
        { id: 'cloture.observations', label: 'Observations', type: 'textarea', placeholder: "Opérations de liquidation, sort des archives sociales…", required: false, showIf: SHOW_IF_CLOTURE },
    ],

    // ── Vente immobilière avec titre foncier ────────────────────────────────
    vente_immeuble: [
        { id: 'pp.civilite', label: 'Civilité du vendeur', type: 'select', options: ['M.', 'Mme', 'Mlle', 'M. et Mme'], required: true, section: 'Vendeur', clientRole: 'vendeur' },
        { id: 'pp.prenom_nom', label: 'Nom et prénoms du vendeur', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
        { id: 'pp.ne_a', label: 'Né(e) à', type: 'text', placeholder: 'Conakry', required: false },
        { id: 'pp.date_naissance', label: 'Date de naissance', type: 'date', placeholder: '15/03/1985', required: false },
        { id: 'pp.nationalite', label: 'Nationalité', type: 'text', placeholder: 'Guinéenne', required: false },
        { id: 'pp.situation_matrimoniale', label: 'Situation matrimoniale', type: 'select', options: SITUATIONS_MATRIMONIALES, required: false },
        { id: 'pp.regime_matrimonial', label: 'Régime matrimonial', type: 'select', options: REGIMES_MATRIMONIAUX, required: false },
        { id: 'pp.demeurant_ville', label: 'Ville (résidence)', type: 'text', placeholder: 'Conakry', required: true },
        { id: 'pp.commune', label: 'Commune (résidence)', type: 'text', placeholder: 'Kaloum', required: true },
        { id: 'pp.quartier', label: 'Quartier (résidence)', type: 'text', placeholder: 'Almamya', required: true },
        { id: 'pp.pays', label: 'Pays de résidence', type: 'text', placeholder: 'Guinée', required: false, readonly: true },
        { id: 'pp.piece_type', label: "Type de pièce", type: 'text', placeholder: 'CNI CEDEAO / Passeport', required: true, datalist: PIECES_TYPES },
        { id: 'pp.piece_numero', label: 'Numéro de pièce', type: 'text', placeholder: 'GN00123456', required: true, mono: true },
        { id: 'pp.piece_delivree_le', label: 'Pièce délivrée le', type: 'date', placeholder: '01/01/2020', required: false },
        { id: 'pp.piece_delivree_a', label: 'Délivrée à', type: 'text', placeholder: 'Conakry', required: false },
        { id: 'pp.piece_expire_le', label: 'Expire le', type: 'date', placeholder: '01/01/2030', required: false },
        { id: 'pp.telephone', label: 'Téléphone vendeur', type: 'tel', placeholder: '622 XX XX XX', required: false },
        { id: 'pp.email', label: 'Email vendeur', type: 'email', placeholder: 'email@exemple.com', required: false },
        { id: 'acq.civilite', label: "Civilité de l'acquéreur", type: 'select', options: ['M.', 'Mme', 'Mlle', 'M. et Mme'], required: true, section: 'Acquéreur', clientRole: 'acheteur' },
        { id: 'acq.prenom_nom', label: "Nom et prénoms de l'acquéreur", type: 'text', placeholder: 'Mariama SOW', required: true },
        { id: 'acq.ne_a', label: 'Né(e) à', type: 'text', placeholder: 'Conakry', required: false },
        { id: 'acq.date_naissance', label: 'Date de naissance', type: 'date', placeholder: '15/03/1985', required: false },
        { id: 'acq.nationalite', label: 'Nationalité', type: 'text', placeholder: 'Guinéenne', required: false },
        { id: 'acq.situation_matrimoniale', label: 'Situation matrimoniale', type: 'select', options: SITUATIONS_MATRIMONIALES, required: false },
        { id: 'acq.regime_matrimonial', label: 'Régime matrimonial', type: 'select', options: REGIMES_MATRIMONIAUX, required: false },
        { id: 'acq.demeurant_ville', label: 'Ville (résidence)', type: 'text', placeholder: 'Conakry', required: true },
        { id: 'acq.commune', label: 'Commune (résidence)', type: 'text', placeholder: 'Kaloum', required: true },
        { id: 'acq.quartier', label: 'Quartier (résidence)', type: 'text', placeholder: 'Almamya', required: true },
        { id: 'acq.pays', label: 'Pays de résidence', type: 'text', placeholder: 'Guinée', required: false, readonly: true },
        { id: 'acq.piece_type', label: "Type de pièce", type: 'text', placeholder: 'CNI CEDEAO / Passeport', required: true, datalist: PIECES_TYPES },
        { id: 'acq.piece_numero', label: 'Numéro de pièce', type: 'text', placeholder: 'GN00123456', required: true, mono: true },
        { id: 'acq.piece_delivree_le', label: 'Pièce délivrée le', type: 'date', placeholder: '01/01/2020', required: false },
        { id: 'acq.piece_delivree_a', label: 'Délivrée à', type: 'text', placeholder: 'Conakry', required: false },
        { id: 'acq.piece_expire_le', label: 'Expire le', type: 'date', placeholder: '01/01/2030', required: false },
        { id: 'acq.telephone', label: "Téléphone acquéreur", type: 'tel', placeholder: '622 XX XX XX', required: false },
        { id: 'acq.email', label: "Email acquéreur", type: 'email', placeholder: 'email@exemple.com', required: false },
        { id: 'bien.parcelle_numero', label: 'Numéro de parcelle', type: 'text', placeholder: 'P-001', required: false, mono: true, section: 'Bien immobilier', publicIntake: true },
        { id: 'bien.lot', label: 'Lot', type: 'text', placeholder: 'Lot 12', required: false, publicIntake: true },
        { id: 'bien.lieu_de', label: 'Situé à', type: 'text', placeholder: 'Quartier, Commune, Ville', required: true, publicIntake: true },
        { id: 'bien.nature_terrain', label: 'Nature du terrain', type: 'text', placeholder: 'Terrain nu / Immeuble bâti', required: true, publicIntake: true },
        { id: 'bien.usage', label: 'Usage', type: 'select', options: ['Résidentiel', 'Commercial', 'Industriel', 'Mixte', 'Agricole'], required: true, publicIntake: true },
        { id: 'bien.superficie', label: 'Superficie (m²)', type: 'number', placeholder: '500', required: true, mono: true, publicIntake: true },
        { id: 'bien.pcp', label: 'PCP (Plan Cadastral Parcellaire)', type: 'text', placeholder: 'PCP-XXX', required: false, mono: true },
        { id: 'bien.titre_foncier_numero', label: 'Numéro du titre foncier', type: 'text', placeholder: 'TF-2018-KAL-004521', required: true, mono: true, publicIntake: true },
        { id: 'bien.livre_foncier_ville', label: 'Ville du livre foncier', type: 'text', placeholder: 'Conakry', required: true },
        { id: 'bien.limite_nord', label: 'Limite Nord', type: 'text', placeholder: 'Rue de la Paix', required: false },
        { id: 'bien.limite_sud', label: 'Limite Sud', type: 'text', placeholder: 'Parcelle de M. Camara', required: false },
        { id: 'bien.limite_est', label: 'Limite Est', type: 'text', placeholder: 'Route Nationale', required: false },
        { id: 'bien.limite_ouest', label: 'Limite Ouest', type: 'text', placeholder: 'Cours d\'eau', required: false },
        { id: 'bien.origine_propriete', label: 'Origine de la propriété', type: 'textarea', placeholder: 'Achat selon acte du…', required: false },
        { id: 'bien.prix_vente_chiffres', label: 'Prix de vente (GNF)', type: 'number', placeholder: '250000000', required: true, mono: true, section: 'Transaction', publicIntake: true },
        { id: 'transaction.taxe_plusvalue_chiffres', label: 'Taxe de plus-value (GNF)', type: 'number', placeholder: '0', required: false, mono: true },
        { id: 'transaction.provision_chiffres', label: 'Provision réclamée (GNF)', type: 'number', placeholder: '5000000', required: false, mono: true },
    ],

    // ── Vente immobilière sans titre foncier ────────────────────────────────
    vente_sans_titre: [
        { id: 'pp.civilite', label: 'Civilité du vendeur', type: 'select', options: ['M.', 'Mme', 'Mlle', 'M. et Mme'], required: true, section: 'Vendeur', clientRole: 'vendeur' },
        { id: 'pp.prenom_nom', label: 'Nom et prénoms du vendeur', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
        { id: 'pp.ne_a', label: 'Né(e) à', type: 'text', placeholder: 'Conakry', required: false },
        { id: 'pp.date_naissance', label: 'Date de naissance', type: 'date', placeholder: '15/03/1985', required: false },
        { id: 'pp.nationalite', label: 'Nationalité', type: 'text', placeholder: 'Guinéenne', required: false },
        { id: 'pp.situation_matrimoniale', label: 'Situation matrimoniale', type: 'select', options: SITUATIONS_MATRIMONIALES, required: false },
        { id: 'pp.regime_matrimonial', label: 'Régime matrimonial', type: 'select', options: REGIMES_MATRIMONIAUX, required: false },
        { id: 'pp.demeurant_ville', label: 'Ville (résidence)', type: 'text', placeholder: 'Conakry', required: true },
        { id: 'pp.commune', label: 'Commune (résidence)', type: 'text', placeholder: 'Kaloum', required: true },
        { id: 'pp.quartier', label: 'Quartier (résidence)', type: 'text', placeholder: 'Almamya', required: true },
        { id: 'pp.pays', label: 'Pays de résidence', type: 'text', placeholder: 'Guinée', required: false, readonly: true },
        { id: 'pp.piece_type', label: "Type de pièce", type: 'text', placeholder: 'CNI CEDEAO / Passeport', required: true, datalist: PIECES_TYPES },
        { id: 'pp.piece_numero', label: 'Numéro de pièce', type: 'text', placeholder: 'GN00123456', required: true, mono: true },
        { id: 'pp.piece_delivree_le', label: 'Pièce délivrée le', type: 'date', placeholder: '01/01/2020', required: false },
        { id: 'pp.piece_delivree_a', label: 'Délivrée à', type: 'text', placeholder: 'Conakry', required: false },
        { id: 'pp.piece_expire_le', label: 'Expire le', type: 'date', placeholder: '01/01/2030', required: false },
        { id: 'pp.telephone', label: 'Téléphone vendeur', type: 'tel', placeholder: '622 XX XX XX', required: false },
        { id: 'pp.email', label: 'Email vendeur', type: 'email', placeholder: 'email@exemple.com', required: false },
        { id: 'acq.civilite', label: "Civilité de l'acquéreur", type: 'select', options: ['M.', 'Mme', 'Mlle', 'M. et Mme'], required: true, section: 'Acquéreur', clientRole: 'acheteur' },
        { id: 'acq.prenom_nom', label: "Nom et prénoms de l'acquéreur", type: 'text', placeholder: 'Mariama SOW', required: true },
        { id: 'acq.ne_a', label: 'Né(e) à', type: 'text', placeholder: 'Conakry', required: false },
        { id: 'acq.date_naissance', label: 'Date de naissance', type: 'date', placeholder: '15/03/1985', required: false },
        { id: 'acq.nationalite', label: 'Nationalité', type: 'text', placeholder: 'Guinéenne', required: false },
        { id: 'acq.situation_matrimoniale', label: 'Situation matrimoniale', type: 'select', options: SITUATIONS_MATRIMONIALES, required: false },
        { id: 'acq.regime_matrimonial', label: 'Régime matrimonial', type: 'select', options: REGIMES_MATRIMONIAUX, required: false },
        { id: 'acq.demeurant_ville', label: 'Ville (résidence)', type: 'text', placeholder: 'Conakry', required: true },
        { id: 'acq.commune', label: 'Commune (résidence)', type: 'text', placeholder: 'Kaloum', required: true },
        { id: 'acq.quartier', label: 'Quartier (résidence)', type: 'text', placeholder: 'Almamya', required: true },
        { id: 'acq.pays', label: 'Pays de résidence', type: 'text', placeholder: 'Guinée', required: false, readonly: true },
        { id: 'acq.piece_type', label: "Type de pièce", type: 'text', placeholder: 'CNI CEDEAO / Passeport', required: true, datalist: PIECES_TYPES },
        { id: 'acq.piece_numero', label: 'Numéro de pièce', type: 'text', placeholder: 'GN00123456', required: true, mono: true },
        { id: 'acq.piece_delivree_le', label: 'Pièce délivrée le', type: 'date', placeholder: '01/01/2020', required: false },
        { id: 'acq.piece_delivree_a', label: 'Délivrée à', type: 'text', placeholder: 'Conakry', required: false },
        { id: 'acq.piece_expire_le', label: 'Expire le', type: 'date', placeholder: '01/01/2030', required: false },
        { id: 'acq.telephone', label: "Téléphone acquéreur", type: 'tel', placeholder: '622 XX XX XX', required: false },
        { id: 'acq.email', label: "Email acquéreur", type: 'email', placeholder: 'email@exemple.com', required: false },
        { id: 'bien.parcelle_numero', label: 'Numéro de parcelle', type: 'text', placeholder: 'P-001', required: true, mono: true, section: 'Bien immobilier', publicIntake: true },
        { id: 'bien.lot', label: 'Lot', type: 'text', placeholder: 'Lot 12', required: false, publicIntake: true },
        { id: 'bien.lieu_de', label: 'Situé à', type: 'text', placeholder: 'Quartier, Commune, Ville', required: true, publicIntake: true },
        { id: 'bien.nature_terrain', label: 'Nature du terrain', type: 'text', placeholder: 'Terrain nu / Immeuble bâti', required: true, publicIntake: true },
        { id: 'bien.usage', label: 'Usage', type: 'select', options: ['Résidentiel', 'Commercial', 'Industriel', 'Mixte', 'Agricole'], required: true, publicIntake: true },
        { id: 'bien.superficie', label: 'Superficie (m²)', type: 'number', placeholder: '500', required: true, mono: true, publicIntake: true },
        { id: 'bien.autorisation_occuper', label: "Autorisation d'occuper / Acte de cession", type: 'text', placeholder: 'AO-XXX / Référence', required: false, mono: true, publicIntake: true },
        { id: 'bien.origine_propriete', label: 'Origine de la propriété', type: 'textarea', placeholder: 'Achat selon acte du…', required: false },
        { id: 'bien.prix_vente_chiffres', label: 'Prix de vente (GNF)', type: 'number', placeholder: '250000000', required: true, mono: true, section: 'Transaction', publicIntake: true },
        { id: 'transaction.taxe_plusvalue_chiffres', label: 'Taxe de plus-value (GNF)', type: 'number', placeholder: '0', required: false, mono: true },
        { id: 'transaction.provision_chiffres', label: 'Provision réclamée (GNF)', type: 'number', placeholder: '5000000', required: false, mono: true },
    ],

    // ── Bail d'habitation ───────────────────────────────────────────────────
    bail_habitation: [
        ...PP_BAILLEUR,
        ...LOC_PRENEUR,
        { id: 'bien.adresse', label: 'Adresse du bien loué', type: 'text', placeholder: 'Quartier, Commune, Ville', required: true, section: 'Bien immobilier', publicIntake: true },
        { id: 'bien.description', label: 'Description du bien', type: 'textarea', placeholder: 'Villa 4 pièces / Appartement F3…', required: false, publicIntake: true },
        { id: 'bien.superficie', label: 'Superficie (m²)', type: 'number', placeholder: '120', required: false, mono: true, publicIntake: true },
        { id: 'bien.usage', label: 'Usage', type: 'select', options: ['Résidentiel', 'Usage mixte'], required: true, publicIntake: true },
        { id: 'bien.origine_propriete', label: 'Origine de la propriété du bailleur', type: 'textarea', placeholder: 'Acquis par acte du…, titre foncier n°…', required: false, publicIntake: true },
        { id: 'bail.date_prise_effet', label: "Date de prise d'effet", type: 'date', placeholder: '01/08/2026', required: true, section: 'Conditions du bail', publicIntake: true },
        { id: 'bail.duree_chiffres', label: 'Durée du bail (années)', type: 'number', placeholder: '2', required: true, mono: true, publicIntake: true },
        { id: 'bail.loyer_chiffres', label: 'Loyer mensuel (GNF)', type: 'number', placeholder: '5000000', required: true, mono: true, publicIntake: true },
        { id: 'bail.periodicite', label: 'Périodicité du paiement', type: 'select', options: ['Mensuel', 'Trimestriel', 'Semestriel', 'Annuel'], required: true, publicIntake: true },
        { id: 'bail.caution_chiffres', label: 'Caution (GNF)', type: 'number', placeholder: '10000000', required: false, mono: true, publicIntake: true },
        { id: 'bail.avance_loyer', label: "Avance sur loyer (mois)", type: 'number', placeholder: '3', required: false, mono: true, publicIntake: true },
        { id: 'bail.destination', label: 'Destination des lieux', type: 'text', placeholder: 'Habitation principale', required: false, publicIntake: true },
    ],

    // ── Bail commercial ─────────────────────────────────────────────────────
    bail_commercial: [
        ...PP_BAILLEUR,
        ...LOC_PRENEUR,
        { id: 'bien.adresse', label: 'Adresse du local commercial', type: 'text', placeholder: 'Quartier, Commune, Ville', required: true, section: 'Local commercial', publicIntake: true },
        { id: 'bien.description', label: 'Description du local', type: 'textarea', placeholder: 'Local rez-de-chaussée, 80 m²…', required: false, publicIntake: true },
        { id: 'bien.superficie', label: 'Superficie (m²)', type: 'number', placeholder: '80', required: false, mono: true, publicIntake: true },
        { id: 'bail.date_prise_effet', label: "Date de prise d'effet", type: 'date', placeholder: '01/08/2026', required: true, section: 'Conditions du bail', publicIntake: true },
        { id: 'bail.duree_chiffres', label: 'Durée du bail (années)', type: 'number', placeholder: '3', required: true, mono: true, publicIntake: true },
        { id: 'bail.loyer_chiffres', label: 'Loyer mensuel (GNF)', type: 'number', placeholder: '20000000', required: true, mono: true, publicIntake: true },
        { id: 'bail.periodicite', label: 'Périodicité du paiement', type: 'select', options: ['Mensuel', 'Trimestriel', 'Semestriel', 'Annuel'], required: true, publicIntake: true },
        { id: 'bail.caution_chiffres', label: 'Caution (GNF)', type: 'number', placeholder: '60000000', required: false, mono: true, publicIntake: true },
        { id: 'bail.droit_entree_chiffres', label: "Droit d'entrée / Pas-de-porte (GNF)", type: 'number', placeholder: '0', required: false, mono: true, publicIntake: true },
        { id: 'bail.destination', label: 'Activité commerciale autorisée', type: 'text', placeholder: 'Commerce général, import-export…', required: true, publicIntake: true },
        { id: 'bail.clause_renouvellement', label: 'Clause de renouvellement', type: 'text', placeholder: 'Tacite reconduction / Non renouvelable', required: false, publicIntake: true },
    ],

    // ── Bail à construction ─────────────────────────────────────────────────
    bail_construction: [
        ...PP_BAILLEUR,
        ...LOC_PRENEUR,
        { id: 'bien.superficie', label: 'Superficie du terrain (m²)', type: 'number', placeholder: '2000', required: true, mono: true, section: 'Terrain', publicIntake: true },
        { id: 'bien.lieu_de', label: 'Situé à', type: 'text', placeholder: 'Quartier, Commune, Ville', required: true, publicIntake: true },
        { id: 'bien.titre_foncier_numero', label: 'Numéro du titre foncier', type: 'text', placeholder: 'TF-2018-KAL-004521', required: false, mono: true, publicIntake: true },
        { id: 'bien.description', label: 'Description du terrain', type: 'textarea', placeholder: 'Terrain nu, non construit, clôturé…', required: false, publicIntake: true },
        { id: 'bail.date_prise_effet', label: "Date de prise d'effet", type: 'date', placeholder: '01/08/2026', required: true, section: 'Conditions du bail', publicIntake: true },
        { id: 'bail.duree_chiffres', label: 'Durée du bail à construction (années)', type: 'number', placeholder: '30', required: true, mono: true, publicIntake: true },
        { id: 'bail.loyer_chiffres', label: 'Redevance annuelle (GNF)', type: 'number', placeholder: '10000000', required: true, mono: true, publicIntake: true },
        { id: 'bail.engagement_construction', label: 'Engagement de construction', type: 'textarea', placeholder: 'Nature des constructions prévues, délai de réalisation…', required: true, publicIntake: true },
        { id: 'bail.valeur_constructions_chiffres', label: 'Valeur estimée des constructions (GNF)', type: 'number', placeholder: '500000000', required: false, mono: true, publicIntake: true },
        { id: 'bail.destination', label: 'Destination des constructions', type: 'text', placeholder: 'Résidentiel / Commercial / Industriel', required: false, publicIntake: true },
    ],

    // ── Hypothèque conventionnelle ──────────────────────────────────────────
    hypotheque_conv: [
        { id: 'pp.civilite', label: 'Civilité du débiteur', type: 'select', options: ['M.', 'Mme', 'Mlle'], required: true, section: 'Débiteur / Emprunteur', clientRole: 'debiteur' },
        { id: 'pp.prenom_nom', label: 'Nom et prénoms', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
        { id: 'pp.ne_a', label: 'Né(e) à', type: 'text', placeholder: 'Conakry', required: false },
        { id: 'pp.date_naissance', label: 'Date de naissance', type: 'date', placeholder: '15/03/1985', required: false },
        { id: 'pp.nationalite', label: 'Nationalité', type: 'text', placeholder: 'Guinéenne', required: false },
        { id: 'pp.situation_matrimoniale', label: 'Situation matrimoniale', type: 'select', options: SITUATIONS_MATRIMONIALES, required: false },
        { id: 'pp.regime_matrimonial', label: 'Régime matrimonial', type: 'select', options: REGIMES_MATRIMONIAUX, required: false },
        { id: 'pp.adresse', label: 'Adresse complète', type: 'text', placeholder: 'Quartier, Commune, Ville', required: true },
        { id: 'pp.piece_type', label: "Type de pièce d'identité", type: 'text', placeholder: 'CNI CEDEAO / Passeport', required: true, datalist: PIECES_TYPES },
        { id: 'pp.piece_numero', label: 'Numéro de pièce', type: 'text', placeholder: 'GN00123456', required: true, mono: true },
        { id: 'pp.piece_delivree_le', label: 'Pièce délivrée le', type: 'date', placeholder: '01/01/2020', required: false },
        { id: 'pp.piece_delivree_a', label: 'Délivrée à', type: 'text', placeholder: 'Conakry', required: false },
        { id: 'pp.telephone', label: 'Téléphone', type: 'tel', placeholder: '622 XX XX XX', required: false },
        { id: 'pp.email', label: 'Email', type: 'email', placeholder: 'email@exemple.com', required: false },
        { id: 'bq.denomination', label: 'Dénomination de la banque', type: 'text', placeholder: 'Ecobank Guinée SA', required: true, section: 'Banque / Créancier', clientRole: 'creancier' },
        { id: 'bq.forme', label: 'Forme juridique', type: 'text', placeholder: 'SA', required: false },
        { id: 'bq.siege_ville', label: 'Ville', type: 'text', placeholder: 'Conakry', required: false },
        { id: 'bq.siege_commune', label: 'Commune du siège', type: 'text', placeholder: 'Conakry', required: false },
        { id: 'bq.siege_quartier', label: 'Quartier du siège', type: 'text', placeholder: 'Kaloum', required: false },
        { id: 'bq.representant_nom', label: 'Représentant légal de la banque', type: 'text', placeholder: 'Nom du Directeur Général', required: true },
        { id: 'bq.representant_qualite', label: 'Qualité du représentant', type: 'text', placeholder: 'Directeur Général / Fondé de pouvoir', required: false },
        { id: 'bq.montant_credit_chiffres', label: 'Montant du crédit accordé (GNF)', type: 'number', placeholder: '500000000', required: true, mono: true },
        { id: 'bq.taux_interet', label: "Taux d'intérêt annuel (%)", type: 'number', placeholder: '18', required: false, mono: true, decimals: 2 },
        { id: 'bq.duree_credit_chiffres', label: 'Durée du crédit (mois)', type: 'number', placeholder: '60', required: false, mono: true },
        { id: 'bq.type_garantie', label: 'Type de garantie', type: 'text', placeholder: 'Affectation hypothécaire de 1er rang', required: false },
        { id: 'bq.rang_hypothecaire', label: "Rang de l'hypothèque", type: 'text', placeholder: '1er rang', required: false },
        { id: 'bien.titre_foncier_numero', label: 'Numéro du titre foncier hypothéqué', type: 'text', placeholder: 'TF-2018-KAL-004521', required: true, mono: true, section: 'Bien hypothéqué', publicIntake: true },
        { id: 'bien.superficie', label: 'Superficie (m²)', type: 'number', placeholder: '500', required: false, mono: true, publicIntake: true },
        { id: 'bien.lieu_de', label: 'Situé à', type: 'text', placeholder: 'Quartier, Commune, Ville', required: true, publicIntake: true },
        { id: 'bien.nature_terrain', label: 'Nature du terrain / bien', type: 'text', placeholder: 'Terrain bâti / Immeuble à usage résidentiel', required: false, publicIntake: true },
        { id: 'bien.limite_nord', label: 'Limite Nord', type: 'text', placeholder: 'Rue de la Paix', required: false },
        { id: 'bien.limite_sud', label: 'Limite Sud', type: 'text', placeholder: 'Parcelle de M. Camara', required: false },
        { id: 'bien.limite_est', label: 'Limite Est', type: 'text', placeholder: 'Route Nationale', required: false },
        { id: 'bien.limite_ouest', label: 'Limite Ouest', type: 'text', placeholder: "Cours d'eau", required: false },
    ],

    // ── Mainlevée d'hypothèque ──────────────────────────────────────────────
    mainlevee: [
        { id: 'pp.civilite', label: 'Civilité du débiteur', type: 'select', options: ['M.', 'Mme', 'Mlle'], required: true, section: 'Débiteur', clientRole: 'debiteur' },
        { id: 'pp.prenom_nom', label: 'Nom et prénoms', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
        { id: 'pp.adresse', label: 'Adresse', type: 'text', placeholder: 'Quartier, Commune, Ville', required: true },
        { id: 'pp.piece_type', label: "Type de pièce", type: 'text', placeholder: 'CNI CEDEAO / Passeport', required: false, datalist: PIECES_TYPES },
        { id: 'pp.piece_numero', label: 'Numéro de pièce', type: 'text', placeholder: 'GN00123456', required: false, mono: true },
        { id: 'pp.telephone', label: 'Téléphone', type: 'tel', placeholder: '622 XX XX XX', required: false },
        { id: 'bq.denomination', label: 'Dénomination de la banque créancière', type: 'text', placeholder: 'Ecobank Guinée SA', required: true, section: 'Banque créancière', clientRole: 'creancier' },
        { id: 'bq.representant_nom', label: 'Représentant légal', type: 'text', placeholder: 'Nom du Directeur Général', required: true },
        { id: 'bq.representant_qualite', label: 'Qualité du représentant', type: 'text', placeholder: 'Directeur Général / Fondé de pouvoir', required: false },
        { id: 'hypotheque.reference_acte', label: "Référence de l'acte d'hypothèque", type: 'text', placeholder: 'HYP-2023-0045', required: true, mono: true, section: 'Hypothèque à radier' },
        { id: 'hypotheque.date_acte', label: "Date de l'acte", type: 'date', placeholder: '15/03/2023', required: true },
        { id: 'hypotheque.notaire_acte', label: 'Notaire instrumentaire', type: 'text', placeholder: 'Maître Ayelama BAH', required: false },
        { id: 'hypotheque.montant_chiffres', label: 'Montant garanti à l\'origine (GNF)', type: 'number', placeholder: '500000000', required: false, mono: true },
        { id: 'bien.titre_foncier_numero', label: 'Numéro du titre foncier concerné', type: 'text', placeholder: 'TF-2018-KAL-004521', required: true, mono: true, publicIntake: true },
        { id: 'hypotheque.rang', label: "Rang de l'hypothèque", type: 'text', placeholder: '1er rang', required: false },
    ],

    // ── Modification de société ─────────────────────────────────────────────
    // Réécrit le 2026-08-11. La version précédente tenait en six champs, dont un
    // texte libre pour la dénomination et un choix unique pour le type de
    // modification : elle ne captait ni l'état de la société **avant** le
    // changement, ni l'assemblée qui le décide, ni les personnes qui y figurent —
    // or les statuts mis à jour et le procès-verbal exigent les trois.
    //
    // Trois principes :
    //   1. la société vient du **registre** (`societePicker`), pas d'une ressaisie ;
    //   2. l'assemblée décide **plusieurs** modifications (`modif.types`), constatées
    //      par un seul PV ;
    //   3. chaque type ouvre son bloc « avant → après », et rien d'autre.
    modification: [

        // ── 1. Société concernée — état AVANT modification ───────────────────
        // Rattachée à une fiche du registre : les champs sont alors masqués et
        // remplis par la fiche (même parti pris que pour les clients liés). Ils
        // restent déclarés parce qu'ils sont la projection ${soc.*} attendue par
        // les modèles Word.
        { id: 'soc.denomination', label: 'Dénomination sociale', type: 'text', placeholder: 'Raison sociale exacte', required: true, section: 'Société concernée', societePicker: true, publicIntake: true },
        { id: 'soc.sigle', label: 'Sigle', type: 'text', placeholder: 'Ex : FD', required: false, publicIntake: true },
        { id: 'soc.forme', label: 'Forme juridique', type: 'select', options: FORMES_SOCIETE, required: true, publicIntake: true },
        { id: 'soc.rccm', label: 'Numéro RCCM actuel', type: 'text', placeholder: 'GN-CON-2020-B-XXXX', required: true, mono: true, publicIntake: true },
        { id: 'soc.nif', label: 'NIF', type: 'text', placeholder: '000123456', required: false, mono: true, publicIntake: true },
        // Précisé le 2026-09-29 : c'est la date d'**immatriculation au RCCM**, celle que l'acte
        // cite (« immatriculée sous le numéro …, en date du … »), et celle depuis laquelle
        // court la durée statutaire. Elle se remplit désormais toute seule au retour de la
        // formalité APIP — voir App\Enums\DonneeAuRetour::RccmDate.
        { id: 'soc.date_constitution', label: "Date d'immatriculation au RCCM", type: 'date', placeholder: '15/03/2020', required: false, publicIntake: true },
        { id: 'soc.capital_chiffres', label: 'Capital social actuel (GNF)', type: 'number', placeholder: '50 000 000', required: true, mono: true, publicIntake: true },
        { id: 'soc.nombre_parts', label: 'Nombre de parts actuel', type: 'number', placeholder: '100', required: false, mono: true, publicIntake: true },
        { id: 'soc.valeur_nominale_chiffres', label: "Valeur nominale d'une part (GNF)", type: 'number', placeholder: '500 000', required: false, mono: true, publicIntake: true },
        { id: 'soc.siege_ville', label: 'Ville du siège actuel', type: 'text', placeholder: 'Conakry', required: true, publicIntake: true },
        { id: 'soc.siege_commune', label: 'Commune du siège actuel', type: 'text', placeholder: 'Kaloum', required: true, publicIntake: true },
        { id: 'soc.siege_quartier', label: 'Quartier du siège actuel', type: 'text', placeholder: 'Almamya', required: true, publicIntake: true },
        { id: 'soc.objet_social', label: 'Objet social actuel', type: 'textarea', placeholder: 'Commerce général, import-export…', required: false, publicIntake: true },
        { id: 'soc.gerant_actuel', label: 'Gérant / dirigeant actuel', type: 'text', placeholder: 'Ibrahima DIALLO', required: false, publicIntake: true },
        { id: 'soc.email_societe', label: 'Email de la société', type: 'text', placeholder: 'contact@societe.com', required: false, publicIntake: true },
        { id: 'soc.telephone_societe', label: 'Téléphone de la société', type: 'tel', placeholder: '622 XX XX XX', required: false, publicIntake: true },

        // ── 2. Modification(s) décidée(s) ────────────────────────────────────
        // Miroir de App\Enums\TypeModificationStatutaire — les libellés doivent
        // correspondre exactement (depuisLibelles() les reconnaît par label). Choix
        // multiple : une même assemblée décide couramment une cession de parts, un
        // nouveau gérant et un transfert de siège. Jamais `publicIntake` : la
        // qualification juridique du changement n'est pas au client de la faire.
        { id: 'modif.types', label: 'Modifications décidées', type: 'checkbox_group', required: true, section: 'Modification(s) décidée(s)',
          // Fait afficher sous les cases l'impact, les actes produits et les droits
          // d'enregistrement de la sélection courante (voir ConsequencesModification).
          consequences: 'modification',
          options: [
            'Changement de gérant statutaire',
            'Changement de gérant non statutaire',
            'Transfert du siège social',
            'Augmentation de capital',
            'Diminution de capital',
            'Cession de parts sociales',
            "Modification de l'objet social",
            'Modification de dénomination',
            'Modification de forme juridique',
          ],
          note: 'Détermine les actes à produire, les formalités à engager et les droits à percevoir.' },

        // ── 3. Assemblée générale (procès-verbal) ────────────────────────────
        // Produit dans TOUS les cas de modification. Le président et le secrétaire de
        // séance sont de simples mentions de l'acte : ils ne fournissent aucune pièce,
        // donc pas de `clientRole` ni de fiche client à rattacher.
        { id: 'ag.type', label: "Nature de la décision", type: 'select', required: true, section: 'Assemblée générale',
          options: ['Assemblée générale extraordinaire', 'Assemblée générale ordinaire', "Décision de l'associé unique"] },
        { id: 'ag.date', label: "Date de l'assemblée", type: 'date', placeholder: '01/08/2026', required: true },
        { id: 'ag.heure', label: 'Heure', type: 'text', placeholder: '10h00', required: false },
        { id: 'ag.lieu', label: "Lieu de l'assemblée", type: 'text', placeholder: 'Siège social, Conakry', required: false },
        { id: 'ag.president_seance', label: 'Président de séance', type: 'text', placeholder: 'Ibrahima DIALLO', required: false },
        { id: 'ag.secretaire_seance', label: 'Secrétaire de séance', type: 'text', placeholder: 'Mariama SOW', required: false },
        { id: 'ag.parts_representees', label: 'Parts présentes ou représentées', type: 'number', placeholder: '100', required: false, mono: true },
        { id: 'ag.quorum_atteint', label: 'Quorum atteint', type: 'checkbox', required: false },
        // Souvent différente de la date d'assemblée (effet différé, ou rétroactif au
        // premier jour de l'exercice) : c'est elle qui figure aux statuts mis à jour.
        { id: 'ag.date_effet', label: "Date d'effet de la modification", type: 'date', placeholder: '01/09/2026', required: false,
          note: "Laisser vide si l'effet est immédiat à la date de l'assemblée." },
        { id: 'ag.resolutions', label: 'Résolutions adoptées', type: 'textarea', placeholder: 'Texte des résolutions, ou complément au détail des blocs ci-dessous', required: false },

        // ── 4. Cession de parts sociales ─────────────────────────────────────
        // Actes édités : acte de cession, PV d'AGE, statuts mis à jour et RCCM.
        {
            id: 'modif.cedants', type: 'repeatable', label: 'Cédant(s)', section: 'Cession de parts — cédants', clientRole: 'cedant',
            min: 1, max: 10, showIf: { field: 'modif.types', includes: 'Cession de parts sociales' },
            fields: CEDANT_SCHEMA,
        },
        {
            id: 'modif.cessionnaires', type: 'repeatable', label: 'Cessionnaire(s)', section: 'Cession de parts — cessionnaires', clientRole: 'cessionnaire',
            min: 1, max: 10, showIf: { field: 'modif.types', includes: 'Cession de parts sociales' },
            fields: CESSIONNAIRE_SCHEMA,
        },
        { id: 'modif.date_cession', label: 'Date de la cession', type: 'date', placeholder: '01/08/2026', required: false, section: 'Cession de parts — conditions',
          showIf: { field: 'modif.types', includes: 'Cession de parts sociales' } },
        { id: 'modif.valeur_parts_cedees', label: 'Valeur totale des parts cédées (GNF)', type: 'number', placeholder: '25 000 000', required: true, mono: true,
          showIf: { field: 'modif.types', includes: 'Cession de parts sociales' },
          note: 'Assiette du droit de cession de 2 % — la valeur des parts, non le capital social.' },
        // L'agrément préalable des associés est une condition de validité de la
        // cession à un tiers en SARL : sans lui, l'acte est attaquable.
        { id: 'modif.agrement_associes', label: 'Agrément des associés obtenu', type: 'checkbox', required: false,
          showIf: { field: 'modif.types', includes: 'Cession de parts sociales' },
          note: "Obligatoire pour une cession à un tiers en SARL — mentionné au procès-verbal." },
        // ── 5. Transfert du siège social ─────────────────────────────────────
        // Documents établis : statuts mis à jour et procès-verbal.
        { id: 'modif.siege_nouveau_ville', section: 'Transfert du siège social', label: 'Nouvelle ville', type: 'text', placeholder: 'Conakry', required: true,
          showIf: { field: 'modif.types', includes: 'Transfert du siège social' } },
        { id: 'modif.siege_nouveau_commune', label: 'Nouvelle commune', type: 'text', placeholder: 'Kaloum', required: true,
          showIf: { field: 'modif.types', includes: 'Transfert du siège social' } },
        { id: 'modif.siege_nouveau_quartier', label: 'Nouveau quartier du siège', type: 'text', placeholder: 'Almamya', required: true,
          showIf: { field: 'modif.types', includes: 'Transfert du siège social' } },
        { id: 'modif.siege_justificatif', label: "Titre d'occupation du nouveau siège", type: 'select',
          options: ['Bail', 'Titre foncier', 'Attestation de domiciliation', 'Autre'], required: false,
          showIf: { field: 'modif.types', includes: 'Transfert du siège social' } },

        // ── 6. Augmentation de capital ───────────────────────────────────────
        // Une DNSV est établie pour constater le montant augmenté.
        { id: 'modif.augmentation_montant', label: "Montant de l'augmentation (GNF)", type: 'number', placeholder: '50 000 000', required: true, mono: true, section: 'Augmentation de capital',
          showIf: { field: 'modif.types', includes: 'Augmentation de capital' } },
        { id: 'modif.augmentation_capital_apres', label: 'Capital après augmentation (GNF)', type: 'number', placeholder: '100 000 000', required: false, mono: true, readonly: true,
          showIf: { field: 'modif.types', includes: 'Augmentation de capital' },
          note: 'Calculé : capital actuel + montant de l’augmentation.' },
        { id: 'modif.augmentation_modalite', label: "Modalité de l'augmentation", type: 'select',
          options: ['Apports en numéraire', 'Apports en nature', 'Incorporation de réserves'], required: true,
          showIf: { field: 'modif.types', includes: 'Augmentation de capital' } },
        { id: 'modif.augmentation_parts_nouvelles', label: 'Nombre de parts nouvelles', type: 'number', placeholder: '100', required: false, mono: true,
          showIf: { field: 'modif.types', includes: 'Augmentation de capital' } },
        { id: 'modif.augmentation_banque', label: 'Banque de dépôt des fonds', type: 'text', placeholder: 'Ecobank Guinée SA', required: false,
          showIf: { field: 'modif.types', includes: 'Augmentation de capital' },
          note: "Reporté sur la DNSV avec l'attestation de dépôt." },
        { id: 'modif.augmentation_date_versement', label: 'Date du versement', type: 'date', placeholder: '01/08/2026', required: false,
          showIf: { field: 'modif.types', includes: 'Augmentation de capital' } },
        {
            id: 'modif.souscripteurs', type: 'repeatable', label: 'Souscripteurs', section: 'Augmentation de capital — souscripteurs', clientRole: 'souscripteur',
            min: 1, max: 20, showIf: { field: 'modif.types', includes: 'Augmentation de capital' },
            fields: SOUSCRIPTEUR_SCHEMA,
        },

        // ── 7. Diminution de capital ─────────────────────────────────────────
        // Aucune DNSV n'est requise : rien n'est souscrit ni versé.
        { id: 'modif.diminution_montant', label: 'Montant de la réduction (GNF)', type: 'number', placeholder: '20 000 000', required: true, mono: true, section: 'Diminution de capital',
          showIf: { field: 'modif.types', includes: 'Diminution de capital' } },
        { id: 'modif.diminution_capital_apres', label: 'Capital après réduction (GNF)', type: 'number', placeholder: '30 000 000', required: false, mono: true, readonly: true,
          showIf: { field: 'modif.types', includes: 'Diminution de capital' },
          note: 'Calculé : capital actuel − montant de la réduction.' },
        { id: 'modif.diminution_motif', label: 'Motif de la réduction', type: 'select',
          options: ['Résorption de pertes', 'Remboursement aux associés'], required: true,
          showIf: { field: 'modif.types', includes: 'Diminution de capital' } },
        { id: 'modif.diminution_parts_annulees', label: 'Nombre de parts annulées', type: 'number', placeholder: '40', required: false, mono: true,
          showIf: { field: 'modif.types', includes: 'Diminution de capital' } },

        // ── 7 bis. Répartition du capital après ──────────────────────────────
        // Affichée pour les **trois** opérations qui changent qui détient quoi : une cession, mais
        // aussi une augmentation (parts nouvelles) et une diminution (parts annulées). C'est ce
        // tableau que les statuts mis à jour reprennent — restreinte à la cession, la répartition
        // finale d'une augmentation devait être ressaisie à la main dans le .docx généré.
        //
        // Placée **après** les blocs de capital, et non au milieu de la cession comme à l'origine :
        // on ne renseigne la répartition finale qu'une fois connu de combien le capital varie.
        {
            id: 'modif.repartition_apres', type: 'repeatable', label: 'Répartition du capital après modification', section: 'Répartition du capital après',
            min: 1, max: 20,
            showIf: { field: 'modif.types', includesAny: ['Cession de parts sociales', 'Augmentation de capital', 'Diminution de capital'] },
            fields: [
                { id: 'associe', label: 'Associé', type: 'text', placeholder: 'Ibrahima DIALLO', required: true },
                { id: 'parts_chiffres', label: 'Nombre de parts détenues', type: 'number', placeholder: '60', required: true, mono: true },
                { id: 'pourcentage', label: 'Pourcentage (%)', type: 'number', placeholder: '60', required: false, mono: true, decimals: 2 },
            ],
        },

        // ── 8. Changement de gérant ──────────────────────────────────────────
        // Un seul bloc pour les deux types : les champs sont identiques, seul l'impact
        // statutaire diffère — et c'est TypeModificationStatutaire qui le porte
        // (impacteStatuts() est faux pour le gérant non statutaire).
        //
        // Le gérant sortant est une mention de l'acte, pas un fournisseur de pièces :
        // sans `clientRole`, il n'ouvre pas de checklist. Le gérant entrant, si.
        { id: 'gerant_sortant.prenom_nom', label: 'Gérant sortant', type: 'text', placeholder: 'Ibrahima DIALLO', required: true, section: 'Gérant sortant',
          showIf: { field: 'modif.types', includesAny: ['Changement de gérant statutaire', 'Changement de gérant non statutaire'] } },
        { id: 'gerant_sortant.motif', label: 'Motif de la cessation', type: 'select',
          options: ['Démission', 'Révocation', 'Décès', 'Fin de mandat'], required: true,
          showIf: { field: 'modif.types', includesAny: ['Changement de gérant statutaire', 'Changement de gérant non statutaire'] } },
        { id: 'gerant_sortant.date_cessation', label: 'Date de cessation des fonctions', type: 'date', placeholder: '01/08/2026', required: false,
          showIf: { field: 'modif.types', includesAny: ['Changement de gérant statutaire', 'Changement de gérant non statutaire'] } },

        // Durée du mandat et pouvoirs sont dans ce même bloc : ce sont des données de
        // l'acte, non de l'identité — elles restent donc saisissables quand une fiche
        // client est rattachée au gérant entrant.
        ...GERANT_ENTRANT_FIELDS,

        // ── 9. Objet social ──────────────────────────────────────────────────
        { id: 'modif.objet_operation', label: "Nature du changement d'objet", type: 'select',
          options: ["Ajout d'activités", "Retrait d'activités", "Remplacement complet de l'objet"], required: true, section: 'Objet social',
          showIf: { field: 'modif.types', includes: "Modification de l'objet social" } },
        { id: 'modif.objet_nouveau', label: 'Nouvel objet social', type: 'textarea', placeholder: "Texte complet du nouvel objet social, tel qu'il figurera aux statuts", required: true,
          showIf: { field: 'modif.types', includes: "Modification de l'objet social" },
          note: "L'objet actuel est repris dans la section « Société concernée » ci-dessus." },

        // ── 10. Modification de dénomination ─────────────────────────────────
        { id: 'modif.denomination_nouvelle', label: 'Nouvelle dénomination sociale', type: 'text', placeholder: 'Nouvelle Raison Sociale', required: true, section: 'Modification de dénomination',
          showIf: { field: 'modif.types', includes: 'Modification de dénomination' } },
        { id: 'modif.sigle_nouveau', label: 'Nouveau sigle (facultatif)', type: 'text', placeholder: 'NRS', required: false,
          showIf: { field: 'modif.types', includes: 'Modification de dénomination' } },

        // ── 11. Modification de forme juridique ──────────────────────────────
        { id: 'modif.forme_nouvelle', label: 'Nouvelle forme juridique', type: 'select',
          options: FORMES_SOCIETE, required: true, section: 'Modification de forme juridique',
          showIf: { field: 'modif.types', includes: 'Modification de forme juridique' } },

        // ── 12. Précisions ───────────────────────────────────────────────────
        // Conservé mais rétrogradé : ce champ portait à lui seul toute l'information
        // de la modification, il n'est plus qu'un complément.
        { id: 'objet_modification', label: 'Précisions complémentaires', type: 'textarea', placeholder: 'Éléments de contexte non couverts par les sections ci-dessus', required: false, section: 'Précisions', publicIntake: true },
    ],
};

// ─────────────────────────────────────────────────────────────────────────────
// ─────────────────────────────────────────────────────────────────────────────
// Filtre les champs selon showIf et les valeurs actuelles du formulaire.
// showIf: { field: 'fieldId' }                     → visible si values[fieldId] est truthy
// showIf: { field: 'fieldId', not: true }           → visible si values[fieldId] est falsy
// showIf: { field: 'fieldId', equals: 'v' }         → visible si la valeur vaut exactement 'v'
// showIf: { field: 'fieldId', includes: 'v' }       → visible si le tableau contient 'v'
// showIf: { field: 'fieldId', includesAny: [...] }  → visible si le tableau contient au moins
//                                                     l'une des valeurs
//
// `includes` / `includesAny` servent aux champs multi-choix (type `checkbox_group`), dont la
// valeur est un tableau de libellés : c'est ce qui rend les blocs de la modification de
// société conditionnels au type de modification décidé, sans logique ad hoc dans le rendu.
// ─────────────────────────────────────────────────────────────────────────────
export function getVisibleFields(fields, values) {
    return fields.filter(field => estVisible(field.showIf, values));
}

/**
 * Une condition `showIf` est-elle satisfaite ?
 *
 * Extraite de `getVisibleFields()` pour pouvoir être **récursive** : la forme `{ all: [...] }`
 * conjugue plusieurs conditions. Les champs propres à un motif de représentation en exigent deux
 * — « la personne se fait représenter » ET « le motif est celui-ci ». Sans conjonction, décocher
 * la case laisserait le motif en valeur dans `formValues` et ses champs affichés : la famille de
 * défaut que `purgerChampsInvisibles()` décrit déjà.
 *
 * Volontairement **pas transitive** (un champ visible dont la condition désigne un champ lui-même
 * masqué reste visible). Ce serait sans doute la sémantique juste, et cela réparerait toute la
 * famille d'un coup — mais c'est un changement de comportement sur les 16 questionnaires, à
 * mesurer avant d'adopter, pas à glisser dans cette passe.
 */
function estVisible(showIf, values) {
    if (!showIf) return true;

    if (showIf.all !== undefined) {
        return showIf.all.every(condition => estVisible(condition, values));
    }

    {
        const current = values[showIf.field];

        if (showIf.equals !== undefined) {
            return showIf.not ? current !== showIf.equals : current === showIf.equals;
        }
        if (showIf.includes !== undefined) {
            const present = Array.isArray(current) && current.includes(showIf.includes);
            return showIf.not ? !present : present;
        }
        if (showIf.includesAny !== undefined) {
            const present = Array.isArray(current) && showIf.includesAny.some(v => current.includes(v));
            return showIf.not ? !present : present;
        }

        // Un tableau vide est une absence de choix, pas une valeur : sans ce cas, un
        // `checkbox_group` vidé de ses cases laisserait ses champs dépendants affichés
        // ([] étant truthy en JavaScript).
        if (Array.isArray(current)) {
            return showIf.not ? current.length === 0 : current.length > 0;
        }
        return showIf.not ? !current : !!current;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Complément de getVisibleFields() : retire des valeurs celles des champs que
// `showIf` masque actuellement.
//
// Décocher une modification n'effaçait pas ses champs. Cocher « Cession de parts »,
// saisir 25 000 000 en valeur des parts cédées, puis décocher : la valeur restait dans
// `donnees`, partait au serveur, et `FacturationService::deduireAssiette()` la retenait
// **en priorité** — la facture portait donc une assiette de 25 000 000 sur un dossier
// sans cession. Le même travers existait ailleurs sans être traité : les champs `ger.*`
// d'une SARLU restaient soumis après avoir décoché « le gérant est une personne
// différente de l'associé unique ».
//
// ⚠️ À appliquer **à la soumission uniquement**, jamais en cours de saisie ni sur un
// brouillon : un brouillon doit conserver une saisie mise de côté, pour qu'en recochant
// la case l'utilisateur retrouve ses valeurs.
//
// Les champs absents du schéma sont conservés : `donnees` peut porter des clés dérivées
// (projection d'une fiche client ou société) qui ne correspondent à aucun champ déclaré.
// ─────────────────────────────────────────────────────────────────────────────
export function purgerChampsInvisibles(fields, values) {
    const declares = new Set(fields.map(f => f.id));
    const visibles = new Set(getVisibleFields(fields, values).map(f => f.id));

    return Object.fromEntries(
        Object.entries(values).filter(([cle]) => !declares.has(cle) || visibles.has(cle)),
    );
}

// Lien TypeActe.code (BD) → clé QUESTIONNAIRES (frontend)
// ─────────────────────────────────────────────────────────────────────────────
export const TYPE_ACTE_CODE_MAP = {
    // Société
    'SOC-SARLU': 'creation_sarlu',
    'SOC-SARL': 'creation_sarl',
    'SOC-SA': 'creation_sa',
    'SOC-SAS': 'creation_sas',
    'SOC-SASU': 'creation_sasu',
    'SOC-SNC': 'creation_snc',
    'SOC-GIE': 'creation_gie',
    'SOC-DIS': 'dissolution',
    // Vente
    'VTE-IMM': 'vente_immeuble',
    'VTE-SAN': 'vente_sans_titre',
    // Bail
    'BAI-HAB': 'bail_habitation',
    'BAI-COM': 'bail_commercial',
    'BAI-CON': 'bail_construction',
    // Hypothèque
    'HYP-CON': 'hypotheque_conv',
    'HYP-MAI': 'mainlevee',
    // Modification
    'SOC-MOD': 'modification',
};

// ─────────────────────────────────────────────────────────────────────────────
// Triplets géographiques — ville → commune → quartier
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Les triplets ville/commune/quartier des questionnaires, déclarés **une fois**.
 *
 * Le référentiel de lieux (2026-08-12) rend ces trois champs dépendants : une commune n'existe que
 * sous sa ville, un quartier que sous sa commune. Plutôt que de retyper les **48 déclarations de
 * champ** concernées — et de risquer d'en oublier —, la relation est décrite ici et les rendus la
 * consultent. Les identifiants de champ ne changent pas : ni `questionnaires.donnees`, ni les
 * balises `${soc.siege_quartier}` des modèles Word n'ont à bouger.
 *
 * Le préfixe varie (`soc.siege_`, `pp.`, `bq.siege_`, `modif.siege_nouveau_`, ou rien du tout dans
 * les blocs répétables) et le nom du champ ville n'est pas régulier (`siege_ville` ici,
 * `demeurant_ville` là) : une convention de nommage ne suffisait pas, cette table est explicite.
 */
export const TRIPLETS_GEO = [
    { ville: 'soc.siege_ville',              commune: 'soc.siege_commune',              quartier: 'soc.siege_quartier' },
    { ville: 'bq.siege_ville',               commune: 'bq.siege_commune',               quartier: 'bq.siege_quartier' },
    { ville: 'modif.siege_nouveau_ville',    commune: 'modif.siege_nouveau_commune',    quartier: 'modif.siege_nouveau_quartier' },
    { ville: 'pp.demeurant_ville',           commune: 'pp.commune',                     quartier: 'pp.quartier' },
    { ville: 'ger.demeurant_ville',          commune: 'ger.commune',                    quartier: 'ger.quartier' },
    { ville: 'acq.demeurant_ville',          commune: 'acq.commune',                    quartier: 'acq.quartier' },
    // Bloc **dérivé** de GER_FIELDS par substitution de préfixe (`ger.` → `gerant_entrant.`) : ses
    // champs géo existaient donc sans figurer ici, et n'avaient aucune cascade — le clerc y saisissait
    // une commune en texte libre pendant que le bloc « gérant » d'à côté proposait le référentiel.
    // Un préfixe dérivé se déclare ici comme les autres : la dérivation ne l'inscrit pas d'elle-même.
    { ville: 'gerant_entrant.demeurant_ville', commune: 'gerant_entrant.commune',        quartier: 'gerant_entrant.quartier' },
    // Même cas, même piège : `liquidateur.*` est dérivé de GER_FIELDS par substitution de
    // préfixe, donc ses champs géo existent sans figurer ici. Sans cette ligne, le liquidateur
    // serait le seul dirigeant du projet à saisir sa commune en texte libre.
    { ville: 'liquidateur.demeurant_ville',  commune: 'liquidateur.commune',            quartier: 'liquidateur.quartier' },
    // Siège **de la liquidation**, distinct du siège social de la société dissoute.
    { ville: 'dissolution.siege_ville',      commune: 'dissolution.siege_commune',      quartier: 'dissolution.siege_quartier' },
    // Blocs répétables (associés, gérants) : les champs y sont nommés sans préfixe.
    { ville: 'demeurant_ville',              commune: 'commune',                        quartier: 'quartier' },
    // Sous-espace du représentant. Écrit en clair, comme PAIRES_DATES et pour la même raison :
    // ces tables sont relues par regex, ligne par ligne, par les tests de parité. L'oubli — le
    // défaut qu'a connu `gerant_entrant.*` — est fermé par
    // `test_tout_prefixe_de_representation_a_ses_controles_de_dates_et_sa_cascade_geo`.
    { ville: 'pp.repr_demeurant_ville',      commune: 'pp.repr_commune',                quartier: 'pp.repr_quartier' },
    { ville: 'ger.repr_demeurant_ville',     commune: 'ger.repr_commune',               quartier: 'ger.repr_quartier' },
    { ville: 'acq.repr_demeurant_ville',     commune: 'acq.repr_commune',               quartier: 'acq.repr_quartier' },
    { ville: 'loc.repr_demeurant_ville',     commune: 'loc.repr_commune',               quartier: 'loc.repr_quartier' },
    { ville: 'repr_demeurant_ville',         commune: 'repr_commune',                   quartier: 'repr_quartier' },
];

/**
 * Rôle géographique d'un champ — `null` s'il n'en a pas.
 *
 * ⚠️ `bien.livre_foncier_ville` finit par « ville » sans être un lieu du référentiel : c'est la
 * ville du livre foncier, une mention cadastrale. Ne pas se fier au suffixe est précisément la
 * raison d'être de `TRIPLETS_GEO`.
 *
 * @returns {{niveau: string, parentField: string|null}|null}
 */
/**
 * Groupes de dates dont la cohérence se contrôle ensemble — naissance, délivrance et expiration
 * d'une même personne.
 *
 * Même parti que `TRIPLETS_GEO` juste au-dessus, et pour la même raison : les contrôles existaient
 * dans `ModalNouveauClient` sur la fiche client, et **nulle part** sur les 25 champs de date des
 * questionnaires. Une déclaration, pas une règle recopiée par champ.
 *
 * ⚠️ `gerant_entrant.*` est un bloc **dérivé** de `GER_FIELDS` par substitution de préfixe : ses
 * dates existent sans être écrites nulle part en clair. Elles se déclarent donc ici comme les
 * autres — la dérivation ne les inscrit pas d'elle-même.
 */
export const PAIRES_DATES = [
    { naissance: 'pp.date_naissance',              delivree: 'pp.piece_delivree_le',              expire: 'pp.piece_expire_le' },
    { naissance: 'ger.date_naissance',             delivree: 'ger.piece_delivree_le',             expire: 'ger.piece_expire_le' },
    { naissance: 'acq.date_naissance',             delivree: 'acq.piece_delivree_le',             expire: 'acq.piece_expire_le' },
    { naissance: 'gerant_entrant.date_naissance',  delivree: 'gerant_entrant.piece_delivree_le',  expire: 'gerant_entrant.piece_expire_le' },
    // Bloc dérivé lui aussi (voir LIQUIDATEUR_FIELDS) : ses dates n'existent en clair nulle part.
    { naissance: 'liquidateur.date_naissance',     delivree: 'liquidateur.piece_delivree_le',     expire: 'liquidateur.piece_expire_le' },
    // Blocs répétables (associés, gérants, souscripteurs) : champs nommés sans préfixe.
    { naissance: 'date_naissance',                 delivree: 'piece_delivree_le',                 expire: 'piece_expire_le' },
    // Le président d'une SASU n'a qu'une date de naissance déclarée.
    { naissance: 'soc.president_date_naissance',   delivree: null,                                expire: null },
    // Sous-espace du représentant : sa pièce d'identité mérite les mêmes contrôles de cohérence
    // que celle des parties — c'est elle qui l'identifie au moment où il signe.
    //
    // ⚠️ Écrit en clair et non généré depuis PREFIXES_REPRESENTATION, bien que ce soit la même
    // liste : `CoherenceDonneesTest` extrait cette table **ligne par ligne, par regex**, pour la
    // confronter à son miroir PHP (`CoherenceDonneesService::GROUPES_DATES`). Une expression
    // générée y serait illisible, et la parité — qui est le vrai garde-fou — cesserait d'être
    // vérifiée. Un test dédié affirme que ces préfixes sont bien ceux de PREFIXES_REPRESENTATION.
    { naissance: 'pp.repr_date_naissance',   delivree: 'pp.repr_piece_delivree_le',   expire: 'pp.repr_piece_expire_le' },
    { naissance: 'ger.repr_date_naissance',  delivree: 'ger.repr_piece_delivree_le',  expire: 'ger.repr_piece_expire_le' },
    { naissance: 'acq.repr_date_naissance',  delivree: 'acq.repr_piece_delivree_le',  expire: 'acq.repr_piece_expire_le' },
    { naissance: 'loc.repr_date_naissance',  delivree: 'loc.repr_piece_delivree_le',  expire: 'loc.repr_piece_expire_le' },
    { naissance: 'repr_date_naissance',      delivree: 'repr_piece_delivree_le',      expire: 'repr_piece_expire_le' },
];

/**
 * Dates isolées dont on peut affirmer une borne, **et elles seules**.
 *
 * ⚠️ Ce qui n'y figure pas est délibéré, pas oublié : `bail.date_prise_effet` et
 * `modif.augmentation_date_versement` sont légitimement futures ; `dissolution.date_assemblee`,
 * `hypotheque.date_acte`, `modif.date_cession` et `gerant_sortant.date_cessation` restent libres
 * parce que je ne sais pas quelle borne leur donner — même doctrine que pour les régimes
 * matrimoniaux : une contrainte inventée est pire qu'une contrainte absente. Une ligne suffira le
 * jour où l'étude les arbitrera.
 */
export const CONTRAINTES_DATES = [
    {
        champ: 'soc.date_constitution',
        genre: 'passee',
        message: 'La date de constitution ne peut pas être dans le futur.',
    },
    {
        champ: 'ag.date_effet',
        genre: 'ordre',
        apres: 'ag.date',
        message: "La date d'effet ne peut pas précéder l'assemblée qui l'a décidée.",
    },
];

export function roleGeo(fieldId) {
    for (const triplet of TRIPLETS_GEO) {
        if (fieldId === triplet.ville)    return { niveau: 'ville',    parentField: null };
        if (fieldId === triplet.commune)  return { niveau: 'commune',  parentField: triplet.ville };
        if (fieldId === triplet.quartier) return { niveau: 'quartier', parentField: triplet.commune };
    }

    return null;
}

/**
 * Valeurs à écrire quand un champ géographique change : la valeur elle-même, **et le vidage de ses
 * descendants**.
 *
 * Sans ce vidage, changer la ville laisse une commune orpheline — « Ratoma » sous « Kindia » —
 * c'est-à-dire exactement l'incohérence que la cascade est là pour supprimer.
 *
 * @returns {Object} patch à fusionner dans les valeurs du formulaire
 */
export function patchGeo(fieldId, valeur) {
    const triplet = TRIPLETS_GEO.find(t => [t.ville, t.commune, t.quartier].includes(fieldId));

    if (!triplet) return { [fieldId]: valeur };

    if (fieldId === triplet.ville) {
        return { [triplet.ville]: valeur, [triplet.commune]: '', [triplet.quartier]: '' };
    }

    if (fieldId === triplet.commune) {
        return { [triplet.commune]: valeur, [triplet.quartier]: '' };
    }

    return { [triplet.quartier]: valeur };
}
