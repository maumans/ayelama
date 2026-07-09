import { useState, useRef, useLayoutEffect, useEffect } from 'react';
import { Input } from '@/components/ui/input';
import { formatPhoneDisplay } from '@/lib/phoneFormat';
import { countDigits, positionAfterDigits } from '@/lib/maskedInput';

/**
 * Champ téléphone formaté à la volée façon "621 45 67 43" (groupes 3-2-2-2, 9 chiffres).
 * Contrairement à NumberField, la valeur affichée EST la valeur stockée/soumise — le
 * téléphone est un champ texte libre côté backend, sans contrainte de format.
 */
export function PhoneField({ value, onValueChange, className, ...props }) {
    const inputRef = useRef(null);
    const pendingCursor = useRef(null);
    const [display, setDisplay] = useState(() => formatPhoneDisplay(value));

    useEffect(() => {
        setDisplay(prev => (prev === formatPhoneDisplay(value) ? prev : formatPhoneDisplay(value)));
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
        const next = formatPhoneDisplay(raw);
        pendingCursor.current = positionAfterDigits(next, digitsBeforeCursor);
        setDisplay(next);
        onValueChange(next);
    };

    return (
        <Input
            ref={inputRef}
            type="tel"
            inputMode="numeric"
            value={display}
            onChange={handleChange}
            className={className}
            {...props}
        />
    );
}
