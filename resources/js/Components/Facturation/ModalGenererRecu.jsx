import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { notifyValidationError } from '@/lib/toast';

const fmt = (n) => n ? Number(n).toLocaleString('fr-FR') : '0';

export function ModalGenererRecu({ open, onClose, paiements = [] }) {
    const sansRecu = paiements.filter(p => !p.recu);
    const [paiementId, setPaiementId] = useState('');

    useEffect(() => {
        if (open) {
            setPaiementId(sansRecu.length ? String(sansRecu[0].id) : '');
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const submit = (e) => {
        e.preventDefault();
        if (!paiementId) return;
        router.post(`/paiements/${paiementId}/recu`, {}, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: notifyValidationError,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Générer un reçu</DialogTitle>
                </DialogHeader>

                {sansRecu.length === 0 ? (
                    <div className="py-6 text-center text-sm text-slate-500">
                        Tous les paiements enregistrés ont déjà un reçu.
                    </div>
                ) : (
                    <form onSubmit={submit} className="space-y-4 pt-1">
                        <div className="space-y-1.5">
                            <Label>Paiement concerné</Label>
                            <Select value={paiementId} onValueChange={setPaiementId}>
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {sansRecu.map(p => (
                                        <SelectItem key={p.id} value={String(p.id)}>
                                            {fmt(p.montant)} GNF — {p.date_paiement}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                            <Button type="submit" variant="seal" disabled={!paiementId}>
                                <Receipt className="h-4 w-4" /> Générer le reçu
                            </Button>
                        </DialogFooter>
                    </form>
                )}
            </DialogContent>
        </Dialog>
    );
}
