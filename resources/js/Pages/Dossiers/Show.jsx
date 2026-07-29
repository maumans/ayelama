import React, { useState, useRef, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Check, Clock, AlertTriangle, FileText, Download, Eye,
    Building, Send, ClipboardCheck, Phone, MapPin,
    ArrowRight, CheckCircle2, Plus, Trash2, Upload, PenSquare, X,
    MailCheck, CheckCheck, Square, Pencil, RefreshCw, Zap,
    XCircle, Shield, Mail, Lock, Banknote, Wallet, Receipt,
    History, Users, Info,
} from 'lucide-react';
import { STATUT_META as FORMALITE_STATUT_META, organismeBadgeClass, organismeShortLabel } from '@/data/formaliteStatuts';
import { STATUT_META as REVISION_STATUT_META } from '@/data/revisionStatuts';
import { ETAPE_META, ETAPE_ORDER } from '@/data/etapeMeta';
import { ModalDepotFormalite } from '@/Components/Formalites/ModalDepotFormalite';
import { ModalRetourFormalite } from '@/Components/Formalites/ModalRetourFormalite';
import { PieceGedRow } from '@/Components/Formalites/PieceGedRow';
import { ModalEnregistrerPaiement } from '@/Components/Facturation/ModalEnregistrerPaiement';
import { ModalLigneFacture } from '@/Components/Facturation/ModalLigneFacture';
import { QUESTIONNAIRES, TYPE_ACTE_CODE_MAP, getVisibleFields } from '@/data/questionnaires';
import { RepeatableGroup } from '@/Components/ui/RepeatableGroup';
import { DateField } from '@/components/ui/date-field';
import { NumberField } from '@/components/ui/number-field';
import { PhoneField } from '@/components/ui/phone-field';
import { ClientPicker } from '@/Components/ui/client-picker';
import { ModalNouveauClient } from '@/Components/ModalNouveauClient';
import { mapClientToPrefixedFields, buildPartieFields } from '@/lib/clientFields';
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
import DocumentInlinePreview from '@/Components/documents/DocumentInlinePreview';

const docStatutConfig = {
    a_editer: { label: 'À éditer', color: 'text-slate-500 bg-slate-50 border-slate-200' },
    edite:    { label: 'Édité',    color: 'text-blue-600 bg-blue-50 border-blue-200' },
};

const TYPE_DOC_LABELS = {
    acte_principal: 'Acte principal',
    page_garde:     'Page de garde',
    attestation:    'Attestation',
    declaration:    'Déclaration',
    dnsv:           'DNSV',
    insertion:      'Insertion au JORG',
    rccm:           'RCCM',
    note_frais:     'Note de frais',
    bordereau:      'Bordereau / Tableau',
    annexe:         'Annexe',
    procedure:      'Procédure',
    lettre:         'Lettre / Transmission',
    recepisse:      'Récépissé',
};

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

function ModalEditQuestionnaire({ open, onClose, dossier }) {
    const questKey  = TYPE_ACTE_CODE_MAP[dossier.typeActe?.code];
    const fields    = QUESTIONNAIRES[questKey] ?? [];
    const [formValues, setFormValues] = useState(dossier.questionnaire ?? {});
    const [clientLinks, setClientLinks] = useState(() => initialClientLinks(fields, dossier.parties));
    const [creatingClientForGroup, setCreatingClientForGroup] = useState(null);

    useEffect(() => {
        if (open) {
            setFormValues(dossier.questionnaire ?? {});
            setClientLinks(initialClientLinks(fields, dossier.parties));
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

    const submit = (e) => {
        e.preventDefault();
        router.patch(`/dossiers/${dossier.reference}/questionnaire`, {
            donnees: formValues,
            parties: buildPartiesPayload(fields, formValues, clientLinks),
            managedRoles: getManagedClientRoles(fields),
        }, { onSuccess: () => onClose(), onError: notifyValidationError });
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
                                        <ClientPicker
                                            placeholder={`Rechercher un client existant (${group.name})…`}
                                            linked={clientLinks[group.clientRole] ?? null}
                                            onSelect={(client) => applyClientToSection(group, client)}
                                            onUnlink={() => unlinkClientFromSection(group.clientRole)}
                                            onCreateNew={() => setCreatingClientForGroup(group)}
                                        />
                                    </div>
                                )}
                                {group.fields.map(field => (
                                    <div key={field.id} className="space-y-1.5">
                                        {field.type !== 'repeatable' && field.type !== 'checkbox' && field.type !== 'checkbox_required' && (
                                            <Label htmlFor={`qedit-${field.id}`}>
                                                {field.label}
                                                {field.required && <span className="text-danger ml-1">*</span>}
                                            </Label>
                                        )}
                                        {field.type === 'repeatable' ? (
                                            <>
                                                <p className="text-sm font-medium text-slate-700 mb-1">
                                                    {field.label}
                                                    {field.required && <span className="text-danger ml-1">*</span>}
                                                </p>
                                                <RepeatableGroup
                                                    fieldDef={field}
                                                    value={formValues[field.id] ?? []}
                                                    onChange={val => setFormValues(p => ({ ...p, [field.id]: val }))}
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
                                        ) : field.type === 'tel' ? (
                                            <PhoneField
                                                id={`qedit-${field.id}`}
                                                placeholder={field.placeholder}
                                                value={formValues[field.id] || ''}
                                                onValueChange={val => setFormValues(p => ({ ...p, [field.id]: val }))}
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

function InformationsTab({ dossier, can, onEditQuest, managedRoles, onAjouterPersonne, onSupprimerPersonne }) {
    const [pieceModalPartie, setPieceModalPartie] = useState(null);
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
            <Card>
                <CardHeader className="pb-3 flex flex-row items-center justify-between">
                    <CardTitle>Fiche dossier — {dossier.typeActe?.label}</CardTitle>
                    {can?.update && (
                        <Button size="sm" variant="outline" className="h-8 gap-1.5" onClick={onEditQuest}>
                            <PenSquare className="h-3.5 w-3.5" />
                            Modifier le questionnaire
                        </Button>
                    )}
                </CardHeader>
                <CardContent>
                    {renderQuestContent()}
                </CardContent>
            </Card>

            <Card>
                <CardHeader className="pb-3 flex flex-row items-center justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <Users className="h-4 w-4 text-seal" />
                        Personnes associées au dossier
                    </CardTitle>
                    {can?.update && (
                        <Button size="sm" variant="outline" className="h-8 gap-1.5" onClick={onAjouterPersonne}>
                            <Plus className="h-3.5 w-3.5" /> Ajouter une personne
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="space-y-3">
                    {!dossier.parties?.length ? (
                        <p className="text-sm text-slate-400 italic py-2">Aucune partie enregistrée.</p>
                    ) : dossier.parties.map((partie, i) => {
                        const estLibre = !managedRoles.includes(partie.role);
                        return (
                            <Card key={i}>
                                <CardContent className="p-4">
                                    <div className="flex items-start gap-4">
                                        <PartiePhotoAvatar partie={partie} canEdit={can?.genererDocuments} />
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
                                        {can?.update && estLibre && (
                                            <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-danger shrink-0"
                                                onClick={() => onSupprimerPersonne(partie)} title="Retirer cette personne">
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                    <PartiePiecesList
                                        partie={partie}
                                        canEdit={can?.genererDocuments}
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

function ClotureItemRow({ item, nom, onPreview, peutDeposer }) {
    return (
        <div className="flex items-center justify-between gap-3 py-3">
            <div className="min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                    <FileText className="h-3.5 w-3.5 text-slate-300 shrink-0" />
                    <span className="text-sm font-medium text-slate-800 truncate">{nom}</span>
                    {item.est_signe_cachete ? (
                        <Badge variant="outline" className="bg-success-bg text-success-text border-success/30 text-[10px] gap-1">
                            <Lock className="h-2.5 w-2.5" /> Signé/cacheté
                        </Badge>
                    ) : (
                        <Badge variant="outline" className="bg-warning-bg text-warning-text border-warning/30 text-[10px]">
                            En attente
                        </Badge>
                    )}
                </div>
                {item.est_signe_cachete && item.signe_cachete_par && (
                    <p className="text-[10px] text-slate-400 mt-0.5 ml-5">
                        Par {item.signe_cachete_par} le {item.signe_cachete_at}
                    </p>
                )}
            </div>
            <div className="flex items-center gap-1 shrink-0">
                {item.has_file && (
                    <>
                        <Button variant="ghost" size="icon-sm" title="Prévisualiser"
                            onClick={() => onPreview({ id: item.id, nom, chemin_fichier: item.chemin_fichier, version: item.version }, item.url_preview, item.url_download)}>
                            <Eye className="h-3.5 w-3.5" />
                        </Button>
                        <Button variant="ghost" size="icon-sm" asChild title="Télécharger">
                            <a href={item.url_download} download><Download className="h-3.5 w-3.5" /></a>
                        </Button>
                    </>
                )}
                {!item.est_signe_cachete && peutDeposer && (
                    <UploadSigneButton doc={item} />
                )}
            </div>
        </div>
    );
}

function ClotureTab({ dossier, can, onPreview }) {
    const documentsRequis = (dossier.documents ?? []).filter(d => d.est_requis);
    const courriersRequis = (dossier.courriers ?? []).filter(c => c.est_requis);
    const total  = documentsRequis.length + courriersRequis.length;
    const signes = documentsRequis.filter(d => d.est_signe_cachete).length + courriersRequis.filter(c => c.est_signe_cachete).length;

    return (
        <div className="space-y-4">
            {total === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <Lock className="h-10 w-10 text-slate-200 mb-3" />
                        <p className="text-sm text-slate-500 font-medium">Aucun document ou courrier obligatoire</p>
                        <p className="text-xs text-slate-400 mt-1 max-w-sm">
                            Rien n'est configuré comme obligatoire à la clôture pour ce type d'acte
                            (Paramètres &gt; Clôture).
                        </p>
                    </CardContent>
                </Card>
            ) : (
                <div className={cn(
                    'px-4 py-3 rounded-lg border flex items-center gap-2 text-sm',
                    signes === total ? 'bg-success-bg border-success/30 text-success-text' : 'bg-warning-bg border-warning/30 text-warning-text'
                )}>
                    <Lock className="h-4 w-4 shrink-0" />
                    {signes}/{total} élément{total > 1 ? 's' : ''} obligatoire{total > 1 ? 's' : ''} signé{total > 1 ? 's' : ''}/cacheté{total > 1 ? 's' : ''}
                    {signes < total && ' — requis avant de clôturer le dossier'}
                </div>
            )}

            {documentsRequis.length > 0 && (
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm">Documents requis</CardTitle>
                    </CardHeader>
                    <CardContent className="pt-0 divide-y divide-slate-100">
                        {documentsRequis.map(doc => (
                            <ClotureItemRow key={`doc-${doc.id}`} item={doc} nom={doc.nom} onPreview={onPreview} peutDeposer={can?.cloturerDocuments} />
                        ))}
                    </CardContent>
                </Card>
            )}

            {courriersRequis.length > 0 && (
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm">Courriers de transmission requis</CardTitle>
                    </CardHeader>
                    <CardContent className="pt-0 divide-y divide-slate-100">
                        {courriersRequis.map(c => (
                            <ClotureItemRow key={`courrier-${c.id}`} item={c} nom={c.objet} onPreview={onPreview} peutDeposer={can?.cloturerDocuments} />
                        ))}
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function DocumentsTab({ dossier, reference, etape, can, avancing, onSubmitRevision, onPreview, onEditQuest }) {
    const [confirmState, setConfirmState] = useState(null);
    const [generating, setGenerating] = useState(false);
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
                        {can?.update && (
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
                                    <tr key={doc.id}>
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
                                        <td className="text-xs text-slate-500">{TYPE_DOC_LABELS[doc.categorie] ?? doc.categorie}</td>
                                        <td className="pr-5">
                                            <div className="flex items-center gap-1 justify-end flex-wrap">
                                                {doc.chemin_fichier && (
                                                    <>
                                                        <Button variant="ghost" size="icon-sm" title="Prévisualiser"
                                                            onClick={() => onPreview(doc)}>
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
        </Card>
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

    const handleCloture  = () => setConfirmState({
        title: 'Clôturer cette formalité ?',
        description: 'La formalité sera marquée comme clôturée.',
        confirmLabel: 'Clôturer',
        variant: 'default',
        onConfirm: () => patch({ statut: 'cloture' }),
    });
    const handleTogglePreview = (p) => setPreviewPieceId(id => id === p.id ? null : p.id);
    const handleSupprimer = () => setConfirmState({
        title: `Supprimer la formalité ${f.libelle || f.organismeLabel} ?`,
        description: 'Cette action est irréversible.',
        confirmLabel: 'Supprimer',
        variant: 'destructive',
        onConfirm: () => router.delete(`/formalites/${f.id}`, { preserveState: true, onError: notifyValidationError }),
    });

    const isCloture  = f.statut === 'cloture';
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
            !f.estDepassee && isCloture                                 && 'border-l-slate-300',
            !f.estDepassee && f.statut === 'retour_recu'                && 'border-l-success',
            !f.estDepassee && (f.statut === 'depose' || f.statut === 'en_attente') && 'border-l-blue-400',
            !f.estDepassee && f.statut === 'a_deposer'                  && 'border-l-slate-200',
            !f.estDepassee && f.statut === 'rejete'                     && 'border-l-danger',
            isCloture && 'opacity-60',
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

                {/* Pièces collapsible */}
                {pieces.length > 0 && (
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
                                <Upload className="h-3.5 w-3.5" /> {f.statut === 'rejete' ? 'Redéposer' : 'Préparer dépôt'}
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
                        {f.statut === 'retour_recu' && (
                            <Button size="sm" variant="outline" className="h-7 text-xs gap-1 border-green-300 text-green-700 hover:bg-green-50"
                                onClick={handleCloture}>
                                <CheckCheck className="h-3.5 w-3.5" /> Clôturer
                            </Button>
                        )}
                        {isCloture && (
                            <span className="flex items-center gap-1 text-xs text-slate-400">
                                <Check className="h-3.5 w-3.5 text-success" /> Clôturée
                            </span>
                        )}
                        <div className="flex-1" />
                        {!isCloture && (
                            <Button size="icon-sm" variant="ghost" className="text-slate-300 hover:text-red-500"
                                onClick={handleSupprimer} title="Supprimer cette formalité">
                                <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                        )}
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

    const termine  = formalites.filter(f => f.statut === 'retour_recu' || f.statut === 'cloture').length;
    const retard   = formalites.filter(f => f.estDepassee && f.statut !== 'retour_recu' && f.statut !== 'cloture').length;
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

function ExpeditionTab({ dossier, reference, can, onPreview }) {
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
                            return (
                                <div key={m.id} className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 p-3">
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
                                                    variant="ghost" size="icon-sm" title="Aperçu"
                                                    onClick={() => onPreview({ id: genere.id, nom: genere.objet, chemin_fichier: genere.chemin_fichier }, genere.url_preview, genere.url_download)}
                                                >
                                                    <Eye className="h-3.5 w-3.5" />
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
                                                variant="ghost" size="icon-sm" title="Aperçu"
                                                onClick={() => onPreview({ id: c.id, nom: c.objet, chemin_fichier: c.chemin_fichier }, c.url_preview, c.url_download)}
                                            >
                                                <Eye className="h-3.5 w-3.5" />
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
                        </Card>
                    ))}
                </div>
            )}
        </div>
    );
}

function getStepBlockers(dossier) {
    const etape = dossier?.etape?.value;
    const docs = dossier?.documents ?? [];
    const formalites = dossier?.formalites ?? [];
    const revision = dossier?.revision;
    const modelesCourrier = dossier?.courrierModelesApplicables ?? [];
    const courriers = dossier?.courriers ?? [];

    switch (etape) {
        case 'initialisation': {
            const b = [];
            if (!dossier.objet?.trim()) b.push("L'objet du dossier n'est pas renseigné");
            if (!dossier.notaire) b.push("Aucun notaire n'est assigné au dossier");
            if (!dossier.reviseur) b.push("Aucun certificateur n'est assigné au dossier");
            return b;
        }
        case 'edition': {
            if (docs.length === 0) return ["Aucun document n'a été ajouté — au moins un acte est requis"];
            return [];
        }
        case 'revision': {
            if (revision?.statut === 'valide') return [];
            return [{
                renvoye:     "Certification renvoyée en correction — les points signalés doivent être corrigés",
                en_attente:  "Certification en attente — elle doit être évaluée par le certificateur",
                en_cours:    "Certification en cours — elle doit être validée pour continuer",
            }[revision?.statut] ?? "La certification doit être validée avant de passer aux formalités"];
        }
        case 'formalites': {
            const nonClos = formalites.filter(f => f.statut !== 'cloture');
            if (nonClos.length > 0) return [`${nonClos.length} formalité(s) non clôturée(s) : ${nonClos.map(f => f.libelle || f.organismeLabel || f.organisme).join(', ')}`];
            return [];
        }
        case 'expedition': {
            if (modelesCourrier.length === 0) return [];
            const envoye = courriers.some(c => c.type === 'transmission' && c.statut === 'envoye');
            if (!envoye) return ["Au moins une lettre de transmission doit être générée et marquée « envoyée »"];
            return [];
        }
        default:
            return [];
    }
}

const fmtGNF = (n) => Number(n || 0).toLocaleString('fr-FR');

function FacturationTab({ dossier, can, onPreview }) {
    const [paiementOpen, setPaiementOpen] = useState(false);
    const [paiementEnEdition, setPaiementEnEdition] = useState(null);
    const [paiementASupprimer, setPaiementASupprimer] = useState(null);
    const [ligneOpen, setLigneOpen] = useState(false);
    const [ligneEnEdition, setLigneEnEdition] = useState(null);
    const [ligneASupprimer, setLigneASupprimer] = useState(null);
    const peutGerer = !!can?.gererFacturation;

    const consulterRecu = (recu) => {
        onPreview({ id: recu.id, nom: `Reçu ${recu.numero}`, chemin_fichier: 'recu.pdf' }, recu.url_apercu, recu.url_telechargement);
    };

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
                    <div className={cn('text-lg font-semibold font-ref mt-0.5', soldeRestant > 0 ? 'text-warning-text' : 'text-success')}>
                        {fmtGNF(Math.abs(soldeRestant))} GNF
                    </div>
                </CardContent></Card>
            </div>

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
                            <Button variant="seal" size="sm" className="h-8 gap-1" onClick={() => setPaiementOpen(true)}>
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
                                    <tr key={p.id}>
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
                                                    <Button variant="ghost" size="icon-sm" className="h-7 w-7 text-slate-400 hover:text-seal" title={`Consulter le reçu ${p.recu.numero}`} onClick={() => consulterRecu(p.recu)}>
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
    initialisation: 'informations',
    edition:        'documents',
    revision:       'revision',
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
const TAB_STAGE = {
    documents:   'edition',
    revision:    'revision',
    formalites:  'formalites',
    expedition:  'expedition',
    cloture:     'cloture',
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
        cloture:    (dossier.documents ?? []).some(d => d.est_signe_cachete) || (dossier.courriers ?? []).some(c => c.est_signe_cachete),
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
    const { dossier, can, reviseurs, formalistes, notaires } = usePage().props;
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
    const [previewDoc, setPreviewDoc] = useState(null);
    const openPreview = (doc, previewUrl, downloadUrl) => setPreviewDoc({ doc, previewUrl, downloadUrl });
    const previewRef = useRef(null);

    // Ferme l'aperçu en cours quand on change d'onglet — sinon il resterait affiché
    // sous un onglet sans rapport avec le document consulté.
    useEffect(() => { setPreviewDoc(null); }, [activeTab]);

    // Amène le panneau d'aperçu dans le champ de vision dès son ouverture, sans
    // quoi l'utilisateur devrait défiler manuellement pour le voir apparaître.
    useEffect(() => {
        if (previewDoc) previewRef.current?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }, [previewDoc]);

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
                            <div className="flex items-center gap-2 min-w-0">
                                <span className={cn(
                                    'inline-flex items-center text-xs font-semibold px-2.5 py-1 rounded-full border shrink-0',
                                    ETAPE_META[dossier.etape?.value]?.badge ?? 'bg-slate-100 text-slate-600 border-slate-200'
                                )}>
                                    {dossier.etape?.label}
                                </span>
                                {(avancerErrors.length > 0 || blockers.length > 0) && (
                                    <span className="text-xs text-amber-600 flex items-center gap-1 shrink-0">
                                        <AlertTriangle className="h-3 w-3" />
                                        {(avancerErrors.length > 0 ? avancerErrors : blockers).length} condition(s) requise(s)
                                    </span>
                                )}
                            </div>
                            <Button
                                variant="seal"
                                onClick={() => handleAvancer()}
                                disabled={avancing || blockers.length > 0}
                                title={blockers.length > 0 ? 'Des conditions sont requises avant d\'avancer' : ''}
                            >
                                <ArrowRight className="h-4 w-4" />
                                {avancing ? 'En cours…' : (dossier.etapeSuivante ? `${dossier.etapeSuivante.label} →` : 'Avancer →')}
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
                                                        {(avancerErrors.length > 0 ? avancerErrors : blockers).map((msg, i) => (
                                                            <li key={i} className="text-xs text-amber-700 flex items-start gap-1.5">
                                                                <span className="mt-1.5 h-1 w-1 rounded-full bg-amber-400 shrink-0" />
                                                                {msg}
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
                                    <TabsTrigger value="cloture" className={cn(tabPasEncoreAtteint('cloture', etape, dossier) && 'opacity-40')}>
                                        <span className="flex items-center gap-1.5">
                                            <Lock className="h-3.5 w-3.5" />
                                            Clôture
                                            {(() => {
                                                const restants = (dossier.documents ?? []).filter(d => d.est_requis && !d.est_signe_cachete).length
                                                    + (dossier.courriers ?? []).filter(c => c.est_requis && !c.est_signe_cachete).length;
                                                return restants > 0 ? (
                                                    <span className="text-[10px] bg-warning-bg text-warning-text px-1.5 py-0.5 rounded-full">
                                                        {restants}
                                                    </span>
                                                ) : null;
                                            })()}
                                            {etape === 'cloture' && activeTab !== 'cloture' && (
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
                                        onPreview={openPreview}
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
                                                                        {TYPE_DOC_LABELS[doc.categorie] ?? doc.categorie}
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
                                                                        onClick={() => openPreview(doc)}
                                                                        className="flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium border border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50 transition-all"
                                                                    >
                                                                        <Eye className="h-3.5 w-3.5" />
                                                                        Aperçu
                                                                    </button>
                                                                </div>
                                                            )}
                                                        </div>

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
                                        onPreview={openPreview}
                                    />
                                </TabsContent>

                                {/* Onglet Clôture */}
                                <TabsContent value="cloture">
                                    <ClotureTab dossier={dossier} can={can} onPreview={openPreview} />
                                </TabsContent>

                                {/* Onglet Facturation */}
                                <TabsContent value="facturation">
                                    <FacturationTab dossier={dossier} can={can} onPreview={openPreview} />
                                </TabsContent>
                            </Tabs>
                        </motion.div>

                        <AnimatePresence>
                            {previewDoc && (
                                <DocumentInlinePreview
                                    ref={previewRef}
                                    doc={previewDoc.doc}
                                    previewUrl={previewDoc.previewUrl}
                                    downloadUrl={previewDoc.downloadUrl}
                                    onClose={() => setPreviewDoc(null)}
                                />
                            )}
                        </AnimatePresence>

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
