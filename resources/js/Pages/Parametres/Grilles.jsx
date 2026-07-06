import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    ClipboardCheck, Plus, Trash2, Pencil, ChevronDown, ChevronUp,
    GripVertical, X, Check, Minus,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

// ── Helpers ──────────────────────────────────────────────────────────────────

function tmpId() {
    return 'new-' + Math.random().toString(36).slice(2, 9);
}

/** Parse points plats [{id, groupe, label, requis}] → groups [{label, points}] */
function pointsToGroups(points) {
    if (!points?.length) return [{ id: tmpId(), label: 'Groupe 1', points: [] }];
    const map = {};
    const order = [];
    for (const p of points) {
        if (!map[p.groupe]) { map[p.groupe] = []; order.push(p.groupe); }
        map[p.groupe].push({ id: p.id, label: p.label, requis: p.requis !== false, piece: p.piece || '' });
    }
    return order.map(g => ({ id: tmpId(), label: g, points: map[g] }));
}

/** Groups → points plats */
function groupsToPoints(groups) {
    return groups.flatMap(g =>
        g.points.map(p => ({
            id:     p.id?.startsWith('new-') ? undefined : p.id,
            groupe: g.label,
            label:  p.label,
            requis: p.requis,
            piece:  p.piece || null,
        }))
    );
}

// ── Éditeur de grille (modale) ────────────────────────────────────────────────

function ModalGrille({ open, onClose, typesActes, grille = null }) {
    const isEdit = !!grille;

    const [typeActeId, setTypeActeId] = useState('');
    const [version, setVersion]       = useState('1.0');
    const [groups, setGroups]         = useState([]);
    const [processing, setProcessing] = useState(false);

    React.useEffect(() => {
        if (!open) return;
        if (grille) {
            setTypeActeId(String(grille.type_acte_id));
            setVersion(grille.version);
            setGroups(pointsToGroups(grille.points));
        } else {
            setTypeActeId('');
            setVersion('1.0');
            setGroups([{ id: tmpId(), label: 'Groupe 1', points: [] }]);
        }
    }, [open, grille]);

    // ── Actions groupes ────────────────────────────────────────────────────

    const addGroupe = () =>
        setGroups(gs => [...gs, { id: tmpId(), label: `Groupe ${gs.length + 1}`, points: [] }]);

    const removeGroupe = (gid) =>
        setGroups(gs => gs.filter(g => g.id !== gid));

    const updateGroupeLabel = (gid, label) =>
        setGroups(gs => gs.map(g => g.id === gid ? { ...g, label } : g));

    // ── Actions points ─────────────────────────────────────────────────────

    const addPoint = (gid) =>
        setGroups(gs => gs.map(g => g.id !== gid ? g : {
            ...g, points: [...g.points, { id: tmpId(), label: '', requis: true, piece: '' }]
        }));

    const removePoint = (gid, pid) =>
        setGroups(gs => gs.map(g => g.id !== gid ? g : {
            ...g, points: g.points.filter(p => p.id !== pid)
        }));

    const updatePoint = (gid, pid, field, value) =>
        setGroups(gs => gs.map(g => g.id !== gid ? g : {
            ...g, points: g.points.map(p => p.id !== pid ? p : { ...p, [field]: value })
        }));

    const movePoint = (gid, pid, dir) => {
        setGroups(gs => gs.map(g => {
            if (g.id !== gid) return g;
            const idx = g.points.findIndex(p => p.id === pid);
            const newIdx = idx + dir;
            if (newIdx < 0 || newIdx >= g.points.length) return g;
            const pts = [...g.points];
            [pts[idx], pts[newIdx]] = [pts[newIdx], pts[idx]];
            return { ...g, points: pts };
        }));
    };

    // ── Soumission ─────────────────────────────────────────────────────────

    const totalPoints = groups.reduce((n, g) => n + g.points.length, 0);

    const submit = () => {
        const points = groupsToPoints(groups);
        setProcessing(true);

        const opts = {
            onSuccess: () => { onClose(); setProcessing(false); },
            onError:   () => setProcessing(false),
        };

        if (isEdit) {
            router.put(`/parametres/grilles/${grille.id}`, { version, points }, opts);
        } else {
            router.post('/parametres/grilles', { type_acte_id: typeActeId, version, points }, opts);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] flex flex-col">
                <DialogHeader className="shrink-0">
                    <DialogTitle className="font-serif text-ink">
                        {isEdit ? 'Modifier la grille' : 'Nouvelle grille de révision'}
                    </DialogTitle>
                </DialogHeader>

                <div className="flex-1 overflow-y-auto pr-1 space-y-5 py-2">

                    {/* Type acte + version */}
                    <div className="grid grid-cols-3 gap-3">
                        <div className="col-span-2 space-y-1.5">
                            <Label>Type d'acte</Label>
                            {isEdit ? (
                                <p className="text-sm font-medium text-ink px-3 py-2 bg-slate-50 rounded-md border border-slate-200">
                                    {grille?.typeActeLabel}
                                </p>
                            ) : (
                                <Select value={typeActeId} onValueChange={setTypeActeId}>
                                    <SelectTrigger><SelectValue placeholder="Choisir un type d'acte…" /></SelectTrigger>
                                    <SelectContent className="max-h-60">
                                        {typesActes?.map(t => (
                                            <SelectItem key={t.id} value={String(t.id)}>{t.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Version</Label>
                            <Input
                                value={version}
                                onChange={e => setVersion(e.target.value)}
                                placeholder="1.0"
                            />
                        </div>
                    </div>

                    {/* Groupes */}
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <Label className="text-sm font-semibold">
                                Points de contrôle
                                <span className="ml-2 text-xs font-normal text-slate-400">
                                    {totalPoints} point{totalPoints > 1 ? 's' : ''} au total
                                </span>
                            </Label>
                            <Button variant="outline" size="sm" className="h-7 text-xs" onClick={addGroupe}>
                                <Plus className="h-3.5 w-3.5" /> Ajouter un groupe
                            </Button>
                        </div>

                        {groups.map((groupe, gi) => (
                            <div key={groupe.id} className="border border-slate-200 rounded-lg overflow-hidden">
                                {/* En-tête groupe */}
                                <div className="flex items-center gap-2 px-3 py-2 bg-slate-50 border-b border-slate-200">
                                    <GripVertical className="h-4 w-4 text-slate-300 shrink-0" />
                                    <Input
                                        value={groupe.label}
                                        onChange={e => updateGroupeLabel(groupe.id, e.target.value)}
                                        className="h-7 text-xs font-semibold bg-white border-slate-200 flex-1"
                                        placeholder="Nom du groupe…"
                                    />
                                    <Button
                                        variant="ghost" size="icon-sm"
                                        className="text-slate-300 hover:text-danger shrink-0"
                                        onClick={() => removeGroupe(groupe.id)}
                                        disabled={groups.length === 1}
                                    >
                                        <X className="h-3.5 w-3.5" />
                                    </Button>
                                </div>

                                {/* Points */}
                                <div className="divide-y divide-slate-100">
                                    {groupe.points.map((point, pi) => (
                                        <div key={point.id} className="flex items-start gap-2 px-3 py-2.5">
                                            <div className="flex flex-col gap-0.5 shrink-0 mt-1">
                                                <button
                                                    onClick={() => movePoint(groupe.id, point.id, -1)}
                                                    disabled={pi === 0}
                                                    className="text-slate-300 hover:text-slate-500 disabled:opacity-20"
                                                >
                                                    <ChevronUp className="h-3 w-3" />
                                                </button>
                                                <button
                                                    onClick={() => movePoint(groupe.id, point.id, 1)}
                                                    disabled={pi === groupe.points.length - 1}
                                                    className="text-slate-300 hover:text-slate-500 disabled:opacity-20"
                                                >
                                                    <ChevronDown className="h-3 w-3" />
                                                </button>
                                            </div>

                                            <Input
                                                value={point.label}
                                                onChange={e => updatePoint(groupe.id, point.id, 'label', e.target.value)}
                                                className="h-7 text-xs flex-1"
                                                placeholder="Libellé du point de contrôle…"
                                            />

                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <button
                                                        onClick={() => updatePoint(groupe.id, point.id, 'requis', !point.requis)}
                                                        className={cn(
                                                            'shrink-0 flex items-center gap-1 px-2 py-1 rounded text-[10px] font-medium border transition-colors',
                                                            point.requis
                                                                ? 'bg-ink/5 text-ink border-ink/20'
                                                                : 'bg-slate-50 text-slate-400 border-slate-200'
                                                        )}
                                                    >
                                                        {point.requis ? <Check className="h-3 w-3" /> : <Minus className="h-3 w-3" />}
                                                        {point.requis ? 'Requis' : 'Optionnel'}
                                                    </button>
                                                </TooltipTrigger>
                                                <TooltipContent>Cliquer pour basculer requis / optionnel</TooltipContent>
                                            </Tooltip>

                                            <Button
                                                variant="ghost" size="icon-sm"
                                                className="text-slate-300 hover:text-danger shrink-0"
                                                onClick={() => removePoint(groupe.id, point.id)}
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    ))}

                                    <button
                                        onClick={() => addPoint(groupe.id)}
                                        className="w-full text-xs text-slate-400 hover:text-seal hover:bg-seal-light/30 py-2.5 flex items-center justify-center gap-1.5 transition-colors"
                                    >
                                        <Plus className="h-3.5 w-3.5" />
                                        Ajouter un point dans ce groupe
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                <DialogFooter className="shrink-0 pt-2 border-t border-slate-100 mt-2">
                    <Button variant="outline" onClick={onClose}>Annuler</Button>
                    <Button
                        variant="seal"
                        onClick={submit}
                        disabled={processing || !totalPoints || (!isEdit && !typeActeId)}
                    >
                        {isEdit ? 'Enregistrer les modifications' : 'Créer la grille'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

// ── Ligne grille dans la table ────────────────────────────────────────────────

function LigneGrille({ grille, onEdit }) {
    const [confirmState, setConfirmState] = useState(null);

    const toggleActif = () => {
        router.put(`/parametres/grilles/${grille.id}`, { est_active: !grille.est_active }, { preserveScroll: true });
    };

    const supprimer = () => setConfirmState({
        title:        `Supprimer la grille "${grille.typeActeLabel}" v${grille.version} ?`,
        description:  'Cette grille sera définitivement supprimée. Si elle était active, le dossier utilisera la grille par défaut.',
        confirmLabel: 'Supprimer',
        variant:      'destructive',
        onConfirm:    () => router.delete(`/parametres/grilles/${grille.id}`, { preserveScroll: true }),
    });

    const nPoints = grille.points?.length ?? 0;
    const nGroupes = grille.points
        ? [...new Set(grille.points.map(p => p.groupe))].length
        : 0;

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
            <tr className={cn(!grille.est_active && 'opacity-50')}>
                <td className="font-medium text-ink">{grille.typeActeLabel}</td>
                <td>
                    <span className="font-ref text-seal text-xs font-semibold">v{grille.version}</span>
                </td>
                <td>
                    <span className="text-xs text-slate-500">
                        {nPoints} point{nPoints > 1 ? 's' : ''}
                        {nGroupes > 0 && ` · ${nGroupes} groupe${nGroupes > 1 ? 's' : ''}`}
                    </span>
                </td>
                <td className="text-center">
                    <Switch checked={grille.est_active} onCheckedChange={toggleActif} />
                </td>
                <td>
                    <div className="flex items-center gap-0.5">
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-ink" onClick={() => onEdit(grille)}>
                                    <Pencil className="h-3.5 w-3.5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Modifier</TooltipContent>
                        </Tooltip>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-danger" onClick={supprimer}>
                                    <Trash2 className="h-3.5 w-3.5" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>Supprimer</TooltipContent>
                        </Tooltip>
                    </div>
                </td>
            </tr>
        </>
    );
}

// ── Groupe par catégorie ──────────────────────────────────────────────────────

const CATEGORIE_LABELS = {
    societe:    'Sociétés',
    vente:      'Ventes immobilières',
    hypotheque: 'Hypothèques',
    bail:       'Baux',
    donation:   'Donations',
    succession: 'Successions',
    procuration:'Procurations',
    courrier:   'Courriers',
};

function GroupeCategorie({ cat, items, onEdit }) {
    const [open, setOpen] = useState(true);

    return (
        <div>
            <button
                onClick={() => setOpen(o => !o)}
                className="flex items-center gap-2 mb-2 group"
            >
                <span className="text-xs font-semibold text-slate-400 uppercase tracking-wider group-hover:text-slate-600 transition-colors">
                    {CATEGORIE_LABELS[cat] ?? cat}
                </span>
                <span className="text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded-full">{items.length}</span>
                {open
                    ? <ChevronUp className="h-3 w-3 text-slate-300" />
                    : <ChevronDown className="h-3 w-3 text-slate-300" />}
            </button>

            <AnimatePresence initial={false}>
                {open && (
                    <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.18 }}
                        className="overflow-hidden mb-4"
                    >
                        <Card className="overflow-hidden">
                            <table className="table-notarial w-full">
                                <thead>
                                    <tr>
                                        <th>Type d'acte</th>
                                        <th>Version</th>
                                        <th>Points</th>
                                        <th className="text-center">Active</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map(g => (
                                        <LigneGrille key={g.id} grille={g} onEdit={onEdit} />
                                    ))}
                                </tbody>
                            </table>
                        </Card>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

// ── Aperçu d'une grille (expansion) ──────────────────────────────────────────

// ── Page principale ───────────────────────────────────────────────────────────

export default function GrillesIndex() {
    const { grilles = [], typesActes = [] } = usePage().props;

    const [modalOpen, setModalOpen]   = useState(false);
    const [editGrille, setEditGrille] = useState(null);

    const openCreate = () => { setEditGrille(null); setModalOpen(true); };
    const openEdit   = (g)  => { setEditGrille(g);  setModalOpen(true); };
    const closeModal = ()   => { setModalOpen(false); setEditGrille(null); };

    const totalActifs  = grilles.filter(g => g.est_active).length;
    const typesCouvert = [...new Set(grilles.filter(g => g.est_active).map(g => g.type_acte_id))].length;

    // Grouper les grilles par catégorie (via typesActes lookup)
    const typeMap = Object.fromEntries(typesActes.map(t => [t.id, t]));
    const grouped = grilles.reduce((acc, g) => {
        const cat = typeMap[g.type_acte_id]?.categorie ?? 'autre';
        if (!acc[cat]) acc[cat] = [];
        acc[cat].push(g);
        return acc;
    }, {});

    return (
        <AppLayout breadcrumbs={[
            { label: 'Paramètres', href: '/parametres' },
            { label: 'Grilles de révision' },
        ]}>
            <Head title="Grilles de révision — Paramètres" />

            <div className="p-6 max-w-[1100px] mx-auto space-y-5">

                {/* En-tête */}
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-display text-ink">Grilles de révision</h1>
                        <p className="text-slate-500 text-sm mt-1">
                            Points de contrôle bloquants avant la signature, configurables par type d'acte
                        </p>
                    </div>
                    <Button variant="seal" onClick={openCreate}>
                        <Plus className="h-4 w-4" />
                        Nouvelle grille
                    </Button>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-3 gap-3">
                    {[
                        { label: 'Grilles configurées', value: grilles.length,  color: 'text-ink' },
                        { label: 'Grilles actives',     value: totalActifs,     color: 'text-success' },
                        { label: 'Types d\'actes couverts', value: typesCouvert, color: 'text-seal' },
                    ].map(k => (
                        <Card key={k.label} className="p-4 text-center">
                            <div className={cn('text-3xl font-bold', k.color)}>{k.value}</div>
                            <div className="text-[10px] text-slate-400 mt-0.5 uppercase tracking-wide">{k.label}</div>
                        </Card>
                    ))}
                </div>

                {/* Contenu */}
                {grilles.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-20 text-center">
                            <ClipboardCheck className="h-12 w-12 text-slate-200 mb-4" />
                            <h3 className="font-serif text-heading text-slate-500">Aucune grille configurée</h3>
                            <p className="text-sm text-slate-400 mt-1 max-w-sm">
                                Sans grille personnalisée, les dossiers utilisent la grille par défaut (7 points génériques).
                                Créez une grille pour adapter les points de contrôle à chaque type d'acte.
                            </p>
                            <Button variant="seal" className="mt-5" onClick={openCreate}>
                                <Plus className="h-4 w-4" /> Créer la première grille
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div>
                        {Object.entries(grouped).map(([cat, items], gi) => (
                            <motion.div
                                key={cat}
                                initial={{ opacity: 0, y: 5 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: gi * 0.04 }}
                            >
                                <GroupeCategorie cat={cat} items={items} onEdit={openEdit} />
                            </motion.div>
                        ))}
                    </div>
                )}

                {/* Bloc info grille par défaut */}
                <Card className="bg-slate-50 border-slate-200">
                    <CardContent className="p-4 flex items-start gap-3">
                        <ClipboardCheck className="h-5 w-5 text-slate-400 shrink-0 mt-0.5" />
                        <div>
                            <p className="text-sm font-medium text-slate-600">Grille par défaut</p>
                            <p className="text-xs text-slate-400 mt-0.5">
                                Utilisée pour les types d'actes sans grille active. Contient 7 points répartis en 3 groupes :
                                <em> Identité et parties</em>, <em>Contenu et mentions légales</em>, <em>Conformité légale</em>.
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <ModalGrille
                open={modalOpen}
                onClose={closeModal}
                typesActes={typesActes}
                grille={editGrille}
            />
        </AppLayout>
    );
}
