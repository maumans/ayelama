import { useRef } from 'react';
import { router } from '@inertiajs/react';
import { CheckCircle2, Square, Paperclip, Upload, Eye, RefreshCw } from 'lucide-react';
import { cn } from '@/lib/utils';
import { notifyValidationError } from '@/lib/toast';
import DocumentInlinePreview from '@/Components/documents/DocumentInlinePreview';

// Ligne "pièce requise" réutilisée dans les modals dépôt/retour et dans la
// checklist collapsible des cartes formalité. `est_fourni` n'est plus modifiable
// manuellement ici — seul le téléversement d'un fichier dans la GED fait foi (il
// coche automatiquement la pièce côté serveur), l'indicateur ci-dessous n'est
// qu'un reflet visuel de cet état, pas un bouton.
export function PieceGedRow({ piece, peutGerer, isPreviewOpen, onTogglePreview, className }) {
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
        <div className={className}>
            <div className="flex items-center gap-2 px-1 py-1.5">
                <span className="shrink-0" title={piece.aUnFichier ? 'Pièce fournie' : 'Pièce manquante'}>
                    {piece.aUnFichier
                        ? <CheckCircle2 className="h-4 w-4 text-success" />
                        : <Square className="h-4 w-4 text-slate-300" />}
                </span>

                <span className={cn('text-sm flex-1', piece.aUnFichier ? 'text-slate-500' : 'text-slate-700')}>
                    {piece.label}
                </span>

                {piece.aUnFichier ? (
                    <div className="flex items-center gap-3 shrink-0">
                        <button
                            type="button"
                            onClick={() => onTogglePreview?.(piece)}
                            className="text-xs text-seal hover:underline flex items-center gap-1"
                        >
                            <Eye className="h-3 w-3" /> {isPreviewOpen ? 'Masquer' : 'Voir'}
                        </button>
                        <a
                            href={`/formalites/pieces/${piece.id}/telecharger`}
                            className="text-xs text-slate-400 hover:text-seal flex items-center gap-1"
                        >
                            <Paperclip className="h-3 w-3" /> Télécharger
                        </a>
                        {peutGerer && (
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
                                    className="text-xs text-slate-400 hover:text-seal flex items-center gap-1"
                                >
                                    <RefreshCw className="h-3 w-3" /> Remplacer
                                </button>
                            </>
                        )}
                    </div>
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

            {isPreviewOpen && piece.aUnFichier && (
                <div className="mt-1 mb-2">
                    <DocumentInlinePreview
                        doc={{ id: piece.id, nom: piece.label, version: piece.version, chemin_fichier: piece.chemin_fichier }}
                        previewUrl={`/documents/${piece.id}/preview`}
                        downloadUrl={`/formalites/pieces/${piece.id}/telecharger`}
                        onClose={() => onTogglePreview?.(piece)}
                    />
                </div>
            )}
        </div>
    );
}
