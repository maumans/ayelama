import React, { useState, useRef, useEffect, useMemo } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Check, Clock, AlertTriangle, FileText, Download, Eye, Building, Send, ClipboardCheck, Phone, MapPin, ArrowRight, CheckCircle2, Plus, Trash2, Upload, PenSquare, X, MailCheck, CheckCheck, Square, Pencil, RefreshCw, Zap, XCircle, Shield, Mail, Lock, Banknote, Wallet, Receipt, History, Users, Info, FileSignature, CalendarDays, Building2, CopyCheck, AlertCircle
} from 'lucide-react';
import { STATUT_META as FORMALITE_STATUT_META, organismeBadgeClass, organismeShortLabel } from '@/data/formaliteStatuts';
import { STATUT_META as REVISION_STATUT_META } from '@/data/revisionStatuts';
import { ETAPE_META, ETAPE_ORDER } from '@/data/etapeMeta';
import { ModalDepotFormalite } from '@/Components/Formalites/ModalDepotFormalite';
import { ModalRetourFormalite } from '@/Components/Formalites/ModalRetourFormalite';
import { PieceGedRow } from '@/Components/Formalites/PieceGedRow';
import { PieceStagedRow } from '@/Components/ui/PieceStagedRow';
import { ModalEnregistrerPaiement } from '@/Components/Facturation/ModalEnregistrerPaiement';
import { ModalLigneFacture } from '@/Components/Facturation/ModalLigneFacture';
import { QUESTIONNAIRES, TYPE_ACTE_CODE_MAP, getVisibleFields, purgerChampsInvisibles, roleGeo, patchGeo } from '@/data/questionnaires';
import { RepeatableGroup } from '@/Components/ui/RepeatableGroup';
import { DateField } from '@/components/ui/date-field';
import { NumberField } from '@/components/ui/number-field';
import { PhoneField } from '@/components/ui/phone-field';
import { ClientPicker } from '@/Components/ui/client-picker';
import { ModalNouveauClient } from '@/Components/ModalNouveauClient';
import { ClientRoleSection } from '@/Components/ui/client-role-section';
import { ChoixMultiple } from '@/Components/ui/choix-multiple';
import { LieuSelect } from '@/Components/ui/lieu-select';
import { ChampVerrouille } from '@/Components/ui/champ-verrouille';
import { tableExclusionsModification } from '@/lib/exclusionsChoix';
import { PiecesConstitutivesCard } from '@/Components/Societes/PiecesConstitutivesCard';
import { AccordClientCard, ANCRE_ACCORD_CLIENT } from '@/Components/Dossiers/AccordClientCard';
import { ClotureTab } from '@/Components/Dossiers/ClotureTab';

/** Ancre DOM de la carte « Parties & pièces » (onglet Informations). */
const ANCRE_PIECES_PARTIES = 'pieces-parties';
import { mapClientToPrefixedFields, buildPartieFields, estChampIdentite } from '@/lib/clientFields';
import { groupFieldsBySection, buildPartiesPayload, getManagedClientRoles } from '@/lib/partiesPayload';
import { isoDateToFR, frDateToISO } from '@/lib/dates';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import { notifyValidationError } from '@/lib/toast';
import { ApercuSousLigne, useApercuEnLigne } from '@/Components/documents/ApercuSousLigne';

const docStatutConfig = {
    a_editer: { label: 'À éditer', color: 'text-slate-500 bg-slate-50 border-slate-200' },
    edite:    { label: 'Édité',    color: 'text-blue-600 bg-blue-50 border-blue-200' },
};

// Le vocabulaire des types de document n'est plus recopié ici : le serveur sert `typeDocLabel`
// avec chaque document (voir HasTypeDocumentLabel, seule référence). Les copies locales avaient
// divergé — les quatre types de la modification statutaire y manquaient, et leur slug brut
// s'affichait à l'écran.

// ── Modal : modifier les infos générales du dossier ─────────────────────────

function ModalEditDossier({ open, onClose, dossier, reviseurs, formalistes, notaires, canReassigner }) {
    const [form, setForm] = useState({
        objet:        dossier.objet ?? '',
        valeur:       dossier.valeur ?? '',
        echeance:     dossier.echeance ?? '',
        urgent:       dossier.urgent ?? false,
        notes:        dossier.notes ?? '',
        notaire_id:   dossier.notaire?.id   ?? '',
        reviseur_id:  dossier.reviseur?.id  ?? '',
        formaliste_id: dossier.formaliste?.id ?? '',
    });

    useEffect(() => {
        if (open) setForm({
            objet:        dossier.objet ?? '',
            valeur:       dossier.valeur ?? '',
            echeance:     dossier.echeance ?? '',
            urgent:       dossier.urgent ?? false,
            notes:        dossier.notes ?? '',
            notaire_id:   dossier.notaire?.id   ?? '',
            reviseur_id:  dossier.reviseur?.id  ?? '',
            formaliste_id: dossier.formaliste?.id ?? '',
        });
    }, [open]);

    const f = (k) => (e) => setForm(p => ({ ...p, [k]: e.target.value }));

    const submit = (e) => {
        e.preventDefault();
        router.patch(`/dossiers/${dossier.reference}`, {
            objet:         form.objet,
            valeur:        form.valeur        || null,
            echeance:      form.echeance      || null,
            urgent:        form.urgent,
            notes:         form.notes         || null,
            notaire_id:    form.notaire_id    || null,
            reviseur_id:   form.reviseur_id   || null,
            formaliste_id: form.formaliste_id || null,
        }, { onSuccess: () => onClose(), onError: notifyValidationError });
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader><DialogTitle>Modifier le dossier</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="space-y-4 pt-1">
                    <div className="space-y-1.5">
                        <Label>Objet du dossier <span className="text-danger">*</span></Label>
                        <textarea
                            value={form.objet}
                            onChange={f('objet')}
                            required
                            rows={2}
                            className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seal resize-none"
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Valeur (GNF)</Label>
                            <NumberField value={form.valeur} onValueChange={val => setForm(p => ({ ...p, valeur: val }))} placeholder="0" />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Échéance</Label>
                            <DateField
                                value={isoDateToFR(form.echeance)}
                                onValueChange={val => setForm(p => ({ ...p, echeance: frDateToISO(val) }))}
                            />
                        </div>
                    </div>
                    <label className="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer w-fit">
                        <Checkbox
                            checked={form.urgent}
                            onCheckedChange={(checked) => setForm(p => ({ ...p, urgent: checked === true }))}
                        />
                        Dossier urgent
                    </label>
                    {canReassigner && (
                        <>
                            <div className="space-y-1.5">
                                <Label>Notaire</Label>
                                <select value={form.notaire_id} onChange={f('notaire_id')}
                                    className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal">
                                    <option value="">Aucun</option>
                                    {(notaires ?? []).map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                                </select>
                            </div>
                            <div className="space-y-1.5">
                                <Label>Certificateur</Label>
                                <select value={form.reviseur_id} onChange={f('reviseur_id')}
                                    className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal">
                                    <option value="">Aucun</option>
                                    {(reviseurs ?? []).map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                                </select>
                            </div>
                            <div className="space-y-1.5">
                                <Label>Formaliste</Label>
                                <select value={form.formaliste_id} onChange={f('formaliste_id')}
                                    className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal">
                                    <option value="">Aucun</option>
                                    {(formalistes ?? []).map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                                </select>
                            </div>
                        </>
                    )}
                    <div className="space-y-1.5">
                        <Label>Notes</Label>
                        <textarea
                            value={form.notes}
                            onChange={f('notes')}
                            rows={3}
                            placeholder="Contexte, remarques ou instructions particulières…"
                            className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seal resize-none"
                        />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit">Enregistrer</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// ── Modal : modifier le questionnaire ───────────────────────────────────────

function initialClientLinks(fields, parties) {
    const links = {};
    for (const group of groupFieldsBySection(fields)) {
        if (!group.clientRole) continue;
        const partie = (parties ?? []).find(p => p.role === group.clientRole && p.client);
        if (partie) links[group.clientRole] = partie.client;
    }
    return links;
}

// Attache un `partie_id` à chaque item d'un bloc répétable en le faisant correspondre par
// position aux `Partie` existantes du même rôle — rend explicite côté client la même
// correspondance que le backend applique déjà (DossierController::updateQuestionnaire()),
// pour permettre l'affichage de la checklist de pièces par personne et une resynchronisation
// fiable à l'enregistrement (voir buildPartiesPayload()).
function attachPartieIds(fields, values, parties) {
    const next = { ...values };
    for (const field of fields) {
        if (field.type !== 'repeatable' || !field.clientRole) continue;
        const items = next[field.id] ?? [];
        const partiesDuRole = (parties ?? []).filter(p => p.role === field.clientRole);
        // `client` (l'objet, pas seulement l'id) est réattaché ici : sans lui, la
        // ligne rouvrirait en saisie libre et réafficherait les champs d'identité
        // que la refonte a justement retirés (voir ClientRoleSection).
        next[field.id] = items.map((item, i) => ({
            ...item,
            partie_id: partiesDuRole[i]?.id ?? undefined,
            client: partiesDuRole[i]?.client ?? item.client ?? undefined,
            client_id: partiesDuRole[i]?.client?.id ?? item.client_id ?? undefined,
        }));
    }
    return next;
}

function ModalEditQuestionnaire({ open, onClose, dossier }) {
    const questKey  = TYPE_ACTE_CODE_MAP[dossier.typeActe?.code];
    const fields    = QUESTIONNAIRES[questKey] ?? [];
    const [formValues, setFormValues] = useState(dossier.questionnaire ?? {});
    const [stagedPieces, setStagedPieces] = useState({});
    const [previewKey, setPreviewKey] = useState(null);
    const [clientLinks, setClientLinks] = useState(() => initialClientLinks(fields, dossier.parties));
    const [creatingClientForGroup, setCreatingClientForGroup] = useState(null);
    const [editingClient, setEditingClient] = useState(null);
    const [saisieLibreRoles, setSaisieLibreRoles] = useState({});
    const partiesById = useMemo(
        () => Object.fromEntries((dossier.parties ?? []).map(p => [p.id, p])),
        [dossier.parties]
    );
    // Modifications statutaires mutuellement exclusives — mêmes exclusions qu'à la création : un
    // garde-fou posé d'un seul côté laisserait la combinaison interdite ressaisissable ici.
    const exclusionsModification = tableExclusionsModification(usePage().props.typesModification);

    const handleStagedPieceChange = (groupName, key, file) => {
        setStagedPieces(prev => ({
            ...prev,
            [groupName]: {
                ...(prev[groupName] || {}),
                [key]: file,
            }
        }));
    };

    useEffect(() => {
        if (open) {
            setFormValues(attachPartieIds(fields, dossier.questionnaire ?? {}, dossier.parties));
            setClientLinks(initialClientLinks(fields, dossier.parties));
            setSaisieLibreRoles({});
            setStagedPieces({});
            setPreviewKey(null);
        }
    }, [open]);

    const applyClientToSection = (group, client) => {
        const prefix = group.fields[0].id.split('.')[0];
        const fieldIds = group.fields.map(f => f.id);
        const mapped = mapClientToPrefixedFields(client, prefix, fieldIds);
        setFormValues(prev => ({ ...prev, ...mapped }));
        setClientLinks(prev => ({ ...prev, [group.clientRole]: client }));
    };

    const unlinkClientFromSection = (role) => {
        setClientLinks(prev => {
            const next = { ...prev };
            delete next[role];
            return next;
        });
    };

    const toggleSaisieLibre = (role, actif) => {
        setSaisieLibreRoles(prev => ({ ...prev, [role]: actif }));
        if (actif) unlinkClientFromSection(role);
    };

    // Dès qu'une fiche est rattachée, ses champs d'identité sortent du formulaire :
    // même règle que dans l'assistant de création (voir Create.jsx).
    const champsAffichables = (group) => {
        if (!group.clientRole || !clientLinks[group.clientRole]) return group.fields;
        return group.fields.filter(f =>
            f.type === 'repeatable'
            || f.type === 'checkbox'
            || f.type === 'checkbox_required'
            || f.type === 'checkbox_group'
            || !estChampIdentite(f.id)
        );
    };

    const champsIdentiteManquants = (group) => {
        if (!group.clientRole || !clientLinks[group.clientRole]) return [];
        return group.fields
            .filter(f => f.required && estChampIdentite(f.id) && !formValues[f.id])
            .map(f => f.label);
    };

    const submit = (e) => {
        e.preventDefault();

        // Purgé des champs des blocs décochés, exactement comme à la création : décocher une
        // modification sans effacer ses valeurs les laisserait projetées dans les actes régénérés
        // par cette action (voir purgerChampsInvisibles). Sert aussi aux `parties`, pour qu'une
        // cession retirée ne conserve pas ses cédants.
        const donneesSoumises = purgerChampsInvisibles(fields, formValues);

        router.patch(`/dossiers/${dossier.reference}/questionnaire`, {
            donnees: donneesSoumises,
            parties: buildPartiesPayload(fields, donneesSoumises, clientLinks, stagedPieces),
            managedRoles: getManagedClientRoles(fields),
        }, { forceFormData: true, onSuccess: () => onClose(), onError: notifyValidationError });
    };

    // Si aucun schéma connu, afficher les champs existants en mode générique
    const genericFields = fields.length === 0
        ? Object.keys(dossier.questionnaire ?? {}).map(k => ({ id: k, label: k, type: 'text' }))
        : [];

    const allFields = fields.length > 0 ? fields : genericFields;
    const visibleFields = getVisibleFields(allFields, formValues);

    return (
        <>
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader><DialogTitle>Modifier le questionnaire</DialogTitle></DialogHeader>
                <form onSubmit={submit}>
                    <div className="max-h-[62vh] overflow-y-auto space-y-4 py-2 pr-1">
                        {groupFieldsBySection(visibleFields).map((group, gi) => (
                            <React.Fragment key={gi}>
                                {group.name && (
                                    <div className={cn('pb-1', gi > 0 && 'pt-4 border-t border-slate-100')}>
                                        <h4 className="text-xs font-semibold text-slate-500 uppercase tracking-wider">{group.name}</h4>
                                    </div>
                                )}
                                {group.clientRole && (
                                    <div className="mb-1">
                                        {/* Même composant que l'assistant de création : la section
                                            désigne une fiche client au lieu de resaisir son identité,
                                            pour que création et édition ne divergent pas. */}
                                        <ClientRoleSection
                                            roleLabel={group.name}
                                            linked={clientLinks[group.clientRole] ?? null}
                                            onSelect={(client) => applyClientToSection(group, client)}
                                            onUnlink={() => unlinkClientFromSection(group.clientRole)}
                                            onCreateNew={() => setCreatingClientForGroup(group)}
                                            onEditClient={(client) => setEditingClient(client)}
                                            champsManquants={champsIdentiteManquants(group)}
                                            saisieLibre={!!saisieLibreRoles[group.clientRole]}
                                            onToggleSaisieLibre={(v) => toggleSaisieLibre(group.clientRole, v)}
                                        />
                                    </div>
                                )}
                                {champsAffichables(group).map(field => (
                                    <div key={field.id} className="space-y-1.5">
                                        {field.type !== 'repeatable' && field.type !== 'checkbox' && field.type !== 'checkbox_required' && (
                                            <Label htmlFor={`qedit-${field.id}`}>
                                                {field.label}
                                                {field.required && <span className="text-danger ml-1">*</span>}
                                            </Label>
                                        )}
                                        {roleGeo(field.id) ? (
                                            /* Même cascade que l'assistant : un type de champ géré
                                               d'un seul côté rendrait la valeur non modifiable
                                               après la création du dossier. */
                                            <LieuSelect
                                                id={`qedit-${field.id}`}
                                                niveau={roleGeo(field.id).niveau}
                                                parentNom={roleGeo(field.id).parentField
                                                    ? (formValues[roleGeo(field.id).parentField] || null)
                                                    : null}
                                                value={formValues[field.id] || ''}
                                                onChange={val => setFormValues(p => ({ ...p, ...patchGeo(field.id, val) }))}
                                                peutAjouter
                                            />
                                        ) : field.type === 'checkbox_group' ? (
                                            <ChoixMultiple
                                                field={field}
                                                valeurs={formValues[field.id] ?? []}
                                                onChange={val => setFormValues(p => ({ ...p, [field.id]: val }))}
                                                idPrefix="qedit-"
                                                exclusions={exclusionsModification}
                                            />
                                        ) : field.type === 'repeatable' ? (
                                            <>
                                                <p className="text-sm font-medium text-slate-700 mb-1">
                                                    {field.label}
                                                    {field.required && <span className="text-danger ml-1">*</span>}
                                                </p>
                                                <RepeatableGroup
                                                    fieldDef={field}
                                                    value={formValues[field.id] ?? []}
                                                    onChange={val => setFormValues(p => ({ ...p, [field.id]: val }))}
                                                    partiesById={partiesById}
                                                    piecesRequises={usePage().props.piecesRequises}
                                                    stagedPieces={stagedPieces[field.id] || {}}
                                                    onStagedPieceChange={(key, file) => handleStagedPieceChange(field.id, key, file)}
                                                />
                                            </>
                                        ) : field.type === 'textarea' ? (
                                            <textarea
                                                id={`qedit-${field.id}`}
                                                rows={3}
                                                placeholder={field.placeholder}
                                                value={formValues[field.id] || ''}
                                                onChange={e => setFormValues(p => ({ ...p, [field.id]: e.target.value }))}
                                                className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seal resize-none"
                                            />
                                        ) : field.type === 'select' ? (
                                            <select
                                                id={`qedit-${field.id}`}
                                                value={formValues[field.id] || ''}
                                                onChange={e => setFormValues(p => ({ ...p, [field.id]: e.target.value }))}
                                                className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal"
                                            >
                                                <option value="">— Choisir —</option>
                                                {(field.options ?? []).map(opt => (
                                                    <option key={opt} value={opt}>{opt}</option>
                                                ))}
                                            </select>
                                        ) : (field.type === 'checkbox' || field.type === 'checkbox_required') ? (
                                            <div className="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    id={`qedit-${field.id}`}
                                                    checked={!!formValues[field.id]}
                                                    onChange={e => setFormValues(p => ({ ...p, [field.id]: e.target.checked }))}
                                                    className="h-4 w-4 rounded border-slate-300 text-seal focus:ring-seal"
                                                />
                                                <label htmlFor={`qedit-${field.id}`} className="text-sm text-slate-700 cursor-pointer">
                                                    {field.label}
                                                    {field.required && <span className="text-danger ml-1">*</span>}
                                                </label>
                                            </div>
                                        ) : field.type === 'date' ? (
                                            <DateField
                                                id={`qedit-${field.id}`}
                                                value={formValues[field.id] || ''}
                                                onValueChange={val => setFormValues(p => ({ ...p, [field.id]: val }))}
                                            />
                                        ) : field.type === 'number' ? (
                                            <NumberField
                                                id={`qedit-${field.id}`}
                                                decimals={field.decimals ?? 0}
                                                placeholder={field.placeholder}
                                                value={formValues[field.id] || ''}
                                                onValueChange={val => setFormValues(p => ({ ...p, [field.id]: val }))}
                                                className={cn(field.mono && 'font-ref')}
                                            />
                                        ) : field.type === 'year' ? (
                                            <Input
                                                id={`qedit-${field.id}`}
                                                type="text"
                                                inputMode="numeric"
                                                maxLength={4}
                                                placeholder={field.placeholder}
                                                value={formValues[field.id] || ''}
                                                onChange={e => {
                                                    const v = e.target.value.replace(/\D/g, '').slice(0, 4);
                                                    setFormValues(p => ({ ...p, [field.id]: v }));
                                                }}
                                                className="font-ref"
                                            />
                                        ) : field.type === 'tel' ? (
                                            <PhoneField
                                                id={`qedit-${field.id}`}
                                                placeholder={field.placeholder}
                                                value={formValues[field.id] || ''}
                                                onValueChange={val => setFormValues(p => ({ ...p, [field.id]: val }))}
                                            />
                                        ) : field.readonly ? (
                                            /* `readonly` était honoré par l'assistant mais **pas
                                               ici** : un champ verrouillé à la création redevenait
                                               librement modifiable à la première correction. */
                                            <ChampVerrouille
                                                id={`qedit-${field.id}`}
                                                value={formValues[field.id] || ''}
                                                onChange={val => setFormValues(p => ({ ...p, [field.id]: val }))}
                                                placeholder={field.placeholder}
                                                className={cn(field.mono && 'font-ref')}
                                            />
                                        ) : (
                                            <Input
                                                id={`qedit-${field.id}`}
                                                type={field.type === 'email' ? 'email' : 'text'}
                                                placeholder={field.placeholder}
                                                value={formValues[field.id] || ''}
                                                onChange={e => setFormValues(p => ({ ...p, [field.id]: e.target.value }))}
                                                className={cn(field.mono && 'font-ref')}
                                            />
                                        )}
                                    </div>
                                ))}
                                {/* Pièces justificatives pour les rôles simples (non-répétables) */}
                                {(() => {
                                    if (!group.clientRole) return null;
                                    if (group.fields.some(f => f.type === 'repeatable')) return null;

                                    // Si la partie existe déjà, RepeatableGroup et la fiche client s'en occupent,
                                    // mais pour les non-répétables, s'il existe déjà une Partie, on ne propose pas d'upload temporaire.
                                    // On vérifie si un client (donc une partie) est déjà lié.
                                    const hasExistingPartie = !!clientLinks[group.clientRole];
                                    if (hasExistingPartie) return null;

                                    let categorieRole = '';
                                    if (group.clientRole === 'associe_unique') {
                                        categorieRole = 'associe_physique';
                                    } else if (['bailleur', 'locataire', 'vendeur', 'acheteur', 'liquidateur', 'creancier', 'debiteur'].includes(group.clientRole)) {
                                        categorieRole = group.clientRole;
                                    }
                                    
                                    const piecesRequisesSection = usePage().props.piecesRequises?.[categorieRole] ?? {};
                                    const piecesKeys = Object.keys(piecesRequisesSection);
                                    
                                    if (piecesKeys.length === 0) return null;

                                    return (
                                        <div className="mt-4 pt-4 border-t border-slate-100 divide-y divide-slate-50/80">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2 px-1">
                                                Pièces justificatives requises (création)
                                            </p>
                                            {piecesKeys.map(cat => {
                                                const key = cat;
                                                const previewId = `${group.clientRole}:${key}`;
                                                return (
                                                    <PieceStagedRow
                                                        key={key}
                                                        piece={{ label: piecesRequisesSection[key] }}
                                                        file={stagedPieces[group.clientRole]?.[key]}
                                                        onFileSelected={(f) => handleStagedPieceChange(group.clientRole, key, f)}
                                                        isPreviewOpen={previewKey === previewId}
                                                        onTogglePreview={() => setPreviewKey(k => k === previewId ? null : previewId)}
                                                    />
                                                );
                                            })}
                                        </div>
                                    );
                                })()}
                            </React.Fragment>
                        ))}
                        {allFields.length === 0 && (
                            <p className="text-sm text-slate-400 italic py-4 text-center">Aucun champ de questionnaire trouvé.</p>
                        )}
                    </div>
                    <DialogFooter className="pt-4 mt-2 border-t border-slate-100">
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit">Enregistrer le questionnaire</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
        <ModalNouveauClient
            open={creatingClientForGroup !== null}
            onClose={() => setCreatingClientForGroup(null)}
            onCreated={(client) => {
                applyClientToSection(creatingClientForGroup, client);
                setCreatingClientForGroup(null);
            }}
        />
        {/* Correction en place de la fiche rattachée — la nouvelle valeur se propage
            à tous les rôles qui la désignent, ici et dans les autres dossiers
            (voir ClientProjectionService côté serveur). */}
        <ModalNouveauClient
            open={editingClient !== null}
            client={editingClient}
            onClose={() => setEditingClient(null)}
            onCreated={(client) => {
                setClientLinks(prev => Object.fromEntries(
                    Object.entries(prev).map(([role, c]) => [role, c?.id === client.id ? client : c])
                ));
                setEditingClient(null);
            }}
        />
        </>
    );
}

// ── Composant : onglet Informations ─────────────────────────────────────────

function PartiePhotoAvatar({ partie, canEdit }) {
    const inputRef = useRef(null);
    const [uploading, setUploading] = useState(false);

    const handleFile = (file) => {
        if (!file) return;
        setUploading(true);
        router.post(`/parties/${partie.id}/photo`, { fichier: file }, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onError: notifyValidationError,
            onFinish: () => setUploading(false),
        });
    };

    return (
        <div className="relative shrink-0">
            <Avatar className="h-10 w-10">
                {partie.photo?.has_file ? (
                    <img
                        src={partie.photo.url_preview}
                        alt={partie.nom}
                        className="h-full w-full object-cover rounded-full"
                    />
                ) : (
                    <AvatarFallback className="bg-ink text-white text-sm">
                        {partie.initiales ?? partie.nom?.split(' ').map(n => n[0]).join('').slice(0, 2)}
                    </AvatarFallback>
                )}
            </Avatar>
            {canEdit && (
                <>
                    <input
                        ref={inputRef}
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={(e) => handleFile(e.target.files?.[0])}
                    />
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        disabled={uploading}
                        title="Changer la photo"
                        className="absolute -bottom-1 -right-1 h-4.5 w-4.5 rounded-full bg-seal text-white flex items-center justify-center border border-white"
                    >
                        <Upload className="h-2.5 w-2.5" />
                    </button>
                </>
            )}
        </div>
    );
}

function PartiePiecesList({ partie, canEdit, onAjouter }) {
    if (!partie.pieces?.length && !canEdit) return null;

    return (
        <div className="mt-3 pt-3 border-t border-slate-100 flex flex-wrap items-center gap-1.5">
            {partie.pieces?.map((piece) => (
                <span
                    key={piece.id}
                    className="inline-flex items-center gap-1.5 text-[11px] pl-2 pr-1 py-1 rounded-full border border-slate-200 bg-slate-50 text-slate-600"
                >
                    <FileText className="h-3 w-3 shrink-0" />
                    {piece.nom}
                    {piece.has_file && (
                        <a href={piece.url_download} download className="text-seal hover:text-seal-hover" title="Télécharger">
                            <Download className="h-3 w-3" />
                        </a>
                    )}
                    {canEdit && (
                        <button
                            type="button"
                            onClick={() => router.delete(`/documents/${piece.id}`, { preserveScroll: true })}
                            className="text-slate-300 hover:text-danger"
                            title="Supprimer"
                        >
                            <X className="h-3 w-3" />
                        </button>
                    )}
                </span>
            ))}
            {canEdit && (
                <button
                    type="button"
                    onClick={onAjouter}
                    className="inline-flex items-center gap-1 text-[11px] px-2 py-1 rounded-full border border-dashed border-slate-300 text-slate-400 hover:text-seal hover:border-seal transition-colors"
                >
                    <Plus className="h-3 w-3" /> Pièce
                </button>
            )}
        </div>
    );
}

function ModalAjouterPiecePartie({ partie, onClose }) {
    const [nom, setNom] = useState('');
    const [fichier, setFichier] = useState(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (partie) { setNom(''); setFichier(null); }
    }, [partie?.id]);

    const submit = () => {
        if (!nom.trim() || !fichier) return;
        setSaving(true);
        router.post(`/parties/${partie.id}/pieces`, { nom: nom.trim(), fichier }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
            onError: notifyValidationError,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <Dialog open={!!partie} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader><DialogTitle>Ajouter une pièce{partie ? ` — ${partie.nom}` : ''}</DialogTitle></DialogHeader>
                <div className="space-y-3 py-2">
                    <div className="space-y-1.5">
                        <Label>Nom de la pièce</Label>
                        <Input placeholder="ex : CNI, passeport…" value={nom} onChange={e => setNom(e.target.value)} />
                    </div>
                    <div className="space-y-1.5">
                        <Label>Fichier</Label>
                        <Input type="file" onChange={e => setFichier(e.target.files?.[0] ?? null)} />
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>Annuler</Button>
                    <Button onClick={submit} disabled={saving || !nom.trim() || !fichier}>
                        {saving ? 'Ajout…' : 'Ajouter'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/**
 * Société du registre sur laquelle porte le dossier, et — pour une modification — ce que
 * celle-ci implique : impact statutaire, actes produits, formalités à venir.
 *
 * Ces informations étaient calculées côté serveur depuis le 2026-08-05
 * (`TypeModificationStatutaire`, `ReglesSocieteService::modificationStatutaire()`) sans être
 * affichées nulle part : en consultant un dossier, on ne pouvait pas savoir pourquoi tel acte
 * y figure et tel autre non, ni quelles démarches allaient suivre.
 */
function SocieteEtModificationCard({ societe, modificationStatutaire }) {
    if (!societe && !modificationStatutaire) return null;

    const montant = (v) => v ? `${Math.round(Number(v)).toLocaleString('fr-FR')} GNF` : '—';

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2">
                    <Building2 className="h-4 w-4 text-blue-600" />
                    {modificationStatutaire ? 'Société modifiée' : 'Société concernée'}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {societe && (
                    <dl className="grid grid-cols-1 gap-x-8 gap-y-3 sm:grid-cols-3">
                        <div className="space-y-0.5">
                            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">Dénomination</dt>
                            <dd className="text-sm text-slate-800">{societe.nom_complet || societe.denomination}</dd>
                        </div>
                        <div className="space-y-0.5">
                            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">Forme</dt>
                            <dd className="text-sm text-slate-800">{societe.forme_label || societe.forme || '—'}</dd>
                        </div>
                        <div className="space-y-0.5">
                            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">RCCM</dt>
                            <dd className="font-ref text-sm text-slate-800">{societe.rccm_numero || '—'}</dd>
                        </div>
                        <div className="space-y-0.5">
                            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">Capital au registre</dt>
                            <dd className="font-ref text-sm text-slate-800">{montant(societe.capital_chiffres)}</dd>
                        </div>
                        <div className="space-y-0.5 sm:col-span-2">
                            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">Siège au registre</dt>
                            <dd className="text-sm text-slate-800">
                                {[societe.siege_quartier, societe.siege_commune, societe.siege_ville].filter(Boolean).join(', ') || '—'}
                            </dd>
                        </div>
                    </dl>
                )}

                {societe && modificationStatutaire && (
                    <p className="rounded-md border border-slate-200 bg-slate-50/70 p-2.5 text-xs text-slate-500">
                        Ces valeurs sont celles du registre. Elles seront mises à jour automatiquement
                        quand la modification deviendra effective, à l'entrée du dossier en Expédition —
                        c'est-à-dire une fois les formalités RCCM revenues.
                    </p>
                )}

                {modificationStatutaire && (
                    <div className="space-y-3 rounded-lg border border-seal/30 bg-seal-light/50 p-3">
                        <div>
                            <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                Modifications décidées
                            </p>
                            <div className="mt-1 flex flex-wrap gap-1.5">
                                {modificationStatutaire.types.map(t => (
                                    <span key={t.valeur} className="rounded-full border border-seal/30 bg-white px-2.5 py-0.5 text-xs text-slate-700">
                                        {t.label}
                                    </span>
                                ))}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Impact</p>
                                <ul className="mt-0.5 space-y-0.5 text-xs text-slate-600">
                                    <li>
                                        {modificationStatutaire.impacteStatuts
                                            ? 'Statuts à mettre à jour'
                                            : 'Statuts inchangés'}
                                    </li>
                                    <li>
                                        {modificationStatutaire.impacteRccm
                                            ? 'Enregistrement au RCCM requis'
                                            : 'Pas de passage au RCCM'}
                                    </li>
                                    <li>
                                        {modificationStatutaire.exigeDnsv
                                            ? 'DNSV à établir (capital augmenté)'
                                            : 'Aucune DNSV'}
                                    </li>
                                </ul>
                            </div>
                            <div>
                                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                                    Actes attendus au dossier
                                </p>
                                {/* Chaque acte dit s'il est réellement produisible. Le panneau
                                    dérive de l'enum, la génération lit les modèles : annoncer
                                    « Déclaration de modification RCCM » quand aucun modèle actif ne
                                    peut la produire rendait l'onglet Actes vide et inexplicable. */}
                                <ul className="mt-0.5 space-y-0.5 text-xs text-slate-600">
                                    {(modificationStatutaire.documents
                                        ?? Object.entries(modificationStatutaire.documentsRequis).map(([slug, label]) => ({ slug, label, disponible: true }))
                                    ).map(doc => (
                                        <li key={doc.slug} className="flex items-start gap-1.5">
                                            {doc.disponible
                                                ? <CheckCircle2 className="mt-0.5 h-3 w-3 shrink-0 text-success" />
                                                : <AlertCircle className="mt-0.5 h-3 w-3 shrink-0 text-warning-text" />}
                                            <span className={doc.disponible ? '' : 'text-warning-text'}>
                                                {doc.label}
                                                {!doc.disponible && (
                                                    <>
                                                        {' — '}
                                                        <Link href="/modeles" className="underline hover:no-underline">
                                                            modèle manquant
                                                        </Link>
                                                    </>
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function InformationsTab({ dossier, can, onEditQuest, managedRoles, onAjouterPersonne, onSupprimerPersonne, societe, modificationStatutaire }) {
    const [pieceModalPartie, setPieceModalPartie] = useState(null);
    const [previewPieceKey, setPreviewPieceKey] = useState(null);
    const togglePreviewPiece = (partieId, categorie) => {
        const key = `${partieId}:${categorie}`;
        setPreviewPieceKey(k => k === key ? null : key);
    };

    // Redirection depuis la création du dossier (?focus=pieces, voir DossierController::store())
    // — amène directement l'attention sur les pièces à fournir plutôt que de laisser l'utilisateur
    // les découvrir en scrollant.
    const piecesCardRef = useRef(null);
    const [showFocusPiecesBanner] = useState(
        () => new URLSearchParams(window.location.search).get('focus') === 'pieces'
    );
    useEffect(() => {
        if (showFocusPiecesBanner) {
            piecesCardRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, []);

    /**
     * Rôles réellement présents au dossier et porteurs de pièces — « associé/gérant » était écrit
     * pour une constitution et s'affichait tel quel sur une modification, dont les personnes sont
     * des cédants, cessionnaires, souscripteurs ou gérants entrants.
     */
    const rolesAvecPieces = [...new Set(
        (dossier.parties ?? [])
            .filter(p => p.piecesChecklist?.length > 0)
            .map(p => String(p.role ?? '').replace(/_/g, ' '))
    )].join(', ');

    /** Pièces que cette personne a déjà fournies ailleurs et qui manquent ici. */
    const nbReprises = (partie) =>
        (partie.piecesChecklist ?? []).filter(i => !i.aUnFichier && i.reprise).length;

    const questKey    = TYPE_ACTE_CODE_MAP[dossier.typeActe?.code];
    const questFields = QUESTIONNAIRES[questKey] ?? [];
    const hasQuestData = dossier.questionnaire && Object.keys(dossier.questionnaire).length > 0;

    // Sections ordonnées (les champs repeatable ont leur propre section)
    const sections = questFields.length > 0
        ? [...new Set(questFields.map(f => f.section ?? ''))].map(sec => ({
            name: sec,
            fields: questFields.filter(f => (f.section ?? '') === sec),
        }))
        : [];

    const renderQuestContent = () => {
        if (questFields.length > 0 && hasQuestData) {
            return (
                <div className="space-y-6">
                    {sections.map(sec => {
                        const filled = sec.fields.filter(f => {
                            const v = dossier.questionnaire[f.id];
                            if (f.type === 'repeatable') return Array.isArray(v) && v.length > 0;
                            return v !== undefined && v !== null && v !== '';
                        });
                        if (!filled.length) return null;
                        return (
                            <div key={sec.name || '_'} className="space-y-3">
                                {sec.name && (
                                    <h4 className="text-xs font-semibold text-slate-500 uppercase tracking-wider border-b border-slate-100 pb-1.5">
                                        {sec.name}
                                    </h4>
                                )}
                                {/* Champs scalaires */}
                                {(() => {
                                    const scalars = filled.filter(f => f.type !== 'repeatable');
                                    if (!scalars.length) return null;
                                    return (
                                        <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                                            {scalars.map(field => (
                                                <div key={field.id} className="space-y-0.5">
                                                    <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{field.label}</dt>
                                                    <dd className={cn('text-sm text-slate-800', field.mono && 'font-ref')}>
                                                        {typeof dossier.questionnaire[field.id] === 'boolean'
                                                            ? (dossier.questionnaire[field.id] ? 'Oui' : 'Non')
                                                            // Choix multiple (`checkbox_group`) : String() sur un
                                                            // tableau colle les libellés séparés par des virgules.
                                                            : Array.isArray(dossier.questionnaire[field.id])
                                                            ? dossier.questionnaire[field.id].join(' · ')
                                                            : String(dossier.questionnaire[field.id])}
                                                    </dd>
                                                </div>
                                            ))}
                                        </dl>
                                    );
                                })()}
                                {/* Blocs répétables */}
                                {filled.filter(f => f.type === 'repeatable').map(field => (
                                    <div key={field.id} className="pt-1">
                                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">{field.label}</p>
                                        <RepeatableGroup
                                            fieldDef={field}
                                            value={dossier.questionnaire[field.id] ?? []}
                                            onChange={() => {}}
                                            readOnly
                                        />
                                    </div>
                                ))}
                            </div>
                        );
                    })}
                </div>
            );
        }

        if (hasQuestData) {
            // Affichage brut quand aucun schéma n'est trouvé
            return (
                <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                    {Object.entries(dossier.questionnaire)
                        .filter(([, v]) => !Array.isArray(v))
                        .map(([key, value]) => (
                            <div key={key} className="space-y-0.5">
                                <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{key}</dt>
                                <dd className="text-sm text-slate-800">
                                    {typeof value === 'boolean' ? (value ? 'Oui' : 'Non') : String(value)}
                                </dd>
                            </div>
                        ))}
                </dl>
            );
        }

        return <p className="text-sm text-slate-400 italic">Questionnaire non renseigné — cliquez sur « Modifier » pour saisir les informations.</p>;
    };

    return (
        <div className="space-y-5">
            {/* En tête de l'onglet : c'est la condition bloquante pour quitter
                l'Édition (DossierStepService::verifierEdition), elle doit être la
                première chose vue — même après réception, pour que l'utilisateur
                sache d'un coup d'œil où en est cette pièce du dossier. */}
            <AccordClientCard dossier={dossier} can={can} />

            {/* Placée avant le questionnaire : pour une modification, savoir de quelle société
                on part et ce que le changement implique conditionne la lecture de tout le reste. */}
            <SocieteEtModificationCard societe={societe} modificationStatutaire={modificationStatutaire} />

            {/* Dossier constitutif — juste après la société, et avant les personnes : c'est la base
                documentaire sur laquelle les actes seront produits. Modifiable à la même condition
                que le questionnaire, donc à l'Initialisation. */}
            {societe && (
                <PiecesConstitutivesCard
                    societe={societe}
                    dossierReference={dossier.reference}
                    modifiable={!!can?.modifierQuestionnaire}
                    // Seule la prop `societe` est rechargée : la fiche dossier est lourde, et
                    // recharger la page entière refermerait les onglets et les aperçus ouverts.
                    onRafraichir={() => router.reload({ only: ['societe'] })}
                />
            )}

            <Card>
                <CardHeader className="pb-3 flex flex-row items-center justify-between">
                    <CardTitle>Fiche dossier — {dossier.typeActe?.label}</CardTitle>
                    <div className="flex items-center gap-2">
                        <Button size="sm" variant="outline" className="h-8 gap-1.5" asChild>
                            <a href={`/dossiers/${dossier.reference}/fiche-recueil`} target="_blank" rel="noopener noreferrer">
                                <Download className="h-3.5 w-3.5" />
                                Imprimer / télécharger la fiche
                            </a>
                        </Button>
                        {/* `modifierQuestionnaire` et non `update` : le questionnaire n'est
                            modifiable qu'à l'Édition — le modifier plus tard régénérerait les
                            actes sur un contenu que la certification n'a pas vu. La raison est
                            affichée plutôt que le bouton simplement absent. */}
                        {can?.modifierQuestionnaire ? (
                            <Button size="sm" variant="outline" className="h-8 gap-1.5" onClick={onEditQuest}>
                                <PenSquare className="h-3.5 w-3.5" />
                                Modifier le questionnaire
                            </Button>
                        ) : can?.update && (
                            <span className="text-xs text-slate-400 max-w-[260px] text-right leading-snug">
                                Questionnaire modifiable uniquement à l'étape Édition — passez par
                                le renvoi en correction.
                            </span>
                        )}
                    </div>
                </CardHeader>
                <CardContent>
                    {renderQuestContent()}
                </CardContent>
            </Card>

            <Card id={ANCRE_PIECES_PARTIES} ref={piecesCardRef}>
                <CardHeader className="pb-3 flex flex-row items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <Users className="h-4 w-4 text-seal" />
                        Personnes associées au dossier
                    </CardTitle>
                    {can?.modifierQuestionnaire ? (
                        <Button size="sm" variant="outline" className="h-8 gap-1.5" onClick={onAjouterPersonne}>
                            <Plus className="h-3.5 w-3.5" /> Ajouter une personne
                        </Button>
                    ) : can?.update && (
                        <span className="text-xs text-slate-400 max-w-[240px] text-right leading-snug">
                            Composition de l'acte arrêtée depuis l'Édition.
                        </span>
                    )}
                </CardHeader>
                <CardContent className="space-y-3">
                    {showFocusPiecesBanner && (
                        <div className="flex items-start gap-3 p-3 rounded-lg bg-seal-light border border-seal/30">
                            <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5 text-seal" />
                            <div className="text-sm text-ink">
                                Dossier créé — pensez à téléverser les pièces justificatives
                                {rolesAvecPieces ? ` de chaque ${rolesAvecPieces}` : ' des personnes'}{' '}
                                ci-dessous avant de faire confirmer le client.
                            </div>
                        </div>
                    )}
                    {!dossier.parties?.length ? (
                        <p className="text-sm text-slate-400 italic py-2">Aucune partie enregistrée.</p>
                    ) : dossier.parties.map((partie, i) => {
                        const estLibre = !managedRoles.includes(partie.role);
                        return (
                            <Card key={i}>
                                <CardContent className="p-4">
                                    <div className="flex items-start gap-4">
                                        <PartiePhotoAvatar partie={partie} canEdit={can?.gererPieces} />
                                        <div className="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <div className="font-medium text-slate-800">{partie.nom}</div>
                                                <Badge variant="secondary" className="mt-1">{partie.role}</Badge>
                                            </div>
                                            <div className="space-y-1 text-xs text-slate-500">
                                                {partie.cni && (
                                                    <div className="flex items-center gap-1.5">
                                                        <FileText className="h-3 w-3 shrink-0" />
                                                        <span className="font-ref">{partie.cni}</span>
                                                    </div>
                                                )}
                                                {partie.telephone && (
                                                    <div className="flex items-center gap-1.5">
                                                        <Phone className="h-3 w-3 shrink-0" />
                                                        {partie.telephone}
                                                    </div>
                                                )}
                                                {partie.adresse && (
                                                    <div className="flex items-center gap-1.5">
                                                        <MapPin className="h-3 w-3 shrink-0" />
                                                        {partie.adresse}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        {/* Une personne déjà connue de l'étude a rarement une seule
                                            pièce à reprendre — les cliquer une à une n'apporte rien. */}
                                        {can?.gererPieces && nbReprises(partie) > 0 && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="h-8 shrink-0 gap-1.5 border-seal/40 bg-seal-light text-seal-hover hover:border-seal"
                                                onClick={() => router.post(`/parties/${partie.id}/pieces/reprendre-tout`, {}, { preserveScroll: true, preserveState: true })}
                                                title="Copier dans ce dossier les pièces déjà fournies par cette personne"
                                            >
                                                <CopyCheck className="h-3.5 w-3.5" />
                                                Reprendre {nbReprises(partie)} pièce{nbReprises(partie) > 1 ? 's' : ''}
                                            </Button>
                                        )}
                                        {can?.modifierQuestionnaire && estLibre && (
                                            <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-danger shrink-0"
                                                onClick={() => onSupprimerPersonne(partie)} title="Retirer cette personne">
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                    {partie.piecesChecklist?.length > 0 && (
                                        <div className="mt-3 pt-3 border-t border-slate-100 divide-y divide-slate-50">
                                            {partie.piecesChecklist.map(item => (
                                                <PieceGedRow
                                                    key={item.categorie}
                                                    piece={item}
                                                    peutGerer={can?.gererPieces}
                                                    isPreviewOpen={previewPieceKey === `${partie.id}:${item.categorie}`}
                                                    onTogglePreview={() => togglePreviewPiece(partie.id, item.categorie)}
                                                    uploadUrl={`/parties/${partie.id}/pieces/${item.categorie}/televerser`}
                                                    downloadUrl={`/documents/${item.id}/download`}
                                                    repriseUrl={`/parties/${partie.id}/pieces/${item.categorie}/reprendre`}
                                                />
                                            ))}
                                        </div>
                                    )}
                                    <PartiePiecesList
                                        partie={partie}
                                        canEdit={can?.gererPieces}
                                        onAjouter={() => setPieceModalPartie(partie)}
                                    />
                                </CardContent>
                            </Card>
                        );
                    })}
                </CardContent>
            </Card>

            <ModalAjouterPiecePartie partie={pieceModalPartie} onClose={() => setPieceModalPartie(null)} />
        </div>
    );
}

function SignatureTab({ dossier, can }) {
    const [signatureTypeEdit, setSignatureTypeEdit] = useState(null); // 'client' or 'notaire'

    return (
        <div className="space-y-5">
            <Card>
                <CardHeader className="pb-3 border-b border-slate-100 flex flex-row justify-between items-center">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <FileSignature className="h-4 w-4 text-seal" />
                            Signatures
                        </CardTitle>
                        <p className="text-xs text-slate-500 mt-1">Renseignez les dates de signature pour chaque partie concernée.</p>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 border-b border-slate-100 text-slate-500 text-xs text-left">
                            <tr>
                                <th className="px-6 py-3 font-medium">Partie</th>
                                <th className="px-6 py-3 font-medium">Date de signature</th>
                                {can?.enregistrerSignatures && <th className="px-6 py-3 font-medium text-right w-32">Action</th>}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {/* Client */}
                            <tr className="hover:bg-slate-50/50 transition-colors">
                                <td className="px-6 py-4">
                                    <div className="font-medium text-slate-800">{dossier.parties?.map(p => p.nom).join(' & ') || 'Client'}</div>
                                    <div className="text-xs text-slate-500">Partie(s) au dossier</div>
                                </td>
                                <td className="px-6 py-4">
                                    {dossier.date_signature_client ? (
                                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-green-50 text-green-700 font-medium text-sm">
                                            <CheckCircle2 className="h-4 w-4" />
                                            {isoDateToFR(dossier.date_signature_client)}
                                        </span>
                                    ) : (
                                        <span className="text-slate-400 italic">Non renseignée</span>
                                    )}
                                </td>
                                {can?.enregistrerSignatures && (
                                    <td className="px-6 py-4 text-right">
                                        <Button variant="outline" size="sm" onClick={() => setSignatureTypeEdit('client')}>
                                            <CalendarDays className="h-3.5 w-3.5 mr-1.5" />
                                            {dossier.date_signature_client ? 'Modifier' : 'Renseigner'}
                                        </Button>
                                    </td>
                                )}
                            </tr>
                            {/* Notaire */}
                            <tr className="hover:bg-slate-50/50 transition-colors">
                                <td className="px-6 py-4">
                                    <div className="font-medium text-slate-800">{dossier.notaire?.name || 'Notaire'}</div>
                                    <div className="text-xs text-slate-500">Notaire instrumentant</div>
                                </td>
                                <td className="px-6 py-4">
                                    {dossier.date_signature_notaire ? (
                                        <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-green-50 text-green-700 font-medium text-sm">
                                            <CheckCircle2 className="h-4 w-4" />
                                            {isoDateToFR(dossier.date_signature_notaire)}
                                        </span>
                                    ) : (
                                        <span className="text-slate-400 italic">Non renseignée</span>
                                    )}
                                </td>
                                {can?.enregistrerSignatures && (
                                    <td className="px-6 py-4 text-right">
                                        <Button variant="outline" size="sm" onClick={() => setSignatureTypeEdit('notaire')}>
                                            <CalendarDays className="h-3.5 w-3.5 mr-1.5" />
                                            {dossier.date_signature_notaire ? 'Modifier' : 'Renseigner'}
                                        </Button>
                                    </td>
                                )}
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <ModalSaisieDateSignature
                open={!!signatureTypeEdit}
                onClose={() => setSignatureTypeEdit(null)}
                type={signatureTypeEdit}
                dossier={dossier}
            />
        </div>
    );
}

function ModalSaisieDateSignature({ open, onClose, type, dossier }) {
    const isClient = type === 'client';
    const existingDate = isClient ? dossier?.date_signature_client : dossier?.date_signature_notaire;
    const [date, setDate] = useState(existingDate ?? '');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (open) {
            setDate(existingDate ?? '');
        }
    }, [open, existingDate]);

    const submit = (e) => {
        e.preventDefault();
        setSaving(true);
        // Route dédiée : ces deux champs ne sont plus acceptés par la mise à jour
        // générique du dossier, ils ne sont modifiables qu'à l'étape Signature.
        router.patch(`/dossiers/${dossier.reference}/signatures`, {
            [isClient ? 'date_signature_client' : 'date_signature_notaire']: date || null,
        }, {
            preserveScroll: true,
            preserveState: true,
            onError: notifyValidationError,
            onFinish: () => setSaving(false),
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open={open} onOpenChange={o => !o && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Date de signature — {isClient ? (dossier.parties?.map(p => p.nom).join(' & ') || 'Client') : (dossier.notaire?.name || 'Notaire')}
                    </DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Date de signature</Label>
                        <DateField 
                            value={isoDateToFR(date)}
                            onValueChange={val => setDate(frDateToISO(val))}
                        />
                    </div>
                    <div className="flex justify-end gap-2 pt-4 border-t border-slate-100">
                        <Button type="button" variant="ghost" onClick={onClose} disabled={saving}>Annuler</Button>
                        <Button type="submit" variant="seal" disabled={saving || date === (existingDate ?? '')}>
                            {saving ? 'Enregistrement…' : 'Enregistrer'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DocumentHistoryDialog({ doc, onClose }) {
    const [versions, setVersions] = useState(null);

    useEffect(() => {
        if (!doc) return;
        setVersions(null);
        fetch(doc.url_versions, { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(data => setVersions(data.versions ?? []))
            .catch(() => setVersions([]));
    }, [doc?.id]);

    const restaurer = (v) => {
        router.post(`/documents/versions/${v.id}/restaurer`, {}, { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open={!!doc} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Historique — {doc?.nom}</DialogTitle>
                </DialogHeader>
                {versions === null ? (
                    <p className="text-sm text-slate-400 py-4">Chargement…</p>
                ) : versions.length === 0 ? (
                    <p className="text-sm text-slate-400 py-4">Aucune version enregistrée.</p>
                ) : (
                    <div className="space-y-1 max-h-80 overflow-y-auto">
                        {versions.map((v) => (
                            <div key={v.id} className={cn(
                                'flex items-center justify-between gap-2 px-3 py-2 rounded-md border',
                                v.est_actuelle ? 'border-seal/40 bg-seal-light/40' : 'border-slate-100'
                            )}>
                                <div className="min-w-0">
                                    <div className="text-sm text-slate-800 flex items-center gap-1.5">
                                        v{v.numero}
                                        {v.est_actuelle && <Badge variant="outline" className="text-[10px]">Actuelle</Badge>}
                                    </div>
                                    <div className="text-xs text-slate-400 truncate">
                                        {v.cree_par ?? 'Système'} · {v.created_at}
                                    </div>
                                </div>
                                <div className="flex items-center gap-1 shrink-0">
                                    <Button variant="ghost" size="icon-sm" asChild title="Télécharger cette version">
                                        <a href={v.url_download} download><Download className="h-3.5 w-3.5" /></a>
                                    </Button>
                                    {!v.est_actuelle && (
                                        <Button variant="ghost" size="icon-sm" title="Restaurer cette version" onClick={() => restaurer(v)}>
                                            <RefreshCw className="h-3.5 w-3.5" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}

function UploadSigneButton({ doc }) {
    const inputRef = useRef(null);
    const [uploading, setUploading] = useState(false);

    const handleFile = (file) => {
        if (!file) return;
        setUploading(true);
        router.post(doc.url_televerser_signe, { fichier: file }, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onError: notifyValidationError,
            onFinish: () => setUploading(false),
        });
    };

    return (
        <>
            <input ref={inputRef} type="file" className="hidden" onChange={(e) => handleFile(e.target.files?.[0])} />
            <Button
                variant="outline" size="sm" className="h-7 gap-1 text-xs"
                onClick={() => inputRef.current?.click()}
                disabled={uploading}
            >
                <Upload className="h-3 w-3" />
                {uploading ? 'Envoi…' : 'Déposer version signée/cachetée'}
            </Button>
        </>
    );
}

function DocumentsTab({ dossier, reference, etape, can, avancing, onSubmitRevision, onEditQuest }) {
    // Aperçu déplié sous la ligne du document, et non dans le panneau de pied de page.
    const apercu = useApercuEnLigne();

    const [confirmState, setConfirmState] = useState(null);
    const [generating, setGenerating] = useState(false);
    const [depotActe, setDepotActe] = useState(false);
    const [regenerating, setRegenerating] = useState(new Set());
    const [historyDoc, setHistoryDoc] = useState(null);

    // Verdicts de certification par document — sert à signaler directement dans cette
    // liste quels actes ont été renvoyés en correction, sans devoir aller consulter
    // l'onglet Certification pour le savoir. Un point "périmé" (perime=true) a déjà
    // été régénéré depuis le renvoi — le commentaire reste affiché (contexte) mais en
    // atténué, distinct des corrections encore à traiter.
    const revisionPointByDoc = {};
    (dossier.revision?.points ?? []).forEach(p => { revisionPointByDoc[String(p.point_id)] = p; });
    const docsACorreiger = (dossier.documents ?? []).filter(doc => revisionPointByDoc[String(doc.id)]?.etat === 'a_corriger' && !revisionPointByDoc[String(doc.id)]?.perime);
    const docsCorrigesEnAttente = (dossier.documents ?? []).filter(doc => revisionPointByDoc[String(doc.id)]?.etat === 'a_corriger' && revisionPointByDoc[String(doc.id)]?.perime);

    const handleGenererModeles = () => {
        setGenerating(true);
        router.post(`/dossiers/${reference}/generer-documents`, {}, {
            onFinish: () => setGenerating(false),
        });
    };

    const handleRegenerer = (doc) => {
        setRegenerating(prev => new Set(prev).add(doc.id));
        router.post(`/documents/${doc.id}/regenerer`, {}, {
            onFinish: () => setRegenerating(prev => {
                const next = new Set(prev);
                next.delete(doc.id);
                return next;
            }),
        });
    };

    const supprimer = (doc) => {
        setConfirmState({
            title: `Supprimer "${doc.nom}" ?`,
            description: 'Ce document sera définitivement supprimé.',
            confirmLabel: 'Supprimer',
            variant: 'destructive',
            onConfirm: () => router.delete(`/documents/${doc.id}`, { preserveState: true, onError: notifyValidationError }),
        });
    };

    return (
        <Card>
            <CardHeader className="pb-3 flex flex-row items-center justify-between">
                <CardTitle>Actes &amp; documents</CardTitle>
                <div className="flex items-center gap-2">
                    {/* Sans modèle actif, « Générer » ne produit rien et l'étape Édition exige
                        pourtant au moins un acte : le dépôt manuel est la seule issue. La route
                        serveur existait déjà, seule cette entrée manquait. */}
                    {can?.genererDocuments && (
                        <Button size="sm" variant="outline" className="h-8 gap-1" onClick={() => setDepotActe(true)}>
                            <Upload className="h-3.5 w-3.5" />
                            Déposer un acte
                        </Button>
                    )}
                    {can?.genererDocuments && (
                        <Button size="sm" variant="outline" className="h-8 gap-1" onClick={handleGenererModeles} disabled={generating}>
                            <RefreshCw className={cn('h-3.5 w-3.5', generating && 'animate-spin')} />
                            {generating ? 'Génération…' : 'Générer depuis les modèles'}
                        </Button>
                    )}
                </div>
            </CardHeader>
            <CardContent className="px-0 pb-0">
                {docsACorreiger.length > 0 && (
                    <div className="mx-5 mt-1 mb-4 p-4 rounded-lg bg-danger-bg border border-red-200 flex flex-col sm:flex-row sm:items-start gap-3">
                        <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5 text-danger-text" />
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-danger-text">
                                {docsACorreiger.length} document{docsACorreiger.length > 1 ? 's' : ''} renvoyé{docsACorreiger.length > 1 ? 's' : ''} en correction par le certificateur
                            </p>
                            <ul className="mt-1.5 space-y-1">
                                {docsACorreiger.map(doc => (
                                    <li key={doc.id} className="text-xs text-danger-text/80">
                                        <span className="font-medium">{doc.nom}</span>
                                        {revisionPointByDoc[String(doc.id)]?.commentaire && (
                                            <> — {revisionPointByDoc[String(doc.id)].commentaire}</>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                        {can?.modifierQuestionnaire && (
                            <Button size="sm" variant="warning" className="shrink-0" onClick={onEditQuest}>
                                <PenSquare className="h-3.5 w-3.5" />
                                Modifier le questionnaire
                            </Button>
                        )}
                    </div>
                )}
                {docsCorrigesEnAttente.length > 0 && (
                    <div className="mx-5 mt-1 mb-4 p-4 rounded-lg bg-slate-50 border border-slate-200 flex flex-col sm:flex-row sm:items-start gap-3">
                        <Clock className="h-4 w-4 shrink-0 mt-0.5 text-slate-400" />
                        <div className="flex-1 min-w-0">
                            <p className="text-sm font-medium text-slate-600">
                                {docsCorrigesEnAttente.length} document{docsCorrigesEnAttente.length > 1 ? 's' : ''} corrigé{docsCorrigesEnAttente.length > 1 ? 's' : ''} — en attente de re-soumission à la certification
                            </p>
                            <ul className="mt-1.5 space-y-1">
                                {docsCorrigesEnAttente.map(doc => (
                                    <li key={doc.id} className="text-xs text-slate-500">
                                        <span className="font-medium">{doc.nom}</span>
                                        {revisionPointByDoc[String(doc.id)]?.commentaire && (
                                            <> — <span className="italic">ancien commentaire : {revisionPointByDoc[String(doc.id)].commentaire}</span></>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                )}
                {/* Actes que la configuration prévoit et qui manquent au dossier. Corriger un
                    rattachement de gabarit ne réveille pas les dossiers existants — une correction
                    ne doit pas modifier en silence les dossiers d'autres clercs — mais sans cet
                    encart, l'acte manquant restait invisible tant qu'on ne le cherchait pas. */}
                {(dossier.actesManquants?.length ?? 0) > 0 && (
                    <div className="mx-5 mt-1 mb-4 flex flex-col gap-3 rounded-lg border border-amber-200 bg-warning-bg p-4 sm:flex-row sm:items-start">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-warning-text" />
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-medium text-warning-text">
                                {dossier.actesManquants.length} acte{dossier.actesManquants.length > 1 ? 's' : ''} prévu{dossier.actesManquants.length > 1 ? 's' : ''} par la configuration {dossier.actesManquants.length > 1 ? 'ne sont' : "n'est"} pas au dossier
                            </p>
                            <ul className="mt-1.5 space-y-0.5">
                                {dossier.actesManquants.map(a => (
                                    <li key={a.nom} className="text-xs text-warning-text">
                                        {a.nom} <span className="text-slate-400">— {a.type_document}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                        <Button size="sm" variant="warning" className="shrink-0" onClick={handleGenererModeles} disabled={generating}>
                            <RefreshCw className={cn('h-3.5 w-3.5', generating && 'animate-spin')} />
                            {generating ? 'Génération…' : 'Produire les actes manquants'}
                        </Button>
                    </div>
                )}
                {!dossier.documents?.length ? (
                    <div className="px-5 py-10 text-center">
                        <FileText className="h-10 w-10 text-slate-200 mx-auto mb-3" />
                        <p className="text-sm text-slate-400">Aucun document pour ce dossier.</p>
                        {can?.genererDocuments && (
                            <Button size="sm" variant="outline" className="mt-4" onClick={handleGenererModeles} disabled={generating}>
                                <RefreshCw className={cn('h-3.5 w-3.5', generating && 'animate-spin')} />
                                {generating ? 'Génération…' : 'Générer depuis les modèles'}
                            </Button>
                        )}
                    </div>
                ) : (
                    <table className="w-full table-notarial">
                        <thead>
                            <tr>
                                <th className="pl-5">Document</th>
                                <th>Type</th>
                                <th className="pr-5">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {dossier.documents.map((doc) => {
                                const pointDoc = revisionPointByDoc[String(doc.id)];
                                const aCorreiger = pointDoc?.etat === 'a_corriger' && !pointDoc?.perime;
                                const corrigeEnAttente = pointDoc?.etat === 'a_corriger' && pointDoc?.perime;
                                return (
                                    <React.Fragment key={doc.id}>
                                    <tr>
                                        <td className="pl-5">
                                            <div className="flex items-center gap-2">
                                                <FileText className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                                                <div>
                                                    <div className="flex items-center gap-1.5 flex-wrap">
                                                        <span className="text-sm text-slate-800">{doc.nom}</span>
                                                        {doc.version && <span className="text-[10px] text-slate-400">v{doc.version}</span>}
                                                        {aCorreiger && (
                                                            <Badge variant="danger" title={pointDoc?.commentaire || ''}>
                                                                <AlertTriangle className="h-3 w-3" />
                                                                À corriger
                                                            </Badge>
                                                        )}
                                                        {corrigeEnAttente && (
                                                            <Badge variant="secondary" title={pointDoc?.commentaire ? `Ancien commentaire : ${pointDoc.commentaire}` : ''}>
                                                                <Clock className="h-3 w-3" />
                                                                En attente de re-soumission
                                                            </Badge>
                                                        )}
                                                        {doc.est_signe_cachete && (
                                                            <span title="Verrouillé (signé/cacheté) — voir l'onglet Clôture">
                                                                <Lock className="h-3 w-3 text-success" />
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="text-xs text-slate-500">{doc.typeDocLabel ?? doc.categorie}</td>
                                        <td className="pr-5">
                                            <div className="flex items-center gap-1 justify-end flex-wrap">
                                                {doc.chemin_fichier && (
                                                    <>
                                                        <Button variant="ghost" size="icon-sm" title="Prévisualiser"
                                                            onClick={() => apercu.basculer(`doc-${doc.id}`)}>
                                                            <Eye className="h-3.5 w-3.5" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon-sm" asChild title="Télécharger">
                                                            <a href={`/documents/${doc.id}/download`} download>
                                                                <Download className="h-3.5 w-3.5" />
                                                            </a>
                                                        </Button>
                                                        <Button variant="ghost" size="icon-sm" title="Historique des versions"
                                                            onClick={() => setHistoryDoc(doc)}>
                                                            <History className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </>
                                                )}
                                                {can?.genererDocuments && !doc.est_signe_cachete && (
                                                    <>
                                                        <Button
                                                            variant="ghost" size="icon-sm"
                                                            title="Régénérer depuis le modèle (écrase la version actuelle)"
                                                            onClick={() => handleRegenerer(doc)}
                                                            disabled={regenerating.has(doc.id)}
                                                        >
                                                            <RefreshCw className={cn('h-3.5 w-3.5 text-slate-400', regenerating.has(doc.id) && 'animate-spin text-blue-500')} />
                                                        </Button>
                                                        <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-red-500"
                                                            onClick={() => supprimer(doc)} title="Supprimer">
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                    {/* Dans un tableau, l'aperçu doit occuper sa propre ligne
                                        sur toute la largeur — colSpan={3} suit les trois
                                        colonnes de l'en-tête. */}
                                    {apercu.estOuvert(`doc-${doc.id}`) && doc.has_file && (
                                        <tr>
                                            <td colSpan={3} className="px-5 pb-3">
                                                <ApercuSousLigne
                                                    ouvert
                                                    doc={doc}
                                                    previewUrl={doc.url_preview}
                                                    downloadUrl={doc.url_download}
                                                    onFermer={apercu.fermer}
                                                />
                                            </td>
                                        </tr>
                                    )}
                                    </React.Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                )}
                {can?.avancer && etape === 'edition' && (
                    <div className="px-5 py-3 border-t border-slate-100">
                        <Button variant="outline" size="sm" onClick={onSubmitRevision} disabled={avancing}>
                            <Send className="h-3.5 w-3.5" />
                            {avancing ? 'Envoi…' : 'Soumettre à certification'}
                        </Button>
                    </div>
                )}
            </CardContent>
            <ConfirmDialog
                open={!!confirmState}
                onClose={() => setConfirmState(null)}
                title={confirmState?.title ?? ''}
                description={confirmState?.description}
                confirmLabel={confirmState?.confirmLabel}
                variant={confirmState?.variant}
                onConfirm={confirmState?.onConfirm ?? (() => {})}
            />
            <DocumentHistoryDialog doc={historyDoc} onClose={() => setHistoryDoc(null)} />
            <ModalDeposerActe dossier={dossier} ouvert={depotActe} onClose={() => setDepotActe(false)} />
        </Card>
    );
}

/**
 * Dépôt manuel d'un acte au dossier.
 *
 * L'étape Édition exige au moins un acte pour être franchie, et n'offrait que « Générer depuis les
 * modèles » : un dossier dont aucun modèle n'est actif — le cas de toutes les modifications de
 * statuts tant que les gabarits ne sont pas fournis — restait bloqué sans recours.
 * `DocumentController::store` et sa route existaient déjà : seule cette entrée manquait.
 */
function ModalDeposerActe({ dossier, ouvert, onClose }) {
    const [nom, setNom] = useState('');
    const [categorie, setCategorie] = useState('acte_principal');
    const [fichier, setFichier] = useState(null);
    const [envoi, setEnvoi] = useState(false);

    const fermer = () => {
        setNom(''); setCategorie('acte_principal'); setFichier(null);
        onClose();
    };

    const soumettre = (e) => {
        e.preventDefault();
        setEnvoi(true);
        router.post(`/dossiers/${dossier.reference}/documents`, { nom, categorie, fichier }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: fermer,
            onError: notifyValidationError,
            onFinish: () => setEnvoi(false),
        });
    };

    return (
        <Dialog open={ouvert} onOpenChange={(o) => !o && fermer()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Déposer un acte</DialogTitle>
                </DialogHeader>
                <form onSubmit={soumettre} className="space-y-4">
                    <div>
                        <Label htmlFor="acte-nom">Nom de l'acte</Label>
                        <Input
                            id="acte-nom"
                            value={nom}
                            onChange={(e) => setNom(e.target.value)}
                            placeholder="Procès-verbal de l'assemblée"
                            required
                            className="mt-1"
                        />
                    </div>
                    <div>
                        <Label htmlFor="acte-categorie">Catégorie</Label>
                        <select
                            id="acte-categorie"
                            value={categorie}
                            onChange={(e) => setCategorie(e.target.value)}
                            className="mt-1 w-full rounded-md border border-slate-200 px-3 py-2 text-sm"
                        >
                            <option value="acte_principal">Acte principal</option>
                            <option value="annexe">Annexe</option>
                            <option value="procedure">Procédure</option>
                            <option value="lettre">Lettre</option>
                            <option value="recepisse">Récépissé</option>
                        </select>
                    </div>
                    <div>
                        <Label htmlFor="acte-fichier">Fichier</Label>
                        <Input
                            id="acte-fichier"
                            type="file"
                            accept=".pdf,.doc,.docx,.odt,.xlsx,.xls"
                            onChange={(e) => setFichier(e.target.files?.[0] ?? null)}
                            className="mt-1"
                        />
                        <p className="mt-1 text-xs text-slate-400">
                            Facultatif — un acte peut être créé maintenant et son fichier déposé plus tard.
                        </p>
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="ghost" onClick={fermer}>Annuler</Button>
                        <Button type="submit" disabled={envoi || !nom.trim()}>
                            {envoi ? 'Dépôt…' : 'Déposer'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}

const revisionStatutColors = Object.fromEntries(
    Object.entries(REVISION_STATUT_META).map(([key, meta]) => [key, meta.badge])
);

const revisionStatutLabels = Object.fromEntries(
    Object.entries(REVISION_STATUT_META).map(([key, meta]) => [key, meta.label])
);

function FormaliteCardDossier({ f, peutGerer }) {
    const [showPieces, setShowPieces] = useState(false);
    const [confirmState, setConfirmState] = useState(null);
    const [depotOpen, setDepotOpen] = useState(false);
    const [retourOpen, setRetourOpen] = useState(false);
    const [previewPieceId, setPreviewPieceId] = useState(null);
    const pieces  = f.pieces ?? [];
    const fournis = pieces.filter(p => p.est_fourni).length;

    const patch = (data) =>
        router.patch(`/formalites/${f.id}`, data, { preserveState: true, onError: notifyValidationError });

    const handleTogglePreview = (p) => setPreviewPieceId(id => id === p.id ? null : p.id);
    const handleSupprimer = () => setConfirmState({
        title: `Supprimer la formalité ${f.libelle || f.organismeLabel} ?`,
        description: 'Cette action est irréversible.',
        confirmLabel: 'Supprimer',
        variant: 'destructive',
        onConfirm: () => router.delete(`/formalites/${f.id}`, { preserveState: true, onError: notifyValidationError }),
    });

    const meta = FORMALITE_STATUT_META[f.statut] ?? FORMALITE_STATUT_META.a_deposer;
    const peutDeposer = f.statut === 'a_deposer' || f.statut === 'rejete';

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
        <ModalDepotFormalite open={depotOpen} onClose={() => setDepotOpen(false)} formalite={f} />
        <ModalRetourFormalite open={retourOpen} onClose={() => setRetourOpen(false)} formalite={f} />
        <Card className={cn(
            'border-l-4',
            f.estDepassee                                               && 'border-l-danger',
            !f.estDepassee && f.statut === 'retour_recu'                && 'border-l-success',
            !f.estDepassee && (f.statut === 'depose' || f.statut === 'en_attente') && 'border-l-blue-400',
            !f.estDepassee && f.statut === 'a_deposer'                  && 'border-l-slate-200',
            !f.estDepassee && f.statut === 'rejete'                     && 'border-l-danger',
        )}>
            <CardContent className="p-4">
                {/* Ligne organisme + badge statut */}
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <div className="font-medium text-slate-800 flex items-center gap-1.5">
                            {f.ordre && <span className="text-[10px] text-slate-400 font-ref">Ordre {f.ordre}</span>}
                            <span className={cn('text-[10px] font-medium px-1.5 py-0.5 rounded border', organismeBadgeClass(f.organisme))}>
                                {organismeShortLabel(f.organisme, f.organismeLabel)}
                            </span>
                            {f.libelle}
                            {f.estDepassee && <AlertTriangle className="h-3.5 w-3.5 text-danger" />}
                        </div>
                        {f.montant_calcule > 0 && (
                            <div className="text-xs text-slate-500 font-ref mt-0.5">
                                {Number(f.montant_calcule).toLocaleString('fr-GN')} GNF
                                {f.montant_paye != null && ` payé${f.numero_recepisse ? ` · Reçu ${f.numero_recepisse}` : ''}`}
                            </div>
                        )}
                        {f.echeance_at && (
                            <div className={cn('text-xs mt-0.5 flex items-center gap-1',
                                f.estDepassee ? 'text-danger-text font-medium' : 'text-slate-400')}>
                                <Clock className="h-3 w-3" />
                                Échéance {new Date(f.echeance_at).toLocaleDateString('fr-FR')}
                                {f.estDepassee && ' — dépassée'}
                            </div>
                        )}
                        {f.estBloquee && (
                            <div className="text-xs mt-1 flex items-center gap-1 text-slate-500">
                                <Lock className="h-3 w-3" />
                                Attend : {f.dependDeLabel} — se débloquera automatiquement à la réception
                            </div>
                        )}
                    </div>
                    <span className={cn('shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border', meta.badge)}>
                        {f.estBloquee ? 'Bloqué' : meta.label}
                    </span>
                </div>

                {/* Pièces attendues au RETOUR de l'organisme, pas au dépôt : masquées tant
                    que la formalité n'a pas été déposée. Les afficher dès « à déposer »
                    laissait croire qu'il fallait les téléverser avant d'aller à l'organisme.
                    `rejete` compte comme déposé — la formalité a fait l'aller-retour. */}
                {pieces.length > 0 && f.statut !== 'a_deposer' && (
                    <div className="mt-3 border-t border-slate-100 pt-2">
                        <button
                            type="button"
                            onClick={() => setShowPieces(v => !v)}
                            className="flex items-center gap-2 w-full text-left group"
                        >
                            <span className="text-xs text-slate-500 group-hover:text-ink transition-colors">
                                Pièces : {fournis}/{pieces.length}
                            </span>
                            <div className="flex-1 h-1 bg-slate-100 rounded-full overflow-hidden">
                                <div
                                    className="h-full bg-success rounded-full transition-all"
                                    style={{ width: `${pieces.length ? (fournis / pieces.length) * 100 : 0}%` }}
                                />
                            </div>
                            <span className="text-[10px] text-slate-400">{showPieces ? '▲' : '▼'}</span>
                        </button>
                        {showPieces && (
                            <div className="mt-1 divide-y divide-slate-50">
                                {pieces.map(p => (
                                    <PieceGedRow
                                        key={p.id}
                                        piece={p}
                                        peutGerer={peutGerer}
                                        isPreviewOpen={previewPieceId === p.id}
                                        onTogglePreview={handleTogglePreview}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* Boutons d'action */}
                {peutGerer && (
                    <div className="mt-3 pt-2 border-t border-slate-100 flex items-center gap-2 flex-wrap">
                        {peutDeposer && !f.estBloquee && (
                            <Button size="sm" variant="seal" className="h-7 text-xs gap-1" onClick={() => setDepotOpen(true)}>
                                <Upload className="h-3.5 w-3.5" /> {f.statut === 'rejete' ? 'Redéposer' : 'Marquer le dépôt'}
                            </Button>
                        )}
                        {(f.statut === 'depose' || f.statut === 'en_attente') && (
                            <Button
                                size="sm"
                                variant={f.estDepassee ? 'destructive' : 'outline'}
                                className={cn('h-7 text-xs gap-1', !f.estDepassee && 'border-green-300 text-green-700 hover:bg-green-50')}
                                onClick={() => setRetourOpen(true)}
                            >
                                <MailCheck className="h-3.5 w-3.5" /> Enregistrer un retour
                            </Button>
                        )}
                        <div className="flex-1" />
                        <Button size="icon-sm" variant="ghost" className="text-slate-300 hover:text-red-500"
                            onClick={handleSupprimer} title="Supprimer cette formalité">
                            <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                    </div>
                )}
            </CardContent>
        </Card>
        </>
    );
}

function FormalitesTab({ dossier, reference, can }) {
    const peutGerer = can?.gererFormalites;
    const formalites = dossier.formalites ?? [];

    const termine  = formalites.filter(f => f.statut === 'retour_recu').length;
    const retard   = formalites.filter(f => f.estDepassee && f.statut !== 'retour_recu').length;
    const bloque   = formalites.filter(f => f.estBloquee).length;
    const aDeposer = formalites.filter(f => (f.statut === 'a_deposer' || f.statut === 'rejete') && !f.estBloquee && !f.estDepassee).length;

    const fraisEstimes = formalites.reduce((sum, f) => sum + (f.montant_calcule || 0), 0);
    const dejaPayes    = formalites.reduce((sum, f) => sum + (f.montant_paye || 0), 0);
    const resteAPayer  = Math.max(0, fraisEstimes - dejaPayes);
    const progression  = formalites.length ? Math.round((termine / formalites.length) * 100) : 0;

    return (
        <div className="space-y-3">
            {formalites.length > 0 && (
                <Card>
                    <CardContent className="p-4 space-y-3">
                        <div className="flex items-center justify-between">
                            <span className="text-xs text-slate-500">Progression globale</span>
                            <span className="text-xs text-slate-500">{termine} / {formalites.length} démarches terminées ({progression}%)</span>
                        </div>
                        <Progress value={progression} className="h-1.5" />
                        <div className="flex items-center gap-4 text-xs text-slate-500 flex-wrap">
                            <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-success" /> Reçu : {termine}</span>
                            <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-danger" /> Retard : {retard}</span>
                            <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-amber-400" /> À déposer : {aDeposer}</span>
                            <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-slate-300" /> Bloqué : {bloque}</span>
                        </div>
                        <div className="grid grid-cols-3 gap-3 pt-1">
                            <div className="rounded-lg border border-slate-100 p-2.5">
                                <div className="text-[10px] text-slate-400 uppercase tracking-wide">Frais estimés</div>
                                <div className="text-sm font-semibold text-ink font-ref mt-0.5">{fraisEstimes.toLocaleString('fr-FR')} GNF</div>
                            </div>
                            <div className="rounded-lg border border-slate-100 p-2.5">
                                <div className="text-[10px] text-slate-400 uppercase tracking-wide">Déjà payés</div>
                                <div className="text-sm font-semibold text-success font-ref mt-0.5">{dejaPayes.toLocaleString('fr-FR')} GNF</div>
                            </div>
                            <div className="rounded-lg border border-slate-100 p-2.5">
                                <div className="text-[10px] text-slate-400 uppercase tracking-wide">Reste à payer</div>
                                <div className="text-sm font-semibold text-warning-text font-ref mt-0.5">{resteAPayer.toLocaleString('fr-FR')} GNF</div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Liste vide */}
            {!formalites.length && (
                <Card>
                    <CardContent className="p-6 text-center py-12">
                        <Building className="h-10 w-10 text-slate-200 mx-auto mb-3" />
                        <p className="text-slate-500 text-sm font-medium">Aucune formalité pour ce dossier</p>
                        <p className="text-xs text-slate-400 mt-1">
                            Les formalités sont générées automatiquement depuis les barèmes configurés pour ce type d'acte.
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* Cartes */}
            {formalites.map(f => (
                <FormaliteCardDossier key={f.id} f={f} peutGerer={peutGerer} />
            ))}
        </div>
    );
}

function ExpeditionTab({ dossier, reference, can }) {
    // Aperçu déplié sous la ligne concernée, et non dans le panneau de pied de page.
    const apercu = useApercuEnLigne();

    const [generatingId, setGeneratingId] = useState(null);
    const peutGerer  = can?.genererCourriers;
    const modeles    = dossier.courrierModelesApplicables ?? [];
    const courriers  = dossier.courriers ?? [];

    const genererCourrier = (modele) => {
        setGeneratingId(modele.id);
        router.post(`/dossiers/${reference}/courriers/generer`, { modele_courrier_id: modele.id }, {
            preserveScroll: true,
            onError: notifyValidationError,
            onFinish: () => setGeneratingId(null),
        });
    };

    const marquerEnvoye = (courrier) => {
        router.patch(`/courriers/${courrier.id}`, { statut: 'envoye' }, {
            preserveScroll: true,
            preserveState: true,
            onError: notifyValidationError,
        });
    };

    // Rattache à chaque modèle applicable le dernier courrier déjà généré à
    // partir de lui (le cas échéant), pour proposer aperçu/téléchargement/envoi
    // au même endroit que le bouton « Générer » plutôt que dans une liste séparée.
    const dernierParModele = new Map();
    modeles.forEach(m => {
        const dernier = courriers
            .filter(c => c.type === 'transmission' && c.objet === m.nom)
            .sort((a, b) => b.id - a.id)[0];
        if (dernier) dernierParModele.set(m.id, dernier);
    });
    const idsDejaAffiches = new Set([...dernierParModele.values()].map(c => c.id));
    const autresCourriers = courriers.filter(c => !idsDejaAffiches.has(c.id));

    return (
        <div className="space-y-4">
            {modeles.length > 0 && (
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm">Lettres de transmission disponibles</CardTitle>
                        <p className="text-xs text-slate-400">Générées à partir des données du dossier — à relire avant envoi.</p>
                    </CardHeader>
                    <CardContent className="pt-0 space-y-2">
                        {modeles.map(m => {
                            const genere = dernierParModele.get(m.id);
                            const cleApercu = `modele-${m.id}`;
                            return (
                                <div key={m.id}>
                                <div className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <span className="text-sm font-medium text-slate-800 truncate">{m.nom}</span>
                                            {genere && (
                                                <Badge variant={genere.statut === 'envoye' ? 'success' : 'secondary'} className="text-[10px]">
                                                    {genere.statut === 'envoye' ? 'Envoyé' : 'Brouillon'}
                                                </Badge>
                                            )}
                                        </div>
                                        {genere?.envoye_at && (
                                            <p className="text-xs text-slate-400 mt-0.5">envoyé le {genere.envoye_at}</p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-1 shrink-0">
                                        {genere?.has_file && (
                                            <>
                                                <Button
                                                    variant="ghost" size="icon-sm"
                                                    title={apercu.estOuvert(cleApercu) ? "Masquer l'aperçu" : 'Aperçu'}
                                                    onClick={() => apercu.basculer(cleApercu)}
                                                >
                                                    <Eye className={cn('h-3.5 w-3.5', apercu.estOuvert(cleApercu) && 'text-seal')} />
                                                </Button>
                                                <Button variant="ghost" size="icon-sm" asChild title="Télécharger">
                                                    <a href={genere.url_download} download>
                                                        <Download className="h-3.5 w-3.5" />
                                                    </a>
                                                </Button>
                                            </>
                                        )}
                                        {genere && peutGerer && genere.statut !== 'envoye' && (
                                            <Button variant="ghost" size="icon-sm" title="Marquer envoyé" onClick={() => marquerEnvoye(genere)}>
                                                <MailCheck className="h-3.5 w-3.5 text-slate-400" />
                                            </Button>
                                        )}
                                        {peutGerer && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="h-8 gap-1.5"
                                                disabled={generatingId === m.id}
                                                onClick={() => genererCourrier(m)}
                                            >
                                                <Send className={cn('h-3.5 w-3.5', generatingId === m.id && 'animate-pulse')} />
                                                {generatingId === m.id ? 'Génération…' : genere ? 'Régénérer' : 'Générer'}
                                            </Button>
                                        )}
                                    </div>
                                </div>
                                {genere?.has_file && (
                                    <ApercuSousLigne
                                        ouvert={apercu.estOuvert(cleApercu)}
                                        doc={{ id: genere.id, nom: genere.objet, chemin_fichier: genere.chemin_fichier }}
                                        previewUrl={genere.url_preview}
                                        downloadUrl={genere.url_download}
                                        onFermer={apercu.fermer}
                                    />
                                )}
                                </div>
                            );
                        })}
                    </CardContent>
                </Card>
            )}

            {autresCourriers.length === 0 ? (
                modeles.length === 0 && (
                    <Card>
                        <CardContent className="p-6 text-center py-12">
                            <Mail className="h-10 w-10 text-slate-200 mx-auto mb-3" />
                            <p className="text-slate-500 text-sm font-medium">Aucun courrier pour ce dossier</p>
                            <p className="text-xs text-slate-400 mt-1">Aucun modèle de courrier n'est configuré pour ce type d'acte.</p>
                        </CardContent>
                    </Card>
                )
            ) : (
                <div className="space-y-2">
                    <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Autres courriers</p>
                    {autresCourriers.map(c => (
                        <Card key={c.id}>
                            <CardContent className="p-4 flex items-center justify-between gap-3">
                                <div className="min-w-0">
                                    <div className="flex items-center gap-2 flex-wrap">
                                        <span className="text-sm font-medium text-slate-800 truncate">{c.objet}</span>
                                        <Badge variant={c.statut === 'envoye' ? 'success' : 'secondary'} className="text-[10px]">
                                            {c.statut === 'envoye' ? 'Envoyé' : 'Brouillon'}
                                        </Badge>
                                    </div>
                                    <p className="text-xs text-slate-400 mt-0.5 truncate">
                                        {c.destinataire || 'Destinataire non renseigné'}
                                        {c.envoye_at && ` · envoyé le ${c.envoye_at}`}
                                    </p>
                                </div>
                                <div className="flex items-center gap-1 shrink-0">
                                    {c.has_file && (
                                        <>
                                            <Button
                                                variant="ghost" size="icon-sm"
                                                title={apercu.estOuvert(`courrier-${c.id}`) ? "Masquer l'aperçu" : 'Aperçu'}
                                                onClick={() => apercu.basculer(`courrier-${c.id}`)}
                                            >
                                                <Eye className={cn('h-3.5 w-3.5', apercu.estOuvert(`courrier-${c.id}`) && 'text-seal')} />
                                            </Button>
                                            <Button variant="ghost" size="icon-sm" asChild title="Télécharger">
                                                <a href={c.url_download} download>
                                                    <Download className="h-3.5 w-3.5" />
                                                </a>
                                            </Button>
                                        </>
                                    )}
                                    {peutGerer && c.statut !== 'envoye' && (
                                        <Button variant="ghost" size="icon-sm" title="Marquer envoyé" onClick={() => marquerEnvoye(c)}>
                                            <MailCheck className="h-3.5 w-3.5 text-slate-400" />
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                            {c.has_file && (
                                <div className="px-4 pb-3">
                                    <ApercuSousLigne
                                        ouvert={apercu.estOuvert(`courrier-${c.id}`)}
                                        doc={{ id: c.id, nom: c.objet, chemin_fichier: c.chemin_fichier }}
                                        previewUrl={c.url_preview}
                                        downloadUrl={c.url_download}
                                        onFermer={apercu.fermer}
                                    />
                                </div>
                            )}
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * Conditions restant à remplir pour quitter l'étape courante.
 *
 * ⚠️ Miroir de DossierStepService::verifierPrerequis(), qui reste l'autorité : ce
 * fichier n'affiche que ce que le serveur appliquera. Toute règle modifiée là-bas
 * doit l'être ici — contrairement aux deux `match` PHP (exhaustifs), ce `switch`
 * JavaScript n'a aucun filet si une étape est ajoutée.
 *
 * Chaque blocage est un objet { texte, tab?, ancre? } : `tab`/`ancre` rendent le
 * message cliquable pour amener directement à l'endroit où agir, plutôt que de
 * laisser l'utilisateur chercher la section concernée.
 */
function getStepBlockers(dossier) {
    const etape = dossier?.etape?.value;
    const docs = dossier?.documents ?? [];
    const formalites = dossier?.formalites ?? [];
    const revision = dossier?.revision;

    switch (etape) {

        // Constitution du dossier — miroir de
        // DossierStepService::erreursDeConstitution(). Partagé par les deux premières
        // étapes : l'Édition rejoue ces contrôles pour les dossiers antérieurs au
        // 2026-08-04, qui n'ont jamais franchi d'Initialisation.
        case 'initialisation':
        case 'edition': {
            const b = [];
            if (!dossier.objet?.trim()) b.push({ texte: "L'objet du dossier n'est pas renseigné" });
            if (!dossier.notaire) b.push({ texte: "Aucun notaire n'est assigné au dossier" });
            if (!dossier.reviseur) b.push({ texte: "Aucun certificateur n'est assigné au dossier" });
            const partiesIncompletes = (dossier.parties ?? []).filter(p =>
                (p.piecesChecklist ?? []).some(item => !item.est_fourni));
            if (partiesIncompletes.length > 0) {
                b.push({
                    texte: `Pièces justificatives manquantes pour : ${partiesIncompletes.map(p => p.nom).join(', ')}`,
                    tab: 'informations',
                    ancre: ANCRE_PIECES_PARTIES,
                    action: 'Compléter les pièces',
                });
            }
            if (!dossier.accordClient?.est_signe_cachete) {
                // Libellé porté par le dossier : une modification de statuts attend la décision
                // des associés, pas une fiche de recueil signée.
                const attendu = dossier.accordAttendu;
                b.push({
                    texte: attendu
                        ? `« ${attendu.nom} » n'a pas été téléversé`
                        : "L'accord signé du client sur le questionnaire n'a pas été téléversé",
                    tab: 'informations',
                    ancre: ANCRE_ACCORD_CLIENT,
                    action: attendu?.imprimable === false ? 'Déposer la décision' : "Déposer l'accord",
                });
            }
            // Les actes ne sont générés qu'à l'entrée en Édition : ne rien exiger avant.
            if (etape === 'edition' && docs.length === 0) {
                b.push({ texte: "Aucun acte n'a été produit — au moins un est requis", tab: 'documents', action: 'Voir les actes' });
            }
            return b;
        }
        case 'revision': {
            if (revision?.statut === 'valide') return [];
            const texte = {
                renvoye:    'Certification renvoyée en correction — les points signalés doivent être corrigés',
                en_attente: 'Certification en attente — elle doit être évaluée par le certificateur',
                en_cours:   'Certification en cours — elle doit être validée pour continuer',
            }[revision?.statut] ?? 'La certification doit être validée avant de passer aux signatures';
            return [{ texte, tab: 'revision', action: 'Ouvrir la certification' }];
        }
        case 'signature': {
            const b = [];
            if (!dossier.date_signature_client) {
                b.push({ texte: "La date de signature du client n'est pas renseignée", tab: 'signature', action: 'Renseigner' });
            }
            if (!dossier.date_signature_notaire) {
                b.push({ texte: "La date de signature du notaire n'est pas renseignée", tab: 'signature', action: 'Renseigner' });
            }
            return b;
        }
        case 'formalites': {
            // « Terminée » = retour reçu (le statut `cloture` par formalité a été
            // supprimé) — voir StatutFormalite::estTerminee() côté serveur.
            const nonClos = formalites.filter(f => f.statut !== 'retour_recu');
            if (nonClos.length === 0) return [];
            const noms = nonClos.map(f => f.libelle || f.organismeLabel || f.organisme).join(', ');
            return [{
                texte: `${nonClos.length} formalité(s) sans retour enregistré : ${noms}`,
                tab: 'formalites',
                action: 'Ouvrir les formalités',
            }];
        }
        case 'expedition': {
            // Règle alignée sur verifierExpedition() : la facture doit être soldée.
            const b = [];
            const factures = dossier?.factures ?? [];
            const resteAPayer = factures.reduce((acc, f) => acc + (f.soldeRestant ?? 0), 0);
            if (resteAPayer > 0) {
                b.push({
                    texte: `Facture non soldée : ${fmtGNF(resteAPayer)} GNF restent à encaisser.`,
                    tab: 'facturation',
                    action: "Aller à la facturation",
                });
            }

            return b;
        }
        // 'cloture' : étape terminale, aucun bouton « Avancer » n'est rendu.
        default:
            return [];
    }
}

const fmtGNF = (n) => Number(n || 0).toLocaleString('fr-FR');

function FacturationTab({ dossier, can }) {
    // Aperçu du reçu déplié sous la ligne de paiement concernée.
    const apercu = useApercuEnLigne();

    const [paiementOpen, setPaiementOpen] = useState(false);
    const [paiementEnEdition, setPaiementEnEdition] = useState(null);
    const [paiementASupprimer, setPaiementASupprimer] = useState(null);
    const [ligneOpen, setLigneOpen] = useState(false);
    const [ligneEnEdition, setLigneEnEdition] = useState(null);
    const [ligneASupprimer, setLigneASupprimer] = useState(null);
    const peutGerer = !!can?.gererFacturation;


    const genererRecu = (p) => {
        router.post(`/paiements/${p.id}/recu`, {}, { preserveScroll: true, onError: notifyValidationError });
    };

    if (!dossier.factures?.length) {
        return (
            <Card>
                <CardContent className="p-6 text-center py-12">
                    <FileText className="h-10 w-10 text-slate-200 mx-auto mb-3" />
                    <p className="text-slate-500 text-sm font-medium">Aucune facture générée</p>
                    <p className="text-xs text-slate-400 mt-1">La facture sera calculée selon les barèmes configurés.</p>
                </CardContent>
            </Card>
        );
    }

    const facture = dossier.factures[dossier.factures.length - 1];
    const paiements = facture.paiements ?? [];
    const soldeRestant = facture.soldeRestant ?? 0;
    // Les lignes ne restent modifiables que tant qu'aucun paiement n'a été
    // enregistré — au-delà, le total ne doit plus bouger sous des encaissements
    // déjà effectués (même verrou que côté backend, voir FactureController).
    const lignesModifiables = peutGerer && paiements.length === 0;

    return (
        <div className="space-y-5">
            <ModalLigneFacture
                open={ligneOpen || !!ligneEnEdition}
                onClose={() => { setLigneOpen(false); setLigneEnEdition(null); }}
                factureId={facture.id}
                ligne={ligneEnEdition}
            />
            <ConfirmDialog
                open={!!ligneASupprimer}
                onClose={() => setLigneASupprimer(null)}
                title="Supprimer cette ligne ?"
                description={ligneASupprimer ? `La ligne « ${ligneASupprimer.designation} » sera définitivement supprimée.` : ''}
                confirmLabel="Supprimer"
                onConfirm={() => {
                    router.delete(`/lignes/${ligneASupprimer.id}`, { preserveScroll: true, onError: notifyValidationError });
                }}
            />
            <ModalEnregistrerPaiement
                open={paiementOpen || !!paiementEnEdition}
                onClose={() => { setPaiementOpen(false); setPaiementEnEdition(null); }}
                dossierReference={dossier.reference}
                soldeRestant={soldeRestant}
                totalFacture={facture.total_chiffres}
                paiement={paiementEnEdition}
            />
            <ConfirmDialog
                open={!!paiementASupprimer}
                onClose={() => setPaiementASupprimer(null)}
                title="Supprimer ce paiement ?"
                description={paiementASupprimer ? `Le paiement de ${fmtGNF(paiementASupprimer.montant)} GNF du ${paiementASupprimer.date_paiement} sera définitivement supprimé.` : ''}
                confirmLabel="Supprimer"
                onConfirm={() => {
                    router.delete(`/paiements/${paiementASupprimer.id}`, { preserveScroll: true, onError: notifyValidationError });
                }}
            />

            {/* Tuiles de synthèse */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <Card><CardContent className="p-4">
                    <div className="text-[10px] text-slate-400 uppercase tracking-wide">Honoraires facturés</div>
                    <div className="text-lg font-semibold text-ink font-ref mt-0.5">{fmtGNF(facture.total_chiffres)} GNF</div>
                </CardContent></Card>
                <Card><CardContent className="p-4">
                    <div className="text-[10px] text-slate-400 uppercase tracking-wide">Provisions reçues</div>
                    <div className="text-lg font-semibold text-success font-ref mt-0.5">{fmtGNF(facture.totalPaye)} GNF</div>
                </CardContent></Card>
                <Card><CardContent className="p-4">
                    <div className="text-[10px] text-slate-400 uppercase tracking-wide">
                        {soldeRestant < 0 ? 'Trop-perçu' : 'Solde restant dû'}
                    </div>
                    {/* Un trop-perçu n'est plus un état atteignable (le total des paiements
                        est plafonné au total facturé) : quand il apparaît, c'est une anomalie
                        de données antérieures — donc en rouge, pas en vert. */}
                    <div className={cn('text-lg font-semibold font-ref mt-0.5',
                        soldeRestant < 0 ? 'text-danger' : soldeRestant > 0 ? 'text-warning-text' : 'text-success')}>
                        {fmtGNF(Math.abs(soldeRestant))} GNF
                    </div>
                </CardContent></Card>
            </div>

            {facture.estTropPercue && (
                <div className="flex items-start gap-2.5 p-3 rounded-lg bg-danger-bg border border-danger/20 text-xs text-danger-text">
                    <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
                    <span>
                        <strong>Anomalie à corriger.</strong> Cette facture a encaissé{' '}
                        <span className="font-ref">{fmtGNF(Math.abs(soldeRestant))} GNF</span> de plus que son
                        total. Le total des paiements ne peut plus dépasser le total facturé — cet écart
                        vient d'un enregistrement antérieur à cette règle. Corrigez ou supprimez un paiement
                        ci-dessous (possible tant qu'aucun reçu n'a été émis).
                    </span>
                </div>
            )}

            <Card>
                <CardHeader className="pb-3 border-b border-slate-100 flex flex-row justify-between items-center">
                    <div>
                        <CardTitle>Note de frais / Facture</CardTitle>
                        <p className="text-xs text-slate-500 mt-1">Générée automatiquement d'après les barèmes</p>
                    </div>
                    <div className="flex gap-2">
                        {lignesModifiables && (
                            <Button variant="outline" size="sm" className="h-8 gap-1" onClick={() => setLigneOpen(true)}>
                                <Plus className="h-3.5 w-3.5" /> Ajouter une ligne
                            </Button>
                        )}
                        {peutGerer && (
                            <Button
                                variant="seal"
                                size="sm"
                                className="h-8 gap-1"
                                onClick={() => setPaiementOpen(true)}
                                disabled={!facture.peutRecevoirPaiement}
                                title={facture.peutRecevoirPaiement
                                    ? undefined
                                    : facture.total_chiffres > 0
                                        ? 'Facture entièrement soldée — le total des paiements ne peut pas dépasser le total facturé'
                                        : "Aucun montant à encaisser : ajoutez d'abord une ligne à la facture"}
                            >
                                <Wallet className="h-3.5 w-3.5" /> Enregistrer paiement
                            </Button>
                        )}
                        <a href={`/factures/${facture.id}/telecharger`} download>
                            <Button variant="outline" size="sm" className="h-8 gap-1">
                                <Download className="h-3.5 w-3.5" /> Télécharger
                            </Button>
                        </a>
                    </div>
                </CardHeader>
                <CardContent className="pt-5 space-y-6">
                    {/* En-tête facture */}
                    <div className="flex justify-between items-start">
                        <div>
                            <div className="font-medium text-slate-800 text-sm">{facture.objet}</div>
                            <div className="text-xs text-slate-500 mt-0.5">Assiette de calcul : {fmtGNF(facture.assiette_chiffres)} GNF</div>
                        </div>
                        <div className="text-right">
                            <div className="text-sm font-semibold text-seal">N° {facture.note_numero}</div>
                            <div className="text-xs text-slate-500 mt-0.5">{facture.note_date}</div>
                        </div>
                    </div>

                    {/* Tableau des lignes */}
                    <div className="border rounded-lg overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 border-b border-slate-200 text-slate-500 text-xs text-left">
                                <tr>
                                    <th className="px-4 py-2 font-medium">Désignation</th>
                                    <th className="px-4 py-2 font-medium text-right w-24">Qté</th>
                                    <th className="px-4 py-2 font-medium text-right w-36">Montant (GNF)</th>
                                    <th className="px-4 py-2 font-medium text-right w-36">Total (GNF)</th>
                                    {lignesModifiables && <th className="px-4 py-2 font-medium text-right w-16"></th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {facture.lignes?.map((ligne, i) => (
                                    <tr key={i} className="hover:bg-slate-50 transition-colors">
                                        <td className="px-4 py-3 text-slate-700">{ligne.designation}</td>
                                        <td className="px-4 py-3 text-slate-500 text-right">{ligne.quantite}</td>
                                        <td className="px-4 py-3 text-slate-600 text-right font-ref">{fmtGNF(ligne.montant)}</td>
                                        <td className="px-4 py-3 text-slate-800 font-medium text-right font-ref">{fmtGNF(ligne.total)}</td>
                                        {lignesModifiables && (
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="icon-sm" className="h-7 w-7 text-slate-400 hover:text-seal" title="Modifier" onClick={() => setLigneEnEdition(ligne)}>
                                                        <Pencil className="h-3.5 w-3.5" />
                                                    </Button>
                                                    <Button variant="ghost" size="icon-sm" className="h-7 w-7 text-slate-300 hover:text-danger" title="Supprimer" onClick={() => setLigneASupprimer(ligne)}>
                                                        <Trash2 className="h-3.5 w-3.5" />
                                                    </Button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-slate-50 border-t border-slate-200">
                                <tr>
                                    <td colSpan={3} className="px-4 py-3 text-right font-semibold text-slate-700">TOTAL À PAYER</td>
                                    <td className="px-4 py-3 text-right font-bold text-seal font-ref text-base">
                                        {fmtGNF(facture.total_chiffres)} GNF
                                    </td>
                                    {lignesModifiables && <td />}
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </CardContent>
            </Card>

            {/* Paiements */}
            <Card>
                <CardHeader className="pb-3 border-b border-slate-100">
                    <CardTitle>Paiements</CardTitle>
                </CardHeader>
                <CardContent className="pt-4">
                    {paiements.length === 0 && soldeRestant <= 0 ? (
                        <p className="text-sm text-slate-400 text-center py-4">Aucun mouvement enregistré.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="text-slate-500 text-xs text-left border-b border-slate-100">
                                <tr>
                                    <th className="py-2 font-medium">Date</th>
                                    <th className="py-2 font-medium">Type</th>
                                    <th className="py-2 font-medium text-right">Montant</th>
                                    <th className="py-2 font-medium text-right">Statut</th>
                                    {peutGerer && <th className="py-2 font-medium text-right w-20"></th>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-50">
                                {paiements.map(p => (
                                    <React.Fragment key={p.id}>
                                    <tr>
                                        <td className="py-2.5 text-slate-600">{p.date_paiement}</td>
                                        <td className="py-2.5 text-slate-700">
                                            Paiement{p.moyen_paiement && ` (${p.moyen_paiement})`}
                                        </td>
                                        <td className="py-2.5 text-right font-ref text-success font-medium">+{fmtGNF(p.montant)}</td>
                                        <td className="py-2.5 text-right">
                                            {p.recu ? (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-bg text-success-text border border-green-200">
                                                    Reçu émis
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                                    Sans reçu
                                                </span>
                                            )}
                                        </td>
                                        {peutGerer && (
                                            <td className="py-2.5 text-right">
                                                {p.recu ? (
                                                    <Button variant="ghost" size="icon-sm" className="h-7 w-7 text-slate-400 hover:text-seal" title={`Consulter le reçu ${p.recu.numero}`} onClick={() => apercu.basculer(`recu-${p.recu.id}`)}>
                                                        <Eye className="h-3.5 w-3.5" />
                                                    </Button>
                                                ) : (
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Button variant="ghost" size="icon-sm" className="h-7 w-7 text-slate-400 hover:text-seal" title="Modifier" onClick={() => setPaiementEnEdition(p)}>
                                                            <Pencil className="h-3.5 w-3.5" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon-sm" className="h-7 w-7 text-slate-400 hover:text-seal" title="Générer le reçu" onClick={() => genererRecu(p)}>
                                                            <Receipt className="h-3.5 w-3.5" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon-sm" className="h-7 w-7 text-slate-300 hover:text-danger" title="Supprimer" onClick={() => setPaiementASupprimer(p)}>
                                                            <Trash2 className="h-3.5 w-3.5" />
                                                        </Button>
                                                    </div>
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                    {p.recu && apercu.estOuvert(`recu-${p.recu.id}`) && (
                                        <tr>
                                            <td colSpan={peutGerer ? 5 : 4} className="pb-3">
                                                <ApercuSousLigne
                                                    ouvert
                                                    doc={{ id: p.recu.id, nom: `Reçu ${p.recu.numero}`, chemin_fichier: 'recu.pdf' }}
                                                    previewUrl={p.recu.url_apercu}
                                                    downloadUrl={p.recu.url_telechargement}
                                                    onFermer={apercu.fermer}
                                                />
                                            </td>
                                        </tr>
                                    )}
                                    </React.Fragment>
                                ))}
                                {soldeRestant > 0 && (
                                    <tr>
                                        <td className="py-2.5 text-slate-400">—</td>
                                        <td className="py-2.5 text-slate-700">Reste à payer</td>
                                        <td className="py-2.5 text-right font-ref text-warning-text font-medium">{fmtGNF(soldeRestant)}</td>
                                        <td className="py-2.5 text-right">
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                                                Attente
                                            </span>
                                        </td>
                                        {peutGerer && <td />}
                                    </tr>
                                )}
                                {soldeRestant < 0 && (
                                    <tr>
                                        <td className="py-2.5 text-slate-400">—</td>
                                        <td className="py-2.5 text-slate-700">Trop-perçu (provisions supérieures aux honoraires)</td>
                                        <td className="py-2.5 text-right font-ref text-success font-medium">{fmtGNF(Math.abs(soldeRestant))}</td>
                                        <td className="py-2.5 text-right">
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-success-bg text-success-text border border-green-200">
                                                Crédit client
                                            </span>
                                        </td>
                                        {peutGerer && <td />}
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    )}
                </CardContent>
            </Card>

        </div>
    );
}

// Reconstruit l'état local des verdicts de révision (par document) à partir des points
// déjà sauvegardés (tableau [{point_id, etat, commentaire}]) — point_id = id du document.
function buildInitialRevisionEtats(documents, points) {
    const byId = {};
    (points ?? []).forEach(p => { byId[String(p.point_id)] = { etat: p.etat, commentaire: p.commentaire, perime: !!p.perime }; });
    const init = {};
    (documents ?? []).forEach(doc => {
        const saved = byId[String(doc.id)];
        if (saved?.perime) {
            // Document régénéré depuis ce verdict (ex. questionnaire modifié) — on ne le
            // préremplit pas comme déjà évalué, le certificateur doit se prononcer à nouveau
            // sur la nouvelle version. L'ancien verdict/commentaire reste dispo pour contexte.
            init[String(doc.id)] = { etat: null, commentaire: '', ancienEtat: saved.etat, ancienCommentaire: saved.commentaire };
        } else {
            init[String(doc.id)] = { etat: saved?.etat ?? null, commentaire: saved?.commentaire ?? '' };
        }
    });
    return init;
}

// Onglet à afficher par défaut pour chaque étape du workflow — sert à la fois
// à ouvrir le bon onglet à l'arrivée sur le dossier et à y basculer
// automatiquement dès que l'étape change (avancer, renvoyer en correction…).
const ETAPE_TAB = {
    // L'Initialisation se joue dans l'onglet Informations : accord client à déposer,
    // pièces des personnes à compléter, questionnaire à relire.
    initialisation: 'informations',
    edition:        'documents',
    revision:       'revision',
    signature:      'signature',
    formalites:     'formalites',
    expedition:     'expedition',
    cloture:        'informations',
};

// Ordre du workflow (ETAPE_ORDER, importé de data/etapeMeta.js) — sert à estomper
// les onglets pas encore pertinents pour l'étape courante (ex. "Formalités" avant
// que le dossier n'atteigne cette étape) sans jamais les rendre inaccessibles :
// la traçabilité complète reste utile (audit, anticipation), seule la hiérarchie
// visuelle change.

// Étape minimale à partir de laquelle chaque onglet devient pleinement pertinent.
// Les onglets absents de cette table (informations, facturation) sont
// transversaux et ne sont jamais estompés.
// Étape à partir de laquelle un onglet devient pleinement actif — c'est-à-dire l'étape
// PENDANT laquelle on y travaille, pas celle qu'il porte dans son nom.
const TAB_STAGE = {
    documents:   'edition',
    revision:    'revision',
    signature:   'signature',
    formalites:  'formalites',
    expedition:  'expedition',
    // ⚠️ `expedition`, pas `cloture` : la vérification de l'inventaire se fait PENDANT
    // l'Expédition — c'est le prérequis pour en sortir, et `DossierPolicy::cloturerDocuments`
    // ne l'autorise qu'à cette étape. Le régler sur `cloture` estompait l'onglet au moment
    // précis où il fallait y agir : on demandait de vérifier 19 pièces dans un onglet qui
    // s'affichait comme non atteint. Contradiction signalée en usage réel.
    cloture:     'expedition',
};

// Un onglet déjà atteint une fois (des données concrètes y existent déjà) reste
// pleinement visible même si le dossier est repassé à une étape antérieure —
// après un renvoi en correction par exemple, l'étape recule de « révision » à
// « édition » mais la grille de révision déjà évaluée reste essentielle à
// consulter pour savoir quoi corriger ; l'estomper la rendrait invisible au
// moment précis où elle est le plus utile.
function tabPasEncoreAtteint(tabValue, etapeActuelle, dossier) {
    const stage = TAB_STAGE[tabValue];
    if (!stage) return false;
    if (ETAPE_ORDER.indexOf(stage) <= ETAPE_ORDER.indexOf(etapeActuelle)) return false;

    const dejaDesDonnees = {
        documents:  dossier.documents?.length > 0,
        revision:   !!dossier.revision,
        formalites: dossier.formalites?.length > 0,
        expedition: dossier.courriers?.length > 0,
        // Atteint dès qu'une pièce a été vérifiée — sert au cas où le dossier reculerait
        // d'Expédition à Formalités : le travail déjà fait reste consultable.
        cloture:    (dossier.clotureProgression?.verifiees ?? 0) > 0,
    }[tabValue];

    return !dejaDesDonnees;
}

// Ajoute une personne au dossier en dehors des rôles gérés par le questionnaire
// (ex. accompagnateur, témoin) — persiste immédiatement via un endpoint dédié,
// indépendant du flux de sauvegarde du questionnaire.
function ModalAjouterPersonne({ open, onClose, reference }) {
    const [client, setClient] = useState(null);
    const [role, setRole] = useState('');
    const [creatingClient, setCreatingClient] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (open) { setClient(null); setRole(''); }
    }, [open]);

    const submit = () => {
        if (!client || !role.trim()) return;
        setSaving(true);
        router.post(`/dossiers/${reference}/parties`, {
            ...buildPartieFields(client, {}, ''),
            role: role.trim(),
            client_id: client.id,
        }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: notifyValidationError,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <>
            <Dialog open={open} onOpenChange={onClose}>
                <DialogContent className="max-w-md">
                    <DialogHeader><DialogTitle>Ajouter une personne</DialogTitle></DialogHeader>
                    <div className="space-y-3 py-2">
                        <p className="text-xs text-slate-400">
                            Personne présente pour ce dossier mais qui n'est pas forcément liée à
                            l'acte en cours (accompagnateur, témoin…).
                        </p>
                        <div className="space-y-1.5">
                            <Label>Client</Label>
                            <ClientPicker
                                placeholder="Rechercher un client existant…"
                                linked={client}
                                onSelect={setClient}
                                onUnlink={() => setClient(null)}
                                onCreateNew={() => setCreatingClient(true)}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Qualité</Label>
                            <Input
                                placeholder="ex : témoin, accompagnateur…"
                                value={role}
                                onChange={e => setRole(e.target.value)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={onClose}>Annuler</Button>
                        <Button onClick={submit} disabled={saving || !client || !role.trim()}>
                            {saving ? 'Ajout…' : 'Ajouter'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
            <ModalNouveauClient
                open={creatingClient}
                onClose={() => setCreatingClient(false)}
                onCreated={(c) => { setClient(c); setCreatingClient(false); }}
            />
        </>
    );
}

export default function DossierShow() {
    const { dossier, can, reviseurs, formalistes, notaires, societe, modificationStatutaire } = usePage().props;
    const [activeTab, setActiveTab] = useState(() => {
        const requested = new URLSearchParams(window.location.search).get('tab');
        return requested || ETAPE_TAB[dossier?.etape?.value] || 'informations';
    });
    const [avancing, setAvancing] = useState(false);
    const [avancerErrors, setAvancerErrors] = useState([]);
    const [editDossierOpen, setEditDossierOpen] = useState(false);
    const [editQuestOpen, setEditQuestOpen] = useState(false);
    const [ajoutPersonneOpen, setAjoutPersonneOpen] = useState(false);
    const [historiqueOpen, setHistoriqueOpen] = useState(false);
    // Aperçu des documents de l'onglet Certification, déplié sous la carte concernée.
    // Le panneau d'aperçu unique en pied de page a été supprimé : chaque liste déplie
    // désormais l'aperçu sous la ligne concernée (voir Components/documents/ApercuSousLigne).
    const apercuCertif = useApercuEnLigne();

    // L'onglet actif est reporté dans l'URL (?tab=…), lue par l'initialiseur ci-dessus.
    // Deux bénéfices : un remontage du composant — quelle qu'en soit la cause — retrouve
    // l'onglet consulté au lieu de sauter sur celui de l'étape courante (c'est ce qui se
    // produisait après « Tout vérifier » dans l'onglet Clôture), et un lien vers un
    // onglet précis devient partageable.
    // replaceState et non pushState : chaque changement d'onglet n'a pas à créer une
    // entrée d'historique que le bouton Retour devrait défaire une par une.
    useEffect(() => {
        const url = new URL(window.location.href);
        if (url.searchParams.get('tab') === activeTab) return;
        url.searchParams.set('tab', activeTab);
        window.history.replaceState(window.history.state, '', url);
    }, [activeTab]);


    // Révision — évaluation inline des documents (onglet "Révision")
    const [revisionEtats, setRevisionEtats] = useState(() => buildInitialRevisionEtats(dossier.documents, dossier.revision?.points));
    const [showRenvoyerDialog, setShowRenvoyerDialog] = useState(false);
    const [motifRenvoyer, setMotifRenvoyer] = useState('');
    const [savingRevision, setSavingRevision] = useState(false);
    const [validatingRevision, setValidatingRevision] = useState(false);
    const [renvoyantRevision, setRenvoyantRevision] = useState(false);

    const etape = dossier?.etape?.value ?? '';
    const reference = dossier?.reference ?? '';

    // Rôles gérés par le questionnaire de ce type d'acte — sert à distinguer les
    // "autres personnes" (rôle libre, ajoutées indépendamment du questionnaire)
    // des parties structurées (modifiables uniquement via "Modifier le questionnaire").
    const managedRoles = getManagedClientRoles(QUESTIONNAIRES[TYPE_ACTE_CODE_MAP[dossier.typeActe?.code]] ?? []);

    const supprimerPersonne = (partie) => {
        router.delete(`/parties/${partie.id}`, { preserveScroll: true, onError: notifyValidationError });
    };

    // Bascule automatiquement sur l'onglet correspondant dès que l'étape change
    // en cours de session (avancer, ou renvoyer en correction qui fait reculer
    // le dossier) — ignore le montage initial pour ne pas écraser un onglet
    // demandé explicitement via ?tab= ou l'étape courante (voir l'initialiseur
    // de useState ci-dessus).
    const etapePrecedenteRef = useRef(etape);
    useEffect(() => {
        if (etapePrecedenteRef.current !== etape) {
            setActiveTab(ETAPE_TAB[etape] ?? 'informations');
            etapePrecedenteRef.current = etape;
        }
    }, [etape]);

    const blockers = can?.avancer ? getStepBlockers(dossier) : [];

    /**
     * Amène à l'endroit où lever un blocage : bascule sur l'onglet concerné puis fait
     * défiler jusqu'à la section. Le défilement est différé d'un tick — l'onglet
     * cible n'est monté qu'après le rendu déclenché par setActiveTab.
     */
    const allerAuBlocage = ({ tab, ancre }) => {
        if (tab) setActiveTab(tab);
        if (!ancre) return;
        setTimeout(() => {
            document.getElementById(ancre)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 60);
    };

    const actionContextuel = {
        revision:   { label: 'Voir la certification', tab: 'revision', variant: 'seal',    icon: ClipboardCheck },
        formalites: { label: 'Voir les formalités', tab: 'formalites', variant: 'default', icon: Building },
        expedition: { label: "Voir l'expédition",   tab: 'expedition', variant: 'default', icon: Mail },
    };
    const action = actionContextuel[etape];

    const revisionDocList     = dossier.documents ?? [];
    const revisionStatut      = dossier.revision?.statut ?? 'en_attente';
    // Un point périmé (document régénéré depuis un renvoi en correction) a déjà été
    // remis à null (etat) par buildInitialRevisionEtats — il compte donc naturellement
    // comme non-évalué ici, cohérent avec Revision::pointsValides() côté backend, qui
    // exige que le certificateur se prononce à nouveau sur la version régénérée.
    const revisionEvalues     = Object.values(revisionEtats).filter(e => e.etat !== null).length;
    const revisionOk          = Object.values(revisionEtats).filter(e => e.etat === 'ok').length;
    const revisionACorriger   = Object.values(revisionEtats).filter(e => e.etat === 'a_corriger').length;
    const revisionACorrigerSansCommentaire = Object.values(revisionEtats)
        .some(e => e.etat === 'a_corriger' && !e.commentaire?.trim());
    const revisionPct         = revisionDocList.length > 0 ? Math.round((revisionEvalues / revisionDocList.length) * 100) : 0;
    const revisionCanValidate = revisionACorriger === 0 && revisionEvalues === revisionDocList.length && revisionDocList.length > 0;
    const revisionTousEvalues = revisionEvalues === revisionDocList.length;
    const revisionCanRenvoyer = revisionACorriger > 0 && !revisionACorrigerSansCommentaire && revisionTousEvalues;

    const setRevisionVerdict = (docId, etat) => {
        setRevisionEtats(prev => ({ ...prev, [docId]: { ...prev[docId], etat } }));
    };
    const setRevisionCommentaire = (docId, commentaire) => {
        setRevisionEtats(prev => ({ ...prev, [docId]: { ...prev[docId], commentaire } }));
    };

    const handleSaveRevision = () => {
        setSavingRevision(true);
        router.put(`/dossiers/${reference}/revision`, { points: revisionEtats }, {
            preserveScroll: true,
            preserveState: true,
            onError: notifyValidationError,
            onFinish: () => setSavingRevision(false),
        });
    };

    // Sauvegarde d'abord les points en base (l'évaluation ne vit qu'en état local
    // React tant qu'on n'a pas cliqué « Sauvegarder ») avant de valider/renvoyer,
    // sinon le serveur peut refuser l'action car il ne connaît pas encore les
    // verdicts que l'utilisateur vient de saisir.
    const handleValiderRevision = () => {
        setValidatingRevision(true);
        router.put(`/dossiers/${reference}/revision`, { points: revisionEtats }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                router.post(`/dossiers/${reference}/revision/valider`, {}, {
                    preserveScroll: true,
                    preserveState: true,
                    onError: notifyValidationError,
                    onFinish: () => setValidatingRevision(false),
                });
            },
            onError: (errors) => { notifyValidationError(errors); setValidatingRevision(false); },
        });
    };

    const handleRenvoyerRevision = () => {
        setRenvoyantRevision(true);
        router.put(`/dossiers/${reference}/revision`, { points: revisionEtats }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                router.post(`/dossiers/${reference}/revision/renvoyer`, { motif: motifRenvoyer }, {
                    preserveScroll: true,
                    preserveState: true,
                    onError: notifyValidationError,
                    onFinish: () => { setRenvoyantRevision(false); setShowRenvoyerDialog(false); },
                });
            },
            onError: (errors) => { notifyValidationError(errors); setRenvoyantRevision(false); },
        });
    };

    const handleAvancer = (onDone) => {
        setAvancerErrors([]);
        setAvancing(true);
        router.post(`/dossiers/${reference}/avancer`, {}, {
            onFinish: () => setAvancing(false),
            onError: (errors) => setAvancerErrors(Object.values(errors).flat()),
            onSuccess: () => { setAvancerErrors([]); onDone?.(); },
        });
    };

    if (!dossier) {
        return (
            <AppLayout breadcrumbs={[{ label: 'Dossiers', href: '/dossiers' }, { label: 'Chargement…' }]}>
                <div className="p-6 text-center text-slate-400">Chargement…</div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={[
            { label: 'Dossiers', href: '/dossiers' },
            { label: reference }
        ]}>
            <Head title={`${reference} — Ayelema`} />

            <div className="flex gap-0 h-full">
                {/* Zone principale */}
                <div className="flex-1 min-w-0 overflow-y-auto">
                    {/* Barre d'action « Avancer » — toujours visible en scrollant, pour
                        qu'elle reste facile à trouver quel que soit l'onglet ou la
                        largeur d'écran (le panneau latéral droit est masqué sous xl). */}
                    {can?.avancer && (
                        <div className="sticky top-0 z-30 bg-white/95 backdrop-blur-sm border-b border-slate-200 px-6 py-3 flex items-center justify-between gap-3 shadow-sm">
                            {/* Le statut était un petit badge pâle, difficile à repérer alors
                                que c'est l'information qui commande tout le reste de l'écran :
                                pastille de couleur, libellé lisible, et position dans le
                                workflow (« 4 / 6 ») pour savoir d'un coup d'œil où en est le
                                dossier. */}
                            <div className="flex min-w-0 items-center gap-3">
                                <span className={cn(
                                    'inline-flex shrink-0 items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-semibold',
                                    ETAPE_META[dossier.etape?.value]?.badge ?? 'bg-slate-100 text-slate-600 border-slate-200'
                                )}>
                                    <span className={cn(
                                        'h-2 w-2 rounded-full',
                                        ETAPE_META[dossier.etape?.value]?.dot ?? 'bg-slate-400'
                                    )} />
                                    {dossier.etape?.label}
                                    <span className="text-[11px] font-normal opacity-70">
                                        {(ETAPE_ORDER.indexOf(dossier.etape?.value) + 1) || '?'} / {ETAPE_ORDER.length}
                                    </span>
                                </span>
                                {(avancerErrors.length > 0 || blockers.length > 0) && (
                                    <span className="flex shrink-0 items-center gap-1 text-xs text-amber-600">
                                        <AlertTriangle className="h-3 w-3" />
                                        {(avancerErrors.length > 0 ? avancerErrors : blockers).length} condition(s) requise(s)
                                    </span>
                                )}
                            </div>
                            {/* Le bouton n'affichait que le nom de l'étape cible, précédé d'une
                                icône flèche ET suivi d'un « → » en texte : « → Expédition → ».
                                Rien n'indiquait qu'il s'agissait de faire avancer le dossier, et
                                la double flèche brouillait la lecture. Désormais une étiquette
                                « Étape suivante » surmonte la destination : l'action et la cible
                                sont lisibles séparément. */}
                            <Button
                                variant="seal"
                                size="lg"
                                className="h-auto shrink-0 gap-2.5 py-2"
                                onClick={() => handleAvancer()}
                                disabled={avancing || blockers.length > 0}
                                title={blockers.length > 0
                                    ? 'Des conditions sont requises avant de passer à l\'étape suivante'
                                    : (dossier.etapeSuivante ? `Faire passer le dossier à l'étape ${dossier.etapeSuivante.label}` : '')}
                            >
                                {avancing ? (
                                    <>
                                        <RefreshCw className="h-4 w-4 animate-spin" />
                                        Passage en cours…
                                    </>
                                ) : (
                                    <>
                                        <span className="flex flex-col items-start leading-tight text-left">
                                            <span className="text-[10px] font-normal uppercase tracking-wide opacity-80">
                                                {dossier.etapeSuivante?.value === 'cloture' ? 'Dernière étape' : 'Étape suivante'}
                                            </span>
                                            <span className="text-sm font-semibold">
                                                {dossier.etapeSuivante?.label ?? 'Avancer'}
                                            </span>
                                        </span>
                                        <ArrowRight className="h-4 w-4 shrink-0" />
                                    </>
                                )}
                            </Button>
                        </div>
                    )}
                    <div className="p-6 space-y-5 max-w-[960px]">

                        {/* En-tête dossier */}
                        <motion.div initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }}>
                            <Card>
                                <CardContent className="p-5">
                                    <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                        <div className="space-y-2">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="font-ref text-sm text-seal">{reference}</span>
                                                {/* Statut également ici : l'en-tête collant n'apparaît
                                                    que si l'utilisateur peut faire avancer le dossier
                                                    (voir la condition plus haut), or tout le monde doit
                                                    savoir où il en est. */}
                                                <span className={cn(
                                                    'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold',
                                                    ETAPE_META[dossier.etape?.value]?.badge ?? 'bg-slate-100 text-slate-600 border-slate-200'
                                                )}>
                                                    <span className={cn(
                                                        'h-1.5 w-1.5 rounded-full',
                                                        ETAPE_META[dossier.etape?.value]?.dot ?? 'bg-slate-400'
                                                    )} />
                                                    {dossier.etape?.label}
                                                </span>
                                                {dossier.typeActe?.categorie && (
                                                    <Badge variant="secondary">{dossier.typeActe.categorie}</Badge>
                                                )}
                                                {dossier.typeActe?.label && (
                                                    <Badge variant="outline">{dossier.typeActe.label}</Badge>
                                                )}
                                                {dossier.estEnRetard && (
                                                    <Badge variant="danger" className="gap-1">
                                                        <AlertTriangle className="h-2.5 w-2.5" />
                                                        En retard
                                                    </Badge>
                                                )}
                                                {dossier.urgent && (
                                                    <Badge variant="warning" className="gap-1">
                                                        <Zap className="h-2.5 w-2.5" />
                                                        Urgent
                                                    </Badge>
                                                )}
                                            </div>
                                            <h1 className="font-serif text-display text-ink">{dossier.objet}</h1>
                                            <div className="flex items-center gap-4 text-xs text-slate-500">
                                                {dossier.echeance && (
                                                    <span className={cn('flex items-center gap-1', dossier.estEnRetard && 'text-danger')}>
                                                        <Clock className="h-3 w-3" />
                                                        Échéance {dossier.echeance}
                                                    </span>
                                                )}
                                                {dossier.valeur > 0 && (
                                                    <span className="font-ref">{Number(dossier.valeur).toLocaleString('fr-GN')} GNF</span>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2 shrink-0 flex-wrap">
                                            <Button variant="outline" size="sm" className="h-8 gap-1.5"
                                                onClick={() => setHistoriqueOpen(true)} title="Historique du dossier">
                                                <History className="h-3.5 w-3.5" />
                                                Historique
                                            </Button>
                                            {can?.update && (
                                                <Button variant="outline" size="sm" className="h-8 gap-1.5"
                                                    onClick={() => setEditDossierOpen(true)} title="Modifier le dossier">
                                                    <Pencil className="h-3.5 w-3.5" />
                                                    Modifier
                                                </Button>
                                            )}
                                            {action && (
                                                action.tab ? (
                                                    <Button variant={action.variant} onClick={() => setActiveTab(action.tab)}>
                                                        <action.icon className="h-4 w-4" />
                                                        {action.label}
                                                    </Button>
                                                ) : (
                                                    <Button variant={action.variant} asChild>
                                                        <Link href={action.href}>
                                                            <action.icon className="h-4 w-4" />
                                                            {action.label}
                                                        </Link>
                                                    </Button>
                                                )
                                            )}
                                        </div>
                                    </div>

                                    {/* Conditions requises avant avancer */}
                                    {can?.avancer && (avancerErrors.length > 0 || blockers.length > 0) && (
                                        <div className="mt-3 p-3 rounded-lg border bg-amber-50 border-amber-200">
                                            <div className="flex items-start gap-2">
                                                <AlertTriangle className="h-4 w-4 text-amber-500 mt-0.5 shrink-0" />
                                                <div className="flex-1">
                                                    <p className="text-xs font-semibold text-amber-800 mb-1.5">
                                                        Conditions requises avant de passer à l'étape suivante
                                                    </p>
                                                    <ul className="space-y-1">
                                                        {/* Les erreurs serveur arrivent en chaînes brutes, les blocages
                                                            calculés en objets { texte, tab?, ancre? } — normalisés ici
                                                            pour n'avoir qu'un seul rendu. */}
                                                        {(avancerErrors.length > 0
                                                            ? avancerErrors.map(texte => ({ texte }))
                                                            : blockers
                                                        ).map((item, i) => (
                                                            <li key={i} className="text-xs text-amber-700 flex items-start gap-1.5">
                                                                <span className="mt-1.5 h-1 w-1 rounded-full bg-amber-400 shrink-0" />
                                                                {/* Bouton explicite et non simple soulignement : le
                                                                    lien passait inaperçu, et l'utilisateur ne voyait pas
                                                                    par où lever le blocage — signalé en usage réel. */}
                                                                {item.tab ? (
                                                                    <span className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                                                        <span>{item.texte}</span>
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => allerAuBlocage(item)}
                                                                            className="inline-flex shrink-0 items-center gap-1 rounded-md border border-amber-300 bg-white/70 px-2 py-0.5 text-[11px] font-medium text-amber-800 transition-colors hover:bg-white"
                                                                        >
                                                                            {item.action ?? 'Corriger'}
                                                                            <ArrowRight className="h-3 w-3" />
                                                                        </button>
                                                                    </span>
                                                                ) : item.texte}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Rappel : dossier renvoyé en correction */}
                                    {etape === 'edition' && dossier.revision?.statut === 'renvoye' && (
                                        <div className="mt-3 p-3 rounded-lg border bg-danger-bg border-red-200">
                                            <div className="flex items-start gap-2">
                                                <AlertTriangle className="h-4 w-4 text-danger mt-0.5 shrink-0" />
                                                <div className="flex-1">
                                                    <p className="text-xs font-semibold text-danger-text mb-1">
                                                        Ce dossier a été renvoyé en correction
                                                    </p>
                                                    {dossier.revision?.commentaire && (
                                                        <p className="text-xs text-danger-text/90 mb-1.5">{dossier.revision.commentaire}</p>
                                                    )}
                                                    <button
                                                        type="button"
                                                        onClick={() => setActiveTab('revision')}
                                                        className="text-xs font-medium text-danger-text underline underline-offset-2 hover:no-underline"
                                                    >
                                                        Voir le détail des points signalés
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Équipe */}
                                    <div className="flex items-center gap-4 mt-4 pt-4 border-t border-slate-100 flex-wrap">
                                        {dossier.redacteur && (
                                            <div className="flex items-center gap-2">
                                                <Avatar className="h-6 w-6">
                                                    <AvatarFallback className="text-[9px] bg-ink text-white">
                                                        {dossier.redacteur.initiales ?? dossier.redacteur.name?.slice(0, 2)}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <div>
                                                    <div className="text-xs font-medium text-slate-700">{dossier.redacteur.name}</div>
                                                    <div className="text-[10px] text-slate-400">Rédacteur</div>
                                                </div>
                                            </div>
                                        )}
                                        {dossier.reviseur && (
                                            <>
                                                <Separator orientation="vertical" className="h-6" />
                                                <div className="flex items-center gap-2">
                                                    <Avatar className="h-6 w-6">
                                                        <AvatarFallback className="text-[9px] bg-seal text-white">
                                                            {dossier.reviseur.initiales ?? dossier.reviseur.name?.slice(0, 2)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <div className="text-xs font-medium text-slate-700">{dossier.reviseur.name}</div>
                                                        <div className="text-[10px] text-slate-400">Certificateur</div>
                                                    </div>
                                                </div>
                                            </>
                                        )}
                                        {dossier.notaire && (
                                            <>
                                                <Separator orientation="vertical" className="h-6" />
                                                <div className="flex items-center gap-2">
                                                    <Avatar className="h-6 w-6">
                                                        <AvatarFallback className="text-[9px] bg-stone-600 text-white">
                                                            {dossier.notaire.initiales ?? dossier.notaire.name?.slice(0, 2)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <div className="text-xs font-medium text-slate-700">{dossier.notaire.name}</div>
                                                        <div className="text-[10px] text-slate-400">Notaire</div>
                                                    </div>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </motion.div>

                        {/* Onglets */}
                        <motion.div initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }}>
                            <Tabs value={activeTab} onValueChange={setActiveTab}>
                                <TabsList className="w-full justify-start overflow-x-auto">
                                    <TabsTrigger value="informations">
                                        <span className="flex items-center gap-1.5">
                                            <Info className="h-3.5 w-3.5" />
                                            Informations
                                            {/* L'Initialisation se joue dans cet onglet : même
                                                pastille « étape en cours » que les autres. */}
                                            {etape === 'initialisation' && activeTab !== 'informations' && (
                                                <span className="h-1.5 w-1.5 rounded-full bg-seal" title="Étape en cours" />
                                            )}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="documents" className={cn(tabPasEncoreAtteint('documents', etape, dossier) && 'opacity-40')}>
                                        <span className="flex items-center gap-1.5">
                                            <FileText className="h-3.5 w-3.5" />
                                            Actes & documents
                                            {dossier.documents?.length > 0 && (
                                                <span className="text-[10px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full">
                                                    {dossier.documents.length}
                                                </span>
                                            )}
                                            {etape === 'edition' && activeTab !== 'documents' && (
                                                <span className="h-1.5 w-1.5 rounded-full bg-seal" title="Étape en cours" />
                                            )}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="revision" className={cn(tabPasEncoreAtteint('revision', etape, dossier) && 'opacity-40')}>
                                        <span className="flex items-center gap-1.5">
                                            <ClipboardCheck className="h-3.5 w-3.5" />
                                            Certification
                                            {dossier.revision?.statut === 'en_cours' && (
                                                <span className="h-1.5 w-1.5 rounded-full bg-warning" />
                                            )}
                                            {etape === 'revision' && activeTab !== 'revision' && (
                                                <span className="h-1.5 w-1.5 rounded-full bg-seal" title="Étape en cours" />
                                            )}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="signature" className={cn(tabPasEncoreAtteint('signature', etape, dossier) && 'opacity-40')}>
                                        <span className="flex items-center gap-1.5">
                                            <FileSignature className="h-3.5 w-3.5" />
                                            Signature
                                            {etape === 'signature' && activeTab !== 'signature' && (
                                                <span className="h-1.5 w-1.5 rounded-full bg-seal" title="Étape en cours" />
                                            )}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="formalites" className={cn(tabPasEncoreAtteint('formalites', etape, dossier) && 'opacity-40')}>
                                        <span className="flex items-center gap-1.5">
                                            <Building className="h-3.5 w-3.5" />
                                            Formalités
                                            {dossier.formalites?.length > 0 && (
                                                <span className="text-[10px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full">
                                                    {dossier.formalites.length}
                                                </span>
                                            )}
                                            {etape === 'formalites' && activeTab !== 'formalites' && (
                                                <span className="h-1.5 w-1.5 rounded-full bg-seal" title="Étape en cours" />
                                            )}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="expedition" className={cn(tabPasEncoreAtteint('expedition', etape, dossier) && 'opacity-40')}>
                                        <span className="flex items-center gap-1.5">
                                            <Mail className="h-3.5 w-3.5" />
                                            Expédition
                                            {dossier.courriers?.length > 0 && (
                                                <span className="text-[10px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full">
                                                    {dossier.courriers.length}
                                                </span>
                                            )}
                                            {etape === 'expedition' && activeTab !== 'expedition' && (
                                                <span className="h-1.5 w-1.5 rounded-full bg-seal" title="Étape en cours" />
                                            )}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="facturation">
                                        <span className="flex items-center gap-1.5">
                                            <Receipt className="h-3.5 w-3.5" />
                                            Facturation
                                            {dossier.factures?.length > 0 && (
                                                <span className="text-[10px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full">
                                                    {dossier.factures.length}
                                                </span>
                                            )}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="cloture" className={cn(tabPasEncoreAtteint('cloture', etape, dossier) && 'opacity-40')}>
                                        <span className="flex items-center gap-1.5">
                                            <Lock className="h-3.5 w-3.5" />
                                            Clôture
                                            {(() => {
                                                // Pièces restant à vérifier dans l'inventaire. Le compteur
                                                // lisait `est_requis`, colonne vidée lors du passage à
                                                // l'inventaire dérivé : il affichait donc toujours 0.
                                                const { total = 0, verifiees = 0 } = dossier.clotureProgression ?? {};
                                                const restantes = total - verifiees;
                                                return restantes > 0 ? (
                                                    <span className="text-[10px] bg-warning-bg text-warning-text px-1.5 py-0.5 rounded-full">
                                                        {restantes}
                                                    </span>
                                                ) : total > 0 ? (
                                                    <span className="text-[10px] bg-success-bg text-success-text px-1.5 py-0.5 rounded-full">
                                                        ✓
                                                    </span>
                                                ) : null;
                                            })()}
                                            {etape === 'cloture' && activeTab !== 'cloture' && (
                                                <span className="h-1.5 w-1.5 rounded-full bg-seal" title="Étape en cours" />
                                            )}
                                        </span>
                                    </TabsTrigger>
                                </TabsList>

                                {/* Onglet Informations */}
                                <TabsContent value="informations">
                                    {/* Bloc questionnaire */}
                                    <InformationsTab
                                        dossier={dossier}
                                        can={can}
                                        onEditQuest={() => setEditQuestOpen(true)}
                                        managedRoles={managedRoles}
                                        onAjouterPersonne={() => setAjoutPersonneOpen(true)}
                                        onSupprimerPersonne={supprimerPersonne}
                                        societe={societe}
                                        modificationStatutaire={modificationStatutaire}
                                    />
                                </TabsContent>

                                {/* Onglet Actes & Documents */}
                                <TabsContent value="documents">
                                    <DocumentsTab
                                        dossier={dossier}
                                        reference={reference}
                                        etape={etape}
                                        can={can}
                                        avancing={avancing}
                                        onSubmitRevision={() => handleAvancer()}
                                        onEditQuest={() => setEditQuestOpen(true)}
                                    />
                                </TabsContent>

                                {/* Onglet Révision */}
                                <TabsContent value="revision" className="space-y-4">
                                    {/* Dialog renvoyer en correction */}
                                    {showRenvoyerDialog && (
                                        <motion.div
                                            initial={{ opacity: 0 }}
                                            animate={{ opacity: 1 }}
                                            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                                            onClick={e => e.target === e.currentTarget && setShowRenvoyerDialog(false)}
                                        >
                                            <motion.div
                                                initial={{ scale: 0.95, opacity: 0 }}
                                                animate={{ scale: 1, opacity: 1 }}
                                                className="bg-white rounded-xl shadow-dialog p-6 w-full max-w-md"
                                            >
                                                <h3 className="font-serif text-heading text-ink mb-1">Renvoyer en correction</h3>
                                                <p className="text-sm text-slate-500 mb-4">
                                                    Décrivez le motif général du renvoi (optionnel) — le rédacteur le recevra en plus des commentaires par document.
                                                </p>
                                                <textarea
                                                    rows={3}
                                                    value={motifRenvoyer}
                                                    onChange={e => setMotifRenvoyer(e.target.value)}
                                                    placeholder="Motif général du renvoi en correction…"
                                                    className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-seal resize-none mb-4"
                                                />
                                                <div className="flex items-center gap-2 justify-end">
                                                    <Button variant="outline" onClick={() => setShowRenvoyerDialog(false)}>Annuler</Button>
                                                    <Button variant="warning" onClick={handleRenvoyerRevision} disabled={renvoyantRevision}>
                                                        <AlertTriangle className="h-4 w-4" />
                                                        {renvoyantRevision ? 'Envoi…' : 'Confirmer le renvoi'}
                                                    </Button>
                                                </div>
                                            </motion.div>
                                        </motion.div>
                                    )}

                                    {/* En-tête révision */}
                                    <Card className={cn(
                                        'border-l-4',
                                        revisionStatut === 'valide' && 'border-l-success',
                                        revisionStatut === 'en_cours' && 'border-l-seal',
                                        revisionStatut === 'renvoye' && 'border-l-danger',
                                        revisionStatut === 'en_attente' && 'border-l-slate-300',
                                    )}>
                                        <CardContent className="p-5">
                                            <div className="flex items-center justify-between gap-4">
                                                <div>
                                                    <h3 className="font-serif text-heading text-ink">Certification des actes</h3>
                                                    {dossier.revision?.reviseur && (
                                                        <p className="text-sm text-slate-500 mt-0.5">Certificateur : {dossier.revision.reviseur.name}</p>
                                                    )}
                                                </div>
                                                <span className={cn(
                                                    'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border shrink-0',
                                                    revisionStatutColors[revisionStatut] ?? 'bg-slate-50 text-slate-600 border-slate-200'
                                                )}>
                                                    {revisionStatutLabels[revisionStatut] ?? revisionStatut}
                                                </span>
                                            </div>

                                            {dossier.revision?.commentaire && (
                                                <div className="p-3 rounded-lg bg-slate-50 border border-slate-200 text-sm text-slate-700 mt-4">
                                                    {dossier.revision.commentaire}
                                                </div>
                                            )}

                                            {revisionDocList.length > 0 && (
                                                <div className="mt-5 space-y-2">
                                                    <div className="flex items-center justify-between text-xs">
                                                        <span className="text-slate-500">
                                                            {revisionEvalues}/{revisionDocList.length} document{revisionDocList.length > 1 ? 's' : ''} évalué{revisionEvalues > 1 ? 's' : ''}
                                                        </span>
                                                        <div className="flex items-center gap-3">
                                                            {revisionOk > 0 && (
                                                                <span className="flex items-center gap-1 text-success">
                                                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                                                    {revisionOk} OK
                                                                </span>
                                                            )}
                                                            {revisionACorriger > 0 && (
                                                                <span className="flex items-center gap-1 text-danger">
                                                                    <XCircle className="h-3.5 w-3.5" />
                                                                    {revisionACorriger} à corriger
                                                                </span>
                                                            )}
                                                        </div>
                                                    </div>
                                                    <Progress
                                                        value={revisionPct}
                                                        indicatorClassName={revisionACorriger > 0 ? 'bg-danger' : revisionEvalues === revisionDocList.length ? 'bg-success' : 'bg-seal'}
                                                    />
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>

                                    {/* Aucun document */}
                                    {revisionDocList.length === 0 && (
                                        <Card>
                                            <CardContent className="p-10 flex flex-col items-center gap-3 text-center">
                                                <FileText className="h-10 w-10 text-slate-200" />
                                                <p className="text-slate-500 text-sm">Aucun document n'a encore été ajouté à ce dossier.</p>
                                            </CardContent>
                                        </Card>
                                    )}

                                    {/* Liste des documents */}
                                    <div className="space-y-3">
                                        {revisionDocList.map((doc) => {
                                            const docId   = String(doc.id);
                                            const etatDoc = revisionEtats[docId] ?? { etat: null, commentaire: '' };
                                            const isOk    = etatDoc.etat === 'ok';
                                            const isNok   = etatDoc.etat === 'a_corriger';
                                            // Document régénéré depuis un renvoi en correction, pas encore réévalué dans
                                            // cette nouvelle version — l'ancien verdict est gardé pour contexte uniquement.
                                            const isCorrigeEnAttente = !etatDoc.etat && etatDoc.ancienEtat != null;

                                            return (
                                                <Card key={doc.id} className={cn(
                                                    'transition-colors border',
                                                    isOk && 'border-success/40 bg-success-bg/20',
                                                    isNok && 'border-danger/30 bg-danger-bg/30',
                                                    isCorrigeEnAttente && 'border-slate-200 bg-slate-50',
                                                    !isOk && !isNok && !isCorrigeEnAttente && 'border-slate-200',
                                                )}>
                                                    <CardContent className="p-5">
                                                        <div className="flex items-start justify-between gap-3">
                                                            <div className="flex items-start gap-3 min-w-0">
                                                                <div className={cn(
                                                                    'h-9 w-9 rounded-lg flex items-center justify-center shrink-0 mt-0.5',
                                                                    isOk ? 'bg-success/10' : isNok ? 'bg-danger/10' : isCorrigeEnAttente ? 'bg-slate-200' : 'bg-slate-100'
                                                                )}>
                                                                    {isOk ? (
                                                                        <CheckCircle2 className="h-4.5 w-4.5 text-success" />
                                                                    ) : isNok ? (
                                                                        <XCircle className="h-4.5 w-4.5 text-danger" />
                                                                    ) : isCorrigeEnAttente ? (
                                                                        <Clock className="h-4.5 w-4.5 text-slate-400" />
                                                                    ) : (
                                                                        <FileText className="h-4.5 w-4.5 text-slate-400" />
                                                                    )}
                                                                </div>
                                                                <div className="min-w-0">
                                                                    <p className="font-medium text-slate-800 leading-snug">{doc.nom}</p>
                                                                    <Badge variant="outline" className="mt-1">
                                                                        {doc.typeDocLabel ?? doc.categorie}
                                                                    </Badge>
                                                                </div>
                                                            </div>

                                                            {doc.has_file && (
                                                                <div className="flex items-center gap-1.5 shrink-0">
                                                                    <a
                                                                        href={doc.url_download}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium border border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50 transition-all"
                                                                    >
                                                                        <Download className="h-3.5 w-3.5" />
                                                                        Télécharger
                                                                    </a>
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => apercuCertif.basculer(`certif-${doc.id}`)}
                                                                        className="flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium border border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50 transition-all"
                                                                    >
                                                                        <Eye className="h-3.5 w-3.5" />
                                                                        Aperçu
                                                                    </button>
                                                                </div>
                                                            )}
                                                        </div>

                                                        {doc.has_file && (
                                                            <ApercuSousLigne
                                                                ouvert={apercuCertif.estOuvert(`certif-${doc.id}`)}
                                                                doc={doc}
                                                                previewUrl={doc.url_preview}
                                                                downloadUrl={doc.url_download}
                                                                onFermer={apercuCertif.fermer}
                                                            />
                                                        )}

                                                        {isCorrigeEnAttente && (
                                                            <div className="mt-4 pt-4 border-t border-slate-100 flex items-start gap-2 text-xs text-slate-500 bg-slate-50 rounded-lg p-3">
                                                                <Clock className="h-3.5 w-3.5 shrink-0 mt-0.5" />
                                                                <div>
                                                                    <span className="font-medium text-slate-600">Régénéré depuis un renvoi en correction</span>
                                                                    {etatDoc.ancienCommentaire && (
                                                                        <> — ancien commentaire : « {etatDoc.ancienCommentaire} »</>
                                                                    )}
                                                                    {can?.reviser && ' — merci de réexaminer la nouvelle version.'}
                                                                </div>
                                                            </div>
                                                        )}

                                                        {can?.reviser && (
                                                            <div className="flex items-center gap-2 mt-4 pt-4 border-t border-slate-100">
                                                                <span className="text-xs text-slate-400 mr-1">Verdict :</span>
                                                                <button
                                                                    onClick={() => setRevisionVerdict(docId, isOk ? null : 'ok')}
                                                                    className={cn(
                                                                        'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border transition-all',
                                                                        isOk
                                                                            ? 'bg-success text-white border-success shadow-sm'
                                                                            : 'bg-white text-slate-500 border-slate-200 hover:border-success hover:text-success'
                                                                    )}
                                                                >
                                                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                                                    OK
                                                                </button>
                                                                <button
                                                                    onClick={() => setRevisionVerdict(docId, isNok ? null : 'a_corriger')}
                                                                    className={cn(
                                                                        'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border transition-all',
                                                                        isNok
                                                                            ? 'bg-danger text-white border-danger shadow-sm'
                                                                            : 'bg-white text-slate-500 border-slate-200 hover:border-danger hover:text-danger'
                                                                    )}
                                                                >
                                                                    <AlertTriangle className="h-3.5 w-3.5" />
                                                                    À corriger
                                                                </button>
                                                            </div>
                                                        )}

                                                        {(can?.reviser || etatDoc.commentaire) && (
                                                            <div className="mt-3">
                                                                <span className="text-xs text-slate-400">
                                                                    {isNok ? (
                                                                        <>Commentaire <span className="text-danger">*</span> — obligatoire pour un document à corriger</>
                                                                    ) : (
                                                                        'Commentaire (optionnel)'
                                                                    )}
                                                                </span>
                                                                <textarea
                                                                    value={etatDoc.commentaire}
                                                                    onChange={e => setRevisionCommentaire(docId, e.target.value)}
                                                                    placeholder="Décrivez les corrections à apporter…"
                                                                    rows={2}
                                                                    readOnly={!can?.reviser}
                                                                    className={cn(
                                                                        'mt-1 w-full text-sm rounded-lg border px-3 py-2 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-seal resize-none',
                                                                        isNok && !etatDoc.commentaire?.trim() ? 'border-danger' : 'border-slate-200'
                                                                    )}
                                                                />
                                                            </div>
                                                        )}

                                                        {!can?.reviser && etatDoc.etat && !isCorrigeEnAttente && (
                                                            <div className={cn(
                                                                'mt-4 pt-4 border-t border-slate-100 flex items-center gap-2 text-sm font-medium',
                                                                isOk ? 'text-success' : 'text-danger-text'
                                                            )}>
                                                                {isOk ? <CheckCircle2 className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}
                                                                {isOk ? 'Document validé' : 'À corriger'}
                                                            </div>
                                                        )}
                                                    </CardContent>
                                                </Card>
                                            );
                                        })}
                                    </div>

                                    {/* Avertissement si documents à corriger */}
                                    {revisionACorriger > 0 && (
                                        <div className="flex items-start gap-3 p-4 rounded-lg bg-danger-bg border border-red-200 text-danger-text text-sm">
                                            <XCircle className="h-4 w-4 shrink-0 mt-0.5" />
                                            <div>
                                                {revisionStatut === 'renvoye' ? (
                                                    <>
                                                        <span className="font-medium">Déjà renvoyé en correction — </span>
                                                        {revisionACorriger} document{revisionACorriger > 1 ? 's' : ''} en attente de correction par le rédacteur.
                                                    </>
                                                ) : (
                                                    <>
                                                        <span className="font-medium">Validation bloquée — </span>
                                                        {revisionACorriger} document{revisionACorriger > 1 ? 's' : ''} à corriger.
                                                        {!revisionTousEvalues && ' Évaluez également les documents restants avant de renvoyer en correction.'}
                                                        {revisionTousEvalues && ' Renvoyez le dossier en édition pour que le rédacteur effectue les corrections.'}
                                                    </>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {/* Actions */}
                                    {can?.reviser && (
                                        <div className="flex flex-col sm:flex-row items-center gap-3 pt-1">
                                            <div className="flex-1" />
                                            <Button
                                                variant="outline"
                                                onClick={handleSaveRevision}
                                                disabled={savingRevision || revisionACorrigerSansCommentaire}
                                                title={revisionACorrigerSansCommentaire ? 'Ajoutez un commentaire aux documents « À corriger »' : ''}
                                            >
                                                <Send className="h-4 w-4" />
                                                {savingRevision ? 'Sauvegarde…' : 'Sauvegarder'}
                                            </Button>
                                            <Button
                                                variant="warning"
                                                disabled={!revisionCanRenvoyer}
                                                onClick={() => setShowRenvoyerDialog(true)}
                                                title={!revisionTousEvalues ? 'Évaluez tous les documents avant de renvoyer en correction' : (revisionACorrigerSansCommentaire ? 'Ajoutez un commentaire aux documents « À corriger »' : '')}
                                            >
                                                <AlertTriangle className="h-4 w-4" />
                                                Renvoyer en correction
                                                {revisionACorriger > 0 && (
                                                    <span className="ml-1 text-xs bg-warning-text/20 px-1.5 py-0.5 rounded-full">
                                                        {revisionACorriger}
                                                    </span>
                                                )}
                                            </Button>
                                            <Button
                                                variant="seal"
                                                disabled={!revisionCanValidate || validatingRevision || revisionStatut === 'valide'}
                                                onClick={handleValiderRevision}
                                            >
                                                <Shield className="h-4 w-4" />
                                                {validatingRevision ? 'Validation…' : revisionStatut === 'valide' ? 'Certification validée ✓' : 'Valider la certification'}
                                                {!revisionCanValidate && revisionEvalues < revisionDocList.length && revisionStatut !== 'valide' && (
                                                    <span className="text-xs opacity-70 ml-1">
                                                        ({revisionDocList.length - revisionEvalues} restant{revisionDocList.length - revisionEvalues > 1 ? 's' : ''})
                                                    </span>
                                                )}
                                            </Button>
                                        </div>
                                    )}
                                </TabsContent>

                                {/* Onglet Signature */}
                                <TabsContent value="signature">
                                    <SignatureTab dossier={dossier} can={can} />
                                </TabsContent>

                                {/* Onglet Formalités */}
                                <TabsContent value="formalites">
                                    <FormalitesTab
                                        dossier={dossier}
                                        reference={reference}
                                        etape={etape}
                                        can={can}
                                    />
                                </TabsContent>

                                {/* Onglet Expédition */}
                                <TabsContent value="expedition">
                                    <ExpeditionTab
                                        dossier={dossier}
                                        reference={reference}
                                        can={can}
                                    />
                                </TabsContent>

                                {/* Onglet Clôture */}
                                <TabsContent value="cloture">
                                    {/* Pas de onPreview : l'onglet Clôture déplie l'aperçu
                                        sous la pièce concernée plutôt que dans le panneau
                                        de pied de page, qui faisait perdre de vue quelle
                                        pièce on regardait sur un long inventaire. */}
                                    <ClotureTab dossier={dossier} can={can} />
                                </TabsContent>

                                {/* Onglet Facturation */}
                                <TabsContent value="facturation">
                                    <FacturationTab dossier={dossier} can={can} />
                                </TabsContent>
                            </Tabs>
                        </motion.div>

                    </div>
                </div>

                {/* Panneau latéral droit */}
                <div className="hidden xl:flex w-64 shrink-0 flex-col gap-4 p-4 border-l border-slate-200 overflow-y-auto">
                    <div>
                        <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Étape courante</h3>
                        <div className={cn(
                            'p-3 rounded-lg border',
                            ETAPE_META[dossier.etape?.value]?.badge ?? 'bg-slate-100 text-slate-600 border-slate-200'
                        )}>
                            <div className="text-sm font-semibold">{dossier.etape?.label}</div>
                            {dossier.reviseur && etape === 'revision' && (
                                <div className="text-xs text-slate-500 mt-1">Certificateur : {dossier.reviseur.name}</div>
                            )}
                        </div>
                    </div>
                    {dossier.echeance && (
                        <>
                            <Separator />
                            <div>
                                <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Échéance</h3>
                                <div className={cn(
                                    'flex items-center gap-2 text-sm',
                                    dossier.estEnRetard ? 'text-danger' : 'text-slate-600'
                                )}>
                                    <Clock className="h-3.5 w-3.5" />
                                    {dossier.echeance}
                                    {dossier.estEnRetard && <span className="text-xs font-medium">(dépassée)</span>}
                                </div>
                            </div>
                        </>
                    )}
                    {dossier.formalites?.filter(f => f.estUrgente).length > 0 && (
                        <>
                            <Separator />
                            <div>
                                <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Formalités urgentes</h3>
                                {dossier.formalites.filter(f => f.estUrgente).map(f => (
                                    <div key={f.id} className="flex items-start gap-2 p-2 rounded-lg bg-danger-bg border border-red-200 text-danger-text text-xs mb-2">
                                        <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5" />
                                        <span>{f.libelle || f.organismeLabel}</span>
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                    {dossier.notes && (
                        <>
                            <Separator />
                            <div>
                                <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Notes</h3>
                                <p className="text-sm text-slate-600 whitespace-pre-wrap">{dossier.notes}</p>
                            </div>
                        </>
                    )}
                    <Separator />
                    <button
                        onClick={() => setHistoriqueOpen(true)}
                        className="flex items-center gap-1.5 text-xs text-slate-500 hover:text-seal transition-colors"
                    >
                        <History className="h-3.5 w-3.5" />
                        Voir l'historique complet
                    </button>
                </div>

            </div>
            {/* Modals d'édition */}
            {can?.update && (
                <>
                    <ModalEditDossier
                        open={editDossierOpen}
                        onClose={() => setEditDossierOpen(false)}
                        dossier={dossier}
                        reviseurs={reviseurs}
                        formalistes={formalistes}
                        notaires={notaires}
                        canReassigner={can?.reassigner}
                    />
                    <ModalEditQuestionnaire
                        open={editQuestOpen}
                        onClose={() => setEditQuestOpen(false)}
                        dossier={dossier}
                    />
                    <ModalAjouterPersonne
                        open={ajoutPersonneOpen}
                        onClose={() => setAjoutPersonneOpen(false)}
                        reference={reference}
                    />
                </>
            )}

            {/* Historique — regroupe l'ancien onglet "Journal", accessible depuis
                l'en-tête et le panneau latéral plutôt que noyé dans la barre
                d'onglets (qui débordait sur les écrans étroits). */}
            <Dialog open={historiqueOpen} onOpenChange={setHistoriqueOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <History className="h-4 w-4 text-seal" />
                            Historique du dossier
                        </DialogTitle>
                    </DialogHeader>
                    <div className="max-h-[60vh] overflow-y-auto -mx-6 px-6">
                        {!dossier.journal?.length ? (
                            <div className="py-8 text-center text-sm text-slate-400">Aucune activité enregistrée</div>
                        ) : (
                            dossier.journal.map((entry, i) => (
                                <div key={i} className="flex gap-4 py-3 border-b border-slate-50 last:border-0">
                                    <div className="shrink-0">
                                        {entry.user ? (
                                            <div className="h-5 w-5 rounded-full bg-ink text-white flex items-center justify-center text-[8px] font-semibold">
                                                {entry.user.initiales ?? '?'}
                                            </div>
                                        ) : (
                                            <div className="h-5 w-5 rounded-full bg-slate-100" />
                                        )}
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <span className="text-slate-800 text-sm">{entry.action}</span>
                                        <div className="text-xs text-slate-400 mt-0.5">{entry.created_at}</div>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
