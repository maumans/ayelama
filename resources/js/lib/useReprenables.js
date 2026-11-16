import { useEffect, useRef, useState } from 'react';
import { typePersonneCanonique } from '@/lib/piecesRequises';

// Pièces qu'une personne existante a déjà fournies ailleurs, et qui seront **reprises d'office**
// à la création du dossier (retour R08, 2026-11-16).
//
// Lecture seule : la reprise elle-même se fait côté serveur (`DossierController::
// reprendrePiecesConnues()`), à partir des mêmes règles. L'écran ne fait que l'annoncer, avec la
// date — une pièce d'identité a une durée de validité, et il faut pouvoir juger un vieux scan
// trop ancien et en déposer un neuf, qui l'emportera.
//
// Une requête par combinaison (fiche, rôle, nature, motif), mise en cache pour la session de
// saisie : le jeu exigé dépend des quatre, et c'est le serveur qui le compose.

const signature = ({ clientId, role, typePersonne, motif }) =>
    `${clientId}|${role}|${typePersonneCanonique(typePersonne)}|${motif ?? ''}`;

/**
 * @returns {(personne: {clientId?: number, role?: string, typePersonne?: string, motif?: string}, categorie: string)
 *            => ({dossier: string, date: ?string}|null)}
 */
export function useReprenables() {
    const [cache, setCache] = useState({});
    const demandes = useRef(new Map());

    // Les demandes sont relevées pendant le rendu, et envoyées après : appeler setState pendant
    // le rendu d'un autre composant est interdit par React.
    useEffect(() => {
        for (const [sig, personne] of demandes.current) {
            if (cache[sig] !== undefined) continue;
            setCache(c => ({ ...c, [sig]: 'en_cours' }));
            const params = new URLSearchParams({
                role: personne.role,
                type_personne: typePersonneCanonique(personne.typePersonne),
                ...(personne.motif ? { motif: personne.motif } : {}),
            });
            fetch(`/clients/${personne.clientId}/pieces-reprenables?${params}`, {
                headers: { Accept: 'application/json' },
            })
                .then(r => (r.ok ? r.json() : {}))
                .catch(() => ({}))
                // Une réponse vide est mise en cache aussi : la redemander à chaque rendu ferait
                // une requête par frappe au clavier.
                .then(json => setCache(c => ({ ...c, [sig]: json && typeof json === 'object' ? json : {} })));
        }
        demandes.current.clear();
    });

    return (personne, categorie) => {
        if (!personne?.clientId || !personne?.role) return null;
        const sig = signature(personne);
        const connu = cache[sig];
        if (connu === undefined) {
            demandes.current.set(sig, personne);
            return null;
        }
        return connu === 'en_cours' ? null : (connu[categorie] ?? null);
    };
}
