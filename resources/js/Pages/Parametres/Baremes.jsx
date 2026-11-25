import React, { useState, useEffect, useMemo } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Scale, Plus, Trash2, ChevronDown, ChevronUp,
    Percent, X, ClipboardCheck, Pencil, AlertTriangle, Search,
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
    taux: '', montant_fixe: '', quantite_defaut: '1', base_calcul: 'valeur_acte', description: '',
    remise_autorisee: false,
    nature: '',
    genere_formalite: false, depend_de_bareme_id: '',
    type_impot: '', retour_attendu: '', delai_heures: '', pieces_requises: [],
    donnees_au_retour: [],
};

const FILTRES_VIDES = {
    recherche: '', categorie: '', organisme: '', statut: '', calcul: '',
    formalite: false, aConfirmer: false, masquerVides: false,
};

/** Un barème passe-t-il les filtres portant sur le barème lui-même (pas sur son type d'acte) ? */
function baremeCorrespond(b, f) {
    if (f.organisme && b.organisme !== f.organisme) return false;
    if (f.statut === 'actifs' && !b.actif) return false;
    if (f.statut === 'inactifs' && b.actif) return false;
    if (f.calcul && b.base_calcul !== f.calcul) return false;
    if (f.formalite && !b.genere_formalite) return false;
    if (f.aConfirmer && !b.a_confirmer) return false;
    return true;
}

/**
 * « moins de 100 M : 500 000 · dès 100 M : 1 000 000 » — la grille d'un barème par tranche de
 * capital (débours des constitutions, 2026-11-23). Déclarée hors de tout composant : une `const`
 * placée sous son premier usage rendrait la page blanche (CLAUDE.md, règle 2).
 */
function grilleTranches(tranches = []) {
    const millions = (n) => `${Number(n) / 1_000_000} M`;
    const tri = [...tranches].sort((a, b) => a.a_partir_de - b.a_partir_de);
    return tri.map((t, i) => {
        const suivante = tri[i + 1];
        const borne = i === 0 && suivante
            ? `moins de ${millions(suivante.a_partir_de)}`
            : `dès ${millions(t.a_partir_de)}`;
        return `${borne} : ${Number(t.montant).toLocaleString('fr-GN')}`;
    }).join(' · ');
}

const ORGANISME_COLORS = {
    APIP:         'bg-blue-50 text-blue-700',
    Impots:       'bg-amber-50 text-amber-800',
    Conservation: 'bg-green-50 text-green-700',
    CNSS:         'bg-purple-50 text-purple-700',
    Greffe:       'bg-fuchsia-50 text-fuchsia-700',
    Notaire:      'bg-ink/10 text-ink',
    Autre:        'bg-slate-100 text-slate-600',
};

function ModalAjouterBareme({ open, onClose, bareme = null, typeActePreselection = null, typesActes, typesActesAvecBaremes, organismes, donneesAuRetour = [] }) {
    const isEdit = Boolean(bareme);
    const naturesLigne = usePage().props.referentiels?.naturesLigne ?? [];
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
                quantite_defaut:  String(bareme.quantite_defaut ?? 1),
                remise_autorisee: !!bareme.remise_autorisee,
                nature:           bareme.nature ?? '',
                base_calcul:      bareme.base_calcul ?? 'valeur_acte',
                description:      bareme.description ?? '',
                genere_formalite: !!bareme.genere_formalite,
                depend_de_bareme_id: bareme.depend_de_bareme_id ? String(bareme.depend_de_bareme_id) : '',
                type_impot:       bareme.type_impot ?? '',
                retour_attendu:   bareme.retour_attendu ?? '',
                delai_heures:     bareme.delai_heures ?? '',
                pieces_requises:  bareme.pieces_requises ?? [],
                donnees_au_retour: bareme.donnees_au_retour ?? [],
            } : {
                // Ouverte depuis la carte d'un type d'acte : ce type est pré-coché, les autres
                // restent cochables (un même barème peut valoir pour plusieurs types).
                ...EMPTY_BAREME_FORM,
                type_acte_ids: typeActePreselection ? [String(typeActePreselection)] : [],
            });
            setErrors({});
            setNouvellePiece('');
        }
    }, [open, bareme?.id, typeActePreselection]);

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
        const { applicable_tous, type_acte_ids, nature, ...rest } = form;
        const payload = {
            ...rest,
            // Vide = déduite de l'organisme par le serveur (Bareme::booted) ; jamais envoyée vide.
            ...(nature ? { nature } : {}),
            taux:         form.taux         !== '' ? parseFloat(form.taux)         : null,
            montant_fixe: form.montant_fixe !== '' ? parseFloat(form.montant_fixe) : null,
            quantite_defaut: parseInt(form.quantite_defaut, 10) || 1,
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

                    {bareme?.tranches?.length ? (
                        <div className="space-y-1.5">
                            <Label>Montant par tranche de capital (GNF)</Label>
                            <p className="text-sm text-slate-700 bg-slate-50 rounded-lg px-3 py-2 font-ref">
                                {grilleTranches(bareme.tranches)}
                            </p>
                            <p className="text-xs text-slate-400">
                                Grille fixée par l'étude, non modifiable ici : elle se corrige dans
                                ReglesGestionHonorairesSeeder::GRILLE_DEBOURS.
                            </p>
                        </div>
                    ) : form.base_calcul === 'valeur_acte' ? (
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

                    <div className="space-y-1.5">
                        <Label>Quantité par défaut</Label>
                        <NumberField
                            placeholder="1"
                            value={form.quantite_defaut}
                            onValueChange={val => setForm(f => ({ ...f, quantite_defaut: val }))}
                        />
                        <p className="text-xs text-slate-400">
                            Nombre d'exemplaires, de pages… appliqué par défaut à la ligne de facture
                            générée — ajustable ensuite au cas par cas sur chaque dossier.
                        </p>
                        {errors.quantite_defaut && <p className="text-xs text-danger-text">{errors.quantite_defaut}</p>}
                    </div>

                    {/* Nature (2026-11-23) : ce qui fonde la comptabilité des dossiers — l'étude
                        garde un honoraire, elle reverse un débours. Liste servie par le serveur. */}
                    <div className="space-y-1.5">
                        <Label>Nature</Label>
                        <select
                            value={form.nature}
                            onChange={e => setForm(f => ({ ...f, nature: e.target.value }))}
                            className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal"
                        >
                            <option value="">Selon l'organisme (Notaire → honoraire, sinon débours)</option>
                            {naturesLigne.map(n => <option key={n.valeur} value={n.valeur}>{n.label}</option>)}
                        </select>
                        {errors.nature && <p className="text-xs text-danger-text">{errors.nature}</p>}
                    </div>

                    {/* Remise — distinct de la formalité, et volontairement au-dessus : c'est la
                        nature de la ligne (honoraire ou débours) qui décide, pas son circuit. */}
                    <div className="rounded-lg border border-slate-200 p-3">
                        <label className="flex items-start gap-2.5 text-sm text-slate-700 cursor-pointer w-fit">
                            <Checkbox checked={form.remise_autorisee}
                                onCheckedChange={(checked) => setForm(f => ({ ...f, remise_autorisee: checked === true }))} />
                            <span>
                                <span className="flex items-center gap-1.5">
                                    <Percent className="h-3.5 w-3.5 text-seal-hover" />
                                    Cette ligne peut recevoir une remise
                                </span>
                                <span className="block text-xs text-slate-500 mt-0.5">
                                    À réserver aux honoraires de l'étude. Un débours — impôts, greffe,
                                    APIP, journal — est versé intégralement au tiers : le remiser ferait
                                    perdre de l'argent réel, pas de la marge.
                                </span>
                            </span>
                        </label>
                        {errors.remise_autorisee && <p className="text-xs text-danger-text">{errors.remise_autorisee}</p>}
                    </div>

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

                                {/* ── Données délivrées par l'autorité ──────────────────
                                    Distinct des pièces requises, qui sont des **fichiers** :
                                    ici on déclare les **valeurs** que le formaliste devra
                                    saisir au retour, et qui seront portées à la fiche société.

                                    La liste vient du serveur (`donneesAuRetour`) : cet écran
                                    ne porte aucun nom de donnée, et un cas ajouté à l'enum y
                                    apparaît sans toucher au front. */}
                                <div className="mt-3 pt-3 border-t border-slate-100">
                                    <Label className="text-xs">
                                        Informations délivrées au retour
                                        <span className="text-slate-400"> — reportées à la fiche société</span>
                                    </Label>
                                    <div className="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                                        {(donneesAuRetour ?? []).map(d => {
                                            const coche = (form.donnees_au_retour ?? []).includes(d.valeur);
                                            return (
                                                <label key={d.valeur} className="flex items-start gap-2 text-sm cursor-pointer">
                                                    <input
                                                        type="checkbox"
                                                        checked={coche}
                                                        onChange={() => setForm(f => ({
                                                            ...f,
                                                            donnees_au_retour: coche
                                                                ? f.donnees_au_retour.filter(v => v !== d.valeur)
                                                                : [...(f.donnees_au_retour ?? []), d.valeur],
                                                        }))}
                                                        className="mt-1"
                                                    />
                                                    <span>
                                                        <span className="text-ink">{d.label}</span>
                                                        {d.exemple && <span className="block text-[11px] text-slate-400 font-mono">{d.exemple}</span>}
                                                    </span>
                                                </label>
                                            );
                                        })}
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

function TypeActeRow({ typeActe, baremesVisibles, filtreActif = false, onEdit, onAdd }) {
    const total = typeActe.baremes?.length ?? 0;
    const visibles = baremesVisibles ?? typeActe.baremes ?? [];
    const [open, setOpen] = useState(total > 0);
    const [confirmState, setConfirmState] = useState(null);

    // Un filtre qui vient de faire apparaître des correspondances les montre : une carte
    // repliée sur un résultat de recherche le cacherait.
    useEffect(() => {
        if (filtreActif && visibles.length > 0) setOpen(true);
    }, [filtreActif, visibles.length]);

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
            {/* En-tête : la zone de repli et le bouton d'ajout sont deux contrôles frères —
                un bouton dans un bouton est du HTML invalide, et le clic remonterait. */}
            <div className="flex items-center gap-2 pr-3 hover:bg-slate-50 transition-colors">
                <button
                    type="button"
                    onClick={() => setOpen(o => !o)}
                    className="flex-1 min-w-0 flex items-center justify-between gap-3 pl-4 py-3 text-left"
                >
                    <div className="flex items-center gap-3 min-w-0 flex-wrap">
                        <span className="font-medium text-sm text-ink">{typeActe.label}</span>
                        <span className="text-[10px] text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                            {typeActe.categorieLabel}
                        </span>
                        <span className="text-[10px] text-slate-400">
                            {filtreActif && visibles.length !== total
                                ? `${visibles.length} / ${total} barème${total > 1 ? 's' : ''}`
                                : `${total} barème${total > 1 ? 's' : ''}`}
                        </span>
                    </div>
                    {open ? <ChevronUp className="h-4 w-4 shrink-0 text-slate-300" /> : <ChevronDown className="h-4 w-4 shrink-0 text-slate-300" />}
                </button>
                <Button
                    variant="outline"
                    size="sm"
                    className="h-7 shrink-0 text-xs"
                    onClick={() => onAdd?.(typeActe.id)}
                    title={`Ajouter un barème à « ${typeActe.label} »`}
                >
                    <Plus className="h-3.5 w-3.5" />
                    Ajouter
                </Button>
            </div>

            {open && (
                <div className="border-t border-slate-100">
                    {visibles.length === 0 ? (
                        <div className="flex flex-col items-center gap-2 py-6">
                            <p className="text-sm text-slate-400 text-center">
                                {total === 0
                                    ? "Aucun barème configuré pour ce type d'acte."
                                    : 'Aucun barème ne correspond aux filtres.'}
                            </p>
                            {total === 0 && (
                                <Button variant="ghost" size="sm" className="text-seal-hover" onClick={() => onAdd?.(typeActe.id)}>
                                    <Plus className="h-3.5 w-3.5" /> Ajouter le premier barème
                                </Button>
                            )}
                        </div>
                    ) : (
                        <table className="table-notarial w-full">
                            <thead>
                                <tr>
                                    <th>Organisme</th>
                                    <th>Libellé</th>
                                    <th>Calcul</th>
                                    <th>Taux / Montant</th>
                                    <th>Qté</th>
                                    <th>Actif</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                {visibles.map(b => (
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
                                            {/* D'où vient ce montant. Un tarif qu'aucune note de frais de
                                                l'étude ne donne part quand même sur une facture : il doit
                                                au moins le dire, ici et sur la note. Voir la migration
                                                `ajouter_source_aux_baremes`. */}
                                            {b.a_confirmer ? (
                                                <div className="mt-0.5 flex items-start gap-1 text-[10px] font-normal text-amber-700" title={b.source ?? ''}>
                                                    <AlertTriangle className="mt-px h-2.5 w-2.5 shrink-0" />
                                                    <span>Montant non confirmé par l'étude</span>
                                                </div>
                                            ) : b.source && b.actif ? (
                                                <div className="mt-0.5 text-[10px] font-normal text-slate-400" title={b.source}>
                                                    {b.source.length > 64 ? b.source.slice(0, 64) + '…' : b.source}
                                                </div>
                                            ) : !b.actif && b.source ? (
                                                <div className="mt-0.5 text-[10px] font-normal text-slate-400 italic" title={b.source}>
                                                    {b.source.length > 72 ? b.source.slice(0, 72) + '…' : b.source}
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="text-xs text-slate-500">
                                            {b.tranches?.length ? 'Par tranche de capital' : b.base_calcul === 'valeur_acte' ? '% valeur acte' : 'Montant fixe'}
                                        </td>
                                        <td className="font-ref text-seal font-semibold text-sm">
                                            {b.tranches?.length
                                                ? <span className="text-xs">{grilleTranches(b.tranches)}</span>
                                                : b.base_calcul === 'valeur_acte'
                                                ? `${parseFloat(b.taux ?? 0).toFixed(4).replace(/\.?0+$/, '')} %`
                                                : `${Number(b.montant_fixe ?? 0).toLocaleString('fr-GN')} GNF`
                                            }
                                        </td>
                                        <td className="font-ref text-sm text-slate-600">
                                            {b.quantite_defaut ?? 1}
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

const TOUS = '__tous__';

/** Select de filtre : Radix refuse un `SelectItem` de valeur vide — d'où la sentinelle `TOUS`. */
function FiltreSelect({ value, onChange, placeholder, options, className }) {
    return (
        <Select value={value || TOUS} onValueChange={v => onChange(v === TOUS ? '' : v)}>
            <SelectTrigger className={cn('h-9 text-sm', value ? 'border-seal/50 bg-seal/5' : '', className)}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={TOUS}>{placeholder}</SelectItem>
                {options.map(o => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
            </SelectContent>
        </Select>
    );
}

function FiltreBascule({ actif, onChange, children }) {
    return (
        <button
            type="button"
            onClick={() => onChange(!actif)}
            className={cn(
                'inline-flex items-center gap-1.5 h-8 px-3 rounded-full border text-xs font-medium transition-colors',
                actif ? 'border-seal bg-seal/10 text-seal-hover' : 'border-slate-200 text-slate-500 hover:bg-slate-50',
            )}
        >
            {children}
        </button>
    );
}

export default function ParametresBaremes() {
    const { typesActes = [], categories = [], organismes = [], filters = {}, stats = {}, donneesAuRetour = [] } = usePage().props;

    // Filtrage entièrement côté écran : le serveur sert tous les types d'actes, si bien que la
    // modale de création les propose toujours tous. Seule la catégorie reste lisible dans l'URL
    // (`?categorie=`), pour les liens existants.
    const [filtres, setFiltres] = useState({ ...FILTRES_VIDES, categorie: filters.categorie ?? '' });
    const [modal, setModal] = useState({ open: false, bareme: null, typeActeId: null });

    const majFiltre = (cle, valeur) => setFiltres(f => ({ ...f, [cle]: valeur }));
    const nbFiltresActifs = Object.values(filtres).filter(v => v !== '' && v !== false).length;

    // Filtres portant sur le barème lui-même (hors recherche) : quand l'un d'eux est posé, un
    // type d'acte sans barème correspondant n'a plus rien à montrer et disparaît.
    const filtreSurBaremes = Boolean(
        filtres.organisme || filtres.statut || filtres.calcul || filtres.formalite || filtres.aConfirmer,
    );
    const filtreActif = filtreSurBaremes || filtres.recherche.trim() !== '';

    const typesFiltres = useMemo(() => {
        const q = filtres.recherche.trim().toLowerCase();
        return typesActes
            .filter(t => !filtres.categorie || t.categorie === filtres.categorie)
            .map(t => {
                const typeCorrespond = q !== '' && t.label.toLowerCase().includes(q);
                const baremesVisibles = (t.baremes ?? []).filter(b =>
                    baremeCorrespond(b, filtres)
                    && (q === '' || typeCorrespond
                        || b.libelle?.toLowerCase().includes(q)
                        || b.organisme?.toLowerCase().includes(q)
                        || b.description?.toLowerCase().includes(q)),
                );
                return { typeActe: t, baremesVisibles, typeCorrespond };
            })
            .filter(({ typeActe, baremesVisibles, typeCorrespond }) => {
                if (filtres.masquerVides && (typeActe.baremes?.length ?? 0) === 0) return false;
                if (!filtreActif) return true;
                if (baremesVisibles.length > 0) return true;
                // Une recherche qui nomme le type d'acte le garde même vide : c'est le chemin
                // le plus direct vers son bouton « Ajouter ».
                return typeCorrespond && !filtreSurBaremes;
            });
    }, [typesActes, filtres, filtreActif, filtreSurBaremes]);

    const nbBaremesVisibles = typesFiltres.reduce((n, x) => n + x.baremesVisibles.length, 0);

    // Les organismes proposés au filtre : la liste de référence, plus ceux réellement portés
    // par un barème (un barème ancien peut citer un organisme retiré de la liste).
    const optionsOrganismes = useMemo(() => {
        const presents = typesActes.flatMap(t => (t.baremes ?? []).map(b => b.organisme)).filter(Boolean);
        return [...new Set([...organismes, ...presents])].map(o => ({ value: o, label: o }));
    }, [typesActes, organismes]);

    // `categorieLabel` est transmis : la modale range ses cases à cocher par catégorie, et sans
    // lui tout tombait sous « Autre ».
    const allTypesActes = typesActes.map(t => ({ id: t.id, label: t.label, categorieLabel: t.categorieLabel }));

    const ouvrirCreation = (typeActeId = null) => setModal({ open: true, bareme: null, typeActeId });

    return (
        <AppLayout breadcrumbs={[{ label: 'Paramètres', href: '/parametres' }, { label: 'Barèmes & Taux' }]}>
            <Head title="Barèmes — Ayelema" />

            <div className="p-6 max-w-[1000px] mx-auto space-y-5">

                {/* En-tête */}
                <div>
                    <h1 className="font-serif text-display text-ink">Barèmes & Taux</h1>
                    <p className="text-slate-500 text-sm mt-1">Taux fiscaux et honoraires notariaux par type d'acte</p>
                </div>

                {/* KPIs. Le troisième compte ce que l'étude doit relire : un tarif qu'aucune de
                    ses notes de frais ne donne part quand même sur une facture. Affiché même à
                    zéro serait trompeur — une carte à zéro invite à cliquer pour rien.
                    « Actifs » et « À confirmer » sont cliquables : ils posent le filtre. */}
                <div className={cn('grid gap-3', stats.aConfirmer > 0 ? 'grid-cols-3' : 'grid-cols-2')}>
                    <Card className="p-4 text-center">
                        <div className="text-2xl font-bold text-ink">{stats.total ?? 0}</div>
                        <div className="text-xs text-slate-500 mt-0.5">Barèmes configurés</div>
                    </Card>
                    <Card
                        className={cn('p-4 text-center cursor-pointer transition-colors hover:border-success/40',
                            filtres.statut === 'actifs' && 'border-success/60')}
                        onClick={() => majFiltre('statut', filtres.statut === 'actifs' ? '' : 'actifs')}
                    >
                        <div className="text-2xl font-bold text-success">{stats.actifs ?? 0}</div>
                        <div className="text-xs text-slate-500 mt-0.5">Actifs</div>
                    </Card>
                    {stats.aConfirmer > 0 && (
                        <Card
                            className={cn('p-4 text-center border-amber-200 bg-amber-50/60 cursor-pointer transition-colors hover:border-amber-300',
                                filtres.aConfirmer && 'border-amber-400')}
                            onClick={() => majFiltre('aConfirmer', !filtres.aConfirmer)}
                        >
                            <div className="text-2xl font-bold text-amber-700">{stats.aConfirmer}</div>
                            <div className="text-xs text-amber-700/80 mt-0.5">À confirmer par l'étude</div>
                        </Card>
                    )}
                </div>

                {/* Barre de filtres. Le bouton de création global y est rangé, juste au-dessus de
                    la liste qu'il alimente ; chaque carte a en plus son propre « Ajouter », qui
                    pré-coche son type d'acte. */}
                <Card className="p-3 space-y-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative flex-1 min-w-[220px]">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                            <Input
                                className="h-9 pl-8 text-sm"
                                placeholder="Rechercher un libellé, un type d'acte, un organisme…"
                                value={filtres.recherche}
                                onChange={e => majFiltre('recherche', e.target.value)}
                            />
                        </div>
                        <Button variant="seal" className="h-9" onClick={() => ouvrirCreation()}>
                            <Plus className="h-4 w-4" />
                            Nouveau barème
                        </Button>
                    </div>

                    <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <FiltreSelect value={filtres.categorie} onChange={v => majFiltre('categorie', v)}
                            placeholder="Toutes catégories" options={categories} />
                        <FiltreSelect value={filtres.organisme} onChange={v => majFiltre('organisme', v)}
                            placeholder="Tous organismes" options={optionsOrganismes} />
                        <FiltreSelect value={filtres.statut} onChange={v => majFiltre('statut', v)}
                            placeholder="Actifs et inactifs"
                            options={[{ value: 'actifs', label: 'Actifs seulement' }, { value: 'inactifs', label: 'Inactifs seulement' }]} />
                        <FiltreSelect value={filtres.calcul} onChange={v => majFiltre('calcul', v)}
                            placeholder="Tout mode de calcul"
                            options={[{ value: 'valeur_acte', label: '% de la valeur' }, { value: 'montant_fixe', label: 'Montant fixe' }]} />
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <FiltreBascule actif={filtres.formalite} onChange={v => majFiltre('formalite', v)}>
                            <ClipboardCheck className="h-3.5 w-3.5" /> Génère une formalité
                        </FiltreBascule>
                        <FiltreBascule actif={filtres.aConfirmer} onChange={v => majFiltre('aConfirmer', v)}>
                            <AlertTriangle className="h-3.5 w-3.5" /> Montant à confirmer
                        </FiltreBascule>
                        <FiltreBascule actif={filtres.masquerVides} onChange={v => majFiltre('masquerVides', v)}>
                            Masquer les types sans barème
                        </FiltreBascule>

                        {nbFiltresActifs > 0 && (
                            <Button variant="ghost" size="sm" className="h-8 text-slate-400"
                                onClick={() => setFiltres(FILTRES_VIDES)}>
                                <X className="h-3.5 w-3.5 mr-1" /> Réinitialiser ({nbFiltresActifs})
                            </Button>
                        )}

                        <span className="ml-auto text-xs text-slate-400">
                            {typesFiltres.length} type{typesFiltres.length > 1 ? 's' : ''} d'actes
                            {' · '}{nbBaremesVisibles} barème{nbBaremesVisibles > 1 ? 's' : ''}
                        </span>
                    </div>
                </Card>

                {/* Légende organismes — un clic filtre sur l'organisme, un second le retire. */}
                <div className="flex flex-wrap gap-1.5">
                    {Object.entries(ORGANISME_COLORS).map(([org, cls]) => (
                        <button
                            key={org}
                            type="button"
                            onClick={() => majFiltre('organisme', filtres.organisme === org ? '' : org)}
                            className={cn('inline-flex text-[10px] font-medium px-2 py-0.5 rounded-full transition-opacity', cls,
                                filtres.organisme && filtres.organisme !== org && 'opacity-40',
                                filtres.organisme === org && 'ring-1 ring-current')}
                        >
                            {org}
                        </button>
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
                ) : typesFiltres.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-14 text-center">
                            <Search className="h-10 w-10 text-slate-200 mb-3" />
                            <h3 className="font-serif text-heading text-slate-500">Aucun résultat</h3>
                            <p className="text-sm text-slate-400 mt-1">Aucun barème ne correspond à ces filtres.</p>
                            <Button variant="outline" size="sm" className="mt-4" onClick={() => setFiltres(FILTRES_VIDES)}>
                                <X className="h-3.5 w-3.5 mr-1" /> Réinitialiser les filtres
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-2">
                        {typesFiltres.map(({ typeActe: t, baremesVisibles }, i) => (
                            <motion.div
                                key={t.id}
                                initial={{ opacity: 0, y: 5 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: Math.min(i, 15) * 0.03 }}
                            >
                                <TypeActeRow
                                    typeActe={t}
                                    baremesVisibles={baremesVisibles}
                                    filtreActif={filtreActif}
                                    onAdd={ouvrirCreation}
                                    onEdit={(b) => setModal({ open: true, bareme: { ...b, type_acte_id: t.id }, typeActeId: null })}
                                />
                            </motion.div>
                        ))}
                    </div>
                )}
            </div>

            <ModalAjouterBareme
                open={modal.open}
                onClose={() => setModal({ open: false, bareme: null, typeActeId: null })}
                bareme={modal.bareme}
                typeActePreselection={modal.typeActeId}
                typesActes={allTypesActes}
                typesActesAvecBaremes={typesActes}
                organismes={organismes}
                donneesAuRetour={donneesAuRetour}
            />
        </AppLayout>
    );
}
