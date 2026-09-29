// Produit docs/kit-modeles/balises-resolvables.txt — la liste EXHAUSTIVE des balises
// que `ActesGeneratorService` sait remplir, dérivée de la source de vérité
// `resources/js/data/questionnaires.js`.
//
// Pourquoi un fichier généré plutôt qu'une liste tenue à la main : la liste à la main
// a déjà divergé — le dictionnaire de balises cite 83 balises sans champ réel, et trois
// modèles en service portent des balises qui ne se rempliront jamais.
//
//   node tools/generer-balises-resolvables.mjs
//
// `tools/verifier-balises.php` refuse de tourner si ce fichier est plus vieux que
// questionnaires.js — une liste périmée serait pire qu'aucune liste.

import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const RACINE = path.resolve(import.meta.dirname, '..');
const SORTIE = path.join(RACINE, 'docs/kit-modeles/balises-resolvables.txt');

// questionnaires.js importe `@/lib/dates` (alias Vite, inconnu de Node) : on neutralise
// l'import le temps de l'évaluation. Seule la liste des champs nous intéresse.
// Node ne connaît pas l'alias `@/` de Vite : les modules sont recopiés dans un répertoire
// temporaire, l'alias réécrit en chemin relatif. Seules leurs données nous intéressent.
const tempDir = path.join(RACINE, 'node_modules/.cache-balises');
fs.rmSync(tempDir, { recursive: true, force: true });
fs.mkdirSync(tempDir, { recursive: true });

const copier = (relatif, transformer = (t) => t) => {
    const cible = path.join(tempDir, path.basename(relatif));
    const source = fs.readFileSync(path.join(RACINE, relatif), 'utf8');
    fs.writeFileSync(cible, transformer(source).replace(/'@\/lib\/([a-zA-Z]+)'/g, "'./$1.js'"));

    return pathToFileURL(cible).href;
};

copier('resources/js/lib/dates.js');
const urlChampsClient = copier('resources/js/lib/clientFields.js');
const urlQuestionnaires = copier('resources/js/data/questionnaires.js', (t) => t.replace(
    /^import \{ anneePremierExercice \} from '@\/lib\/dates';$/m,
    'const anneePremierExercice = () => new Date().getFullYear();',
));

const { QUESTIONNAIRES } = await import(urlQuestionnaires);

// Miroir JS assumé de ClientProjectionService::SUFFIXES_* — voir clientFields.js, dont le
// contrat est verrouillé côté serveur par ClientProjectionTest.
const { suffixesIdentite } = await import(urlChampsClient);

fs.rmSync(tempDir, { recursive: true, force: true });

const balises = new Set();

// Injectées par ActesGeneratorService quel que soit l'acte — miroir de
// remplirConstantesOffice() et remplirInfosDossier().
for (const b of [
    'office.notaire', 'office.titre', 'office.charge', 'office.residence', 'office.adresse',
    'office.bp', 'office.commune', 'office.ville', 'office.telephones', 'office.email',
    'dossier.reference', 'dossier.objet', 'acte.numero', 'acte.reference',
    'acte.nb_pages', 'acte.nb_pages_lettres',
    'date_acte_jma', 'annee_lettres', 'date_acte_lettres', 'date_acte_lettres_complete',
    // Dérivées par remplirQuestionnaire() hors du parcours des champs.
    'bail.date_fin',
    'pp.adresse', 'ger.adresse', 'acq.adresse', 'loc.adresse', 'liquidateur.adresse',
    // Dissolution : mentions dérivées par remplirMentionsDissolution(), donc absentes du
    // schéma. `soc.mention_liquidation` vient du **statut de la fiche** (les deux actes de
    // l'étude écrivaient deux formulations différentes) ; la durée de l'article 5 se calcule
    // depuis la constitution et la date d'effet (celle du PV réel était fausse de vingt jours).
    'soc.mention_liquidation', 'soc.duree_lettres',
    'dissolution.duree_reduite', 'dissolution.date_expiration',
]) balises.add(b);

// Données délivrées par les organismes au retour d'une formalité — miroir assumé de
// `App\Enums\DonneeAuRetour`. Elles ne viennent d'aucun schéma de questionnaire : c'est le
// formaliste qui les saisit à la réception, et `ActesGeneratorService::remplirDonneesAuRetour()`
// les injecte.
//
// ⚠️ Duplication PHP/JS **gardée** : `DonneesAuRetourTest` confronte cette liste à l'enum. Sans
// elle, `verifier-balises.php` déclarerait non résolvable une balise que le moteur remplit — et
// l'étude en conclurait qu'elle n'existe pas.
//
// ⚠️ Ces balises sont **vides dans un acte produit avant le retour** : les actes sortent à
// l'Édition, la formalité revient deux étapes plus tard. Elles servent aux actes régénérés.
for (const d of [
    'rccm_numero', 'nif', 'jal_journal', 'depot_greffe_numero',
    'quittance_numero', 'declaration_modificative_numero',
]) balises.add(`retour.${d}`);

// Les deux données de type date, dont le moteur dérive `_jma` et `_lettres`. Les variantes
// sont écrites en clair plutôt que par `ajouter()` : cette fonction est déclarée **plus bas**,
// et l'appeler ici la placerait dans sa zone morte temporelle — le défaut que ce dépôt a déjà
// rencontré trois fois, et que `vite build` ne rattrape pas.
for (const d of ['rccm_date', 'jal_date_parution']) {
    balises.add(`retour.${d}`);
    balises.add(`retour.${d}_jma`);
    balises.add(`retour.${d}_lettres`);
}

// Variantes automatiques — mêmes règles que remplirQuestionnaire() : `_jma`/`_lettres`
// pour une date, `_lettres`/`_formate` pour un nom finissant par `_chiffres`. Rien d'autre.
const ajouter = (id, type) => {
    balises.add(id);
    if (type === 'date') { balises.add(`${id}_jma`); balises.add(`${id}_lettres`); }
    if (id.endsWith('_chiffres')) {
        balises.add(id.replace('_chiffres', '_lettres'));
        balises.add(id.replace('_chiffres', '_formate'));
    }
};

// Emplacements qui portent une personne : le préfixe d'une section scalaire (`pp`), ou la clé
// d'un bloc répétable (`associes`). C'est là que la projection écrit le sous-espace du
// représentant et la mention de comparution.
const emplacements = new Set();

for (const champs of Object.values(QUESTIONNAIRES)) {
    let prefixeSection = null;

    for (const f of champs) {
        if (f.type === 'repeatable') {
            balises.add(f.id);
            for (const s of f.fields ?? []) ajouter(`${f.id}.${s.id}`, s.type);
            if (f.clientRole) emplacements.add(f.id);
            continue;
        }

        // `clientRole` est porté par le premier champ de la section : son préfixe est celui de
        // toute la section.
        if (f.section) prefixeSection = f.id.includes('.') ? f.id.split('.')[0] : null;
        if (f.clientRole && prefixeSection) emplacements.add(prefixeSection);

        ajouter(f.id, f.type);
    }
}

// Sous-espace du représentant et mention de comparution — écrits par
// ClientProjectionService::valeursRepresentation(), donc **absents du schéma** pour la plupart.
// La projection re-préfixe TOUTE l'identité d'une fiche (`repr_nom`, `repr_adresse`,
// `repr_identite_notariale`…), là où le questionnaire n'en déclare qu'une vingtaine en saisie.
// Sans ces lignes, `verifier-balises.php` dirait non résolvable un modèle qui les emploie, et
// l'étude en conclurait qu'elles n'existent pas.
const SUFFIXES_MANDAT = [
    'repr_motif', 'repr_qualite',
    'repr_titre_forme', 'repr_titre_date', 'repr_titre_autorite', 'repr_titre_reference',
];

for (const emplacement of emplacements) {
    balises.add(`${emplacement}.comparution`);

    for (const suffixe of [...suffixesIdentite().map(s => `repr_${s}`), ...SUFFIXES_MANDAT]) {
        // `repr_titre_date` est une date au format JJ/MM/AAAA dans `donnees` : le moteur en
        // dérive `_jma` et `_lettres` comme pour n'importe quelle autre.
        ajouter(`${emplacement}.${suffixe}`, suffixe === 'repr_titre_date' ? 'date' : 'text');
    }
}

fs.writeFileSync(SORTIE, [...balises].sort().join('\n') + '\n');
console.log(`${balises.size} balises résolvables → ${path.relative(RACINE, SORTIE)}`);
