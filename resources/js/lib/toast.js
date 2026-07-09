import { useEffect, useState } from 'react';

// Store de toasts minimal (pub/sub), sans dépendance externe — le composant
// <Toaster /> (Components/ui/toaster.jsx) s'y abonne via useToasts() et affiche
// chaque entrée avec les primitives Radix Toast.
let toasts = [];
let idSeq = 0;
const listeners = new Set();

function emit() {
    listeners.forEach((listener) => listener(toasts));
}

function dismiss(id) {
    toasts = toasts.filter((t) => t.id !== id);
    emit();
}

function push(variant, message, options = {}) {
    if (!message) return;
    const id = ++idSeq;
    toasts = [...toasts, { id, variant, message, duration: options.duration ?? 5000 }];
    emit();
    return id;
}

export const toast = {
    success: (message, options) => push('success', message, options),
    error: (message, options) => push('error', message, options),
    info: (message, options) => push('info', message, options),
    dismiss,
};

// Formate un bag d'erreurs de validation Inertia/Laravel ({ champ: "message" })
// en un message de toast unique — utilisé comme callback onError générique.
export function notifyValidationError(errors) {
    if (!errors || typeof errors !== 'object') {
        toast.error('Une erreur est survenue.');
        return;
    }
    const first = Object.values(errors)[0];
    toast.error(Array.isArray(first) ? first[0] : (first || 'Une erreur est survenue.'));
}

export function useToasts() {
    const [state, setState] = useState(toasts);
    useEffect(() => {
        listeners.add(setState);
        return () => listeners.delete(setState);
    }, []);
    return state;
}
