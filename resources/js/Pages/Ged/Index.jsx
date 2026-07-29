import { useState, useEffect, useRef, useCallback } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    Archive, ChevronDown, Download, Eye, FileText, FolderOpen, Search, Users, X,
} from 'lucide-react';
import DocumentPreviewModal from '@/Components/documents/DocumentPreviewModal';

const TYPE_META = {
    acte:             { label: 'Acte',              badge: 'bg-blue-50 text-blue-700 border-blue-200' },
    piece_formalite:  { label: 'Pièce formalité',   badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    piece_partie:     { label: 'Pièce partie',      badge: 'bg-violet-50 text-violet-700 border-violet-200' },
};

const CATEGORIE_LABELS = {
    acte_principal:       'Acte principal',
    annexe:               'Annexe',
    procedure:            'Procédure',
    lettre:               'Lettre',
    recepisse:            'Récépissé',
    piece_justificative:  'Pièce justificative',
    photo:                'Photo',
    autre:                'Autre',
};

function DocRow({ doc, onPreview }) {
    const meta = TYPE_META[doc.type] ?? TYPE_META.acte;

    return (
        <div className="flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 transition-colors">
            <FileText className="h-4 w-4 text-slate-300 shrink-0" />
            <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2 flex-wrap">
                    <span className="text-sm text-slate-800">{doc.nom}</span>
                    {doc.version && <span className="text-[10px] text-slate-400 font-ref">v{doc.version}</span>}
                    <Badge variant="outline" className={`${meta.badge} text-[10px] px-1.5 py-0`}>{meta.label}</Badge>
                    {!doc.has_file && <span className="text-[10px] text-slate-400">Sans fichier</span>}
                </div>
                {doc.contexte && <p className="text-xs text-slate-400 mt-0.5">{doc.contexte}</p>}
            </div>
            <span className="text-xs text-slate-400 shrink-0 hidden sm:block">{doc.updated_at}</span>
            {doc.has_file && (
                <div className="flex items-center gap-0.5 shrink-0">
                    <Button variant="ghost" size="icon-sm" title="Prévisualiser"
                        onClick={() => onPreview({ id: doc.id, nom: doc.nom, chemin_fichier: doc.chemin_fichier, version: doc.version }, doc.url_preview, doc.url_download)}>
                        <Eye className="h-3.5 w-3.5" />
                    </Button>
                    <Button variant="ghost" size="icon-sm" asChild title="Télécharger">
                        <a href={doc.url_download} download><Download className="h-3.5 w-3.5" /></a>
                    </Button>
                </div>
            )}
        </div>
    );
}

function DossierGroup({ groupe, isOpen, onToggle, onPreview }) {
    return (
        <Card className="overflow-hidden">
            <button
                type="button"
                onClick={onToggle}
                className="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-slate-50 transition-colors"
            >
                <div className="h-8 w-8 rounded-lg bg-seal-light flex items-center justify-center shrink-0">
                    <FolderOpen className="h-4 w-4 text-seal" />
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <span className="font-medium text-slate-800 text-sm font-ref">{groupe.reference}</span>
                        <Badge variant="secondary" className="text-[10px]">
                            {groupe.documents.length} document{groupe.documents.length > 1 ? 's' : ''}
                        </Badge>
                    </div>
                    <p className="text-xs text-slate-500 truncate mt-0.5">{groupe.objet}</p>
                </div>
                <Link
                    href={groupe.url_dossier}
                    onClick={e => e.stopPropagation()}
                    className="text-xs text-seal hover:underline shrink-0 hidden sm:block"
                >
                    Voir le dossier
                </Link>
                <ChevronDown className={`h-4 w-4 text-slate-400 shrink-0 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
            </button>
            <AnimatePresence initial={false}>
                {isOpen && (
                    <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.2 }}
                        className="overflow-hidden border-t border-slate-100"
                    >
                        <div className="divide-y divide-slate-100">
                            {groupe.documents.map((doc) => (
                                <DocRow key={doc.id} doc={doc} onPreview={onPreview} />
                            ))}
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </Card>
    );
}

export default function GedIndex({ groupes, stats = {}, filters: init = {} }) {
    const [q, setQ]                 = useState(init.q ?? '');
    const [type, setType]           = useState(init.type ?? '');
    const [categorie, setCategorie] = useState(init.categorie ?? '');
    const [previewDoc, setPreviewDoc] = useState(null);
    const firstRender = useRef(true);

    const data = groupes?.data ?? [];
    const [openRefs, setOpenRefs] = useState(() => new Set(data[0] ? [data[0].reference] : []));

    useEffect(() => {
        // À chaque nouvelle page/recherche, ouvre le premier groupe par défaut plutôt
        // que de garder l'état d'ouverture d'une page précédente (références différentes).
        setOpenRefs(new Set(data[0] ? [data[0].reference] : []));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [groupes]);

    const toggle = (reference) => setOpenRefs(prev => {
        const next = new Set(prev);
        next.has(reference) ? next.delete(reference) : next.add(reference);
        return next;
    });

    const openPreview = (doc, previewUrl, downloadUrl) => setPreviewDoc({ doc, previewUrl, downloadUrl });

    const apply = useCallback((params) => {
        const clean = Object.fromEntries(Object.entries(params).filter(([, v]) => v));
        router.get('/ged', clean, { preserveState: true, replace: true });
    }, []);

    useEffect(() => {
        if (firstRender.current) { firstRender.current = false; return; }
        const t = setTimeout(() => apply({ q, type, categorie }), 350);
        return () => clearTimeout(t);
    }, [q]);

    const setF = (key, val) => {
        const next = { q, type, categorie, [key]: val };
        if (key === 'type')      setType(val);
        if (key === 'categorie') setCategorie(val);
        apply(next);
    };

    const reset = () => {
        setQ(''); setType(''); setCategorie('');
        router.get('/ged', {}, { preserveState: true, replace: true });
    };

    const hasFilters = q || type || categorie;

    return (
        <AppLayout breadcrumbs={[{ label: 'GED' }]}>
            <Head title="GED — Ayelema" />

            <div className="p-6 max-w-[1100px] mx-auto space-y-5">

                <div>
                    <h1 className="font-serif text-display text-ink">Gestion électronique des documents</h1>
                    <p className="text-slate-500 text-sm mt-1">Tous les documents et pièces, groupés par dossier</p>
                </div>

                {/* KPIs */}
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    {[
                        { label: 'Dossiers',              value: stats.dossiers        ?? 0, cls: 'text-ink',       Icon: FolderOpen },
                        { label: 'Actes de dossier',      value: stats.actes           ?? 0, cls: 'text-blue-600',   Icon: FileText },
                        { label: 'Pièces de formalité',   value: stats.piecesFormalite ?? 0, cls: 'text-amber-600',  Icon: FileText },
                        { label: 'Pièces de partie',      value: stats.piecesPartie    ?? 0, cls: 'text-violet-600', Icon: Users },
                    ].map(({ label, value, cls, Icon }) => (
                        <div key={label} className="bg-white rounded-lg border border-slate-200 p-3 shadow-sm">
                            <div className="flex items-center justify-between mb-1">
                                <span className="text-xs text-slate-500">{label}</span>
                                <Icon className={`h-4 w-4 ${cls}`} />
                            </div>
                            <div className={`text-2xl font-semibold ${cls}`}>{value}</div>
                        </div>
                    ))}
                </div>

                {/* filters */}
                <div className="flex flex-wrap gap-2 items-center">
                    <div className="relative flex-1 min-w-[200px] max-w-xs">
                        <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                        <Input
                            className="pl-8 h-8 text-sm"
                            placeholder="Nom du document…"
                            value={q}
                            onChange={e => setQ(e.target.value)}
                        />
                        {q && (
                            <button
                                onClick={() => { setQ(''); apply({ q: '', type, categorie }); }}
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>

                    <Select value={type || '__all__'} onValueChange={v => setF('type', v === '__all__' ? '' : v)}>
                        <SelectTrigger className="h-8 text-sm w-[180px]">
                            <SelectValue placeholder="Tous types" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">Tous types</SelectItem>
                            <SelectItem value="acte">Acte de dossier</SelectItem>
                            <SelectItem value="piece_formalite">Pièce de formalité</SelectItem>
                            <SelectItem value="piece_partie">Pièce de partie</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select value={categorie || '__all__'} onValueChange={v => setF('categorie', v === '__all__' ? '' : v)}>
                        <SelectTrigger className="h-8 text-sm w-[170px]">
                            <SelectValue placeholder="Toutes catégories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">Toutes catégories</SelectItem>
                            {Object.entries(CATEGORIE_LABELS).map(([value, label]) => (
                                <SelectItem key={value} value={value}>{label}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {hasFilters && (
                        <Button variant="ghost" size="sm" className="h-8 gap-1 text-slate-500" onClick={reset}>
                            <X className="h-3.5 w-3.5" /> Réinitialiser
                        </Button>
                    )}

                    <span className="ml-auto text-xs text-slate-400">
                        {stats.dossiers ?? 0} dossier{(stats.dossiers ?? 0) !== 1 ? 's' : ''}
                    </span>
                </div>

                {/* groupes */}
                {data.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-20 text-center">
                            <Archive className="h-12 w-12 text-slate-200 mb-4" />
                            <h3 className="font-serif text-heading text-slate-500">Aucun document</h3>
                            <p className="text-sm text-slate-400 mt-1">
                                {hasFilters ? 'Aucun résultat pour ces filtres.' : 'Aucun document enregistré pour le moment.'}
                            </p>
                            {hasFilters && (
                                <button onClick={reset} className="text-xs text-seal hover:underline mt-2">
                                    Effacer les filtres
                                </button>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-2">
                        {data.map((groupe) => (
                            <DossierGroup
                                key={groupe.reference}
                                groupe={groupe}
                                isOpen={openRefs.has(groupe.reference)}
                                onToggle={() => toggle(groupe.reference)}
                                onPreview={openPreview}
                            />
                        ))}
                    </div>
                )}

                {/* pagination */}
                {(groupes?.last_page ?? 1) > 1 && (
                    <div className="flex justify-center gap-2 pt-2">
                        {(groupes?.links ?? []).map((link, i) => (
                            <button
                                key={i}
                                disabled={!link.url || link.active}
                                onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                                className={`px-3 py-1.5 rounded text-sm border transition-colors ${
                                    link.active
                                        ? 'bg-seal text-white border-seal'
                                        : link.url
                                            ? 'border-slate-200 hover:bg-slate-50'
                                            : 'border-slate-100 text-slate-300 cursor-not-allowed'
                                }`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </div>
                )}
            </div>

            {previewDoc && (
                <DocumentPreviewModal
                    doc={previewDoc.doc}
                    previewUrl={previewDoc.previewUrl}
                    downloadUrl={previewDoc.downloadUrl}
                    onClose={() => setPreviewDoc(null)}
                />
            )}
        </AppLayout>
    );
}
