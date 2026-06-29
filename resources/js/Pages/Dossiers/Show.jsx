import React, { useState, useRef, useEffect } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Check, Clock, AlertTriangle, FileText, Download, Eye,
    Building, User, Calendar, ChevronRight, Send, ClipboardCheck,
    PenLine, Truck, Archive, Phone, MapPin,
    ArrowRight, CheckCircle2, Plus, Trash2, Upload, PenSquare, X,
    MailCheck, CheckCheck, Square,
} from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { cn } from '@/lib/utils';

const STEPS = [
    { id: 'initialisation', label: 'Initialisation', short: 'Init.' },
    { id: 'edition', label: 'Édition actes', short: 'Édition' },
    { id: 'revision', label: 'Révision', short: 'Révision' },
    { id: 'signature_client', label: 'Signature client', short: 'Sig. client' },
    { id: 'signature_notaire', label: 'Signature notaire', short: 'Sig. notaire' },
    { id: 'formalites', label: 'Formalités', short: 'Formalités' },
    { id: 'expedition', label: 'Expédition', short: 'Expédition' },
    { id: 'cloture', label: 'Clôturé', short: 'Clôturé' },
];

const docStatutConfig = {
    a_editer: { label: 'À éditer', color: 'text-slate-500 bg-slate-50 border-slate-200' },
    edite: { label: 'Édité', color: 'text-blue-600 bg-blue-50 border-blue-200' },
    signe_client: { label: 'Signé client', color: 'text-purple-600 bg-purple-50 border-purple-200' },
    signe_notaire: { label: 'Signé notaire', color: 'text-success bg-success-bg border-green-200' },
};

const TYPE_DOC_LABELS = {
    acte_principal: 'Acte principal',
    annexe:         'Annexe',
    procedure:      'Procédure',
    lettre:         'Lettre',
    recepisse:      'Récépissé',
};

const DOC_STATUT_NEXT = {
    a_editer:      { label: 'Marquer édité',            nextStatut: 'edite' },
    edite:         { label: 'Signature client obtenue', nextStatut: 'signe_client' },
    signe_client:  { label: 'Signature notaire obtenue',nextStatut: 'signe_notaire' },
    signe_notaire: null,
};

const EMPTY_DOC_FORM = { nom: '', type_document: 'acte_principal', version: '1.0', signature_client_requise: true };

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
        fd.append('signature_client_requise', form.signature_client_requise ? '1' : '0');
        if (fichier) fd.append('fichier', fichier);
        router.post(`/dossiers/${reference}/documents`, fd, {
            onSuccess: () => { onClose(); setForm(EMPTY_DOC_FORM); setFichier(null); },
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
                        <Label>Fichier <span className="text-slate-400 text-xs">(PDF, Word, ODT — optionnel)</span></Label>
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
                                accept=".pdf,.doc,.docx,.odt"
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

const PDF_EXTS = ['pdf'];
function isPdf(chemin) {
    if (!chemin) return false;
    return PDF_EXTS.includes(chemin.split('.').pop()?.toLowerCase());
}

function ModalPreviewDocument({ doc, onClose }) {
    const pdf = isPdf(doc?.chemin_fichier);
    const previewUrl = `/documents/${doc?.id}/preview`;

    useEffect(() => {
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [onClose]);

    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-black/70 backdrop-blur-sm" onClick={onClose}>
            {/* Barre supérieure */}
            <div
                className="flex items-center justify-between px-4 py-2 bg-ink text-white shrink-0"
                onClick={e => e.stopPropagation()}
            >
                <div className="flex items-center gap-3 min-w-0">
                    <FileText className="h-4 w-4 text-slate-300 shrink-0" />
                    <span className="text-sm font-medium truncate">{doc?.nom}</span>
                    {doc?.version && <span className="text-xs text-slate-400 shrink-0">v{doc.version}</span>}
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <a
                        href={`/documents/${doc?.id}/download`}
                        download
                        className="flex items-center gap-1.5 text-xs text-slate-300 hover:text-white border border-slate-600 rounded px-2 py-1 transition-colors"
                        onClick={e => e.stopPropagation()}
                    >
                        <Download className="h-3.5 w-3.5" />
                        Télécharger
                    </a>
                    <button
                        onClick={onClose}
                        className="h-7 w-7 flex items-center justify-center rounded hover:bg-white/10 transition-colors"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
            </div>

            {/* Zone d'aperçu */}
            <div className="flex-1 min-h-0 overflow-hidden" onClick={e => e.stopPropagation()}>
                {pdf ? (
                    <iframe
                        src={previewUrl}
                        className="w-full h-full border-0"
                        title={doc?.nom}
                    />
                ) : (
                    <div className="flex flex-col items-center justify-center h-full gap-4 text-white">
                        <FileText className="h-16 w-16 text-slate-500" />
                        <p className="text-sm text-slate-300">
                            L'aperçu n'est pas disponible pour ce format.
                        </p>
                        <a
                            href={`/documents/${doc?.id}/download`}
                            download
                            className="flex items-center gap-2 text-sm bg-seal text-white px-4 py-2 rounded-md hover:bg-seal/90 transition-colors"
                        >
                            <Download className="h-4 w-4" />
                            Télécharger le fichier
                        </a>
                    </div>
                )}
            </div>
        </div>
    );
}

function DocumentsTab({ dossier, reference, etape, can }) {
    const [addOpen, setAddOpen] = useState(false);
    const [previewDoc, setPreviewDoc] = useState(null);
    const [confirmState, setConfirmState] = useState(null);

    const avancerStatut = (doc) => {
        const next = DOC_STATUT_NEXT[doc.statut];
        if (!next) return;
        router.post(`/documents/${doc.id}/update`, { statut: next.nextStatut }, { preserveState: true });
    };

    const supprimer = (doc) => {
        setConfirmState({
            title: `Supprimer "${doc.nom}" ?`,
            description: 'Ce document sera définitivement supprimé.',
            confirmLabel: 'Supprimer',
            variant: 'destructive',
            onConfirm: () => router.delete(`/documents/${doc.id}`, { preserveState: true }),
        });
    };

    return (
        <Card>
            <CardHeader className="pb-3 flex flex-row items-center justify-between">
                <CardTitle>Actes &amp; documents</CardTitle>
                <Button size="sm" variant="outline" className="h-8" onClick={() => setAddOpen(true)}>
                    <Plus className="h-3.5 w-3.5" /> Nouveau document
                </Button>
            </CardHeader>
            <CardContent className="px-0 pb-0">
                {!dossier.documents?.length ? (
                    <div className="px-5 py-10 text-center">
                        <FileText className="h-10 w-10 text-slate-200 mx-auto mb-3" />
                        <p className="text-sm text-slate-400">Aucun document pour ce dossier.</p>
                        <Button size="sm" variant="outline" className="mt-4" onClick={() => setAddOpen(true)}>
                            <Plus className="h-3.5 w-3.5" /> Ajouter un document
                        </Button>
                    </div>
                ) : (
                    <table className="w-full table-notarial">
                        <thead>
                            <tr>
                                <th className="pl-5">Document</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th className="pr-5">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {dossier.documents.map((doc) => {
                                const statut = docStatutConfig[doc.statut] || docStatutConfig.a_editer;
                                const nextAction = DOC_STATUT_NEXT[doc.statut];
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
                                        <td>
                                            <span className={cn('inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border', statut.color)}>
                                                {statut.label}
                                            </span>
                                        </td>
                                        <td className="pr-5">
                                            <div className="flex items-center gap-1">
                                                {doc.chemin_fichier && (
                                                    <>
                                                        <Button variant="ghost" size="icon-sm" title="Prévisualiser"
                                                            onClick={() => setPreviewDoc(doc)}>
                                                            <Eye className="h-3.5 w-3.5" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon-sm" asChild title="Télécharger">
                                                            <a href={`/documents/${doc.id}/download`} download>
                                                                <Download className="h-3.5 w-3.5" />
                                                            </a>
                                                        </Button>
                                                    </>
                                                )}
                                                {nextAction && (
                                                    <Button variant="ghost" size="sm" className="h-7 text-xs px-2 text-seal hover:text-seal"
                                                        onClick={() => avancerStatut(doc)} title={nextAction.label}>
                                                        <PenSquare className="h-3 w-3 mr-1" />
                                                        {nextAction.label}
                                                    </Button>
                                                )}
                                                <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-red-500"
                                                    onClick={() => supprimer(doc)} title="Supprimer">
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
                {can?.reviser && etape === 'edition' && (
                    <div className="px-5 py-3 border-t border-slate-100">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={`/dossiers/${reference}/revision`}>
                                <Send className="h-3.5 w-3.5" />
                                Soumettre à révision
                            </Link>
                        </Button>
                    </div>
                )}
            </CardContent>
            <ModalAjouterDocument open={addOpen} onClose={() => setAddOpen(false)} reference={reference} />
            {previewDoc && (
                <ModalPreviewDocument doc={previewDoc} onClose={() => setPreviewDoc(null)} />
            )}
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

const EMPTY_FORMALITE_FORM = { organisme: 'apip', montant_base: '', taux: '', echeance_at: '', pieces: [] };

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
            statut:       'a_deposer',
            montant_base: form.montant_base || null,
            taux:         form.taux || null,
            echeance_at:  form.echeance_at || null,
            pieces:       form.pieces,
        }, {
            onSuccess: () => { onClose(); setForm(EMPTY_FORMALITE_FORM); },
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
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Montant de base <span className="text-slate-400 text-xs">(GNF)</span></Label>
                            <Input type="number" min="0" value={form.montant_base} onChange={f('montant_base')} placeholder="0" />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Taux <span className="text-slate-400 text-xs">(ex : 0.05)</span></Label>
                            <Input type="number" step="0.0001" min="0" max="1" value={form.taux} onChange={f('taux')} placeholder="0.05" />
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
        router.patch(`/formalites/${f.id}`, data, { preserveState: true });

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
        title: `Supprimer la formalité ${f.organismeLabel} ?`,
        description: 'Cette action est irréversible.',
        confirmLabel: 'Supprimer',
        variant: 'destructive',
        onConfirm: () => router.delete(`/formalites/${f.id}`, { preserveState: true }),
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
                            {f.organismeLabel}
                            {f.estDepassee && <AlertTriangle className="h-3.5 w-3.5 text-danger" />}
                        </div>
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

function FormalitesTab({ dossier, reference, etape, can }) {
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

function getStepBlockers(dossier) {
    const etape = dossier?.etape?.value;
    const docs = dossier?.documents ?? [];
    const formalites = dossier?.formalites ?? [];
    const revision = dossier?.revision;

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
            const nonEdites = docs.filter(d => d.statut === 'a_editer');
            if (nonEdites.length > 0) return [`${nonEdites.length} document(s) pas encore édité(s) : ${nonEdites.map(d => d.nom).join(', ')}`];
            return [];
        }
        case 'revision': {
            if (revision?.statut === 'valide') return [];
            return [{
                renvoye:     "Révision renvoyée en correction — les points signalés doivent être corrigés",
                en_attente:  "Révision en attente — elle doit être évaluée par le réviseur",
                en_cours:    "Révision en cours — elle doit être validée pour continuer",
            }[revision?.statut] ?? "La révision doit être validée avant de passer à la signature client"];
        }
        case 'signature_client': {
            const nonSignes = docs.filter(d => d.signature_client_requise && !['signe_client', 'signe_notaire'].includes(d.statut));
            if (nonSignes.length > 0) return [`${nonSignes.length} document(s) en attente de signature client : ${nonSignes.map(d => d.nom).join(', ')}`];
            return [];
        }
        case 'signature_notaire': {
            const nonSignes = docs.filter(d => d.statut !== 'signe_notaire');
            if (nonSignes.length > 0) return [`${nonSignes.length} document(s) sans signature notaire : ${nonSignes.map(d => d.nom).join(', ')}`];
            return [];
        }
        case 'formalites': {
            const nonClos = formalites.filter(f => f.statut !== 'cloture');
            if (nonClos.length > 0) return [`${nonClos.length} formalité(s) non clôturée(s) : ${nonClos.map(f => f.organismeLabel || f.organisme).join(', ')}`];
            return [];
        }
        default:
            return [];
    }
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

export default function DossierShow() {
    const { dossier, can } = usePage().props;
    const [activeTab, setActiveTab] = useState('informations');
    const [avancing, setAvancing] = useState(false);
    const [avancerErrors, setAvancerErrors] = useState([]);

    const etape = dossier?.etape?.value ?? '';
    const reference = dossier?.reference ?? '';

    const blockers = can?.avancer ? getStepBlockers(dossier) : [];

    const actionContextuel = {
        revision: { label: 'Voir la révision', href: `/dossiers/${reference}/revision`, variant: 'seal', icon: ClipboardCheck },
        signature_notaire: { label: 'Voir signature', href: '#', variant: 'default', icon: PenLine },
        formalites: { label: 'Voir les formalités', tab: 'formalites', variant: 'default', icon: Building },
    };
    const action = actionContextuel[etape];

    const handleAvancer = () => {
        setAvancerErrors([]);
        setAvancing(true);
        router.post(`/dossiers/${reference}/avancer`, {}, {
            onFinish: () => setAvancing(false),
            onError: (errors) => setAvancerErrors(Object.values(errors).flat()),
            onSuccess: () => setAvancerErrors([]),
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
                                                    onClick={handleAvancer}
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
                                    <TabsTrigger value="parties">
                                        Parties
                                        {dossier.parties?.length > 0 && (
                                            <span className="ml-1.5 text-[10px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full">
                                                {dossier.parties.length}
                                            </span>
                                        )}
                                    </TabsTrigger>
                                    <TabsTrigger value="journal">Journal</TabsTrigger>
                                </TabsList>

                                {/* Onglet Informations */}
                                <TabsContent value="informations">
                                    <Card>
                                        <CardHeader className="pb-3">
                                            <CardTitle>Fiche dossier — {dossier.typeActe?.label}</CardTitle>
                                        </CardHeader>
                                        <CardContent>
                                            {dossier.questionnaire && Object.keys(dossier.questionnaire).length > 0 ? (
                                                <dl className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                                                    {Object.entries(dossier.questionnaire).map(([key, value]) => (
                                                        <div key={key} className="space-y-0.5">
                                                            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">{key}</dt>
                                                            <dd className="text-sm text-slate-800">
                                                                {typeof value === 'boolean' ? (value ? 'Oui' : 'Non') : String(value)}
                                                            </dd>
                                                        </div>
                                                    ))}
                                                </dl>
                                            ) : (
                                                <div className="space-y-4">
                                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                                                        <div className="space-y-0.5">
                                                            <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">Objet</dt>
                                                            <dd className="text-sm text-slate-800">{dossier.objet}</dd>
                                                        </div>
                                                        {dossier.valeur > 0 && (
                                                            <div className="space-y-0.5">
                                                                <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">Valeur</dt>
                                                                <dd className="text-sm text-slate-800 font-ref">{Number(dossier.valeur).toLocaleString('fr-GN')} GNF</dd>
                                                            </div>
                                                        )}
                                                        {dossier.echeance && (
                                                            <div className="space-y-0.5">
                                                                <dt className="text-xs font-semibold uppercase tracking-wider text-slate-400">Échéance</dt>
                                                                <dd className="text-sm text-slate-800">{dossier.echeance}</dd>
                                                            </div>
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-slate-400 italic">Aucun questionnaire renseigné.</p>
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                </TabsContent>

                                {/* Onglet Actes & Documents */}
                                <TabsContent value="documents">
                                    <DocumentsTab
                                        dossier={dossier}
                                        reference={reference}
                                        etape={etape}
                                        can={can}
                                    />
                                </TabsContent>

                                {/* Onglet Révision */}
                                <TabsContent value="revision">
                                    {dossier.revision ? (
                                        <Card className={cn(
                                            'border-l-4',
                                            dossier.revision.statut === 'valide' && 'border-l-success',
                                            dossier.revision.statut === 'en_cours' && 'border-l-seal',
                                            dossier.revision.statut === 'renvoye' && 'border-l-danger',
                                            dossier.revision.statut === 'en_attente' && 'border-l-slate-300',
                                        )}>
                                            <CardContent className="p-5">
                                                <div className="flex items-center justify-between gap-4 mb-4">
                                                    <div>
                                                        <h3 className="font-serif text-heading text-ink">Grille de contrôle</h3>
                                                        {dossier.revision.reviseur && (
                                                            <p className="text-sm text-slate-500 mt-0.5">Réviseur : {dossier.revision.reviseur.name}</p>
                                                        )}
                                                    </div>
                                                    <span className={cn(
                                                        'inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border',
                                                        revisionStatutColors[dossier.revision.statut] ?? 'bg-slate-50 text-slate-600 border-slate-200'
                                                    )}>
                                                        {revisionStatutLabels[dossier.revision.statut] ?? dossier.revision.statut}
                                                    </span>
                                                </div>
                                                {dossier.revision.commentaire && (
                                                    <div className="p-3 rounded-lg bg-slate-50 border border-slate-200 text-sm text-slate-700 mb-4">
                                                        {dossier.revision.commentaire}
                                                    </div>
                                                )}
                                                <div className="flex gap-2">
                                                    <Button variant="seal" asChild>
                                                        <Link href={`/dossiers/${reference}/revision`}>
                                                            <ClipboardCheck className="h-4 w-4" />
                                                            Accéder à la grille
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ) : (
                                        <Card>
                                            <CardContent className="p-6 flex flex-col items-center gap-4 text-center py-12">
                                                <div className="h-12 w-12 rounded-full bg-warning-bg flex items-center justify-center">
                                                    <ClipboardCheck className="h-6 w-6 text-warning" />
                                                </div>
                                                <div>
                                                    <h3 className="font-serif text-heading text-ink">Révision en attente</h3>
                                                    <p className="text-slate-500 text-sm mt-1">La grille de contrôle n'a pas encore été soumise</p>
                                                </div>
                                                {can?.reviser && (
                                                    <Button variant="seal" asChild>
                                                        <Link href={`/dossiers/${reference}/revision`}>
                                                            <ClipboardCheck className="h-4 w-4" />
                                                            Accéder à la révision
                                                        </Link>
                                                    </Button>
                                                )}
                                            </CardContent>
                                        </Card>
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
                                        <span>{f.organismeLabel}</span>
                                    </div>
                                ))}
                            </div>
                        </>
                    )}
                </div>

            </div>
        </AppLayout>
    );
}
