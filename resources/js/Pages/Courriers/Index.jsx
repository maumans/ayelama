import { useState, useEffect, useRef, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Tooltip, TooltipContent, TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    ArrowUpDown, CalendarDays, CheckCircle2, ChevronDown, ChevronUp,
    Clock, FileText, FolderOpen, Mail, Pencil, Plus, RotateCcw,
    Search, Send, Trash2, X,
} from 'lucide-react';

/* ─── constants ─────────────────────────────────────────── */

const TYPES = [
    { value: 'transmission', label: 'Lettre de transmission' },
    { value: 'convocation',  label: 'Convocation' },
    { value: 'relance',      label: 'Relance' },
    { value: 'divers',       label: 'Divers' },
];

const TYPE_META = {
    transmission: { badge: 'bg-blue-50 text-blue-700 border-blue-200',   bar: 'bg-blue-500' },
    convocation:  { badge: 'bg-amber-50 text-amber-700 border-amber-200', bar: 'bg-amber-400' },
    relance:      { badge: 'bg-red-50 text-red-700 border-red-200',       bar: 'bg-red-400' },
    divers:       { badge: 'bg-slate-100 text-slate-600 border-slate-200', bar: 'bg-slate-400' },
};

const getTypeMeta = (t) => TYPE_META[t] ?? TYPE_META.divers;
const getTypeLabel = (t) => TYPES.find(x => x.value === t)?.label ?? t;

/* ─── Modal create/edit ──────────────────────────────────── */

function ModalCourrier({ open, onClose, courrier = null, dossiers }) {
    const isEdit = courrier !== null;

    const blank = { destinataire: '', adresse: '', objet: '', type: 'transmission', contenu: '', dossier_id: '' };
    const [form, setForm]     = useState(blank);
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (open) {
            setForm(isEdit ? {
                destinataire: courrier.destinataire ?? '',
                adresse:      courrier.adresse      ?? '',
                objet:        courrier.objet        ?? '',
                type:         courrier.type         ?? 'transmission',
                contenu:      courrier.contenu      ?? '',
                dossier_id:   courrier.dossier_id ? String(courrier.dossier_id) : '',
            } : blank);
            setErrors({});
        }
    }, [open, courrier?.id]);

    const setF = (k, v) => setForm(f => ({ ...f, [k]: v }));

    const submit = () => {
        const data = { ...form, dossier_id: form.dossier_id || null };
        if (isEdit) {
            router.patch(`/courriers/${courrier.id}`, data, { onSuccess: onClose, onError: setErrors });
        } else {
            router.post('/courriers', data, {
                onSuccess: () => { onClose(); setForm(blank); },
                onError: setErrors,
            });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-serif text-ink">
                        {isEdit ? `Modifier ${courrier.reference}` : 'Nouveau courrier'}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Type</Label>
                            <Select value={form.type} onValueChange={v => setF('type', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {TYPES.map(t => (
                                        <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Dossier lié <span className="text-slate-400">(optionnel)</span></Label>
                            <Select value={form.dossier_id} onValueChange={v => setF('dossier_id', v)}>
                                <SelectTrigger><SelectValue placeholder="Aucun" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Aucun</SelectItem>
                                    {(dossiers ?? []).map(d => (
                                        <SelectItem key={d.id} value={String(d.id)}>
                                            {d.reference} — {d.objet?.slice(0, 28)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Destinataire</Label>
                        <Input
                            placeholder="Nom ou organisme"
                            value={form.destinataire}
                            onChange={e => setF('destinataire', e.target.value)}
                        />
                        {errors.destinataire && <p className="text-xs text-danger-text">{errors.destinataire}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label>Adresse <span className="text-slate-400">(optionnel)</span></Label>
                        <Input
                            placeholder="Adresse postale ou email"
                            value={form.adresse}
                            onChange={e => setF('adresse', e.target.value)}
                        />
                    </div>

                    <div className="space-y-1.5">
                        <Label>Objet</Label>
                        <Input
                            placeholder="Objet du courrier"
                            value={form.objet}
                            onChange={e => setF('objet', e.target.value)}
                        />
                        {errors.objet && <p className="text-xs text-danger-text">{errors.objet}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label>Contenu <span className="text-slate-400">(optionnel)</span></Label>
                        <textarea
                            rows={4}
                            className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 resize-none"
                            placeholder="Corps du courrier…"
                            value={form.contenu}
                            onChange={e => setF('contenu', e.target.value)}
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>Annuler</Button>
                    <Button variant="seal" onClick={submit}>
                        <Mail className="h-4 w-4" />
                        {isEdit ? 'Enregistrer les modifications' : 'Créer le courrier'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/* ─── CourrierRow ────────────────────────────────────────── */

function CourrierRow({ c, onEdit, idx }) {
    const [showContenu, setShowContenu] = useState(false);
    const [confirmState, setConfirmState] = useState(null);
    const typeMeta = getTypeMeta(c.type);

    const patch = (data) => router.patch(`/courriers/${c.id}`, data, { preserveScroll: true });

    const marquerEnvoye = () => patch({ statut: 'envoye' });
    const revenirBrouillon = () => setConfirmState({
        title: 'Remettre en brouillon ?',
        description: `Le courrier ${c.reference} sera remis en statut brouillon.`,
        confirmLabel: 'Remettre en brouillon',
        variant: 'default',
        onConfirm: () => patch({ statut: 'brouillon' }),
    });
    const supprimer = () => setConfirmState({
        title: `Supprimer "${c.reference}" ?`,
        description: 'Cette action est irréversible.',
        confirmLabel: 'Supprimer',
        variant: 'destructive',
        onConfirm: () => router.delete(`/courriers/${c.id}`, { preserveScroll: true }),
    });

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
        <motion.div
            initial={{ opacity: 0, y: 5 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: idx * 0.03 }}
        >
            <Card className={`border-l-4 transition-shadow hover:shadow-sm ${
                c.statut === 'envoye' ? 'border-l-success' : 'border-l-warning'
            }`}>
                <CardContent className="p-4">
                    <div className="flex items-start gap-4">
                        {/* info */}
                        <div className="flex-1 min-w-0">
                            {/* row 1: ref + badges */}
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="font-ref text-sm text-seal font-semibold">{c.reference}</span>
                                <Badge className={`text-[10px] px-1.5 py-0 border ${typeMeta.badge}`}>
                                    {getTypeLabel(c.type)}
                                </Badge>
                                <Badge className={`text-[10px] px-1.5 py-0 border flex items-center gap-1 ${
                                    c.statut === 'envoye'
                                        ? 'bg-success-bg text-success-text border-green-200'
                                        : 'bg-warning-bg text-warning-text border-amber-200'
                                }`}>
                                    {c.statut === 'envoye'
                                        ? <><CheckCircle2 className="h-2.5 w-2.5" /> Envoyé</>
                                        : <><Clock className="h-2.5 w-2.5" /> Brouillon</>}
                                </Badge>
                            </div>

                            {/* row 2: objet */}
                            <p className="text-sm font-medium text-slate-800 mt-1 truncate">{c.objet}</p>

                            {/* row 3: meta */}
                            <div className="flex items-center gap-4 mt-1 text-xs text-slate-400 flex-wrap">
                                <span>À : <span className="text-slate-600">{c.destinataire}</span></span>
                                {c.adresse && (
                                    <span className="text-slate-400 truncate max-w-[160px]">{c.adresse}</span>
                                )}
                                {c.dossierRef && (
                                    <button
                                        onClick={() => router.visit(`/dossiers/${c.dossierRef}`)}
                                        className="flex items-center gap-1 text-seal hover:underline"
                                    >
                                        <FolderOpen className="h-3 w-3" />
                                        {c.dossierRef}
                                    </button>
                                )}
                                {c.statut === 'envoye' && c.envoye_at
                                    ? <span>Envoyé le {c.envoye_at}</span>
                                    : <span>Créé le {c.created_at}</span>}
                                {c.redacteur && <span>Par {c.redacteur}</span>}
                            </div>
                        </div>

                        {/* actions */}
                        <div className="flex items-center gap-1 shrink-0">
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button variant="ghost" size="icon-sm"
                                        className="h-7 w-7 text-slate-400 hover:text-ink"
                                        onClick={() => onEdit(c)}
                                    >
                                        <Pencil className="h-3.5 w-3.5" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent><p className="text-xs">Modifier</p></TooltipContent>
                            </Tooltip>

                            {c.statut === 'brouillon' && (
                                <Button variant="outline" size="sm"
                                    className="h-7 text-xs gap-1"
                                    onClick={marquerEnvoye}
                                >
                                    <Send className="h-3 w-3" /> Envoyer
                                </Button>
                            )}
                            {c.statut === 'envoye' && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button variant="ghost" size="icon-sm"
                                            className="h-7 w-7 text-slate-400 hover:text-warning"
                                            onClick={revenirBrouillon}
                                        >
                                            <RotateCcw className="h-3.5 w-3.5" />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent><p className="text-xs">Revenir en brouillon</p></TooltipContent>
                                </Tooltip>
                            )}

                            {c.contenu && (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button variant="ghost" size="icon-sm"
                                            className="h-7 w-7 text-slate-400 hover:text-ink"
                                            onClick={() => setShowContenu(v => !v)}
                                        >
                                            {showContenu
                                                ? <ChevronUp className="h-3.5 w-3.5" />
                                                : <ChevronDown className="h-3.5 w-3.5" />}
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p className="text-xs">{showContenu ? 'Masquer' : 'Voir le contenu'}</p>
                                    </TooltipContent>
                                </Tooltip>
                            )}

                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button variant="ghost" size="icon-sm"
                                        className="h-7 w-7 text-slate-300 hover:text-danger"
                                        onClick={supprimer}
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent><p className="text-xs">Supprimer</p></TooltipContent>
                            </Tooltip>
                        </div>
                    </div>

                    {/* contenu preview */}
                    <AnimatePresence>
                        {showContenu && c.contenu && (
                            <motion.div
                                initial={{ height: 0, opacity: 0 }}
                                animate={{ height: 'auto', opacity: 1 }}
                                exit={{ height: 0, opacity: 0 }}
                                className="overflow-hidden"
                            >
                                <div className="mt-3 pt-3 border-t border-slate-100">
                                    <p className="text-xs text-slate-600 whitespace-pre-line leading-relaxed">
                                        {c.contenu}
                                    </p>
                                </div>
                            </motion.div>
                        )}
                    </AnimatePresence>
                </CardContent>
            </Card>
        </motion.div>
        </>
    );
}

/* ─── Page ───────────────────────────────────────────────── */

export default function CourriersIndex({ courriers, dossiers = [], stats = {}, filters: init = {} }) {
    const [q,      setQ]      = useState(init.q      ?? '');
    const [type,   setType]   = useState(init.type   ?? '');
    const [statut, setStatut] = useState(init.statut ?? '');
    const [sort,   setSort]   = useState(init.sort   ?? '');
    const [modal,  setModal]  = useState({ open: false, courrier: null });
    const firstRender = useRef(true);

    const apply = useCallback((params) => {
        const clean = Object.fromEntries(Object.entries(params).filter(([, v]) => v));
        router.get('/courriers', clean, { preserveState: true, replace: true });
    }, []);

    /* debounce search */
    useEffect(() => {
        if (firstRender.current) { firstRender.current = false; return; }
        const t = setTimeout(() => apply({ q, type, statut, sort }), 350);
        return () => clearTimeout(t);
    }, [q]);

    const setF = (key, val) => {
        const next = { q, type, statut, sort, [key]: val };
        if (key === 'type')   setType(val);
        if (key === 'statut') setStatut(val);
        if (key === 'sort')   setSort(val);
        apply(next);
    };

    const reset = () => {
        setQ(''); setType(''); setStatut(''); setSort('');
        router.get('/courriers', {}, { preserveState: true, replace: true });
    };

    const hasFilters = q || type || statut || sort;
    const data       = courriers?.data ?? [];
    const total      = courriers?.total ?? 0;
    const parType    = stats?.parType ?? [];
    const grandTotal = stats?.total ?? 0;

    return (
        <AppLayout breadcrumbs={[{ label: 'Courriers' }]}>
            <Head title="Courriers — Ayelema" />

            <div className="p-6 max-w-[1100px] mx-auto space-y-5">

                {/* header */}
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-display text-ink">Courriers</h1>
                        <p className="text-slate-500 text-sm mt-1">Lettres de transmission, convocations et correspondance officielle</p>
                    </div>
                    <Button variant="seal" onClick={() => setModal({ open: true, courrier: null })}>
                        <Plus className="h-4 w-4" />
                        Nouveau courrier
                    </Button>
                </div>

                {/* KPIs */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    {[
                        { label: 'Total',      value: stats.total      ?? 0, cls: 'text-ink',     Icon: Mail },
                        { label: 'Brouillons', value: stats.brouillons ?? 0, cls: 'text-warning',  Icon: Clock },
                        { label: 'Envoyés',    value: stats.envoyes    ?? 0, cls: 'text-success',  Icon: Send },
                        { label: 'Ce mois',    value: stats.ceMois     ?? 0, cls: 'text-seal',     Icon: CalendarDays },
                    ].map(({ label, value, cls, Icon }) => (
                        <div key={label} className="bg-white rounded-lg border border-slate-200 p-3 shadow-sm">
                            <div className="flex items-center justify-between mb-1">
                                <span className="text-xs text-slate-500">{label}</span>
                                <Icon className={`h-4 w-4 ${cls}`} />
                            </div>
                            <div className={`text-2xl font-semibold ${cls}`}>{value}</div>
                        </div>
                    ))}
                </div>

                {/* répartition par type */}
                {parType.length > 0 && grandTotal > 0 && (
                    <div className="bg-white rounded-lg border border-slate-200 px-4 py-3 shadow-sm">
                        <p className="text-xs font-medium text-slate-500 mb-2">Répartition par type</p>
                        <div className="flex h-2 rounded-full overflow-hidden mb-2 gap-px">
                            {parType.map(({ type: t, count }) => {
                                const m   = getTypeMeta(t);
                                const pct = (count / grandTotal) * 100;
                                return (
                                    <Tooltip key={t}>
                                        <TooltipTrigger asChild>
                                            <div
                                                className={`h-full cursor-pointer hover:opacity-80 transition-opacity ${m.bar}`}
                                                style={{ width: `${pct}%`, minWidth: pct > 0 ? '4px' : '0' }}
                                            />
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p className="text-xs">{getTypeLabel(t)} : {count}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                );
                            })}
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {parType.map(({ type: t, count }) => {
                                const m = getTypeMeta(t);
                                return (
                                    <button
                                        key={t}
                                        onClick={() => setF('type', type === t ? '' : t)}
                                        className={`flex items-center gap-1 text-xs px-2 py-0.5 rounded-full border transition-all ${
                                            type === t
                                                ? `${m.badge} ring-1 ring-offset-1 ring-current font-medium`
                                                : `${m.badge} opacity-60 hover:opacity-100`
                                        }`}
                                    >
                                        {getTypeLabel(t)}
                                        <span className="font-semibold">{count}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* filters */}
                <div className="flex flex-wrap gap-2 items-center">
                    <div className="relative flex-1 min-w-[200px] max-w-xs">
                        <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                        <Input
                            className="pl-8 h-8 text-sm"
                            placeholder="Référence, objet, destinataire…"
                            value={q}
                            onChange={e => setQ(e.target.value)}
                        />
                        {q && (
                            <button
                                onClick={() => { setQ(''); apply({ q: '', type, statut, sort }); }}
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>

                    <Select value={type || '__all__'} onValueChange={v => setF('type', v === '__all__' ? '' : v)}>
                        <SelectTrigger className="h-8 text-sm w-[170px]">
                            <SelectValue placeholder="Tous types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">Tous types</SelectItem>
                            {TYPES.map(t => <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>)}
                        </SelectContent>
                    </Select>

                    <Select value={statut || '__all__'} onValueChange={v => setF('statut', v === '__all__' ? '' : v)}>
                        <SelectTrigger className="h-8 text-sm w-[140px]">
                            <SelectValue placeholder="Tous statuts" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">Tous statuts</SelectItem>
                            <SelectItem value="brouillon">Brouillons</SelectItem>
                            <SelectItem value="envoye">Envoyés</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select value={sort || '__none__'} onValueChange={v => setF('sort', v === '__none__' ? '' : v)}>
                        <SelectTrigger className="h-8 text-sm w-[150px]">
                            <ArrowUpDown className="h-3.5 w-3.5 text-slate-400 mr-1 shrink-0" />
                            <SelectValue placeholder="Trier" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none__">Date création ↓</SelectItem>
                            <SelectItem value="objet">Objet A–Z</SelectItem>
                            <SelectItem value="envoye">Date envoi ↓</SelectItem>
                        </SelectContent>
                    </Select>

                    {hasFilters && (
                        <Button variant="ghost" size="sm" className="h-8 gap-1 text-slate-500" onClick={reset}>
                            <X className="h-3.5 w-3.5" /> Réinitialiser
                        </Button>
                    )}

                    <span className="ml-auto text-xs text-slate-400">
                        {total} courrier{total !== 1 ? 's' : ''}
                    </span>
                </div>

                {/* list */}
                {data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-20 text-center">
                            <Mail className="h-12 w-12 text-slate-200 mb-4" />
                            <h3 className="font-serif text-heading text-slate-500">Aucun courrier</h3>
                            <p className="text-sm text-slate-400 mt-1">
                                {hasFilters ? 'Aucun résultat pour ces filtres.' : 'Créez le premier courrier.'}
                            </p>
                            {hasFilters && (
                                <button onClick={reset} className="text-xs text-seal hover:underline mt-2">
                                    Effacer les filtres
                                </button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-2">
                        {data.map((c, i) => (
                            <CourrierRow
                                key={c.id}
                                c={c}
                                idx={i}
                                onEdit={(c) => setModal({ open: true, courrier: c })}
                            />
                        ))}
                    </div>
                )}

                {/* pagination */}
                {(courriers?.last_page ?? 1) > 1 && (
                    <div className="flex justify-center gap-2 pt-2">
                        {(courriers?.links ?? []).map((link, i) => (
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

            <ModalCourrier
                open={modal.open}
                onClose={() => setModal({ open: false, courrier: null })}
                courrier={modal.courrier}
                dossiers={dossiers}
            />
        </AppLayout>
    );
}
