import React, { useState, useRef, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Check, Clock, AlertTriangle, FileText, Download, Eye,
    Building, Send, ClipboardCheck, Phone, MapPin,
    ArrowRight, CheckCircle2, Plus, Trash2, Upload, PenSquare, X,
    MailCheck, CheckCheck, Square, Pencil, RefreshCw, Zap,
    XCircle, Shield, Mail,
} from 'lucide-react';
import { QUESTIONNAIRES, TYPE_ACTE_CODE_MAP, getVisibleFields } from '@/data/questionnaires';
import { RepeatableGroup } from '@/Components/ui/RepeatableGroup';
import { DateField } from '@/components/ui/date-field';
import { NumberField } from '@/components/ui/number-field';
import { PhoneField } from '@/components/ui/phone-field';
import { ClientPicker } from '@/Components/ui/client-picker';
import { ModalNouveauClient } from '@/Components/ModalNouveauClient';
import { mapClientToPrefixedFields } from '@/lib/clientFields';
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
import DocumentPreviewModal from '@/Components/documents/DocumentPreviewModal';

const STEPS = [
    { id: 'initialisation', label: 'Initialisation', short: 'Init.' },
    { id: 'edition',        label: 'Édition actes',  short: 'Édition' },
    { id: 'revision',       label: 'Révision',        short: 'Révision' },
    { id: 'formalites',     label: 'Formalités',      short: 'Formalités' },
    { id: 'expedition',     label: 'Expédition',       short: 'Expédition' },
    { id: 'cloture',        label: 'Clôturé',          short: 'Clôturé' },
];

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

const EMPTY_DOC_FORM = { nom: '', type_document: 'acte_principal', version: '1.0' };

function ModalAjouterDocument({ open, onClose, reference }) {
    const [form, setForm] = useState(EMPTY_DOC_FORM);
    const [fichier, setFichier] = useState(null);
    const fileRef = useRef(null);

    const submit = (e) => {
        e.preventDefault();
        const fd = new FormData();
        fd.append('nom', form.nom);
        fd.append('type_document', form.type_document);
        fd.append('version', form.version);
        if (fichier) fd.append('fichier', fichier);
        router.post(`/dossiers/${reference}/documents`, fd, {
            onSuccess: () => { onClose(); setForm(EMPTY_DOC_FORM); setFichier(null); },
            onError: notifyValidationError,
            forceFormData: true,
        });
    };

    const f = (k) => (e) => setForm(p => ({ ...p, [k]: typeof e === 'string' ? e : e.target.value }));

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader><DialogTitle>Ajouter un document</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Nom du document</Label>
                        <Input value={form.nom} onChange={f('nom')} required placeholder="ex : Acte de vente" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Type</Label>
                            <Select value={form.type_document} onValueChange={f('type_document')}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {Object.entries(TYPE_DOC_LABELS).map(([v, l]) => (
                                        <SelectItem key={v} value={v}>{l}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1.5">
                            <Label>Version</Label>
                            <Input value={form.version} onChange={f('version')} placeholder="1.0" />
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Fichier <span className="text-slate-400 text-xs">(PDF, Word, Excel, ODT — optionnel)</span></Label>
                        <div
                            onClick={() => fileRef.current?.click()}
                            className={cn(
                                'border-2 border-dashed rounded-lg px-4 py-6 text-center cursor-pointer transition-colors',
                                fichier ? 'border-seal bg-seal-light/20' : 'border-slate-200 hover:border-slate-300'
                            )}
                        >
                            <Upload className="h-5 w-5 mx-auto mb-1 text-slate-300" />
                            <p className="text-xs text-slate-500">
                                {fichier ? fichier.name : 'Cliquer pour choisir un fichier'}
                            </p>
                            <input ref={fileRef} type="file" className="hidden"
                                accept=".pdf,.doc,.docx,.odt,.xlsx,.xls"
                                onChange={e => setFichier(e.target.files[0] || null)} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit">Ajouter</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

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
                                <Label>Réviseur</Label>
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

function InformationsTab({ dossier, can, onEditQuest }) {
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
    );
}

function DocumentsTab({ dossier, reference, etape, can, avancing, onSubmitRevision, onPreview }) {
    const [addOpen, setAddOpen] = useState(false);
    const [confirmState, setConfirmState] = useState(null);
    const [generating, setGenerating] = useState(false);
    const [regenerating, setRegenerating] = useState(new Set());

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
                        <>
                            <Button size="sm" variant="outline" className="h-8 gap-1" onClick={handleGenererModeles} disabled={generating}>
                                <RefreshCw className={cn('h-3.5 w-3.5', generating && 'animate-spin')} />
                                {generating ? 'Génération…' : 'Générer depuis les modèles'}
                            </Button>
                            <Button size="sm" variant="outline" className="h-8 gap-1" onClick={() => setAddOpen(true)}>
                                <Plus className="h-3.5 w-3.5" /> Nouveau document
                            </Button>
                        </>
                    )}
                </div>
            </CardHeader>
            <CardContent className="px-0 pb-0">
                {!dossier.documents?.length ? (
                    <div className="px-5 py-10 text-center">
                        <FileText className="h-10 w-10 text-slate-200 mx-auto mb-3" />
                        <p className="text-sm text-slate-400">Aucun document pour ce dossier.</p>
                        {can?.genererDocuments && (
                            <Button size="sm" variant="outline" className="mt-4" onClick={() => setAddOpen(true)}>
                                <Plus className="h-3.5 w-3.5" /> Ajouter un document
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
                                return (
                                    <tr key={doc.id}>
                                        <td className="pl-5">
                                            <div className="flex items-center gap-2">
                                                <FileText className="h-3.5 w-3.5 text-slate-400 shrink-0" />
                                                <div>
                                                    <span className="text-sm text-slate-800">{doc.nom}</span>
                                                    {doc.version && <span className="ml-1.5 text-[10px] text-slate-400">v{doc.version}</span>}
                                                </div>
                                            </div>
                                        </td>
                                        <td className="text-xs text-slate-500">{TYPE_DOC_LABELS[doc.type_document] ?? doc.type_document}</td>
                                        <td className="pr-5">
                                            <div className="flex items-center gap-1">
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
                                                    </>
                                                )}
                                                {can?.genererDocuments && (
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
                            {avancing ? 'Envoi…' : 'Soumettre à révision'}
                        </Button>
                    </div>
                )}
            </CardContent>
            <ModalAjouterDocument open={addOpen} onClose={() => setAddOpen(false)} reference={reference} />
            <ConfirmDialog
                open={!!confirmState}
                onClose={() => setConfirmState(null)}
                title={confirmState?.title ?? ''}
                description={confirmState?.description}
                confirmLabel={confirmState?.confirmLabel}
                variant={confirmState?.variant}
                onConfirm={confirmState?.onConfirm ?? (() => {})}
            />
        </Card>
    );
}

const revisionStatutColors = {
    en_attente: 'text-slate-500 bg-slate-50 border-slate-200',
    en_cours: 'text-warning-text bg-warning-bg border-amber-200',
    valide: 'text-success bg-success-bg border-green-200',
    renvoye: 'text-danger-text bg-danger-bg border-red-200',
};

const revisionStatutLabels = {
    en_attente: 'En attente',
    en_cours: 'En cours',
    valide: 'Validée',
    renvoye: 'Renvoyée',
};

const formaliteStatutColors = {
    a_deposer: 'bg-slate-50 text-slate-600 border-slate-200',
    depose: 'bg-blue-50 text-blue-700 border-blue-200',
    en_attente: 'bg-amber-50 text-amber-700 border-amber-200',
    retour_recu: 'bg-green-50 text-green-700 border-green-200',
    cloture: 'bg-slate-100 text-slate-500 border-slate-200',
};

const formaliteStatutLabels = {
    a_deposer: 'À déposer',
    depose: 'Déposé',
    en_attente: 'En attente',
    retour_recu: 'Retour reçu',
    cloture: 'Clôturé',
};

const ORGANISMES = [
    { value: 'apip',                  label: 'APIP' },
    { value: 'impots',                label: 'Direction des Impôts' },
    { value: 'conservation_fonciere', label: 'Conservation foncière' },
    { value: 'cnss',                  label: 'CNSS' },
];

const EMPTY_FORMALITE_FORM = { organisme: 'apip', libelle: '', montant_base: '', taux: '', echeance_at: '', pieces: [] };

function ModalAjouterFormalite({ open, onClose, reference }) {
    const [form, setForm] = useState(EMPTY_FORMALITE_FORM);
    const [nouvellePiece, setNouvellePiece] = useState('');

    const f = (k) => (e) => setForm(p => ({ ...p, [k]: typeof e === 'string' ? e : e.target.value }));

    const ajouterPiece = () => {
        const label = nouvellePiece.trim();
        if (!label) return;
        setForm(p => ({ ...p, pieces: [...p.pieces, { label }] }));
        setNouvellePiece('');
    };

    const retirerPiece = (i) => setForm(p => ({ ...p, pieces: p.pieces.filter((_, idx) => idx !== i) }));

    const submit = (e) => {
        e.preventDefault();
        router.post(`/dossiers/${reference}/formalites`, {
            organisme:    form.organisme,
            libelle:      form.libelle || null,
            statut:       'a_deposer',
            montant_base: form.montant_base || null,
            taux:         form.taux || null,
            echeance_at:  form.echeance_at || null,
            pieces:       form.pieces,
        }, {
            onSuccess: () => { onClose(); setForm(EMPTY_FORMALITE_FORM); },
            onError: notifyValidationError,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader><DialogTitle>Ajouter une formalité</DialogTitle></DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Organisme</Label>
                        <Select value={form.organisme} onValueChange={f('organisme')}>
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                                {ORGANISMES.map(o => (
                                    <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Libellé <span className="text-slate-400 text-xs">(précise la démarche, sinon le nom de l'organisme sera affiché)</span></Label>
                        <Input value={form.libelle} onChange={f('libelle')} placeholder="ex : Droits d'enregistrement" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Montant de base <span className="text-slate-400 text-xs">(GNF)</span></Label>
                            <NumberField value={form.montant_base} onValueChange={val => setForm(p => ({ ...p, montant_base: val }))} placeholder="0" />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Taux <span className="text-slate-400 text-xs">(ex : 0.05)</span></Label>
                            <NumberField decimals={4} value={form.taux} onValueChange={val => setForm(p => ({ ...p, taux: val }))} placeholder="0.05" />
                        </div>
                    </div>
                    <div className="space-y-1.5">
                        <Label>Échéance <span className="text-slate-400 text-xs">(optionnel)</span></Label>
                        <Input type="datetime-local" value={form.echeance_at} onChange={f('echeance_at')} />
                    </div>
                    <div className="space-y-1.5">
                        <Label>
                            Pièces à fournir
                            {form.pieces.length > 0 && (
                                <span className="ml-2 text-xs font-normal text-slate-400">
                                    {form.pieces.length} pièce{form.pieces.length > 1 ? 's' : ''}
                                </span>
                            )}
                        </Label>
                        <div className="rounded-lg border border-slate-200 bg-slate-50 p-2 min-h-[80px] flex flex-col gap-1.5">
                            {form.pieces.length === 0 && (
                                <p className="text-xs text-slate-400 text-center my-auto py-3">
                                    Aucune pièce ajoutée — saisissez ci-dessous
                                </p>
                            )}
                            {form.pieces.map((p, i) => (
                                <div key={i} className="flex items-center gap-2 bg-white rounded-md border border-slate-200 px-2.5 py-1.5 group">
                                    <span className="flex-1 text-sm text-slate-700">{p.label}</span>
                                    <button
                                        type="button"
                                        onClick={() => retirerPiece(i)}
                                        className="text-slate-300 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100"
                                    >
                                        <X className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            ))}
                        </div>
                        <div className="flex gap-2">
                            <Input
                                value={nouvellePiece}
                                onChange={e => setNouvellePiece(e.target.value)}
                                placeholder="ex : Copie CNI gérant, Statuts de la société…"
                                onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); ajouterPiece(); }}}
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={ajouterPiece}
                                disabled={!nouvellePiece.trim()}
                                className="shrink-0"
                            >
                                <Plus className="h-3.5 w-3.5 mr-1" /> Ajouter
                            </Button>
                        </div>
                        <p className="text-[11px] text-slate-400">Appuyez sur Entrée ou cliquez Ajouter</p>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit">Ajouter</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function FormaliteCardDossier({ f, peutGerer }) {
    const [showPieces, setShowPieces] = useState(false);
    const [confirmState, setConfirmState] = useState(null);
    const pieces  = f.pieces ?? [];
    const fournis = pieces.filter(p => p.est_fourni).length;
    const today   = new Date().toISOString().slice(0, 10);

    const patch = (data) =>
        router.patch(`/formalites/${f.id}`, data, { preserveState: true, onError: notifyValidationError });

    const handleDepose   = () => patch({ statut: 'depose',      depose_at: today });
    const handleRetour   = () => patch({ statut: 'retour_recu', retour_at: today });
    const handleCloture  = () => setConfirmState({
        title: 'Clôturer cette formalité ?',
        description: 'La formalité sera marquée comme clôturée.',
        confirmLabel: 'Clôturer',
        variant: 'default',
        onConfirm: () => patch({ statut: 'cloture' }),
    });
    const handleToggle   = (p) => peutGerer && patch({ pieces: [{ id: p.id, est_fourni: !p.est_fourni }] });
    const handleSupprimer = () => setConfirmState({
        title: `Supprimer la formalité ${f.libelle || f.organismeLabel} ?`,
        description: 'Cette action est irréversible.',
        confirmLabel: 'Supprimer',
        variant: 'destructive',
        onConfirm: () => router.delete(`/formalites/${f.id}`, { preserveState: true, onError: notifyValidationError }),
    });

    const isCloture  = f.statut === 'cloture';

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
            'border-l-4',
            f.estDepassee                                               && 'border-l-danger',
            !f.estDepassee && isCloture                                 && 'border-l-slate-300',
            !f.estDepassee && f.statut === 'retour_recu'                && 'border-l-success',
            !f.estDepassee && (f.statut === 'depose' || f.statut === 'en_attente') && 'border-l-blue-400',
            !f.estDepassee && f.statut === 'a_deposer'                  && 'border-l-slate-200',
            isCloture && 'opacity-60',
        )}>
            <CardContent className="p-4">
                {/* Ligne organisme + badge statut */}
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <div className="font-medium text-slate-800 flex items-center gap-1.5">
                            {f.libelle || f.organismeLabel}
                            {f.estDepassee && <AlertTriangle className="h-3.5 w-3.5 text-danger" />}
                        </div>
                        {f.libelle && f.libelle !== f.organismeLabel && (
                            <div className="text-xs text-slate-400 mt-0.5">{f.organismeLabel}</div>
                        )}
                        {f.montant_calcule > 0 && (
                            <div className="text-xs text-slate-500 font-ref mt-0.5">
                                {Number(f.montant_calcule).toLocaleString('fr-GN')} GNF
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
                    </div>
                    <span className={cn(
                        'shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border',
                        formaliteStatutColors[f.statut] ?? 'bg-slate-50 text-slate-500 border-slate-200'
                    )}>
                        {formaliteStatutLabels[f.statut] ?? f.statut}
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
                            <ul className="mt-2 space-y-1">
                                {pieces.map(p => (
                                    <li
                                        key={p.id}
                                        onClick={() => handleToggle(p)}
                                        className={cn(
                                            'flex items-center gap-2 px-1 py-0.5 rounded',
                                            peutGerer && 'cursor-pointer hover:bg-slate-50'
                                        )}
                                    >
                                        {p.est_fourni
                                            ? <CheckCircle2 className="h-3.5 w-3.5 text-success shrink-0" />
                                            : <Square className="h-3.5 w-3.5 text-slate-300 shrink-0" />}
                                        <span className={cn('text-xs', p.est_fourni ? 'line-through text-slate-400' : 'text-slate-700')}>
                                            {p.label}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}

                {/* Boutons d'action */}
                {peutGerer && (
                    <div className="mt-3 pt-2 border-t border-slate-100 flex items-center gap-2 flex-wrap">
                        {f.statut === 'a_deposer' && (
                            <Button size="sm" variant="outline" className="h-7 text-xs gap-1" onClick={handleDepose}>
                                <Upload className="h-3.5 w-3.5" /> Marquer déposé
                            </Button>
                        )}
                        {(f.statut === 'depose' || f.statut === 'en_attente') && (
                            <Button size="sm" variant="outline" className="h-7 text-xs gap-1 border-green-300 text-green-700 hover:bg-green-50"
                                onClick={handleRetour}>
                                <MailCheck className="h-3.5 w-3.5" /> Retour reçu
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
    const [addOpen, setAddOpen] = useState(false);
    const peutGerer = can?.gererFormalites;
    const cloturees = dossier.formalites?.filter(f => f.statut === 'cloture').length ?? 0;

    return (
        <div className="space-y-3">
            {/* En-tête */}
            <div className="flex items-center justify-between">
                <span className="text-sm text-slate-500">
                    {dossier.formalites?.length > 0
                        ? `${dossier.formalites.length} formalité(s) — ${cloturees} clôturée(s)`
                        : 'Aucune formalité enregistrée'}
                </span>
                {peutGerer && (
                    <Button size="sm" variant="outline" className="h-8" onClick={() => setAddOpen(true)}>
                        <Plus className="h-3.5 w-3.5" /> Ajouter une formalité
                    </Button>
                )}
            </div>

            {/* Liste vide */}
            {!dossier.formalites?.length && (
                <Card>
                    <CardContent className="p-6 text-center py-12">
                        <Building className="h-10 w-10 text-slate-200 mx-auto mb-3" />
                        <p className="text-slate-500 text-sm font-medium">Aucune formalité pour ce dossier</p>
                        {peutGerer ? (
                            <Button size="sm" variant="outline" className="mt-4" onClick={() => setAddOpen(true)}>
                                <Plus className="h-3.5 w-3.5" /> Ajouter la première formalité
                            </Button>
                        ) : (
                            <p className="text-xs text-slate-400 mt-1">Les formalités seront ajoutées par le formaliste.</p>
                        )}
                    </CardContent>
                </Card>
            )}

            {/* Cartes */}
            {dossier.formalites?.map(f => (
                <FormaliteCardDossier key={f.id} f={f} peutGerer={peutGerer} />
            ))}

            <ModalAjouterFormalite open={addOpen} onClose={() => setAddOpen(false)} reference={reference} />
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
        router.post(`/dossiers/${reference}/courriers/generer`, { modele_acte_id: modele.id }, {
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

    return (
        <div className="space-y-4">
            {peutGerer && modeles.length > 0 && (
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm">Lettres de transmission disponibles</CardTitle>
                        <p className="text-xs text-slate-400">Générées à partir des données du dossier — à relire avant envoi.</p>
                    </CardHeader>
                    <CardContent className="pt-0 flex flex-wrap gap-2">
                        {modeles.map(m => (
                            <Button
                                key={m.id}
                                size="sm"
                                variant="outline"
                                className="h-8 gap-1.5"
                                disabled={generatingId === m.id}
                                onClick={() => genererCourrier(m)}
                            >
                                <Send className={cn('h-3.5 w-3.5', generatingId === m.id && 'animate-pulse')} />
                                {generatingId === m.id ? 'Génération…' : m.nom}
                            </Button>
                        ))}
                    </CardContent>
                </Card>
            )}

            {courriers.length === 0 ? (
                <Card>
                    <CardContent className="p-6 text-center py-12">
                        <Mail className="h-10 w-10 text-slate-200 mx-auto mb-3" />
                        <p className="text-slate-500 text-sm font-medium">Aucun courrier généré pour ce dossier</p>
                        {modeles.length === 0 && (
                            <p className="text-xs text-slate-400 mt-1">Aucun modèle de courrier n'est configuré pour ce type d'acte.</p>
                        )}
                    </CardContent>
                </Card>
            ) : (
                <div className="space-y-2">
                    {courriers.map(c => (
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
            if (!dossier.reviseur) b.push("Aucun réviseur n'est assigné au dossier");
            return b;
        }
        case 'edition': {
            if (docs.length === 0) return ["Aucun document n'a été ajouté — au moins un acte est requis"];
            return [];
        }
        case 'revision': {
            if (revision?.statut === 'valide') return [];
            return [{
                renvoye:     "Révision renvoyée en correction — les points signalés doivent être corrigés",
                en_attente:  "Révision en attente — elle doit être évaluée par le réviseur",
                en_cours:    "Révision en cours — elle doit être validée pour continuer",
            }[revision?.statut] ?? "La révision doit être validée avant de passer aux formalités"];
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

function FacturationTab({ dossier }) {
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

    const facture = dossier.factures[0];

    return (
        <Card>
            <CardHeader className="pb-3 border-b border-slate-100 flex flex-row justify-between items-center">
                <div>
                    <CardTitle>Note de frais / Facture</CardTitle>
                    <p className="text-xs text-slate-500 mt-1">Générée automatiquement d'après les barèmes</p>
                </div>
                <div className="flex gap-2">
                    {/* Placeholder for future print/download actions */}
                    <Button variant="outline" size="sm" className="h-8 gap-1" title="Bientôt disponible">
                        <Download className="h-3.5 w-3.5" /> Exporter PDF
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="pt-5 space-y-6">
                {/* En-tête facture */}
                <div className="flex justify-between items-start">
                    <div>
                        <div className="font-medium text-slate-800 text-sm">{facture.objet}</div>
                        <div className="text-xs text-slate-500 mt-0.5">Assiette de calcul : {Number(facture.assiette_chiffres).toLocaleString('fr-GN')} GNF</div>
                    </div>
                    <div className="text-right">
                        <div className="text-sm font-semibold text-seal">N° {facture.note_numero}</div>
                        <div className="text-xs text-slate-500 mt-0.5">{new Date(facture.note_date).toLocaleDateString('fr-FR')}</div>
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
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {facture.lignes?.map((ligne, i) => (
                                <tr key={i} className="hover:bg-slate-50 transition-colors">
                                    <td className="px-4 py-3 text-slate-700">{ligne.designation}</td>
                                    <td className="px-4 py-3 text-slate-500 text-right">{ligne.quantite}</td>
                                    <td className="px-4 py-3 text-slate-600 text-right font-ref">
                                        {Number(ligne.montant).toLocaleString('fr-GN')}
                                    </td>
                                    <td className="px-4 py-3 text-slate-800 font-medium text-right font-ref">
                                        {(ligne.quantite * ligne.montant).toLocaleString('fr-GN')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot className="bg-slate-50 border-t border-slate-200">
                            <tr>
                                <td colSpan={3} className="px-4 py-3 text-right font-semibold text-slate-700">TOTAL À PAYER</td>
                                <td className="px-4 py-3 text-right font-bold text-seal font-ref text-base">
                                    {Number(facture.total_chiffres).toLocaleString('fr-GN')} GNF
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}

function WorkflowStepper({ currentStep }) {
    const currentIdx = STEPS.findIndex(s => s.id === currentStep);
    return (
        <div className="flex items-center gap-0 overflow-x-auto pb-1">
            {STEPS.map((step, i) => {
                const isDone = i < currentIdx;
                const isCurrent = i === currentIdx;
                const isPending = i > currentIdx;
                return (
                    <React.Fragment key={step.id}>
                        <div className="flex flex-col items-center gap-1.5 shrink-0 px-2">
                            <div className={cn(
                                'h-7 w-7 rounded-full flex items-center justify-center text-xs font-semibold transition-all',
                                isDone && 'bg-success text-white',
                                isCurrent && 'bg-seal text-white ring-2 ring-seal/30 ring-offset-1',
                                isPending && 'bg-slate-100 text-slate-400'
                            )}>
                                {isDone ? <Check className="h-3.5 w-3.5" /> : <span>{i + 1}</span>}
                            </div>
                            <span className={cn(
                                'text-[10px] font-medium whitespace-nowrap leading-tight text-center',
                                isDone && 'text-success',
                                isCurrent && 'text-seal',
                                isPending && 'text-slate-400'
                            )}>
                                <span className="hidden sm:block">{step.label}</span>
                                <span className="sm:hidden">{step.short}</span>
                            </span>
                        </div>
                        {i < STEPS.length - 1 && (
                            <div className={cn(
                                'flex-1 h-px min-w-[8px] max-w-[32px] mt-[-14px]',
                                i < currentIdx ? 'bg-success' : 'bg-slate-200'
                            )} />
                        )}
                    </React.Fragment>
                );
            })}
        </div>
    );
}

// Reconstruit l'état local des verdicts de révision (par document) à partir des points
// déjà sauvegardés (tableau [{point_id, etat, commentaire}]) — point_id = id du document.
function buildInitialRevisionEtats(documents, points) {
    const byId = {};
    (points ?? []).forEach(p => { byId[String(p.point_id)] = { etat: p.etat, commentaire: p.commentaire }; });
    const init = {};
    (documents ?? []).forEach(doc => {
        const saved = byId[String(doc.id)];
        init[String(doc.id)] = { etat: saved?.etat ?? null, commentaire: saved?.commentaire ?? '' };
    });
    return init;
}

export default function DossierShow() {
    const { dossier, can, reviseurs, formalistes, notaires } = usePage().props;
    const [activeTab, setActiveTab] = useState('informations');
    const [avancing, setAvancing] = useState(false);
    const [avancerErrors, setAvancerErrors] = useState([]);
    const [editDossierOpen, setEditDossierOpen] = useState(false);
    const [editQuestOpen, setEditQuestOpen] = useState(false);
    const [previewDoc, setPreviewDoc] = useState(null);
    const openPreview = (doc, previewUrl, downloadUrl) => setPreviewDoc({ doc, previewUrl, downloadUrl });

    // Révision — évaluation inline des documents (onglet "Révision")
    const [revisionEtats, setRevisionEtats] = useState(() => buildInitialRevisionEtats(dossier.documents, dossier.revision?.points));
    const [showRenvoyerDialog, setShowRenvoyerDialog] = useState(false);
    const [motifRenvoyer, setMotifRenvoyer] = useState('');
    const [savingRevision, setSavingRevision] = useState(false);
    const [validatingRevision, setValidatingRevision] = useState(false);
    const [renvoyantRevision, setRenvoyantRevision] = useState(false);

    const etape = dossier?.etape?.value ?? '';
    const reference = dossier?.reference ?? '';

    const blockers = can?.avancer ? getStepBlockers(dossier) : [];

    const actionContextuel = {
        revision:   { label: 'Voir la révision',   tab: 'revision',   variant: 'seal',    icon: ClipboardCheck },
        formalites: { label: 'Voir les formalités', tab: 'formalites', variant: 'default', icon: Building },
        expedition: { label: "Voir l'expédition",   tab: 'expedition', variant: 'default', icon: Mail },
    };
    const action = actionContextuel[etape];

    const revisionDocList     = dossier.documents ?? [];
    const revisionStatut      = dossier.revision?.statut ?? 'en_attente';
    const revisionEvalues     = Object.values(revisionEtats).filter(e => e.etat !== null).length;
    const revisionOk          = Object.values(revisionEtats).filter(e => e.etat === 'ok').length;
    const revisionACorriger   = Object.values(revisionEtats).filter(e => e.etat === 'a_corriger').length;
    const revisionACorrigerSansCommentaire = Object.values(revisionEtats)
        .some(e => e.etat === 'a_corriger' && !e.commentaire?.trim());
    const revisionPct         = revisionDocList.length > 0 ? Math.round((revisionEvalues / revisionDocList.length) * 100) : 0;
    const revisionCanValidate = revisionACorriger === 0 && revisionEvalues === revisionDocList.length && revisionDocList.length > 0;
    const revisionCanRenvoyer = revisionACorriger > 0 && !revisionACorrigerSansCommentaire;

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
                                                    <Badge variant="destructive" className="gap-1">
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
                                            {can?.avancer && (
                                                <Button
                                                    variant="outline"
                                                    onClick={() => handleAvancer()}
                                                    disabled={avancing || blockers.length > 0}
                                                    title={blockers.length > 0 ? 'Des conditions sont requises avant d\'avancer' : ''}
                                                >
                                                    <ArrowRight className="h-4 w-4" />
                                                    {avancing ? 'En cours…' : 'Avancer →'}
                                                </Button>
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
                                                        <div className="text-[10px] text-slate-400">Réviseur</div>
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

                        {/* Stepper workflow */}
                        <motion.div initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.05 }}>
                            <Card>
                                <CardContent className="p-4">
                                    <WorkflowStepper currentStep={etape} />
                                </CardContent>
                            </Card>
                        </motion.div>

                        {/* Onglets */}
                        <motion.div initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }}>
                            <Tabs value={activeTab} onValueChange={setActiveTab}>
                                <TabsList className="w-full justify-start overflow-x-auto">
                                    <TabsTrigger value="informations">Informations</TabsTrigger>
                                    <TabsTrigger value="documents">
                                        Actes & documents
                                        {dossier.documents?.length > 0 && (
                                            <span className="ml-1.5 text-[10px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full">
                                                {dossier.documents.length}
                                            </span>
                                        )}
                                    </TabsTrigger>
                                    <TabsTrigger value="revision">
                                        <span className="flex items-center gap-1.5">
                                            Révision
                                            {dossier.revision?.statut === 'en_cours' && (
                                                <span className="h-1.5 w-1.5 rounded-full bg-warning" />
                                            )}
                                        </span>
                                    </TabsTrigger>
                                    <TabsTrigger value="formalites">
                                        Formalités
                                        {dossier.formalites?.length > 0 && (
                                            <span className="ml-1.5 text-[10px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full">
                                                {dossier.formalites.length}
                                            </span>
                                        )}
                                    </TabsTrigger>
                                    <TabsTrigger value="expedition">
                                        Expédition
                                        {dossier.courriers?.length > 0 && (
                                            <span className="ml-1.5 text-[10px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full">
                                                {dossier.courriers.length}
                                            </span>
                                        )}
                                    </TabsTrigger>
                                    <TabsTrigger value="parties">
                                        Parties
                                        {dossier.parties?.length > 0 && (
                                            <span className="ml-1.5 text-[10px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full">
                                                {dossier.parties.length}
                                            </span>
                                        )}
                                    </TabsTrigger>
                                    <TabsTrigger value="facturation">
                                        Facturation
                                        {dossier.factures?.length > 0 && (
                                            <span className="ml-1.5 text-[10px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full">
                                                {dossier.factures.length}
                                            </span>
                                        )}
                                    </TabsTrigger>
                                    <TabsTrigger value="journal">Journal</TabsTrigger>
                                </TabsList>

                                {/* Onglet Informations */}
                                <TabsContent value="informations">
                                    {/* Bloc questionnaire */}
                                    <InformationsTab
                                        dossier={dossier}
                                        can={can}
                                        onEditQuest={() => setEditQuestOpen(true)}
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
                                        onSubmitRevision={() => handleAvancer(() => setActiveTab('revision'))}
                                        onPreview={openPreview}
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
                                                    <h3 className="font-serif text-heading text-ink">Révision des documents</h3>
                                                    {dossier.revision?.reviseur && (
                                                        <p className="text-sm text-slate-500 mt-0.5">Réviseur : {dossier.revision.reviseur.name}</p>
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

                                            return (
                                                <Card key={doc.id} className={cn(
                                                    'transition-colors border',
                                                    isOk  && 'border-success/40 bg-success-bg/20',
                                                    isNok && 'border-danger/30 bg-danger-bg/30',
                                                    !isOk && !isNok && 'border-slate-200',
                                                )}>
                                                    <CardContent className="p-5">
                                                        <div className="flex items-start justify-between gap-3">
                                                            <div className="flex items-start gap-3 min-w-0">
                                                                <div className={cn(
                                                                    'h-9 w-9 rounded-lg flex items-center justify-center shrink-0 mt-0.5',
                                                                    isOk ? 'bg-success/10' : isNok ? 'bg-danger/10' : 'bg-slate-100'
                                                                )}>
                                                                    {isOk ? (
                                                                        <CheckCircle2 className="h-4.5 w-4.5 text-success" />
                                                                    ) : isNok ? (
                                                                        <XCircle className="h-4.5 w-4.5 text-danger" />
                                                                    ) : (
                                                                        <FileText className="h-4.5 w-4.5 text-slate-400" />
                                                                    )}
                                                                </div>
                                                                <div className="min-w-0">
                                                                    <p className="font-medium text-slate-800 leading-snug">{doc.nom}</p>
                                                                    <Badge variant="outline" className="mt-1">
                                                                        {TYPE_DOC_LABELS[doc.type_document] ?? doc.type_document}
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

                                                        {(isNok || etatDoc.commentaire) && (
                                                            <div className="mt-3">
                                                                {isNok && (
                                                                    <span className="text-xs text-slate-400">
                                                                        Commentaire <span className="text-danger">*</span> — obligatoire pour un document à corriger
                                                                    </span>
                                                                )}
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

                                                        {!can?.reviser && etatDoc.etat && (
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
                                                        Renvoyez le dossier en édition pour que le rédacteur effectue les corrections.
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
                                                {validatingRevision ? 'Validation…' : revisionStatut === 'valide' ? 'Révision validée ✓' : 'Valider la révision'}
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

                                {/* Onglet Parties */}
                                <TabsContent value="parties">
                                    {!dossier.parties?.length ? (
                                        <Card>
                                            <CardContent className="p-6 text-center py-12">
                                                <p className="text-slate-400 text-sm">Aucune partie enregistrée.</p>
                                            </CardContent>
                                        </Card>
                                    ) : (
                                        <div className="space-y-3">
                                            {dossier.parties.map((partie, i) => (
                                                <Card key={i}>
                                                    <CardContent className="p-4">
                                                        <div className="flex items-start gap-4">
                                                            <Avatar className="h-10 w-10 shrink-0">
                                                                <AvatarFallback className="bg-ink text-white text-sm">
                                                                    {partie.initiales ?? partie.nom?.split(' ').map(n => n[0]).join('').slice(0, 2)}
                                                                </AvatarFallback>
                                                            </Avatar>
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
                                                        </div>
                                                    </CardContent>
                                                </Card>
                                            ))}
                                        </div>
                                    )}
                                </TabsContent>

                                {/* Onglet Facturation */}
                                <TabsContent value="facturation">
                                    <FacturationTab dossier={dossier} />
                                </TabsContent>

                                {/* Onglet Journal */}
                                <TabsContent value="journal">
                                    <Card>
                                        <CardHeader className="pb-3">
                                            <CardTitle>Journal d'activité</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-0 px-0 pb-0">
                                            {!dossier.journal?.length ? (
                                                <div className="px-5 py-8 text-center text-sm text-slate-400">Aucune activité enregistrée</div>
                                            ) : (
                                                dossier.journal.map((entry, i) => (
                                                    <div key={i} className="flex gap-4 px-5 py-3 border-b border-slate-50 last:border-0">
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
                                        </CardContent>
                                    </Card>
                                </TabsContent>
                            </Tabs>
                        </motion.div>

                    </div>
                </div>

                {/* Panneau latéral droit */}
                <div className="hidden xl:flex w-64 shrink-0 flex-col gap-4 p-4 border-l border-slate-200 overflow-y-auto">
                    <div>
                        <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Étape courante</h3>
                        <div className="p-3 rounded-lg bg-seal/5 border border-seal/20">
                            <div className="text-sm font-medium text-seal">{dossier.etape?.label}</div>
                            {dossier.reviseur && etape === 'revision' && (
                                <div className="text-xs text-slate-500 mt-1">Réviseur : {dossier.reviseur.name}</div>
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
                </>
            )}
            {previewDoc && (
                <DocumentPreviewModal
                    doc={previewDoc.doc}
                    previewUrl={previewDoc.previewUrl}
                    downloadUrl={previewDoc.downloadUrl}
                    onClose={() => setPreviewDoc(null)}
                />
            )}
        </AppLayout>
    );
}
