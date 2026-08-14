import { useState, useCallback, useEffect, useRef } from 'react';
import axios from 'axios';
import { Search, Building2, BuildingIcon, X, Check, Loader2, PlusCircle, Users, ExternalLink } from 'lucide-react';

/**
 * Sélecteur de société du registre (`/societes/autocomplete`).
 *
 * Même structure que ClientPicker — débounce 250 ms, AbortController, état « rattaché »
 * avec détachement, entrée « créer » en pied de liste — parce que c'est le même geste,
 * appliqué à la personne morale plutôt qu'à la personne physique.
 *
 * Deux capacités qui lui sont propres :
 *
 *   1. **carte de synthèse** de la société rattachée (forme, RCCM, capital, siège, dossier
 *      d'origine). Ouvrir un dossier de modification suppose de vérifier qu'on part bien de
 *      l'état actuel de la société : afficher seulement son nom ne le permettrait pas.
 *
 *   2. **import des personnes connues** — les associés et gérants du dossier de
 *      constitution. Pour une cession de parts, le cédant est presque toujours un associé
 *      déjà fiché : le proposer en un clic évite la ressaisie et garantit que c'est la
 *      **même** fiche client qui est réutilisée, non un doublon concurrent (la fiche client
 *      est la source de vérité de l'identité).
 */
export function SocietePicker({
    linked,
    onSelect,
    onUnlink,
    onCreateNew,
    onImporterPersonnes,
    placeholder,
}) {
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
            const res = await axios.get('/societes/autocomplete', {
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
        const personnes = linked.personnesConnues ?? [];

        return (
            <div className="rounded-lg border border-seal/30 bg-seal-light px-3 py-2.5 space-y-2.5">
                <div className="flex items-start gap-2">
                    <Check className="mt-0.5 h-3.5 w-3.5 shrink-0 text-seal-hover" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium text-slate-800">
                            {linked.nom_complet || linked.denomination || 'Société sans dénomination'}
                        </p>
                        <p className="mt-0.5 text-xs text-slate-500">
                            {[linked.forme, linked.rccm_numero].filter(Boolean).join(' · ') || 'Forme et RCCM non renseignés'}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onUnlink}
                        className="shrink-0 text-slate-400 transition-colors hover:text-danger"
                        title="Détacher cette société"
                    >
                        <X className="h-3.5 w-3.5" />
                    </button>
                </div>

                <dl className="grid grid-cols-2 gap-x-4 gap-y-1.5 border-t border-seal/20 pt-2 sm:grid-cols-3">
                    <SyntheseItem label="Capital actuel" value={formaterMontant(linked.capital_chiffres)} mono />
                    <SyntheseItem label="Parts" value={linked.nombre_parts || '—'} mono />
                    <SyntheseItem
                        label="Siège"
                        value={[linked.siege_quartier, linked.siege_commune, linked.siege_ville].filter(Boolean).join(', ') || '—'}
                    />
                </dl>

                {linked.dossier_origine?.reference && (
                    <a
                        href={`/dossiers/${linked.dossier_origine.reference}`}
                        target="_blank"
                        rel="noreferrer"
                        className="inline-flex items-center gap-1 text-xs text-seal-hover hover:underline"
                    >
                        <ExternalLink className="h-3 w-3" />
                        Dossier de constitution {linked.dossier_origine.reference}
                    </a>
                )}

                {/* Les personnes du dossier de constitution : proposées, jamais imposées —
                    un associé d'origine peut avoir déjà cédé toutes ses parts. */}
                {personnes.length > 0 && onImporterPersonnes && (
                    <div className="border-t border-seal/20 pt-2">
                        <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                            Personnes connues de cette société
                        </p>
                        <div className="mt-1.5 flex flex-wrap gap-1.5">
                            {personnes.map(p => (
                                <button
                                    key={p.partie_id}
                                    type="button"
                                    onClick={() => onImporterPersonnes(p)}
                                    disabled={!p.client_id}
                                    title={p.client_id
                                        ? 'Ajouter cette personne aux clients du dossier'
                                        : "Cette personne n'a pas de fiche client — à créer manuellement"}
                                    className="inline-flex items-center gap-1 rounded-full border border-seal/30 bg-white px-2.5 py-1 text-xs text-slate-700 transition-colors hover:bg-seal/10 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <Users className="h-3 w-3 text-seal-hover" />
                                    {p.nom}
                                    <span className="text-slate-400">· {libelleRole(p.role)}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className="relative" ref={boxRef}>
            <div className="relative">
                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                <input
                    type="text"
                    value={query}
                    onChange={e => { setQuery(e.target.value); setOpen(true); }}
                    onFocus={() => { setOpen(true); search(query); }}
                    placeholder={placeholder ?? 'Rechercher une société du registre (dénomination, sigle, RCCM, NIF)…'}
                    className="h-8 w-full rounded-lg border border-slate-200 bg-white pl-8 pr-2 text-sm focus:outline-none focus:ring-2 focus:ring-seal"
                />
                {loading && <Loader2 className="absolute right-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 animate-spin text-slate-300" />}
            </div>
            {open && (
                <div className="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg">
                    <div className="max-h-56 overflow-y-auto">
                        {!loading && results.length === 0 && (
                            <div className="px-3 py-2 text-xs text-slate-400">
                                {query.length > 0
                                    ? `Aucune société trouvée pour « ${query} » — créez sa fiche ci-dessous.`
                                    : 'Aucune société au registre pour le moment.'}
                            </div>
                        )}
                        {results.map(s => (
                            <button
                                key={s.id}
                                type="button"
                                onClick={() => { onSelect(s); setOpen(false); setQuery(''); }}
                                className="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm transition-colors hover:bg-slate-50"
                            >
                                <span className="flex items-center gap-1.5 font-medium text-slate-800">
                                    <Building2 className="h-3.5 w-3.5 text-blue-600" />
                                    {s.nom_complet || s.denomination}
                                </span>
                                <span className="pl-5 text-xs text-slate-400">
                                    {[s.forme, s.rccm_numero, formaterMontant(s.capital_chiffres, true)].filter(Boolean).join(' · ') || 'Aucun détail enregistré'}
                                </span>
                            </button>
                        ))}
                    </div>
                    <button
                        type="button"
                        onClick={() => { onCreateNew(); setOpen(false); }}
                        className="flex w-full items-center gap-2 border-t border-slate-100 px-3 py-2 text-sm text-seal-hover transition-colors hover:bg-seal-light"
                    >
                        <PlusCircle className="h-3.5 w-3.5" />
                        Ajouter une société absente du registre
                    </button>
                </div>
            )}
        </div>
    );
}

function SyntheseItem({ label, value, mono }) {
    return (
        <div className="min-w-0">
            <dt className="text-[10px] font-semibold uppercase tracking-wider text-slate-400">{label}</dt>
            <dd className={`truncate text-xs font-medium text-slate-700 ${mono ? 'font-ref' : ''}`}>{value}</dd>
        </div>
    );
}

function formaterMontant(valeur, court = false) {
    const n = Number(valeur);
    if (!valeur || Number.isNaN(n) || n === 0) return court ? null : '—';
    return `${n.toLocaleString('fr-FR')} GNF`;
}

function libelleRole(role) {
    return {
        associe: 'associé',
        associe_unique: 'associé unique',
        gerant: 'gérant',
    }[role] ?? role;
}
