import { useState } from 'react';
import { Lock, Pencil } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * Champ dont la valeur est une constante de fait, modifiable seulement par un geste délibéré.
 *
 * Le pays de résidence vaut « République de Guinée » sur la totalité des fiches : le laisser en
 * saisie libre l'exposait à une modification par inadvertance — un clic de trop, une saisie qui
 * déborde — sur une donnée qui figure ensuite dans les actes.
 *
 * ⚠️ Verrouillé, **pas supprimé** : un client peut résider à l'étranger, et l'étude doit pouvoir
 * l'enregistrer. Le déverrouillage est explicite et visible, ce qui est exactement la demande —
 * empêcher la modification *involontaire*, pas la modification.
 */
export function ChampVerrouille({ value, onChange, placeholder, id, className, disabled = false }) {
    const [deverrouille, setDeverrouille] = useState(false);

    if (deverrouille && !disabled) {
        return (
            <Input
                id={id}
                autoFocus
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                onBlur={() => setDeverrouille(false)}
                placeholder={placeholder}
                className={className}
            />
        );
    }

    return (
        <button
            type="button"
            id={id}
            disabled={disabled}
            onClick={() => setDeverrouille(true)}
            title="Cliquez pour modifier — cette valeur est la même sur la quasi-totalité des fiches"
            className={cn(
                'flex h-10 w-full items-center gap-2 rounded-md border border-slate-200 bg-slate-50',
                'px-3 py-2 text-left text-sm text-slate-500 transition-colors',
                disabled ? 'cursor-not-allowed' : 'hover:border-slate-300',
                className,
            )}
        >
            <Lock className="h-3 w-3 shrink-0 text-slate-400" />
            <span className="min-w-0 flex-1 truncate">{value || placeholder}</span>
            <Pencil className="h-3 w-3 shrink-0 text-slate-300" />
        </button>
    );
}
