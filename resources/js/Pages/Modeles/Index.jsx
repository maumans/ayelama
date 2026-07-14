import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    FileText, Plus, Search, Trash2, Copy,
    CheckCircle2, XCircle, X, LayoutGrid, List,
    Pencil, ClipboardCopy, Check, ChevronDown, ChevronUp,
    ArrowUpDown, FileCheck, Paperclip, PenLine, Mail, Receipt,
    BookOpen, ShieldCheck, ClipboardList, Fingerprint,
    Newspaper, Building2, Calculator, LayoutList,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { notifyValidationError } from '@/lib/toast';

// ── Constantes ─────────────────────────────────────────────────────────────

const TYPES_DOC = [
    { value: 'acte_principal', label: 'Acte principal',       icon: FileCheck,     color: 'bg-ink/10 text-ink border-ink/20' },
    { value: 'page_garde',     label: 'Page de garde',         icon: BookOpen,      color: 'bg-slate-50 text-slate-600 border-slate-200' },
    { value: 'attestation',    label: 'Attestation',           icon: ShieldCheck,   color: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    { value: 'declaration',    label: 'Déclaration',           icon: ClipboardList, color: 'bg-violet-50 text-violet-700 border-violet-200' },
    { value: 'dnsv',           label: 'DNSV',                  icon: Fingerprint,   color: 'bg-orange-50 text-orange-700 border-orange-200' },
    { value: 'insertion',      label: 'Insertion au JORG',     icon: Newspaper,     color: 'bg-cyan-50 text-cyan-700 border-cyan-200' },
    { value: 'rccm',           label: 'RCCM',                  icon: Building2,     color: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
    { value: 'note_frais',     label: 'Note de frais',         icon: Calculator,    color: 'bg-yellow-50 text-yellow-700 border-yellow-200' },
    { value: 'bordereau',      label: 'Bordereau / Tableau',   icon: LayoutList,    color: 'bg-pink-50 text-pink-700 border-pink-200' },
    { value: 'annexe',         label: 'Annexe',                icon: Paperclip,     color: 'bg-blue-50 text-blue-700 border-blue-200' },
    { value: 'procedure',      label: 'Procédure',             icon: PenLine,       color: 'bg-amber-50 text-amber-700 border-amber-200' },
    { value: 'lettre',         label: 'Lettre / Transmission', icon: Mail,          color: 'bg-purple-50 text-purple-700 border-purple-200' },
    { value: 'recepisse',      label: 'Récépissé',             icon: Receipt,       color: 'bg-green-50 text-green-700 border-green-200' },
];
const TYPE_DOC_MAP = Object.fromEntries(TYPES_DOC.map(t => [t.value, t]));

// ── Composants utilitaires ──────────────────────────────────────────────────

function TypeDocBadge({ type, size = 'sm' }) {
    const t = TYPE_DOC_MAP[type] ?? { label: type, color: 'bg-slate-100 text-slate-600 border-slate-200' };
    return (
        <span className={cn(
            'inline-flex items-center gap-1 font-medium border rounded-full',
            size === 'sm' ? 'text-[10px] px-2 py-0.5' : 'text-xs px-2.5 py-1',
            t.color
        )}>
            {t.label}
        </span>
    );
}

function CopyPathButton({ path }) {
    const [copied, setCopied] = useState(false);

    const copy = (e) => {
        e.stopPropagation();
        navigator.clipboard.writeText(path).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1800);
        });
    };

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    onClick={copy}
                    className="flex items-center gap-1 text-slate-400 hover:text-seal transition-colors group"
                >
                    {copied
                        ? <Check className="h-3 w-3 text-success" />
                        : <ClipboardCopy className="h-3 w-3" />
                    }
                    <span className={cn(
                        'text-[10px] font-ref truncate max-w-[140px] transition-colors',
                        copied ? 'text-success' : 'text-slate-400 group-hover:text-slate-600'
                    )}>
                        {copied ? 'Copié !' : path}
                    </span>
                </button>
            </TooltipTrigger>
            <TooltipContent side="top" className="max-w-xs break-all text-xs">{path}</TooltipContent>
        </Tooltip>
    );
}

// ── Modale Créer / Modifier ─────────────────────────────────────────────────

import { useForm } from '@inertiajs/react';

function ModalModele({ open, onClose, typesActes, modele = null }) {
    const isEdit = !!modele;
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        nom:            '',
        type_acte_id:   '',
        type_document:  'acte_principal',
        version:        '1.0',
        fichier:        null,
        chemin_fichier: '',
    });

    // sync si modele change (ouverture édition)
    React.useEffect(() => {
        if (open) {
            clearErrors();
            if (modele) {
                setData({
                    nom:            modele.nom,
                    type_acte_id:   String(modele.type_acte_id),
                    type_document:  modele.type_document,
                    version:        modele.version,
                    fichier:        null,
                    chemin_fichier: modele.chemin_fichier,
                });
            } else {
                reset();
            }
        }
    }, [open, modele]);

    const submit = () => {
        const opts = { onSuccess: () => { onClose(); reset(); } };
        if (isEdit) {
            // Pour uploader un fichier en PATCH avec Laravel + Inertia
            router.post(`/modeles/${modele.id}`, {
                _method: 'patch',
                ...data
            }, { ...opts, forceFormData: true });
        } else {
            post('/modeles', { ...opts, forceFormData: true });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-serif text-ink">
                        {isEdit ? 'Modifier le modèle' : "Nouveau modèle d'acte"}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    <div className="space-y-1.5">
                        <Label>Nom du modèle</Label>
                        <Input
                            placeholder="ex : Statuts SARL — Constitution"
                            value={data.nom}
                            onChange={e => setData('nom', e.target.value)}
                        />
                        {errors.nom && <p className="text-xs text-danger-text">{errors.nom}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Type d'acte</Label>
                            <Select value={data.type_acte_id} onValueChange={v => setData('type_acte_id', v)}>
                                <SelectTrigger><SelectValue placeholder="Choisir…" /></SelectTrigger>
                                <SelectContent className="max-h-60">
                                    {typesActes?.map(t => (
                                        <SelectItem key={t.id} value={String(t.id)}>{t.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.type_acte_id && <p className="text-xs text-danger-text">{errors.type_acte_id}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label>Type de document</Label>
                            <Select value={data.type_document} onValueChange={v => setData('type_document', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {TYPES_DOC.map(t => <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <div className="space-y-1.5">
                            <Label>Version</Label>
                            <Input
                                placeholder="1.0"
                                value={data.version}
                                onChange={e => setData('version', e.target.value)}
                            />
                        </div>
                        <div className="col-span-2 space-y-1.5">
                            <Label>Fichier (.docx)</Label>
                            <Input
                                type="file"
                                accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                onChange={e => setData('fichier', e.target.files[0])}
                            />
                            {!data.fichier && data.chemin_fichier && (
                                <p className="text-[10px] text-slate-400 truncate mt-1">Actuel : {data.chemin_fichier}</p>
                            )}
                            {errors.fichier && <p className="text-xs text-danger-text">{errors.fichier}</p>}
                            {errors.chemin_fichier && <p className="text-xs text-danger-text">{errors.chemin_fichier}</p>}
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>Annuler</Button>
                    <Button variant="seal" onClick={submit} disabled={processing}>
                        {isEdit ? 'Enregistrer' : 'Créer le modèle'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

// ── Modale Créer / Modifier un courrier de transmission ─────────────────────

function ModalModeleCourrier({ open, onClose, typesActes, categories, modele = null }) {
    const isEdit = !!modele;
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        nom:              '',
        type_document:    'lettre',
        version:          '1.0',
        fichier:          null,
        chemin_fichier:   '',
        applicable_tous:  false,
        type_acte_ids:    [],
    });

    React.useEffect(() => {
        if (open) {
            clearErrors();
            if (modele) {
                setData({
                    nom:             modele.nom,
                    type_document:   modele.type_document,
                    version:         modele.version,
                    fichier:         null,
                    chemin_fichier:  modele.chemin_fichier,
                    applicable_tous: modele.applicable_tous,
                    type_acte_ids:   (modele.type_acte_ids ?? []).map(String),
                });
            } else {
                reset();
            }
        }
    }, [open, modele]);

    const toggleTypeActe = (id) => {
        const key = String(id);
        setData('type_acte_ids', data.type_acte_ids.includes(key)
            ? data.type_acte_ids.filter(v => v !== key)
            : [...data.type_acte_ids, key]);
    };

    const submit = () => {
        const opts = { onSuccess: () => { onClose(); reset(); }, onError: notifyValidationError };
        if (isEdit) {
            router.post(`/modeles-courriers/${modele.id}`, { _method: 'patch', ...data }, { ...opts, forceFormData: true });
        } else {
            post('/modeles-courriers', { ...opts, forceFormData: true });
        }
    };

    const categorieLabels = Object.fromEntries((categories ?? []).map(c => [c.value, c.label]));
    const groupes = (typesActes ?? []).reduce((acc, t) => {
        const key = categorieLabels[t.categorie] ?? t.categorie;
        if (!acc[key]) acc[key] = [];
        acc[key].push(t);
        return acc;
    }, {});

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="font-serif text-ink">
                        {isEdit ? 'Modifier le courrier de transmission' : 'Nouveau courrier de transmission'}
                    </DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    <div className="space-y-1.5">
                        <Label>Nom de la lettre</Label>
                        <Input
                            placeholder="ex : Transmission modification société"
                            value={data.nom}
                            onChange={e => setData('nom', e.target.value)}
                        />
                        {errors.nom && <p className="text-xs text-danger-text">{errors.nom}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Type de document</Label>
                            <Select value={data.type_document} onValueChange={v => setData('type_document', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {TYPES_DOC.map(t => <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Version</Label>
                            <Input
                                placeholder="1.0"
                                value={data.version}
                                onChange={e => setData('version', e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Fichier (.docx)</Label>
                        <Input
                            type="file"
                            accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                            onChange={e => setData('fichier', e.target.files[0])}
                        />
                        {!data.fichier && data.chemin_fichier && (
                            <p className="text-[10px] text-slate-400 truncate mt-1">Actuel : {data.chemin_fichier}</p>
                        )}
                        {errors.fichier && <p className="text-xs text-danger-text">{errors.fichier}</p>}
                        {errors.chemin_fichier && <p className="text-xs text-danger-text">{errors.chemin_fichier}</p>}
                    </div>

                    <div className="rounded-lg border border-slate-200 p-3 space-y-3">
                        <label className="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer w-fit">
                            <Checkbox checked={data.applicable_tous}
                                onCheckedChange={(checked) => setData('applicable_tous', checked === true)} />
                            <span>Applicable à tous les types d'actes</span>
                        </label>

                        {!data.applicable_tous && (
                            <div className="max-h-52 overflow-y-auto space-y-3 pt-1 border-t border-slate-100">
                                {Object.entries(groupes).map(([cat, items]) => (
                                    <div key={cat}>
                                        <p className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">{cat}</p>
                                        <div className="space-y-1.5">
                                            {items.map(t => (
                                                <label key={t.id} className="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                                    <Checkbox checked={data.type_acte_ids.includes(String(t.id))}
                                                        onCheckedChange={() => toggleTypeActe(t.id)} />
                                                    <span>{t.label}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                                {errors.type_acte_ids && <p className="text-xs text-danger-text">{errors.type_acte_ids}</p>}
                            </div>
                        )}
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>Annuler</Button>
                    <Button variant="seal" onClick={submit} disabled={processing}>
                        {isEdit ? 'Enregistrer' : 'Créer la lettre'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

// ── Vue Table — groupe par catégorie ────────────────────────────────────────

function GroupeTable({ categorieLabel, items, can, onEdit }) {
    const [open, setOpen] = useState(true);
    const [confirmState, setConfirmState] = useState(null);

    const toggleActif = (m) => {
        router.patch(`/modeles/${m.id}`, { est_actif: !m.est_actif }, { preserveScroll: true });
    };
    const supprimer = (m) => setConfirmState({
        title: `Supprimer "${m.nom}" ?`,
        description: 'Ce modèle sera définitivement supprimé.',
        confirmLabel: 'Supprimer',
        variant: 'destructive',
        onConfirm: () => router.delete(`/modeles/${m.id}`, { preserveScroll: true }),
    });
    const dupliquer = (m) => {
        router.post(`/modeles/${m.id}/dupliquer`, {}, { preserveScroll: true });
    };

    return (
        <div>
            <ConfirmDialog
                open={!!confirmState}
                onClose={() => setConfirmState(null)}
                title={confirmState?.title ?? ''}
                description={confirmState?.description}
                confirmLabel={confirmState?.confirmLabel}
                variant={confirmState?.variant}
                onConfirm={confirmState?.onConfirm ?? (() => {})}
            />
            <button
                onClick={() => setOpen(o => !o)}
                className="flex items-center gap-2 mb-2 group"
            >
                <span className="text-xs font-semibold text-slate-400 uppercase tracking-wider group-hover:text-slate-600 transition-colors">
                    {categorieLabel}
                </span>
                <span className="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded-full">{items.length}</span>
                {open
                    ? <ChevronUp className="h-3 w-3 text-slate-300 group-hover:text-slate-500" />
                    : <ChevronDown className="h-3 w-3 text-slate-300 group-hover:text-slate-500" />
                }
            </button>

            <AnimatePresence initial={false}>
                {open && (
                    <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.18 }}
                        className="overflow-hidden"
                    >
                        <Card className="overflow-hidden mb-4">
                            <table className="table-notarial w-full">
                                <thead>
                                    <tr>
                                        <th className="w-8"></th>
                                        <th>Nom</th>
                                        <th>Type d'acte</th>
                                        <th>Type doc</th>
                                        <th>Version</th>
                                        <th>Chemin fichier</th>
                                        <th>MAJ</th>
                                        <th className="text-center">Actif</th>
                                        {can.administrer && <th></th>}
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map(m => {
                                        const tdInfo = TYPE_DOC_MAP[m.type_document];
                                        const Icon = tdInfo?.icon ?? FileText;
                                        return (
                                            <tr key={m.id} className={cn(!m.est_actif && 'opacity-50')}>
                                                <td>
                                                    <Icon className={cn('h-3.5 w-3.5', tdInfo ? tdInfo.color.split(' ')[1] : 'text-slate-400')} />
                                                </td>
                                                <td className="font-medium text-ink max-w-[220px] truncate" title={m.nom}>{m.nom}</td>
                                                <td className="text-slate-500 text-sm">{m.typeActeLabel}</td>
                                                <td><TypeDocBadge type={m.type_document} /></td>
                                                <td>
                                                    <span className="font-ref text-seal text-xs font-semibold">v{m.version}</span>
                                                </td>
                                                <td><CopyPathButton path={m.chemin_fichier} /></td>
                                                <td className="text-slate-400 text-xs">{m.updated_at}</td>
                                                <td className="text-center">
                                                    {can.administrer ? (
                                                        <Switch checked={m.est_actif} onCheckedChange={() => toggleActif(m)} />
                                                    ) : (
                                                        m.est_actif
                                                            ? <CheckCircle2 className="h-4 w-4 text-success mx-auto" />
                                                            : <XCircle className="h-4 w-4 text-slate-300 mx-auto" />
                                                    )}
                                                </td>
                                                {can.administrer && (
                                                    <td>
                                                        <div className="flex items-center gap-0.5">
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-ink" onClick={() => onEdit(m)}>
                                                                        <Pencil className="h-3.5 w-3.5" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>Modifier</TooltipContent>
                                                            </Tooltip>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-seal" onClick={() => dupliquer(m)}>
                                                                        <Copy className="h-3.5 w-3.5" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>Dupliquer</TooltipContent>
                                                            </Tooltip>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-danger" onClick={() => supprimer(m)}>
                                                                        <Trash2 className="h-3.5 w-3.5" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>Supprimer</TooltipContent>
                                                            </Tooltip>
                                                        </div>
                                                    </td>
                                                )}
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </Card>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

// ── Vue Cartes ───────────────────────────────────────────────────────────────

function CarteModele({ modele, can, onEdit }) {
    const tdInfo = TYPE_DOC_MAP[modele.type_document];
    const Icon = tdInfo?.icon ?? FileText;
    const [confirmState, setConfirmState] = useState(null);

    const toggleActif = () => {
        router.patch(`/modeles/${modele.id}`, { est_actif: !modele.est_actif }, { preserveScroll: true });
    };
    const supprimer = () => setConfirmState({
        title: `Supprimer "${modele.nom}" ?`,
        description: 'Ce modèle sera définitivement supprimé.',
        confirmLabel: 'Supprimer',
        variant: 'destructive',
        onConfirm: () => router.delete(`/modeles/${modele.id}`, { preserveScroll: true }),
    });
    const dupliquer = () => {
        router.post(`/modeles/${modele.id}/dupliquer`, {}, { preserveScroll: true });
    };

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
        <Card className={cn(
            'flex flex-col transition-shadow hover:shadow-md',
            !modele.est_actif && 'opacity-60'
        )}>
            <CardContent className="p-4 flex-1 flex flex-col gap-3">
                {/* Type document badge + actif indicator */}
                <div className="flex items-start justify-between gap-2">
                    <div className={cn('flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border', tdInfo?.color ?? 'bg-slate-50 border-slate-200')}>
                        <Icon className="h-4 w-4 shrink-0" />
                        <span className="text-xs font-medium">{tdInfo?.label ?? modele.type_document}</span>
                    </div>
                    <div className="flex items-center gap-1.5 mt-0.5">
                        <span className={cn(
                            'h-2 w-2 rounded-full',
                            modele.est_actif ? 'bg-success' : 'bg-slate-300'
                        )} />
                        <span className="text-[10px] text-slate-400">{modele.est_actif ? 'Actif' : 'Inactif'}</span>
                    </div>
                </div>

                {/* Nom */}
                <div>
                    <h4 className="font-medium text-sm text-ink leading-snug line-clamp-2">{modele.nom}</h4>
                    <p className="text-xs text-slate-400 mt-0.5">{modele.typeActeLabel}</p>
                </div>

                {/* Chemin + version */}
                <div className="mt-auto space-y-1.5">
                    <CopyPathButton path={modele.chemin_fichier} />
                    <div className="flex items-center justify-between">
                        <span className="font-ref text-seal text-xs font-semibold">v{modele.version}</span>
                        <span className="text-[10px] text-slate-300">{modele.updated_at}</span>
                    </div>
                </div>
            </CardContent>

            {/* Actions */}
            {can.administrer && (
                <div className="border-t border-slate-100 px-4 py-2 flex items-center gap-1">
                    <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-ink" onClick={() => onEdit(modele)}>
                        <Pencil className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-seal" onClick={dupliquer}>
                        <Copy className="h-3.5 w-3.5" />
                    </Button>
                    <div className="flex-1" />
                    <Switch checked={modele.est_actif} onCheckedChange={toggleActif} />
                    <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-danger" onClick={supprimer}>
                        <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                </div>
            )}
        </Card>
        </>
    );
}

// ── Vue Table — courriers de transmission ───────────────────────────────────

function TableModelesCourriers({ items, can, onEdit }) {
    const [confirmState, setConfirmState] = useState(null);

    const toggleActif = (m) => {
        router.patch(`/modeles-courriers/${m.id}`, { est_actif: !m.est_actif }, { preserveScroll: true });
    };
    const supprimer = (m) => setConfirmState({
        title: `Supprimer "${m.nom}" ?`,
        description: 'Ce modèle de courrier sera définitivement supprimé.',
        confirmLabel: 'Supprimer',
        variant: 'destructive',
        onConfirm: () => router.delete(`/modeles-courriers/${m.id}`, { preserveScroll: true }),
    });
    const dupliquer = (m) => {
        router.post(`/modeles-courriers/${m.id}/dupliquer`, {}, { preserveScroll: true });
    };

    if (items.length === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center py-20 text-center">
                    <Mail className="h-12 w-12 text-slate-200 mb-4" />
                    <h3 className="font-serif text-heading text-slate-500">Aucun courrier de transmission</h3>
                    <p className="text-sm text-slate-400 mt-1">
                        {can.administrer ? "Créez la première lettre de transmission." : "Aucune lettre disponible pour l'instant."}
                    </p>
                </CardContent>
            </Card>
        );
    }

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
            <Card className="overflow-hidden">
                <table className="table-notarial w-full">
                    <thead>
                        <tr>
                            <th className="w-8"></th>
                            <th>Nom</th>
                            <th>Type doc</th>
                            <th>Types d'actes liés</th>
                            <th>Version</th>
                            <th>Chemin fichier</th>
                            <th>MAJ</th>
                            <th className="text-center">Actif</th>
                            {can.administrer && <th></th>}
                        </tr>
                    </thead>
                    <tbody>
                        {items.map(m => {
                            const tdInfo = TYPE_DOC_MAP[m.type_document];
                            const Icon = tdInfo?.icon ?? FileText;
                            return (
                                <tr key={m.id} className={cn(!m.est_actif && 'opacity-50')}>
                                    <td>
                                        <Icon className={cn('h-3.5 w-3.5', tdInfo ? tdInfo.color.split(' ')[1] : 'text-slate-400')} />
                                    </td>
                                    <td className="font-medium text-ink max-w-[220px] truncate" title={m.nom}>{m.nom}</td>
                                    <td><TypeDocBadge type={m.type_document} /></td>
                                    <td className="max-w-[260px]">
                                        {m.applicable_tous ? (
                                            <span className="text-[10px] px-2 py-0.5 rounded-full font-medium bg-seal-light/30 text-seal border border-seal/20">
                                                Tous types d'actes
                                            </span>
                                        ) : (
                                            <div className="flex flex-wrap gap-1">
                                                {(m.typesActesLabels ?? []).map((label, i) => (
                                                    <span key={i} className="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-100 text-slate-500 border border-slate-200">
                                                        {label}
                                                    </span>
                                                ))}
                                                {(m.typesActesLabels ?? []).length === 0 && (
                                                    <span className="text-[10px] text-slate-300">Aucun type lié</span>
                                                )}
                                            </div>
                                        )}
                                    </td>
                                    <td>
                                        <span className="font-ref text-seal text-xs font-semibold">v{m.version}</span>
                                    </td>
                                    <td><CopyPathButton path={m.chemin_fichier} /></td>
                                    <td className="text-slate-400 text-xs">{m.updated_at}</td>
                                    <td className="text-center">
                                        {can.administrer ? (
                                            <Switch checked={m.est_actif} onCheckedChange={() => toggleActif(m)} />
                                        ) : (
                                            m.est_actif
                                                ? <CheckCircle2 className="h-4 w-4 text-success mx-auto" />
                                                : <XCircle className="h-4 w-4 text-slate-300 mx-auto" />
                                        )}
                                    </td>
                                    {can.administrer && (
                                        <td>
                                            <div className="flex items-center gap-0.5">
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-ink" onClick={() => onEdit(m)}>
                                                            <Pencil className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>Modifier</TooltipContent>
                                                </Tooltip>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-seal" onClick={() => dupliquer(m)}>
                                                            <Copy className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>Dupliquer</TooltipContent>
                                                </Tooltip>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-danger" onClick={() => supprimer(m)}>
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>Supprimer</TooltipContent>
                                                </Tooltip>
                                            </div>
                                        </td>
                                    )}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </Card>
        </>
    );
}

// ── Page principale ──────────────────────────────────────────────────────────

export default function ModelesIndex() {
    const {
        modeles = [], modelesCourriers = [], typesActes = [], categories = [],
        filters = {}, stats = {}, auth,
    } = usePage().props;
    const can = auth?.user?.can ?? {};

    const [tab, setTab]               = useState('actes'); // 'actes' | 'courriers'
    const [search, setSearch]         = useState(filters.q             ?? '');
    const [categorie, setCategorie]   = useState(filters.categorie     ?? '');
    const [typeDoc, setTypeDoc]       = useState(filters.type_document ?? '');
    const [statut, setStatut]         = useState(filters.statut        ?? '');
    const [sort, setSort]             = useState(filters.sort          ?? 'nom');
    const [vue, setVue]               = useState('table'); // 'table' | 'cartes'
    const [modalOpen, setModalOpen]   = useState(false);
    const [editModele, setEditModele] = useState(null);
    const [courrierModalOpen, setCourrierModalOpen] = useState(false);
    const [editCourrier, setEditCourrier]           = useState(null);

    const applyFilters = (overrides = {}) => {
        router.get('/modeles', {
            q:             overrides.q             !== undefined ? overrides.q             : search,
            categorie:     overrides.categorie     !== undefined ? overrides.categorie     : categorie,
            type_document: overrides.type_document !== undefined ? overrides.type_document : typeDoc,
            statut:        overrides.statut        !== undefined ? overrides.statut        : statut,
            sort:          overrides.sort          !== undefined ? overrides.sort          : sort,
        }, { preserveState: true, replace: true });
    };

    const resetFiltres = () => {
        setSearch(''); setCategorie(''); setTypeDoc(''); setStatut(''); setSort('nom');
        router.get('/modeles', {}, { preserveState: true, replace: true });
    };

    const hasFiltres = search || categorie || typeDoc || statut || sort !== 'nom';

    const openEdit = (m) => { setEditModele(m); setModalOpen(true); };
    const closeModal = () => { setModalOpen(false); setEditModele(null); };

    const openEditCourrier = (m) => { setEditCourrier(m); setCourrierModalOpen(true); };
    const closeCourrierModal = () => { setCourrierModalOpen(false); setEditCourrier(null); };

    // Grouper par catégorie
    const grouped = modeles.reduce((acc, m) => {
        const key = m.categorieLabel ?? 'Autre';
        if (!acc[key]) acc[key] = [];
        acc[key].push(m);
        return acc;
    }, {});

    const totalType = TYPES_DOC.reduce((s, t) => s + (stats.parTypeDoc?.[t.value] ?? 0), 0) || 1;

    return (
        <AppLayout breadcrumbs={[{ label: "Modèles d'actes" }]}>
                <Head title="Modèles d'actes — Ayelema" />

                <div className="p-6 max-w-[1300px] mx-auto space-y-5">

                    {/* ── En-tête ──────────────────────────────────────────── */}
                    <div className="flex items-end justify-between gap-4">
                        <div>
                            <h1 className="font-serif text-display text-ink">Modèles d'actes</h1>
                            <p className="text-slate-500 text-sm mt-1">
                                Bibliothèque des {stats.total ?? 0} modèle{(stats.total ?? 0) > 1 ? 's' : ''} de documents notariaux
                            </p>
                        </div>
                        {can.administrer && (
                            tab === 'actes' ? (
                                <Button variant="seal" onClick={() => { setEditModele(null); setModalOpen(true); }}>
                                    <Plus className="h-4 w-4" />
                                    Nouveau modèle
                                </Button>
                            ) : (
                                <Button variant="seal" onClick={() => { setEditCourrier(null); setCourrierModalOpen(true); }}>
                                    <Plus className="h-4 w-4" />
                                    Nouveau courrier
                                </Button>
                            )
                        )}
                    </div>

                    <Tabs value={tab} onValueChange={setTab}>
                        <TabsList>
                            <TabsTrigger value="actes">Actes</TabsTrigger>
                            <TabsTrigger value="courriers">
                                Courriers de transmission
                                {modelesCourriers.length > 0 && (
                                    <span className="ml-1.5 text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded-full">{modelesCourriers.length}</span>
                                )}
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="actes" className="space-y-5 pt-4">

                    {/* ── Stats ────────────────────────────────────────────── */}
                    <div className="grid grid-cols-1 lg:grid-cols-5 gap-3">
                        {/* KPIs */}
                        <div className="lg:col-span-2 grid grid-cols-3 gap-3">
                            {[
                                { label: 'Total',    value: stats.total    ?? 0, color: 'text-ink' },
                                { label: 'Actifs',   value: stats.actifs   ?? 0, color: 'text-success' },
                                { label: 'Inactifs', value: stats.inactifs ?? 0, color: 'text-slate-400' },
                            ].map(k => (
                                <Card key={k.label} className="p-3 text-center">
                                    <div className={cn('text-2xl font-bold', k.color)}>{k.value}</div>
                                    <div className="text-[10px] text-slate-400 mt-0.5 uppercase tracking-wide">{k.label}</div>
                                </Card>
                            ))}
                        </div>

                        {/* Répartition par type document */}
                        <Card className="lg:col-span-3 p-4">
                            <div className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Répartition par type de document</div>
                            <div className="space-y-2">
                                {TYPES_DOC.map(t => {
                                    const count = stats.parTypeDoc?.[t.value] ?? 0;
                                    const pct = Math.round((count / totalType) * 100);
                                    const Icon = t.icon;
                                    return (
                                        <div key={t.value} className="flex items-center gap-2">
                                            <Icon className={cn('h-3.5 w-3.5 shrink-0', t.color.split(' ')[1])} />
                                            <span className="text-xs text-slate-600 w-28 shrink-0">{t.label}</span>
                                            <div className="flex-1 bg-slate-100 rounded-full h-1.5 overflow-hidden">
                                                <motion.div
                                                    className={cn('h-full rounded-full', t.color.split(' ')[0])}
                                                    initial={{ width: 0 }}
                                                    animate={{ width: `${pct}%` }}
                                                    transition={{ duration: 0.6, delay: 0.1 }}
                                                />
                                            </div>
                                            <span className="text-xs font-ref text-slate-500 w-6 text-right">{count}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </Card>
                    </div>

                    {/* ── Filtres ───────────────────────────────────────────── */}
                    <div className="flex flex-wrap gap-2 items-center">
                        <div className="relative flex-1 min-w-[180px] max-w-xs">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                            <Input
                                className="pl-9 h-8 text-sm"
                                placeholder="Rechercher un modèle…"
                                value={search}
                                onChange={e => setSearch(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && applyFilters({ q: search })}
                            />
                        </div>

                        <Select value={categorie} onValueChange={v => { setCategorie(v); applyFilters({ categorie: v }); }}>
                            <SelectTrigger className="h-8 text-sm w-44">
                                <SelectValue placeholder="Toutes catégories" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Toutes catégories</SelectItem>
                                {categories.map(c => <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>)}
                            </SelectContent>
                        </Select>

                        <Select value={typeDoc} onValueChange={v => { setTypeDoc(v); applyFilters({ type_document: v }); }}>
                            <SelectTrigger className="h-8 text-sm w-40">
                                <SelectValue placeholder="Tout type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Tout type</SelectItem>
                                {TYPES_DOC.map(t => <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>)}
                            </SelectContent>
                        </Select>

                        <Select value={statut} onValueChange={v => { setStatut(v); applyFilters({ statut: v }); }}>
                            <SelectTrigger className="h-8 text-sm w-32">
                                <SelectValue placeholder="Statut" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Tous</SelectItem>
                                <SelectItem value="actif">Actifs</SelectItem>
                                <SelectItem value="inactif">Inactifs</SelectItem>
                            </SelectContent>
                        </Select>

                        <Select value={sort} onValueChange={v => { setSort(v); applyFilters({ sort: v }); }}>
                            <SelectTrigger className="h-8 text-sm w-40">
                                <ArrowUpDown className="h-3.5 w-3.5 mr-1 text-slate-400" />
                                <SelectValue placeholder="Trier par" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="nom">Trier par nom</SelectItem>
                                <SelectItem value="date">Trier par date</SelectItem>
                            </SelectContent>
                        </Select>

                        {hasFiltres && (
                            <Button variant="ghost" size="sm" className="h-8 text-slate-400 hover:text-slate-600" onClick={resetFiltres}>
                                <X className="h-3.5 w-3.5 mr-1" /> Réinitialiser
                            </Button>
                        )}

                        <div className="ml-auto flex items-center gap-1 border border-slate-200 rounded-lg p-0.5 bg-white">
                            <button
                                onClick={() => setVue('table')}
                                className={cn('p-1.5 rounded-md transition-colors', vue === 'table' ? 'bg-ink text-white' : 'text-slate-400 hover:text-slate-600')}
                            >
                                <List className="h-4 w-4" />
                            </button>
                            <button
                                onClick={() => setVue('cartes')}
                                className={cn('p-1.5 rounded-md transition-colors', vue === 'cartes' ? 'bg-ink text-white' : 'text-slate-400 hover:text-slate-600')}
                            >
                                <LayoutGrid className="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    {/* ── Contenu ───────────────────────────────────────────── */}
                    {modeles.length === 0 ? (
                        <Card>
                            <CardContent className="flex flex-col items-center justify-center py-20 text-center">
                                <FileText className="h-12 w-12 text-slate-200 mb-4" />
                                <h3 className="font-serif text-heading text-slate-500">Aucun modèle trouvé</h3>
                                <p className="text-sm text-slate-400 mt-1">
                                    {hasFiltres ? 'Aucun résultat pour ces filtres.' : can.administrer ? "Créez le premier modèle d'acte." : "Aucun modèle disponible pour l'instant."}
                                </p>
                                {hasFiltres && (
                                    <Button variant="outline" size="sm" className="mt-4" onClick={resetFiltres}>
                                        <X className="h-3.5 w-3.5 mr-1" /> Effacer les filtres
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    ) : vue === 'table' ? (
                        /* Vue TABLE groupée par catégorie */
                        <div>
                            {Object.entries(grouped).map(([cat, items], gi) => (
                                <motion.div
                                    key={cat}
                                    initial={{ opacity: 0, y: 5 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ delay: gi * 0.04 }}
                                >
                                    <GroupeTable
                                        categorieLabel={cat}
                                        items={items}
                                        can={can}
                                        onEdit={openEdit}
                                    />
                                </motion.div>
                            ))}
                        </div>
                    ) : (
                        /* Vue CARTES */
                        <div>
                            {Object.entries(grouped).map(([cat, items], gi) => (
                                <motion.div
                                    key={cat}
                                    initial={{ opacity: 0, y: 5 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ delay: gi * 0.04 }}
                                    className="mb-6"
                                >
                                    <div className="flex items-center gap-2 mb-3">
                                        <span className="text-xs font-semibold text-slate-400 uppercase tracking-wider">{cat}</span>
                                        <span className="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded-full">{items.length}</span>
                                    </div>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3">
                                        {items.map((m, i) => (
                                            <motion.div
                                                key={m.id}
                                                initial={{ opacity: 0, scale: 0.97 }}
                                                animate={{ opacity: 1, scale: 1 }}
                                                transition={{ delay: i * 0.04 }}
                                            >
                                                <CarteModele modele={m} can={can} onEdit={openEdit} />
                                            </motion.div>
                                        ))}
                                    </div>
                                </motion.div>
                            ))}
                        </div>
                    )}

                        </TabsContent>

                        <TabsContent value="courriers" className="pt-4">
                            <TableModelesCourriers items={modelesCourriers} can={can} onEdit={openEditCourrier} />
                        </TabsContent>
                    </Tabs>
                </div>

                <ModalModele
                    open={modalOpen}
                    onClose={closeModal}
                    typesActes={typesActes}
                    modele={editModele}
                />

                <ModalModeleCourrier
                    open={courrierModalOpen}
                    onClose={closeCourrierModal}
                    typesActes={typesActes}
                    categories={categories}
                    modele={editCourrier}
                />
            </AppLayout>
    );
}
