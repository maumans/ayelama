import React, { useState, useEffect, useRef, useCallback } from 'react';
import { router } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import { Search, FolderOpen, Users, ArrowRight, X } from 'lucide-react';
import { cn } from '@/lib/utils';

const typeIcon = {
    dossier: FolderOpen,
    partie:  Users,
};

export default function GlobalSearch() {
    const [open, setOpen]       = useState(false);
    const [query, setQuery]     = useState('');
    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState(0);
    const inputRef = useRef(null);
    const abortRef = useRef(null);

    // Ouvrir avec ⌘K / Ctrl+K
    useEffect(() => {
        const onKey = (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                e.preventDefault();
                setOpen(v => !v);
            }
            if (e.key === 'Escape') setOpen(false);
        };
        document.addEventListener('keydown', onKey);
        // Aussi depuis le bouton topbar
        const btn = document.getElementById('global-search-trigger');
        if (btn) btn.addEventListener('click', () => setOpen(true));
        return () => document.removeEventListener('keydown', onKey);
    }, []);

    useEffect(() => {
        if (open) {
            setQuery('');
            setResults([]);
            setSelected(0);
            setTimeout(() => inputRef.current?.focus(), 50);
        }
    }, [open]);

    const search = useCallback(async (q) => {
        if (q.length < 2) { setResults([]); return; }
        if (abortRef.current) abortRef.current.abort();
        abortRef.current = new AbortController();
        setLoading(true);
        try {
            const res = await fetch(`/search?q=${encodeURIComponent(q)}`, {
                signal: abortRef.current.signal,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            setResults(data.results ?? []);
            setSelected(0);
        } catch (err) {
            if (err.name !== 'AbortError') setResults([]);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        const timer = setTimeout(() => search(query), 250);
        return () => clearTimeout(timer);
    }, [query, search]);

    const navigate = (href) => {
        setOpen(false);
        router.visit(href);
    };

    const onKeyDown = (e) => {
        if (!results.length) return;
        if (e.key === 'ArrowDown')  { e.preventDefault(); setSelected(s => Math.min(s + 1, results.length - 1)); }
        if (e.key === 'ArrowUp')    { e.preventDefault(); setSelected(s => Math.max(s - 1, 0)); }
        if (e.key === 'Enter' && results[selected]) navigate(results[selected].href);
    };

    return (
        <AnimatePresence>
            {open && (
                <>
                    {/* Backdrop */}
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        className="fixed inset-0 z-50 bg-ink/40 backdrop-blur-sm"
                        onClick={() => setOpen(false)}
                    />

                    {/* Palette */}
                    <motion.div
                        initial={{ opacity: 0, scale: 0.97, y: -8 }}
                        animate={{ opacity: 1, scale: 1, y: 0 }}
                        exit={{ opacity: 0, scale: 0.97, y: -8 }}
                        transition={{ duration: 0.15 }}
                        className="fixed top-[15vh] left-1/2 -translate-x-1/2 z-50 w-full max-w-lg mx-auto"
                    >
                        <div className="bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden">
                            {/* Input */}
                            <div className="flex items-center gap-3 px-4 py-3 border-b border-slate-100">
                                <Search className="h-4 w-4 text-slate-400 shrink-0" />
                                <input
                                    ref={inputRef}
                                    type="text"
                                    value={query}
                                    onChange={e => setQuery(e.target.value)}
                                    onKeyDown={onKeyDown}
                                    placeholder="Rechercher un dossier, une partie…"
                                    className="flex-1 bg-transparent text-sm text-slate-800 placeholder-slate-400 outline-none"
                                />
                                {query && (
                                    <button onClick={() => setQuery('')} className="text-slate-400 hover:text-slate-600 transition-colors">
                                        <X className="h-3.5 w-3.5" />
                                    </button>
                                )}
                                <kbd className="text-xs text-slate-300 font-mono border border-slate-200 px-1.5 py-0.5 rounded">Esc</kbd>
                            </div>

                            {/* Résultats */}
                            <div className="max-h-72 overflow-y-auto">
                                {loading && (
                                    <div className="px-4 py-8 text-center text-sm text-slate-400">Recherche…</div>
                                )}

                                {!loading && query.length >= 2 && results.length === 0 && (
                                    <div className="px-4 py-8 text-center text-sm text-slate-400">
                                        Aucun résultat pour « {query} »
                                    </div>
                                )}

                                {!loading && results.length > 0 && (
                                    <ul className="py-2">
                                        {results.map((result, i) => {
                                            const Icon = typeIcon[result.type] ?? FolderOpen;
                                            return (
                                                <li key={`${result.type}-${result.id}`}>
                                                    <button
                                                        onClick={() => navigate(result.href)}
                                                        onMouseEnter={() => setSelected(i)}
                                                        className={cn(
                                                            'flex items-center gap-3 w-full px-4 py-2.5 text-left transition-colors',
                                                            i === selected ? 'bg-slate-50' : 'hover:bg-slate-50'
                                                        )}
                                                    >
                                                        <span className="flex h-7 w-7 items-center justify-center rounded-md bg-ink/5 shrink-0">
                                                            <Icon className="h-3.5 w-3.5 text-ink" />
                                                        </span>
                                                        <div className="flex-1 min-w-0">
                                                            <div className="text-sm font-medium text-slate-800 truncate">{result.label}</div>
                                                            {result.sublabel && (
                                                                <div className="text-xs text-slate-400 truncate">{result.sublabel}</div>
                                                            )}
                                                        </div>
                                                        {result.badge && (
                                                            <span className="text-xs text-slate-400 border border-slate-200 rounded px-1.5 py-0.5 shrink-0">
                                                                {result.badge}
                                                            </span>
                                                        )}
                                                        {i === selected && <ArrowRight className="h-3.5 w-3.5 text-slate-300 shrink-0" />}
                                                    </button>
                                                </li>
                                            );
                                        })}
                                    </ul>
                                )}

                                {!loading && query.length < 2 && (
                                    <div className="px-4 py-6 text-center text-xs text-slate-400">
                                        Saisissez au moins 2 caractères pour rechercher
                                    </div>
                                )}
                            </div>

                            {/* Footer */}
                            <div className="flex items-center gap-3 px-4 py-2 border-t border-slate-100 text-[11px] text-slate-400">
                                <span><kbd className="font-mono">↑↓</kbd> naviguer</span>
                                <span><kbd className="font-mono">↵</kbd> ouvrir</span>
                                <span><kbd className="font-mono">Esc</kbd> fermer</span>
                            </div>
                        </div>
                    </motion.div>
                </>
            )}
        </AnimatePresence>
    );
}
