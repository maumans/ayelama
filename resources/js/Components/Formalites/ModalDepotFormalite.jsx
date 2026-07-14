import { useState, useEffect, useMemo } from 'react';
import { router } from '@inertiajs/react';
import { Zap, Banknote, AlertTriangle } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DateField } from '@/components/ui/date-field';
import { NumberField } from '@/components/ui/number-field';
import { isoDateToFR, frDateToISO } from '@/lib/dates';
import { notifyValidationError } from '@/lib/toast';
import { PieceGedRow } from '@/Components/Formalites/PieceGedRow';

const fmt = (n) => n ? Number(n).toLocaleString('fr-FR') : '0';

function todayISO() {
    return new Date().toISOString().slice(0, 10);
}

function dateRetourPrevueLabel(dateDepotISO, delaiHeures) {
    if (!dateDepotISO || !delaiHeures) return null;
    const d = new Date(dateDepotISO + 'T00:00:00');
    d.setTime(d.getTime() + delaiHeures * 3600 * 1000);
    return d.toLocaleDateString('fr-FR');
}

export function ModalDepotFormalite({ open, onClose, formalite, onTogglePiece }) {
    const [dateDepot, setDateDepot] = useState(todayISO());
    const [montantPaye, setMontantPaye] = useState('');
    const [numeroRecepisse, setNumeroRecepisse] = useState('');

    useEffect(() => {
        if (open) {
            setDateDepot(todayISO());
            setMontantPaye(formalite?.montant_calcule ? String(formalite.montant_calcule) : '');
            setNumeroRecepisse('');
        }
    }, [open, formalite?.id]);

    const retourPrevue = useMemo(
        () => dateRetourPrevueLabel(dateDepot, formalite?.delai_heures),
        [dateDepot, formalite?.delai_heures]
    );

    if (!formalite) return null;

    const pieces = formalite.pieces ?? [];
    const piecesManquantes = pieces.filter(p => !p.est_fourni).length;
    const peutConfirmer = piecesManquantes === 0;

    const submit = (e) => {
        e.preventDefault();
        router.post(`/formalites/${formalite.id}/deposer`, {
            date_depot: dateDepot,
            montant_paye: montantPaye || null,
            numero_recepisse: numeroRecepisse,
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
                    <DialogTitle>Enregistrer un dépôt — {formalite.libelle}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4 pt-1">
                    {pieces.length > 0 && (
                        <div className="rounded-lg border border-slate-200">
                            <div className="flex items-center justify-between px-3 py-2 border-b border-slate-100">
                                <span className="text-xs font-medium text-slate-500 uppercase tracking-wide">Pièces requises</span>
                                {formalite.bareme_id && (
                                    <span className="inline-flex items-center gap-1 text-[10px] bg-seal-light text-seal px-2 py-0.5 rounded-full">
                                        <Zap className="h-3 w-3" /> Auto depuis template {formalite.dossier?.typeActe} × {formalite.organismeLabel}
                                    </span>
                                )}
                            </div>
                            <div className="px-2 py-1 divide-y divide-slate-50">
                                {pieces.map(p => (
                                    <PieceGedRow key={p.id} piece={p} peutGerer onToggle={onTogglePiece} />
                                ))}
                            </div>
                            {piecesManquantes > 0 && (
                                <div className="flex items-center gap-2 px-3 py-2 border-t border-amber-100 bg-amber-50 text-xs text-amber-800">
                                    <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                                    {piecesManquantes} pièce{piecesManquantes > 1 ? 's' : ''} manquante{piecesManquantes > 1 ? 's' : ''} — à cocher (ou téléverser) avant de confirmer le dépôt
                                </div>
                            )}
                        </div>
                    )}

                    <div className="flex items-center gap-2 bg-warning-bg border border-amber-200 rounded-lg px-3 py-2">
                        <Banknote className="h-4 w-4 text-warning-text shrink-0" />
                        <span className="text-sm text-warning-text">
                            Montant à emporter : <strong className="font-ref">{fmt(formalite.montant_calcule)} GNF</strong> — calculé auto (frais {formalite.organismeLabel}
                            {formalite.libelle && formalite.libelle !== formalite.organismeLabel ? ` · ${formalite.libelle}` : ''})
                        </span>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Date de dépôt (défaut : aujourd'hui)</Label>
                            <DateField
                                value={isoDateToFR(dateDepot)}
                                onValueChange={val => setDateDepot(frDateToISO(val))}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Montant réellement payé (GNF)</Label>
                            <NumberField value={montantPaye} onValueChange={setMontantPaye} placeholder="0" />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <Label>N° Récépissé <span className="text-danger">*</span></Label>
                        <Input
                            value={numeroRecepisse}
                            onChange={e => setNumeroRecepisse(e.target.value)}
                            placeholder="Ex: APIP-2026-05-XXXX"
                            required
                        />
                    </div>

                    {retourPrevue && (
                        <div className="flex items-center gap-2 bg-success-bg border border-green-200 rounded-lg px-3 py-2">
                            <Zap className="h-4 w-4 text-success shrink-0" />
                            <span className="text-sm text-success-text">
                                Date retour prévue : <strong>{retourPrevue}</strong> — calculée auto (dépôt + délai légal {formalite.organismeLabel} {formalite.delai_heures}h)
                            </span>
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button
                            type="submit"
                            variant="seal"
                            disabled={!peutConfirmer}
                            title={!peutConfirmer ? 'Cochez toutes les pièces requises avant de confirmer' : ''}
                        >
                            Confirmer le dépôt
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
