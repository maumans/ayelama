/**
 * Deux contrôles sur la structure des questionnaires, que seule l'évaluation du schéma révèle.
 *
 *   node tools/verifier-sections-questionnaire.mjs
 *
 *   A. Chaque carte de personne ne contient que les champs de cette personne.
 *   B. Un champ d'identité masqué par le rattachement d'une fiche est bien **rempli** par elle.
 *   C. La nature déclarée à l'écran part bien au serveur, pour **toute** section liée à un rôle.
 *   D. Rattacher une fiche remplit la section **entière**, pas seulement sa partie visible.
 *   E. Le rattachement d'office ne se déclenche que sur un candidat **unique** et rattachable.
 *
 * **Le défaut que ce contrôle attrape.** `groupFieldsBySection()` ouvre une carte au **premier**
 * champ portant `section`. Un champ déclaré avant ce marqueur tombe donc dans la carte
 * **précédente** — sans erreur, sans avertissement, avec une mise en page qui a l'air normale.
 *
 * C'est arrivé : `personneBimodale()` retournait `{prefixe}.type_personne` avant la civilité, qui
 * portait `section`. Le select « Nature » du requérant d'une dissolution s'affichait donc sous
 * « Décision de dissolution », et celui du liquidateur dans la carte du requérant. Personne ne
 * l'a vu pendant deux semaines. Et en corrigeant, une seconde variante du même défaut est
 * apparue aussitôt : `GER_FIELDS[0]` porte `section: 'Gérant'`, que le spread recopiait, si bien
 * que la civilité rouvrait une carte « Gérant » au milieu de la personne.
 *
 * `vite build` ne voit rien de tout cela — c'est un objet de données parfaitement valide. Seule
 * l'évaluation du schéma le montre, d'où ce script plutôt qu'un test PHPUnit : la règle porte sur
 * une structure JavaScript qu'il faut exécuter pour connaître.
 *
 * La règle, vérifiée sur les 12 questionnaires au 2026-09-30 (0 violation) : dans une carte liée
 * à un rôle client, **tous les champs partagent le préfixe de la carte**. Un champ d'un autre
 * préfixe y décrirait une autre personne que celle dont la carte porte le nom — et le masquage
 * qui suit le rattachement d'une fiche se tromperait de cible.
 */
import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const RACINE = path.resolve(import.meta.dirname, '..');

// Node ne connaît pas l'alias `@/` de Vite : mêmes recopie et réécriture que
// generer-balises-resolvables.mjs, dont ce script suit la technique.
const tmp = path.join(RACINE, 'node_modules/.cache-sections');
fs.rmSync(tmp, { recursive: true, force: true });
fs.mkdirSync(tmp, { recursive: true });

const copier = (relatif, transformer = (t) => t) => {
    const cible = path.join(tmp, path.basename(relatif));
    const source = fs.readFileSync(path.join(RACINE, relatif), 'utf8');
    fs.writeFileSync(
        cible,
        transformer(source)
            .replace(/'@\/lib\/([a-zA-Z]+)'/g, "'./$1.js'")
            .replace(/'@\/data\/([a-zA-Z]+)'/g, "'./$1.js'"),
    );

    return pathToFileURL(cible).href;
};

copier('resources/js/lib/dates.js');
copier('resources/js/lib/piecesRequises.js');
const urlChampsClient = copier('resources/js/lib/clientFields.js');

const urlQuestionnaires = copier('resources/js/data/questionnaires.js', (t) => t.replace(
    /^import \{ anneePremierExercice \} from '@\/lib\/dates';$/m,
    'const anneePremierExercice = () => new Date().getFullYear();',
));
const urlParties = copier('resources/js/lib/partiesPayload.js');

const { QUESTIONNAIRES } = await import(urlQuestionnaires);
const { groupFieldsBySection, buildPartiesPayload, rolesARattacherDoffice } = await import(urlParties);
const { suffixesIdentite, mapClientToPrefixedFields, mapClientToRepeatableItem } = await import(urlChampsClient);

fs.rmSync(tmp, { recursive: true, force: true });

const prefixeDe = (id) => id.split('.')[0];
const anomalies = [];
let cartes = 0;

for (const [nom, champs] of Object.entries(QUESTIONNAIRES)) {
    for (const groupe of groupFieldsBySection(champs)) {
        if (!groupe.clientRole) continue;

        cartes++;
        const attendu = prefixeDe(groupe.fields[0].id);
        const etrangers = groupe.fields
            .map((f) => f.id)
            .filter((id) => prefixeDe(id) !== attendu);

        if (etrangers.length > 0) {
            anomalies.push(
                `  ${nom} → « ${groupe.name} » (rôle ${groupe.clientRole}, préfixe ${attendu})\n` +
                `      champ(s) d'une autre personne : ${etrangers.join(', ')}`,
            );
        }
    }
}

if (anomalies.length > 0) {
    console.error(
        `${anomalies.length} carte(s) mélangent des personnes :\n\n${anomalies.join('\n')}\n\n` +
        'Le champ fautif est probablement déclaré **avant** celui qui porte `section`, ou hérite\n' +
        "d'un `section` par un spread. Voir personneBimodale() dans questionnaires.js.",
    );
    process.exit(1);
}

console.log(`A. ${cartes} carte(s) de personne — chacune ne contient que ses propres champs.`);

// ── B. Un champ masqué doit être un champ rempli ────────────────────────────────────────
//
// `estChampIdentite()` fait disparaître de l'écran tout champ dont le suffixe figure dans
// `SUFFIXES_IDENTITE`, dès qu'une fiche client est rattachée : l'identité vient de la fiche.
// Mais rien ne garantissait que `mapClientToPrefixedFields()` **produise** ce suffixe. Un
// suffixe déclaré sans être projeté donne un champ masqué **et vide** — et s'il est `required`,
// la carte affiche « Fiche incomplète pour cet acte » sur une fiche entièrement remplie, sans
// aucun moyen de la compléter puisque le champ n'est plus à l'écran.
//
// C'est arrivé avec `type_personne` : posé par `buildPartieFields()` (le payload) et par
// `ClientProjectionService` (le serveur), mais **pas** par la projection du formulaire. Le
// dossier enregistré était correct ; seul l'écran mentait. Et le défaut est resté invisible
// tant que le champ était rangé dans la carte voisine, hors du périmètre de
// `champsIdentiteManquants()`.

// Une fiche fictive entièrement remplie : la projection ne rend que ce qu'elle sait faire.
const ficheTest = (type) => ({
    id: 1, type,
    civilite: 'M.', prenom_nom: 'Amadou Diallo', nom_famille: 'Diallo', prenoms: 'Amadou',
    ne_a: 'Conakry', date_naissance: '1980-01-01', nationalite: 'Guinéenne',
    situation_matrimoniale: 'Marié(e)', regime_matrimonial: 'Séparation de biens',
    piece_type: 'CNI CEDEAO', piece_numero: 'GN001', piece_delivree_le: '2020-01-01',
    piece_delivree_a: 'Conakry', piece_expire_le: '2030-01-01',
    denomination: 'Société Témoin', forme: 'SARL', rccm: 'RC-0000001',
    representant_legal: 'Amadou Diallo', representant_qualite: 'Gérant',
    siege: 'Kaporo', quartier: 'Kaporo', commune: 'Ratoma', demeurant_ville: 'Conakry',
    pays: 'République de Guinée', telephone: '624 00 00 00', email: 'temoin@example.gn',
});

const suffixes = suffixesIdentite();
const muets = [];
let verifies = 0;

for (const [nom, champs] of Object.entries(QUESTIONNAIRES)) {
    for (const groupe of groupFieldsBySection(champs)) {
        if (!groupe.clientRole) continue;

        const ids = groupe.fields.map((f) => f.id);
        // Les deux natures : un champ réservé aux personnes physiques (`ne_a`) n'a pas à être
        // projeté pour une société. Seul un champ muet pour **les deux** est un trou.
        const projete = {
            ...mapClientToPrefixedFields(ficheTest('physique'), groupe.fields[0].id.split('.')[0], ids),
            ...mapClientToPrefixedFields(ficheTest('morale'), groupe.fields[0].id.split('.')[0], ids),
        };

        for (const champ of groupe.fields) {
            // ⚠️ **Descendre dans les blocs répétables.** `groupFieldsBySection()` rend le bloc
            // comme **un seul** champ de type `repeatable` : sans ce détour, six rôles sur huit
            // — associés, cédants, cessionnaires, souscripteurs, actionnaires, membres —
            // échappaient au contrôle. Leurs items sont projetés par une autre fonction,
            // `mapClientToRepeatableItem()`, et masqués par `RepeatableGroup.jsx` : c'est la
            // même règle, sur un second chemin, donc le même risque.
            if (champ.type === 'repeatable') {
                const idsItem = (champ.fields ?? []).map((f) => f.id);
                const projeteItem = {
                    ...mapClientToRepeatableItem(ficheTest('physique'), idsItem),
                    ...mapClientToRepeatableItem(ficheTest('morale'), idsItem),
                };

                for (const sous of champ.fields ?? []) {
                    if (!sous.required || !suffixes.includes(sous.id)) continue;

                    verifies++;
                    if (!(sous.id in projeteItem)) {
                        muets.push(
                            `  ${nom} → « ${groupe.name} » → ${champ.id}[] : ${sous.id} (« ${sous.label} »)`,
                        );
                    }
                }
                continue;
            }

            if (!champ.required) continue;

            const suffixe = champ.id.split('.').slice(1).join('.');
            if (!suffixes.includes(suffixe)) continue;

            verifies++;
            if (!(champ.id in projete)) {
                muets.push(
                    `  ${nom} → « ${groupe.name} » : ${champ.id} (« ${champ.label} »)`,
                );
            }
        }
    }
}

if (muets.length > 0) {
    console.error(
        `\n${muets.length} champ(s) requis sont masqués par le rattachement d'une fiche sans être\n` +
        `remplis par elle — la carte dira « Fiche incomplète » sans offrir de quoi compléter :\n\n` +
        `${muets.join('\n')}\n\n` +
        'Ajoutez le suffixe à `mapClientToPrefixedFields()` (resources/js/lib/clientFields.js),\n' +
        'ou retirez-le de SUFFIXES_IDENTITE si la fiche ne le porte réellement pas.',
    );
    process.exit(1);
}

console.log(`B. ${verifies} champ(s) requis masqués par une fiche — tous renseignés par elle.`);

// ── C. Ce qui est coché à l'écran doit partir au serveur ───────────────────────────────
//
// `buildPartiesPayload()` a **deux branches** — sections scalaires et blocs répétables — et
// elles ont divergé sans bruit : la seconde envoyait `type_personne`, la première l'oubliait.
// Mesuré le 2026-09-30 avant correction : **39 parties sur 41 avaient la colonne à NULL**.
// Rien ne le signalait, parce que le serveur a un repli — `Partie::piecesRequisesPour()` traite
// une valeur absente comme « physique ». L'écran affichait donc le jeu de pièces d'une société,
// le serveur en exigeait un autre, et les deux se croyaient d'accord.
//
// Le contrôle exécute la vraie fonction : un test PHP ne l'aurait pas vu, puisqu'il poste
// directement ce que le frontend est censé envoyer.

const cochee = 'Personne morale';
const manquantes = [];
let payloads = 0;

for (const [nom, champs] of Object.entries(QUESTIONNAIRES)) {
    for (const groupe of groupFieldsBySection(champs)) {
        if (!groupe.clientRole) continue;

        const premier = groupe.fields[0];
        const repetable = premier.type === 'repeatable';
        const prefixe = repetable ? null : premier.id.split('.')[0];

        // Seules les sections qui **declarent** une nature sont concernees. Un gerant, un
        // commissaire, un bailleur n'ont pas ce champ : leur partie vaut « physique » par
        // repli, et c'est correct. L'invariant porte sur ce que le formulaire permet de dire,
        // pas sur ce qu'il pourrait dire — l'absence du champ la ou une societe est plausible
        // (la banque creanciere, par exemple) est une autre question, consignee au devbook.
        const declareLaNature = repetable
            ? (premier.fields ?? []).some((f) => f.id === 'type_personne')
            : groupe.fields.some((f) => f.id === `${prefixe}.type_personne`);
        if (!declareLaNature) continue;

        // Une valeur pour chaque champ requis, plus la nature : `buildPartiesPayload` ignore
        // une section dont le nom est vide, et on veut le payload, pas le filtre.
        const valeurs = {};
        const remplir = (liste, prefixer) => {
            for (const f of liste) {
                const id = prefixer ? `${prefixe}.${f.id.split('.').slice(1).join('.')}` : f.id;
                valeurs[id] = f.id.endsWith('type_personne') ? cochee : 'X';
            }
        };

        if (repetable) {
            const item = {};
            for (const f of premier.fields ?? []) item[f.id] = f.id === 'type_personne' ? cochee : 'X';
            valeurs[premier.id] = [item];
        } else {
            remplir(groupe.fields, false);
        }

        const parties = buildPartiesPayload(champs, valeurs, {});
        const partie = parties.find((p) => p.role === groupe.clientRole);
        if (!partie) continue;

        payloads++;
        if (partie.type_personne !== 'morale') {
            manquantes.push(
                `  ${nom} → « ${groupe.name} » (${groupe.clientRole}, ${repetable ? 'répétable' : 'scalaire'}) : ` +
                `type_personne = ${JSON.stringify(partie.type_personne)}`,
            );
        }
    }
}

if (manquantes.length > 0) {
    console.error(
        `\n${manquantes.length} section(s) n'envoient pas la nature déclarée au serveur :\n\n` +
        `${manquantes.join('\n')}\n\n` +
        "La partie sera traitée en personne physique et le jeu de pièces exigé ne sera pas celui\n" +
        "affiché. Voir les deux branches de buildPartiesPayload() dans lib/partiesPayload.js.",
    );
    process.exit(1);
}

console.log(`C. ${payloads} section(s) liée(s) à un rôle — toutes transmettent la nature déclarée.`);

// ── D. Rattacher une fiche remplit la section entière ──────────────────────────────────
//
// `applyClientToSection()` projetait la fiche sur `group.fields`, c'est-à-dire sur le groupe
// **rendu** — donc filtré par `getVisibleFields()`. Dans un bloc bimodal, l'état civil est
// conditionné à `type_personne` : tant que la nature est inconnue, ces champs sont invisibles,
// donc absents de la liste. Or c'est le rattachement lui-même qui pose la nature. Les huit
// champs apparaissaient **juste après**, vides — et masqués, puisque ce sont des champs
// d'identité. Vérifié sur la dissolution : 10 clés projetées au lieu de 17.
//
// Le contrôle porte sur la **source**, pas sur le comportement : le défaut est une liste mal
// choisie à l'appel, et c'est exactement ce qu'on relit mal. Même technique que
// `PiecesRequisesParRoleTest`, qui lit le JS depuis PHP.

const APPELANTS = [
    'resources/js/Pages/Dossiers/Create.jsx',
    'resources/js/Pages/Dossiers/Show.jsx',
];

const filtrees = [];
for (const chemin of APPELANTS) {
    const code = fs.readFileSync(path.join(RACINE, chemin), 'utf8')
        .replace(/(\/\/[^\n]*|\/\*[\s\S]*?\*\/)/g, '');

    // Chaque appel à la projection, avec la ligne qui a construit sa liste d'ids juste avant.
    const blocs = code.match(/const fieldIds = [^;]+;[\s\S]{0,200}?mapClientToPrefixedFields\(/g) ?? [];

    for (const bloc of blocs) {
        if (!/champsDuRole\(/.test(bloc)) {
            filtrees.push(`  ${chemin} : ${bloc.split('\n')[0].trim()}`);
        }
    }
}

if (filtrees.length > 0) {
    console.error(
        `\n${filtrees.length} projection(s) de fiche partent d'une liste de champs filtrée :\n\n` +
        `${filtrees.join('\n')}\n\n` +
        "Utilisez `champsDuRole(questionnaire, role)` — le schéma complet. Une liste filtrée par\n" +
        "la visibilité laisse vides les champs que le rattachement fait justement apparaître.",
    );
    process.exit(1);
}

console.log('D. Les projections de fiche partent toutes du schéma complet du rôle.');

// ── E. Le rattachement d'office, et ses quatre abstentions ────────────────────────────
//
// La règle décide à la place du clerc : elle doit donc s'abstenir dès qu'il y a le moindre
// doute. Quatre abstentions, chacune pour une raison différente — et la première est la seule
// qui agit. Vérifiées ici parce que la règle vit dans une fonction pure, sortie du composant
// exprès pour cela.

const unClient = (id, nom) => ({ id, type: 'physique', prenom_nom: nom, civilite: 'Mme' });
const sane = unClient(1, 'Malama SANE');
const autre = unClient(2, 'Ibrahima camara');

const attendus = [
    ['un seul candidat pourvu d\'une fiche', 'dissolution',
     [{ role: 'associe_unique', client: sane }, { role: 'gerant', client: autre }], {}, 1],
    ['deux candidats pour le même rôle', 'dissolution',
     [{ role: 'associe_unique', client: sane }, { role: 'associe_unique', client: autre }], {}, 0],
    ['le rôle est déjà pourvu à la main', 'dissolution',
     [{ role: 'associe_unique', client: sane }], { associe_unique: sane }, 0],
    ['le candidat n\'a pas de fiche client', 'dissolution',
     [{ role: 'associe_unique', client: null }], {}, 0],
    ['le rôle est porté par un bloc répétable', 'creation_sarl',
     [{ role: 'associe', client: sane }], {}, 0],
];

const ecarts = [];
for (const [titre, nomQ, personnes, liens, attendu] of attendus) {
    const obtenu = rolesARattacherDoffice(QUESTIONNAIRES[nomQ], personnes, liens).length;
    if (obtenu !== attendu) {
        ecarts.push(`  ${titre} (${nomQ}) : ${obtenu} rattachement(s), ${attendu} attendu(s)`);
    }
}

if (ecarts.length > 0) {
    console.error(
        `\nLe rattachement d'office ne se comporte pas comme prévu :\n\n${ecarts.join('\n')}\n\n` +
        "Voir rolesARattacherDoffice() dans lib/partiesPayload.js. Décider à la place du clerc\n" +
        'sur un rôle ambigu désignerait un associé qui a peut-être cédé ses parts.',
    );
    process.exit(1);
}

console.log(`E. Rattachement d'office : ${attendus.length} cas — une action, quatre abstentions.`);
