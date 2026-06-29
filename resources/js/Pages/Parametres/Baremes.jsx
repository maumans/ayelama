import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Scale, Plus, Trash2, ChevronDown, ChevronUp,
    Percent, X
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

const ORGANISME_COLORS = {
    APIP:         'bg-blue-50 text-blue-700',
    Impots:       'bg-amber-50 text-amber-800',
    Conservation: 'bg-green-50 text-green-700',
    CNSS:         'bg-purple-50 text-purple-700',
    Notaire:      'bg-ink/10 text-ink',
    Autre:        'bg-slate-100 text-slate-600',
};

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
            onSuccess: () => {
                onClose();
                setForm({ type_acte_id: '', organisme: 'Impots', libelle: '', taux: '', montant_fixe: '', base_calcul: 'valeur_acte', description: '' });
            },
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
                                    {typesActes?.map(t => (
                                        <SelectItem key={t.id} value={String(t.id)}>{t.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.type_acte_id && <p className="text-xs text-danger-text">{errors.type_acte_id}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <Label>Organisme</Label>
                            <Select value={form.organisme} onValueChange={v => setForm(f => ({ ...f, organisme: v }))}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {organismes?.map(o => <SelectItem key={o} value={o}>{o}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Libellé</Label>
                        <Input
                            placeholder="ex : Droits d'enregistrement"
                            value={form.libelle}
                            onChange={e => setForm(f => ({ ...f, libelle: e.target.value }))}
                        />
                        {errors.libelle && <p className="text-xs text-danger-text">{errors.libelle}</p>}
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
                                <Input
                                    type="number"
                                    step="0.0001"
                                    min="0"
                                    max="100"
                                    placeholder="2.5"
                                    className="pr-8"
                                    value={form.taux}
                                    onChange={e => setForm(f => ({ ...f, taux: e.target.value }))}
                                />
                                <Percent className="absolute right-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                            </div>
                            {errors.taux && <p className="text-xs text-danger-text">{errors.taux}</p>}
                        </div>
                    ) : (
                        <div className="space-y-1.5">
                            <Label>Montant fixe (GNF)</Label>
                            <div className="relative">
                                <Input
                                    type="number"
                                    step="1000"
                                    min="0"
                                    placeholder="50000"
                                    className="pr-14"
                                    value={form.montant_fixe}
                                    onChange={e => setForm(f => ({ ...f, montant_fixe: e.target.value }))}
                                />
                                <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-ref">GNF</span>
                            </div>
                            {errors.montant_fixe && <p className="text-xs text-danger-text">{errors.montant_fixe}</p>}
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <Label>Description <span className="text-slate-400">(optionnel)</span></Label>
                        <Input
                            placeholder="Note ou précision sur ce barème"
                            value={form.description}
                            onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
                        />
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

    const toggleActif = (bareme) => {
        router.patch(`/parametres/baremes/${bareme.id}`, { actif: !bareme.actif }, { preserveScroll: true });
    };

    const supprimer = (bareme) => setConfirmState({
        title: `Supprimer "${bareme.libelle}" ?`,
        description: 'Ce barème sera définitivement supprimé.',
        confirmLabel: 'Supprimer',
        variant: 'destructive',
        onConfirm: () => router.delete(`/parametres/baremes/${bareme.id}`, { preserveScroll: true }),
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
            <button
                onClick={() => setOpen(o => !o)}
                className="w-full flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition-colors text-left"
            >
                <div className="flex items-center gap-3">
                    <span className="font-medium text-sm text-ink">{typeActe.label}</span>
                    <span className="text-[10px] text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                        {typeActe.categorieLabel}
                    </span>
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
                                        <td>
                                            <Switch
                                                checked={b.actif}
                                                onCheckedChange={() => toggleActif(b)}
                                            />
                                        </td>
                                        <td>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                className="text-slate-300 hover:text-danger"
                                                onClick={() => supprimer(b)}
                                            >
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

export default function ParametresBaremes() {
    const { typesActes = [], categories = [], organismes = [], filters = {}, stats = {} } = usePage().props;

    const [categorie, setCategorie] = useState(filters.categorie ?? '');
    const [modalOpen, setModalOpen] = useState(false);

    const allTypesActes = typesActes.map(t => ({ id: t.id, label: t.label }));

    return (
        <AppLayout breadcrumbs={[{ label: 'Paramètres', href: '/parametres' }, { label: 'Barèmes & Taux' }]}>
            <Head title="Barèmes — Ayelema" />

            <div className="p-6 max-w-[1000px] mx-auto space-y-5">

                {/* En-tête */}
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-display text-ink">Barèmes & Taux</h1>
                        <p className="text-slate-500 text-sm mt-1">Taux fiscaux et honoraires notariaux par type d'acte</p>
                    </div>
                    <Button variant="seal" onClick={() => setModalOpen(true)}>
                        <Plus className="h-4 w-4" />
                        Nouveau barème
                    </Button>
                </div>

                {/* KPIs */}
                <div className="grid grid-cols-2 gap-3">
                    <Card className="p-4 text-center">
                        <div className="text-2xl font-bold text-ink">{stats.total ?? 0}</div>
                        <div className="text-xs text-slate-500 mt-0.5">Barèmes configurés</div>
                    </Card>
                    <Card className="p-4 text-center">
                        <div className="text-2xl font-bold text-success">{stats.actifs ?? 0}</div>
                        <div className="text-xs text-slate-500 mt-0.5">Actifs</div>
                    </Card>
                </div>

                {/* Filtre catégorie */}
                <div className="flex items-center gap-2">
                    <Select
                        value={categorie}
                        onValueChange={v => {
                            setCategorie(v);
                            router.get('/parametres/baremes', { categorie: v }, { preserveState: true, replace: true });
                        }}
                    >
                        <SelectTrigger className="h-8 text-sm w-52">
                            <SelectValue placeholder="Toutes catégories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Toutes catégories</SelectItem>
                            {categories.map(c => <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>)}
                        </SelectContent>
                    </Select>

                    {categorie && (
                        <Button variant="ghost" size="sm" className="h-8 text-slate-400"
                            onClick={() => { setCategorie(''); router.get('/parametres/baremes', {}, { preserveState: true, replace: true }); }}>
                            <X className="h-3.5 w-3.5 mr-1" /> Tout voir
                        </Button>
                    )}

                    <span className="ml-auto text-xs text-slate-400">{typesActes.length} type{typesActes.length > 1 ? 's' : ''} d'actes</span>
                </div>

                {/* Légende organismes */}
                <div className="flex flex-wrap gap-1.5">
                    {Object.entries(ORGANISME_COLORS).map(([org, cls]) => (
                        <span key={org} className={cn('inline-flex text-[10px] font-medium px-2 py-0.5 rounded-full', cls)}>{org}</span>
                    ))}
                </div>

                {/* Liste par type d'acte */}
                {typesActes.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-20 text-center">
                            <Scale className="h-12 w-12 text-slate-200 mb-4" />
                            <h3 className="font-serif text-heading text-slate-500">Aucun type d'acte</h3>
                            <p className="text-sm text-slate-400 mt-1">Configurez d'abord des types d'actes.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-2">
                        {typesActes.map((t, i) => (
                            <motion.div
                                key={t.id}
                                initial={{ opacity: 0, y: 5 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: i * 0.03 }}
                            >
                                <TypeActeRow typeActe={t} />
                            </motion.div>
                        ))}
                    </div>
                )}
            </div>

            <ModalAjouterBareme
                open={modalOpen}
                onClose={() => setModalOpen(false)}
                typesActes={allTypesActes}
                organismes={organismes}
            />
        </AppLayout>
    );
}
