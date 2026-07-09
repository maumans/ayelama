import * as ToastPrimitive from '@radix-ui/react-toast';
import { AnimatePresence, motion } from 'framer-motion';
import { CheckCircle2, XCircle, Info, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { toast, useToasts } from '@/lib/toast';

const VARIANTS = {
    success: {
        icon: CheckCircle2,
        className: 'bg-success-bg border-green-200 text-success',
    },
    error: {
        icon: XCircle,
        className: 'bg-danger-bg border-red-200 text-danger-text',
    },
    info: {
        icon: Info,
        className: 'bg-slate-50 border-slate-200 text-slate-700',
    },
};

function ToastItem({ id, variant, message, duration }) {
    const { icon: Icon, className } = VARIANTS[variant] ?? VARIANTS.info;

    return (
        <ToastPrimitive.Root
            duration={duration}
            onOpenChange={(open) => { if (!open) toast.dismiss(id); }}
            asChild
            forceMount
        >
            <motion.li
                layout
                initial={{ opacity: 0, y: 16, scale: 0.95 }}
                animate={{ opacity: 1, y: 0, scale: 1 }}
                exit={{ opacity: 0, x: 60, transition: { duration: 0.15 } }}
                transition={{ type: 'spring', stiffness: 400, damping: 32 }}
                className={cn(
                    'pointer-events-auto flex items-start gap-2.5 rounded-lg border shadow-lg px-4 py-3 w-full max-w-sm',
                    className
                )}
            >
                <Icon className="h-4.5 w-4.5 shrink-0 mt-0.5" />
                <ToastPrimitive.Description className="flex-1 text-sm font-medium leading-snug">
                    {message}
                </ToastPrimitive.Description>
                <ToastPrimitive.Close className="shrink-0 opacity-60 hover:opacity-100 transition-opacity" aria-label="Fermer">
                    <X className="h-3.5 w-3.5" />
                </ToastPrimitive.Close>
            </motion.li>
        </ToastPrimitive.Root>
    );
}

export function Toaster() {
    const toasts = useToasts();

    return (
        <ToastPrimitive.Provider swipeDirection="right">
            <AnimatePresence mode="popLayout">
                {toasts.map((t) => (
                    <ToastItem key={t.id} {...t} />
                ))}
            </AnimatePresence>
            <ToastPrimitive.Viewport className="fixed bottom-0 right-0 z-[100] flex w-full max-w-sm flex-col gap-2 p-4 outline-none list-none" />
        </ToastPrimitive.Provider>
    );
}
