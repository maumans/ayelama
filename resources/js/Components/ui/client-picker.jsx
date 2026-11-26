import { useState, useCallback, useEffect, useRef } from 'react';
import axios from 'axios';
import { Search, UserPlus, X, Check, Loader2, Users, Pencil } from 'lucide-react';
import { clientDisplayName, clientSubtitle } from '@/lib/clientFields';

/**
 * Sélecteur de client réutilisable : recherche/sélection d'un client existant
 * (autocomplétion sur `/clients/autocomplete`) ou déclenchement de la création
 * d'un nouveau client (délégué au parent via onCreateNew, généralement une modale).
 *
 * Quand `linked` est fourni, affiche l'état "client rattaché" avec possibilité
 * de détacher (onUnlink) plutôt que le champ de recherche.
 *
 * `personnesDuDossier` ([{ client, roles }], voir `personnesDesignees()` dans
 * lib/partiesPayload.js) liste les personnes déjà désignées ailleurs dans le dossier, **avec
 * la place qu'elles y tiennent**, pour les réutiliser sans les rechercher.
 *
 * Refonte du 2026-11-26, après un retour d'usage jugé « catastrophique » :
 *  - les personnes du dossier étaient des puces sans titre ni rôle : on ne savait ni ce
 *    qu'elles étaient, ni qu'elles tenaient déjà un rôle ;
 *  - le focus ouvrait **tout le répertoire** avant la moindre frappe ; la liste ne s'ouvre
 *    plus qu'à partir de deux caractères ;
 *  - « Créer un nouveau client » n'existait qu'au bas de cette liste déroulante : c'est
 *    désormais un bouton visible en permanence à côté du champ.
 */
const LONGUEUR_MINIMALE = 2;

export function ClientPicker({ onSelect, onCreateNew, onEditClient, linked, onUnlink, placeholder, personnesDuDossier = [] }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const abortRef = useRef(null);
    const boxRef = useRef(null);

    useEffect(() => {
        function onClickOutside(e) {
            if (boxRef.current && !boxRef.current.contains(e.target)) setOpen(false);
        }
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    const search = useCallback(async (q) => {
        if (abortRef.current) abortRef.current.abort();
        abortRef.current = new AbortController();
        setLoading(true);
        try {
            const res = await axios.get('/clients/autocomplete', {
                params: { q },
                signal: abortRef.current.signal,
            });
            setResults(res.data ?? []);
        } catch (err) {
            if (err.code !== 'ERR_CANCELED') setResults([]);
        } finally {
            setLoading(false);
        }
    }, []);

    const assezLong = query.trim().length >= LONGUEUR_MINIMALE;

    useEffect(() => {
        if (!assezLong) {
            if (abortRef.current) abortRef.current.abort();
            setResults([]);
            setLoading(false);
            return;
        }
        const t = setTimeout(() => search(query.trim()), 250);
        return () => clearTimeout(t);
    }, [query, assezLong, search]);

    if (linked) {
        return (
            <div className="rounded-lg border border-seal/30 bg-seal-light px-3 py-2 text-sm">
                <div className="flex items-center gap-2">
                    <Check className="h-3.5 w-3.5 text-seal-hover shrink-0" />
                    <span className="flex-1 min-w-0 truncate">
                        <span className="font-medium text-slate-800">{clientDisplayName(linked) || 'Client sans nom'}</span>
                        {clientSubtitle(linked) && <span className="text-slate-500"> · {clientSubtitle(linked)}</span>}
                    </span>
                    <button
                        type="button"
                        onClick={onUnlink}
                        className="text-slate-400 hover:text-danger transition-colors shrink-0"
                        title="Retirer le lien client"
                    >
                        <X className="h-3.5 w-3.5" />
                    </button>
                </div>

                {/* Corriger la fiche sans quitter l'écran — même geste que dans les cartes de rôle
                    (ClientRoleSection), où il existait déjà. Sans lui, une fiche créée à la volée et
                    incomplète obligeait à passer par le Répertoire, puis à revenir.

                    Sur sa **propre ligne**, et non à droite du nom : `DialogContent` est une grille,
                    dont la colonne se dimensionne sur le contenu le plus large. Mesuré dans le modal
                    « Modifier le dossier » (448 px) : le bouton en ligne portait le formulaire à
                    468 px, qui débordait alors de la boîte — titre et libellés coupés à gauche.

                    Optionnel : un appelant qui ne sait pas propager la correction ne le passe pas. */}
                {onEditClient && (
                    <button
                        type="button"
                        onClick={() => onEditClient(linked)}
                        className="mt-1 inline-flex items-center gap-1 text-xs text-slate-500 transition-colors hover:text-seal hover:underline"
                    >
                        <Pencil className="h-3 w-3" /> Modifier la fiche
                    </button>
                )}
            </div>
        );
    }

    const choisir = (client) => { onSelect(client); setOpen(false); setQuery(''); };

    return (
        <div className="space-y-2" ref={boxRef}>
            {personnesDuDossier.length > 0 && (
                <div className="rounded-lg border border-slate-200 bg-white">
                    <p className="flex items-center gap-1.5 px-3 pt-2 text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                        <Users className="h-3 w-3" />
                        Déjà dans ce dossier
                    </p>
                    <ul className="py-1">
                        {personnesDuDossier.map(({ client, roles = [] }) => (
                            <li key={client.id}>
                                <button
                                    type="button"
                                    onClick={() => choisir(client)}
                                    className="flex w-full items-baseline gap-2 px-3 py-1.5 text-left text-sm transition-colors hover:bg-seal-light"
                                    title={`Désigner ${clientDisplayName(client) || 'ce client'}`}
                                >
                                    <span className="font-medium text-slate-800">{clientDisplayName(client) || 'Client sans nom'}</span>
                                    <span className="truncate text-xs text-slate-400">
                                        {roles.length > 0 ? roles.join(' · ') : 'pas encore désigné'}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="flex items-center gap-2">
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                    <input
                        type="text"
                        value={query}
                        onChange={e => { setQuery(e.target.value); setOpen(true); }}
                        onFocus={() => setOpen(true)}
                        placeholder={placeholder ?? 'Rechercher dans le répertoire (nom, pièce, téléphone)…'}
                        className="w-full h-8 text-sm rounded-lg border border-slate-200 bg-white pl-8 pr-2 focus:outline-none focus:ring-2 focus:ring-seal"
                    />
                    {loading && <Loader2 className="absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 animate-spin text-slate-300" />}

                    {open && assezLong && (
                        <div className="absolute z-20 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg overflow-hidden">
                            <div className="max-h-56 overflow-y-auto">
                                {loading && results.length === 0 && (
                                    <div className="px-3 py-2 text-xs text-slate-400">Recherche…</div>
                                )}
                                {!loading && results.length === 0 && (
                                    <div className="px-3 py-2 text-xs text-slate-400">
                                        Aucune fiche trouvée pour « {query.trim()} ».
                                        {onCreateNew && ' Utilisez « Créer une fiche ».'}
                                    </div>
                                )}
                                {results.map(c => (
                                    <button
                                        key={c.id}
                                        type="button"
                                        onClick={() => choisir(c)}
                                        className="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm hover:bg-slate-50 transition-colors"
                                    >
                                        <span className="font-medium text-slate-800">{clientDisplayName(c) || 'Client sans nom'}</span>
                                        {clientSubtitle(c) && <span className="text-xs text-slate-400">{clientSubtitle(c)}</span>}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {onCreateNew && (
                    <button
                        type="button"
                        onClick={() => { setOpen(false); onCreateNew(); }}
                        className="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg border border-seal/40 bg-white px-2.5 text-xs font-medium text-seal-hover transition-colors hover:bg-seal-light"
                    >
                        <UserPlus className="h-3.5 w-3.5" />
                        Créer une fiche
                    </button>
                )}
            </div>
        </div>
    );
}
