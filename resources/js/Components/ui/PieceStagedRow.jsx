import { useRef, useState, useEffect } from 'react';
import { CheckCircle2, Square, Upload, Eye, RefreshCw, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { PreviewBody } from '@/Components/documents/DocumentPreviewModal';

/**
 * `fichierBrouillon` ({ chemin, nom }) : pièce déjà téléversée dans un brouillon de
 * dossier. Elle n'est plus un File côté navigateur — on affiche son nom et on
 * permet de la remplacer ou de la retirer, sans aperçu (le fichier est sur le
 * disque privé du serveur, et exposer une pièce d'identité derrière une URL de
 * prévisualisation pour un dossier qui n'existe pas encore serait excessif).
 */
export function PieceStagedRow({ piece, file, fichierBrouillon, onFileSelected, onRetirerBrouillon, isPreviewOpen, onTogglePreview, className }) {
    const inputRef = useRef(null);
    const [previewUrl, setPreviewUrl] = useState(null);

    useEffect(() => {
        if (file) {
            const url = URL.createObjectURL(file);
            setPreviewUrl(url);
            return () => URL.revokeObjectURL(url);
        } else {
            setPreviewUrl(null);
        }
    }, [file]);

    const fournie = !!file || !!fichierBrouillon;

    return (
        <div className={className}>
            <div className="flex items-center gap-2 px-1 py-1.5">
                <span className="shrink-0" title={fournie ? 'Pièce sélectionnée' : 'Pièce manquante'}>
                    {fournie
                        ? <CheckCircle2 className="h-4 w-4 text-success" />
                        : <Square className="h-4 w-4 text-slate-300" />}
                </span>

                <span className={cn('text-sm flex-1', fournie ? 'text-slate-500' : 'text-slate-700')}>
                    {piece.label}
                    {!file && fichierBrouillon && (
                        <span className="ml-1.5 text-xs text-slate-400">
                            — {fichierBrouillon.nom} <span className="text-slate-300">(conservée au brouillon)</span>
                        </span>
                    )}
                </span>

                {!file && fichierBrouillon ? (
                    <div className="flex shrink-0 items-center gap-3">
                        <input
                            ref={inputRef}
                            type="file"
                            className="hidden"
                            onChange={(e) => onFileSelected?.(e.target.files?.[0] || null)}
                        />
                        <button
                            type="button"
                            onClick={() => inputRef.current?.click()}
                            className="flex items-center gap-1 text-xs text-slate-400 hover:text-seal"
                        >
                            <RefreshCw className="h-3 w-3" /> Remplacer
                        </button>
                        <button
                            type="button"
                            onClick={() => onRetirerBrouillon?.()}
                            className="flex items-center gap-1 text-xs text-danger-text hover:underline"
                        >
                            <X className="h-3 w-3" /> Retirer
                        </button>
                    </div>
                ) : file ? (
                    <div className="flex items-center gap-3 shrink-0">
                        <button
                            type="button"
                            onClick={() => onTogglePreview?.(piece)}
                            className="text-xs text-seal hover:underline flex items-center gap-1"
                        >
                            <Eye className="h-3 w-3" /> {isPreviewOpen ? 'Masquer' : 'Aperçu'}
                        </button>
                        <input
                            ref={inputRef}
                            type="file"
                            className="hidden"
                            onChange={(e) => onFileSelected?.(e.target.files?.[0] || null)}
                        />
                        <button
                            type="button"
                            onClick={() => inputRef.current?.click()}
                            className="text-xs text-slate-400 hover:text-seal flex items-center gap-1"
                        >
                            <RefreshCw className="h-3 w-3" /> Remplacer
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                onFileSelected?.(null);
                                if (isPreviewOpen) onTogglePreview?.(piece);
                            }}
                            className="text-xs text-danger-text hover:underline flex items-center gap-1"
                        >
                            <X className="h-3 w-3" /> Retirer
                        </button>
                    </div>
                ) : (
                    <>
                        <input
                            ref={inputRef}
                            type="file"
                            className="hidden"
                            onChange={(e) => onFileSelected?.(e.target.files?.[0] || null)}
                        />
                        <button
                            type="button"
                            onClick={() => inputRef.current?.click()}
                            className="text-xs text-slate-400 hover:text-seal flex items-center gap-1 shrink-0"
                        >
                            <Upload className="h-3 w-3" /> Sélectionner
                        </button>
                    </>
                )}
            </div>

            {isPreviewOpen && file && previewUrl && (
                <div className="mt-1 mb-2 border border-slate-200 rounded-md overflow-hidden bg-slate-50 h-64 relative">
                    <button
                        onClick={() => onTogglePreview?.(piece)}
                        className="absolute top-2 right-2 z-10 p-1 bg-white/80 hover:bg-white rounded-full shadow-sm"
                    >
                        <X className="h-4 w-4 text-slate-600" />
                    </button>
                    <PreviewBody doc={{ nom: file.name, chemin_fichier: file.name }} previewUrl={previewUrl} downloadUrl={previewUrl} />
                </div>
            )}
        </div>
    );
}
