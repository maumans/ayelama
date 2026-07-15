import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    Banknote, Wallet, AlertTriangle, ChevronRight,
    ChevronLeft, X, Search, ArrowUpDown, Receipt,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { STATUT_META } from '@/data/facturationStatuts';

const fmt = (n) => Number(n || 0).toLocaleString('fr-FR');

export default function FacturationIndex() {
    const { factures, stats, filters, auth } = usePage().props;
    const facturesData = factures?.data ?? [];
    const totalPages   = factures?.last_page ?? 1;
    const currentPage  = factures?.current_page ?? 1;

    const [search, setSearch] = useState(filters?.q ?? '');
    const [statut, setStatut] = useState(filters?.statut ?? '');
    const [sortBy, setSortBy] = useState(filters?.sort ?? '');

    const applyFilters = (overrides = {}) => {
        const vals = {
            q:      overrides.q      !== undefined ? overrides.q      : search,
            statut: overrides.statut !== undefined ? overrides.statut : statut,
            sort:   overrides.sort   !== undefined ? overrides.sort   : sortBy,
        };
        const params = Object.fromEntries(Object.entries(vals).filter(([, v]) => v));
        router.get('/facturation', params, { preserveState: true, replace: true });
    };

    const resetFiltres = () => {
        setSearch(''); setStatut(''); setSortBy('');
        router.get('/facturation', {}, { preserveState: false, replace: true });
    };

    const hasFiltres = search || statut || sortBy;
    const parStatut = stats?.parStatut ?? { impaye: 0, partiel: 0, paye: 0 };
    const totalDossiers = parStatut.impaye + parStatut.partiel + parStatut.paye;

    return (
        <AppLayout breadcrumbs={[{ label: 'Facturation' }]}>
            <Head title="Facturation — Ayelema" />

            <div className="p-6 max-w-[1000px] mx-auto space-y-5">

                {/* En-tête */}
                <div>
                    <h1 className="font-serif text-display text-ink">Facturation</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        Suivi des honoraires, provisions et soldes restants dus
                    </p>
                </div>

                {/* Stats */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    {[
                        { label: 'Total facturé',   value: `${fmt(stats?.totalFacture)} GNF`,  color: 'text-ink',          Icon: Banknote },
                        { label: 'Total encaissé',  value: `${fmt(stats?.totalEncaisse)} GNF`, color: 'text-success',      Icon: Wallet },
                        { label: 'Solde restant dû', value: `${fmt(stats?.soldeRestant)} GNF`,  color: (stats?.soldeRestant ?? 0) > 0 ? 'text-warning-text' : 'text-success', Icon: AlertTriangle },
                        { label: 'Dossiers impayés', value: parStatut.impaye ?? 0,               color: (parStatut.impaye ?? 0) > 0 ? 'text-danger' : 'text-slate-400', Icon: Receipt },
                    ].map(({ label, value, color, Icon }) => (
                        <Card key={label} className="p-3">
                            <div className="flex items-start justify-between">
                                <div>
                                    <div className={cn('text-xl font-bold leading-none', color)}>{value}</div>
                                    <div className="text-[10px] text-slate-400 mt-1 uppercase tracking-wide">{label}</div>
                                </div>
                                <Icon className={cn('h-4 w-4 mt-0.5 opacity-40', color)} />
                            </div>
                        </Card>
                    ))}
                </div>

                {/* Distribution impayé/partiel/payé */}
                {totalDossiers > 0 && (
                    <div className="flex items-center gap-3">
                        <div className="flex-1 flex h-2 rounded-full overflow-hidden bg-slate-100">
                            {parStatut.impaye > 0 && (
                                <motion.div className="h-full bg-danger" initial={{ width: 0 }}
                                    animate={{ width: `${(parStatut.impaye / totalDossiers) * 100}%` }} transition={{ duration: 0.6 }} />
                            )}
                            {parStatut.partiel > 0 && (
                                <motion.div className="h-full bg-warning" initial={{ width: 0 }}
                                    animate={{ width: `${(parStatut.partiel / totalDossiers) * 100}%` }} transition={{ duration: 0.6, delay: 0.1 }} />
                            )}
                            {parStatut.paye > 0 && (
                                <motion.div className="h-full bg-success" initial={{ width: 0 }}
                                    animate={{ width: `${(parStatut.paye / totalDossiers) * 100}%` }} transition={{ duration: 0.6, delay: 0.2 }} />
                            )}
                        </div>
                        <div className="flex items-center gap-3 text-[10px] text-slate-500 shrink-0">
                            <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-danger inline-block" /> Impayé</span>
                            <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-warning inline-block" /> Partiel</span>
                            <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-full bg-success inline-block" /> Payé</span>
                        </div>
                    </div>
                )}

                {/* Filtres */}
                <div className="flex flex-wrap gap-2 items-center">
                    <div className="relative flex-1 min-w-[180px] max-w-xs">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                        <Input
                            className="pl-9 h-8 text-sm"
                            placeholder="Référence, objet, n° facture…"
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && applyFilters({ q: search })}
                        />
                        {search && (
                            <button onClick={() => { setSearch(''); applyFilters({ q: '' }); }}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>

                    <Select value={statut || '__all__'} onValueChange={v => { const s = v === '__all__' ? '' : v; setStatut(s); applyFilters({ statut: s }); }}>
                        <SelectTrigger className="h-8 text-sm w-36">
                            <SelectValue placeholder="Tous statuts" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__all__">Tous statuts</SelectItem>
                            <SelectItem value="impaye">Impayé</SelectItem>
                            <SelectItem value="partiel">Partiel</SelectItem>
                            <SelectItem value="paye">Payé</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select value={sortBy || '__none__'} onValueChange={v => { const s = v === '__none__' ? '' : v; setSortBy(s); applyFilters({ sort: s }); }}>
                        <SelectTrigger className="h-8 text-sm w-40">
                            <ArrowUpDown className="h-3.5 w-3.5 mr-1 text-slate-400 shrink-0" />
                            <SelectValue placeholder="Trier par" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none__">Plus récentes</SelectItem>
                            <SelectItem value="montant">Montant ↓</SelectItem>
                        </SelectContent>
                    </Select>

                    {hasFiltres && (
                        <Button variant="ghost" size="sm" className="h-8 text-slate-400 hover:text-slate-600" onClick={resetFiltres}>
                            <X className="h-3.5 w-3.5 mr-1" /> Réinitialiser
                        </Button>
                    )}
                </div>

                {/* Liste */}
                {facturesData.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-20 text-center">
                        <Banknote className="h-12 w-12 text-slate-200 mb-4" />
                        <h3 className="font-serif text-heading text-slate-500">
                            {hasFiltres ? 'Aucun résultat' : 'Aucune facture'}
                        </h3>
                        <p className="text-sm text-slate-400 mt-1">
                            {hasFiltres ? 'Modifiez vos critères de recherche.' : 'Les factures apparaîtront ici dès qu\'un dossier en génère une.'}
                        </p>
                        {hasFiltres && (
                            <Button variant="outline" size="sm" className="mt-4" onClick={resetFiltres}>
                                <X className="h-3.5 w-3.5 mr-1" /> Effacer les filtres
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="space-y-3">
                        {facturesData.map((f, i) => {
                            const meta = STATUT_META[f.statut] ?? STATUT_META.impaye;
                            return (
                                <motion.div key={f.id} initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.04 }}>
                                    <Card
                                        className={cn('hover:shadow-md transition-all cursor-pointer group border-l-4', meta.border)}
                                        onClick={() => router.visit(`/dossiers/${f.dossier.reference}?tab=facturation`)}
                                    >
                                        <CardContent className="p-4">
                                            <div className="flex items-start gap-4">
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center gap-2 flex-wrap">
                                                        <span className="font-ref text-sm text-seal font-semibold">{f.dossier.reference}</span>
                                                        <span className={cn('inline-flex items-center text-[10px] font-medium px-2 py-0.5 rounded-full border', meta.badge)}>
                                                            {meta.label}
                                                        </span>
                                                        {f.dossier.typeActe && (
                                                            <span className="bg-slate-50 border border-slate-200 rounded-full px-2 py-0.5 text-[10px] text-slate-500">
                                                                {f.dossier.typeActe}
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="text-sm font-medium text-slate-800 mt-1 truncate">{f.dossier.objet}</p>
                                                    {f.dossier.clientPrincipal && (
                                                        <p className="text-xs text-slate-400">{f.dossier.clientPrincipal}</p>
                                                    )}
                                                    <div className="flex items-center gap-4 mt-2 text-xs">
                                                        <span className="text-slate-500">Facturé : <strong className="font-ref text-slate-700">{fmt(f.total_chiffres)} GNF</strong></span>
                                                        <span className="text-slate-500">Encaissé : <strong className="font-ref text-success">{fmt(f.totalPaye)} GNF</strong></span>
                                                        <span className="text-slate-500">Solde : <strong className={cn('font-ref', f.soldeRestant > 0 ? 'text-warning-text' : 'text-success')}>{fmt(f.soldeRestant)} GNF</strong></span>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2 shrink-0 self-center">
                                                    <ChevronRight className="h-4 w-4 text-slate-300 group-hover:text-seal transition-colors" />
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </motion.div>
                            );
                        })}
                    </div>
                )}

                {/* Pagination */}
                {totalPages > 1 && (
                    <div className="flex items-center justify-between pt-2">
                        <span className="text-xs text-slate-500">Page {currentPage} sur {totalPages}</span>
                        <div className="flex items-center gap-2">
                            {factures.prev_page_url && (
                                <Button variant="outline" size="sm" onClick={() => router.get(factures.prev_page_url)}>
                                    <ChevronLeft className="h-4 w-4" /> Précédent
                                </Button>
                            )}
                            {factures.next_page_url && (
                                <Button variant="outline" size="sm" onClick={() => router.get(factures.next_page_url)}>
                                    Suivant <ChevronRight className="h-4 w-4" />
                                </Button>
                            )}
                        </div>
                    </div>
                )}

            </div>
        </AppLayout>
    );
}
