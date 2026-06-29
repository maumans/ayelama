import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    CheckCircle2, XCircle, Minus, ChevronLeft, Send,
    AlertTriangle, Check, Shield, FileText,
    ClipboardCheck, Users, DollarSign, Scale
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';

const STATUTS = {
    en_attente: { label: 'En attente', color: 'text-slate-400 bg-slate-50 border-slate-200' },
    en_cours: { label: 'En cours', color: 'text-warning-text bg-warning-bg border-amber-200' },
    valide: { label: 'Validé', color: 'text-success bg-success-bg border-green-200' },
    renvoye: { label: 'Renvoyé', color: 'text-danger-text bg-danger-bg border-red-200' },
};

// Default grille if no server grille configured
const DEFAULT_GROUPES = [
    {
        id: 'identite',
        icon: Users,
        label: 'Identité et parties',
        points: [
            { id: 'p1', label: 'Identité et pièces des parties complètes', requis: true },
            { id: 'p2', label: 'Adresse cohérente entre les documents', requis: true },
        ],
    },
    {
        id: 'contenu',
        icon: FileText,
        label: 'Contenu et mentions légales',
        points: [
            { id: 'p3', label: 'Mentions obligatoires présentes', requis: true },
            { id: 'p4', label: 'Cohérence des montants en chiffres et en lettres', requis: true },
            { id: 'p5', label: 'Dates et références conformes', requis: true },
        ],
    },
    {
        id: 'conformite',
        icon: Scale,
        label: 'Conformité légale',
        points: [
            { id: 'p6', label: 'Acte conforme au droit applicable', requis: true },
            { id: 'p7', label: 'Signatures et paraphes requis identifiés', requis: false },
        ],
    },
];

function buildGroupes(grilleFromServer) {
    if (!grilleFromServer) return DEFAULT_GROUPES;
    const GROUPE_ICONS = { identite: Users, denomination: FileText, capital: DollarSign, conformite: Scale };
    return Object.entries(grilleFromServer).map(([groupLabel, points]) => ({
        id: groupLabel.toLowerCase().replace(/\s+/g, '_'),
        icon: GROUPE_ICONS[groupLabel.toLowerCase().replace(/\s+/g, '_')] ?? FileText,
        label: groupLabel,
        points: (Array.isArray(points) ? points : Object.values(points)).map(p => ({
            id: p.id ?? p,
            label: p.label ?? p,
            requis: p.requis !== false,
        })),
    }));
}

export default function Revision() {
    const { dossier, revision, grille, can } = usePage().props;

    const groupes = buildGroupes(grille);

    const buildInitialEtats = () => {
        const initial = {};
        groupes.forEach(g => g.points.forEach(p => {
            const existing = revision?.points?.[p.id];
            initial[p.id] = { etat: existing?.etat ?? null, commentaire: existing?.commentaire ?? '' };
        }));
        return initial;
    };

    const [etats, setEtats] = useState(buildInitialEtats);
    const [commentaireGeneral, setCommentaireGeneral] = useState(revision?.commentaire ?? '');
    const [showRenvoyerDialog, setShowRenvoyerDialog] = useState(false);
    const [motifRenvoyer, setMotifRenvoyer] = useState('');
    const [saving, setSaving] = useState(false);
    const [validating, setValidating] = useState(false);
    const [renvoyant, setRenvoyant] = useState(false);

    const allPoints = groupes.flatMap(g => g.points);
    const totalPoints = allPoints.length;
    const pointsEvalues = Object.values(etats).filter(e => e.etat !== null).length;
    const pointsConformes = Object.values(etats).filter(e => e.etat === 'conforme').length;
    const pointsNonConformes = Object.values(etats).filter(e => e.etat === 'non_conforme').length;
    const pctProgress = totalPoints > 0 ? Math.round((pointsEvalues / totalPoints) * 100) : 0;
    const canValidate = pointsNonConformes === 0 && pointsEvalues === totalPoints;
    const revisionStatut = revision?.statut ?? 'en_attente';
    const statutConf = STATUTS[revisionStatut] ?? STATUTS.en_attente;

    const setEtat = (pointId, etat) => {
        setEtats(prev => ({ ...prev, [pointId]: { ...prev[pointId], etat } }));
    };
    const setCommentaire = (pointId, commentaire) => {
        setEtats(prev => ({ ...prev, [pointId]: { ...prev[pointId], commentaire } }));
    };

    const handleSave = () => {
        setSaving(true);
        router.put(`/dossiers/${dossier.reference}/revision`, { points: etats }, {
            onFinish: () => setSaving(false),
        });
    };

    const handleValider = () => {
        setValidating(true);
        router.post(`/dossiers/${dossier.reference}/revision/valider`, {}, {
            onFinish: () => setValidating(false),
        });
    };

    const handleRenvoyer = () => {
        setRenvoyant(true);
        router.post(`/dossiers/${dossier.reference}/revision/renvoyer`, { motif: motifRenvoyer }, {
            onFinish: () => { setRenvoyant(false); setShowRenvoyerDialog(false); },
        });
    };

    if (!dossier) {
        return (
            <AppLayout breadcrumbs={[{ label: 'Dossiers', href: '/dossiers' }]}>
                <div className="p-6 text-center text-slate-400">Chargement…</div>
            </AppLayout>
        );
    }

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
                                    Indiquez le motif du renvoi (optionnel). Le rédacteur recevra ce commentaire.
                                </p>
                                <textarea
                                    rows={3}
                                    value={motifRenvoyer}
                                    onChange={e => setMotifRenvoyer(e.target.value)}
                                    placeholder="Motif du renvoi en correction…"
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
                        revisionStatut === 'valide' && 'border-l-success',
                        revisionStatut === 'en_cours' && 'border-l-seal',
                        revisionStatut === 'renvoye' && 'border-l-danger',
                        revisionStatut === 'en_attente' && 'border-l-slate-300',
                    )}>
                        <CardContent className="p-5">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <div className="flex items-center gap-2 mb-1">
                                        <ClipboardCheck className="h-4 w-4 text-slate-400" />
                                        <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">Contrôle qualité avant signature</span>
                                    </div>
                                    <h1 className="font-serif text-display text-ink">Grille de contrôle</h1>
                                    <p className="text-slate-500 text-sm mt-1">
                                        <span className="font-ref text-seal">{dossier.reference}</span>
                                        {' — '}
                                        {dossier.objet}
                                        {dossier.typeActe && ` · ${dossier.typeActe}`}
                                    </p>
                                </div>
                                <span className={cn(
                                    'inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold border shrink-0',
                                    statutConf.color
                                )}>
                                    {statutConf.label}
                                </span>
                            </div>

                            {/* Barre de progression */}
                            <div className="mt-5 space-y-2">
                                <div className="flex items-center justify-between text-xs">
                                    <span className="text-slate-500">{pointsEvalues}/{totalPoints} points évalués</span>
                                    <div className="flex items-center gap-3">
                                        {pointsConformes > 0 && (
                                            <span className="flex items-center gap-1 text-success">
                                                <CheckCircle2 className="h-3.5 w-3.5" />
                                                {pointsConformes} conforme{pointsConformes > 1 ? 's' : ''}
                                            </span>
                                        )}
                                        {pointsNonConformes > 0 && (
                                            <span className="flex items-center gap-1 text-danger">
                                                <XCircle className="h-3.5 w-3.5" />
                                                {pointsNonConformes} non conforme{pointsNonConformes > 1 ? 's' : ''}
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <Progress
                                    value={pctProgress}
                                    indicatorClassName={pointsNonConformes > 0 ? 'bg-danger' : pointsEvalues === totalPoints ? 'bg-success' : 'bg-seal'}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </motion.div>

                {/* Grille de contrôle */}
                <div className="space-y-4">
                    {groupes.map((groupe, gi) => {
                        const GroupIcon = groupe.icon;
                        return (
                            <motion.div
                                key={groupe.id}
                                initial={{ opacity: 0, y: 8 }}
                                animate={{ opacity: 1, y: 0 }}
                                transition={{ delay: gi * 0.06 }}
                            >
                                <Card>
                                    <CardHeader className="pb-2">
                                        <div className="flex items-center gap-2">
                                            <div className="h-7 w-7 rounded-lg bg-ink/5 flex items-center justify-center">
                                                <GroupIcon className="h-4 w-4 text-ink" />
                                            </div>
                                            <CardTitle className="text-base">{groupe.label}</CardTitle>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="px-0 pb-0">
                                        {groupe.points.map((point, pi) => {
                                            const etatPoint = etats[point.id] ?? { etat: null, commentaire: '' };
                                            const isConforme = etatPoint.etat === 'conforme';
                                            const isNonConforme = etatPoint.etat === 'non_conforme';
                                            const isNA = etatPoint.etat === 'na';

                                            return (
                                                <div
                                                    key={point.id}
                                                    className={cn(
                                                        'px-5 py-3.5 border-b border-slate-50 last:border-0 transition-colors',
                                                        isConforme && 'bg-success-bg/30',
                                                        isNonConforme && 'bg-danger-bg/40',
                                                    )}
                                                >
                                                    <div className="flex items-start gap-4">
                                                        <div className="shrink-0 mt-0.5">
                                                            {isConforme ? (
                                                                <CheckCircle2 className="h-5 w-5 text-success" />
                                                            ) : isNonConforme ? (
                                                                <XCircle className="h-5 w-5 text-danger" />
                                                            ) : isNA ? (
                                                                <Minus className="h-5 w-5 text-slate-300" />
                                                            ) : (
                                                                <div className="h-5 w-5 rounded-full border-2 border-slate-200" />
                                                            )}
                                                        </div>

                                                        <div className="flex-1 min-w-0">
                                                            <div className="flex items-start justify-between gap-3">
                                                                <div>
                                                                    <p className="text-sm font-medium text-slate-800">{point.label}</p>
                                                                    {!point.requis && <span className="text-xs text-slate-400 italic">(optionnel)</span>}
                                                                </div>
                                                                {can?.update && (
                                                                    <div className="flex items-center gap-1.5 shrink-0">
                                                                        <button
                                                                            onClick={() => setEtat(point.id, isConforme ? null : 'conforme')}
                                                                            className={cn(
                                                                                'flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium border transition-all',
                                                                                isConforme
                                                                                    ? 'bg-success text-white border-success'
                                                                                    : 'bg-white text-slate-500 border-slate-200 hover:border-success hover:text-success'
                                                                            )}
                                                                        >
                                                                            <Check className="h-3 w-3" />
                                                                            Conforme
                                                                        </button>
                                                                        <button
                                                                            onClick={() => setEtat(point.id, isNonConforme ? null : 'non_conforme')}
                                                                            className={cn(
                                                                                'flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium border transition-all',
                                                                                isNonConforme
                                                                                    ? 'bg-danger text-white border-danger'
                                                                                    : 'bg-white text-slate-500 border-slate-200 hover:border-danger hover:text-danger'
                                                                            )}
                                                                        >
                                                                            <XCircle className="h-3 w-3" />
                                                                            Non conforme
                                                                        </button>
                                                                        {!point.requis && (
                                                                            <button
                                                                                onClick={() => setEtat(point.id, isNA ? null : 'na')}
                                                                                className={cn(
                                                                                    'px-2.5 py-1 rounded-md text-xs font-medium border transition-all',
                                                                                    isNA
                                                                                        ? 'bg-slate-400 text-white border-slate-400'
                                                                                        : 'bg-white text-slate-400 border-slate-200 hover:border-slate-400'
                                                                                )}
                                                                            >
                                                                                N/A
                                                                            </button>
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </div>

                                                            <AnimatePresence>
                                                                {(isNonConforme || etatPoint.commentaire) && (
                                                                    <motion.div
                                                                        initial={{ height: 0, opacity: 0 }}
                                                                        animate={{ height: 'auto', opacity: 1 }}
                                                                        exit={{ height: 0, opacity: 0 }}
                                                                        className="overflow-hidden"
                                                                    >
                                                                        <textarea
                                                                            value={etatPoint.commentaire}
                                                                            onChange={e => setCommentaire(point.id, e.target.value)}
                                                                            placeholder="Commentaire du réviseur…"
                                                                            rows={2}
                                                                            readOnly={!can?.update}
                                                                            className="mt-2 w-full text-xs rounded-md border border-slate-200 px-3 py-2 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-seal focus:border-transparent resize-none"
                                                                        />
                                                                    </motion.div>
                                                                )}
                                                            </AnimatePresence>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </CardContent>
                                </Card>
                            </motion.div>
                        );
                    })}
                </div>

                {/* Commentaire général */}
                <Card>
                    <CardHeader className="pb-2">
                        <CardTitle className="text-base">Commentaire général du réviseur</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <textarea
                            value={commentaireGeneral}
                            onChange={e => setCommentaireGeneral(e.target.value)}
                            placeholder="Observations générales sur le dossier…"
                            rows={3}
                            readOnly={!can?.update}
                            className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2.5 text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-seal focus:border-transparent resize-none"
                        />
                    </CardContent>
                </Card>

                {/* Actions finales */}
                <motion.div
                    initial={{ opacity: 0, y: 8 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.3 }}
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
                        <Button variant="outline" size="lg" onClick={handleSave} disabled={saving}>
                            <Send className="h-4 w-4" />
                            {saving ? 'Sauvegarde…' : 'Sauvegarder'}
                        </Button>
                    )}

                    {can?.renvoyer && (
                        <Button
                            variant="warning"
                            size="lg"
                            disabled={pointsNonConformes === 0}
                            onClick={() => setShowRenvoyerDialog(true)}
                        >
                            <AlertTriangle className="h-4 w-4" />
                            Renvoyer en correction
                            {pointsNonConformes > 0 && (
                                <span className="ml-1 text-xs bg-warning-text/20 px-1.5 py-0.5 rounded-full">
                                    {pointsNonConformes}
                                </span>
                            )}
                        </Button>
                    )}

                    {can?.valider && (
                        <Button
                            variant="seal"
                            size="lg"
                            disabled={!canValidate || validating || revisionStatut === 'valide'}
                            onClick={handleValider}
                        >
                            <Shield className="h-4 w-4" />
                            {validating ? 'Validation…' : revisionStatut === 'valide' ? 'Révision validée ✓' : 'Valider la révision'}
                            {!canValidate && pointsEvalues < totalPoints && (
                                <span className="text-xs opacity-70 ml-1">
                                    ({totalPoints - pointsEvalues} restant{totalPoints - pointsEvalues > 1 ? 's' : ''})
                                </span>
                            )}
                        </Button>
                    )}
                </motion.div>

                {/* Avertissement si non conforme */}
                {pointsNonConformes > 0 && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        className="flex items-start gap-3 p-4 rounded-lg bg-danger-bg border border-red-200 text-danger-text text-sm"
                    >
                        <XCircle className="h-4 w-4 shrink-0 mt-0.5" />
                        <div>
                            <span className="font-medium">Validation bloquée.</span>
                            {' '}{pointsNonConformes} point{pointsNonConformes > 1 ? 's' : ''} non conforme{pointsNonConformes > 1 ? 's' : ''} détecté{pointsNonConformes > 1 ? 's' : ''}.
                            Corrigez ou renvoyez le dossier en correction.
                        </div>
                    </motion.div>
                )}

            </div>
        </AppLayout>
    );
}
