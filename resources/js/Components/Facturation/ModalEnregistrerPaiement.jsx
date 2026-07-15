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

const MOYENS_PAIEMENT = [
    { value: 'especes', label: 'Espèces' },
    { value: 'cheque', label: 'Chèque' },
    { value: 'virement', label: 'Virement' },
    { value: 'mobile_money', label: 'Mobile money' },
];

function todayISO() {
    return new Date().toISOString().slice(0, 10);
}

export function ModalEnregistrerPaiement({ open, onClose, dossierReference }) {
    const [datePaiement, setDatePaiement] = useState(todayISO());
    const [montant, setMontant] = useState('');
    const [moyenPaiement, setMoyenPaiement] = useState('');
    const [notes, setNotes] = useState('');

    useEffect(() => {
        if (open) {
            setDatePaiement(todayISO());
            setMontant('');
            setMoyenPaiement('');
            setNotes('');
        }
    }, [open]);

    const submit = (e) => {
        e.preventDefault();
        router.post(`/dossiers/${dossierReference}/paiements`, {
            date_paiement: datePaiement,
            montant,
            moyen_paiement: moyenPaiement || null,
            notes: notes || null,
        }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: notifyValidationError,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Enregistrer un paiement</DialogTitle>
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
                            <NumberField value={montant} onValueChange={setMontant} placeholder="0" required />
                        </div>
                    </div>

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
                        <Button type="submit" variant="seal">Enregistrer le paiement</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
