import React, { useState, useMemo } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    FolderOpen, ClipboardCheck, Clock, Building2,
    AlertTriangle, ChevronRight, Plus,
    PenLine, CheckCircle2, Package, MessageSquare, FolderPlus, Activity,
    LayoutGrid, Archive, Mail, CalendarDays, Filter, X,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/* ═══════════════════════════════════════════════════════════
   Metadata
═══════════════════════════════════════════════════════════ */

const ETAPE_META = {
    initialisation:    { label: 'Initialisation', short: 'Init.',      ordre: 0, color: 'bg-slate-400',  light: 'bg-slate-50',   text: 'text-slate-600',   border: 'border-slate-300' },
    edition:           { label: 'Édition',        short: 'Édition',    ordre: 1, color: 'bg-blue-500',   light: 'bg-blue-50',    text: 'text-blue-700',    border: 'border-blue-300' },
    revision:          { label: 'Révision',       short: 'Révision',   ordre: 2, color: 'bg-amber-500',  light: 'bg-amber-50',   text: 'text-amber-700',   border: 'border-amber-300' },
    signature_client:  { label: 'Sig. Client',    short: 'Sig.Cli.',   ordre: 3, color: 'bg-purple-500', light: 'bg-purple-50',  text: 'text-purple-700',  border: 'border-purple-300' },
    signature_notaire: { label: 'Sig. Notaire',   short: 'Sig.Not.',   ordre: 4, color: 'bg-violet-500', light: 'bg-violet-50',  text: 'text-violet-700',  border: 'border-violet-300' },
    formalites:        { label: 'Formalités',     short: 'Formalités', ordre: 5, color: 'bg-orange-500', light: 'bg-orange-50',  text: 'text-orange-700',  border: 'border-orange-300' },
    expedition:        { label: 'Expédition',     short: 'Expédition', ordre: 6, color: 'bg-cyan-500',   light: 'bg-cyan-50',    text: 'text-cyan-700',    border: 'border-cyan-300' },
    cloture:           { label: 'Clôturés',       short: 'Clôturés',   ordre: 7, color: 'bg-green-500',  light: 'bg-green-50',   text: 'text-green-700',   border: 'border-green-300' },
};

const ETAPE_KEYS = ['initialisation', 'edition', 'revision', 'signature_client', 'signature_notaire', 'formalites', 'expedition', 'cloture'];

const JOURNAL_META = {
    creation:   { icon: FolderPlus,     color: 'text-blue-500',   bg: 'bg-blue-50'   },
    edition:    { icon: PenLine,        color: 'text-slate-500',  bg: 'bg-slate-100' },
    revision:   { icon: ClipboardCheck, color: 'text-amber-500',  bg: 'bg-amber-50'  },
    validation: { icon: CheckCircle2,   color: 'text-green-500',  bg: 'bg-green-50'  },
    signature:  { icon: PenLine,        color: 'text-purple-500', bg: 'bg-purple-50' },
    formalite:  { icon: Building2,      color: 'text-orange-500', bg: 'bg-orange-50' },
    expedition: { icon: Package,        color: 'text-cyan-500',   bg: 'bg-cyan-50'   },
    note:       { icon: MessageSquare,  color: 'text-slate-400',  bg: 'bg-slate-50'  },
};

/* ═══════════════════════════════════════════════════════════
   Sub-components
═══════════════════════════════════════════════════════════ */

function KpiCard({ label, value, icon: Icon, iconBg, iconColor, href, urgent, delay = 0 }) {
    return (
        <motion.div initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ delay }}>
            <Link href={href}>
                <Card className={cn('hover:shadow-md transition-all cursor-pointer group', urgent && 'ring-1 ring-warning/40')}>
                    <CardContent className="p-5">
                        <div className="flex items-start justify-between gap-2">
                            <div className={cn('h-10 w-10 rounded-xl flex items-center justify-center shrink-0', iconBg)}>
                                <Icon className={cn('h-5 w-5', iconColor)} />
                            </div>
                            {urgent && <span className="h-2 w-2 rounded-full bg-warning mt-1 shrink-0 animate-pulse" />}
                        </div>
                        <div className="mt-3">
                            <div className="text-2xl font-bold text-ink font-ref">{value}</div>
                            <div className="text-xs text-slate-500 mt-0.5 leading-tight">{label}</div>
                        </div>
                    </CardContent>
                </Card>
            </Link>
        </motion.div>
    );
}

function SecondaryKpi({ label, value, icon: Icon, color, href }) {
    return (
        <Link href={href}>
            <div className="flex items-center gap-2.5 bg-white border border-slate-200 rounded-xl px-4 py-2.5 hover:shadow-sm hover:border-slate-300 transition-all">
                <Icon className={cn('h-3.5 w-3.5 shrink-0', color)} />
                <span className={cn('text-base font-semibold tabular-nums', color)}>{value}</span>
                <span className="text-xs text-slate-500">{label}</span>
            </div>
        </Link>
    );
}

function EtapeDots({ ordre }) {
    return (
        <div className="flex gap-0.5 mt-1.5">
            {ETAPE_KEYS.slice(0, 7).map((_, i) => (
                <div key={i} className={`h-1 w-2 rounded-full transition-colors ${i <= ordre ? 'bg-seal' : 'bg-slate-200'}`} />
            ))}
        </div>
    );
}

/* ─── Pipeline ───────────────────────────────────────────── */

function PipelineEtapes({ parEtape, activeFilter, onFilter }) {
    const countMap = Object.fromEntries((parEtape ?? []).map(e => [e.etape, e.count]));

    return (
        <div className="flex items-stretch gap-1 overflow-x-auto pb-1">
            {ETAPE_KEYS.map((key, i) => {
                const meta = ETAPE_META[key];
                const count = countMap[key] ?? 0;
                const isActive = activeFilter === key;
                const hasNext = i < ETAPE_KEYS.length - 1;
                return (
                    <React.Fragment key={key}>
                        <button
                            onClick={() => onFilter(isActive ? null : key)}
                            className={cn(
                                'flex flex-col items-center gap-1.5 px-3 py-3 min-w-[90px] rounded-xl transition-all',
                                isActive
                                    ? `${meta.light} border-2 ${meta.border} shadow-sm`
                                    : 'bg-white border border-slate-200 hover:bg-slate-50',
                                !isActive && count === 0 && 'opacity-40'
                            )}
                        >
                            <div className={cn('text-xl font-bold tabular-nums', meta.text)}>{count}</div>
                            <div className={cn('h-0.5 w-full rounded-full', count > 0 ? meta.color : 'bg-slate-200')} />
                            <div className="text-[10px] text-slate-500 text-center leading-tight">{meta.short}</div>
                        </button>
                        {hasNext && (
                            <div className="self-center shrink-0 text-slate-300 text-xs">›</div>
                        )}
                    </React.Fragment>
                );
            })}
        </div>
    );
}

/* ─── Activity item ──────────────────────────────────────── */

function ActivityItem({ item }) {
    const meta = JOURNAL_META[item.type] ?? { icon: Activity, color: 'text-slate-400', bg: 'bg-slate-50' };
    const Icon = meta.icon;
    return (
        <div className="px-4 py-2.5 flex items-start gap-2.5 hover:bg-slate-50/50 transition-colors">
            <div className={cn('h-6 w-6 rounded-lg flex items-center justify-center shrink-0 mt-0.5', meta.bg)}>
                <Icon className={cn('h-3 w-3', meta.color)} />
            </div>
            <div className="flex-1 min-w-0">
                <p className="text-xs text-slate-700 leading-relaxed line-clamp-2">{item.action}</p>
                <div className="flex items-center gap-2 mt-0.5">
                    {item.dossier && (
                        <Link href={`/dossiers/${item.dossier}`} className="font-ref text-[10px] text-seal hover:underline">
                            {item.dossier}
                        </Link>
                    )}
                    <span className="text-[10px] text-slate-400">{item.created_at}</span>
                </div>
            </div>
            {item.user && (
                <div className="h-5 w-5 rounded-full bg-ink/10 text-ink flex items-center justify-center text-[8px] font-semibold shrink-0">
                    {item.user}
                </div>
            )}
        </div>
    );
}

/* ═══════════════════════════════════════════════════════════
   Page principale
═══════════════════════════════════════════════════════════ */

export default function Dashboard() {
    const { stats, fileAttente, alertesUrgentes, activiteRecente, parCategorie, parEtape = [], auth } = usePage().props;
    const can = auth?.user?.can ?? {};
    const prenom = auth?.user?.name?.split(' ')[0] ?? '';

    const [etapeFilter, setEtapeFilter] = useState(null);
    const [showRetard, setShowRetard]   = useState(false);

    const filteredDossiers = useMemo(() => (fileAttente ?? []).filter(d => {
        if (etapeFilter && d.etape?.value !== etapeFilter) return false;
        if (showRetard && !d.estEnRetard) return false;
        return true;
    }), [fileAttente, etapeFilter, showRetard]);

    const catMax = Math.max(...(parCategorie ?? []).map(c => c.total), 1);

    const now = new Date();
    const dateLabel = now.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

    return (
        <AppLayout breadcrumbs={[{ label: 'Tableau de bord' }]}>
            <Head title="Tableau de bord — Ayelema" />
            <div className="p-6 space-y-6 max-w-[1200px] mx-auto">

                {/* ── En-tête ── */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-display text-ink">
                            {prenom ? `Bonjour, ${prenom}` : 'Tableau de bord'}
                        </h1>
                        <p className="text-slate-500 text-sm mt-1 capitalize">{dateLabel}</p>
                    </div>
                    {can?.creerDossier && (
                        <Button variant="seal" size="sm" asChild>
                            <Link href="/dossiers/create">
                                <Plus className="h-3.5 w-3.5" />
                                Nouveau dossier
                            </Link>
                        </Button>
                    )}
                </div>

                {/* ── Alertes urgentes ── */}
                <AnimatePresence>
                    {alertesUrgentes?.length > 0 && (
                        <motion.div
                            initial={{ opacity: 0, height: 0 }}
                            animate={{ opacity: 1, height: 'auto' }}
                            exit={{ opacity: 0, height: 0 }}
                            className="flex items-start gap-3 px-4 py-3 rounded-xl bg-danger-bg border border-red-200 text-danger-text text-sm"
                        >
                            <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5 animate-pulse" />
                            <div className="space-y-1">
                                <span className="font-semibold">Échéances dépassées ou imminentes :</span>
                                {alertesUrgentes.map((a) => (
                                    <div key={a.reference} className="flex items-center gap-2 flex-wrap">
                                        <Link href={`/dossiers/${a.reference}`} className="font-ref text-seal hover:underline">{a.reference}</Link>
                                        <span className="text-slate-600">{a.objet}</span>
                                        <span className={cn('font-medium text-xs', a.estEnRetard ? 'text-red-600' : 'text-danger-text')}>
                                            {a.echeanceDiff}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </motion.div>
                    )}
                </AnimatePresence>

                {/* ── KPIs primaires ── */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <KpiCard
                        label="Dossiers en cours" value={stats?.enCours ?? 0}
                        icon={FolderOpen} iconBg="bg-slate-50" iconColor="text-ink"
                        href="/dossiers" delay={0.04}
                    />
                    <KpiCard
                        label="En attente de révision" value={stats?.enRevision ?? 0}
                        icon={ClipboardCheck} iconBg="bg-warning-bg" iconColor="text-warning"
                        href="/revisions" delay={0.08} urgent={(stats?.enRevision ?? 0) > 0}
                    />
                    <KpiCard
                        label="Échéances proches" value={stats?.echeancesProches ?? 0}
                        icon={Clock}
                        iconBg={(stats?.echeancesProches ?? 0) > 0 ? 'bg-danger-bg' : 'bg-slate-50'}
                        iconColor={(stats?.echeancesProches ?? 0) > 0 ? 'text-danger' : 'text-slate-500'}
                        href="/dossiers" delay={0.12} urgent={(stats?.echeancesProches ?? 0) > 0}
                    />
                    <KpiCard
                        label="Formalités urgentes" value={stats?.formalitesUrgentes ?? 0}
                        icon={Building2} iconBg="bg-orange-50" iconColor="text-orange-600"
                        href="/formalites" delay={0.16}
                    />
                </div>

                {/* ── Stats secondaires ── */}
                <motion.div
                    initial={{ opacity: 0, y: 4 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.18 }}
                    className="flex flex-wrap gap-2"
                >
                    <SecondaryKpi label="dossiers au total"    value={stats?.total ?? 0}              icon={LayoutGrid}   color="text-ink"       href="/dossiers" />
                    <SecondaryKpi label="créés ce mois"        value={stats?.ceMois ?? 0}             icon={CalendarDays} color="text-blue-600"  href="/dossiers" />
                    <SecondaryKpi label="clôturés"             value={stats?.clos ?? 0}               icon={Archive}      color="text-green-600" href="/dossiers?etape=cloture" />
                    <SecondaryKpi label="courriers en attente" value={stats?.courriersEnAttente ?? 0} icon={Mail}         color="text-slate-500" href="/courriers" />
                </motion.div>

                {/* ── Pipeline étapes ── */}
                {parEtape?.length > 0 && (
                    <motion.div initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.22 }}>
                        <Card>
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle>Pipeline des dossiers</CardTitle>
                                        <CardDescription className="mt-0.5">Distribution par étape · Cliquer pour filtrer la file d'attente</CardDescription>
                                    </div>
                                    {etapeFilter && (
                                        <button onClick={() => setEtapeFilter(null)}
                                            className="flex items-center gap-1 text-xs text-slate-400 hover:text-slate-600 transition-colors">
                                            <X className="h-3 w-3" /> Effacer le filtre
                                        </button>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent>
                                <PipelineEtapes parEtape={parEtape} activeFilter={etapeFilter} onFilter={setEtapeFilter} />
                            </CardContent>
                        </Card>
                    </motion.div>
                )}

                {/* ── Grille principale ── */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {/* File d'attente */}
                    <motion.div className="lg:col-span-2" initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.27 }}>
                        <Card className="h-full">
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between gap-2">
                                    <div>
                                        <CardTitle>File d'attente</CardTitle>
                                        <CardDescription className="mt-0.5">Dossiers en cours par ordre d'échéance</CardDescription>
                                    </div>
                                    <div className="flex items-center gap-2 shrink-0">
                                        <button
                                            onClick={() => setShowRetard(r => !r)}
                                            className={cn(
                                                'flex items-center gap-1.5 text-[11px] px-2.5 py-1 rounded-full border transition-all',
                                                showRetard
                                                    ? 'bg-red-50 text-red-600 border-red-200'
                                                    : 'bg-white text-slate-400 border-slate-200 hover:border-slate-300'
                                            )}
                                        >
                                            <Clock className="h-3 w-3" /> En retard
                                        </button>
                                        <Link href="/dossiers" className="text-xs text-seal hover:underline flex items-center gap-1">
                                            Voir tous <ChevronRight className="h-3 w-3" />
                                        </Link>
                                    </div>
                                </div>
                                {/* Indicateur de filtre actif */}
                                {(etapeFilter || showRetard) && (
                                    <div className="flex items-center gap-2 mt-2 text-xs text-slate-500">
                                        <Filter className="h-3 w-3" />
                                        <span>Filtre :</span>
                                        {etapeFilter && (
                                            <span className={cn('px-2 py-0.5 rounded-full text-[11px]', ETAPE_META[etapeFilter]?.light, ETAPE_META[etapeFilter]?.text)}>
                                                {ETAPE_META[etapeFilter]?.label}
                                            </span>
                                        )}
                                        {showRetard && (
                                            <span className="px-2 py-0.5 rounded-full text-[11px] bg-red-50 text-red-600">En retard</span>
                                        )}
                                    </div>
                                )}
                            </CardHeader>
                            <CardContent className="px-0 pb-0">
                                {filteredDossiers.length === 0 ? (
                                    <div className="px-6 py-10 text-center text-sm text-slate-400">
                                        {etapeFilter || showRetard ? 'Aucun dossier pour ce filtre' : 'Aucun dossier en cours'}
                                    </div>
                                ) : (
                                    <div className="divide-y divide-slate-50">
                                        {filteredDossiers.map((d) => {
                                            const em = ETAPE_META[d.etape?.value] ?? {};
                                            return (
                                                <Link
                                                    key={d.reference}
                                                    href={`/dossiers/${d.reference}`}
                                                    className="flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50/60 transition-colors group"
                                                >
                                                    {/* Pastille étape */}
                                                    <div className={cn('h-2.5 w-2.5 rounded-full shrink-0 mt-1', em.color ?? 'bg-slate-300')} />

                                                    <div className="flex-1 min-w-0">
                                                        <div className="flex items-center gap-2 flex-wrap">
                                                            <span className="font-ref text-xs text-seal">{d.reference}</span>
                                                            <span className={cn('text-[10px] font-medium px-1.5 py-0.5 rounded-full', em.light, em.text)}>
                                                                {d.etape?.label}
                                                            </span>
                                                            {d.estEnRetard && (
                                                                <span className="text-[10px] font-medium px-1.5 py-0.5 rounded-full bg-red-50 text-red-600">
                                                                    En retard
                                                                </span>
                                                            )}
                                                        </div>
                                                        <div className="text-sm text-slate-700 truncate mt-0.5">{d.objet}</div>
                                                        <div className="flex items-center gap-3 mt-0.5">
                                                            {d.typeActe && <span className="text-xs text-slate-400">{d.typeActe}</span>}
                                                            {d.echeanceDiff && (
                                                                <span className={cn('text-xs flex items-center gap-1', d.estEnRetard ? 'text-red-500' : 'text-slate-400')}>
                                                                    <Clock className="h-2.5 w-2.5" />
                                                                    {d.echeanceDiff}
                                                                </span>
                                                            )}
                                                        </div>
                                                        <EtapeDots ordre={d.etapeOrdre ?? 0} />
                                                    </div>

                                                    <div className="flex items-center gap-2 shrink-0">
                                                        {d.redacteur && (
                                                            <div className="h-6 w-6 rounded-full bg-ink text-white flex items-center justify-center text-[9px] font-semibold">
                                                                {d.redacteur}
                                                            </div>
                                                        )}
                                                        <ChevronRight className="h-3.5 w-3.5 text-slate-300 group-hover:text-seal transition-colors" />
                                                    </div>
                                                </Link>
                                            );
                                        })}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </motion.div>

                    {/* Activité récente */}
                    <motion.div initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.32 }}>
                        <Card className="h-full">
                            <CardHeader className="pb-3">
                                <CardTitle>Activité récente</CardTitle>
                            </CardHeader>
                            <CardContent className="px-0 pb-2">
                                {(!activiteRecente || activiteRecente.length === 0) ? (
                                    <div className="px-6 py-10 text-center text-sm text-slate-400">Aucune activité récente</div>
                                ) : (
                                    <div className="divide-y divide-slate-50">
                                        {activiteRecente.slice(0, 10).map((a, i) => (
                                            <ActivityItem key={i} item={a} />
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </motion.div>
                </div>

                {/* ── Répartition catégories ── */}
                {parCategorie?.length > 0 && (
                    <motion.div initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.37 }}>
                        <Card>
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle>Répartition par catégorie</CardTitle>
                                        <CardDescription className="mt-0.5">Tous les dossiers, toutes étapes</CardDescription>
                                    </div>
                                    <Link href="/dossiers" className="text-xs text-seal hover:underline flex items-center gap-1">
                                        Voir tous <ChevronRight className="h-3 w-3" />
                                    </Link>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1.5">
                                    {parCategorie.map((cat, i) => (
                                        <Link key={cat.categorie} href={`/dossiers?categorie=${cat.categorie}`}
                                            className="flex items-center gap-3 group hover:bg-slate-50 rounded-lg px-2 py-1.5 transition-colors">
                                            <span className="text-xs text-slate-600 w-32 shrink-0 truncate group-hover:text-seal transition-colors">
                                                {cat.label ?? cat.categorie}
                                            </span>
                                            <div className="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                                <motion.div
                                                    className="h-full bg-seal rounded-full"
                                                    initial={{ width: 0 }}
                                                    animate={{ width: `${(cat.total / catMax) * 100}%` }}
                                                    transition={{ duration: 0.6, delay: 0.4 + i * 0.04 }}
                                                />
                                            </div>
                                            <span className="text-xs font-semibold text-ink tabular-nums w-5 text-right">{cat.total}</span>
                                        </Link>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </motion.div>
                )}

            </div>
        </AppLayout>
    );
}
