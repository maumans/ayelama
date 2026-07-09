import { useEffect, useRef, useState } from 'react';
import { FileText, Download, X, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';

const PDF_EXTS   = ['pdf'];
const DOCX_EXTS  = ['docx'];
const EXCEL_EXTS = ['xlsx', 'xls'];

function getExt(chemin) {
    if (!chemin) return '';
    return chemin.split('.').pop()?.toLowerCase() ?? '';
}

function classifyPreview(chemin) {
    const ext = getExt(chemin);
    if (PDF_EXTS.includes(ext))   return 'pdf';
    if (DOCX_EXTS.includes(ext))  return 'docx';
    if (EXCEL_EXTS.includes(ext)) return 'excel';
    return 'unsupported';
}

function LoadingState() {
    return (
        <div className="flex flex-col items-center justify-center h-full gap-3 text-white">
            <Loader2 className="h-8 w-8 animate-spin text-slate-400" />
            <p className="text-sm text-slate-300">Chargement de l'aperçu…</p>
        </div>
    );
}

function UnavailableState({ downloadUrl, message }) {
    return (
        <div className="flex flex-col items-center justify-center h-full gap-4 text-white">
            <FileText className="h-16 w-16 text-slate-500" />
            <p className="text-sm text-slate-300 text-center max-w-sm px-4">
                {message ?? "L'aperçu n'est pas disponible pour ce format."}
            </p>
            <a
                href={downloadUrl}
                download
                className="flex items-center gap-2 text-sm bg-seal text-white px-4 py-2 rounded-md hover:bg-seal/90 transition-colors"
            >
                <Download className="h-4 w-4" />
                Télécharger le fichier
            </a>
        </div>
    );
}

function DocxPreview({ downloadUrl, previewUrl }) {
    const containerRef = useRef(null);
    const [status, setStatus] = useState('loading'); // loading | ready | error

    useEffect(() => {
        let cancelled = false;
        setStatus('loading');

        (async () => {
            try {
                const [{ renderAsync }, res] = await Promise.all([
                    import('docx-preview'),
                    fetch(previewUrl, { credentials: 'same-origin' }),
                ]);
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const buffer = await res.arrayBuffer();
                if (cancelled || !containerRef.current) return;
                containerRef.current.innerHTML = '';
                await renderAsync(buffer, containerRef.current, undefined, {
                    inWrapper: true,
                    ignoreWidth: false,
                    breakPages: true,
                });
                if (!cancelled) setStatus('ready');
            } catch (err) {
                console.error('Aperçu DOCX impossible :', err);
                if (!cancelled) setStatus('error');
            }
        })();

        return () => { cancelled = true; };
    }, [previewUrl]);

    if (status === 'error') {
        return <UnavailableState downloadUrl={downloadUrl} message="Impossible d'afficher l'aperçu (fichier corrompu ou invalide)." />;
    }

    return (
        <div className="h-full overflow-auto bg-slate-100">
            {status === 'loading' && <LoadingState />}
            <div ref={containerRef} className={cn('mx-auto py-4', status !== 'ready' && 'hidden')} />
        </div>
    );
}

function ExcelPreview({ downloadUrl, previewUrl }) {
    const [status, setStatus] = useState('loading');
    const [sheets, setSheets] = useState([]);
    const [activeSheet, setActiveSheet] = useState(0);

    useEffect(() => {
        let cancelled = false;
        setStatus('loading');
        setSheets([]);
        setActiveSheet(0);

        (async () => {
            try {
                const [XLSX, res] = await Promise.all([
                    import('xlsx'),
                    fetch(previewUrl, { credentials: 'same-origin' }),
                ]);
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                const buffer = await res.arrayBuffer();
                if (cancelled) return;
                const workbook = XLSX.read(buffer, { type: 'array' });
                const parsed = workbook.SheetNames.map((name) => ({
                    name,
                    rows: XLSX.utils.sheet_to_json(workbook.Sheets[name], {
                        header: 1, defval: '', blankrows: false,
                    }).slice(0, 2000),
                }));
                if (cancelled) return;
                if (parsed.length === 0) throw new Error('Classeur vide');
                setSheets(parsed);
                setStatus('ready');
            } catch (err) {
                console.error('Aperçu Excel impossible :', err);
                if (!cancelled) setStatus('error');
            }
        })();

        return () => { cancelled = true; };
    }, [previewUrl]);

    if (status === 'loading') return <LoadingState />;
    if (status === 'error') {
        return <UnavailableState downloadUrl={downloadUrl} message="Impossible d'afficher l'aperçu (fichier corrompu ou invalide)." />;
    }

    const sheet = sheets[activeSheet];

    return (
        <div className="h-full flex flex-col bg-white">
            {sheets.length > 1 && (
                <div className="flex gap-1 px-3 pt-2 border-b border-slate-200 overflow-x-auto shrink-0">
                    {sheets.map((s, i) => (
                        <button
                            key={s.name}
                            type="button"
                            onClick={() => setActiveSheet(i)}
                            className={cn(
                                'px-3 py-1.5 text-xs font-medium rounded-t-md border-b-2 shrink-0',
                                i === activeSheet
                                    ? 'border-seal text-seal'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                            )}
                        >
                            {s.name}
                        </button>
                    ))}
                </div>
            )}
            <div className="flex-1 overflow-auto p-3">
                <table className="text-xs border-collapse">
                    <tbody>
                        {sheet.rows.map((row, r) => (
                            <tr key={r}>
                                {row.map((cell, c) => (
                                    <td key={c} className="border border-slate-200 px-2 py-1 whitespace-nowrap">
                                        {cell === null || cell === undefined ? '' : String(cell)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

export default function DocumentPreviewModal({ doc, onClose, previewUrl: previewUrlProp, downloadUrl: downloadUrlProp }) {
    const kind = classifyPreview(doc?.chemin_fichier);
    const previewUrl  = previewUrlProp  ?? `/documents/${doc?.id}/preview`;
    const downloadUrl = downloadUrlProp ?? `/documents/${doc?.id}/download`;

    useEffect(() => {
        const handler = (e) => { if (e.key === 'Escape') onClose(); };
        document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [onClose]);

    return (
        <div className="fixed inset-0 z-50 flex flex-col bg-black/70 backdrop-blur-sm" onClick={onClose}>
            {/* Barre supérieure */}
            <div
                className="flex items-center justify-between px-4 py-2 bg-ink text-white shrink-0"
                onClick={e => e.stopPropagation()}
            >
                <div className="flex items-center gap-3 min-w-0">
                    <FileText className="h-4 w-4 text-slate-300 shrink-0" />
                    <span className="text-sm font-medium truncate">{doc?.nom}</span>
                    {doc?.version && <span className="text-xs text-slate-400 shrink-0">v{doc.version}</span>}
                </div>
                <div className="flex items-center gap-2 shrink-0">
                    <a
                        href={downloadUrl}
                        download
                        className="flex items-center gap-1.5 text-xs text-slate-300 hover:text-white border border-slate-600 rounded px-2 py-1 transition-colors"
                        onClick={e => e.stopPropagation()}
                    >
                        <Download className="h-3.5 w-3.5" />
                        Télécharger
                    </a>
                    <button
                        onClick={onClose}
                        className="h-7 w-7 flex items-center justify-center rounded hover:bg-white/10 transition-colors"
                    >
                        <X className="h-4 w-4" />
                    </button>
                </div>
            </div>

            {/* Zone d'aperçu */}
            <div className="flex-1 min-h-0 overflow-hidden" onClick={e => e.stopPropagation()}>
                {kind === 'pdf' && (
                    <iframe src={previewUrl} className="w-full h-full border-0" title={doc?.nom} />
                )}
                {kind === 'docx' && <DocxPreview key={doc.id} downloadUrl={downloadUrl} previewUrl={previewUrl} />}
                {kind === 'excel' && <ExcelPreview key={doc.id} downloadUrl={downloadUrl} previewUrl={previewUrl} />}
                {kind === 'unsupported' && <UnavailableState downloadUrl={downloadUrl} />}
            </div>
        </div>
    );
}
