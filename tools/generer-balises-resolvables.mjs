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
const SOURCE = path.join(RACINE, 'resources/js/data/questionnaires.js');
const SORTIE = path.join(RACINE, 'docs/kit-modeles/balises-resolvables.txt');

// questionnaires.js importe `@/lib/dates` (alias Vite, inconnu de Node) : on neutralise
// l'import le temps de l'évaluation. Seule la liste des champs nous intéresse.
const temp = path.join(RACINE, 'node_modules/.cache-balises.mjs');
fs.mkdirSync(path.dirname(temp), { recursive: true });
fs.writeFileSync(temp, fs.readFileSync(SOURCE, 'utf8').replace(
    /^import \{ anneePremierExercice \} from '@\/lib\/dates';$/m,
    'const anneePremierExercice = () => new Date().getFullYear();',
));

const { QUESTIONNAIRES } = await import(pathToFileURL(temp).href);
fs.unlinkSync(temp);

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
]) balises.add(b);

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

for (const champs of Object.values(QUESTIONNAIRES)) {
    for (const f of champs) {
        if (f.type === 'repeatable') {
            balises.add(f.id);
            for (const s of f.fields ?? []) ajouter(`${f.id}.${s.id}`, s.type);
        } else {
            ajouter(f.id, f.type);
        }
    }
}

fs.writeFileSync(SORTIE, [...balises].sort().join('\n') + '\n');
console.log(`${balises.size} balises résolvables → ${path.relative(RACINE, SORTIE)}`);
