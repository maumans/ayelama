import { useState, useCallback } from 'react';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Head, router } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import { notifyValidationError } from '@/lib/toast';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Tooltip, TooltipContent, TooltipTrigger,
} from '@/components/ui/tooltip';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    AlertCircle, Banknote, Building2, Check, CheckCheck,
    Clock, FileText, Filter, Flame, MailCheck,
    Package, Search, Upload, X, ArrowUpDown, Lock,
    LayoutGrid, Table2, Download, ChevronRight,
} from 'lucide-react';
import { STATUT_META, organismeBadgeClass, organismeShortLabel } from '@/data/formaliteStatuts';
import { ModalDepotFormalite } from '@/Components/Formalites/ModalDepotFormalite';
import { ModalRetourFormalite } from '@/Components/Formalites/ModalRetourFormalite';
import { PieceGedRow } from '@/Components/Formalites/PieceGedRow';

/* ─── constants ─────────────────────────────────────────── */

const fmt = (n) => n ? Number(n).toLocaleString('fr-FR') : '0';

const ORGANISMES_ONGLETS = ['apip', 'impots', 'conservation_fonciere', 'cnss', 'greffe'];

function formatDelai(f) {
    if (f.joursRetardOuAvance == null) return { text: '—', cls: 'text-slate-400' };
    const j = f.joursRetardOuAvance;
    const termine = f.statut === 'retour_recu' || f.statut === 'cloture';

    if (termine) {
        return j <= 0
            ? { text: `${Math.abs(j)}j ✓`, cls: 'text-success font-medium' }
            : { text: `+${j}j`, cls: 'text-danger font-medium' };
    }

    return j > 0
        ? { text: `+${j}j`, cls: 'text-danger font-medium' }
        : { text: `${Math.abs(j)}j`, cls: 'text-slate-500' };
}

/* ─── FormaliteCard ──────────────────────────────────────── */

function FormaliteCard({ formalite, showDossier = true }) {
    const [showPieces, setShowPieces] = useState(false);
    const [confirmState, setConfirmState] = useState(null);
    const [depotOpen, setDepotOpen] = useState(false);
    const [retourOpen, setRetourOpen] = useState(false);
    const meta = STATUT_META[formalite.statut] ?? STATUT_META.a_deposer;
    const pieces  = formalite.pieces ?? [];
    const fournis = pieces.filter(p => p.est_fourni).length;

    const patch = (data) =>
        router.patch(`/formalites/${formalite.id}`, data, { preserveScroll: true, onError: notifyValidationError });

    const handleCloture = () => setConfirmState({
        title: 'Clôturer cette formalité ?',
        description: 'La formalité sera marquée comme clôturée.',
        confirmLabel: 'Clôturer',
        variant: 'default',
        onConfirm: () => patch({ statut: 'cloture' }),
    });
    const peutGerer = !!formalite.peutGerer;
    const peutDeposer = formalite.statut === 'a_deposer' || formalite.statut === 'rejete';

    const handleToggle = (p) => peutGerer && patch({ pieces: [{ id: p.id, est_fourni: !p.est_fourni }] });

    const isUrgente  = formalite.estUrgente;
    const isDepassee = formalite.estDepassee;
    const isCloture  = formalite.statut === 'cloture';

    const borderClass = isDepassee
        ? 'border-l-danger'
        : isUrgente ? 'border-l-amber-400'
        : meta.border;

    return (
        <>
        <ConfirmDialog
            open={!!confirmState}
            onClose={() => setConfirmState(null)}
            title={confirmState?.title ?? ''}
            description={confirmState?.description}
            confirmLabel={confirmState?.confirmLabel}
            variant={confirmState?.variant}
            onConfirm={confirmState?.onConfirm ?? (() => {})}
        />
        <ModalDepotFormalite open={depotOpen} onClose={() => setDepotOpen(false)} formalite={formalite} onTogglePiece={handleToggle} />
        <ModalRetourFormalite open={retourOpen} onClose={() => setRetourOpen(false)} formalite={formalite} />
        <motion.div
            layout
            initial={{ opacity: 0, y: 6 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -4 }}
            className={`bg-white rounded-lg border border-slate-200 border-l-4 shadow-sm ${borderClass} ${isCloture ? 'opacity-55' : ''}`}
        >
            {/* row 1 — organisme + badge */}
            <div className="flex items-center justify-between gap-2 px-4 pt-3 pb-1">
                <div className="flex items-center gap-2 min-w-0">
                    {formalite.ordre && <span className="text-[10px] text-slate-400 font-ref shrink-0">#{formalite.ordre}</span>}
                    <span className={cn('text-[10px] font-medium px-1.5 py-0.5 rounded border shrink-0', organismeBadgeClass(formalite.organisme))}>
                        {organismeShortLabel(formalite.organisme, formalite.organismeLabel)}
                    </span>
                    <span className="text-sm font-medium text-ink truncate">{formalite.libelle || formalite.organismeLabel}</span>
                    {(isDepassee || isUrgente) && (
                        <Flame className={`h-3.5 w-3.5 shrink-0 ${isDepassee ? 'text-danger' : 'text-amber-500'}`} />
                    )}
                </div>
                <Badge className={`text-[10px] px-1.5 py-0 shrink-0 border ${meta.badge}`}>
                    {formalite.estBloquee ? 'Bloqué' : meta.label}
                </Badge>
            </div>
            {formalite.estBloquee && (
                <div className="px-4 pb-1 -mt-0.5 text-[11px] text-slate-500 flex items-center gap-1">
                    <Lock className="h-3 w-3" /> Attend : {formalite.dependDeLabel}
                </div>
            )}

            {/* row 2 — montant + dates */}
            <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 px-4 py-1">
                {formalite.montant_calcule > 0 && (
                    <span className="text-xs text-slate-500 flex items-center gap-1">
                        <Banknote className="h-3 w-3" />
                        {fmt(formalite.montant_calcule)} GNF
                    </span>
                )}
                {formalite.echeance_at && (
                    <span className={`text-xs flex items-center gap-1 ${
                        isDepassee ? 'text-danger font-medium'
                        : isUrgente ? 'text-amber-600'
                        : 'text-slate-400'
                    }`}>
                        <Clock className="h-3 w-3" />
                        {isDepassee
                            ? 'Délai dépassé'
                            : isUrgente
                                ? `${formalite.heuresRestantes}h restantes`
                                : new Date(formalite.echeance_at).toLocaleDateString('fr-FR')}
                    </span>
                )}
                {formalite.depose_at && (
                    <span className="text-xs text-slate-400 flex items-center gap-1">
                        <Upload className="h-3 w-3" />
                        déposé le {formalite.depose_at}
                    </span>
                )}
                {formalite.retour_at && (
                    <span className="text-xs text-slate-400 flex items-center gap-1">
                        <MailCheck className="h-3 w-3" />
                        retour le {formalite.retour_at}
                    </span>
                )}
            </div>

            {/* row 3 — pièces */}
            {pieces.length > 0 && (
                <div className="px-4 py-1.5">
                    <button
                        onClick={() => setShowPieces(v => !v)}
                        className="flex items-center gap-2 w-full text-left group"
                    >
                        <Package className="h-3 w-3 text-slate-400 shrink-0" />
                        <span className="text-xs text-slate-500 group-hover:text-ink transition-colors">
                            {fournis}/{pieces.length} pièce{pieces.length > 1 ? 's' : ''}
                        </span>
                        <div className="flex-1">
                            <Progress value={(fournis / pieces.length) * 100} className="h-1" />
                        </div>
                        <span className="text-[10px] text-slate-400 shrink-0">{showPieces ? '▲' : '▼'}</span>
                    </button>
                    <AnimatePresence>
                        {showPieces && (
                            <motion.div
                                initial={{ height: 0, opacity: 0 }}
                                animate={{ height: 'auto', opacity: 1 }}
                                exit={{ height: 0, opacity: 0 }}
                                className="overflow-hidden mt-1.5 divide-y divide-slate-50"
                            >
                                {pieces.map(p => (
                                    <PieceGedRow key={p.id} piece={p} peutGerer={peutGerer} onToggle={handleToggle} />
                                ))}
                            </motion.div>
                        )}
                    </AnimatePresence>
                </div>
            )}

            {/* row 4 — actions */}
            <div className="flex items-center gap-2 px-4 pb-3 pt-2 border-t border-slate-100 mt-1">
                {peutGerer && peutDeposer && !formalite.estBloquee && (
                    <Button size="sm" variant="seal" onClick={() => setDepotOpen(true)} className="h-7 text-xs gap-1">
                        <Upload className="h-3.5 w-3.5" /> {formalite.statut === 'rejete' ? 'Redéposer' : 'Préparer dépôt'}
                    </Button>
                )}
                {peutGerer && (formalite.statut === 'depose' || formalite.statut === 'en_attente') && (
                    <Button
                        size="sm"
                        variant={isDepassee ? 'destructive' : 'success'}
                        onClick={() => setRetourOpen(true)}
                        className="h-7 text-xs gap-1"
                    >
                        <MailCheck className="h-3.5 w-3.5" /> Enregistrer un retour
                    </Button>
                )}
                {peutGerer && formalite.statut === 'retour_recu' && (
                    <Button
                        size="sm" variant="outline" onClick={handleCloture}
                        className="h-7 text-xs gap-1 border-green-300 text-green-700 hover:bg-green-50"
                    >
                        <CheckCheck className="h-3.5 w-3.5" /> Clôturer
                    </Button>
                )}
                {isCloture && (
                    <span className="flex items-center gap-1 text-xs text-slate-400">
                        <Check className="h-3.5 w-3.5" /> Clôturée
                    </span>
                )}
                <div className="flex-1" />
                {showDossier && formalite.dossier?.reference && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                onClick={() => router.visit(`/dossiers/${formalite.dossier.reference}?tab=formalites`)}
                                className="text-[10px] text-slate-400 hover:text-seal transition-colors flex items-center gap-1"
                            >
                                <FileText className="h-3 w-3" />
                                {formalite.dossier.reference}
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p className="text-xs">{formalite.dossier.objet}</p>
                        </TooltipContent>
                    </Tooltip>
                )}
            </div>
        </motion.div>
        </>
    );
}

/* ─── FormalitesTable (vue tableau, maquette "Tableau de bord Formaliste") ── */

function FormaliteTableRow({ formalite }) {
    const [confirmState, setConfirmState] = useState(null);
    const [depotOpen, setDepotOpen] = useState(false);
    const [retourOpen, setRetourOpen] = useState(false);
    const meta = STATUT_META[formalite.statut] ?? STATUT_META.a_deposer;
    const peutGerer = !!formalite.peutGerer;
    const peutDeposer = formalite.statut === 'a_deposer' || formalite.statut === 'rejete';
    const isDepassee = formalite.estDepassee;
    const isCloture = formalite.statut === 'cloture';
    const delai = formatDelai(formalite);

    const patch = (data) =>
        router.patch(`/formalites/${formalite.id}`, data, { preserveScroll: true, onError: notifyValidationError });

    const handleCloture = () => setConfirmState({
        title: 'Clôturer cette formalité ?',
        description: 'La formalité sera marquée comme clôturée.',
        confirmLabel: 'Clôturer',
        variant: 'default',
        onConfirm: () => patch({ statut: 'cloture' }),
    });

    return (
        <tr className={isCloture ? 'opacity-50' : ''}>
            <ConfirmDialog
                open={!!confirmState}
                onClose={() => setConfirmState(null)}
                title={confirmState?.title ?? ''}
                description={confirmState?.description}
                confirmLabel={confirmState?.confirmLabel}
                variant={confirmState?.variant}
                onConfirm={confirmState?.onConfirm ?? (() => {})}
            />
            <ModalDepotFormalite open={depotOpen} onClose={() => setDepotOpen(false)} formalite={formalite} />
            <ModalRetourFormalite open={retourOpen} onClose={() => setRetourOpen(false)} formalite={formalite} />
            <td>
                <button
                    onClick={() => router.visit(`/dossiers/${formalite.dossier.reference}?tab=formalites`)}
                    className="font-ref text-sm text-seal hover:underline"
                >
                    {formalite.dossier?.reference}
                </button>
            </td>
            <td className="text-sm text-slate-600">{formalite.dossier?.clientPrincipal ?? '—'}</td>
            <td>
                <span className={cn('inline-flex text-[10px] font-medium px-2 py-0.5 rounded border', organismeBadgeClass(formalite.organisme))}>
                    {organismeShortLabel(formalite.organisme, formalite.organismeLabel)}
                </span>
            </td>
            <td className="text-sm text-slate-700">{formalite.libelle}</td>
            <td>
                <span className={cn('inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border', meta.badge)}>
                    {formalite.estBloquee ? 'Bloqué' : meta.label}
                </span>
            </td>
            <td className="font-ref text-sm text-slate-700">
                {formalite.montant_calcule > 0 ? `${fmt(formalite.montant_calcule)} ${formalite.montant_paye != null ? '✓' : 'est.'}` : '—'}
            </td>
            <td className={cn('text-sm font-ref', delai.cls)}>{delai.text}</td>
            <td>
                {formalite.estBloquee ? (
                    <span className="text-xs text-slate-400 flex items-center gap-1"><Lock className="h-3 w-3" /> Attend {formalite.dependDeLabel}</span>
                ) : peutGerer && peutDeposer ? (
                    <Button size="sm" variant="seal" className="h-7 text-xs" onClick={() => setDepotOpen(true)}>
                        {formalite.statut === 'rejete' ? 'Redéposer' : 'Préparer dépôt'}
                    </Button>
                ) : peutGerer && (formalite.statut === 'depose' || formalite.statut === 'en_attente') ? (
                    <Button size="sm" variant={isDepassee ? 'destructive' : 'success'} className="h-7 text-xs" onClick={() => setRetourOpen(true)}>
                        Retour
                    </Button>
                ) : peutGerer && formalite.statut === 'retour_recu' ? (
                    <Button size="sm" variant="outline" className="h-7 text-xs border-green-300 text-green-700 hover:bg-green-50" onClick={handleCloture}>
                        Clôturer
                    </Button>
                ) : (
                    <button
                        onClick={() => router.visit(`/dossiers/${formalite.dossier.reference}?tab=formalites`)}
                        className="text-xs text-slate-400 hover:text-seal flex items-center gap-0.5"
                    >
                        Voir dossier <ChevronRight className="h-3 w-3" />
                    </button>
                )}
            </td>
        </tr>
    );
}

function FormalitesTable({ items }) {
    return (
        <div className="overflow-x-auto rounded-lg border border-slate-200">
            <table className="table-notarial w-full">
                <thead>
                    <tr>
                        <th>Dossier</th>
                        <th>Client</th>
                        <th>Organisme</th>
                        <th>Démarche</th>
                        <th>Statut</th>
                        <th>Frais</th>
                        <th>Délai</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    {items.map(f => <FormaliteTableRow key={f.id} formalite={f} />)}
                </tbody>
            </table>
        </div>
    );
}

/* ─── Page ───────────────────────────────────────────────── */

export default function FormalitesIndex({ formalites, stats, statuts, filters: init }) {
    const [vue, setVue] = useState('cartes');
    const [filters, setFilters] = useState({
        q:         init?.q         ?? '',
        statut:    init?.statut    ?? '',
        organisme: init?.organisme ?? '',
        urgentes:  init?.urgentes  ?? '',
        sort:      init?.sort      ?? '',
    });

    const apply = useCallback((vals) => {
        const clean = Object.fromEntries(Object.entries(vals).filter(([, v]) => v));
        router.get('/formalites', clean, { preserveState: true, replace: true });
    }, []);

    const setF = (key, val) => {
        const next = { ...filters, [key]: val };
        setFilters(next);
        apply(next);
    };

    const reset = () => {
        const cleared = { q: '', statut: '', organisme: '', urgentes: '', sort: '' };
        setFilters(cleared);
        router.get('/formalites', {}, { preserveState: true, replace: true });
    };

    const hasFilters = filters.q || filters.statut || filters.organisme || filters.urgentes || filters.sort;

    const exportHref = '/formalites/export.csv?' + new URLSearchParams(
        Object.fromEntries(Object.entries(filters).filter(([, v]) => v))
    ).toString();

    const data = formalites?.data ?? [];

    /* group by dossier */
    const grouped = data.reduce((acc, f) => {
        const ref = f.dossier?.reference ?? '—';
        if (!acc[ref]) acc[ref] = { meta: f.dossier, items: [] };
        acc[ref].items.push(f);
        return acc;
    }, {});

    const urgentes = data.filter(f => f.estUrgente || f.estDepassee);

    return (
        <AppLayout breadcrumbs={[{ label: 'Formalités' }]}>
            <Head title="Formalités — Ayelema" />

            <div className="p-6 max-w-5xl mx-auto space-y-5">

                {/* header */}
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 className="font-serif text-display text-ink">Formalités administratives</h1>
                        <p className="text-sm text-slate-500 mt-1">
                            {stats?.total ?? 0} démarche{(stats?.total ?? 0) !== 1 ? 's' : ''} en cours
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <div className="flex items-center rounded-lg border border-slate-200 p-0.5">
                            <button
                                onClick={() => setVue('cartes')}
                                className={cn('flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-colors',
                                    vue === 'cartes' ? 'bg-ink text-white' : 'text-slate-500 hover:bg-slate-50')}
                            >
                                <LayoutGrid className="h-3.5 w-3.5" /> Cartes
                            </button>
                            <button
                                onClick={() => setVue('tableau')}
                                className={cn('flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium transition-colors',
                                    vue === 'tableau' ? 'bg-ink text-white' : 'text-slate-500 hover:bg-slate-50')}
                            >
                                <Table2 className="h-3.5 w-3.5" /> Tableau
                            </button>
                        </div>
                        <a href={exportHref}>
                            <Button variant="outline" size="sm" className="h-8 gap-1.5">
                                <Download className="h-3.5 w-3.5" /> Export CSV
                            </Button>
                        </a>
                    </div>
                </div>

                {/* stats */}
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                    {[
                        { label: 'Total actives', value: stats?.total      ?? 0, Icon: FileText,  cls: 'text-ink' },
                        { label: 'À déposer',     value: stats?.aDeposer   ?? 0, Icon: Upload,    cls: 'text-slate-500' },
                        { label: 'En cours',      value: stats?.enCours    ?? 0, Icon: Clock,     cls: 'text-blue-600' },
                        { label: 'Retour reçu',   value: stats?.retourRecu ?? 0, Icon: MailCheck, cls: 'text-green-600' },
                        { label: 'Urgentes',      value: stats?.urgentes   ?? 0, Icon: Flame,     cls: (stats?.urgentes ?? 0) > 0 ? 'text-danger' : 'text-slate-400' },
                        { label: 'Frais engagés', value: fmt(stats?.montantTotal ?? 0) + ' GNF', Icon: Banknote, cls: 'text-seal' },
                    ].map(({ label, value, Icon, cls }) => (
                        <div key={label} className="bg-white rounded-lg border border-slate-200 p-3 shadow-sm">
                            <div className="flex items-center justify-between mb-1">
                                <span className="text-xs text-slate-500">{label}</span>
                                <Icon className={`h-4 w-4 ${cls}`} />
                            </div>
                            <div className={`text-2xl font-semibold ${cls}`}>{value}</div>
                        </div>
                    ))}
                </div>

                {/* urgent banners */}
                <AnimatePresence>
                    {urgentes.map(f => (
                        <motion.div
                            key={f.id}
                            initial={{ opacity: 0, y: -6 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, height: 0 }}
                            className={`flex items-center gap-3 px-4 py-2.5 rounded-lg border text-sm ${
                                f.estDepassee
                                    ? 'bg-danger-bg border-danger/30 text-danger-text'
                                    : 'bg-amber-50 border-amber-200 text-amber-800'
                            }`}
                        >
                            <AlertCircle className="h-4 w-4 shrink-0" />
                            <span className="flex-1">
                                <strong>{f.libelle || f.organismeLabel}</strong>
                                {f.dossier?.reference && ` · ${f.dossier.reference}`}
                                {f.estDepassee
                                    ? ' — délai dépassé'
                                    : ` — ${f.heuresRestantes}h restantes`}
                            </span>
                        </motion.div>
                    ))}
                </AnimatePresence>

                {/* onglets organisme */}
                <Tabs value={filters.organisme || '__tous__'} onValueChange={v => setF('organisme', v === '__tous__' ? '' : v)}>
                    <TabsList className="w-full justify-start overflow-x-auto">
                        <TabsTrigger value="__tous__">
                            Tous ({stats?.total ?? 0})
                        </TabsTrigger>
                        {ORGANISMES_ONGLETS.map(org => (
                            <TabsTrigger key={org} value={org}>
                                {organismeShortLabel(org)} ({stats?.parOrganisme?.[org] ?? 0})
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </Tabs>

                {/* filters */}
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative flex-1 min-w-[180px]">
                        <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                        <Input
                            placeholder="Rechercher organisme, dossier…"
                            value={filters.q}
                            onChange={e => setF('q', e.target.value)}
                            className="pl-8 h-8 text-sm"
                        />
                    </div>

                    <Select
                        value={filters.statut || '__all__'}
                        onValueChange={v => setF('statut', v === '__all__' ? '' : v)}
                    >
                        <SelectTrigger className="h-8 w-[170px] text-sm">
                            <Filter className="h-3.5 w-3.5 text-slate-400 mr-1 shrink-0" />
                            <SelectValue placeholder="Statut" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">Tous les statuts</SelectItem>
                            {(statuts ?? []).map(s => (
                                <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.sort || '__none__'}
                        onValueChange={v => setF('sort', v === '__none__' ? '' : v)}
                    >
                        <SelectTrigger className="h-8 w-[155px] text-sm">
                            <ArrowUpDown className="h-3.5 w-3.5 text-slate-400 mr-1 shrink-0" />
                            <SelectValue placeholder="Trier par" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none__">Échéance (défaut)</SelectItem>
                            <SelectItem value="montant">Montant ↓</SelectItem>
                            <SelectItem value="organisme">Organisme A–Z</SelectItem>
                        </SelectContent>
                    </Select>

                    <button
                        onClick={() => setF('urgentes', filters.urgentes === '1' ? '' : '1')}
                        className={`flex items-center gap-1.5 h-8 px-3 rounded-md border text-sm transition-colors ${
                            filters.urgentes === '1'
                                ? 'bg-amber-50 border-amber-300 text-amber-700 font-medium'
                                : 'border-slate-200 text-slate-600 hover:bg-slate-50'
                        }`}
                    >
                        <Flame className={`h-3.5 w-3.5 ${filters.urgentes === '1' ? 'text-amber-500' : 'text-slate-400'}`} />
                        Urgentes
                    </button>

                    {hasFilters && (
                        <Button variant="ghost" size="sm" onClick={reset} className="h-8 gap-1 text-slate-500">
                            <X className="h-3.5 w-3.5" /> Réinitialiser
                        </Button>
                    )}
                </div>

                {/* content */}
                {data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-20 text-center">
                        <Building2 className="h-12 w-12 text-slate-200 mb-4" />
                        <p className="font-serif text-heading text-slate-400">Aucune formalité trouvée</p>
                        {hasFilters && (
                            <button onClick={reset} className="text-xs text-seal hover:underline mt-2">
                                Effacer les filtres
                            </button>
                        )}
                    </div>
                ) : vue === 'tableau' ? (
                    <FormalitesTable items={data} />
                ) : (
                    <div className="space-y-6">
                        {Object.entries(grouped).map(([ref, group], di) => (
                            <motion.div
                                key={ref}
                                initial={{ opacity: 0, y: 8 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: di * 0.05 }}
                            >
                                {/* dossier header */}
                                <div className="flex items-center gap-2 mb-2.5">
                                    <button
                                        onClick={() => group.meta?.reference && router.visit(`/dossiers/${group.meta.reference}?tab=formalites`)}
                                        className="font-ref text-sm text-seal hover:underline flex items-center gap-1.5"
                                    >
                                        <FileText className="h-3.5 w-3.5" />
                                        {ref}
                                    </button>
                                    {group.meta?.objet && (
                                        <span className="text-sm text-slate-600 truncate max-w-xs">
                                            — {group.meta.objet}
                                        </span>
                                    )}
                                    {group.meta?.typeActe && (
                                        <span className="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded shrink-0">
                                            {group.meta.typeActe}
                                        </span>
                                    )}
                                </div>

                                <div className="grid gap-3 sm:grid-cols-2">
                                    <AnimatePresence mode="popLayout">
                                        {group.items.map(f => (
                                            <FormaliteCard key={f.id} formalite={f} showDossier={false} />
                                        ))}
                                    </AnimatePresence>
                                </div>
                            </motion.div>
                        ))}
                    </div>
                )}

                {/* pagination */}
                {(formalites?.last_page ?? 1) > 1 && (
                    <div className="flex justify-center gap-2 pt-2">
                        {(formalites?.links ?? []).map((link, i) => (
                            <button
                                key={i}
                                disabled={!link.url || link.active}
                                onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                                className={`px-3 py-1.5 rounded text-sm border transition-colors ${
                                    link.active
                                        ? 'bg-seal text-white border-seal'
                                        : link.url
                                            ? 'border-slate-200 hover:bg-slate-50'
                                            : 'border-slate-100 text-slate-300 cursor-not-allowed'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}

            </div>
        </AppLayout>
    );
}
