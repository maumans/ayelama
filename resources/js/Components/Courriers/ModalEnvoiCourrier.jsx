import { useEffect, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { AlertTriangle, Send } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DateField } from '@/components/ui/date-field';
import { frDateToISO, isoDateToFR } from '@/lib/dates';
import { notifyValidationError } from '@/lib/toast';

/**
 * Enregistrer la transmission d'un courrier : **date** et **personne qui transmet**.
 *
 * Demande de l'étude (2026-11-17) : un clic sur « Marquer envoyé » suffisait, daté du clic et sans
 * personne nommée. Partagée par les trois endroits qui marquaient un courrier envoyé — les deux
 * listes de l'onglet Expédition et l'écran Courriers — pour qu'ils ne divergent pas.
 *
 * Les bornes viennent du serveur (`Courrier::bornesDateEnvoi()`), les mêmes que sa validation ;
 * `min`/`max` ne bornent que le calendrier, une date tapée au clavier est donc aussi contrôlée ici.
 * Le transmetteur est prérempli avec l'utilisateur connecté, mais reste libre : ce peut être un
 * coursier ou un tiers.
 */
export function ModalEnvoiCourrier({ courrier, onClose }) {
    const connecte = usePage().props.auth?.user?.name ?? '';
    const bornes = courrier?.bornes_envoi;

    const [date, setDate] = useState('');
    const [transmisPar, setTransmisPar] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (courrier) {
            setDate(bornes?.max ?? '');
            setTransmisPar(connecte);
        }
    }, [courrier?.id]);

    // Dates ISO : la comparaison de chaînes AAAA-MM-JJ suit l'ordre chronologique.
    const erreurDate = !date
        ? 'La date de transmission est obligatoire.'
        : bornes && date > bornes.max
          ? 'La date de transmission ne peut pas être dans le futur.'
          : bornes && date < bornes.min
            ? `Un courrier ne peut pas être transmis avant sa création (${isoDateToFR(bornes.min)}).`
            : null;
    const erreurTransmis = transmisPar.trim() ? null : 'Indiquez la personne qui transmet le courrier.';

    const submit = (e) => {
        e.preventDefault();
        if (erreurDate || erreurTransmis) return;
        setSaving(true);
        router.post(
            `/courriers/${courrier.id}/envoyer`,
            { envoye_le: date, transmis_par: transmisPar.trim() },
            {
                preserveScroll: true,
                preserveState: true,
                onError: notifyValidationError,
                onSuccess: onClose,
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <Dialog open={!!courrier} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Transmission — {courrier?.objet}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1.5">
                        <Label>Date de transmission</Label>
                        <DateField
                            value={isoDateToFR(date)}
                            onValueChange={(val) => setDate(frDateToISO(val))}
                            min={bornes?.min}
                            max={bornes?.max}
                        />
                        {erreurDate && date && (
                            <p className="flex items-center gap-1 text-xs text-danger">
                                <AlertTriangle className="h-3 w-3 shrink-0" />
                                {erreurDate}
                            </p>
                        )}
                    </div>
                    <div className="space-y-1.5">
                        <Label>Transmis par</Label>
                        <Input
                            value={transmisPar}
                            onChange={(e) => setTransmisPar(e.target.value)}
                            maxLength={150}
                            placeholder="Nom de la personne qui transmet"
                        />
                        {erreurTransmis && (
                            <p className="flex items-center gap-1 text-xs text-danger">
                                <AlertTriangle className="h-3 w-3 shrink-0" />
                                {erreurTransmis}
                            </p>
                        )}
                    </div>
                    <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <Button type="button" variant="ghost" onClick={onClose} disabled={saving}>
                            Annuler
                        </Button>
                        <Button type="submit" variant="seal" className="gap-1.5" disabled={saving || !!erreurDate || !!erreurTransmis}>
                            <Send className="h-3.5 w-3.5" />
                            {saving ? 'Enregistrement…' : 'Marquer envoyé'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
