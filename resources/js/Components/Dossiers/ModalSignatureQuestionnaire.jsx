import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Eraser, FileText, PenLine } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { notifyValidationError } from '@/lib/toast';

/**
 * Signature du questionnaire **sur l'écran de l'étude** (retour R23, arbitré le 2026-11-16).
 *
 * Le client relit la fiche de recueil, coche qu'il l'approuve, puis signe au doigt (tablette,
 * écran tactile) ou à la souris. Le serveur produit la fiche signée et la range comme l'accord
 * papier (DossierController::signerAccordClient).
 *
 * Tracé par **Pointer Events** : un seul code pour la souris, le doigt et le stylet, sans
 * dépendance. La toile est mise à l'échelle de l'écran (devicePixelRatio) pour un trait net, et
 * `touch-action: none` empêche le doigt de faire défiler la page au lieu de signer.
 */
export function ModalSignatureQuestionnaire({ open, onClose, dossier, signataireParDefaut }) {
    const toile = useRef(null);
    const dessin = useRef({ actif: false, x: 0, y: 0 });
    const [vide, setVide] = useState(true);
    const [signataire, setSignataire] = useState(signataireParDefaut ?? '');
    const [approuve, setApprouve] = useState(false);
    const [envoi, setEnvoi] = useState(false);

    /**
     * Donner à la toile sa taille réelle × la densité de l'écran, et le style du trait.
     *
     * ⚠️ Appelée **au premier contact**, et non seulement à l'ouverture : la toile n'a sa taille
     * affichée qu'une fois la modale montée et animée. Préparée trop tôt, elle gardait sa taille
     * par défaut (300 × 150) pour un cadre affiché en 460 × 160 — trait déformé et coupé au-delà
     * de 300 px (constaté le 2026-11-16). Idempotente : sans effet si la taille est déjà juste.
     */
    const preparer = () => {
        const c = toile.current;
        if (!c) return;
        const ratio = window.devicePixelRatio || 1;
        const { width, height } = c.getBoundingClientRect();
        const largeur = Math.round(width * ratio);
        const hauteur = Math.round(height * ratio);
        if (!width || (c.width === largeur && c.height === hauteur)) return;

        c.width = largeur;
        c.height = hauteur;
        const ctx = c.getContext('2d');
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth = 2.2;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#13294B';
    };

    // À l'ouverture : formulaire remis à zéro, toile préparée dès qu'elle est affichée.
    useEffect(() => {
        if (!open) return;
        setSignataire(signataireParDefaut ?? '');
        setApprouve(false);
        setVide(true);

        const id = requestAnimationFrame(preparer);

        return () => cancelAnimationFrame(id);
    }, [open]);

    const position = (e) => {
        const r = toile.current.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    };

    const debut = (e) => {
        e.preventDefault();
        preparer();
        toile.current.setPointerCapture(e.pointerId);
        dessin.current = { actif: true, ...position(e) };
    };

    const trace = (e) => {
        if (!dessin.current.actif) return;
        const ctx = toile.current.getContext('2d');
        const { x, y } = position(e);
        ctx.beginPath();
        ctx.moveTo(dessin.current.x, dessin.current.y);
        ctx.lineTo(x, y);
        ctx.stroke();
        dessin.current = { actif: true, x, y };
        if (vide) setVide(false);
    };

    const fin = () => {
        dessin.current.actif = false;
    };

    const effacer = () => {
        const c = toile.current;
        c.getContext('2d').clearRect(0, 0, c.width, c.height);
        setVide(true);
    };

    const valider = () => {
        setEnvoi(true);
        router.post(
            `/dossiers/${dossier.reference}/accord-client/signer`,
            { signature: toile.current.toDataURL('image/png'), signataire, approuve },
            {
                preserveScroll: true,
                onError: notifyValidationError,
                onSuccess: onClose,
                onFinish: () => setEnvoi(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && !envoi && onClose()}>
            <DialogContent className="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Signature du questionnaire</DialogTitle>
                </DialogHeader>

                <div className="space-y-4">
                    <div className="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
                        <span>Le client relit d'abord la fiche de recueil du dossier {dossier.reference}.</span>
                        <Button size="sm" variant="outline" className="h-8 shrink-0 gap-1.5" asChild>
                            <a href={`/dossiers/${dossier.reference}/fiche-recueil`} target="_blank" rel="noopener noreferrer">
                                <FileText className="h-3.5 w-3.5" /> Relire la fiche
                            </a>
                        </Button>
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="signataire">Nom du signataire (client principal)</Label>
                        <Input id="signataire" value={signataire} onChange={(e) => setSignataire(e.target.value)} maxLength={200} />
                    </div>

                    <div className="space-y-1.5">
                        <div className="flex items-center justify-between">
                            <Label>Signature</Label>
                            <button type="button" onClick={effacer} disabled={vide} className="flex items-center gap-1 text-xs text-slate-500 hover:text-seal disabled:opacity-40">
                                <Eraser className="h-3 w-3" /> Effacer
                            </button>
                        </div>
                        <canvas
                            ref={toile}
                            aria-label="Zone de signature"
                            className="block h-40 w-full cursor-crosshair rounded-lg border-2 border-dashed border-slate-300 bg-white"
                            style={{ touchAction: 'none' }}
                            onPointerDown={debut}
                            onPointerMove={trace}
                            onPointerUp={fin}
                            onPointerLeave={fin}
                            onPointerCancel={fin}
                        />
                        {vide && <p className="text-xs text-slate-400">Signez dans le cadre, au doigt ou à la souris.</p>}
                    </div>

                    <label className="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" className="mt-0.5 h-4 w-4 rounded border-slate-300 text-seal" checked={approuve} onChange={(e) => setApprouve(e.target.checked)} />
                        <span>Le client a relu le questionnaire et en approuve le contenu.</span>
                    </label>

                    <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                        <Button type="button" variant="ghost" onClick={onClose} disabled={envoi}>Annuler</Button>
                        <Button
                            type="button"
                            variant="seal"
                            className="gap-1.5"
                            onClick={valider}
                            disabled={envoi || vide || !approuve || !signataire.trim()}
                        >
                            <PenLine className="h-4 w-4" />
                            {envoi ? 'Enregistrement…' : 'Valider la signature'}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
