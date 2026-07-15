import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    ClipboardCheck, Clock, AlertTriangle, ChevronRight,
    ChevronLeft, X, Search, ArrowUpDown, CheckCircle2,
    XCircle, Minus,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { STATUT_META } from '@/data/revisionStatuts';

// ── Barre de progression révision ──────────────────────────────────────────

function RevisionProgress({ revision }) {
    if (!revision) {
        return <span className="text-[10px] text-slate-400 italic">Non démarrée</span>;
    }

    const { conformes = 0, nonConformes = 0, evalues = 0 } = revision;
    const total = conformes + nonConformes;

    if (evalues === 0) {
        return <span className="text-[10px] text-slate-400">Aucun document évalué</span>;
    }

    return (
        <div className="flex items-center gap-2">
            {total > 0 && (
                <div className="flex h-1.5 w-16 rounded-full overflow-hidden bg-slate-100">
                    {conformes > 0 && (
                        <div
                            className="h-full bg-success transition-all"
                            style={{ width: `${(conformes / total) * 100}%` }}
                        />
                    )}
                    {nonConformes > 0 && (
                        <div
                            className="h-full bg-danger transition-all"
                            style={{ width: `${(nonConformes / total) * 100}%` }}
                        />
                    )}
                </div>
            )}
            <span className="text-[10px] text-slate-400">{evalues} évalué{evalues > 1 ? 's' : ''}</span>
            {nonConformes > 0 && (
                <span className="inline-flex items-center gap-0.5 text-[10px] text-danger font-medium">
                    <XCircle className="h-2.5 w-2.5" />
                    {nonConformes} à corriger
                </span>
            )}
            {nonConformes === 0 && conformes > 0 && (
                <span className="inline-flex items-center gap-0.5 text-[10px] text-success font-medium">
                    <CheckCircle2 className="h-2.5 w-2.5" />
                    {conformes} OK
                </span>
            )}
        </div>
    );
}

// ── Page principale ────────────────────────────────────────────────────────

export default function RevisionsIndex() {
    const { dossiers, stats, filters, auth } = usePage().props;
    const dossiersData = dossiers?.data ?? [];
    const totalPages   = dossiers?.last_page ?? 1;
    const currentPage  = dossiers?.current_page ?? 1;

    const [search, setSearch] = useState(filters.q      ?? '');
    const [statut, setStatut] = useState(filters.statut ?? '');
    const [retard, setRetard] = useState(filters.retard === '1');
    const [sortBy, setSortBy] = useState(filters.sort   ?? '');

    const applyFilters = (overrides = {}) => {
        const vals = {
            q:      overrides.q      !== undefined ? overrides.q      : search,
            statut: overrides.statut !== undefined ? overrides.statut : statut,
            retard: overrides.retard !== undefined ? (overrides.retard ? '1' : '') : (retard ? '1' : ''),
            sort:   overrides.sort   !== undefined ? overrides.sort   : sortBy,
        };
        const params = Object.fromEntries(Object.entries(vals).filter(([, v]) => v));
        router.get('/revisions', params, { preserveState: true, replace: true });
    };

    const resetFiltres = () => {
        setSearch(''); setStatut(''); setRetard(false); setSortBy('');
        router.get('/revisions', {}, { preserveState: false, replace: true });
    };

    const hasFiltres = search || statut || retard || sortBy;

    return (
        <AppLayout breadcrumbs={[{ label: 'Révisions' }]}>
            <Head title="Révisions — Ayelema" />

            <div className="p-6 max-w-[1000px] mx-auto space-y-5">

                {/* ── En-tête ──────────────────────────────────────────────── */}
                <div>
                    <h1 className="font-serif text-display text-ink">Révisions</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        <span className="text-slate-700 font-medium">{stats?.total ?? 0}</span> dossier{(stats?.total ?? 0) > 1 ? 's' : ''} en révision
                        {(stats?.enRetard ?? 0) > 0 && (
                            <> · <span className="text-danger font-medium">{stats.enRetard} en retard</span></>
                        )}
                    </p>
                </div>

                {/* ── Stats ─────────────────────────────────────────────────── */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    {[
                        { label: 'Total',       value: stats?.total     ?? 0, color: 'text-ink',      Icon: ClipboardCheck },
                        { label: 'En attente',  value: stats?.enAttente ?? 0, color: 'text-slate-500', Icon: Minus          },
                        { label: 'En cours',    value: stats?.enCours   ?? 0, color: 'text-warning-text', Icon: ClipboardCheck },
                        { label: 'En retard',   value: stats?.enRetard  ?? 0, color: (stats?.enRetard ?? 0) > 0 ? 'text-danger' : 'text-slate-400', Icon: AlertTriangle },
                    ].map(({ label, value, color, Icon }) => (
                        <Card key={label} className="p-3">
                            <div className="flex items-start justify-between">
                                <div>
                                    <div className={cn('text-2xl font-bold leading-none', color)}>{value}</div>
                                    <div className="text-[10px] text-slate-400 mt-1 uppercase tracking-wide">{label}</div>
                                </div>
                                <Icon className={cn('h-4 w-4 mt-0.5 opacity-40', color)} />
                            </div>
                        </Card>
                    ))}
                </div>

                {/* Distribution en attente / en cours */}
                {(stats?.total ?? 0) > 0 && (
                    <div className="flex items-center gap-3">
                        <div className="flex-1 flex h-2 rounded-full overflow-hidden bg-slate-100">
                            {(stats?.enAttente ?? 0) > 0 && (
                                <motion.div
                                    className="h-full bg-slate-300"
                                    initial={{ width: 0 }}
                                    animate={{ width: `${((stats.enAttente) / stats.total) * 100}%` }}
                                    transition={{ duration: 0.6 }}
                                />
                            )}
                            {(stats?.enCours ?? 0) > 0 && (
                                <motion.div
                                    className="h-full bg-warning"
                                    initial={{ width: 0 }}
                                    animate={{ width: `${((stats.enCours) / stats.total) * 100}%` }}
                                    transition={{ duration: 0.6, delay: 0.1 }}
                                />
                            )}
                        </div>
                        <div className="flex items-center gap-3 text-[10px] text-slate-500 shrink-0">
                            <span className="flex items-center gap-1">
                                <span className="h-2 w-2 rounded-full bg-slate-300 inline-block" />
                                En attente
                            </span>
                            <span className="flex items-center gap-1">
                                <span className="h-2 w-2 rounded-full bg-warning inline-block" />
                                En cours
                            </span>
                        </div>
                    </div>
                )}

                {/* ── Filtres ───────────────────────────────────────────────── */}
                <div className="flex flex-wrap gap-2 items-center">
                    <div className="relative flex-1 min-w-[180px] max-w-xs">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                        <Input
                            className="pl-9 h-8 text-sm"
                            placeholder="Référence ou objet…"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && applyFilters({ q: search })}
                        />
                        {search && (
                            <button
                                onClick={() => { setSearch(''); applyFilters({ q: '' }); }}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>

                    <Select value={statut} onValueChange={v => { setStatut(v); applyFilters({ statut: v }); }}>
                        <SelectTrigger className="h-8 text-sm w-36">
                            <SelectValue placeholder="Tous statuts" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Tous statuts</SelectItem>
                            <SelectItem value="en_attente">En attente</SelectItem>
                            <SelectItem value="en_cours">En cours</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select value={sortBy} onValueChange={v => { setSortBy(v); applyFilters({ sort: v }); }}>
                        <SelectTrigger className="h-8 text-sm w-40">
                            <ArrowUpDown className="h-3.5 w-3.5 mr-1 text-slate-400 shrink-0" />
                            <SelectValue placeholder="Trier par" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Par échéance</SelectItem>
                            <SelectItem value="reference">Par référence</SelectItem>
                            <SelectItem value="entree">Plus récents</SelectItem>
                        </SelectContent>
                    </Select>

                    <button
                        onClick={() => {
                            const next = !retard;
                            setRetard(next);
                            applyFilters({ retard: next });
                        }}
                        className={cn(
                            'flex items-center gap-1.5 h-8 px-3 rounded-md text-sm font-medium border transition-colors',
                            retard
                                ? 'bg-danger-bg text-danger border-red-300'
                                : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300'
                        )}
                    >
                        <AlertTriangle className="h-3.5 w-3.5" />
                        En retard
                    </button>

                    {hasFiltres && (
                        <Button
                            variant="ghost" size="sm"
                            className="h-8 text-slate-400 hover:text-slate-600"
                            onClick={resetFiltres}
                        >
                            <X className="h-3.5 w-3.5 mr-1" />
                            Réinitialiser
                        </Button>
                    )}
                </div>

                {/* ── Liste ─────────────────────────────────────────────────── */}
                {dossiersData.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-20 text-center">
                        <ClipboardCheck className="h-12 w-12 text-slate-200 mb-4" />
                        <h3 className="font-serif text-heading text-slate-500">
                            {hasFiltres ? 'Aucun résultat' : 'Aucune révision en attente'}
                        </h3>
                        <p className="text-sm text-slate-400 mt-1">
                            {hasFiltres ? 'Modifiez vos critères de recherche.' : 'Tous les dossiers sont à jour.'}
                        </p>
                        {hasFiltres && (
                            <Button variant="outline" size="sm" className="mt-4" onClick={resetFiltres}>
                                <X className="h-3.5 w-3.5 mr-1" /> Effacer les filtres
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="space-y-3">
                        {dossiersData.map((d, i) => {
                            const statutKey = d.revision?.statut ?? 'en_attente';
                            const meta      = STATUT_META[statutKey] ?? STATUT_META.en_attente;

                            return (
                                <motion.div
                                    key={d.id}
                                    initial={{ opacity: 0, y: 6 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ delay: i * 0.04 }}
                                >
                                    <Card
                                        className={cn(
                                            'hover:shadow-md transition-all cursor-pointer group border-l-4',
                                            meta.border
                                        )}
                                        onClick={() => router.visit(`/dossiers/${d.reference}?tab=revision`)}
                                    >
                                        <CardContent className="p-4">
                                            <div className="flex items-start gap-4">
                                                <div className="flex-1 min-w-0">

                                                    {/* Ligne 1 : référence + badges */}
                                                    <div className="flex items-center gap-2 flex-wrap">
                                                        <span className="font-ref text-sm text-seal font-semibold">{d.reference}</span>
                                                        <span className={cn(
                                                            'inline-flex items-center text-[10px] font-medium px-2 py-0.5 rounded-full border',
                                                            meta.badge
                                                        )}>
                                                            {meta.label}
                                                        </span>
                                                        {d.estEnRetard && (
                                                            <span className="inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-full bg-danger-bg text-danger-text border border-red-200">
                                                                <AlertTriangle className="h-2.5 w-2.5" />
                                                                En retard
                                                            </span>
                                                        )}
                                                        {d.revision?.estValidable && (
                                                            <span className="inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-full bg-success-bg text-success-text border border-green-200">
                                                                <CheckCircle2 className="h-2.5 w-2.5" />
                                                                Prêt à valider
                                                            </span>
                                                        )}
                                                    </div>

                                                    {/* Ligne 2 : objet */}
                                                    <p className="text-sm font-medium text-slate-800 mt-1 truncate">{d.objet}</p>
                                                    {d.clientPrincipal && (
                                                        <p className="text-xs text-slate-400 truncate">{d.clientPrincipal}</p>
                                                    )}

                                                    {/* Ligne 3 : méta */}
                                                    <div className="flex items-center gap-3 mt-1 text-xs text-slate-400 flex-wrap">
                                                        {d.typeActe?.label && <span className="bg-slate-50 border border-slate-200 rounded-full px-2 py-0.5 text-[10px]">{d.typeActe.label}</span>}
                                                        {d.redacteur?.name && <span>Réd. {d.redacteur.name}</span>}
                                                        {d.revision?.reviseur?.name && (
                                                            <span className="text-amber-600">⊙ {d.revision.reviseur.name}</span>
                                                        )}
                                                        {d.echeance  && (
                                                            <span className={cn(
                                                                'flex items-center gap-1',
                                                                d.estEnRetard && 'text-danger font-medium'
                                                            )}>
                                                                <Clock className="h-2.5 w-2.5" />
                                                                {d.echeance}
                                                            </span>
                                                        )}
                                                    </div>

                                                    {/* Ligne 4 : progression révision */}
                                                    <div className="mt-2">
                                                        <RevisionProgress revision={d.revision} />
                                                    </div>
                                                </div>

                                                {/* Actions droite */}
                                                <div className="flex items-center gap-2 shrink-0 self-center">
                                                    <span className="text-xs text-seal font-medium hidden sm:block">Réviser</span>
                                                    <ChevronRight className="h-4 w-4 text-slate-300 group-hover:text-seal transition-colors" />
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </motion.div>
                            );
                        })}
                    </div>
                )}

                {/* ── Pagination ────────────────────────────────────────────── */}
                {totalPages > 1 && (
                    <div className="flex items-center justify-between pt-2">
                        <span className="text-xs text-slate-500">
                            Page {currentPage} sur {totalPages}
                        </span>
                        <div className="flex items-center gap-2">
                            {dossiers.prev_page_url && (
                                <Button variant="outline" size="sm" onClick={() => router.get(dossiers.prev_page_url)}>
                                    <ChevronLeft className="h-4 w-4" /> Précédent
                                </Button>
                            )}
                            {dossiers.next_page_url && (
                                <Button variant="outline" size="sm" onClick={() => router.get(dossiers.next_page_url)}>
                                    Suivant <ChevronRight className="h-4 w-4" />
                                </Button>
                            )}
                        </div>
                    </div>
                )}

            </div>
        </AppLayout>
    );
}
