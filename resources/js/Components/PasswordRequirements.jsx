import { Check, X } from 'lucide-react';
import { cn } from '@/lib/utils';

const RULES = [
    { key: 'length', label: '12 caractères minimum', test: (v) => v.length >= 12 },
    { key: 'upper', label: 'Une lettre majuscule', test: (v) => /[A-Z]/.test(v) },
    { key: 'lower', label: 'Une lettre minuscule', test: (v) => /[a-z]/.test(v) },
    { key: 'number', label: 'Un chiffre', test: (v) => /[0-9]/.test(v) },
    { key: 'symbol', label: 'Un caractère spécial', test: (v) => /[^A-Za-z0-9]/.test(v) },
];

export default function PasswordRequirements({ password = '' }) {
    return (
        <ul className="mt-2 space-y-1">
            {RULES.map((rule) => {
                const met = rule.test(password);
                return (
                    <li
                        key={rule.key}
                        className={cn(
                            'flex items-center gap-1.5 text-xs',
                            met ? 'text-success' : 'text-slate-400'
                        )}
                    >
                        {met ? <Check className="h-3.5 w-3.5" /> : <X className="h-3.5 w-3.5" />}
                        {rule.label}
                    </li>
                );
            })}
        </ul>
    );
}
