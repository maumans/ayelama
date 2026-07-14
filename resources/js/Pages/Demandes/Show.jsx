import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { QUESTIONNAIRES, TYPE_ACTE_CODE_MAP } from '@/data/questionnaires';
import { notifyValidationError, toast } from '@/lib/toast';
import { Copy, ArrowRight, ImageIcon, Check, ChevronLeft } from 'lucide-react';

const STATUT_LABELS = {
    en_attente: 'En attente', soumise: 'Soumise', traitee: 'Traitée', expiree: 'Expirée',
};

export default function DemandeShow() {
    const { demande, notaires, reviseurs, formalistes } = usePage().props;

    const [objet, setObjet] = useState(demande.objet ?? '');
    const [notaireId, setNotaireId] = useState('');
    const [reviseurId, setReviseurId] = useState('');
    const [formalisteId, setFormalisteId] = useState('');
    const [urgent, setUrgent] = useState(false);
    const [notes, setNotes] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});

    const key = TYPE_ACTE_CODE_MAP[demande.typeActe.code];
    const questionnaire = key ? (QUESTIONNAIRES[key] ?? []) : [];
    const labelMap = Object.fromEntries(questionnaire.map(f => [f.id, f.label]));

    const donneesEntries = Object.entries(demande.donnees ?? {}).filter(([, v]) => v !== null && v !== '' && !Array.isArray(v));
    const donneesRepeatable = Object.entries(demande.donnees ?? {}).filter(([, v]) => Array.isArray(v) && v.length);

    const copierLien = () => {
        navigator.clipboard.writeText(demande.url).then(() => toast.success('Lien copié.'));
    };

    const convertir = () => {
        setSubmitting(true);
        router.post(`/demandes/${demande.id}/convertir`, {
            objet,
            notaire_id: notaireId,
            reviseur_id: reviseurId || undefined,
            formaliste_id: formalisteId || undefined,
            urgent,
            notes: notes || undefined,
        }, {
            onError: (errs) => { setErrors(errs); setSubmitting(false); notifyValidationError(errs); },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <AppLayout breadcrumbs={[{ label: 'Demandes clients', href: '/demandes' }, { label: `#${demande.id}` }]}>
            <Head title={`Demande #${demande.id} — Ayelema`} />
            <div className="p-6 max-w-[900px] mx-auto space-y-5">

                <Link href="/demandes" className="flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 w-fit">
                    <ChevronLeft className="h-3.5 w-3.5" /> Retour aux demandes
                </Link>

                <div className="flex items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2 flex-wrap">
                            <h1 className="font-serif text-display text-ink">{demande.typeActe.label}</h1>
                            <Badge variant="secondary">{STATUT_LABELS[demande.statut] ?? demande.statut}</Badge>
                        </div>
                        {demande.clientRole && <p className="text-sm text-slate-500 mt-1">Rôle : {demande.clientRole}</p>}
                        {demande.dossierRef && (
                            <p className="text-sm text-success mt-1">
                                Converti en dossier <Link href={`/dossiers/${demande.dossierRef}`} className="font-ref underline">{demande.dossierRef}</Link>
                            </p>
                        )}
                    </div>
                    <Button variant="outline" size="sm" onClick={copierLien}>
                        <Copy className="h-3.5 w-3.5" /> Copier le lien
                    </Button>
                </div>

                {demande.statut === 'en_attente' && (
                    <Card>
                        <CardContent className="p-6 text-center py-12">
                            <p className="text-slate-500 text-sm">En attente de soumission par le client.</p>
                            <p className="text-xs text-slate-400 mt-1">Expire le {demande.expire_at}</p>
                        </CardContent>
                    </Card>
                )}

                {(demande.statut === 'soumise' || demande.statut === 'traitee') && (
                    <>
                        <Card>
                            <CardHeader className="pb-3"><CardTitle className="text-sm">Objet de la demande</CardTitle></CardHeader>
                            <CardContent className="pt-0">
                                <p className="text-sm text-slate-700">{demande.objet || '—'}</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-3"><CardTitle className="text-sm">Informations soumises</CardTitle></CardHeader>
                            <CardContent className="pt-0 space-y-3">
                                {donneesEntries.length === 0 && donneesRepeatable.length === 0 ? (
                                    <p className="text-sm text-slate-400">Aucune information soumise.</p>
                                ) : (
                                    <>
                                        {donneesEntries.map(([id, value]) => (
                                            <div key={id} className="flex gap-3 text-sm">
                                                <span className="text-slate-500 min-w-[180px] shrink-0">{labelMap[id] ?? id}</span>
                                                <span className="text-slate-800 font-medium">{String(value)}</span>
                                            </div>
                                        ))}
                                        {donneesRepeatable.map(([id, items]) => (
                                            <div key={id} className="pt-1">
                                                <p className="text-xs font-semibold text-seal uppercase tracking-wide mb-1">{labelMap[id] ?? id}</p>
                                                {items.map((item, i) => (
                                                    <div key={i} className="text-xs text-slate-600 bg-slate-50 rounded-lg p-2 mb-1">
                                                        {Object.entries(item).map(([k, v]) => `${k}: ${v}`).join(' · ')}
                                                    </div>
                                                ))}
                                            </div>
                                        ))}
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        {demande.has_scan && (
                            <Card>
                                <CardHeader className="pb-3"><CardTitle className="text-sm flex items-center gap-2"><ImageIcon className="h-4 w-4" /> Document scanné</CardTitle></CardHeader>
                                <CardContent className="pt-0">
                                    <a href={demande.url_scan} target="_blank" rel="noreferrer">
                                        <img src={demande.url_scan} alt="Scan soumis" className="max-h-80 rounded-lg border border-slate-200" />
                                    </a>
                                </CardContent>
                            </Card>
                        )}
                    </>
                )}

                {demande.statut === 'soumise' && (
                    <Card className="border-seal/30">
                        <CardHeader className="pb-3"><CardTitle className="text-sm">Créer le dossier</CardTitle></CardHeader>
                        <CardContent className="pt-0 space-y-4">
                            <div className="space-y-1.5">
                                <Label htmlFor="objet">Objet du dossier <span className="text-danger">*</span></Label>
                                <textarea
                                    id="objet" rows={2} value={objet} onChange={e => setObjet(e.target.value)}
                                    className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seal resize-none"
                                />
                                {errors.objet && <p className="text-xs text-danger">{errors.objet}</p>}
                            </div>

                            <div className="space-y-1.5">
                                <Label htmlFor="notaire_id">Notaire en charge <span className="text-danger">*</span></Label>
                                <select
                                    id="notaire_id" value={notaireId} onChange={e => setNotaireId(e.target.value)}
                                    className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal"
                                >
                                    <option value="">Choisir un notaire…</option>
                                    {(notaires ?? []).map(n => <option key={n.id} value={n.id}>{n.name}</option>)}
                                </select>
                                {errors.notaire_id && <p className="text-xs text-danger">{errors.notaire_id}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-1.5">
                                    <Label htmlFor="reviseur_id">Réviseur</Label>
                                    <select
                                        id="reviseur_id" value={reviseurId} onChange={e => setReviseurId(e.target.value)}
                                        className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal"
                                    >
                                        <option value="">Aucun</option>
                                        {(reviseurs ?? []).map(r => <option key={r.id} value={r.id}>{r.name}</option>)}
                                    </select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="formaliste_id">Formaliste</Label>
                                    <select
                                        id="formaliste_id" value={formalisteId} onChange={e => setFormalisteId(e.target.value)}
                                        className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal"
                                    >
                                        <option value="">Aucun</option>
                                        {(formalistes ?? []).map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
                                    </select>
                                </div>
                            </div>

                            <label className="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer w-fit">
                                <Checkbox checked={urgent} onCheckedChange={(c) => setUrgent(c === true)} />
                                Dossier urgent
                            </label>

                            <div className="space-y-1.5">
                                <Label htmlFor="notes">Notes <span className="text-slate-400 text-xs">(optionnel)</span></Label>
                                <textarea
                                    id="notes" rows={2} value={notes} onChange={e => setNotes(e.target.value)}
                                    className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seal resize-none"
                                />
                            </div>

                            <Button variant="seal" size="lg" className="w-full" onClick={convertir} disabled={submitting}>
                                <Check className="h-4 w-4" />
                                {submitting ? 'Création…' : 'Créer le dossier'}
                                <ArrowRight className="h-4 w-4" />
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
