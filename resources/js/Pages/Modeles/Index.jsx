import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    FileText, Plus, Search, Trash2, Copy,
    CheckCircle2, XCircle, X, LayoutGrid, List, AlertTriangle,
    Pencil, ClipboardCopy, Check, ChevronDown, ChevronUp,
    ArrowUpDown, FileCheck, Paperclip, PenLine, Mail, Receipt,
    BookOpen, ShieldCheck, ClipboardList, Fingerprint,
    Newspaper, Building2, Calculator, LayoutList, Lock,
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

/**
 * Présentation des types de document — icône et couleur **uniquement**.
 *
 * Les libellés ont été retirés le 2026-08-11 : ils vivaient ici en copie de la référence PHP
 * (`HasTypeDocumentLabel::TYPES_DOCUMENT`) et avaient divergé — les quatre types de la modification
 * statutaire y manquaient, et leur slug brut ressortait à l'écran. Le libellé est du vocabulaire
 * métier, il vient donc du serveur (prop `typesDocument`, ou `typeDocLabel` porté par l'objet).
 * L'icône et la couleur, elles, sont de la présentation et n'ont rien à faire en base.
 *
 * Un type absent de cette table garde une icône neutre plutôt que de disparaître.
 */
const PRESENTATION_TYPE_DOC = {
    acte_principal:   { icon: FileCheck,     color: 'bg-ink/10 text-ink border-ink/20' },
    page_garde:       { icon: BookOpen,      color: 'bg-slate-50 text-slate-600 border-slate-200' },
    attestation:      { icon: ShieldCheck,   color: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    declaration:      { icon: ClipboardList, color: 'bg-violet-50 text-violet-700 border-violet-200' },
    dnsv:             { icon: Fingerprint,   color: 'bg-orange-50 text-orange-700 border-orange-200' },
    insertion:        { icon: Newspaper,     color: 'bg-cyan-50 text-cyan-700 border-cyan-200' },
    rccm:             { icon: Building2,     color: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
    acte_cession:     { icon: FileCheck,     color: 'bg-rose-50 text-rose-700 border-rose-200' },
    pv_modification:  { icon: ClipboardList, color: 'bg-teal-50 text-teal-700 border-teal-200' },
    statuts_maj:      { icon: BookOpen,      color: 'bg-sky-50 text-sky-700 border-sky-200' },
    declaration_rccm: { icon: Building2,     color: 'bg-indigo-50 text-indigo-700 border-indigo-200' },
    note_frais:       { icon: Calculator,    color: 'bg-yellow-50 text-yellow-700 border-yellow-200' },
    bordereau:        { icon: LayoutList,    color: 'bg-pink-50 text-pink-700 border-pink-200' },
    annexe:           { icon: Paperclip,     color: 'bg-blue-50 text-blue-700 border-blue-200' },
    procedure:        { icon: PenLine,       color: 'bg-amber-50 text-amber-700 border-amber-200' },
    lettre:           { icon: Mail,          color: 'bg-purple-50 text-purple-700 border-purple-200' },
    recepisse:        { icon: Receipt,       color: 'bg-green-50 text-green-700 border-green-200' },
};

const PRESENTATION_NEUTRE = { icon: FileText, color: 'bg-slate-100 text-slate-600 border-slate-200' };

// ── Composants utilitaires ──────────────────────────────────────────────────

/** `label` vient du serveur ; à défaut, le slug reste lisible plutôt que de disparaître. */
function TypeDocBadge({ type, label, size = 'sm' }) {
    const presentation = PRESENTATION_TYPE_DOC[type] ?? PRESENTATION_NEUTRE;
    return (
        <span className={cn(
            'inline-flex items-center gap-1 font-medium border rounded-full',
            size === 'sm' ? 'text-[10px] px-2 py-0.5' : 'text-xs px-2.5 py-1',
            presentation.color
        )}>
            {label ?? type}
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

/**
 * Une moitié de la liste des rôles.
 *
 * `horsSujet` marque ceux qu'aucun type d'acte coché n'attend : ils restent cochables — préparer un
 * gabarit avant de le rattacher est légitime — mais la raison est dite, et un bouton rattache le
 * type qui donnerait un effet au rôle. C'est ce qui manquait : la contradiction était acceptée en
 * silence, et l'acte n'était produit nulle part.
 */
function ListeRoles({ titre, options, data, setData, horsSujet = false, estRoleInerte, typesAttendant, onRattacher }) {
    if (options.length === 0) return null;

    const basculer = (valeur) => setData('roles', data.roles.includes(valeur)
        ? data.roles.filter(x => x !== valeur)
        : [...data.roles, valeur]);

    return (
        <div>
            {titre && (
                <p className="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{titre}</p>
            )}
            <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                {options.map(td => {
                    const principal = td.value === data.type_document;
                    const coche     = data.roles.includes(td.value) || principal;
                    // Un rôle coché qui ne sert à rien : ni principal, ni attendu par un type coché.
                    // Le critère ne dépend **pas** du découpage de la liste — c'est ce qui manquait,
                    // une constitution cochée suffisait à faire taire tout avertissement.
                    const inerte    = coche && (estRoleInerte?.(td.value) ?? false);
                    const attendus  = inerte ? (typesAttendant?.(td.value) ?? []) : [];

                    return (
                        <div key={td.value} className={horsSujet || inerte ? 'sm:col-span-2' : undefined}>
                            <label className="flex cursor-pointer items-center gap-2 text-sm">
                                <Checkbox
                                    checked={coche}
                                    disabled={principal}
                                    onCheckedChange={() => basculer(td.value)}
                                />
                                <span className={principal || horsSujet || inerte ? 'text-slate-400' : 'text-slate-700'}>
                                    {td.label}
                                </span>
                            </label>

                            {attendus.length > 0 && (
                                <p className="ml-6 mt-0.5 text-xs text-warning-text">
                                    Sans effet : aucun type coché n'attend ce rôle. Attendu par{' '}
                                    {attendus.map((t, i) => (
                                        <span key={t.id}>
                                            {i > 0 && ', '}
                                            <button
                                                type="button"
                                                onClick={() => onRattacher?.(t.id)}
                                                className="underline hover:no-underline"
                                            >
                                                {t.label}
                                            </button>
                                        </span>
                                    ))}
                                    . Cliquez pour rattacher ce type.
                                </p>
                            )}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

// ── Modale Créer / Modifier ─────────────────────────────────────────────────

import { useForm } from '@inertiajs/react';

function ModalModele({ open, onClose, typesActes, categories, typesDocument, modele = null }) {
    const isEdit = !!modele;
    const { data, setData, post, patch, processing, errors, reset, clearErrors, transform } = useForm({
        nom:                 '',
        type_document:       'acte_principal',
        version:             '1.0',
        fichier:             null,
        chemin_fichier:      '',
        // Un modèle peut servir plusieurs types depuis le 2026-08-11 : les statuts, la DNSV et le
        // RCCM d'une SARLU valent aussi pour sa modification. Ces cases sont, depuis la
        // suppression de `type_acte_id`, la **seule** déclaration d'applicabilité.
        applicable_tous:     false,
        type_acte_ids:       [],
        // Variantes du type d'acte auxquelles le gabarit est restreint. Vide = toutes — un
        // procès-verbal sert les sept résolutions, un acte de cession une seule.
        variantes:           [],
        // Rôles remplis : le vocabulaire diffère entre procédures pour un même document (les
        // statuts sont `acte_principal` en création, `statuts_maj` en modification).
        roles:               [],
        rattachements:       [],
    });

    // sync si modele change (ouverture édition)
    React.useEffect(() => {
        if (open) {
            clearErrors();
            if (modele) {
                setData({
                    nom:                 modele.nom,
                    type_document:       modele.type_document,
                    version:             modele.version,
                    fichier:             null,
                    chemin_fichier:      modele.chemin_fichier,
                    applicable_tous:     !!modele.applicable_tous,
                    type_acte_ids:       (modele.type_acte_ids ?? []).map(String),
                    variantes:           [...new Set((modele.rattachements ?? []).map(r => r.variante).filter(Boolean))],
                    roles:               modele.roles ?? [modele.type_document],
                });
            } else {
                reset();
            }
        }
    }, [open, modele]);

    // Même groupage par catégorie que la modale des courriers.
    const groupesTypes = React.useMemo(() => {
        const libelles = Object.fromEntries((categories ?? []).map(c => [c.value, c.label]));

        return (typesActes ?? []).reduce((acc, t) => {
            const cle = libelles[t.categorie] ?? t.categorie ?? 'Autres';
            (acc[cle] ??= []).push(t);
            return acc;
        }, {});
    }, [typesActes, categories]);

    const basculerType = (id) => {
        const cle = String(id);
        setData('type_acte_ids', data.type_acte_ids.includes(cle)
            ? data.type_acte_ids.filter(v => v !== cle)
            : [...data.type_acte_ids, cle]);
    };

    /**
     * Compose les rattachements envoyés au serveur : un par couple (type d'acte, variante).
     *
     * Une variante cochée ne s'applique qu'aux types qui la déclarent — restreindre un gabarit de
     * vente à « cession de parts » n'aurait aucun sens. Les autres types reçoivent `null`, c'est-à-dire
     * « toutes les variantes ».
     */
    /**
     * Les noms de gabarits portent leur forme d'origine (« RCCM SARLU », « Statuts de SARL »).
     * Le nom est structurant — la génération dédoublonne dessus et « Régénérer » retrouve le
     * gabarit par lui — donc un gabarit partagé produira un acte portant ce nom dans **tous** les
     * dossiers concernés, y compris une modification. On le signale, sans jamais l'imposer.
     */
    const nomNeutreSuggere = (nom) => {
        const sansForme = String(nom ?? '')
            .replace(/\s*(de|du|d')?\s*(SARLU|SARL|SASU|SAS|SNC|SAU|SCS|GIE|SA)/gi, '')
            .replace(/\s{2,}/g, ' ')
            .trim();
        return sansForme && sansForme !== String(nom ?? '').trim() ? sansForme : null;
    };

    /**
     * Rôles attendus par les types d'actes cochés — `null` si l'un d'eux les accepte tous.
     *
     * `null` n'est pas « aucun » mais « tous » : une constitution, une vente ou un bail ne
     * déclarent pas de documents attendus. On ne scinde alors pas la liste — séparer là où tout est
     * pertinent serait mentir.
     */
    const typesChoisis = (typesActes ?? []).filter(t => data.type_acte_ids.includes(String(t.id)));

    /**
     * Rôles **explicitement attendus** par les types cochés.
     *
     * ⚠️ Un type sans attente déclarée — constitution, vente, bail — n'y contribue rien, et ce n'est
     * pas un oubli : depuis le correctif de `rolePourProcedure()`, une telle procédure retient
     * toujours le **rôle principal** du gabarit. Un rôle secondaire n'y sert donc jamais.
     *
     * La première version confondait « accepte tout » et « attend tout » : dès qu'une constitution
     * était cochée, plus rien n'était signalé — et c'est précisément ainsi qu'un gabarit RCCM
     * rattaché à deux constitutions a pu porter le rôle de modification sans effet et sans
     * avertissement.
     */
    const rolesAttendusParLesTypesChoisis = [...new Set(
        typesChoisis.flatMap(t => t.roles_attendus ?? [])
    )];

    /** Un rôle secondaire qui ne sert à rien : ni principal, ni attendu par un type coché. */
    const estRoleInerte = (role) =>
        !data.applicable_tous
        && role !== data.type_document
        && !rolesAttendusParLesTypesChoisis.includes(role);

    /**
     * Rôles à mettre en avant — `null` quand aucun type coché ne déclare d'attente : il n'y a alors
     * rien à trier, et scinder la liste laisserait croire à un classement qui n'existe pas.
     */
    const rolesPertinents = (data.applicable_tous || rolesAttendusParLesTypesChoisis.length === 0)
        ? null
        : rolesAttendusParLesTypesChoisis;

    /**
     * Types d'actes qui attendent ce rôle — sert à expliquer pourquoi un rôle est hors sujet, et à
     * proposer de rattacher le type qui lui donnerait un effet.
     */
    const typesAttendant = (role) =>
        (typesActes ?? []).filter(t => (t.roles_attendus ?? []).includes(role));

    const composerRattachements = () => data.type_acte_ids.flatMap(id => {
        const type = (typesActes ?? []).find(t => String(t.id) === String(id));
        const applicables = (type?.variantes ?? [])
            .filter(v => data.variantes.includes(v.valeur))
            .map(v => v.valeur);

        return applicables.length > 0
            ? applicables.map(variante => ({ type_acte_id: id, variante }))
            : [{ type_acte_id: id, variante: null }];
    });

    // Variantes proposées : celles des types d'actes effectivement cochés. Un gabarit de vente ne
    // doit pas se voir proposer « cession de parts sociales ».
    const variantesDisponibles = React.useMemo(() => {
        const vues = new Map();
        for (const id of data.type_acte_ids) {
            const type = (typesActes ?? []).find(t => String(t.id) === String(id));
            for (const v of type?.variantes ?? []) vues.set(v.valeur, v);
        }
        return [...vues.values()];
    }, [data.type_acte_ids, typesActes]);

    const submit = () => {
        // patch()/post() du formulaire (pas le routeur global) : lie correctement
        // errors/processing à ce useForm — sinon un échec (fichier invalide, validation…)
        // reste totalement silencieux, sans message ni indicateur de chargement.
        // Inertia gère lui-même la conversion PATCH+fichier en POST + _method côté client.
        // `transform` et non `setData` : ce dernier est asynchrone, la valeur composée partirait
        // périmée dans la requête déclenchée juste après.
        transform(d => ({ ...d, rattachements: composerRattachements() }));
        const opts = { forceFormData: true, onSuccess: () => { onClose(); reset(); }, onError: notifyValidationError };
        if (isEdit) {
            patch(`/modeles/${modele.id}`, opts);
        } else {
            post('/modeles', opts);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            {/* `flex flex-col overflow-hidden` : la modale porte deux listes à cocher (types d'actes
                et rôles) et dépassait l'écran — les boutons d'action n'étaient plus atteignables.
                Seul le corps défile désormais, le pied reste visible. */}
            {/* La borne de hauteur est posée **ici** et non dans `ui/dialog.jsx` : ce module partagé
                n'est pas toujours rechargé par le serveur de dev (les imports du projet écrivent
                `@/components` alors que le dossier est `Components`, et l'invalidation rate). Cette
                modale reste ainsi correcte quel que soit l'état du graphe de modules. */}
            <DialogContent className="flex max-h-[90dvh] max-w-lg flex-col overflow-hidden">
                <DialogHeader className="shrink-0">
                    <DialogTitle className="font-serif text-ink">
                        {isEdit ? 'Modifier le modèle' : "Nouveau modèle d'acte"}
                    </DialogTitle>
                </DialogHeader>

                {/* Corps défilant, pied fixe. Écrit ici plutôt qu'extrait en composant partagé :
                    ajouter un export à `ui/dialog.jsx` casse le rendu tant que le serveur de dev
                    n'a pas rafraîchi son graphe de modules — les imports du projet écrivent
                    `@/components` en minuscule alors que le dossier est `Components`. */}
                <div className="-mx-1 min-h-0 flex-1 space-y-4 overflow-y-auto px-1 py-2">
                    <div className="space-y-1.5">
                        <Label>Nom du modèle</Label>
                        <Input
                            placeholder="ex : Statuts SARL — Constitution"
                            value={data.nom}
                            onChange={e => setData('nom', e.target.value)}
                        />
                        {errors.nom && <p className="text-xs text-danger-text">{errors.nom}</p>}
                    </div>

                    {/* Le sélecteur « Type d'acte d'origine » a été supprimé avec la colonne
                        `type_acte_id` (2026-08-11) : il faisait saisir deux fois la même chose que
                        la liste ci-dessous, qui est la seule à décider de la génération.
                        « Type de document » reste — c'est le **rôle principal**, autre notion. */}
                    <div className="grid grid-cols-1 gap-3">
                        <div className="space-y-1.5">
                            <Label>Type de document</Label>
                            <Select value={data.type_document} onValueChange={v => setData('type_document', v)}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {(typesDocument ?? []).map(t => <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>)}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Un même gabarit sert souvent plusieurs types : les statuts, la DNSV et le
                        RCCM d'une SARLU valent aussi pour sa modification de statuts. Sans cela,
                        l'étude devait recharger les mêmes fichiers sous chaque type, puis maintenir
                        les copies en parallèle. */}
                    <div className="space-y-3 rounded-lg border border-slate-200 p-3">
                        <div>
                            <Label>Types d'actes concernés</Label>
                            <p className="mt-0.5 text-xs text-slate-400">
                                C'est cette liste qui décide de la génération. Un gabarit peut en servir plusieurs.
                            </p>
                        </div>

                        <label className="flex w-fit cursor-pointer items-center gap-2.5 text-sm text-slate-700">
                            <Checkbox checked={data.applicable_tous}
                                onCheckedChange={(checked) => setData('applicable_tous', checked === true)} />
                            <span>Applicable à tous les types d'actes</span>
                        </label>

                        {!data.applicable_tous && (
                            <div className="max-h-52 space-y-3 overflow-y-auto border-t border-slate-100 pt-2">
                                {Object.entries(groupesTypes).map(([cat, items]) => (
                                    <div key={cat}>
                                        <p className="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-slate-400">{cat}</p>
                                        <div className="space-y-1.5">
                                            {items.map(t => (
                                                <label key={t.id} className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                                                    <Checkbox checked={data.type_acte_ids.includes(String(t.id))}
                                                        onCheckedChange={() => basculerType(t.id)} />
                                                    <span>{t.label}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                                {errors.type_acte_ids && <p className="text-xs text-danger-text">{errors.type_acte_ids}</p>}
                            </div>
                        )}

                        {(data.applicable_tous || data.type_acte_ids.length > 1) && data.nom && (
                            <p className="flex items-start gap-1.5 rounded-md border border-amber-200 bg-warning-bg p-2.5 text-xs text-warning-text">
                                <AlertTriangle className="mt-0.5 h-3 w-3 shrink-0" />
                                <span>
                                    Ce gabarit sert plusieurs procédures : l'acte produit s'appellera
                                    « {data.nom} » dans <strong>tous</strong> les dossiers concernés.
                                    {nomNeutreSuggere(data.nom) && (
                                        <>
                                            {' '}Un intitulé neutre conviendrait mieux —{' '}
                                            <button
                                                type="button"
                                                onClick={() => setData('nom', nomNeutreSuggere(data.nom))}
                                                className="underline hover:no-underline"
                                            >
                                                « {nomNeutreSuggere(data.nom)} »
                                            </button>.
                                        </>
                                    )}
                                </span>
                            </p>
                        )}

                        {/* Variantes : tout ne se répète pas au sein d'un même type d'acte. Un
                            procès-verbal sert les sept résolutions d'une modification, un acte de
                            cession une seule. Vide = toutes. */}
                        {!data.applicable_tous && variantesDisponibles.length > 0 && (
                            <div className="space-y-1.5 border-t border-slate-100 pt-3">
                                <Label>Restreindre à certaines variantes</Label>
                                <p className="text-xs text-slate-400">
                                    Aucune cochée : le gabarit sert toutes les variantes du type d'acte.
                                </p>
                                <div className="grid grid-cols-1 gap-1.5 pt-1 sm:grid-cols-2">
                                    {variantesDisponibles.map(v => (
                                        <label key={v.valeur} className="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                                            <Checkbox
                                                checked={data.variantes.includes(v.valeur)}
                                                onCheckedChange={() => setData('variantes', data.variantes.includes(v.valeur)
                                                    ? data.variantes.filter(x => x !== v.valeur)
                                                    : [...data.variantes, v.valeur])}
                                            />
                                            <span>{v.label}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Rôles : le vocabulaire diffère entre procédures pour un même document.
                            Cocher `acte_principal` et `statuts_maj` fait servir le même gabarit de
                            statuts à la création et à la modification. */}
                        <div className="space-y-1.5 border-t border-slate-100 pt-3">
                            <Label>Rôles remplis par ce gabarit</Label>
                            <p className="text-xs text-slate-400">
                                Le rôle principal ci-dessus est coché d'office. En ajouter permet à un même
                                fichier de servir plusieurs procédures.
                            </p>
                            {/* Scindés selon ce que les types cochés attendent réellement : cocher
                                un rôle qu'aucun d'eux n'attend reste sans effet, et rien ne le
                                disait — c'est ainsi qu'un gabarit RCCM portant le rôle de
                                modification n'était produit sur aucun dossier de modification. */}
                            <div className="max-h-48 space-y-3 overflow-y-auto pt-1">
                                <ListeRoles
                                    titre={rolesPertinents ? 'Attendus par vos types d’actes' : null}
                                    options={(typesDocument ?? []).filter(td => !rolesPertinents || rolesPertinents.includes(td.value))}
                                    data={data}
                                    setData={setData}
                                    estRoleInerte={estRoleInerte}
                                    typesAttendant={typesAttendant}
                                    onRattacher={(id) => setData('type_acte_ids', [...data.type_acte_ids, String(id)])}
                                />

                                {rolesPertinents && (
                                    <ListeRoles
                                        titre="Autres rôles"
                                        options={(typesDocument ?? []).filter(td => !rolesPertinents.includes(td.value))}
                                        data={data}
                                        setData={setData}
                                        horsSujet
                                        estRoleInerte={estRoleInerte}
                                        typesAttendant={typesAttendant}
                                        onRattacher={(id) => setData('type_acte_ids', [...data.type_acte_ids, String(id)])}
                                    />
                                )}
                            </div>
                            {errors.roles && <p className="text-xs text-danger-text">{errors.roles}</p>}
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

                <DialogFooter className="shrink-0 border-t border-slate-100 pt-4">
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

function ModalModeleCourrier({ open, onClose, typesActes, categories, typesDocument, modele = null }) {
    const isEdit = !!modele;
    const { data, setData, post, patch, processing, errors, reset, clearErrors } = useForm({
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
        const opts = { forceFormData: true, onSuccess: () => { onClose(); reset(); }, onError: notifyValidationError };
        if (isEdit) {
            patch(`/modeles-courriers/${modele.id}`, opts);
        } else {
            post('/modeles-courriers', opts);
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
            {/* La borne de hauteur et le défilement sont désormais portés par `DialogContent`
                lui-même — plus besoin de les répéter modale par modale. */}
            <DialogContent className="max-w-lg">
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
                                    {(typesDocument ?? []).map(t => <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>)}
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
                                        const tdInfo = PRESENTATION_TYPE_DOC[m.type_document] ?? PRESENTATION_NEUTRE;
                                        const Icon = tdInfo?.icon ?? FileText;
                                        return (
                                            <tr key={m.id} className={cn(!m.est_actif && 'opacity-50')}>
                                                <td>
                                                    <Icon className={cn('h-3.5 w-3.5', tdInfo ? tdInfo.color.split(' ')[1] : 'text-slate-400')} />
                                                </td>
                                                <td className="font-medium text-ink max-w-[220px] truncate" title={m.nom}>
                                                    <span className="flex items-center gap-1.5">
                                                        {m.nom}
                                                    </span>
                                                </td>
                                                <td className="text-slate-500 text-sm">{m.typeActeLabel}</td>
                                                <td><TypeDocBadge type={m.type_document} label={m.typeDocLabel} /></td>
                                                <td>
                                                    <span className="font-ref text-seal text-xs font-semibold">v{m.version}</span>
                                                </td>
                                                <td>
                                                    <div className="flex items-center gap-1.5">
                                                        <CopyPathButton path={m.chemin_fichier} />
                                                        {!m.fichier_existe && m.est_actif && (
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <span className="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded-full bg-danger-bg text-danger-text border border-red-200 shrink-0">
                                                                        <AlertTriangle className="h-2.5 w-2.5" />
                                                                        Fichier introuvable
                                                                    </span>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    Ce modèle est actif mais son fichier .docx est introuvable sur le
                                                                    disque — les dossiers utilisant ce type d'acte généreront un texte
                                                                    de secours au lieu du document réel.
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        )}
                                                    </div>
                                                </td>
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
    const tdInfo = PRESENTATION_TYPE_DOC[modele.type_document] ?? PRESENTATION_NEUTRE;
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
                            const tdInfo = PRESENTATION_TYPE_DOC[m.type_document] ?? PRESENTATION_NEUTRE;
                            const Icon = tdInfo?.icon ?? FileText;
                            return (
                                <tr key={m.id} className={cn(!m.est_actif && 'opacity-50')}>
                                    <td>
                                        <Icon className={cn('h-3.5 w-3.5', tdInfo ? tdInfo.color.split(' ')[1] : 'text-slate-400')} />
                                    </td>
                                    <td className="font-medium text-ink max-w-[220px] truncate" title={m.nom}>{m.nom}</td>
                                    <td><TypeDocBadge type={m.type_document} label={m.typeDocLabel} /></td>
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
        typesDocument = [], filters = {}, stats = {}, auth,
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
                    <div className="grid grid-cols-3 gap-3">
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
                                {(typesDocument ?? []).map(t => <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>)}
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
                    categories={categories}
                    typesDocument={typesDocument}
                    modele={editModele}
                />

                <ModalModeleCourrier
                    open={courrierModalOpen}
                    onClose={closeCourrierModal}
                    typesActes={typesActes}
                    categories={categories}
                    typesDocument={typesDocument}
                    modele={editCourrier}
                />
            </AppLayout>
    );
}
