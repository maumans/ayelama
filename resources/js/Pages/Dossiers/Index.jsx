import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Plus, Search, FolderOpen, Clock, AlertTriangle, ChevronRight,
    ChevronLeft, X, TrendingUp, CheckCircle2, ArrowUpDown, Zap,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { notifyValidationError } from '@/lib/toast';

// ── Métadonnées par étape ──────────────────────────────────────────────────

const ETAPE_META = {
    initialisation: { dot: 'bg-slate-400',  badge: 'bg-slate-100 text-slate-600 border-slate-200',  bar: 'bg-slate-300'  },
    edition:        { dot: 'bg-blue-400',   badge: 'bg-blue-50 text-blue-700 border-blue-200',      bar: 'bg-blue-300'   },
    revision:       { dot: 'bg-amber-400',  badge: 'bg-amber-50 text-amber-700 border-amber-200',   bar: 'bg-amber-300'  },
    formalites:     { dot: 'bg-orange-400', badge: 'bg-orange-50 text-orange-700 border-orange-200',bar: 'bg-orange-300' },
    expedition:     { dot: 'bg-cyan-400',   badge: 'bg-cyan-50 text-cyan-700 border-cyan-200',      bar: 'bg-cyan-300'   },
    cloture:        { dot: 'bg-green-500',  badge: 'bg-green-50 text-green-700 border-green-200',   bar: 'bg-green-400'  },
};

const ETAPE_ORDER = ['initialisation', 'edition', 'revision', 'formalites', 'expedition', 'cloture'];

// ── Barre de progression workflow ──────────────────────────────────────────

function WorkflowDots({ etapeValue }) {
    const current = ETAPE_ORDER.indexOf(etapeValue);
    return (
        <div className="flex items-center gap-[3px]">
            {ETAPE_ORDER.map((e, i) => (
                <div key={e} className={cn(
                    'rounded-full transition-all',
                    i < current   ? 'h-1.5 w-1.5 bg-success' :
                    i === current ? 'h-2 w-2 ' + (ETAPE_META[e]?.dot ?? 'bg-slate-400') :
                                    'h-1.5 w-1.5 bg-slate-200'
                )} />
            ))}
        </div>
    );
}

// ── Page principale ────────────────────────────────────────────────────────

export default function DossiersIndex() {
    const { dossiers, filters, etapes, categories, stats, auth } = usePage().props;
    const can = auth?.user?.can ?? {};

    const [search, setSearch]       = useState(filters.q         ?? '');
    const [etape, setEtape]         = useState(filters.etape      ?? '');
    const [categorie, setCategorie] = useState(filters.categorie  ?? '');
    const [retard, setRetard]       = useState(filters.retard     === '1');
    const [sortBy, setSortBy]       = useState(filters.sort       ?? '');

    const applyFilters = (overrides = {}) => {
        const vals = {
            q:         overrides.q         !== undefined ? overrides.q         : search,
            etape:     overrides.etape      !== undefined ? overrides.etape      : etape,
            categorie: overrides.categorie  !== undefined ? overrides.categorie  : categorie,
            retard:    overrides.retard     !== undefined ? (overrides.retard ? '1' : '') : (retard ? '1' : ''),
            sort:      overrides.sort       !== undefined ? overrides.sort       : sortBy,
        };
        const params = Object.fromEntries(Object.entries(vals).filter(([, v]) => v));
        router.get('/dossiers', params, { preserveState: true, replace: true });
    };

    const resetFiltres = () => {
        setSearch(''); setEtape(''); setCategorie(''); setRetard(false); setSortBy('');
        router.get('/dossiers', {}, { preserveState: false, replace: true });
    };

    const hasFiltres = search || etape || categorie || retard || sortBy;

    const dossiersData = dossiers?.data ?? [];
    const totalPages   = dossiers?.last_page ?? 1;
    const currentPage  = dossiers?.current_page ?? 1;
    const totalItems   = dossiers?.total ?? 0;

    return (
        <AppLayout breadcrumbs={[{ label: 'Dossiers' }]}>
            <Head title="Dossiers — Ayelema" />

            <div className="p-6 max-w-[1200px] mx-auto space-y-5">

                {/* ── En-tête ──────────────────────────────────────────────── */}
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-display text-ink">Dossiers</h1>
                        <p className="text-sm text-slate-500 mt-1">
                            <span className="text-slate-700 font-medium">{stats?.total ?? 0}</span> dossier{(stats?.total ?? 0) > 1 ? 's' : ''}{' '}·{' '}
                            <span className="text-blue-600">{stats?.enCours ?? 0} en cours</span>
                            {(stats?.enRetard ?? 0) > 0 && (
                                <> · <span className="text-danger font-medium">{stats.enRetard} en retard</span></>
                            )}
                        </p>
                    </div>
                    {can.creerDossier && (
                        <Button variant="seal" asChild>
                            <Link href="/dossiers/create">
                                <Plus className="h-4 w-4" />
                                Nouveau dossier
                            </Link>
                        </Button>
                    )}
                </div>

                {/* ── Stats ─────────────────────────────────────────────────── */}
                <div className="grid grid-cols-2 lg:grid-cols-5 gap-3">
                    {[
                        { label: 'Total',           value: stats?.total        ?? 0, color: 'text-ink',      Icon: FolderOpen    },
                        { label: 'En cours',         value: stats?.enCours      ?? 0, color: 'text-blue-600', Icon: TrendingUp    },
                        { label: 'En retard',        value: stats?.enRetard     ?? 0, color: (stats?.enRetard ?? 0) > 0 ? 'text-danger' : 'text-slate-400', Icon: AlertTriangle },
                        { label: 'Clôturés ce mois', value: stats?.cloturesMois ?? 0, color: 'text-success',  Icon: CheckCircle2  },
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

                    {/* Répartition par étape */}
                    <Card className="p-3">
                        <div className="text-[10px] text-slate-400 uppercase tracking-wider mb-2">Répartition</div>
                        <div className="flex gap-px h-4 rounded overflow-hidden bg-slate-100">
                            {(stats?.parEtape ?? [])
                                .filter(e => e.count > 0)
                                .map(e => {
                                    const total = Math.max(stats?.total ?? 1, 1);
                                    const pct = Math.max(3, Math.round((e.count / total) * 100));
                                    return (
                                        <div
                                            key={e.value}
                                            title={`${e.label} : ${e.count}`}
                                            className={cn('h-full', ETAPE_META[e.value]?.bar ?? 'bg-slate-200')}
                                            style={{ width: `${pct}%` }}
                                        />
                                    );
                                })}
                        </div>
                        <div className="flex flex-wrap gap-x-2 gap-y-0.5 mt-1.5">
                            {(stats?.parEtape ?? []).filter(e => e.count > 0).map(e => (
                                <span key={e.value} className="text-[9px] text-slate-400 leading-tight">
                                    <span className={cn('inline-block h-1.5 w-1.5 rounded-full mr-0.5 align-middle', ETAPE_META[e.value]?.dot ?? 'bg-slate-300')} />
                                    {e.label.split(' ')[0]} {e.count}
                                </span>
                            ))}
                        </div>
                    </Card>
                </div>

                {/* ── Filtres ───────────────────────────────────────────────── */}
                <div className="flex flex-wrap gap-2 items-center">
                    <div className="relative flex-1 min-w-[180px] max-w-sm">
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

                    <Select value={etape} onValueChange={v => { setEtape(v); applyFilters({ etape: v }); }}>
                        <SelectTrigger className="h-8 text-sm w-44">
                            <SelectValue placeholder="Toutes les étapes" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Toutes les étapes</SelectItem>
                            {(etapes ?? []).map(e => (
                                <SelectItem key={e.value} value={e.value}>{e.label}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={categorie} onValueChange={v => { setCategorie(v); applyFilters({ categorie: v }); }}>
                        <SelectTrigger className="h-8 text-sm w-40">
                            <SelectValue placeholder="Toutes catégories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Toutes catégories</SelectItem>
                            {(categories ?? []).map(c => (
                                <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={sortBy} onValueChange={v => { setSortBy(v); applyFilters({ sort: v }); }}>
                        <SelectTrigger className="h-8 text-sm w-40">
                            <ArrowUpDown className="h-3.5 w-3.5 mr-1 text-slate-400 shrink-0" />
                            <SelectValue placeholder="Trier par" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Récents d'abord</SelectItem>
                            <SelectItem value="echeance">Par échéance</SelectItem>
                            <SelectItem value="reference">Par référence</SelectItem>
                            <SelectItem value="valeur">Par valeur</SelectItem>
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
                                : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300 hover:text-slate-700'
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
                        <FolderOpen className="h-12 w-12 text-slate-200 mb-4" />
                        <h3 className="font-serif text-heading text-slate-500">Aucun dossier trouvé</h3>
                        <p className="text-sm text-slate-400 mt-1 mb-4">
                            {hasFiltres ? 'Élargissez vos critères de recherche.' : 'Créez votre premier dossier.'}
                        </p>
                        {can.creerDossier && !hasFiltres && (
                            <Button variant="seal" asChild>
                                <Link href="/dossiers/create">
                                    <Plus className="h-4 w-4" /> Créer un dossier
                                </Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="space-y-2">
                        {dossiersData.map((d, i) => {
                            const meta = ETAPE_META[d.etape?.value] ?? {};
                            return (
                                <motion.div
                                    key={d.id}
                                    initial={{ opacity: 0, y: 5 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ delay: i * 0.03 }}
                                >
                                    <Card
                                        className={cn(
                                            'group hover:shadow-md transition-all cursor-pointer border-l-2',
                                            d.estEnRetard
                                                ? 'border-l-danger'
                                                : 'border-l-transparent hover:border-l-seal'
                                        )}
                                        onClick={() => router.visit(`/dossiers/${d.reference}`)}
                                    >
                                        <CardContent className="p-4">
                                            <div className="flex items-start gap-4">
                                                <div className="flex-1 min-w-0">

                                                    {/* Ligne 1 : référence + badges */}
                                                    <div className="flex items-center gap-2 flex-wrap">
                                                        <span className="font-ref text-sm text-seal font-semibold">{d.reference}</span>
                                                        <span className={cn(
                                                            'inline-flex items-center text-[10px] font-medium px-2 py-0.5 rounded-full border',
                                                            meta.badge ?? 'bg-slate-100 text-slate-500 border-slate-200'
                                                        )}>
                                                            {d.etape?.label}
                                                        </span>
                                                        {d.estEnRetard && (
                                                            <span className="inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-full bg-danger-bg text-danger-text border border-red-200">
                                                                <AlertTriangle className="h-2.5 w-2.5" />
                                                                En retard
                                                            </span>
                                                        )}
                                                        {d.urgent && (
                                                            <span className="inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-full bg-warning-bg text-warning-text border border-amber-200">
                                                                <Zap className="h-2.5 w-2.5" />
                                                                Urgent
                                                            </span>
                                                        )}
                                                    </div>

                                                    {/* Ligne 2 : objet */}
                                                    <p className="text-sm font-medium text-slate-800 mt-1 truncate">{d.objet}</p>

                                                    {/* Ligne 3 : métadonnées */}
                                                    <div className="flex items-center gap-2 mt-1 flex-wrap">
                                                        {d.typeActe && (
                                                            <span className="text-[10px] bg-slate-50 border border-slate-200 rounded-full px-2 py-0.5 text-slate-500">
                                                                {d.typeActe.label}
                                                            </span>
                                                        )}
                                                        <div className="flex items-center gap-2 text-xs text-slate-400">
                                                            {d.redacteur && <span>{d.redacteur.initiales}</span>}
                                                            {d.notaire   && <span>· {d.notaire.initiales}</span>}
                                                            {d.echeance  && (
                                                                <span className={cn(
                                                                    'flex items-center gap-1',
                                                                    d.estEnRetard && 'text-danger font-medium'
                                                                )}>
                                                                    <Clock className="h-2.5 w-2.5" />
                                                                    {d.echeance}
                                                                </span>
                                                            )}
                                                            {(d.valeur ?? 0) > 0 && (
                                                                <span className="font-ref">
                                                                    {Number(d.valeur).toLocaleString('fr-GN')} GNF
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>

                                                    {/* Ligne 4 : barre workflow */}
                                                    <div className="flex items-center gap-2 mt-2">
                                                        <WorkflowDots etapeValue={d.etape?.value} />
                                                        <span className="text-[9px] text-slate-300 font-ref">
                                                            {(d.etapeOrdre ?? 0) + 1}/8
                                                        </span>
                                                    </div>
                                                </div>

                                                {/* Actions droite */}
                                                <div className="flex items-center gap-2 shrink-0 self-center">
                                                    <span className="text-xs text-slate-300 hidden lg:block">{d.updated_at}</span>

                                                    {d.canAvancer && d.etape?.value !== 'cloture' && (
                                                        <button
                                                            disabled={!d.peutAvancer}
                                                            onClick={e => {
                                                                e.stopPropagation();
                                                                if (!d.peutAvancer) {
                                                                    router.visit(`/dossiers/${d.reference}`);
                                                                    return;
                                                                }
                                                                router.post(
                                                                    `/dossiers/${d.reference}/avancer`,
                                                                    {},
                                                                    {
                                                                        preserveScroll: true,
                                                                        onError: (errors) => {
                                                                            notifyValidationError(errors);
                                                                            router.visit(`/dossiers/${d.reference}`);
                                                                        },
                                                                    }
                                                                );
                                                            }}
                                                            title={!d.peutAvancer ? 'Des conditions doivent être remplies — cliquer pour voir le détail' : 'Avancer à l\'étape suivante'}
                                                            className={cn(
                                                                'flex items-center gap-1 text-[11px] font-medium rounded-md px-2 py-1 transition-colors',
                                                                d.peutAvancer
                                                                    ? 'text-seal border border-seal/30 bg-seal/5 hover:bg-seal/15'
                                                                    : 'text-slate-400 border border-slate-200 bg-slate-50 cursor-pointer'
                                                            )}
                                                        >
                                                            <Zap className="h-3 w-3" />
                                                            Avancer
                                                        </button>
                                                    )}

                                                    <ChevronRight className="h-4 w-4 text-slate-200 group-hover:text-seal transition-colors" />
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
                            Page {currentPage} sur {totalPages} — {totalItems} dossiers
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
