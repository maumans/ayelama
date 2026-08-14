// Sérialisation / restauration de l'état de l'assistant de création de dossier,
// pour l'enregistrer en brouillon (table `dossier_brouillons`, voir
// app/Http/Controllers/DossierBrouillonController.php).
//
// Les fichiers sélectionnés ne sont pas sérialisables en JSON : ils partent
// séparément dans le multipart et le serveur ne renvoie que leur emplacement, sous
// `piecesBrouillon`. L'assistant distingue donc deux états pour une pièce :
//   - `stagedPieces[groupe][cle]` — un File choisi dans cette session
//   - `piecesBrouillon[groupe][cle]` — { chemin, nom } déjà téléversé au brouillon

/**
 * État de l'assistant, sans les fichiers. Volontairement explicite plutôt qu'un
 * ramassage automatique : un champ ajouté à l'assistant doit être ajouté ici
 * sciemment, sinon on persisterait des états techniques (ouverture de modales,
 * indicateurs de soumission) qui n'ont aucun sens à la reprise.
 *
 * ⚠️ Le revers de cette liste blanche s'est vérifié : `societeLink` y avait été **oublié**, alors
 * que l'assistant le transmettait et le relisait bien. Reprendre un brouillon de modification
 * retrouvait donc les champs `soc.*` remplis (`formValues` étant persisté) mais **plus la société
 * sélectionnée** — donc `societe_id` partait vide et le dossier naissait délié du registre.
 * `cleseAttendues()` ci-dessous ferme la porte à une nouvelle omission.
 */
export function serialiserEtat(etat) {
    return {
        version: 1,
        step: etat.step,
        categorie: etat.categorie,
        sousGroupe: etat.sousGroupe,
        typeActeId: etat.typeActe?.id ?? null,
        formValues: etat.formValues,
        clientLinks: etat.clientLinks,
        dossierClients: etat.dossierClients,
        saisieLibreRoles: etat.saisieLibreRoles,
        societeLink: etat.societeLink ?? null,
        objet: etat.objet,
        urgent: etat.urgent,
        notes: etat.notes,
        notaireId: etat.notaireId,
        reviseurId: etat.reviseurId,
        formalisteId: etat.formalisteId,
        piecesBrouillon: etat.piecesBrouillon,
    };
}

/**
 * Clés transmises par l'assistant que la liste blanche traite volontairement autrement —
 * `typeActe` est persisté sous la forme réduite `typeActeId`.
 */
const CLES_TRANSFORMEES = new Set(['typeActe']);

/**
 * Garde-fou de développement : signale toute clé de l'état de l'assistant que `serialiserEtat()`
 * laisserait tomber.
 *
 * C'est ce qui manquait le jour où `societeLink` a été oublié dans la liste blanche : l'assistant le
 * transmettait, `serialiserEtat()` le jetait, et le brouillon revenait avec les champs remplis mais
 * la société déliée — aucun signal, ni à la compilation ni à l'exécution.
 *
 * Un avertissement de console plutôt qu'une erreur : un brouillon incomplet reste préférable à un
 * enregistrement refusé. Silencieux en production, où il n'aurait aucun lecteur.
 */
function verifierCouvertureEtat(etat) {
    if (!import.meta.env?.DEV) return;

    const persistees = new Set(Object.keys(serialiserEtat(etat)));
    const oubliees = Object.keys(etat ?? {})
        .filter(cle => !persistees.has(cle) && !CLES_TRANSFORMEES.has(cle));

    if (oubliees.length > 0) {
        console.warn(
            `[brouillon] ${oubliees.join(', ')} ne sera pas conservé : ajoutez ces clés à ` +
            'serialiserEtat() dans resources/js/lib/brouillonDossier.js, sinon la reprise du ' +
            'brouillon les perdra en silence.',
        );
    }
}

/**
 * Construit le FormData d'enregistrement : l'état en JSON encodé (trop imbriqué
 * pour survivre à une sérialisation en champs de formulaire) + les fichiers
 * nouvellement choisis, sous `pieces[groupe][cle]`.
 */
export function construireFormDataBrouillon({ etat, stagedPieces, brouillonId, typeActeId, libelle }) {
    verifierCouvertureEtat(etat);

    const form = new FormData();
    form.append('etat', JSON.stringify(serialiserEtat(etat)));
    if (brouillonId) form.append('brouillon_id', brouillonId);
    if (typeActeId) form.append('type_acte_id', typeActeId);
    if (libelle) form.append('libelle', libelle);

    for (const [groupe, pieces] of Object.entries(stagedPieces ?? {})) {
        for (const [cle, fichier] of Object.entries(pieces ?? {})) {
            if (fichier instanceof File) {
                form.append(`pieces[${groupe}][${cle}]`, fichier);
            }
        }
    }

    return form;
}

/**
 * Libellé du brouillon dans la liste de reprise : l'objet saisi s'il existe (c'est
 * ce que l'utilisateur reconnaîtra), sinon le type d'acte, sinon rien — mieux vaut
 * un libellé vide qu'un « Brouillon #12 » qui n'aide personne à s'y retrouver.
 */
export function libelleBrouillon({ objet, typeActeLabel }) {
    const texte = (objet ?? '').trim();
    if (texte) return texte.slice(0, 255);
    return typeActeLabel ?? null;
}

/**
 * Nombre de pièces déjà téléversées dans un brouillon, tous groupes confondus.
 */
export function compterPieces(piecesBrouillon) {
    return Object.values(piecesBrouillon ?? {})
        .reduce((total, groupe) => total + Object.keys(groupe ?? {}).length, 0);
}
