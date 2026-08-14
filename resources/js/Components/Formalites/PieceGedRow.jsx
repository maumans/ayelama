import { useRef } from 'react';
import { router } from '@inertiajs/react';
import { CheckCircle2, FileText, Paperclip, Upload, Eye, RefreshCw, CopyCheck } from 'lucide-react';
import { cn } from '@/lib/utils';
import { notifyValidationError } from '@/lib/toast';
import DocumentInlinePreview from '@/Components/documents/DocumentInlinePreview';

// Ligne "pièce requise" réutilisée dans les modals dépôt/retour et dans la
// checklist collapsible des cartes formalité. `est_fourni` n'est plus modifiable
// manuellement ici — seul le téléversement d'un fichier dans la GED fait foi (il
// coche automatiquement la pièce côté serveur), l'indicateur ci-dessous n'est
// qu'un reflet visuel de cet état, pas un bouton.
export function PieceGedRow({ piece, peutGerer, isPreviewOpen, onTogglePreview, className, uploadUrl, downloadUrl, repriseUrl }) {
    const inputRef = useRef(null);
    const finalUploadUrl   = uploadUrl   ?? `/formalites/pieces/${piece.id}/televerser`;
    const finalDownloadUrl = downloadUrl ?? `/formalites/pieces/${piece.id}/telecharger`;

    // Pièce que cette même personne a déjà fournie dans un autre dossier : proposée à la reprise
    // plutôt que redemandée. La **date** est affichée — une pièce d'identité a une durée de
    // validité, et reprendre un scan ancien sans le voir serait pire que de le redemander.
    const reprise = !piece.aUnFichier && piece.reprise && repriseUrl ? piece.reprise : null;

    const televerser = (file) => {
        if (!file) return;
        router.post(finalUploadUrl, { fichier: file }, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onError: notifyValidationError,
        });
    };

    const reprendre = () => {
        router.post(repriseUrl, { source_id: reprise.piece_id }, {
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
                        : <FileText className="h-3.5 w-3.5 text-slate-400" />}
                </span>

                <span className={cn('text-sm flex-1', piece.aUnFichier ? 'text-slate-500' : 'text-slate-700')}>
                    {piece.label}
                    {reprise && (
                        <span className="ml-1.5 text-xs text-slate-400">
                            — déjà fournie dans {reprise.dossier}
                            {reprise.date && ` le ${reprise.date}`}
                        </span>
                    )}
                </span>

                {reprise && peutGerer && (
                    <button
                        type="button"
                        onClick={reprendre}
                        className="flex shrink-0 items-center gap-1 rounded-md border border-seal/40 bg-seal-light px-2 py-0.5 text-xs text-seal-hover transition-colors hover:border-seal"
                    >
                        <CopyCheck className="h-3 w-3" /> Reprendre
                    </button>
                )}

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
                            href={finalDownloadUrl}
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
                    null
                )}
            </div>

            {isPreviewOpen && piece.aUnFichier && (
                <div className="mt-1 mb-2">
                    <DocumentInlinePreview
                        doc={{ id: piece.id, nom: piece.label, version: piece.version, chemin_fichier: piece.chemin_fichier }}
                        previewUrl={`/documents/${piece.id}/preview`}
                        downloadUrl={finalDownloadUrl}
                        onClose={() => onTogglePreview?.(piece)}
                    />
                </div>
            )}
        </div>
    );
}
