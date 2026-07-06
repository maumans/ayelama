import { useState, useMemo, useEffect, useRef } from 'react';
import { Head, router } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import AppLayout from '@/Layouts/AppLayout';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import {
    AlertTriangle, Check, CheckCircle, CheckCircle2, ChevronDown, ChevronUp,
    ClipboardList, FileText, GripVertical, Palette, Pencil, Percent, Plus,
    Scale, Search, Settings, Shield, Trash2, Upload, Users, X, XCircle,
} from 'lucide-react';

/* ═══════════════════════════════════════════════════════════
   Metadata
═══════════════════════════════════════════════════════════ */

const ROLE_META = {
    administrateur: { label: 'Admin',     cls: 'bg-amber-50 text-amber-700 border-amber-200' },
    notaire:        { label: 'Notaire',   cls: 'bg-ink/5 text-ink border-ink/20' },
    reviseur:       { label: 'Réviseur',  cls: 'bg-purple-50 text-purple-700 border-purple-200' },
    clerc:          { label: 'Clerc',     cls: 'bg-blue-50 text-blue-700 border-blue-200' },
    formaliste:     { label: 'Formaliste',cls: 'bg-orange-50 text-orange-700 border-orange-200' },
};

const CAT_BAR = {
    immobilier: 'bg-blue-500',
    societe:    'bg-emerald-500',
    famille:    'bg-pink-500',
    succession: 'bg-purple-500',
    commercial: 'bg-amber-500',
    autre:      'bg-slate-400',
};

const CAT_BADGE = {
    immobilier: 'bg-blue-50 text-blue-700 border-blue-200',
    societe:    'bg-emerald-50 text-emerald-700 border-emerald-200',
    famille:    'bg-pink-50 text-pink-700 border-pink-200',
    succession: 'bg-purple-50 text-purple-700 border-purple-200',
    commercial: 'bg-amber-50 text-amber-700 border-amber-200',
    autre:      'bg-slate-100 text-slate-600 border-slate-200',
};

const ORGANISME_COLORS = {
    APIP:         'bg-blue-50 text-blue-700',
    Impots:       'bg-amber-50 text-amber-800',
    Conservation: 'bg-green-50 text-green-700',
    CNSS:         'bg-purple-50 text-purple-700',
    Notaire:      'bg-ink/10 text-ink',
    Autre:        'bg-slate-100 text-slate-600',
};

const TABS = [
    { id: 'utilisateurs', label: 'Utilisateurs',       icon: Users         },
    { id: 'types-actes',  label: "Types d'actes",      icon: FileText      },
    { id: 'baremes',      label: 'Barèmes & Taux',     icon: Scale         },
    { id: 'grilles',      label: 'Grilles de révision',icon: ClipboardList },
    { id: 'apparence',    label: 'Apparence',          icon: Palette       },
];

const CAT_BADGE_ALL = {
    societe:         'bg-emerald-50 text-emerald-700 border-emerald-200',
    vente:           'bg-blue-50 text-blue-700 border-blue-200',
    hypotheque:      'bg-cyan-50 text-cyan-700 border-cyan-200',
    bail:            'bg-orange-50 text-orange-700 border-orange-200',
    donation:        'bg-pink-50 text-pink-700 border-pink-200',
    succession:      'bg-purple-50 text-purple-700 border-purple-200',
    mariage:         'bg-rose-50 text-rose-700 border-rose-200',
    testament:       'bg-violet-50 text-violet-700 border-violet-200',
    procuration:     'bg-sky-50 text-sky-700 border-sky-200',
    courrier:        'bg-slate-100 text-slate-600 border-slate-200',
    prise_en_charge: 'bg-teal-50 text-teal-700 border-teal-200',
};

/* ═══════════════════════════════════════════════════════════
   Shared helpers
═══════════════════════════════════════════════════════════ */

function HealthBar({ label, value, max, barColor }) {
    const pct = max > 0 ? Math.round((value / max) * 100) : 0;
    const textCls = pct === 100 ? 'text-green-600' : pct >= 60 ? 'text-amber-600' : 'text-red-500';
    return (
        <div className="flex items-center gap-3">
            <div className="flex-1 min-w-0">
                <div className="flex items-center justify-between mb-1.5">
                    <span className="text-xs text-slate-600">{label}</span>
                    <span className={`text-xs font-medium tabular-nums ${textCls}`}>{value}/{max}</span>
                </div>
                <div className="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <motion.div className={`h-full rounded-full ${barColor}`}
                        initial={{ width: 0 }} animate={{ width: `${pct}%` }}
                        transition={{ duration: 0.7, ease: 'easeOut' }} />
                </div>
            </div>
            <span className={`text-xs font-semibold w-9 text-right tabular-nums ${textCls}`}>{pct}%</span>
        </div>
    );
}

/* ═══════════════════════════════════════════════════════════
   Tab: Utilisateurs
═══════════════════════════════════════════════════════════ */

const EMPTY_USER_FORM = { name: '', email: '', password: '', role: '', initiales: '', telephone: '' };

function ModalUtilisateur({ open, onClose, utilisateur, roles }) {
    const isEdit = Boolean(utilisateur);
    const [form, setForm] = useState(EMPTY_USER_FORM);

    useEffect(() => {
        if (open) {
            setForm(isEdit ? {
                name: utilisateur.name ?? '', email: utilisateur.email ?? '',
                password: '', role: utilisateur.role ?? '',
                initiales: utilisateur.initiales ?? '', telephone: utilisateur.telephone ?? '',
            } : EMPTY_USER_FORM);
        }
    }, [open, utilisateur?.id]);

    const f = (k) => (e) => setForm(p => ({ ...p, [k]: typeof e === 'string' ? e : e.target.value }));

    const submit = (e) => {
        e.preventDefault();
        const payload = { ...form };
        if (isEdit && !payload.password) delete payload.password;
        const opts = { onSuccess: onClose, preserveState: true };
        if (isEdit) router.patch(`/parametres/utilisateurs/${utilisateur.id}`, payload, opts);
        else router.post('/parametres/utilisateurs', payload, { onSuccess: onClose });
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{isEdit ? "Modifier l'utilisateur" : 'Nouvel utilisateur'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="col-span-2 space-y-1">
                            <Label>Nom complet</Label>
                            <Input value={form.name} onChange={f('name')} required />
                        </div>
                        <div className="col-span-2 space-y-1">
                            <Label>Email</Label>
                            <Input type="email" value={form.email} onChange={f('email')} required />
                        </div>
                        <div className="col-span-2 space-y-1">
                            <Label>
                                {isEdit ? 'Nouveau mot de passe' : 'Mot de passe'}
                                {isEdit && <span className="text-slate-400 text-[11px] ml-1">(laisser vide pour conserver)</span>}
                            </Label>
                            <Input type="password" value={form.password} onChange={f('password')} required={!isEdit} autoComplete="new-password" />
                        </div>
                        <div className="space-y-1">
                            <Label>Rôle</Label>
                            <Select value={form.role} onValueChange={f('role')}>
                                <SelectTrigger><SelectValue placeholder="Choisir…" /></SelectTrigger>
                                <SelectContent>
                                    {roles?.map(r => <SelectItem key={r.value} value={r.value}>{r.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label>Initiales</Label>
                            <Input value={form.initiales}
                                onChange={e => setForm(p => ({ ...p, initiales: e.target.value.toUpperCase().slice(0, 3) }))}
                                maxLength={3} className="font-mono uppercase" placeholder="AB" />
                        </div>
                        <div className="col-span-2 space-y-1">
                            <Label>Téléphone</Label>
                            <Input value={form.telephone} onChange={f('telephone')} placeholder="+224 620 000 000" />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit">{isEdit ? 'Enregistrer' : 'Créer'}</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function TabUtilisateurs({ utilisateurs = [], roles = [] }) {
    const [modal, setModal]     = useState({ open: false, user: null });
    const [search, setSearch]   = useState('');
    const [filterRole, setRole] = useState('');
    const [filterActif, setActif] = useState('');

    const toggleActif = (u) => router.patch(`/parametres/utilisateurs/${u.id}`, { actif: !u.actif }, { preserveState: true });

    const rolesPresents = useMemo(() => {
        const seen = new Set(utilisateurs.map(u => u.role).filter(Boolean));
        return roles.filter(r => seen.has(r.value));
    }, [utilisateurs, roles]);

    const filtered = useMemo(() => {
        const q = search.toLowerCase();
        return utilisateurs.filter(u => {
            if (q && !u.name?.toLowerCase().includes(q) && !u.email?.toLowerCase().includes(q)) return false;
            if (filterRole && u.role !== filterRole) return false;
            if (filterActif === 'actif' && !u.actif) return false;
            if (filterActif === 'inactif' && u.actif) return false;
            return true;
        });
    }, [utilisateurs, search, filterRole, filterActif]);

    const actifCount   = utilisateurs.filter(u => u.actif).length;
    const inactifCount = utilisateurs.length - actifCount;

    return (
        <div className="space-y-4">
            {/* Toolbar */}
            <div className="flex flex-wrap items-center gap-2">
                <div className="relative">
                    <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400 pointer-events-none" />
                    <Input placeholder="Rechercher…" value={search} onChange={e => setSearch(e.target.value)}
                        className="pl-8 h-8 text-sm w-52" />
                </div>
                <div className="flex gap-1.5">
                    {['', ...rolesPresents.map(r => r.value)].map(rv => {
                        const m = rv ? (ROLE_META[rv] ?? { label: rv, cls: 'bg-slate-100 text-slate-600 border-slate-200' }) : null;
                        const active = filterRole === rv;
                        return (
                            <button key={rv || '__all'} onClick={() => setRole(rv)}
                                className={`text-[11px] px-2.5 py-1 rounded-full border transition-all ${active
                                    ? (rv ? m.cls + ' font-semibold' : 'bg-ink text-white border-ink')
                                    : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300'}`}>
                                {rv ? (ROLE_META[rv]?.label ?? rv) : 'Tous'}
                            </button>
                        );
                    })}
                </div>
                <div className="flex gap-1.5 ml-auto">
                    {[{ v: '', label: 'Tous' }, { v: 'actif', label: `Actifs (${actifCount})` }, { v: 'inactif', label: `Inactifs (${inactifCount})` }].map(({ v, label }) => (
                        <button key={v} onClick={() => setActif(v)}
                            className={`text-[11px] px-2.5 py-1 rounded-full border transition-all ${filterActif === v
                                ? 'bg-ink text-white border-ink'
                                : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300'}`}>
                            {label}
                        </button>
                    ))}
                </div>
                <Button size="sm" className="h-8" onClick={() => setModal({ open: true, user: null })}>
                    <Plus className="h-4 w-4" /> Nouvel utilisateur
                </Button>
            </div>

            {/* Table */}
            <div className="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                <table className="table-notarial w-full">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Téléphone</th>
                            <th>Créé le</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {filtered.length === 0 && (
                            <tr><td colSpan={7} className="text-center text-slate-400 text-sm py-8 italic">Aucun utilisateur trouvé.</td></tr>
                        )}
                        {filtered.map(u => {
                            const rm = ROLE_META[u.role];
                            return (
                                <tr key={u.id} className={!u.actif ? 'opacity-60' : ''}>
                                    <td>
                                        <div className="flex items-center gap-2">
                                            <Avatar className="h-7 w-7 shrink-0">
                                                <AvatarFallback className="text-[10px] bg-ink text-white">{u.initiales || u.name?.[0]}</AvatarFallback>
                                            </Avatar>
                                            <span className="font-medium text-sm">{u.name}</span>
                                        </div>
                                    </td>
                                    <td className="text-slate-500 text-sm">{u.email}</td>
                                    <td>
                                        {rm
                                            ? <span className={`text-[10px] px-2 py-0.5 rounded-full border ${rm.cls}`}>{rm.label}</span>
                                            : <span className="text-xs text-slate-400">{u.roleLabel}</span>
                                        }
                                    </td>
                                    <td className="text-slate-500 font-mono text-xs">{u.telephone || '—'}</td>
                                    <td className="text-slate-400 text-xs">{u.created_at || '—'}</td>
                                    <td>
                                        {u.actif
                                            ? <span className="flex items-center gap-1 text-green-600 text-xs"><CheckCircle className="h-3.5 w-3.5" /> Actif</span>
                                            : <span className="flex items-center gap-1 text-red-500 text-xs"><XCircle className="h-3.5 w-3.5" /> Inactif</span>
                                        }
                                    </td>
                                    <td>
                                        <div className="flex items-center gap-1">
                                            <Button variant="ghost" size="icon-sm" onClick={() => setModal({ open: true, user: u })} title="Modifier">
                                                <Pencil className="h-3.5 w-3.5 text-slate-400" />
                                            </Button>
                                            <Button variant="ghost" size="icon-sm" onClick={() => toggleActif(u)} title={u.actif ? 'Désactiver' : 'Activer'}>
                                                {u.actif ? <XCircle className="h-3.5 w-3.5 text-slate-400" /> : <CheckCircle className="h-3.5 w-3.5 text-slate-400" />}
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>

            <ModalUtilisateur open={modal.open} onClose={() => setModal({ open: false, user: null })}
                utilisateur={modal.user} roles={roles} />
        </div>
    );
}

/* ═══════════════════════════════════════════════════════════
   Tab: Types d'actes
═══════════════════════════════════════════════════════════ */

const CATEGORIES_LISTE = [
    { value: 'societe',         label: 'Société' },
    { value: 'vente',           label: "Vente d'immeubles" },
    { value: 'hypotheque',      label: "Contrat d'hypothèque" },
    { value: 'bail',            label: 'Bail' },
    { value: 'donation',        label: 'Donation' },
    { value: 'succession',      label: 'Succession' },
    { value: 'mariage',         label: 'Contrat de mariage' },
    { value: 'testament',       label: 'Testament' },
    { value: 'procuration',     label: 'Procuration' },
    { value: 'courrier',        label: 'Courrier' },
    { value: 'prise_en_charge', label: 'Prise en charge' },
];

const EMPTY_TYPE_FORM = { label: '', code: '', categorie: '', delai_jours: '30', description: '' };

function ModalTypeActe({ open, onClose }) {
    const [form, setForm] = useState(EMPTY_TYPE_FORM);
    const [errors, setErrors] = useState({});

    const f = (k) => (e) => setForm(p => ({ ...p, [k]: typeof e === 'string' ? e : e.target.value }));

    const autoCode = (label, cat) => {
        if (!label || !cat) return '';
        const prefixes = { societe: 'SOC', vente: 'VTE', hypotheque: 'HYP', bail: 'BAI', donation: 'DON', succession: 'SUC', mariage: 'MAR', testament: 'TES', procuration: 'PRO', courrier: 'COU', prise_en_charge: 'PEC' };
        const pfx = prefixes[cat] ?? cat.slice(0, 3).toUpperCase();
        const abbr = label.replace(/[aeiouàâéèêëîïôùûüç\s\-]/gi, '').toUpperCase().slice(0, 3);
        return `${pfx}-${abbr}`;
    };

    const handleLabelChange = (e) => {
        const l = e.target.value;
        setForm(p => ({ ...p, label: l, code: autoCode(l, p.categorie) }));
    };

    const handleCatChange = (v) => {
        setForm(p => ({ ...p, categorie: v, code: autoCode(p.label, v) }));
    };

    const submit = (e) => {
        e.preventDefault();
        setErrors({});
        router.post('/parametres/types-actes', {
            ...form,
            delai_jours: parseInt(form.delai_jours, 10),
        }, {
            onSuccess: () => { onClose(); setForm(EMPTY_TYPE_FORM); },
            onError: setErrors,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle className="font-serif text-ink">Nouveau type d'acte</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Libellé</Label>
                        <Input value={form.label} onChange={handleLabelChange} required placeholder="ex : Constitution GIE" />
                        {errors.label && <p className="text-xs text-red-600">{errors.label}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Catégorie</Label>
                            <Select value={form.categorie} onValueChange={handleCatChange}>
                                <SelectTrigger><SelectValue placeholder="Choisir…" /></SelectTrigger>
                                <SelectContent>
                                    {CATEGORIES_LISTE.map(c => <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                            {errors.categorie && <p className="text-xs text-red-600">{errors.categorie}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Code <span className="text-slate-400 text-[10px]">(auto-généré)</span></Label>
                            <Input value={form.code}
                                onChange={e => setForm(p => ({ ...p, code: e.target.value.toUpperCase() }))}
                                required placeholder="SOC-GIE" className="font-mono uppercase" />
                            {errors.code && <p className="text-xs text-red-600">{errors.code}</p>}
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Délai de traitement (jours)</Label>
                        <Input type="number" min={1} value={form.delai_jours} onChange={f('delai_jours')} required />
                    </div>
                    <div className="space-y-1.5">
                        <Label>Description <span className="text-slate-400">(optionnel)</span></Label>
                        <Input value={form.description} onChange={f('description')} placeholder="Brève description de l'acte" />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit">Créer le type</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function InlineDelaiEdit({ typeActe }) {
    const [editing, setEditing] = useState(false);
    const [val, setVal]         = useState(String(typeActe.delai_jours ?? ''));

    const save = () => {
        const parsed = parseInt(val, 10);
        if (!isNaN(parsed) && parsed > 0 && parsed !== typeActe.delai_jours)
            router.patch(`/parametres/types-actes/${typeActe.id}`, { delai_jours: parsed }, { preserveState: true });
        setEditing(false);
    };
    const cancel = () => { setVal(String(typeActe.delai_jours ?? '')); setEditing(false); };

    if (editing) return (
        <div className="flex items-center gap-1">
            <Input type="number" min={1} value={val} onChange={e => setVal(e.target.value)}
                onKeyDown={e => { if (e.key === 'Enter') save(); if (e.key === 'Escape') cancel(); }}
                className="h-6 w-16 text-xs text-center px-1" autoFocus />
            <button onClick={save}   className="text-green-600 hover:text-green-700"><Check className="h-3.5 w-3.5" /></button>
            <button onClick={cancel} className="text-red-400 hover:text-red-500"><X className="h-3.5 w-3.5" /></button>
        </div>
    );
    return (
        <button onClick={() => setEditing(true)}
            className="flex items-center gap-1 text-sm text-slate-600 group/d hover:text-seal" title="Cliquer pour modifier">
            {typeActe.delai_jours ?? '—'}
            <Pencil className="h-3 w-3 text-slate-300 group-hover/d:text-seal opacity-0 group-hover/d:opacity-100 transition-opacity" />
        </button>
    );
}

function TabTypesActes({ typesActes = [] }) {
    const [search, setSearch] = useState('');
    const [newOpen, setNewOpen] = useState(false);
    const toggleActif = (t) => router.patch(`/parametres/types-actes/${t.id}`, { actif: !t.actif }, { preserveState: true });

    const filtered = useMemo(() => {
        const q = search.toLowerCase();
        if (!q) return typesActes;
        return typesActes.filter(t =>
            t.label?.toLowerCase().includes(q) || t.code?.toLowerCase().includes(q) || t.categorieLabel?.toLowerCase().includes(q)
        );
    }, [typesActes, search]);

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
        <div className="space-y-4">
            <div className="flex items-center gap-3">
                <p className="text-xs text-slate-500 mr-auto">
                    {typesActes.length} type{typesActes.length > 1 ? 's' : ''} configuré{typesActes.length > 1 ? 's' : ''}
                    <span className="ml-1 text-green-600">· {totalActifs} actif{totalActifs > 1 ? 's' : ''}</span>
                </p>
                <div className="relative">
                    <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400 pointer-events-none" />
                    <Input placeholder="Rechercher…" value={search} onChange={e => setSearch(e.target.value)}
                        className="pl-8 h-8 text-sm w-56" />
                </div>
                <Button size="sm" className="h-8" onClick={() => setNewOpen(true)}>
                    <Plus className="h-4 w-4" /> Nouveau type
                </Button>
            </div>

            <AnimatePresence mode="popLayout">
                {grouped.length === 0 && (
                    <div className="text-center text-slate-400 italic text-sm py-12">Aucun type d'acte trouvé.</div>
                )}
                {grouped.map(({ cat, label, items }) => {
                    const actifs = items.filter(t => t.actif).length;
                    return (
                        <motion.div key={cat}
                            initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: -4 }} transition={{ duration: 0.2 }}>
                            <div className="flex items-center gap-2 mb-2 px-1">
                                <div className={`h-3 w-1 rounded-full ${CAT_BAR[cat] ?? CAT_BAR.autre}`} />
                                <span className="text-xs font-semibold text-slate-600 uppercase tracking-wider">{label}</span>
                                <span className={`text-[10px] px-1.5 py-0.5 rounded-full border ${CAT_BADGE[cat] ?? CAT_BADGE.autre}`}>
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
                                                <td><span className="font-medium text-sm">{t.label}</span></td>
                                                <td className="text-center"><InlineDelaiEdit typeActe={t} /></td>
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
            <ModalTypeActe open={newOpen} onClose={() => setNewOpen(false)} />
        </div>
    );
}

/* ═══════════════════════════════════════════════════════════
   Tab: Barèmes
═══════════════════════════════════════════════════════════ */

function ModalAjouterBareme({ open, onClose, typesActes, organismes }) {
    const [form, setForm] = useState({
        type_acte_id: '', organisme: 'Impots', libelle: '',
        taux: '', montant_fixe: '', base_calcul: 'valeur_acte', description: '',
    });
    const [errors, setErrors] = useState({});

    const submit = () => {
        router.post('/parametres/baremes', {
            ...form,
            taux:         form.taux         !== '' ? parseFloat(form.taux)         : null,
            montant_fixe: form.montant_fixe !== '' ? parseFloat(form.montant_fixe) : null,
        }, {
            onSuccess: () => { onClose(); setForm({ type_acte_id: '', organisme: 'Impots', libelle: '', taux: '', montant_fixe: '', base_calcul: 'valeur_acte', description: '' }); },
            onError: setErrors,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle className="font-serif text-ink">Nouveau barème</DialogTitle>
                </DialogHeader>
                <div className="space-y-4 py-2">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Type d'acte</Label>
                            <Select value={form.type_acte_id} onValueChange={v => setForm(f => ({ ...f, type_acte_id: v }))}>
                                <SelectTrigger><SelectValue placeholder="Choisir…" /></SelectTrigger>
                                <SelectContent>
                                    {typesActes?.map(t => <SelectItem key={t.id} value={String(t.id)}>{t.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                            {errors.type_acte_id && <p className="text-xs text-red-600">{errors.type_acte_id}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Organisme</Label>
                            <Select value={form.organisme} onValueChange={v => setForm(f => ({ ...f, organisme: v }))}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>{organismes?.map(o => <SelectItem key={o} value={o}>{o}</SelectItem>)}</SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Libellé</Label>
                        <Input placeholder="ex : Droits d'enregistrement" value={form.libelle}
                            onChange={e => setForm(f => ({ ...f, libelle: e.target.value }))} />
                        {errors.libelle && <p className="text-xs text-red-600">{errors.libelle}</p>}
                    </div>
                    <div className="space-y-1.5">
                        <Label>Base de calcul</Label>
                        <Select value={form.base_calcul} onValueChange={v => setForm(f => ({ ...f, base_calcul: v }))}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="valeur_acte">% de la valeur de l'acte</SelectItem>
                                <SelectItem value="montant_fixe">Montant fixe</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    {form.base_calcul === 'valeur_acte' ? (
                        <div className="space-y-1.5">
                            <Label>Taux (%)</Label>
                            <div className="relative">
                                <Input type="number" step="0.0001" min="0" max="100" placeholder="2.5"
                                    className="pr-8" value={form.taux}
                                    onChange={e => setForm(f => ({ ...f, taux: e.target.value }))} />
                                <Percent className="absolute right-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                            </div>
                            {errors.taux && <p className="text-xs text-red-600">{errors.taux}</p>}
                        </div>
                    ) : (
                        <div className="space-y-1.5">
                            <Label>Montant fixe (GNF)</Label>
                            <div className="relative">
                                <Input type="number" step="1000" min="0" placeholder="50000"
                                    className="pr-14" value={form.montant_fixe}
                                    onChange={e => setForm(f => ({ ...f, montant_fixe: e.target.value }))} />
                                <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-ref">GNF</span>
                            </div>
                            {errors.montant_fixe && <p className="text-xs text-red-600">{errors.montant_fixe}</p>}
                        </div>
                    )}
                    <div className="space-y-1.5">
                        <Label>Description <span className="text-slate-400">(optionnel)</span></Label>
                        <Input placeholder="Note ou précision" value={form.description}
                            onChange={e => setForm(f => ({ ...f, description: e.target.value }))} />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>Annuler</Button>
                    <Button variant="seal" onClick={submit}>Ajouter le barème</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function TypeActeRow({ typeActe }) {
    const [open, setOpen] = useState((typeActe.baremes?.length ?? 0) > 0);
    const [confirmState, setConfirmState] = useState(null);

    const toggleActif = (b) => router.patch(`/parametres/baremes/${b.id}`, { actif: !b.actif }, { preserveScroll: true });
    const supprimer   = (b) => setConfirmState({
        title: `Supprimer "${b.libelle}" ?`,
        description: 'Ce barème sera définitivement supprimé.',
        confirmLabel: 'Supprimer',
        variant: 'destructive',
        onConfirm: () => router.delete(`/parametres/baremes/${b.id}`, { preserveScroll: true }),
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
        <Card className="overflow-hidden">
            <button onClick={() => setOpen(o => !o)}
                className="w-full flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition-colors text-left">
                <div className="flex items-center gap-3">
                    <span className="font-medium text-sm text-ink">{typeActe.label}</span>
                    <span className="text-[10px] text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">{typeActe.categorieLabel}</span>
                    <span className="text-[10px] text-slate-400">
                        {typeActe.baremes?.length ?? 0} barème{(typeActe.baremes?.length ?? 0) > 1 ? 's' : ''}
                    </span>
                </div>
                {open ? <ChevronUp className="h-4 w-4 text-slate-300" /> : <ChevronDown className="h-4 w-4 text-slate-300" />}
            </button>
            {open && (
                <div className="border-t border-slate-100">
                    {(typeActe.baremes?.length ?? 0) === 0 ? (
                        <p className="text-sm text-slate-400 text-center py-6">Aucun barème configuré pour ce type d'acte.</p>
                    ) : (
                        <table className="table-notarial w-full">
                            <thead>
                                <tr>
                                    <th>Organisme</th>
                                    <th>Libellé</th>
                                    <th>Calcul</th>
                                    <th>Taux / Montant</th>
                                    <th>Actif</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {typeActe.baremes?.map(b => (
                                    <tr key={b.id} className={!b.actif ? 'opacity-50' : ''}>
                                        <td>
                                            <span className={cn('inline-flex text-xs px-2 py-0.5 rounded-full font-medium', ORGANISME_COLORS[b.organisme] ?? 'bg-slate-100 text-slate-600')}>
                                                {b.organisme}
                                            </span>
                                        </td>
                                        <td className="font-medium text-sm text-slate-700">{b.libelle}</td>
                                        <td className="text-xs text-slate-500">
                                            {b.base_calcul === 'valeur_acte' ? '% valeur acte' : 'Montant fixe'}
                                        </td>
                                        <td className="font-ref text-seal font-semibold text-sm">
                                            {b.base_calcul === 'valeur_acte'
                                                ? `${parseFloat(b.taux ?? 0).toFixed(4).replace(/\.?0+$/, '')} %`
                                                : `${Number(b.montant_fixe ?? 0).toLocaleString('fr-GN')} GNF`
                                            }
                                        </td>
                                        <td><Switch checked={b.actif} onCheckedChange={() => toggleActif(b)} /></td>
                                        <td>
                                            <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-red-500"
                                                onClick={() => supprimer(b)}>
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            )}
        </Card>
        </>
    );
}

function TabBaremes({ baremesTypesActes = [], categories = [], organismes = [], stats = {} }) {
    const [categorie, setCategorie] = useState('');
    const [modalOpen, setModalOpen] = useState(false);

    const typesActesFlat = baremesTypesActes.map(t => ({ id: t.id, label: t.label }));

    const filtered = categorie
        ? baremesTypesActes.filter(t => t.categorie === categorie)
        : baremesTypesActes;

    return (
        <div className="space-y-4">
            {/* Toolbar */}
            <div className="flex items-center gap-2">
                <Select value={categorie} onValueChange={setCategorie}>
                    <SelectTrigger className="h-8 text-sm w-52"><SelectValue placeholder="Toutes catégories" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">Toutes catégories</SelectItem>
                        {categories.map(c => <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>)}
                    </SelectContent>
                </Select>
                {categorie && (
                    <Button variant="ghost" size="sm" className="h-8 text-slate-400" onClick={() => setCategorie('')}>
                        <X className="h-3.5 w-3.5 mr-1" /> Tout voir
                    </Button>
                )}
                <span className="ml-auto text-xs text-slate-400">{filtered.length} type{filtered.length > 1 ? 's' : ''} d'actes</span>
                <Button size="sm" className="h-8" variant="seal" onClick={() => setModalOpen(true)}>
                    <Plus className="h-4 w-4" /> Nouveau barème
                </Button>
            </div>

            {/* Légende */}
            <div className="flex flex-wrap gap-1.5">
                {Object.entries(ORGANISME_COLORS).map(([org, cls]) => (
                    <span key={org} className={cn('inline-flex text-[10px] font-medium px-2 py-0.5 rounded-full', cls)}>{org}</span>
                ))}
            </div>

            {/* Liste */}
            {filtered.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-20 text-center">
                        <Scale className="h-12 w-12 text-slate-200 mb-4" />
                        <p className="text-sm text-slate-400">Aucun type d'acte trouvé.</p>
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-2">
                    {filtered.map((t, i) => (
                        <motion.div key={t.id} initial={{ opacity: 0, y: 5 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.03 }}>
                            <TypeActeRow typeActe={t} />
                        </motion.div>
                    ))}
                </div>
            )}

            <ModalAjouterBareme open={modalOpen} onClose={() => setModalOpen(false)}
                typesActes={typesActesFlat} organismes={organismes} />
        </div>
    );
}

/* ═══════════════════════════════════════════════════════════
   Tab: Grilles de révision
═══════════════════════════════════════════════════════════ */

const EMPTY_GRILLE_FORM = { type_acte_id: '', version: '1.0' };
const EMPTY_POINT = { groupe: '', label: '', piece: '', requis: true };

function ModalGrille({ open, onClose, grille, typesActes }) {
    const isEdit = Boolean(grille);
    const [form, setForm] = useState(EMPTY_GRILLE_FORM);
    const [points, setPoints] = useState([{ ...EMPTY_POINT }]);
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (open) {
            if (isEdit) {
                setForm({ type_acte_id: String(grille.type_acte_id), version: grille.version });
                setPoints(grille.points?.length ? grille.points.map(p => ({ ...p })) : [{ ...EMPTY_POINT }]);
            } else {
                setForm(EMPTY_GRILLE_FORM);
                setPoints([{ ...EMPTY_POINT }]);
            }
        }
    }, [open, grille?.id]);

    const updatePoint = (i, k, v) => setPoints(ps => ps.map((p, idx) => idx === i ? { ...p, [k]: v } : p));
    const addPoint    = () => setPoints(ps => [...ps, { ...EMPTY_POINT }]);
    const removePoint = (i) => setPoints(ps => ps.filter((_, idx) => idx !== i));

    const submit = (e) => {
        e.preventDefault();
        setErrors({});
        const payload = { ...form, type_acte_id: parseInt(form.type_acte_id, 10), points };
        const opts = {
            onSuccess: () => { onClose(); },
            onError: setErrors,
        };
        if (isEdit) router.put(`/parametres/grilles/${grille.id}`, payload, opts);
        else router.post('/parametres/grilles', payload, opts);
    };

    const groupes = [...new Set(points.map(p => p.groupe).filter(Boolean))];

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="font-serif text-ink">
                        {isEdit ? 'Modifier la grille' : 'Nouvelle grille de révision'}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-5">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Type d'acte</Label>
                            <Select value={form.type_acte_id} onValueChange={v => setForm(f => ({ ...f, type_acte_id: v }))} disabled={isEdit}>
                                <SelectTrigger><SelectValue placeholder="Choisir…" /></SelectTrigger>
                                <SelectContent>
                                    {typesActes?.map(t => <SelectItem key={t.id} value={String(t.id)}>{t.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                            {errors['type_acte_id'] && <p className="text-xs text-red-600">{errors['type_acte_id']}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <Label>Version</Label>
                            <Input value={form.version} onChange={e => setForm(f => ({ ...f, version: e.target.value }))} required placeholder="1.0" />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <div className="flex items-center justify-between">
                            <Label className="text-sm font-semibold text-slate-700">Points de contrôle ({points.length})</Label>
                            <Button type="button" variant="outline" size="sm" className="h-7" onClick={addPoint}>
                                <Plus className="h-3.5 w-3.5" /> Ajouter un point
                            </Button>
                        </div>

                        <div className="border border-slate-200 rounded-lg overflow-hidden">
                            {points.map((pt, i) => (
                                <div key={i} className="flex items-start gap-2 px-3 py-2.5 border-b border-slate-100 last:border-b-0 hover:bg-slate-50">
                                    <GripVertical className="h-4 w-4 text-slate-300 mt-2 shrink-0" />
                                    <div className="flex-1 grid grid-cols-2 gap-2">
                                        <Input
                                            placeholder="Groupe (ex : Identité des parties)"
                                            value={pt.groupe} onChange={e => updatePoint(i, 'groupe', e.target.value)}
                                            list="groupes-list" className="h-8 text-xs" required
                                        />
                                        <Input
                                            placeholder="Libellé du point de contrôle"
                                            value={pt.label} onChange={e => updatePoint(i, 'label', e.target.value)}
                                            className="h-8 text-xs" required
                                        />
                                        <Input
                                            placeholder="Pièce requise (optionnel)"
                                            value={pt.piece ?? ''} onChange={e => updatePoint(i, 'piece', e.target.value)}
                                            className="h-8 text-xs"
                                        />
                                        <label className="flex items-center gap-2 text-xs text-slate-600 cursor-pointer">
                                            <input type="checkbox" checked={pt.requis ?? true}
                                                onChange={e => updatePoint(i, 'requis', e.target.checked)}
                                                className="rounded" />
                                            Obligatoire
                                        </label>
                                    </div>
                                    <button type="button" onClick={() => removePoint(i)}
                                        className="text-slate-300 hover:text-red-500 transition-colors mt-1.5 shrink-0">
                                        <X className="h-4 w-4" />
                                    </button>
                                </div>
                            ))}
                        </div>
                        <datalist id="groupes-list">
                            {groupes.map(g => <option key={g} value={g} />)}
                        </datalist>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit" variant="seal">{isEdit ? 'Enregistrer' : 'Créer la grille'}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function TabGrilles({ grilles = [], typesActes = [] }) {
    const [modal, setModal] = useState({ open: false, grille: null });
    const [confirmState, setConfirmState] = useState(null);

    const supprimer = (g) => setConfirmState({
        title: `Supprimer la grille "${g.typeActeLabel}" v${g.version} ?`,
        description: 'Cette action est irréversible.',
        confirmLabel: 'Supprimer',
        variant: 'destructive',
        onConfirm: () => router.delete(`/parametres/grilles/${g.id}`, { preserveState: true }),
    });

    const toggleActive = (g) => {
        router.put(`/parametres/grilles/${g.id}`, { est_active: !g.est_active }, { preserveState: true });
    };

    const typesActesPourSelect = typesActes.map(t => ({ id: t.id, label: t.label }));

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between gap-3">
                <p className="text-xs text-slate-500">
                    {grilles.length} grille{grilles.length > 1 ? 's' : ''} configurée{grilles.length > 1 ? 's' : ''}
                    <span className="ml-1 text-green-600">· {grilles.filter(g => g.est_active).length} active{grilles.filter(g => g.est_active).length > 1 ? 's' : ''}</span>
                </p>
                <Button size="sm" className="h-8" onClick={() => setModal({ open: true, grille: null })}>
                    <Plus className="h-4 w-4" /> Nouvelle grille
                </Button>
            </div>

            {grilles.length === 0 ? (
                <div className="bg-white border border-slate-200 rounded-xl p-12 text-center">
                    <ClipboardList className="h-10 w-10 text-slate-200 mx-auto mb-3" />
                    <p className="text-sm text-slate-400 mb-4">Aucune grille de révision configurée.</p>
                    <p className="text-xs text-slate-400">Les grilles définissent les points de contrôle lors de la révision de chaque type d'acte.</p>
                    <Button size="sm" variant="outline" className="mt-4" onClick={() => setModal({ open: true, grille: null })}>
                        <Plus className="h-3.5 w-3.5" /> Créer la première grille
                    </Button>
                </div>
            ) : (
                <div className="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <table className="table-notarial w-full">
                        <thead>
                            <tr>
                                <th>Type d'acte</th>
                                <th>Catégorie</th>
                                <th className="text-center">Version</th>
                                <th className="text-center">Points</th>
                                <th className="text-center">Active</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {grilles.map(g => (
                                <tr key={g.id} className={!g.est_active ? 'opacity-50' : ''}>
                                    <td className="font-medium text-sm">{g.typeActeLabel}</td>
                                    <td>
                                        <span className={`text-[10px] px-2 py-0.5 rounded-full border ${CAT_BADGE_ALL[g.typeActeCat] ?? 'bg-slate-100 text-slate-600 border-slate-200'}`}>
                                            {g.typeActeCat ?? '—'}
                                        </span>
                                    </td>
                                    <td className="text-center font-mono text-xs text-slate-500">v{g.version}</td>
                                    <td className="text-center">
                                        <span className="text-sm tabular-nums">{g.points?.length ?? 0}</span>
                                    </td>
                                    <td className="text-center">
                                        <Switch checked={g.est_active} onCheckedChange={() => toggleActive(g)} />
                                    </td>
                                    <td>
                                        <div className="flex items-center gap-1">
                                            <Button variant="ghost" size="icon-sm" onClick={() => setModal({ open: true, grille: g })} title="Modifier">
                                                <Pencil className="h-3.5 w-3.5 text-slate-400" />
                                            </Button>
                                            <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-red-500"
                                                onClick={() => supprimer(g)} title="Supprimer">
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <ModalGrille open={modal.open} onClose={() => setModal({ open: false, grille: null })}
                grille={modal.grille} typesActes={typesActesPourSelect} />
            <ConfirmDialog
                open={!!confirmState}
                onClose={() => setConfirmState(null)}
                title={confirmState?.title ?? ''}
                description={confirmState?.description}
                confirmLabel={confirmState?.confirmLabel}
                variant={confirmState?.variant}
                onConfirm={confirmState?.onConfirm ?? (() => {})}
            />
        </div>
    );
}

/* ═══════════════════════════════════════════════════════════
   Tab: Apparence
═══════════════════════════════════════════════════════════ */

function ColorPicker({ label, description, value, onChange }) {
    const inputRef = useRef(null);
    return (
        <div className="flex items-center justify-between gap-4 py-3 border-b border-slate-100 last:border-b-0">
            <div className="min-w-0">
                <div className="text-sm font-medium text-slate-700">{label}</div>
                {description && <div className="text-xs text-slate-400 mt-0.5">{description}</div>}
            </div>
            <div className="flex items-center gap-2 shrink-0">
                <div
                    className="h-8 w-8 rounded-md border border-slate-300 cursor-pointer shadow-sm transition-transform hover:scale-110"
                    style={{ backgroundColor: value }}
                    onClick={() => inputRef.current?.click()}
                    title="Cliquer pour changer"
                />
                <code className="text-xs font-mono text-slate-500 bg-slate-50 px-2 py-0.5 rounded border border-slate-200 w-20 text-center">
                    {value}
                </code>
                <input
                    ref={inputRef}
                    type="color"
                    value={value}
                    onChange={e => onChange(e.target.value)}
                    className="sr-only"
                />
            </div>
        </div>
    );
}

function TabApparence({ apparence = {} }) {
    const [form, setForm] = useState({
        office_nom:        apparence.office_nom        || 'Maître Ayelama Bah',
        office_sous_titre: apparence.office_sous_titre || 'Notaire',
        couleur_primaire:  apparence.couleur_primaire  || '#0F2D60',
        couleur_accent:    apparence.couleur_accent    || '#E8A520',
        couleur_fond:      apparence.couleur_fond      || '#F5F5F3',
    });
    const [logoPreview, setLogoPreview] = useState(apparence.logo_url || null);
    const [logoFile, setLogoFile]       = useState(null);
    const [saving, setSaving]           = useState(false);
    const [saved, setSaved]             = useState(false);
    const fileRef = useRef(null);

    const f = k => v => setForm(p => ({ ...p, [k]: v }));

    const handleLogoChange = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setLogoFile(file);
        setLogoPreview(URL.createObjectURL(file));
    };

    const uploadLogo = () => {
        if (!logoFile) return;
        const data = new FormData();
        data.append('logo', logoFile);
        router.post('/parametres/apparence/logo', data, {
            forceFormData: true,
            onSuccess: () => { setLogoFile(null); },
        });
    };

    const deleteLogo = () => {
        router.delete('/parametres/apparence/logo', {
            onSuccess: () => { setLogoPreview(null); setLogoFile(null); },
        });
    };

    const saveSettings = (e) => {
        e.preventDefault();
        setSaving(true);
        router.post('/parametres/apparence', form, {
            onSuccess: () => { setSaved(true); setTimeout(() => setSaved(false), 2500); },
            onFinish: () => setSaving(false),
        });
    };

    // Upload logo séparément si un fichier est prêt
    useEffect(() => {
        if (logoFile) uploadLogo();
    }, [logoFile]);

    const previewPrimary = form.couleur_primaire;
    const previewAccent  = form.couleur_accent;
    const previewFond    = form.couleur_fond;

    return (
        <form onSubmit={saveSettings} className="space-y-6">
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {/* Colonne gauche — paramètres */}
                <div className="space-y-5">

                    {/* Logo */}
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <h3 className="text-sm font-semibold text-ink mb-4 flex items-center gap-2">
                            <Upload className="h-4 w-4 text-seal" />
                            Logo de l'office
                        </h3>
                        <div className="flex items-center gap-4">
                            <div className="h-16 w-16 rounded-xl border-2 border-dashed border-slate-200 flex items-center justify-center bg-slate-50 overflow-hidden shrink-0">
                                {logoPreview
                                    ? <img src={logoPreview} alt="Logo" className="h-full w-full object-contain p-1" />
                                    : <span className="text-slate-300 text-xs text-center leading-tight">Aucun logo</span>
                                }
                            </div>
                            <div className="flex flex-col gap-2 min-w-0">
                                <Button type="button" variant="outline" size="sm" className="h-8"
                                    onClick={() => fileRef.current?.click()}>
                                    <Upload className="h-3.5 w-3.5" />
                                    {logoPreview ? 'Changer le logo' : 'Choisir un logo'}
                                </Button>
                                {logoPreview && (
                                    <Button type="button" variant="ghost" size="sm" className="h-8 text-red-500 hover:text-red-600 hover:bg-red-50"
                                        onClick={deleteLogo}>
                                        <Trash2 className="h-3.5 w-3.5" /> Supprimer
                                    </Button>
                                )}
                                <p className="text-[10px] text-slate-400">PNG, JPG ou SVG · max 2 Mo</p>
                                <input ref={fileRef} type="file" accept="image/png,image/jpeg,image/svg+xml"
                                    className="sr-only" onChange={handleLogoChange} />
                            </div>
                        </div>
                    </div>

                    {/* Identité */}
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <h3 className="text-sm font-semibold text-ink mb-4">Identité de l'office</h3>
                        <div className="space-y-3">
                            <div className="space-y-1.5">
                                <Label>Nom de l'office</Label>
                                <Input value={form.office_nom}
                                    onChange={e => f('office_nom')(e.target.value)}
                                    placeholder="Maître Ayelama Bah" />
                            </div>
                            <div className="space-y-1.5">
                                <Label>Sous-titre <span className="text-slate-400 text-[10px]">(affiché sous le nom)</span></Label>
                                <Input value={form.office_sous_titre}
                                    onChange={e => f('office_sous_titre')(e.target.value)}
                                    placeholder="Notaire" />
                            </div>
                        </div>
                    </div>

                    {/* Couleurs */}
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <h3 className="text-sm font-semibold text-ink mb-1">Palette de couleurs</h3>
                        <p className="text-xs text-slate-400 mb-4">Les changements s'appliquent immédiatement à toute l'interface.</p>
                        <div>
                            <ColorPicker
                                label="Couleur principale"
                                description="Sidebar, boutons principaux, titres"
                                value={previewPrimary}
                                onChange={f('couleur_primaire')}
                            />
                            <ColorPicker
                                label="Couleur d'accent"
                                description="Or notarial — icônes actives, badges, boutons d'action"
                                value={previewAccent}
                                onChange={f('couleur_accent')}
                            />
                            <ColorPicker
                                label="Fond de l'application"
                                description="Arrière-plan général de l'interface"
                                value={previewFond}
                                onChange={f('couleur_fond')}
                            />
                        </div>
                        <div className="mt-4 pt-3 border-t border-slate-100">
                            <button
                                type="button"
                                onClick={() => setForm(p => ({ ...p, couleur_primaire: '#0F2D60', couleur_accent: '#E8A520', couleur_fond: '#F5F5F3' }))}
                                className="text-xs text-slate-400 hover:text-slate-600 transition-colors"
                            >
                                Réinitialiser aux couleurs par défaut
                            </button>
                        </div>
                    </div>
                </div>

                {/* Colonne droite — aperçu */}
                <div className="space-y-4">
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <h3 className="text-sm font-semibold text-slate-700 mb-4">Aperçu</h3>

                        {/* Mini sidebar preview */}
                        <div className="rounded-lg overflow-hidden border border-slate-200 shadow-sm">
                            {/* Sidebar */}
                            <div className="flex" style={{ height: 160 }}>
                                <div className="w-32 flex flex-col" style={{ backgroundColor: previewPrimary }}>
                                    {/* Logo area */}
                                    <div className="flex items-center gap-2 px-2 py-2 border-b" style={{ borderColor: 'rgba(255,255,255,0.1)' }}>
                                        <div className="h-6 w-6 rounded flex items-center justify-center overflow-hidden shrink-0"
                                             style={{ backgroundColor: 'rgba(255,255,255,0.15)' }}>
                                            {logoPreview
                                                ? <img src={logoPreview} alt="" className="h-full w-full object-contain" />
                                                : <div className="w-3 h-3 rounded-sm" style={{ backgroundColor: previewAccent }} />
                                            }
                                        </div>
                                        <div>
                                            <div className="text-white text-[8px] font-semibold leading-tight truncate" style={{ maxWidth: 72 }}>
                                                {form.office_nom.split(' ').slice(-2).join(' ')}
                                            </div>
                                            <div className="text-[6px] font-medium tracking-wider truncate" style={{ color: previewAccent, maxWidth: 72 }}>
                                                {form.office_sous_titre.toUpperCase()}
                                            </div>
                                        </div>
                                    </div>
                                    {/* Nav items */}
                                    {['Tableau de bord', 'Dossiers', 'Révisions', 'Formalités'].map((item, i) => (
                                        <div key={item} className="flex items-center gap-1.5 px-2 py-1 mx-1 my-0.5 rounded text-[7px]"
                                             style={{
                                                 backgroundColor: i === 0 ? 'rgba(255,255,255,0.15)' : 'transparent',
                                                 color: i === 0 ? '#fff' : 'rgba(255,255,255,0.6)'
                                             }}>
                                            <div className="h-2 w-2 rounded-sm shrink-0"
                                                 style={{ backgroundColor: i === 0 ? previewAccent : 'rgba(255,255,255,0.3)' }} />
                                            {item}
                                        </div>
                                    ))}
                                </div>

                                {/* Main area */}
                                <div className="flex-1 flex flex-col" style={{ backgroundColor: previewFond }}>
                                    {/* Topbar */}
                                    <div className="h-8 bg-white border-b border-slate-200 flex items-center gap-2 px-3">
                                        <div className="flex-1 h-3 bg-slate-100 rounded-full" />
                                        <div className="h-5 px-2 rounded text-[7px] font-medium text-white flex items-center"
                                             style={{ backgroundColor: previewAccent }}>
                                            + Dossier
                                        </div>
                                    </div>
                                    {/* Content */}
                                    <div className="flex-1 p-3 space-y-2">
                                        <div className="h-3 rounded" style={{ backgroundColor: previewPrimary, opacity: 0.08, width: '60%' }} />
                                        <div className="grid grid-cols-2 gap-1.5">
                                            {[previewPrimary, previewAccent, '#10B981', '#6366F1'].map((c, i) => (
                                                <div key={i} className="h-8 rounded-md bg-white border border-slate-200 flex items-center gap-1 px-1.5">
                                                    <div className="h-3 w-3 rounded shrink-0" style={{ backgroundColor: c, opacity: 0.9 }} />
                                                    <div className="h-1.5 bg-slate-100 rounded flex-1" />
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p className="text-[10px] text-slate-400 text-center mt-3">Aperçu en temps réel</p>
                    </div>

                    {/* Palettes prédéfinies */}
                    <div className="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                        <h3 className="text-sm font-semibold text-slate-700 mb-3">Palettes prédéfinies</h3>
                        <div className="grid grid-cols-2 gap-2">
                            {[
                                { label: 'Notarial Guinée',  primary: '#0F2D60', accent: '#E8A520', fond: '#F5F5F3' },
                                { label: 'Bordeaux Royal',   primary: '#6B1A1A', accent: '#C9A84C', fond: '#FAFAF8' },
                                { label: 'Vert Forêt',       primary: '#1B4332', accent: '#D4A017', fond: '#F4F7F4' },
                                { label: 'Marine Classique', primary: '#1E3A5F', accent: '#C0934E', fond: '#F6F6F4' },
                            ].map(palette => (
                                <button
                                    key={palette.label}
                                    type="button"
                                    onClick={() => setForm(p => ({ ...p, ...palette }))}
                                    className="flex items-center gap-2 p-2 rounded-lg border border-slate-200 hover:border-slate-300 hover:bg-slate-50 transition-all text-left"
                                >
                                    <div className="flex gap-0.5 shrink-0">
                                        {[palette.primary, palette.accent, palette.fond].map((c, i) => (
                                            <div key={i} className="h-4 w-4 rounded-sm border border-black/10" style={{ backgroundColor: c }} />
                                        ))}
                                    </div>
                                    <span className="text-[10px] text-slate-600 font-medium truncate">{palette.label}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            {/* Barre de sauvegarde */}
            <div className="sticky bottom-0 bg-white/95 backdrop-blur border-t border-slate-200 -mx-5 px-5 py-3 flex items-center justify-between gap-3 rounded-b-xl">
                <p className="text-xs text-slate-400">Les couleurs s'appliquent immédiatement — cliquez sur Enregistrer pour les conserver.</p>
                <Button type="submit" variant="seal" disabled={saving} className="shrink-0">
                    {saved ? <><Check className="h-4 w-4" /> Enregistré</> : saving ? 'Enregistrement…' : 'Enregistrer'}
                </Button>
            </div>
        </form>
    );
}

/* ═══════════════════════════════════════════════════════════
   Page principale
═══════════════════════════════════════════════════════════ */

export default function ParametresIndex({
    stats = {}, parRole = [], parCategorie = [],
    utilisateurs = [], roles = [],
    typesActes = [],
    baremesTypesActes = [], categories = [], organismes = [],
    grilles = [],
    apparence = {},
}) {
    const [activeTab, setActiveTab] = useState('utilisateurs');

    const totalUsers  = stats.utilisateurs       ?? 0;
    const actifUsers  = stats.utilisateursActifs ?? 0;
    const totalTypes  = stats.typesActes         ?? 0;
    const actifTypes  = stats.typesActifs        ?? 0;
    const baremes     = stats.baremes            ?? 0;
    const typesAvecB  = stats.typesAvecBaremes   ?? 0;

    const pctBaremes  = totalTypes > 0 ? Math.round((typesAvecB / totalTypes) * 100) : 0;
    const pctTypes    = totalTypes > 0 ? Math.round((actifTypes / totalTypes) * 100) : 0;
    const pctUsers    = totalUsers > 0 ? Math.round((actifUsers / totalUsers) * 100) : 0;
    const healthScore = Math.round((pctBaremes + pctTypes + pctUsers) / 3);

    const scoreColor = healthScore === 100 ? 'bg-green-50 text-green-700 border-green-200'
        : healthScore >= 60 ? 'bg-amber-50 text-amber-700 border-amber-200'
        : 'bg-red-50 text-red-700 border-red-200';

    return (
        <AppLayout breadcrumbs={[{ label: 'Paramètres' }]}>
            <Head title="Paramètres — Ayelema" />
            <div className="p-6 max-w-5xl mx-auto space-y-6">

                {/* En-tête */}
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-display text-ink flex items-center gap-2">
                            <Settings className="h-5 w-5 text-seal shrink-0" />
                            Paramètres &amp; Administration
                        </h1>
                        <p className="text-sm text-slate-500 mt-1 ml-7">Configuration de l'office notarial</p>
                    </div>
                    <div className={`px-3 py-1.5 rounded-full text-xs font-semibold border shrink-0 ${scoreColor}`}>
                        Configuration : {healthScore}%
                    </div>
                </div>

                {/* KPIs */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    {[
                        { label: 'Utilisateurs actifs', value: `${actifUsers}/${totalUsers}`, sub: 'comptes',      cls: 'text-ink' },
                        { label: 'Types actifs',         value: `${actifTypes}/${totalTypes}`, sub: "d'actes",     cls: 'text-seal' },
                        { label: 'Barèmes configurés',   value: baremes,                       sub: 'au total',    cls: baremes === 0 ? 'text-red-500' : 'text-green-600' },
                        { label: 'Types sans barème',    value: totalTypes - typesAvecB,       sub: 'à couvrir',   cls: (totalTypes - typesAvecB) > 0 ? 'text-amber-600' : 'text-green-600' },
                    ].map(({ label, value, sub, cls }) => (
                        <div key={label} className="bg-white rounded-xl border border-slate-200 p-3.5 shadow-sm">
                            <div className="text-[10px] text-slate-500 mb-1 uppercase tracking-wide">{label}</div>
                            <div className={`text-2xl font-semibold tabular-nums ${cls}`}>{value}</div>
                            <div className="text-[10px] text-slate-400 mt-0.5">{sub}</div>
                        </div>
                    ))}
                </div>

                {/* Santé */}
                <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
                    <div className="flex items-center gap-2 mb-4">
                        <Shield className="h-4 w-4 text-seal" />
                        <h3 className="text-sm font-semibold text-ink">Santé de la configuration</h3>
                    </div>
                    <div className="space-y-3.5">
                        <HealthBar label="Couverture des barèmes fiscaux" value={typesAvecB}  max={totalTypes} barColor="bg-seal" />
                        <HealthBar label="Types d'actes actifs"           value={actifTypes}  max={totalTypes} barColor="bg-emerald-500" />
                        <HealthBar label="Utilisateurs actifs"            value={actifUsers}  max={totalUsers} barColor="bg-blue-500" />
                    </div>
                </div>

                {/* Onglets */}
                <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    {/* Tab bar */}
                    <div className="border-b border-slate-200 flex">
                        {TABS.map(({ id, label, icon: Icon }) => {
                            const active = activeTab === id;
                            return (
                                <button
                                    key={id}
                                    onClick={() => setActiveTab(id)}
                                    className={cn(
                                        'flex items-center gap-2 px-5 py-3.5 text-sm font-medium transition-colors relative',
                                        active
                                            ? 'text-seal'
                                            : 'text-slate-500 hover:text-slate-700 hover:bg-slate-50'
                                    )}
                                >
                                    <Icon className="h-4 w-4" />
                                    {label}
                                    {active && (
                                        <motion.div
                                            layoutId="tab-indicator"
                                            className="absolute bottom-0 left-0 right-0 h-0.5 bg-seal"
                                        />
                                    )}
                                </button>
                            );
                        })}
                    </div>

                    {/* Tab content */}
                    <div className="p-5">
                        <AnimatePresence mode="wait">
                            <motion.div
                                key={activeTab}
                                initial={{ opacity: 0, y: 4 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, y: -4 }}
                                transition={{ duration: 0.15 }}
                            >
                                {activeTab === 'utilisateurs' && (
                                    <TabUtilisateurs utilisateurs={utilisateurs} roles={roles} />
                                )}
                                {activeTab === 'types-actes' && (
                                    <TabTypesActes typesActes={typesActes} />
                                )}
                                {activeTab === 'baremes' && (
                                    <TabBaremes
                                        baremesTypesActes={baremesTypesActes}
                                        categories={categories}
                                        organismes={organismes}
                                        stats={stats}
                                    />
                                )}
                                {activeTab === 'grilles' && (
                                    <TabGrilles grilles={grilles} typesActes={typesActes} />
                                )}
                                {activeTab === 'apparence' && (
                                    <TabApparence apparence={apparence} />
                                )}
                            </motion.div>
                        </AnimatePresence>
                    </div>
                </div>

            </div>
        </AppLayout>
    );
}
