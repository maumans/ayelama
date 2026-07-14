import { useRef } from 'react';
import { router } from '@inertiajs/react';
import { CheckCircle2, Square, Paperclip, Upload } from 'lucide-react';
import { cn } from '@/lib/utils';
import { notifyValidationError } from '@/lib/toast';

// Ligne "pièce requise" réutilisée dans les modals dépôt/retour et dans la
// checklist collapsible des cartes formalité — coché manuellement, ou par
// téléversement d'un fichier dans la GED (qui coche automatiquement la pièce).
export function PieceGedRow({ piece, peutGerer, onToggle, className }) {
    const inputRef = useRef(null);

    const televerser = (file) => {
        if (!file) return;
        router.post(`/formalites/pieces/${piece.id}/televerser`, { fichier: file }, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onError: notifyValidationError,
        });
    };

    return (
        <div className={cn('flex items-center gap-2 px-1 py-1.5', className)}>
            <button
                type="button"
                onClick={() => peutGerer && onToggle?.(piece)}
                disabled={!peutGerer}
                className={cn('shrink-0', peutGerer && 'cursor-pointer')}
                title={piece.est_fourni ? 'Marquer non fournie' : 'Marquer fournie'}
            >
                {piece.est_fourni
                    ? <CheckCircle2 className="h-4 w-4 text-success" />
                    : <Square className="h-4 w-4 text-slate-300" />}
            </button>

            <span className={cn('text-sm flex-1', piece.est_fourni ? 'text-slate-500' : 'text-slate-700')}>
                {piece.label}
            </span>

            {piece.aUnFichier ? (
                <a
                    href={`/formalites/pieces/${piece.id}/telecharger`}
                    className="text-xs text-seal hover:underline flex items-center gap-1 shrink-0"
                >
                    <Paperclip className="h-3 w-3" /> Voir GED
                </a>
            ) : peutGerer ? (
                <>
                    <input
                        ref={inputRef}
                        type="file"
                        className="hidden"
                        onChange={(e) => televerser(e.target.files?.[0])}
                    />
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        className="text-xs text-slate-400 hover:text-seal flex items-center gap-1 shrink-0"
                    >
                        <Upload className="h-3 w-3" /> Téléverser
                    </button>
                </>
            ) : (
                <span className="text-xs text-danger-text shrink-0">Manquant</span>
            )}
        </div>
    );
}
