import * as React from 'react';
import * as DialogPrimitive from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

const Dialog = DialogPrimitive.Root;
const DialogTrigger = DialogPrimitive.Trigger;
const DialogPortal = DialogPrimitive.Portal;
const DialogClose = DialogPrimitive.Close;

const DialogOverlay = React.forwardRef(({ className, ...props }, ref) => (
    <DialogPrimitive.Overlay
        ref={ref}
        className={cn('fixed inset-0 z-50 bg-ink/40 backdrop-blur-sm data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0', className)}
        {...props}
    />
));
DialogOverlay.displayName = DialogPrimitive.Overlay.displayName;

/**
 * Une interaction née d'un calque flottant Radix — le menu d'un `Select`, d'un `Popover`, d'un
 * `DropdownMenu` — est-elle en cours ?
 *
 * ⚠️ **Pourquoi cette garde existe.** Ces menus sont *portalés* hors du dialogue : dans le DOM ils
 * en sont frères, pas descendants. Choisir « Chèque » dans le moyen de paiement était donc pris
 * pour un clic **hors** du dialogue, et le dialogue se fermait — le paiement en cours de saisie
 * perdu.
 *
 * La règle est juste en elle-même, indépendamment des rouages de Radix : **cliquer dans le menu
 * d'un champ du dialogue n'est pas cliquer dehors**. Un clic sur le voile, la touche Échap et le
 * bouton de fermeture continuent de fermer normalement.
 *
 * Les trois sélecteurs sont les attributs **réellement émis** par Radix, relevés dans les paquets
 * installés : `data-radix-popper-content-wrapper` enveloppe tout contenu positionné en `popper`
 * (le mode par défaut de notre `SelectContent`), `data-radix-select-viewport` couvre le mode
 * `item-aligned` d'un `Select`, et `data-radix-menu-content` les menus déroulants.
 */
const CALQUES_FLOTTANTS = [
    '[data-radix-popper-content-wrapper]',
    '[data-radix-select-viewport]',
    '[data-radix-menu-content]',
].join(',');

/**
 * Les `pointerdown` survenus **pendant qu'un calque flottant était ouvert**.
 *
 * ⚠️ Second cas, relevé le 2026-11-18 en production avec un enregistreur d'événements — la
 * garde ci-dessus ne le voyait pas. Geste de l'utilisateur : ouvrir une liste, puis cliquer
 * **dans la fenêtre, à côté**, pour la refermer. Tant que la liste est ouverte, Radix retire
 * les clics à tout ce qui est sous elle (`disableOutsidePointerEvents`), le contenu du
 * dialogue compris : le clic traverse et tombe sur le **voile**. La liste se ferme ; le
 * dialogue, qui diffère ce clic jusqu'au `click` (`deferPointerDownOutside`), le reçoit
 * ensuite pour un clic sur le voile — et se ferme. Trois fois de suite dans l'enregistrement,
 * jamais reproduit à la souris rapide en développement.
 *
 * La règle reste celle d'une liste native : **le clic qui referme un menu ne fait que le
 * refermer**. Un second clic sur le voile ferme le dialogue comme avant.
 *
 * L'écoute est en phase de capture, donc avant que Radix ne démonte le calque.
 */
const pointerDownsSousCalque = new WeakSet();

if (typeof document !== 'undefined') {
    document.addEventListener(
        'pointerdown',
        (event) => {
            if (document.querySelector(CALQUES_FLOTTANTS)) pointerDownsSousCalque.add(event);
        },
        true,
    );
}

function vientDunCalqueFlottant(event) {
    const origine = event.detail?.originalEvent;
    if (origine && pointerDownsSousCalque.has(origine)) return true;

    const cible = origine?.target ?? event.target;

    return cible instanceof Element && cible.closest(CALQUES_FLOTTANTS) !== null;
}

const DialogContent = React.forwardRef(({ className, children, onInteractOutside, ...props }, ref) => (
    <DialogPortal>
        <DialogOverlay />
        <DialogPrimitive.Content
            ref={ref}
            onInteractOutside={(event) => {
                if (vientDunCalqueFlottant(event)) {
                    event.preventDefault();

                    return;
                }

                onInteractOutside?.(event);
            }}
            className={cn(
                'fixed left-[50%] top-[50%] z-50 grid max-h-[90dvh] w-full max-w-lg translate-x-[-50%] translate-y-[-50%] gap-4 overflow-y-auto border border-slate-200 bg-white p-6 shadow-dialog duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[state=closed]:slide-out-to-left-1/2 data-[state=closed]:slide-out-to-top-[48%] data-[state=open]:slide-in-from-left-1/2 data-[state=open]:slide-in-from-top-[48%] rounded-xl',
                className
            )}
            {...props}
        >
            {children}
            <DialogPrimitive.Close className="absolute right-4 top-4 rounded-md p-1 text-slate-400 opacity-70 ring-offset-white transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-seal focus:ring-offset-2 disabled:pointer-events-none">
                <X className="h-4 w-4" />
                <span className="sr-only">Fermer</span>
            </DialogPrimitive.Close>
        </DialogPrimitive.Content>
    </DialogPortal>
));
DialogContent.displayName = DialogPrimitive.Content.displayName;

const DialogHeader = ({ className, ...props }) => (
    <div className={cn('flex shrink-0 flex-col space-y-1.5', className)} {...props} />
);

const DialogFooter = ({ className, ...props }) => (
    <div className={cn('flex shrink-0 flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2', className)} {...props} />
);

const DialogTitle = React.forwardRef(({ className, ...props }, ref) => (
    <DialogPrimitive.Title ref={ref} className={cn('font-serif text-heading text-ink', className)} {...props} />
));
DialogTitle.displayName = DialogPrimitive.Title.displayName;

const DialogDescription = React.forwardRef(({ className, ...props }, ref) => (
    <DialogPrimitive.Description ref={ref} className={cn('text-sm text-slate-500', className)} {...props} />
));
DialogDescription.displayName = DialogPrimitive.Description.displayName;

export { Dialog, DialogPortal, DialogOverlay, DialogClose, DialogTrigger, DialogContent, DialogHeader, DialogFooter, DialogTitle, DialogDescription };
