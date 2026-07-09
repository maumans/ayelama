import React, { useState, useMemo } from 'react';
import { usePage, router } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import AppLayout from '@/Layouts/AppLayout';
import { Switch } from '@/components/ui/switch';
import { Input } from '@/components/ui/input';
import { NumberField } from '@/components/ui/number-field';
import { Button } from '@/components/ui/button';
import { FileText, Search, Check, X, Pencil } from 'lucide-react';

const CAT_COLORS = {
    immobilier:  'bg-blue-50 text-blue-700 border-blue-200',
    societe:     'bg-emerald-50 text-emerald-700 border-emerald-200',
    famille:     'bg-pink-50 text-pink-700 border-pink-200',
    succession:  'bg-purple-50 text-purple-700 border-purple-200',
    commercial:  'bg-amber-50 text-amber-700 border-amber-200',
    autre:       'bg-slate-100 text-slate-600 border-slate-200',
};

const CAT_BAR = {
    immobilier: 'bg-blue-500',
    societe:    'bg-emerald-500',
    famille:    'bg-pink-500',
    succession: 'bg-purple-500',
    commercial: 'bg-amber-500',
    autre:      'bg-slate-400',
};

function InlineDelaiEdit({ typeActe }) {
    const [editing, setEditing] = useState(false);
    const [val, setVal]         = useState(String(typeActe.delai_jours ?? ''));

    const save = () => {
        const parsed = parseInt(val, 10);
        if (!isNaN(parsed) && parsed > 0 && parsed !== typeActe.delai_jours) {
            router.patch(`/parametres/types-actes/${typeActe.id}`, { delai_jours: parsed }, { preserveState: true });
        }
        setEditing(false);
    };

    const cancel = () => {
        setVal(String(typeActe.delai_jours ?? ''));
        setEditing(false);
    };

    if (editing) {
        return (
            <div className="flex items-center gap-1">
                <NumberField
                    value={val}
                    onValueChange={setVal}
                    onKeyDown={e => { if (e.key === 'Enter') save(); if (e.key === 'Escape') cancel(); }}
                    className="h-6 w-16 text-xs text-center px-1"
                    autoFocus
                />
                <button onClick={save}   className="text-green-600 hover:text-green-700"><Check className="h-3.5 w-3.5" /></button>
                <button onClick={cancel} className="text-red-400 hover:text-red-500"><X className="h-3.5 w-3.5" /></button>
            </div>
        );
    }

    return (
        <button
            onClick={() => setEditing(true)}
            className="flex items-center gap-1 text-sm text-slate-600 group/delai hover:text-seal"
            title="Cliquer pour modifier"
        >
            {typeActe.delai_jours ?? '—'}
            <Pencil className="h-3 w-3 text-slate-300 group-hover/delai:text-seal opacity-0 group-hover/delai:opacity-100 transition-opacity" />
        </button>
    );
}

export default function ParametresTypesActes() {
    const { typesActes = [] } = usePage().props;

    const [search, setSearch] = useState('');

    const toggleActif = (t) => {
        router.patch(`/parametres/types-actes/${t.id}`, { actif: !t.actif }, { preserveState: true });
    };

    const filtered = useMemo(() => {
        const q = search.toLowerCase();
        if (!q) return typesActes;
        return typesActes.filter(t =>
            t.label?.toLowerCase().includes(q) ||
            t.code?.toLowerCase().includes(q) ||
            t.categorieLabel?.toLowerCase().includes(q)
        );
    }, [typesActes, search]);

    /* Grouper par catégorie en conservant l'ordre naturel */
    const grouped = useMemo(() => {
        const map = new Map();
        for (const t of filtered) {
            const key = t.categorie ?? 'autre';
            if (!map.has(key)) map.set(key, { label: t.categorieLabel ?? t.categorie ?? 'Autre', items: [] });
            map.get(key).items.push(t);
        }
        return [...map.entries()].map(([cat, { label, items }]) => ({ cat, label, items }));
    }, [filtered]);

    const totalActifs = typesActes.filter(t => t.actif).length;

    return (
        <AppLayout breadcrumbs={[{ label: 'Paramètres', href: '/parametres' }, { label: "Types d'actes" }]}>
            <div className="p-6 max-w-5xl mx-auto space-y-5">

                {/* En-tête */}
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-2">
                        <FileText className="h-5 w-5 text-seal" />
                        <div>
                            <h1 className="text-xl font-semibold text-ink">Types d'actes</h1>
                            <p className="text-xs text-slate-500">
                                {typesActes.length} type{typesActes.length > 1 ? 's' : ''} configuré{typesActes.length > 1 ? 's' : ''}
                                <span className="ml-1 text-green-600">· {totalActifs} actif{totalActifs > 1 ? 's' : ''}</span>
                            </p>
                        </div>
                    </div>
                    <div className="relative">
                        <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400 pointer-events-none" />
                        <Input
                            placeholder="Rechercher…"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            className="pl-8 h-8 text-sm w-56"
                        />
                    </div>
                </div>

                {/* Groupes */}
                <AnimatePresence mode="popLayout">
                    {grouped.length === 0 && (
                        <div className="text-center text-slate-400 italic text-sm py-12">
                            Aucun type d'acte trouvé.
                        </div>
                    )}
                    {grouped.map(({ cat, label, items }) => {
                        const barCls   = CAT_BAR[cat]    ?? CAT_BAR.autre;
                        const badgeCls = CAT_COLORS[cat] ?? CAT_COLORS.autre;
                        const actifs   = items.filter(t => t.actif).length;
                        return (
                            <motion.div
                                key={cat}
                                initial={{ opacity: 0, y: 6 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, y: -4 }}
                                transition={{ duration: 0.2 }}
                            >
                                {/* Group header */}
                                <div className="flex items-center gap-2 mb-2 px-1">
                                    <div className={`h-3 w-1 rounded-full ${barCls}`} />
                                    <span className="text-xs font-semibold text-slate-600 uppercase tracking-wider">{label}</span>
                                    <span className={`text-[10px] px-1.5 py-0.5 rounded-full border ${badgeCls}`}>
                                        {actifs}/{items.length} actifs
                                    </span>
                                </div>

                                <div className="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm mb-4">
                                    <table className="table-notarial w-full">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Libellé</th>
                                                <th className="text-center">Délai (j)</th>
                                                <th className="hidden md:table-cell">Description</th>
                                                <th className="text-center">Actif</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {items.map(t => (
                                                <tr key={t.id} className={!t.actif ? 'opacity-50' : ''}>
                                                    <td className="font-mono text-seal text-xs">{t.code}</td>
                                                    <td>
                                                        <span className="font-medium text-sm">{t.label}</span>
                                                    </td>
                                                    <td className="text-center">
                                                        <InlineDelaiEdit typeActe={t} />
                                                    </td>
                                                    <td className="hidden md:table-cell text-slate-500 text-xs max-w-xs truncate">
                                                        {t.description || <span className="italic text-slate-300">—</span>}
                                                    </td>
                                                    <td className="text-center">
                                                        <Switch checked={t.actif} onCheckedChange={() => toggleActif(t)} />
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </motion.div>
                        );
                    })}
                </AnimatePresence>
            </div>
        </AppLayout>
    );
}
