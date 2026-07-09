import { useState, useEffect, useRef, useCallback } from 'react';
import { Head, router } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import AppLayout from '@/Layouts/AppLayout';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Tooltip, TooltipContent, TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    ArrowUpDown, Briefcase, CreditCard, ExternalLink,
    FileText, Mail, MapPin, Phone, Search, Users, UserCheck, X,
} from 'lucide-react';

/* ─── Role metadata ──────────────────────────────────────── */

const ROLE_META = {
    acheteur:       { label: 'Acheteur',       badge: 'bg-blue-50 text-blue-700 border-blue-200',         avatar: 'bg-blue-600' },
    vendeur:        { label: 'Vendeur',        badge: 'bg-emerald-50 text-emerald-700 border-emerald-200', avatar: 'bg-emerald-600' },
    associe:        { label: 'Associé',        badge: 'bg-purple-50 text-purple-700 border-purple-200',    avatar: 'bg-purple-600' },
    associe_unique: { label: 'Associé unique', badge: 'bg-purple-50 text-purple-700 border-purple-200',    avatar: 'bg-purple-600' },
    gerant:         { label: 'Gérant',         badge: 'bg-amber-50 text-amber-700 border-amber-200',       avatar: 'bg-amber-600' },
    heritier:       { label: 'Héritier',       badge: 'bg-rose-50 text-rose-700 border-rose-200',          avatar: 'bg-rose-600' },
    mandant:        { label: 'Mandant',        badge: 'bg-indigo-50 text-indigo-700 border-indigo-200',    avatar: 'bg-indigo-600' },
    mandataire:     { label: 'Mandataire',     badge: 'bg-teal-50 text-teal-700 border-teal-200',          avatar: 'bg-teal-600' },
    donateur:       { label: 'Donateur',       badge: 'bg-orange-50 text-orange-700 border-orange-200',    avatar: 'bg-orange-600' },
    donataire:      { label: 'Donataire',      badge: 'bg-pink-50 text-pink-700 border-pink-200',          avatar: 'bg-pink-600' },
    epoux:          { label: 'Époux',          badge: 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200', avatar: 'bg-fuchsia-600' },
    epouse:         { label: 'Épouse',         badge: 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200', avatar: 'bg-fuchsia-600' },
    creancier:      { label: 'Créancier',      badge: 'bg-red-50 text-red-700 border-red-200',             avatar: 'bg-red-600' },
    debiteur:       { label: 'Débiteur',       badge: 'bg-slate-100 text-slate-600 border-slate-200',      avatar: 'bg-slate-500' },
    bailleur:       { label: 'Bailleur',       badge: 'bg-cyan-50 text-cyan-700 border-cyan-200',          avatar: 'bg-cyan-600' },
    locataire:      { label: 'Locataire',      badge: 'bg-cyan-50 text-cyan-700 border-cyan-200',          avatar: 'bg-cyan-600' },
    actionnaire:    { label: 'Actionnaire',    badge: 'bg-purple-50 text-purple-700 border-purple-200',    avatar: 'bg-purple-600' },
    administrateur: { label: 'Administrateur', badge: 'bg-amber-50 text-amber-700 border-amber-200',       avatar: 'bg-amber-600' },
    membre:         { label: 'Membre',         badge: 'bg-purple-50 text-purple-700 border-purple-200',    avatar: 'bg-purple-600' },
    liquidateur:    { label: 'Liquidateur',    badge: 'bg-slate-100 text-slate-600 border-slate-200',      avatar: 'bg-slate-500' },
};

const DEFAULT_ROLE = { label: null, badge: 'bg-slate-100 text-slate-600 border-slate-200', avatar: 'bg-ink' };

const getRoleMeta  = (role) => ROLE_META[role?.toLowerCase()] ?? DEFAULT_ROLE;
const getRoleLabel = (role) => {
    const m = getRoleMeta(role);
    return m.label ?? (role ? role.charAt(0).toUpperCase() + role.slice(1) : '—');
};

/* ─── PartieCard ─────────────────────────────────────────── */

function PartieCard({ partie, onFilterClient }) {
    const meta = getRoleMeta(partie.role);

    return (
        <div className="bg-white border border-slate-200 rounded-lg p-4 hover:shadow-md hover:border-slate-300 transition-all h-full flex flex-col">
            {/* header */}
            <div className="flex items-start gap-3 mb-3">
                <Avatar className="h-9 w-9 shrink-0">
                    <AvatarFallback className={`text-sm text-white ${meta.avatar}`}>
                        {partie.initiales}
                    </AvatarFallback>
                </Avatar>
                <div className="flex-1 min-w-0">
                    <div className="font-medium text-sm text-ink leading-tight">{partie.nom}</div>
                    <div className="flex items-center gap-1 mt-0.5 flex-wrap">
                        <Badge className={`text-[10px] px-1.5 py-0 border ${meta.badge}`}>
                            {getRoleLabel(partie.role)}
                        </Badge>
                        {partie.client_id && (
                            <button
                                type="button"
                                onClick={() => onFilterClient(partie.client_id)}
                                title={`Client enregistré (${partie.client?.type === 'morale' ? 'personne morale' : 'personne physique'}) — voir ses autres dossiers`}
                                className="inline-flex items-center gap-1 text-[10px] px-1.5 py-0 rounded-full border bg-ink/5 text-ink border-ink/20 hover:bg-ink/10 transition-colors"
                            >
                                <UserCheck className="h-2.5 w-2.5" />
                                Client
                            </button>
                        )}
                    </div>
                </div>
            </div>

            {/* contact */}
            <div className="space-y-1 flex-1">
                {partie.cni && (
                    <div className="flex items-center gap-1.5 text-xs text-slate-600">
                        <CreditCard className="h-3 w-3 text-slate-400 shrink-0" />
                        <span className="font-mono">{partie.cni}</span>
                    </div>
                )}
                {partie.telephone && (
                    <div className="flex items-center gap-1.5 text-xs text-slate-600">
                        <Phone className="h-3 w-3 text-slate-400 shrink-0" />
                        {partie.telephone}
                    </div>
                )}
                {partie.email && (
                    <div className="flex items-center gap-1.5 text-xs text-slate-500">
                        <Mail className="h-3 w-3 text-slate-400 shrink-0" />
                        <span className="truncate">{partie.email}</span>
                    </div>
                )}
                {partie.adresse && (
                    <div className="flex items-start gap-1.5 text-xs text-slate-500">
                        <MapPin className="h-3 w-3 text-slate-400 shrink-0 mt-0.5" />
                        <span className="line-clamp-1">{partie.adresse}</span>
                    </div>
                )}
            </div>

            {/* dossier */}
            <div className="mt-3 pt-2 border-t border-slate-100">
                {partie.dossier?.reference ? (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                onClick={() => router.visit(`/dossiers/${partie.dossier.reference}`)}
                                className="flex items-center gap-1.5 text-xs text-seal hover:underline w-full text-left group"
                            >
                                <FileText className="h-3 w-3 shrink-0" />
                                <span className="font-mono">{partie.dossier.reference}</span>
                                {partie.dossier.typeActe && (
                                    <span className="text-slate-400 truncate">— {partie.dossier.typeActe}</span>
                                )}
                                <ExternalLink className="h-3 w-3 ml-auto shrink-0 opacity-0 group-hover:opacity-60 transition-opacity" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom" className="max-w-xs">
                            {partie.dossier.objet && (
                                <p className="text-xs font-medium">{partie.dossier.objet}</p>
                            )}
                            {partie.dossier.etape && (
                                <p className="text-xs text-slate-400 mt-0.5">Étape : {partie.dossier.etape}</p>
                            )}
                        </TooltipContent>
                    </Tooltip>
                ) : (
                    <span className="text-xs text-slate-300 italic">Sans dossier lié</span>
                )}
            </div>
        </div>
    );
}

/* ─── Page ───────────────────────────────────────────────── */

export default function RepertoireIndex({ parties, stats, roles, filters: init, clientFiltre }) {
    const [q,        setQ]        = useState(init?.q        ?? '');
    const [role,     setRole]     = useState(init?.role     ?? '');
    const [sort,     setSort]     = useState(init?.sort     ?? '');
    const [clientId, setClientId] = useState(init?.client_id ?? '');
    const firstRender     = useRef(true);

    const apply = useCallback((params) => {
        const clean = Object.fromEntries(Object.entries(params).filter(([, v]) => v));
        router.get('/repertoire', clean, { preserveState: true, replace: true });
    }, []);

    /* debounce search input */
    useEffect(() => {
        if (firstRender.current) { firstRender.current = false; return; }
        const t = setTimeout(() => apply({ q, role, sort, client_id: clientId }), 350);
        return () => clearTimeout(t);
    }, [q]);

    const setRoleFilter = (r) => {
        const next = r === role ? '' : r;
        setRole(next);
        apply({ q, role: next, sort, client_id: clientId });
    };

    const setSortFilter = (s) => {
        const next = s === '__none__' ? '' : s;
        setSort(next);
        apply({ q, role, sort: next, client_id: clientId });
    };

    const onFilterClient = (id) => {
        setClientId(id);
        apply({ q, role, sort, client_id: id });
    };

    const reset = () => {
        setQ(''); setRole(''); setSort(''); setClientId('');
        router.get('/repertoire', {}, { preserveState: true, replace: true });
    };

    const hasFilters = q || role || sort || clientId;
    const data       = parties?.data ?? [];
    const parRole    = stats?.parRole ?? [];
    const total      = stats?.total ?? 0;

    return (
        <AppLayout breadcrumbs={[{ label: 'Répertoire' }]}>
            <Head title="Répertoire — Ayelema" />

            <div className="p-6 max-w-6xl mx-auto space-y-5">

                {/* header */}
                <div>
                    <h1 className="text-xl font-semibold text-ink">Répertoire des parties</h1>
                    <p className="text-sm text-slate-500 mt-0.5">
                        {total} personne{total !== 1 ? 's' : ''} enregistrée{total !== 1 ? 's' : ''}
                    </p>
                </div>

                {/* KPIs */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div className="bg-white rounded-lg border border-slate-200 p-3 shadow-sm">
                        <div className="flex items-center justify-between mb-1">
                            <span className="text-xs text-slate-500">Personnes enregistrées</span>
                            <Users className="h-4 w-4 text-ink" />
                        </div>
                        <div className="text-2xl font-semibold text-ink">{total}</div>
                    </div>
                    <div className="bg-white rounded-lg border border-slate-200 p-3 shadow-sm">
                        <div className="flex items-center justify-between mb-1">
                            <span className="text-xs text-slate-500">Dossiers couverts</span>
                            <FileText className="h-4 w-4 text-seal" />
                        </div>
                        <div className="text-2xl font-semibold text-seal">{stats?.dossiersCouverts ?? 0}</div>
                    </div>
                    <div className="bg-white rounded-lg border border-slate-200 p-3 shadow-sm">
                        <div className="flex items-center justify-between mb-1">
                            <span className="text-xs text-slate-500">Rôles distincts</span>
                            <Briefcase className="h-4 w-4 text-slate-500" />
                        </div>
                        <div className="text-2xl font-semibold text-slate-600">{stats?.rolesDisctincts ?? 0}</div>
                    </div>
                </div>

                {/* répartition par rôle */}
                {parRole.length > 0 && (
                    <div className="bg-white rounded-lg border border-slate-200 p-4 shadow-sm">
                        <p className="text-xs font-medium text-slate-500 mb-2">Répartition par rôle</p>
                        {/* barre empilée */}
                        <div className="flex h-2 rounded-full overflow-hidden mb-3 gap-px">
                            {parRole.map(({ role: r, count }) => {
                                const m   = getRoleMeta(r);
                                const pct = total > 0 ? (count / total) * 100 : 0;
                                return (
                                    <Tooltip key={r}>
                                        <TooltipTrigger asChild>
                                            <div
                                                className={`h-full cursor-pointer hover:opacity-80 transition-opacity ${m.avatar}`}
                                                style={{ width: `${pct}%`, minWidth: pct > 0 ? '4px' : '0' }}
                                            />
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p className="text-xs">{getRoleLabel(r)} : {count}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                );
                            })}
                        </div>
                        {/* pills cliquables */}
                        <div className="flex flex-wrap gap-1.5">
                            {parRole.map(({ role: r, count }) => {
                                const m = getRoleMeta(r);
                                return (
                                    <button
                                        key={r}
                                        onClick={() => setRoleFilter(r)}
                                        className={`flex items-center gap-1 text-xs px-2 py-0.5 rounded-full border transition-all ${
                                            role === r
                                                ? `${m.badge} ring-1 ring-offset-1 ring-current font-medium`
                                                : `${m.badge} opacity-60 hover:opacity-100`
                                        }`}
                                    >
                                        {getRoleLabel(r)}
                                        <span className="font-semibold">{count}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                )}

                {/* filters */}
                <div className="flex flex-wrap items-center gap-2">
                    {/* search */}
                    <div className="relative flex-1 min-w-[200px] max-w-sm">
                        <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-slate-400" />
                        <Input
                            value={q}
                            onChange={e => setQ(e.target.value)}
                            placeholder="Nom, CNI, téléphone, email…"
                            className="pl-8 h-8 text-sm"
                        />
                        {q && (
                            <button
                                onClick={() => { setQ(''); apply({ q: '', role, sort, client_id: clientId }); }}
                                className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>

                    {/* role chips */}
                    <div className="flex flex-wrap items-center gap-1">
                        {(roles ?? []).map(r => {
                            const m = getRoleMeta(r);
                            return (
                                <button
                                    key={r}
                                    onClick={() => setRoleFilter(r)}
                                    className={`h-8 px-3 rounded-md border text-xs transition-all ${
                                        role === r
                                            ? `${m.badge} font-medium`
                                            : 'border-slate-200 text-slate-600 hover:bg-slate-50'
                                    }`}
                                >
                                    {getRoleLabel(r)}
                                </button>
                            );
                        })}
                    </div>

                    {/* sort */}
                    <Select value={sort || '__none__'} onValueChange={setSortFilter}>
                        <SelectTrigger className="h-8 w-[145px] text-sm">
                            <ArrowUpDown className="h-3.5 w-3.5 text-slate-400 mr-1 shrink-0" />
                            <SelectValue placeholder="Trier" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none__">Nom A–Z</SelectItem>
                            <SelectItem value="role">Par rôle</SelectItem>
                            <SelectItem value="recent">Plus récent</SelectItem>
                        </SelectContent>
                    </Select>

                    {hasFilters && (
                        <Button variant="ghost" size="sm" onClick={reset} className="h-8 gap-1 text-slate-500">
                            <X className="h-3.5 w-3.5" /> Réinitialiser
                        </Button>
                    )}
                </div>

                {clientId && (
                    <div className="flex items-center gap-2 text-sm bg-ink/5 border border-ink/15 rounded-lg px-3 py-1.5 w-fit">
                        <UserCheck className="h-3.5 w-3.5 text-ink" />
                        Filtré sur le client : <span className="font-medium">{clientFiltre ?? clientId}</span>
                        <button onClick={() => onFilterClient('')} className="text-slate-400 hover:text-slate-600">
                            <X className="h-3.5 w-3.5" />
                        </button>
                    </div>
                )}

                {hasFilters && data.length > 0 && (
                    <p className="text-xs text-slate-400">
                        {parties?.total ?? data.length} résultat{(parties?.total ?? data.length) !== 1 ? 's' : ''}
                    </p>
                )}

                {/* grid */}
                {data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-20 text-slate-400">
                        <Users className="h-10 w-10 mb-3 opacity-30" />
                        <p className="text-sm">Aucune partie trouvée</p>
                        {hasFilters && (
                            <button onClick={reset} className="text-xs text-seal hover:underline mt-2">
                                Effacer les filtres
                            </button>
                        )}
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <AnimatePresence mode="popLayout">
                            {data.map((p, i) => (
                                <motion.div
                                    key={p.id}
                                    initial={{ opacity: 0, y: 8 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ delay: i * 0.03 }}
                                >
                                    <PartieCard partie={p} onFilterClient={onFilterClient} />
                                </motion.div>
                            ))}
                        </AnimatePresence>
                    </div>
                )}

                {/* pagination */}
                {(parties?.last_page ?? 1) > 1 && (
                    <div className="flex justify-center gap-2 pt-2">
                        {(parties?.links ?? []).map((link, i) => (
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
        </AppLayout>
    );
}
