import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField } from '@/components/ui/number-field';
import { notifyValidationError } from '@/lib/toast';

export function ModalLigneFacture({ open, onClose, factureId, ligne = null }) {
    const estModification = !!ligne;

    const [designation, setDesignation] = useState('');
    const [quantite, setQuantite] = useState('1');
    const [montant, setMontant] = useState('');

    useEffect(() => {
        if (open) {
            setDesignation(ligne?.designation ?? '');
            setQuantite(ligne ? String(ligne.quantite) : '1');
            setMontant(ligne ? String(ligne.montant) : '');
        }
    }, [open, ligne]);

    const submit = (e) => {
        e.preventDefault();
        const payload = { designation, quantite: parseInt(quantite, 10) || 1, montant };
        const options = { preserveScroll: true, onSuccess: () => onClose(), onError: notifyValidationError };

        if (estModification) {
            router.patch(`/lignes/${ligne.id}`, payload, options);
        } else {
            router.post(`/factures/${factureId}/lignes`, payload, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{estModification ? 'Modifier la ligne' : 'Ajouter une ligne'}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4 pt-1">
                    <div className="space-y-1.5">
                        <Label>Désignation <span className="text-danger">*</span></Label>
                        <Input value={designation} onChange={e => setDesignation(e.target.value)} placeholder="ex : Timbres fiscaux & rôles" required />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Quantité <span className="text-danger">*</span></Label>
                            <NumberField value={quantite} onValueChange={setQuantite} placeholder="1" required />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Montant unitaire (GNF) <span className="text-danger">*</span></Label>
                            <NumberField value={montant} onValueChange={setMontant} placeholder="0" required />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit" variant="seal">
                            {estModification ? 'Enregistrer les modifications' : 'Ajouter la ligne'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
