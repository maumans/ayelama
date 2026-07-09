import { useState, useRef, useLayoutEffect, useEffect } from 'react';
import { Input } from '@/components/ui/input';
import { toDisplay, parseTyped } from '@/lib/numberFormat';
import { countDigits, positionAfterDigits } from '@/lib/maskedInput';

/**
 * Champ numérique formaté à la volée façon "10 000,6" (espace = milliers, virgule =
 * décimales). `value`/`onValueChange` manipulent la valeur CANONIQUE (point décimal,
 * sans espaces, ex. "10000.6") — c'est elle qui est stockée/soumise, jamais la valeur
 * affichée. `decimals` (0 par défaut) fixe le nombre de décimales autorisées.
 */
export function NumberField({ value, onValueChange, decimals = 0, className, ...props }) {
    const inputRef = useRef(null);
    const pendingCursor = useRef(null);
    const [display, setDisplay] = useState(() => toDisplay(value, decimals));

    useEffect(() => {
        // Resynchronise si la valeur externe change pour une autre raison que la frappe
        // locale (sélection d'un client, reset de formulaire, changement de type d'acte…).
        setDisplay(prev => (parseTyped(prev, decimals).canonical === String(value ?? '') ? prev : toDisplay(value, decimals)));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    useLayoutEffect(() => {
        if (pendingCursor.current !== null && inputRef.current) {
            inputRef.current.setSelectionRange(pendingCursor.current, pendingCursor.current);
            pendingCursor.current = null;
        }
    }, [display]);

    const handleChange = (e) => {
        const raw = e.target.value;
        const cursor = e.target.selectionStart ?? raw.length;
        const digitsBeforeCursor = countDigits(raw.slice(0, cursor));
        const { display: nextDisplay, canonical } = parseTyped(raw, decimals);
        pendingCursor.current = positionAfterDigits(nextDisplay, digitsBeforeCursor);
        setDisplay(nextDisplay);
        onValueChange(canonical);
    };

    return (
        <Input
            ref={inputRef}
            type="text"
            inputMode={decimals > 0 ? 'decimal' : 'numeric'}
            value={display}
            onChange={handleChange}
            className={className}
            {...props}
        />
    );
}
