import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    FileText, Download, Eye, CheckCircle2, AlertTriangle,
    XCircle, ChevronLeft, Send, Shield, ClipboardList,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';

const STATUTS = {
    en_attente: { label: 'En attente',          color: 'text-slate-500 bg-slate-50 border-slate-200' },
    en_cours:   { label: 'En cours',            color: 'text-warning-text bg-warning-bg border-amber-200' },
    valide:     { label: 'Validé',              color: 'text-success bg-success-bg border-green-200' },
    renvoye:    { label: 'Renvoyé en correction', color: 'text-danger-text bg-danger-bg border-red-200' },
};

const TYPE_DOC_LABELS = {
    acte_principal: 'Acte principal',
    annexe:         'Annexe',
    procedure:      'Procédure',
    lettre:         'Lettre',
    recepisse:      'Récépissé',
};

const TYPE_DOC_COLORS = {
    acte_principal: 'bg-seal/10 text-seal border-seal/20',
    annexe:         'bg-slate-100 text-slate-600 border-slate-200',
    procedure:      'bg-violet-50 text-violet-600 border-violet-200',
    lettre:         'bg-amber-50 text-amber-700 border-amber-200',
    recepisse:      'bg-emerald-50 text-emerald-700 border-emerald-200',
};

function buildInitialEtats(documents, savedPoints) {
    const init = {};
    (documents ?? []).forEach(doc => {
        const saved = savedPoints?.[String(doc.id)];
        init[String(doc.id)] = {
            etat:        saved?.etat        ?? null,
            commentaire: saved?.commentaire ?? '',
        };
    });
    return init;
}

export default function Revision() {
    const { dossier, documents, revision, can } = usePage().props;

    const [etats, setEtats] = useState(() => buildInitialEtats(documents, revision?.points));
    const [showRenvoyerDialog, setShowRenvoyerDialog] = useState(false);
    const [motifRenvoyer, setMotifRenvoyer] = useState('');
    const [saving, setSaving] = useState(false);
    const [validating, setValidating] = useState(false);
    const [renvoyant, setRenvoyant] = useState(false);

    const revisionStatut = revision?.statut ?? 'en_attente';
    const statutConf     = STATUTS[revisionStatut] ?? STATUTS.en_attente;

    const docList      = documents ?? [];
    const docEvalues   = Object.values(etats).filter(e => e.etat !== null).length;
    const docOk        = Object.values(etats).filter(e => e.etat === 'ok').length;
    const docACorriger = Object.values(etats).filter(e => e.etat === 'a_corriger').length;
    const docACorrigerSansCommentaire = Object.values(etats)
        .some(e => e.etat === 'a_corriger' && !e.commentaire?.trim());
    const pctProgress  = docList.length > 0 ? Math.round((docEvalues / docList.length) * 100) : 0;
    const canValidate  = docACorriger === 0 && docEvalues === docList.length && docList.length > 0;
    const canRenvoyer  = docACorriger > 0 && !docACorrigerSansCommentaire;

    const setVerdict = (docId, etat) => {
        setEtats(prev => ({ ...prev, [docId]: { ...prev[docId], etat } }));
    };
    const setCommentaire = (docId, commentaire) => {
        setEtats(prev => ({ ...prev, [docId]: { ...prev[docId], commentaire } }));
    };

    const handleSave = () => {
        setSaving(true);
        router.put(`/dossiers/${dossier.reference}/revision`, { points: etats }, {
            onFinish: () => setSaving(false),
        });
    };

    // Sauvegarde d'abord les points en base avant de valider/renvoyer, sinon le
    // serveur peut refuser l'action car il ne connaît pas encore les verdicts que
    // l'utilisateur vient de saisir localement (tant que « Sauvegarder » n'a pas
    // été cliqué séparément).
    const handleValider = () => {
        setValidating(true);
        router.put(`/dossiers/${dossier.reference}/revision`, { points: etats }, {
            onSuccess: () => {
                router.post(`/dossiers/${dossier.reference}/revision/valider`, {}, {
                    onFinish: () => setValidating(false),
                });
            },
            onError: () => setValidating(false),
        });
    };

    const handleRenvoyer = () => {
        setRenvoyant(true);
        router.put(`/dossiers/${dossier.reference}/revision`, { points: etats }, {
            onSuccess: () => {
                router.post(`/dossiers/${dossier.reference}/revision/renvoyer`, { motif: motifRenvoyer }, {
                    onFinish: () => { setRenvoyant(false); setShowRenvoyerDialog(false); },
                });
            },
            onError: () => setRenvoyant(false),
        });
    };

    return (
        <AppLayout breadcrumbs={[
            { label: 'Dossiers', href: '/dossiers' },
            { label: dossier.reference, href: `/dossiers/${dossier.reference}` },
            { label: 'Révision' }
        ]}>
            <Head title={`Révision ${dossier.reference} — Ayelema`} />

            <div className="p-6 max-w-[900px] mx-auto space-y-5">

                {/* Dialog renvoyer */}
                <AnimatePresence>
                    {showRenvoyerDialog && (
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                            onClick={e => e.target === e.currentTarget && setShowRenvoyerDialog(false)}
                        >
                            <motion.div
                                initial={{ scale: 0.95, opacity: 0 }}
                                animate={{ scale: 1, opacity: 1 }}
                                exit={{ scale: 0.95, opacity: 0 }}
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
                                    <Button variant="warning" onClick={handleRenvoyer} disabled={renvoyant}>
                                        <AlertTriangle className="h-4 w-4" />
                                        {renvoyant ? 'Envoi…' : 'Confirmer le renvoi'}
                                    </Button>
                                </div>
                            </motion.div>
                        </motion.div>
                    )}
                </AnimatePresence>

                {/* En-tête */}
                <motion.div initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }}>
                    <Card className={cn(
                        'border-l-4',
                        revisionStatut === 'valide'     && 'border-l-success',
                        revisionStatut === 'en_cours'   && 'border-l-seal',
                        revisionStatut === 'renvoye'    && 'border-l-danger',
                        revisionStatut === 'en_attente' && 'border-l-slate-300',
                    )}>
                        <CardContent className="p-5">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <div className="flex items-center gap-2 mb-1">
                                        <ClipboardList className="h-4 w-4 text-slate-400" />
                                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Révision des actes</span>
                                    </div>
                                    <h1 className="font-serif text-display text-ink">Évaluation des documents</h1>
                                    <p className="text-slate-500 text-sm mt-1">
                                        <span className="font-ref text-seal">{dossier.reference}</span>
                                        {' — '}
                                        {dossier.objet}
                                        {dossier.typeActe && ` · ${dossier.typeActe}`}
                                    </p>
                                    {dossier.redacteur && (
                                        <p className="text-xs text-slate-400 mt-0.5">Rédacteur : {dossier.redacteur}</p>
                                    )}
                                </div>
                                <span className={cn(
                                    'inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold border shrink-0',
                                    statutConf.color
                                )}>
                                    {statutConf.label}
                                </span>
                            </div>

                            {/* Barre de progression */}
                            {docList.length > 0 && (
                                <div className="mt-5 space-y-2">
                                    <div className="flex items-center justify-between text-xs">
                                        <span className="text-slate-500">{docEvalues}/{docList.length} document{docList.length > 1 ? 's' : ''} évalué{docEvalues > 1 ? 's' : ''}</span>
                                        <div className="flex items-center gap-3">
                                            {docOk > 0 && (
                                                <span className="flex items-center gap-1 text-success">
                                                    <CheckCircle2 className="h-3.5 w-3.5" />
                                                    {docOk} OK
                                                </span>
                                            )}
                                            {docACorriger > 0 && (
                                                <span className="flex items-center gap-1 text-danger">
                                                    <XCircle className="h-3.5 w-3.5" />
                                                    {docACorriger} à corriger
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <Progress
                                        value={pctProgress}
                                        indicatorClassName={docACorriger > 0 ? 'bg-danger' : docEvalues === docList.length ? 'bg-success' : 'bg-seal'}
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </motion.div>

                {/* Aucun document */}
                {docList.length === 0 && (
                    <Card>
                        <CardContent className="p-10 flex flex-col items-center gap-3 text-center">
                            <FileText className="h-10 w-10 text-slate-200" />
                            <p className="text-slate-500 text-sm">Aucun document n'a encore été ajouté à ce dossier.</p>
                        </CardContent>
                    </Card>
                )}

                {/* Liste des documents */}
                <div className="space-y-3">
                    {docList.map((doc, idx) => {
                        const docId    = String(doc.id);
                        const etatDoc  = etats[docId] ?? { etat: null, commentaire: '' };
                        const isOk     = etatDoc.etat === 'ok';
                        const isNok    = etatDoc.etat === 'a_corriger';

                        return (
                            <motion.div
                                key={doc.id}
                                initial={{ opacity: 0, y: 8 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: idx * 0.05 }}
                            >
                                <Card className={cn(
                                    'transition-colors border',
                                    isOk  && 'border-success/40 bg-success-bg/20',
                                    isNok && 'border-danger/30 bg-danger-bg/30',
                                    !isOk && !isNok && 'border-slate-200',
                                )}>
                                    <CardContent className="p-5">
                                        {/* En-tête document */}
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="flex items-start gap-3 min-w-0">
                                                <div className={cn(
                                                    'h-9 w-9 rounded-lg flex items-center justify-center shrink-0 mt-0.5',
                                                    isOk  ? 'bg-success/10' : isNok ? 'bg-danger/10' : 'bg-slate-100'
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
                                                    <div className="flex items-center gap-2 mt-1 flex-wrap">
                                                        <span className={cn(
                                                            'inline-flex items-center text-xs px-2 py-0.5 rounded-full border',
                                                            TYPE_DOC_COLORS[doc.type_document] ?? 'bg-slate-100 text-slate-500 border-slate-200'
                                                        )}>
                                                            {TYPE_DOC_LABELS[doc.type_document] ?? doc.type_document}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Liens télécharger / aperçu */}
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
                                                    <a
                                                        href={doc.url_preview}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="flex items-center gap-1 px-2.5 py-1.5 rounded-md text-xs font-medium border border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50 transition-all"
                                                    >
                                                        <Eye className="h-3.5 w-3.5" />
                                                        Aperçu
                                                    </a>
                                                </div>
                                            )}
                                        </div>

                                        {/* Boutons verdict */}
                                        {can?.update && (
                                            <div className="flex items-center gap-2 mt-4 pt-4 border-t border-slate-100">
                                                <span className="text-xs text-slate-400 mr-1">Verdict :</span>
                                                <button
                                                    onClick={() => setVerdict(docId, isOk ? null : 'ok')}
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
                                                    onClick={() => setVerdict(docId, isNok ? null : 'a_corriger')}
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

                                        {/* Commentaire (visible si à corriger ou déjà rempli) */}
                                        <AnimatePresence>
                                            {(isNok || etatDoc.commentaire) && (
                                                <motion.div
                                                    initial={{ height: 0, opacity: 0 }}
                                                    animate={{ height: 'auto', opacity: 1 }}
                                                    exit={{ height: 0, opacity: 0 }}
                                                    className="overflow-hidden"
                                                >
                                                    {isNok && (
                                                        <span className="text-xs text-slate-400">
                                                            Commentaire <span className="text-danger">*</span> — obligatoire pour un document à corriger
                                                        </span>
                                                    )}
                                                    <textarea
                                                        value={etatDoc.commentaire}
                                                        onChange={e => setCommentaire(docId, e.target.value)}
                                                        placeholder="Décrivez les corrections à apporter…"
                                                        rows={2}
                                                        readOnly={!can?.update}
                                                        className={cn(
                                                            'mt-1 w-full text-sm rounded-lg border px-3 py-2 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-seal resize-none',
                                                            isNok && !etatDoc.commentaire?.trim() ? 'border-danger' : 'border-slate-200'
                                                        )}
                                                    />
                                                </motion.div>
                                            )}
                                        </AnimatePresence>

                                        {/* Lecture seule : affichage verdict */}
                                        {!can?.update && etatDoc.etat && (
                                            <div className={cn(
                                                'mt-4 pt-4 border-t border-slate-100 flex items-center gap-2 text-sm font-medium',
                                                isOk  ? 'text-success' : 'text-danger-text'
                                            )}>
                                                {isOk ? <CheckCircle2 className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}
                                                {isOk ? 'Document validé' : 'À corriger'}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            </motion.div>
                        );
                    })}
                </div>

                {/* Avertissement si documents à corriger */}
                {docACorriger > 0 && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        className="flex items-start gap-3 p-4 rounded-lg bg-danger-bg border border-red-200 text-danger-text text-sm"
                    >
                        <XCircle className="h-4 w-4 shrink-0 mt-0.5" />
                        <div>
                            {revisionStatut === 'renvoye' ? (
                                <>
                                    <span className="font-medium">Déjà renvoyé en correction — </span>
                                    {docACorriger} document{docACorriger > 1 ? 's' : ''} en attente de correction par le rédacteur.
                                </>
                            ) : (
                                <>
                                    <span className="font-medium">Validation bloquée — </span>
                                    {docACorriger} document{docACorriger > 1 ? 's' : ''} à corriger.
                                    Renvoyez le dossier en édition pour que le rédacteur effectue les corrections.
                                </>
                            )}
                        </div>
                    </motion.div>
                )}

                {/* Actions finales */}
                <motion.div
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.2 }}
                    className="flex flex-col sm:flex-row items-center gap-3 pt-2"
                >
                    <Button variant="outline" size="lg" asChild>
                        <Link href={`/dossiers/${dossier.reference}`}>
                            <ChevronLeft className="h-4 w-4" />
                            Retour au dossier
                        </Link>
                    </Button>

                    <div className="flex-1" />

                    {can?.update && (
                        <Button
                            variant="outline"
                            size="lg"
                            onClick={handleSave}
                            disabled={saving || docACorrigerSansCommentaire}
                            title={docACorrigerSansCommentaire ? 'Ajoutez un commentaire aux documents « À corriger »' : ''}
                        >
                            <Send className="h-4 w-4" />
                            {saving ? 'Sauvegarde…' : 'Sauvegarder'}
                        </Button>
                    )}

                    {can?.update && (
                        <Button
                            variant="warning"
                            size="lg"
                            disabled={!canRenvoyer}
                            onClick={() => setShowRenvoyerDialog(true)}
                        >
                            <AlertTriangle className="h-4 w-4" />
                            Renvoyer en correction
                            {docACorriger > 0 && (
                                <span className="ml-1 text-xs bg-warning-text/20 px-1.5 py-0.5 rounded-full">
                                    {docACorriger}
                                </span>
                            )}
                        </Button>
                    )}

                    {can?.update && (
                        <Button
                            variant="seal"
                            size="lg"
                            disabled={!canValidate || validating || revisionStatut === 'valide'}
                            onClick={handleValider}
                        >
                            <Shield className="h-4 w-4" />
                            {validating ? 'Validation…' : revisionStatut === 'valide' ? 'Révision validée ✓' : 'Valider la révision'}
                            {!canValidate && docEvalues < docList.length && revisionStatut !== 'valide' && (
                                <span className="text-xs opacity-70 ml-1">
                                    ({docList.length - docEvalues} restant{docList.length - docEvalues > 1 ? 's' : ''})
                                </span>
                            )}
                        </Button>
                    )}
                </motion.div>

            </div>
        </AppLayout>
    );
}
