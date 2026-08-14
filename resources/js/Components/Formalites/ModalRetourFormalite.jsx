import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { CheckCircle2, AlertTriangle, Zap } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

import { Label } from '@/components/ui/label';
import { DateField } from '@/components/ui/date-field';
import { isoDateToFR, frDateToISO } from '@/lib/dates';
import { notifyValidationError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { PieceGedRow } from '@/Components/Formalites/PieceGedRow';

function todayISO() {
    return new Date().toISOString().slice(0, 10);
}

export function ModalRetourFormalite({ open, onClose, formalite }) {
    const [dateRetour, setDateRetour] = useState(todayISO());

    useEffect(() => {
        if (open && formalite?.id) {
            setDateRetour(todayISO());
        }
    }, [open, formalite?.id]);

    const [previewPieceId, setPreviewPieceId] = useState(null);

    if (!formalite) return null;

    const pieces = formalite.pieces ?? [];
    const piecesManquantes = pieces.filter(p => !p.est_fourni).length;
    const peutConfirmer = piecesManquantes === 0;

    const submit = (e) => {
        e.preventDefault();
        router.post(`/formalites/${formalite.id}/retour`, {
            date_retour: dateRetour,
        }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: notifyValidationError,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Enregistrer un retour — {formalite.libelle}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4 pt-1">
                    <div className="space-y-1.5">
                        <Label>Date retour effectif <span className="text-danger">*</span></Label>
                        <DateField
                            value={isoDateToFR(dateRetour)}
                            onValueChange={val => setDateRetour(frDateToISO(val))}
                        />
                    </div>

                    {pieces.length > 0 && (
                        <div className="rounded-lg border border-slate-200">
                            <div className="flex items-center justify-between px-3 py-2 border-b border-slate-100">
                                <span className="text-xs font-medium text-slate-500 uppercase tracking-wide">Pièces requises pour le retour</span>
                            </div>
                            <div className="px-2 py-1 divide-y divide-slate-50">
                                {pieces.map(p => (
                                    <PieceGedRow
                                        key={p.id}
                                        piece={p}
                                        peutGerer={true}
                                        isPreviewOpen={previewPieceId === p.id}
                                        onTogglePreview={(piece) => setPreviewPieceId(id => id === piece.id ? null : piece.id)}
                                    />
                                ))}
                            </div>
                            {piecesManquantes > 0 && (
                                <div className="flex items-center gap-2 px-3 py-2 border-t border-amber-100 bg-amber-50 text-xs text-amber-800">
                                    <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                                    {piecesManquantes} pièce{piecesManquantes > 1 ? 's' : ''} manquante{piecesManquantes > 1 ? 's' : ''} — à téléverser avant de confirmer le retour
                                </div>
                            )}
                        </div>
                    )}

                    {formalite.aDesDependants && (
                        <div className="flex items-center gap-2 bg-success-bg border border-green-200 rounded-lg px-3 py-2">
                            <Zap className="h-4 w-4 text-success shrink-0" />
                            <span className="text-sm text-success-text">
                                Après validation : {formalite.dependantsLabels.join(', ')} se débloquera{formalite.dependantsLabels.length > 1 ? 'ont' : ''} automatiquement — {formalite.dependantsLabels.length > 1 ? 'elles dépendent' : 'elle dépend'} de ce retour.
                            </span>
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button 
                            type="submit" 
                            variant="success"
                            disabled={!peutConfirmer}
                            title={!peutConfirmer ? 'Téléversez toutes les pièces requises avant d\'enregistrer' : ''}
                        >
                            Enregistrer le retour
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
