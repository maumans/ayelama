import { useState, useCallback, useEffect, useRef } from 'react';
import axios from 'axios';
import { Search, UserPlus, X, Check, Loader2, Users } from 'lucide-react';
import { clientDisplayName, clientSubtitle } from '@/lib/clientFields';

/**
 * Sélecteur de client réutilisable : recherche/sélection d'un client existant
 * (autocomplétion sur `/clients/autocomplete`) ou déclenchement de la création
 * d'un nouveau client (délégué au parent via onCreateNew, généralement une modale).
 *
 * Quand `linked` est fourni, affiche l'état "client rattaché" avec possibilité
 * de détacher (onUnlink) plutôt que le champ de recherche.
 *
 * `poolClients`, si fourni, affiche des puces de sélection rapide pour les clients
 * déjà ajoutés au dossier en cours (voir section "Clients du dossier" dans
 * Dossiers/Create.jsx) — permet de les réutiliser comme gérant/associé/etc. sans
 * retaper une recherche.
 */
export function ClientPicker({ onSelect, onCreateNew, linked, onUnlink, placeholder, poolClients = [] }) {
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

    useEffect(() => {
        const t = setTimeout(() => search(query), 250);
        return () => clearTimeout(t);
    }, [query, search]);

    if (linked) {
        return (
            <div className="flex items-center gap-2 rounded-lg border border-seal/30 bg-seal-light px-3 py-2 text-sm">
                <Check className="h-3.5 w-3.5 text-seal-hover shrink-0" />
                <span className="flex-1 min-w-0 truncate">
                    <span className="font-medium text-slate-800">{clientDisplayName(linked)}</span>
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
        );
    }

    return (
        <div className="relative" ref={boxRef}>
            {poolClients.length > 0 && (
                <div className="mb-1.5 flex flex-wrap gap-1.5">
                    {poolClients.map(c => (
                        <button
                            key={c.id}
                            type="button"
                            onClick={() => onSelect(c)}
                            className="inline-flex items-center gap-1 rounded-full border border-seal/30 bg-seal-light px-2.5 py-1 text-xs text-seal-hover hover:bg-seal/10 transition-colors"
                            title="Réutiliser ce client du dossier"
                        >
                            <Users className="h-3 w-3" />
                            {clientDisplayName(c)}
                        </button>
                    ))}
                </div>
            )}
            <div className="relative">
                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                    type="text"
                    value={query}
                    onChange={e => { setQuery(e.target.value); setOpen(true); }}
                    onFocus={() => setOpen(true)}
                    placeholder={placeholder ?? 'Rechercher un client existant…'}
                    className="w-full h-8 text-sm rounded-lg border border-slate-200 bg-white pl-8 pr-2 focus:outline-none focus:ring-2 focus:ring-seal"
                />
                {loading && <Loader2 className="absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 animate-spin text-slate-300" />}
            </div>
            {open && (
                <div className="absolute z-20 mt-1 w-full rounded-lg border border-slate-200 bg-white shadow-lg overflow-hidden">
                    <div className="max-h-56 overflow-y-auto">
                        {!loading && results.length === 0 && (
                            <div className="px-3 py-2 text-xs text-slate-400">
                                {query.length > 0 ? `Aucun client trouvé pour « ${query} »` : 'Aucun client enregistré pour le moment.'}
                            </div>
                        )}
                        {results.map(c => (
                            <button
                                key={c.id}
                                type="button"
                                onClick={() => { onSelect(c); setOpen(false); setQuery(''); }}
                                className="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm hover:bg-slate-50 transition-colors"
                            >
                                <span className="font-medium text-slate-800">{clientDisplayName(c)}</span>
                                {clientSubtitle(c) && <span className="text-xs text-slate-400">{clientSubtitle(c)}</span>}
                            </button>
                        ))}
                    </div>
                    <button
                        type="button"
                        onClick={() => { onCreateNew(); setOpen(false); }}
                        className="flex w-full items-center gap-2 px-3 py-2 text-sm text-seal-hover hover:bg-seal-light border-t border-slate-100 transition-colors"
                    >
                        <UserPlus className="h-3.5 w-3.5" />
                        Créer un nouveau client
                    </button>
                </div>
            )}
        </div>
    );
}
