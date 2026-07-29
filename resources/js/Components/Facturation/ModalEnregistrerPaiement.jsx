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
    const [confirmationRequise, setConfirmationRequise] = useState(false);

    useEffect(() => {
        if (open) {
            setDatePaiement(paiement ? frDateToISO(paiement.date_paiement) : todayISO());
            setMontant(paiement ? String(paiement.montant) : '');
            setMoyenPaiement(paiement?.moyen_paiement ?? '');
            setNotes(paiement?.notes ?? '');
            setConfirmationRequise(false);
        }
    }, [open, paiement]);

    // Un montant très supérieur au total de la facture est presque toujours une faute de
    // frappe (chiffre en trop) plutôt qu'une vraie provision d'avance — on ne bloque pas
    // (payer plus que le dû reste normal), mais on demande une confirmation explicite.
    const montantNum = Number(montant) || 0;
    const montantSuspect = totalFacture > 0 && montantNum > totalFacture * 2;

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
        if (montantSuspect && !confirmationRequise) {
            setConfirmationRequise(true);
            return;
        }
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
                            <Label>Montant (GNF) <span className="text-danger">*</span></Label>
                            <NumberField
                                value={montant}
                                onValueChange={val => { setMontant(val); setConfirmationRequise(false); }}
                                placeholder="0"
                                required
                            />
                        </div>
                    </div>

                    <p className="text-xs text-slate-500 -mt-2">
                        {soldeRestant > 0
                            ? <>Solde restant dû : <span className="font-medium font-ref">{fmtGNF(soldeRestant)} GNF</span></>
                            : <>Trop-perçu actuel : <span className="font-medium font-ref text-success">{fmtGNF(Math.abs(soldeRestant))} GNF</span></>}
                    </p>

                    {montantSuspect && (
                        <div className="flex items-start gap-2 p-3 rounded-lg bg-amber-50 border border-amber-200 text-xs text-amber-800">
                            <AlertTriangle className="h-4 w-4 shrink-0 mt-0.5" />
                            <span>
                                Ce montant ({fmtGNF(montantNum)} GNF) est plus du double du total de la facture
                                ({fmtGNF(totalFacture)} GNF) — vérifiez qu'il n'y a pas d'erreur de saisie avant de confirmer.
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
                        <Button type="submit" variant={confirmationRequise ? 'warning' : 'seal'}>
                            {confirmationRequise ? 'Confirmer malgré tout' : estModification ? 'Enregistrer les modifications' : 'Enregistrer le paiement'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
