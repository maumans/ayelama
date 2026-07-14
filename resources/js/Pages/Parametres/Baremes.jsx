import React, { useState, useEffect } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Scale, Plus, Trash2, ChevronDown, ChevronUp,
    Percent, X, ClipboardCheck, Pencil,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NumberField } from '@/components/ui/number-field';
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
import { cn } from '@/lib/utils';

const EMPTY_BAREME_FORM = {
    applicable_tous: false, type_acte_ids: [], organisme: 'Impots', libelle: '',
    taux: '', montant_fixe: '', base_calcul: 'valeur_acte', description: '',
    genere_formalite: false, depend_de_bareme_id: '',
    type_impot: '', retour_attendu: '', delai_heures: '', pieces_requises: [],
};

const ORGANISME_COLORS = {
    APIP:         'bg-blue-50 text-blue-700',
    Impots:       'bg-amber-50 text-amber-800',
    Conservation: 'bg-green-50 text-green-700',
    CNSS:         'bg-purple-50 text-purple-700',
    Greffe:       'bg-fuchsia-50 text-fuchsia-700',
    Notaire:      'bg-ink/10 text-ink',
    Autre:        'bg-slate-100 text-slate-600',
};

function ModalAjouterBareme({ open, onClose, bareme = null, typesActes, typesActesAvecBaremes, organismes }) {
    const isEdit = Boolean(bareme);
    const [form, setForm] = useState(EMPTY_BAREME_FORM);
    const [nouvellePiece, setNouvellePiece] = useState('');
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (open) {
            setForm(isEdit ? {
                applicable_tous:  false,
                type_acte_ids:    [],
                organisme:        bareme.organisme ?? 'Impots',
                libelle:          bareme.libelle ?? '',
                taux:             bareme.taux ?? '',
                montant_fixe:     bareme.montant_fixe ?? '',
                base_calcul:      bareme.base_calcul ?? 'valeur_acte',
                description:      bareme.description ?? '',
                genere_formalite: !!bareme.genere_formalite,
                depend_de_bareme_id: bareme.depend_de_bareme_id ? String(bareme.depend_de_bareme_id) : '',
                type_impot:       bareme.type_impot ?? '',
                retour_attendu:   bareme.retour_attendu ?? '',
                delai_heures:     bareme.delai_heures ?? '',
                pieces_requises:  bareme.pieces_requises ?? [],
            } : EMPTY_BAREME_FORM);
            setErrors({});
            setNouvellePiece('');
        }
    }, [open, bareme?.id]);

    // Démarches candidates dont celle-ci peut dépendre — uniquement celles du même
    // type d'acte qui génèrent elles-mêmes une formalité (le blocage n'a de sens
    // qu'entre démarches réellement générées pour ce type d'acte).
    const demarchesDependance = isEdit
        ? (typesActesAvecBaremes?.find(t => String(t.id) === String(bareme.type_acte_id))?.baremes ?? [])
            .filter(b => b.genere_formalite && String(b.id) !== String(bareme.id))
        : [];

    const ajouterPiece = () => {
        const label = nouvellePiece.trim();
        if (!label) return;
        setForm(f => ({ ...f, pieces_requises: [...f.pieces_requises, label] }));
        setNouvellePiece('');
    };
    const retirerPiece = (i) => setForm(f => ({ ...f, pieces_requises: f.pieces_requises.filter((_, idx) => idx !== i) }));

    const toggleTypeActe = (id) => {
        const key = String(id);
        setForm(f => ({
            ...f,
            type_acte_ids: f.type_acte_ids.includes(key)
                ? f.type_acte_ids.filter(v => v !== key)
                : [...f.type_acte_ids, key],
        }));
    };

    const submit = () => {
        const { applicable_tous, type_acte_ids, ...rest } = form;
        const payload = {
            ...rest,
            taux:         form.taux         !== '' ? parseFloat(form.taux)         : null,
            montant_fixe: form.montant_fixe !== '' ? parseFloat(form.montant_fixe) : null,
            delai_heures: form.delai_heures !== '' ? parseInt(form.delai_heures, 10) : null,
            depend_de_bareme_id: form.depend_de_bareme_id !== '' ? parseInt(form.depend_de_bareme_id, 10) : null,
            ...(isEdit ? {} : { applicable_tous, type_acte_ids }),
        };
        const opts = { onSuccess: () => onClose(), onError: setErrors };
        if (isEdit) {
            router.patch(`/parametres/baremes/${bareme.id}`, payload, opts);
        } else {
            router.post('/parametres/baremes', payload, opts);
        }
    };

    const groupesTypesActes = (typesActes ?? []).reduce((acc, t) => {
        const key = t.categorieLabel ?? 'Autre';
        if (!acc[key]) acc[key] = [];
        acc[key].push(t);
        return acc;
    }, {});

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle className="font-serif text-ink">{isEdit ? 'Modifier le barème' : 'Nouveau barème'}</DialogTitle>
                </DialogHeader>

                <div className="space-y-4 py-2">
                    <div className="space-y-1.5">
                        <Label>Type{isEdit ? " d'acte" : "s d'acte"}</Label>
                        {isEdit ? (
                            <div className="text-sm text-slate-700 px-3 py-2 rounded-lg border border-slate-200 bg-slate-50">
                                {typesActes?.find(t => String(t.id) === String(bareme.type_acte_id))?.label ?? '—'}
                            </div>
                        ) : (
                            <div className="rounded-lg border border-slate-200 p-3 space-y-3">
                                <label className="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer w-fit">
                                    <Checkbox checked={form.applicable_tous}
                                        onCheckedChange={(checked) => setForm(f => ({ ...f, applicable_tous: checked === true }))} />
                                    <span>Applicable à tous les types d'actes</span>
                                </label>
                                {!form.applicable_tous && (
                                    <div className="max-h-48 overflow-y-auto space-y-3 pt-1 border-t border-slate-100">
                                        {Object.entries(groupesTypesActes).map(([cat, items]) => (
                                            <div key={cat}>
                                                <p className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">{cat}</p>
                                                <div className="space-y-1.5">
                                                    {items.map(t => (
                                                        <label key={t.id} className="flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                                            <Checkbox checked={form.type_acte_ids.includes(String(t.id))}
                                                                onCheckedChange={() => toggleTypeActe(t.id)} />
                                                            <span>{t.label}</span>
                                                        </label>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                )}
                                {errors.type_acte_ids && <p className="text-xs text-danger-text">{errors.type_acte_ids}</p>}
                            </div>
                        )}
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
                                <NumberField
                                    decimals={4}
                                    placeholder="2.5"
                                    className="pr-8"
                                    value={form.taux}
                                    onValueChange={val => setForm(f => ({ ...f, taux: val }))}
                                />
                                <Percent className="absolute right-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                            </div>
                            {errors.taux && <p className="text-xs text-danger-text">{errors.taux}</p>}
                        </div>
                    ) : (
                        <div className="space-y-1.5">
                            <Label>Montant fixe (GNF)</Label>
                            <div className="relative">
                                <NumberField
                                    placeholder="50000"
                                    className="pr-14"
                                    value={form.montant_fixe}
                                    onValueChange={val => setForm(f => ({ ...f, montant_fixe: val }))}
                                />
                                <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 font-ref">GNF</span>
                            </div>
                            {errors.montant_fixe && <p className="text-xs text-danger-text">{errors.montant_fixe}</p>}
                        </div>
                    )}

                    <div className="rounded-lg border border-slate-200 p-3 space-y-3">
                        <label className="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer w-fit">
                            <Checkbox checked={form.genere_formalite}
                                onCheckedChange={(checked) => setForm(f => ({ ...f, genere_formalite: checked === true }))} />
                            <span className="flex items-center gap-1.5">
                                <ClipboardCheck className="h-3.5 w-3.5 text-seal-hover" />
                                Génère automatiquement une formalité à la création du dossier
                            </span>
                        </label>
                        {errors.genere_formalite && <p className="text-xs text-danger-text">{errors.genere_formalite}</p>}

                        {form.genere_formalite && (
                            <div className="space-y-3 pt-1">
                                <div className="grid grid-cols-3 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>Type d'impôt <span className="text-slate-400 text-xs">(optionnel)</span></Label>
                                        <Input value={form.type_impot} onChange={e => setForm(f => ({ ...f, type_impot: e.target.value }))}
                                            placeholder="droits_enregistrement" />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Retour attendu <span className="text-slate-400 text-xs">(optionnel)</span></Label>
                                        <Input value={form.retour_attendu} onChange={e => setForm(f => ({ ...f, retour_attendu: e.target.value }))}
                                            placeholder="Titre foncier mis à jour" />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>Délai <span className="text-slate-400 text-xs">(heures)</span></Label>
                                        <NumberField value={form.delai_heures}
                                            onValueChange={val => setForm(f => ({ ...f, delai_heures: val }))} placeholder="72" />
                                    </div>
                                </div>

                                {isEdit && (
                                    <div className="space-y-1.5">
                                        <Label>Dépend de <span className="text-slate-400 text-xs">(optionnel — bloque cette démarche tant que l'autre n'a pas de retour)</span></Label>
                                        <Select
                                            value={form.depend_de_bareme_id || '__none__'}
                                            onValueChange={v => setForm(f => ({ ...f, depend_de_bareme_id: v === '__none__' ? '' : v }))}
                                        >
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="__none__">Aucune dépendance</SelectItem>
                                                {demarchesDependance.map(d => (
                                                    <SelectItem key={d.id} value={String(d.id)}>{d.organisme} — {d.libelle}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.depend_de_bareme_id && <p className="text-xs text-danger-text">{errors.depend_de_bareme_id}</p>}
                                    </div>
                                )}

                                <div className="space-y-1.5">
                                    <Label>
                                        Pièces requises
                                        {form.pieces_requises.length > 0 && (
                                            <span className="ml-2 text-xs font-normal text-slate-400">
                                                {form.pieces_requises.length} pièce{form.pieces_requises.length > 1 ? 's' : ''}
                                            </span>
                                        )}
                                    </Label>
                                    <div className="rounded-lg border border-slate-200 bg-slate-50 p-2 min-h-[56px] flex flex-col gap-1.5">
                                        {form.pieces_requises.length === 0 && (
                                            <p className="text-xs text-slate-400 text-center my-auto py-3">
                                                Aucune pièce ajoutée — saisissez ci-dessous
                                            </p>
                                        )}
                                        {form.pieces_requises.map((label, i) => (
                                            <div key={i} className="flex items-center gap-2 bg-white rounded-md border border-slate-200 px-2.5 py-1.5 group">
                                                <span className="flex-1 text-sm text-slate-700">{label}</span>
                                                <button type="button" onClick={() => retirerPiece(i)}
                                                    className="text-slate-300 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100">
                                                    <X className="h-3.5 w-3.5" />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                    <div className="flex gap-2">
                                        <Input value={nouvellePiece} onChange={e => setNouvellePiece(e.target.value)}
                                            placeholder="ex : Copie CNI vendeur, Titre foncier original…"
                                            onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); ajouterPiece(); } }} />
                                        <Button type="button" variant="outline" size="sm" onClick={ajouterPiece}
                                            disabled={!nouvellePiece.trim()} className="shrink-0">
                                            <Plus className="h-3.5 w-3.5 mr-1" /> Ajouter
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

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
                    <Button variant="seal" onClick={submit}>{isEdit ? 'Enregistrer' : 'Ajouter le barème'}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function TypeActeRow({ typeActe, onEdit }) {
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
                                        <td className="font-medium text-sm text-slate-700">
                                            {b.libelle}
                                            {b.genere_formalite && (
                                                <span title="Génère une formalité à la création du dossier"
                                                    className="ml-1.5 inline-flex items-center gap-0.5 text-[9px] px-1.5 py-0 rounded-full bg-ink/10 text-ink font-medium align-middle">
                                                    <ClipboardCheck className="h-2.5 w-2.5" /> Formalité
                                                </span>
                                            )}
                                            {b.depend_de_bareme_id && (
                                                <div className="text-[10px] font-normal text-slate-400 mt-0.5">
                                                    Dépend de : {typeActe.baremes?.find(x => String(x.id) === String(b.depend_de_bareme_id))?.libelle ?? '—'}
                                                </div>
                                            )}
                                        </td>
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
                                            <div className="flex items-center gap-0.5">
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    className="text-slate-300 hover:text-ink"
                                                    onClick={() => onEdit?.(b)}
                                                >
                                                    <Pencil className="h-3.5 w-3.5" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    className="text-slate-300 hover:text-danger"
                                                    onClick={() => supprimer(b)}
                                                >
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
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
    const [modal, setModal] = useState({ open: false, bareme: null });

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
                    <Button variant="seal" onClick={() => setModal({ open: true, bareme: null })}>
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
                                <TypeActeRow typeActe={t} onEdit={(b) => setModal({ open: true, bareme: { ...b, type_acte_id: t.id } })} />
                            </motion.div>
                        ))}
                    </div>
                )}
            </div>

            <ModalAjouterBareme
                open={modal.open}
                onClose={() => setModal({ open: false, bareme: null })}
                bareme={modal.bareme}
                typesActes={allTypesActes}
                typesActesAvecBaremes={typesActes}
                organismes={organismes}
            />
        </AppLayout>
    );
}
