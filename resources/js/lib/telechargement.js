// Téléchargement d'un fichier servi par l'application, **avec vérification de ce qui arrive**.
//
// ⚠️ **Le défaut que ce module supprime.** Le bouton de la note de frais était un `<a href download>`
// nu. Quand la réponse n'était pas le fichier attendu — page d'erreur serveur, redirection parce que
// la session avait expiré — le navigateur l'enregistrait **quand même**, sous le nom du dernier
// segment de l'URL : l'étude se retrouvait avec un « telecharger.htm » inexploitable, sans le
// moindre message. Un échec déguisé en succès est le pire des deux.
//
// Ici la réponse est lue, son type vérifié, et un échec **se dit**.

import { toast } from '@/lib/toast';

/** Le serveur a-t-il renvoyé une page web là où un fichier était attendu ? */
function estUnePageWeb(reponse) {
    const type = reponse.headers.get('Content-Type') ?? '';

    return type.includes('text/html');
}

/** Nom proposé par le serveur (`Content-Disposition`), à défaut celui fourni par l'appelant. */
function nomDepuisEntete(reponse, nomParDefaut) {
    const disposition = reponse.headers.get('Content-Disposition') ?? '';
    const trouve = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition);

    return trouve ? decodeURIComponent(trouve[1]) : nomParDefaut;
}

/**
 * Télécharge `url` et le remet à l'utilisateur, ou explique pourquoi ce n'est pas possible.
 *
 * `credentials: 'same-origin'` : le cookie de session doit accompagner la requête, sans quoi on
 * reçoit précisément la redirection de connexion qui produisait le fichier `.htm`.
 *
 * @param {string} url
 * @param {?string} nomParDefaut employé si le serveur ne nomme pas le fichier
 */
export async function telechargerFichier(url, nomParDefaut = null) {
    try {
        const reponse = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/pdf,application/octet-stream,*/*' },
        });

        if (!reponse.ok) {
            toast.error(
                reponse.status === 419 || reponse.status === 401
                    ? 'Votre session a expiré — rechargez la page et reconnectez-vous.'
                    : `Le fichier n'a pas pu être produit (erreur ${reponse.status}).`,
            );

            return false;
        }

        // C'est **ce contrôle** qui manquait : sans lui, la page HTML était enregistrée telle quelle.
        if (estUnePageWeb(reponse)) {
            toast.error(
                "Le serveur a renvoyé une page web au lieu du fichier. Rien n'a été enregistré — "
                + 'signalez-le si cela se reproduit.',
            );

            return false;
        }

        const blob = await reponse.blob();
        const lien = document.createElement('a');
        const objet = URL.createObjectURL(blob);

        lien.href = objet;
        lien.download = nomDepuisEntete(reponse, nomParDefaut ?? 'document');
        document.body.appendChild(lien);
        lien.click();
        lien.remove();

        // Sans révocation, le blob reste en mémoire jusqu'au rechargement de la page.
        URL.revokeObjectURL(objet);

        return true;
    } catch {
        toast.error('Le téléchargement a échoué — vérifiez votre connexion.');

        return false;
    }
}
