import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle2, AlertTriangle, Zap } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DateField } from '@/components/ui/date-field';
import { isoDateToFR, frDateToISO } from '@/lib/dates';
import { notifyValidationError } from '@/lib/toast';
import { cn } from '@/lib/utils';

function todayISO() {
    return new Date().toISOString().slice(0, 10);
}

export function ModalRetourFormalite({ open, onClose, formalite }) {
    const [resultat, setResultat] = useState('recu');
    const [dateRetour, setDateRetour] = useState(todayISO());
    const [referenceDocument, setReferenceDocument] = useState('');
    const [autresRetards, setAutresRetards] = useState([]);

    useEffect(() => {
        if (open && formalite?.id) {
            setResultat('recu');
            setDateRetour(todayISO());
            setReferenceDocument('');
            setAutresRetards([]);
            axios.get(`/formalites/${formalite.id}/autres-retards`)
                .then(res => setAutresRetards(res.data?.formalites ?? []))
                .catch(() => {});
        }
    }, [open, formalite?.id]);

    if (!formalite) return null;

    const submit = (e) => {
        e.preventDefault();
        router.post(`/formalites/${formalite.id}/retour`, {
            resultat,
            date_retour: dateRetour,
            reference_document_recu: referenceDocument || null,
        }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: notifyValidationError,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Enregistrer un retour — {formalite.libelle}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4 pt-1">
                    {autresRetards.map(a => (
                        <div key={a.id} className="bg-danger-bg border border-red-200 rounded-lg px-3 py-2.5">
                            <div className="flex items-center gap-2 text-sm font-medium text-danger-text">
                                <AlertTriangle className="h-4 w-4 shrink-0" />
                                {a.libelle} · {a.dossierReference} · {a.dossierObjet}
                            </div>
                            <div className="text-xs text-danger-text/80 mt-0.5 pl-6">
                                Retard de {a.joursRetard} jour{a.joursRetard > 1 ? 's' : ''}
                            </div>
                        </div>
                    ))}

                    <div className="space-y-1.5">
                        <Label>Résultat du retour <span className="text-danger">*</span></Label>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => setResultat('recu')}
                                className={cn(
                                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border transition-all',
                                    resultat === 'recu'
                                        ? 'bg-success text-white border-success shadow-sm'
                                        : 'bg-white text-slate-500 border-slate-200 hover:border-success hover:text-success'
                                )}
                            >
                                <CheckCircle2 className="h-3.5 w-3.5" /> Retour positif — Reçu
                            </button>
                            <button
                                type="button"
                                onClick={() => setResultat('rejete')}
                                className={cn(
                                    'flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border transition-all',
                                    resultat === 'rejete'
                                        ? 'bg-danger text-white border-danger shadow-sm'
                                        : 'bg-white text-slate-500 border-slate-200 hover:border-danger hover:text-danger'
                                )}
                            >
                                <AlertTriangle className="h-3.5 w-3.5" /> Rejeté — à corriger
                            </button>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Date retour effectif <span className="text-danger">*</span></Label>
                            <DateField
                                value={isoDateToFR(dateRetour)}
                                onValueChange={val => setDateRetour(frDateToISO(val))}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Référence document reçu{resultat === 'recu' && <span className="text-danger"> *</span>}</Label>
                            <Input
                                value={referenceDocument}
                                onChange={e => setReferenceDocument(e.target.value)}
                                placeholder="Ex: Extrait RCCM N°456-2026"
                                required={resultat === 'recu'}
                            />
                        </div>
                    </div>

                    {resultat === 'recu' && formalite.aDesDependants && (
                        <div className="flex items-center gap-2 bg-success-bg border border-green-200 rounded-lg px-3 py-2">
                            <Zap className="h-4 w-4 text-success shrink-0" />
                            <span className="text-sm text-success-text">
                                Après validation : {formalite.dependantsLabels.join(', ')} se débloquera{formalite.dependantsLabels.length > 1 ? 'ont' : ''} automatiquement — {formalite.dependantsLabels.length > 1 ? 'elles dépendent' : 'elle dépend'} de ce retour.
                            </span>
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit" variant={resultat === 'recu' ? 'success' : 'destructive'}>Enregistrer le retour</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
