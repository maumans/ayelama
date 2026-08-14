import { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { Head, router, Link } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    Archive, Building2, ChevronDown, Download, Eye, FileSignature, FileText,
    FolderOpen, Inbox, Lock, Receipt, Search, UserSquare2, X, ChevronRight, Folder, File, FolderArchive, Layers
} from 'lucide-react';
import DocumentPreviewModal from '@/Components/documents/DocumentPreviewModal';

// Une icône par rubrique — valeurs de App\Enums\RubriqueCloture.
const ICONES_RUBRIQUE = {
    actes:             FileText,
    accord_client:     FileSignature,
    pieces_parties:    UserSquare2,
    pieces_formalites: Building2,
    courriers:         Inbox,
    facturation:       Receipt,
};

function PieceRow({ piece, activePreviewId, onTogglePreview }) {
    const pieceKey = `${piece.type}-${piece.id}`;
    const isPreviewing = activePreviewId === pieceKey;

    return (
        <div className="border-b border-slate-100 last:border-0">
            <div className={`flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-slate-50 ${isPreviewing ? 'bg-slate-50' : ''}`}>
                <FileText className="h-4 w-4 shrink-0 text-slate-300" />
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-sm text-slate-800">{piece.nom}</span>
                        {piece.version && <span className="font-ref text-[10px] text-slate-400">v{piece.version}</span>}
                        {piece.est_signe_cachete && (
                            <Badge variant="outline" className="border-green-200 bg-success-bg px-1.5 py-0 text-[10px] text-success-text">
                                <Lock className="mr-0.5 h-2.5 w-2.5" /> Signé/cacheté
                            </Badge>
                        )}
                        {!piece.has_file && <span className="text-[10px] text-slate-400">Sans fichier</span>}
                    </div>
                    {(piece.origine || piece.verifie_at) && (
                        <p className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-400">
                            {piece.origine && <span>{piece.origine}</span>}
                            {piece.verifie_at && (
                                <span className="text-success-text">Vérifiée le {piece.verifie_at}</span>
                            )}
                        </p>
                    )}
                </div>
                {piece.has_file && (
                    <div className="flex shrink-0 items-center gap-0.5">
                        {piece.url_preview && (
                            <Button
                                variant={isPreviewing ? 'secondary' : 'ghost'} size="icon-sm" title="Prévisualiser"
                                onClick={() => onTogglePreview(pieceKey)}
                            >
                                <Eye className="h-3.5 w-3.5" />
                            </Button>
                        )}
                        {piece.url_download && (
                            <Button variant="ghost" size="icon-sm" asChild title="Télécharger">
                                <a href={piece.url_download} download><Download className="h-3.5 w-3.5" /></a>
                            </Button>
                        )}
                    </div>
                )}
            </div>

            <AnimatePresence>
                {isPreviewing && piece.url_preview && (
                    <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.2 }}
                        className="overflow-hidden bg-slate-50/50 px-4 pb-4"
                    >
                        <div className="relative mt-2 h-[500px] w-full rounded-md border border-slate-200 bg-white shadow-sm">
                            <iframe
                                src={piece.url_preview}
                                className="h-full w-full rounded-md"
                                title={`Aperçu ${piece.nom}`}
                            />
                            <div className="absolute top-2 right-6 flex gap-2">
                                {piece.url_download && (
                                    <Button size="sm" variant="secondary" asChild className="shadow-sm">
                                        <a href={piece.url_download} download><Download className="mr-1.5 h-3.5 w-3.5" /> Télécharger</a>
                                    </Button>
                                )}
                                <Button size="icon-sm" variant="secondary" onClick={() => onTogglePreview(pieceKey)} className="shadow-sm" title="Fermer l'aperçu">
                                    <X className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

function RubriqueBloc({ rubrique, activePreviewId, onTogglePreview }) {
    const Icone = ICONES_RUBRIQUE[rubrique.rubrique] ?? FileText;

    return (
        <div>
            <div className="flex items-center gap-2 bg-slate-50/70 px-4 py-1.5">
                <Icone className="h-3.5 w-3.5 shrink-0 text-seal" />
                <span className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {rubrique.ordre}. {rubrique.label}
                </span>
                <span className="text-xs text-slate-400">({rubrique.pieces.length})</span>
            </div>
            <div className="flex flex-col">
                {rubrique.pieces.map(piece => (
                    <PieceRow 
                        key={`${piece.type}-${piece.id}`} 
                        piece={piece} 
                        activePreviewId={activePreviewId}
                        onTogglePreview={onTogglePreview} 
                    />
                ))}
            </div>
        </div>
    );
}

function DossierGroup({ groupe, isOpen, onToggle, activePreviewId, onTogglePreview }) {
    return (
        <Card className="overflow-hidden">
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50"
            >
                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-seal-light">
                    <FolderOpen className="h-4 w-4 text-seal" />
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-ref text-sm font-medium text-slate-800">{groupe.reference}</span>
                        <Badge variant="secondary" className="text-[10px]">
                            {groupe.nbPieces} pièce{groupe.nbPieces > 1 ? 's' : ''}
                        </Badge>
                        {groupe.etapeLabel && (
                            <span className="text-[10px] text-slate-400">{groupe.etapeLabel}</span>
                        )}
                    </div>
                    <p className="mt-0.5 truncate text-xs text-slate-500">{groupe.objet}</p>
                </div>
                <Link
                    href={groupe.url_dossier}
                    onClick={e => e.stopPropagation()}
                    className="hidden shrink-0 text-xs text-seal hover:underline sm:block"
                >
                    Voir le dossier
                </Link>
                <ChevronDown className={`h-4 w-4 shrink-0 text-slate-400 transition-transform ${isOpen ? 'rotate-180' : ''}`} />
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
                        {groupe.rubriques.map(rubrique => (
                            <RubriqueBloc 
                                key={rubrique.rubrique} 
                                rubrique={rubrique} 
                                activePreviewId={activePreviewId}
                                onTogglePreview={onTogglePreview} 
                            />
                        ))}
                    </motion.div>
                )}
            </AnimatePresence>
        </Card>
    );
}

// ── Composants pour l'arborescence (Sidebar) ──

function TreeNode({ label, level = 0, isOpen, onToggle, onClick, isActive, icon: Icon, rightLabel, children }) {
    return (
        <div>
            <div 
                className={`group flex items-center gap-1.5 py-1.5 px-2 rounded-md cursor-pointer text-sm transition-colors ${isActive ? 'bg-seal/10 text-seal font-medium' : 'hover:bg-slate-100 text-slate-700'}`}
                style={{ paddingLeft: `${level * 16 + 8}px` }}
                onClick={onClick}
            >
                <button 
                    onClick={(e) => { 
                        e.stopPropagation(); 
                        if (children) onToggle?.(); 
                    }} 
                    className="flex shrink-0 items-center justify-center h-5 w-5 hover:bg-slate-200/50 rounded text-slate-400"
                >
                    {children ? (
                        <ChevronRight className={`w-3.5 h-3.5 transition-transform ${isOpen ? 'rotate-90' : ''}`} />
                    ) : (
                        <span className="w-3.5 h-3.5" />
                    )}
                </button>
                {Icon && <Icon className={`w-4 h-4 shrink-0 ${isActive ? 'text-seal' : 'text-slate-400'}`} />}
                <span className="truncate flex-1" title={label}>{label}</span>
                {rightLabel && (
                    <span className="shrink-0 text-[10px] text-slate-400 font-normal">{rightLabel}</span>
                )}
            </div>
            {isOpen && children && (
                <div className="mt-0.5 mb-1">{children}</div>
            )}
        </div>
    );
}

function TreeSidebar({ arborescence = {}, filters, onFilterChange }) {
    // Gère l'ouverture/fermeture des nœuds de l'arbre
    const [expanded, setExpanded] = useState(() => {
        const set = new Set();
        // Ouvrir l'année courante si sélectionnée, sinon ouvrir toutes les années
        Object.keys(arborescence).forEach(an => {
            if (!filters.annee || filters.annee === an) set.has(an) || set.add(an);
            if (filters.annee === an && filters.categorie) set.add(`${an}|${filters.categorie}`);
            if (filters.annee === an && filters.categorie && filters.type_acte) set.add(`${an}|${filters.categorie}|${filters.type_acte}`);
        });
        return set;
    });

    const toggle = (path) => {
        setExpanded(prev => {
            const next = new Set(prev);
            next.has(path) ? next.delete(path) : next.add(path);
            return next;
        });
    };

    const isNodeActive = (type, value) => {
        if (type === 'all' && !filters.annee && !filters.categorie && !filters.type_acte && !filters.dossier) return true;
        
        if (type === 'annee') return filters.annee === value.annee && !filters.categorie;
        if (type === 'categorie') return filters.annee === value.annee && filters.categorie === value.categorie && !filters.type_acte;
        if (type === 'type') return filters.annee === value.annee && filters.categorie === value.categorie && filters.type_acte === value.type && !filters.dossier;
        if (type === 'dossier') return filters.dossier === value.dossier;
        return false;
    };

    return (
        <Card className="flex h-full flex-col overflow-hidden bg-slate-50/50 shadow-sm border-slate-200/60">
            <div className="p-4 border-b border-slate-100 bg-white">
                <h3 className="font-semibold text-slate-800 flex items-center gap-2">
                    <Layers className="w-4 h-4 text-seal" /> 
                    Explorateur
                </h3>
            </div>
            <div className="flex-1 overflow-y-auto p-2">
                <TreeNode 
                    label="Toute la GED" 
                    icon={Archive} 
                    isActive={isNodeActive('all')}
                    onClick={() => onFilterChange({ annee: '', categorie: '', type_acte: '', dossier: '' })}
                />
                
                {Object.entries(arborescence).map(([annee, categories]) => {
                    const anneePath = annee;
                    return (
                        <TreeNode
                            key={anneePath}
                            label={annee}
                            level={0}
                            icon={FolderArchive}
                            isOpen={expanded.has(anneePath)}
                            isActive={isNodeActive('annee', { annee })}
                            onToggle={() => toggle(anneePath)}
                            onClick={() => {
                                toggle(anneePath);
                                onFilterChange({ annee, categorie: '', type_acte: '', dossier: '' });
                            }}
                        >
                            {Object.entries(categories).map(([categorie, types]) => {
                                const catPath = `${anneePath}|${categorie}`;
                                return (
                                    <TreeNode
                                        key={catPath}
                                        label={categorie}
                                        level={1}
                                        icon={Folder}
                                        isOpen={expanded.has(catPath)}
                                        isActive={isNodeActive('categorie', { annee, categorie })}
                                        onToggle={() => toggle(catPath)}
                                        onClick={() => {
                                            if (!expanded.has(catPath)) toggle(catPath);
                                            onFilterChange({ annee, categorie, type_acte: '', dossier: '' });
                                        }}
                                    >
                                        {Object.entries(types).map(([type, dossiers]) => {
                                            const typePath = `${catPath}|${type}`;
                                            return (
                                                <TreeNode
                                                    key={typePath}
                                                    label={type}
                                                    level={2}
                                                    icon={FolderOpen}
                                                    isOpen={expanded.has(typePath)}
                                                    isActive={isNodeActive('type', { annee, categorie, type })}
                                                    onToggle={() => toggle(typePath)}
                                                    onClick={() => {
                                                        if (!expanded.has(typePath)) toggle(typePath);
                                                        onFilterChange({ annee, categorie, type_acte: type, dossier: '' });
                                                    }}
                                                >
                                                    {dossiers.map(dossier => (
                                                        <TreeNode
                                                            key={dossier.reference}
                                                            label={dossier.reference}
                                                            rightLabel={dossier.nbPieces}
                                                            level={3}
                                                            icon={FileText}
                                                            isActive={isNodeActive('dossier', { dossier: dossier.reference })}
                                                            onClick={() => onFilterChange({ annee, categorie, type_acte: type, dossier: dossier.reference })}
                                                        />
                                                    ))}
                                                </TreeNode>
                                            );
                                        })}
                                    </TreeNode>
                                );
                            })}
                        </TreeNode>
                    );
                })}
            </div>
        </Card>
    );
}

export default function GedIndex({ groupes, arborescence = {}, stats = {}, rubriques = [], filters: init = {} }) {
    const [q, setQ] = useState(init.q ?? '');
    const [rubrique, setRubrique] = useState(init.rubrique ?? '');
    const [activePreviewId, setActivePreviewId] = useState(null);
    const firstRender = useRef(true);

    const data = groupes?.data ?? [];
    
    // Par défaut on ouvre tous les dossiers si un seul dossier est sélectionné dans l'arborescence, 
    // sinon on n'ouvre que le premier
    const [openRefs, setOpenRefs] = useState(() => {
        if (init.dossier && data.length === 1) return new Set([data[0].reference]);
        return new Set(data[0] ? [data[0].reference] : []);
    });

    useEffect(() => {
        // Ajustement ouverture automatique après navigation
        if (init.dossier && data.length === 1) {
            setOpenRefs(new Set([data[0].reference]));
        } else {
            setOpenRefs(new Set(data[0] ? [data[0].reference] : []));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [groupes, init.dossier]);

    const toggle = (reference) => setOpenRefs(prev => {
        const next = new Set(prev);
        next.has(reference) ? next.delete(reference) : next.add(reference);
        return next;
    });

    const togglePreview = (id) => {
        setActivePreviewId(prev => prev === id ? null : id);
    };

    const apply = useCallback((params) => {
        const currentFilters = { 
            q, 
            rubrique, 
            annee: init.annee, 
            categorie: init.categorie, 
            type_acte: init.type_acte, 
            dossier: init.dossier,
            ...params 
        };
        const clean = Object.fromEntries(Object.entries(currentFilters).filter(([, v]) => v));
        router.get('/ged', clean, { preserveState: true, replace: true });
    }, [q, rubrique, init]);

    useEffect(() => {
        if (firstRender.current) { firstRender.current = false; return; }
        const t = setTimeout(() => apply({ q, rubrique }), 350);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    const changerRubrique = (val) => {
        setRubrique(val);
        apply({ rubrique: val });
    };

    const handleTreeFilter = (treeParams) => {
        // Quand on clique dans l'arbre, on garde la recherche texte et la rubrique si besoin,
        // mais on applique les nouveaux filtres structurels.
        apply(treeParams);
    };

    const reset = () => {
        setQ(''); setRubrique('');
        router.get('/ged', {}, { preserveState: true, replace: true });
    };

    const hasFilters = q || rubrique || init.annee || init.categorie || init.type_acte || init.dossier;
    const parRubrique = stats.parRubrique ?? {};

    // Breadcrumb text based on selection
    let selectionText = "Toutes les pièces";
    if (init.dossier) selectionText = `Dossier ${init.dossier}`;
    else if (init.type_acte) selectionText = `${init.categorie} > ${init.type_acte}`;
    else if (init.categorie) selectionText = `${init.annee} > ${init.categorie}`;
    else if (init.annee) selectionText = `Année ${init.annee}`;

    return (
        <AppLayout breadcrumbs={[{ label: 'GED' }]}>
            <Head title="GED — Ayelema" />

            <div className="mx-auto max-w-[1400px] p-6 h-[calc(100vh-theme(spacing.header))] flex flex-col">
                
                {/* En-tête */}
                <div className="mb-5 shrink-0">
                    <h1 className="font-serif text-display text-ink">Gestion électronique des documents</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        {selectionText} — Explorateur de dossiers et rubriques
                    </p>
                </div>

                <div className="flex flex-col lg:flex-row gap-6 flex-1 min-h-0">
                    
                    {/* Panneau gauche : Arborescence */}
                    <div className="w-full lg:w-72 shrink-0">
                        <TreeSidebar 
                            arborescence={arborescence} 
                            filters={init} 
                            onFilterChange={handleTreeFilter} 
                        />
                    </div>

                    {/* Panneau droit : Contenu principal (Accordéons) */}
                    <div className="flex-1 flex flex-col min-w-0 bg-white/50 rounded-lg">
                        
                        {/* KPIs */}
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-4 shrink-0">
                            {[
                                { label: 'Dossiers affichés', value: stats.dossiers ?? 0, cls: 'text-ink', Icon: FolderOpen },
                                { label: 'Pièces affichées', value: stats.total ?? 0, cls: 'text-seal', Icon: Archive },
                                ...rubriques
                                    .filter(r => (parRubrique[r.value] ?? 0) > 0)
                                    .slice(0, 2)
                                    .map(r => ({
                                        label: r.label,
                                        value: parRubrique[r.value] ?? 0,
                                        cls: 'text-slate-600',
                                        Icon: ICONES_RUBRIQUE[r.value] ?? FileText,
                                    })),
                            ].map(({ label, value, cls, Icon }) => (
                                <div key={label} className="rounded-lg border border-slate-200 bg-white p-3 shadow-sm">
                                    <div className="mb-1 flex items-center justify-between">
                                        <span className="text-xs text-slate-500">{label}</span>
                                        <Icon className={`h-4 w-4 ${cls}`} />
                                    </div>
                                    <div className={`text-2xl font-semibold ${cls}`}>{value}</div>
                                </div>
                            ))}
                        </div>

                        {/* Filtres contextuels (recherche texte, rubriques) */}
                        <div className="flex flex-wrap items-center gap-2 mb-4 shrink-0">
                            <div className="relative min-w-[200px] max-w-xs flex-1">
                                <Search className="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                                <Input
                                    className="h-8 pl-8 text-sm"
                                    placeholder="Chercher une pièce..."
                                    value={q}
                                    onChange={e => setQ(e.target.value)}
                                />
                                {q && (
                                    <button
                                        onClick={() => { setQ(''); apply({ q: '' }); }}
                                        className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                                    >
                                        <X className="h-3.5 w-3.5" />
                                    </button>
                                )}
                            </div>

                            <Select value={rubrique || '__all__'} onValueChange={v => changerRubrique(v === '__all__' ? '' : v)}>
                                <SelectTrigger className="h-8 w-[210px] text-sm">
                                    <SelectValue placeholder="Toutes rubriques" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="__all__">Toutes rubriques</SelectItem>
                                    {rubriques.map(r => (
                                        <SelectItem key={r.value} value={r.value}>
                                            {r.label}
                                            {(parRubrique[r.value] ?? 0) > 0 && ` (${parRubrique[r.value]})`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>

                            {hasFilters && (
                                <Button variant="ghost" size="sm" className="h-8 gap-1 text-slate-500" onClick={reset}>
                                    <X className="h-3.5 w-3.5" /> Tout afficher
                                </Button>
                            )}
                        </div>

                        {/* Liste des dossiers (Accordéons) */}
                        <div className="flex-1 overflow-y-auto min-h-0 space-y-2 pr-2">
                            {data.length === 0 ? (
                                <Card>
                                    <CardContent className="flex flex-col items-center justify-center py-20 text-center">
                                        <Archive className="mb-4 h-12 w-12 text-slate-200" />
                                        <h3 className="font-serif text-heading text-slate-500">Aucune pièce</h3>
                                        <p className="mt-1 text-sm text-slate-400">
                                            {hasFilters ? 'Aucun résultat pour cette sélection.' : 'Aucune pièce enregistrée pour le moment.'}
                                        </p>
                                    </CardContent>
                                </Card>
                            ) : (
                                data.map((groupe) => (
                                    <DossierGroup
                                        key={groupe.reference}
                                        groupe={groupe}
                                        isOpen={openRefs.has(groupe.reference)}
                                        onToggle={() => toggle(groupe.reference)}
                                        activePreviewId={activePreviewId}
                                        onTogglePreview={togglePreview}
                                    />
                                ))
                            )}

                            {/* pagination */}
                            {(groupes?.last_page ?? 1) > 1 && (
                                <div className="flex justify-center gap-2 pt-4 pb-2">
                                    {(groupes?.links ?? []).map((link, i) => (
                                        <button
                                            key={i}
                                            disabled={!link.url || link.active}
                                            onClick={() => link.url && router.visit(link.url, { preserveState: true })}
                                            className={`rounded border px-3 py-1.5 text-sm transition-colors ${
                                                link.active
                                                    ? 'border-seal bg-seal text-white'
                                                    : link.url
                                                        ? 'border-slate-200 hover:bg-slate-50'
                                                        : 'cursor-not-allowed border-slate-100 text-slate-300'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

        </AppLayout>
    );
}
