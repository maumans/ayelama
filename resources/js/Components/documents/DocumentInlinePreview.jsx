import { forwardRef, useEffect } from 'react';
import { motion } from 'framer-motion';
import { FileText, Download, X } from 'lucide-react';
import { PreviewBody } from '@/Components/documents/DocumentPreviewModal';

// Panneau d'aperçu affiché directement dans la page (sous la liste de documents
// concernée), plutôt qu'en overlay plein écran — voir DocumentPreviewModal.jsx
// pour la logique de rendu par type de fichier (PDF/DOCX/Excel), partagée via
// le composant PreviewBody.
const DocumentInlinePreview = forwardRef(function DocumentInlinePreview(
    { doc, onClose, previewUrl: previewUrlProp, downloadUrl: downloadUrlProp },
    ref
) {
    const previewUrl  = previewUrlProp  ?? `/documents/${doc?.id}/preview`;
    const downloadUrl = downloadUrlProp ?? `/documents/${doc?.id}/download`;

    useEffect(() => {
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [onClose]);

    return (
        <motion.div
            ref={ref}
            initial={{ opacity: 0, y: -8 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -8 }}
            transition={{ duration: 0.2 }}
            className="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden"
        >
            {/* Barre supérieure */}
            <div className="flex items-center justify-between px-4 py-2.5 bg-slate-50 border-b border-slate-200">
                <div className="flex items-center gap-2.5 min-w-0">
                    <FileText className="h-4 w-4 text-slate-400 shrink-0" />
                    <span className="text-sm font-medium text-slate-800 truncate">{doc?.nom}</span>
                    {doc?.version && <span className="text-xs text-slate-400 shrink-0">v{doc.version}</span>}
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <a
                        href={downloadUrl}
                        download
                        className="flex items-center gap-1.5 text-xs text-slate-500 hover:text-ink border border-slate-200 rounded-md px-2 py-1 transition-colors hover:bg-white"
                    >
                        <Download className="h-3.5 w-3.5" />
                        Télécharger
                    </a>
                    <button
                        onClick={onClose}
                        title="Fermer l'aperçu"
                        className="h-7 w-7 flex items-center justify-center rounded-md text-slate-400 hover:text-slate-700 hover:bg-white transition-colors"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
            </div>

            {/* Zone d'aperçu */}
            <div className="h-[70vh] bg-slate-100">
                <PreviewBody doc={doc} previewUrl={previewUrl} downloadUrl={downloadUrl} />
            </div>
        </motion.div>
    );
});

export default DocumentInlinePreview;
