import React, { useState, useMemo, useEffect } from 'react';
import { usePage, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PhoneField } from '@/components/ui/phone-field';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { CheckCircle, XCircle, Plus, Pencil, Search, Users } from 'lucide-react';
import { ROLE_META } from '@/data/roles';
import { RoleBadgeList } from '@/components/ui/role-badge';
import PasswordRequirements from '@/Components/PasswordRequirements';

const EMPTY_FORM = { name: '', email: '', password: '', roles: [], initiales: '', telephone: '' };

function ModalUtilisateur({ open, onClose, utilisateur, roles }) {
    const isEdit = Boolean(utilisateur);
    const [form, setForm] = useState(EMPTY_FORM);
    const [rolesError, setRolesError] = useState(false);

    useEffect(() => {
        if (open) {
            setRolesError(false);
            setForm(isEdit ? {
                name:      utilisateur.name      ?? '',
                email:     utilisateur.email     ?? '',
                password:  '',
                roles:     utilisateur.roles     ?? [],
                initiales: utilisateur.initiales ?? '',
                telephone: utilisateur.telephone ?? '',
            } : EMPTY_FORM);
        }
    }, [open, utilisateur?.id]);

    const f = (k) => (e) => setForm(p => ({ ...p, [k]: typeof e === 'string' ? e : e.target.value }));

    const toggleRole = (value, checked) => {
        setForm(p => ({
            ...p,
            roles: checked ? [...p.roles, value] : p.roles.filter(v => v !== value),
        }));
    };

    const submit = (e) => {
        e.preventDefault();
        if (form.roles.length === 0) { setRolesError(true); return; }
        setRolesError(false);

        const payload = { ...form };
        if (isEdit && !payload.password) delete payload.password;

        if (isEdit) {
            router.patch(`/parametres/utilisateurs/${utilisateur.id}`, payload, {
                onSuccess: onClose,
                preserveState: true,
            });
        } else {
            router.post('/parametres/utilisateurs', payload, { onSuccess: onClose });
        }
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'}</DialogTitle>
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
                            <Label>{isEdit ? 'Nouveau mot de passe' : 'Mot de passe'} {isEdit && <span className="text-slate-400 text-[11px]">(laisser vide pour conserver)</span>}</Label>
                            <Input type="password" value={form.password} onChange={f('password')} required={!isEdit} autoComplete="new-password" />
                            {(!isEdit || form.password) && <PasswordRequirements password={form.password} />}
                        </div>
                        <div className="col-span-2 space-y-1">
                            <Label>Rôles</Label>
                            <div className="flex flex-wrap gap-3">
                                {roles?.map(r => (
                                    <label key={r.value} className="flex items-center gap-1.5 text-sm cursor-pointer">
                                        <Checkbox
                                            checked={form.roles.includes(r.value)}
                                            onCheckedChange={(checked) => toggleRole(r.value, checked === true)}
                                        />
                                        {r.label}
                                    </label>
                                ))}
                            </div>
                            {rolesError && <p className="text-xs text-danger">Sélectionnez au moins un rôle.</p>}
                        </div>
                        <div className="space-y-1">
                            <Label>Initiales</Label>
                            <Input
                                value={form.initiales}
                                onChange={e => setForm(p => ({ ...p, initiales: e.target.value.toUpperCase().slice(0, 3) }))}
                                maxLength={3}
                                className="font-mono uppercase"
                                placeholder="AB"
                            />
                        </div>
                        <div className="col-span-2 space-y-1">
                            <Label>Téléphone</Label>
                            <PhoneField value={form.telephone} onValueChange={val => setForm(p => ({ ...p, telephone: val }))} placeholder="622 XX XX XX" />
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

export default function ParametresUtilisateurs() {
    const { utilisateurs = [], roles = [] } = usePage().props;

    const [modal, setModal]     = useState({ open: false, user: null });
    const [search, setSearch]   = useState('');
    const [filterRole, setRole] = useState('');
    const [filterActif, setActif] = useState('');

    const openCreate = () => setModal({ open: true, user: null });
    const openEdit   = (u) => setModal({ open: true, user: u });
    const closeModal = () => setModal({ open: false, user: null });

    const toggleActif = (u) => {
        router.patch(`/parametres/utilisateurs/${u.id}`, { actif: !u.actif }, { preserveState: true });
    };

    const filtered = useMemo(() => {
        const q = search.toLowerCase();
        return utilisateurs.filter(u => {
            if (q && !u.name?.toLowerCase().includes(q) && !u.email?.toLowerCase().includes(q)) return false;
            if (filterRole && !u.roles?.includes(filterRole)) return false;
            if (filterActif === 'actif' && !u.actif) return false;
            if (filterActif === 'inactif' && u.actif) return false;
            return true;
        });
    }, [utilisateurs, search, filterRole, filterActif]);

    const actifCount = utilisateurs.filter(u => u.actif).length;
    const inactifCount = utilisateurs.length - actifCount;

    /* rôles présents dans la liste */
    const rolesPresents = useMemo(() => {
        const seen = new Set(utilisateurs.flatMap(u => u.roles ?? []));
        return roles.filter(r => seen.has(r.value));
    }, [utilisateurs, roles]);

    return (
        <AppLayout breadcrumbs={[{ label: 'Paramètres', href: '/parametres' }, { label: 'Utilisateurs' }]}>
            <div className="p-6 max-w-5xl mx-auto space-y-5">

                {/* En-tête */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Users className="h-5 w-5 text-seal" />
                        <div>
                            <h1 className="text-xl font-semibold text-ink">Utilisateurs</h1>
                            <p className="text-xs text-slate-500">
                                {utilisateurs.length} compte{utilisateurs.length > 1 ? 's' : ''}
                                {inactifCount > 0 && <span className="text-amber-600 ml-1">· {inactifCount} inactif{inactifCount > 1 ? 's' : ''}</span>}
                            </p>
                        </div>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="h-4 w-4" /> Nouvel utilisateur
                    </Button>
                </div>

                {/* Filtres */}
                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400 pointer-events-none" />
                        <Input
                            placeholder="Rechercher…"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            className="pl-8 h-8 text-sm w-52"
                        />
                    </div>

                    {/* Role chips */}
                    <div className="flex gap-1.5">
                        {['', ...rolesPresents.map(r => r.value)].map(rv => {
                            const m = rv ? (ROLE_META[rv] ?? { label: rv, cls: 'bg-slate-100 text-slate-600 border-slate-200' }) : null;
                            const active = filterRole === rv;
                            return (
                                <button
                                    key={rv || '__all'}
                                    onClick={() => setRole(rv)}
                                    className={`text-[11px] px-2.5 py-1 rounded-full border transition-all ${
                                        active
                                            ? (rv ? m.cls + ' font-semibold' : 'bg-ink text-white border-ink')
                                            : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300'
                                    }`}
                                >
                                    {rv ? (ROLE_META[rv]?.label ?? rv) : 'Tous les rôles'}
                                </button>
                            );
                        })}
                    </div>

                    {/* Actif filter */}
                    <div className="flex gap-1.5 ml-auto">
                        {[
                            { v: '',       label: 'Tous' },
                            { v: 'actif',  label: `Actifs (${actifCount})` },
                            { v: 'inactif',label: `Inactifs (${inactifCount})` },
                        ].map(({ v, label }) => (
                            <button
                                key={v}
                                onClick={() => setActif(v)}
                                className={`text-[11px] px-2.5 py-1 rounded-full border transition-all ${
                                    filterActif === v
                                        ? 'bg-ink text-white border-ink'
                                        : 'bg-white text-slate-500 border-slate-200 hover:border-slate-300'
                                }`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
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
                                <tr>
                                    <td colSpan={7} className="text-center text-slate-400 text-sm py-8 italic">
                                        Aucun utilisateur trouvé.
                                    </td>
                                </tr>
                            )}
                            {filtered.map(u => {
                                return (
                                    <tr key={u.id} className={!u.actif ? 'opacity-60' : ''}>
                                        <td>
                                            <div className="flex items-center gap-2">
                                                <Avatar className="h-7 w-7 shrink-0">
                                                    <AvatarFallback className="text-[10px] bg-ink text-white">
                                                        {u.initiales || u.name?.[0]}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <span className="font-medium text-sm">{u.name}</span>
                                            </div>
                                        </td>
                                        <td className="text-slate-500 text-sm">{u.email}</td>
                                        <td>
                                            <RoleBadgeList roles={u.roles} />
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
                                                <Button variant="ghost" size="icon-sm" onClick={() => openEdit(u)} title="Modifier">
                                                    <Pencil className="h-3.5 w-3.5 text-slate-400" />
                                                </Button>
                                                <Button variant="ghost" size="icon-sm" onClick={() => toggleActif(u)}
                                                    title={u.actif ? 'Désactiver' : 'Activer'}>
                                                    {u.actif
                                                        ? <XCircle className="h-3.5 w-3.5 text-slate-400" />
                                                        : <CheckCircle className="h-3.5 w-3.5 text-slate-400" />
                                                    }
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>

            <ModalUtilisateur
                open={modal.open}
                onClose={closeModal}
                utilisateur={modal.user}
                roles={roles}
            />
        </AppLayout>
    );
}
