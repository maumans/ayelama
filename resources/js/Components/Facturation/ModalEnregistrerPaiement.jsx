import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DateField } from '@/components/ui/date-field';
import { NumberField } from '@/components/ui/number-field';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { isoDateToFR, frDateToISO } from '@/lib/dates';
import { notifyValidationError } from '@/lib/toast';
import { AlertTriangle } from 'lucide-react';

const fmtGNF = (n) => Number(n || 0).toLocaleString('fr-FR');

const MOYENS_PAIEMENT = [
    { value: 'especes', label: 'Espèces' },
    { value: 'cheque', label: 'Chèque' },
    { value: 'virement', label: 'Virement' },
    { value: 'mobile_money', label: 'Mobile money' },
];

function todayISO() {
    return new Date().toISOString().slice(0, 10);
}

export function ModalEnregistrerPaiement({ open, onClose, dossierReference, soldeRestant = 0, totalFacture = 0, paiement = null }) {
    const estModification = !!paiement;

    const [datePaiement, setDatePaiement] = useState(todayISO());
    const [montant, setMontant] = useState('');
    const [moyenPaiement, setMoyenPaiement] = useState('');
    const [notes, setNotes] = useState('');

    useEffect(() => {
        if (open) {
            setDatePaiement(paiement ? frDateToISO(paiement.date_paiement) : todayISO());
            setMontant(paiement ? String(paiement.montant) : '');
            setMoyenPaiement(paiement?.moyen_paiement ?? '');
            setNotes(paiement?.notes ?? '');
        }
    }, [open, paiement]);

    // La somme des paiements ne peut pas dépasser le total facturé (invariant
    // contrôlé côté serveur sous verrou — voir FactureController::assertMontantDansSolde).
    // En modification, le paiement édité libère son propre montant.
    const montantNum = Number(montant) || 0;
    const soldeDisponible = Math.max(
        0,
        Number((soldeRestant + (estModification ? Number(paiement?.montant) || 0 : 0)).toFixed(2)),
    );
    const factureSoldee = totalFacture > 0 && soldeDisponible <= 0;
    const montantVide = montantNum <= 0;
    const montantExcessif = montantNum > soldeDisponible;
    const blocage = totalFacture <= 0 || factureSoldee || montantExcessif;

    const enregistrer = () => {
        const payload = {
            date_paiement: datePaiement,
            montant,
            moyen_paiement: moyenPaiement || null,
            notes: notes || null,
        };
        const options = {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: notifyValidationError,
        };

        if (estModification) {
            router.patch(`/paiements/${paiement.id}`, payload, options);
        } else {
            router.post(`/dossiers/${dossierReference}/paiements`, payload, options);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        if (blocage || montantVide) return;
        enregistrer();
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{estModification ? 'Modifier le paiement' : 'Enregistrer un paiement'}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4 pt-1">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Date du paiement</Label>
                            <DateField
                                value={isoDateToFR(datePaiement)}
                                onValueChange={val => setDatePaiement(frDateToISO(val))}
                            />
                        </div>
                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between">
                                <Label>Montant (GNF) <span className="text-danger">*</span></Label>
                                {soldeDisponible > 0 && (
                                    <button
                                        type="button"
                                        onClick={() => setMontant(String(soldeDisponible))}
                                        className="text-[11px] text-seal hover:underline"
                                    >
                                        Solder ({fmtGNF(soldeDisponible)})
                                    </button>
                                )}
                            </div>
                            <NumberField
                                value={montant}
                                onValueChange={setMontant}
                                placeholder="0"
                                aria-invalid={montantExcessif}
                                className={montantExcessif ? 'border-danger focus-visible:ring-danger/30' : undefined}
                                required
                            />
                        </div>
                    </div>

                    <p className="text-xs text-slate-500 -mt-2">
                        Maximum encaissable{estModification ? ' sur ce paiement' : ''} :{' '}
                        <span className="font-medium font-ref">{fmtGNF(soldeDisponible)} GNF</span>
                        {soldeRestant < 0 && (
                            <> — trop-perçu existant de <span className="font-medium font-ref text-danger">{fmtGNF(Math.abs(soldeRestant))} GNF</span></>
                        )}
                    </p>

                    {totalFacture <= 0 && (
                        <div className="flex items-start gap-2 p-3 rounded-lg bg-danger-bg border border-danger/20 text-xs text-danger-text">
                            <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
                            <span>
                                Cette facture n'a aucun montant à encaisser (total à 0 GNF). Ajoutez d'abord
                                une ligne à la note de frais.
                            </span>
                        </div>
                    )}

                    {factureSoldee && (
                        <div className="flex items-start gap-2 p-3 rounded-lg bg-success-bg border border-success/20 text-xs text-success-text">
                            <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
                            <span>
                                Cette facture est entièrement soldée ({fmtGNF(totalFacture)} GNF encaissés).
                                Aucun paiement supplémentaire ne peut être enregistré.
                            </span>
                        </div>
                    )}

                    {montantExcessif && !factureSoldee && totalFacture > 0 && (
                        <div className="flex items-start gap-2 p-3 rounded-lg bg-danger-bg border border-danger/20 text-xs text-danger-text">
                            <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
                            <span>
                                {fmtGNF(montantNum)} GNF dépasse le solde restant dû. Le total des paiements
                                ne peut pas excéder le total facturé ({fmtGNF(totalFacture)} GNF) — maximum
                                ici : {fmtGNF(soldeDisponible)} GNF.
                            </span>
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <Label>Moyen de paiement <span className="text-slate-400 text-xs">(optionnel)</span></Label>
                        <Select value={moyenPaiement || '__none__'} onValueChange={v => setMoyenPaiement(v === '__none__' ? '' : v)}>
                            <SelectTrigger><SelectValue placeholder="Non précisé" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">Non précisé</SelectItem>
                                {MOYENS_PAIEMENT.map(m => (
                                    <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="space-y-1.5">
                        <Label>Notes <span className="text-slate-400 text-xs">(optionnel)</span></Label>
                        <Input value={notes} onChange={e => setNotes(e.target.value)} placeholder="Précision éventuelle…" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit" variant="seal" disabled={blocage || montantVide}>
                            {estModification ? 'Enregistrer les modifications' : 'Enregistrer le paiement'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
