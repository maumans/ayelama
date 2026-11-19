import { Check, X } from 'lucide-react';
import { cn } from '@/lib/utils';

const RULES = [
    { key: 'length', label: '12 caractères minimum', test: (v) => v.length >= 12 },
    { key: 'upper', label: 'Une lettre majuscule', test: (v) => /[A-Z]/.test(v) },
    { key: 'lower', label: 'Une lettre minuscule', test: (v) => /[a-z]/.test(v) },
    { key: 'number', label: 'Un chiffre', test: (v) => /[0-9]/.test(v) },
    { key: 'symbol', label: 'Un caractère spécial', test: (v) => /[^A-Za-z0-9]/.test(v) },
];

/**
 * Miroir de `Password::defaults()` (AppServiceProvider : 12 caractères, casse mixte, chiffre,
 * symbole). `ModalUtilisateur` s'en sert pour bloquer l'envoi plutôt que de laisser le serveur
 * refuser — le refus passait inaperçu (2026-11-19). `CreationUtilisateurTest` vérifie l'accord.
 */
export function motDePasseConforme(password = '') {
    return RULES.every((rule) => rule.test(password));
}

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
