import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField } from '@/components/ui/number-field';
import { notifyValidationError } from '@/lib/toast';
import { cn } from '@/lib/utils';

const formaterGNF = (n) => new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(n || 0));

/** Arrondi à deux décimales, sans traîne flottante — 4500000 × 10 / 100 doit donner 450000. */
const arrondi2 = (n) => Math.round((Number(n) || 0) * 100) / 100;

export function ModalLigneFacture({ open, onClose, factureId, ligne = null }) {
    const estModification = !!ligne;

    const [designation, setDesignation] = useState('');
    const [quantite, setQuantite] = useState('1');
    const [montant, setMontant] = useState('');

    // La remise se saisit sous **deux formes liées** : on tape l'une, l'autre se calcule.
    // `remiseType` retient laquelle a été saisie — c'est elle qui est envoyée au serveur, parce
    // que les deux ne se comportent pas pareil quand le tarif change : un pourcentage suit le
    // nouveau montant, un montant reste ce qu'il est.
    const [remiseType, setRemiseType] = useState(null);
    const [remiseMontant, setRemiseMontant] = useState('');
    const [remisePourcent, setRemisePourcent] = useState('');

    const remiseAutorisee = ligne ? ligne.remiseAutorisee !== false : true;

    useEffect(() => {
        if (!open) return;

        setDesignation(ligne?.designation ?? '');
        setQuantite(ligne ? String(ligne.quantite) : '1');
        setMontant(ligne ? String(ligne.montant) : '');

        // On repart de ce qui a été saisi, et on affiche l'autre forme telle que le serveur
        // l'a calculée — les deux vues d'une même remise.
        setRemiseType(ligne?.remiseType ?? null);
        setRemiseMontant(ligne?.remiseMontant ? String(ligne.remiseMontant) : '');
        setRemisePourcent(ligne?.remiseMontant ? String(ligne.remisePourcentage) : '');
    }, [open, ligne]);

    const brut = arrondi2((parseInt(quantite, 10) || 0) * (parseFloat(montant) || 0));

    // Saisir l'un met l'autre à jour. La valeur envoyée reste celle que l'utilisateur a tapée :
    // recalculer l'une depuis l'autre à l'aller **et** au retour ferait dériver les arrondis.
    const saisirMontant = (val) => {
        setRemiseType('montant');
        setRemiseMontant(val);
        setRemisePourcent(brut > 0 && val !== '' ? String(arrondi2((parseFloat(val) || 0) / brut * 100)) : '');
    };

    const saisirPourcent = (val) => {
        setRemiseType('pourcentage');
        setRemisePourcent(val);
        setRemiseMontant(brut > 0 && val !== '' ? String(arrondi2(brut * (parseFloat(val) || 0) / 100)) : '');
    };

    const retirerRemise = () => {
        setRemiseType(null);
        setRemiseMontant('');
        setRemisePourcent('');
    };

    const valeurSaisie = remiseType === 'pourcentage' ? remisePourcent : remiseMontant;
    const remiseEffective = remiseType && valeurSaisie !== ''
        ? Math.min(arrondi2(remiseType === 'pourcentage' ? brut * (parseFloat(remisePourcent) || 0) / 100 : parseFloat(remiseMontant) || 0), brut)
        : 0;
    const net = arrondi2(brut - remiseEffective);

    const depasse = remiseType === 'montant' && (parseFloat(remiseMontant) || 0) > brut;
    const depasseCent = remiseType === 'pourcentage' && (parseFloat(remisePourcent) || 0) > 100;

    const submit = (e) => {
        e.preventDefault();

        const payload = {
            designation,
            quantite: parseInt(quantite, 10) || 1,
            montant,
            // Envoyé seulement en modification : une ligne nouvelle se crée d'abord, sa remise
            // se pose ensuite — la route de création ne la connaît pas.
            ...(estModification ? {
                remise_type:   remiseType,
                remise_valeur: remiseType ? (valeurSaisie === '' ? null : valeurSaisie) : null,
            } : {}),
        };

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

                    {/* ── Remise ────────────────────────────────────────────────────────
                        Deux champs, une seule remise. Saisir l'un remplit l'autre ; celui
                        que l'utilisateur a tapé est celui qui est stocké, parce qu'un
                        pourcentage suit un tarif qui change et un montant non. */}
                    {estModification && remiseAutorisee && (
                        <div className="space-y-2 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                            <div className="flex items-center justify-between">
                                <Label className="text-xs uppercase tracking-wide text-slate-500">Remise</Label>
                                {remiseType && (
                                    <button type="button" onClick={retirerRemise}
                                        className="text-xs text-slate-500 hover:text-danger">
                                        Retirer
                                    </button>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-normal text-slate-500">En montant (GNF)</Label>
                                    <NumberField
                                        value={remiseMontant}
                                        onValueChange={saisirMontant}
                                        placeholder="0"
                                        className={cn(depasse && 'border-danger')}
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label className="text-xs font-normal text-slate-500">En pourcentage (%)</Label>
                                    <NumberField
                                        value={remisePourcent}
                                        onValueChange={saisirPourcent}
                                        placeholder="0"
                                        className={cn(depasseCent && 'border-danger')}
                                    />
                                </div>
                            </div>

                            {(depasse || depasseCent) && (
                                <p className="text-xs text-danger">
                                    {depasseCent
                                        ? 'Une remise ne peut pas dépasser 100 %.'
                                        : `La remise ne peut pas dépasser le montant de la ligne (${formaterGNF(brut)} GNF).`}
                                </p>
                            )}

                            <div className="flex items-baseline justify-between border-t border-slate-200 pt-2 text-sm">
                                <span className="text-slate-500">
                                    {formaterGNF(brut)} GNF
                                    {remiseEffective > 0 && <span className="text-danger"> − {formaterGNF(remiseEffective)}</span>}
                                </span>
                                <span className="font-semibold tabular-nums text-ink">{formaterGNF(net)} GNF</span>
                            </div>
                        </div>
                    )}

                    {/* Un débours ne se remise pas : l'étude le verse intégralement au tiers.
                        On explique au lieu de masquer — le réglage se change au barème. */}
                    {estModification && !remiseAutorisee && (
                        <p className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">
                            Cette ligne n'accepte pas de remise : c'est un débours, que l'étude verse
                            intégralement à un tiers. Le réglage se change sur son barème, dans
                            Paramètres&nbsp;→&nbsp;Barèmes.
                        </p>
                    )}

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit" variant="seal" disabled={depasse || depasseCent}>
                            {estModification ? 'Enregistrer les modifications' : 'Ajouter la ligne'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
