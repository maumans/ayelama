import React, { useState, useEffect } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    LayoutDashboard, FolderOpen, ClipboardCheck, Building2,
    FileText, Users, Mail, Settings, Bell, Search,
    ChevronLeft, ChevronRight, Plus, LogOut, User,
    Menu
} from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import GlobalSearch from '@/components/GlobalSearch';

/* ── Helpers couleurs dynamiques ───────────────────────────────────── */

function hexToRgb(hex) {
    const h = hex.replace('#', '');
    return [parseInt(h.slice(0,2),16), parseInt(h.slice(2,4),16), parseInt(h.slice(4,6),16)];
}
function adjustHex(hex, amt) {
    const [r,g,b] = hexToRgb(hex);
    const clamp = v => Math.min(255, Math.max(0, v + amt));
    return '#' + [clamp(r),clamp(g),clamp(b)].map(x => x.toString(16).padStart(2,'0')).join('');
}
function useTheme(apparence) {
    useEffect(() => {
        const primary = apparence?.couleur_primaire || '#0F2D60';
        const accent  = apparence?.couleur_accent   || '#E8A520';
        const fond    = apparence?.couleur_fond     || '#F5F5F3';
        const pMed    = adjustHex(primary, 12);
        const pLgt    = adjustHex(primary, 28);
        const aHov    = adjustHex(accent, -18);
        const [pr,pg,pb] = hexToRgb(primary);
        const [pm0,pm1,pm2] = hexToRgb(pMed);
        const [pl0,pl1,pl2] = hexToRgb(pLgt);
        const [ar,ag,ab] = hexToRgb(accent);

        const root = document.documentElement;
        root.style.setProperty('--color-ink',        primary);
        root.style.setProperty('--color-ink-medium',  pMed);
        root.style.setProperty('--color-ink-light',   pLgt);
        root.style.setProperty('--color-seal',        accent);
        root.style.setProperty('--color-seal-hover',  aHov);
        root.style.setProperty('--color-seal-light',  `rgba(${ar},${ag},${ab},0.12)`);
        root.style.setProperty('--color-app-bg',      fond);

        let el = document.getElementById('ayelama-theme');
        if (!el) { el = document.createElement('style'); el.id = 'ayelama-theme'; document.head.appendChild(el); }
        el.textContent = `
.bg-ink{background-color:${primary}!important}.bg-ink-medium{background-color:${pMed}!important}.bg-ink-light{background-color:${pLgt}!important}
.text-ink{color:${primary}!important}.border-ink{border-color:${primary}!important}.border-ink-medium{border-color:${pMed}!important}
.ring-ink{--tw-ring-color:${primary}!important}.hover\\:bg-ink-medium:hover{background-color:${pMed}!important}
.hover\\:bg-ink-medium\\/60:hover,.bg-ink-medium\\/60{background-color:rgba(${pm0},${pm1},${pm2},0.6)!important}
.bg-ink\\/5{background-color:rgba(${pr},${pg},${pb},0.05)!important}.bg-ink\\/10{background-color:rgba(${pr},${pg},${pb},0.1)!important}
.border-ink\\/20{border-color:rgba(${pr},${pg},${pb},0.2)!important}.bg-ink-light\\/30{background-color:rgba(${pl0},${pl1},${pl2},0.3)!important}
.active\\:bg-ink:active{background-color:${primary}!important}
.bg-seal{background-color:${accent}!important}.bg-seal-hover{background-color:${aHov}!important}
.text-seal{color:${accent}!important}.border-seal{border-color:${accent}!important}
.ring-seal{--tw-ring-color:${accent}!important}.focus-visible\\:ring-seal:focus-visible{--tw-ring-color:rgba(${ar},${ag},${ab},0.8)!important}
.bg-seal\\/20{background-color:rgba(${ar},${ag},${ab},0.2)!important}.bg-seal\\/10{background-color:rgba(${ar},${ag},${ab},0.1)!important}
.hover\\:bg-seal-hover:hover{background-color:${aHov}!important}.active\\:bg-seal:active{background-color:${accent}!important}
.shadow-seal,.hover\\:shadow-seal:hover{box-shadow:0 2px 8px 0 rgba(${ar},${ag},${ab},0.35)!important}
.bg-app-bg{background-color:${fond}!important}body{background-color:${fond}!important}
.badge-step-active{border-color:${accent}!important;color:${aHov}!important}
:focus-visible{outline-color:${accent}!important}
        `;
    }, [apparence?.couleur_primaire, apparence?.couleur_accent, apparence?.couleur_fond]);
}

function ScalesIcon({ className }) {
    return (
        <svg viewBox="0 0 40 40" className={cn('shrink-0', className)} fill="none">
            {/* Arc supérieur */}
            <path d="M8 20 Q20 6 32 20" stroke="white" strokeWidth="2.5" fill="none" strokeLinecap="round"/>
            {/* Losange central */}
            <path d="M20 9 L22 13 L20 17 L18 13 Z" fill="#E8A520"/>
            {/* Mât central */}
            <line x1="20" y1="13" x2="20" y2="33" stroke="white" strokeWidth="2"/>
            {/* Poutre horizontale */}
            <line x1="8" y1="21" x2="32" y2="21" stroke="white" strokeWidth="2"/>
            {/* Chaîne gauche */}
            <line x1="10" y1="21" x2="10" y2="27" stroke="white" strokeWidth="1.5"/>
            {/* Chaîne droite */}
            <line x1="30" y1="21" x2="30" y2="27" stroke="white" strokeWidth="1.5"/>
            {/* Plateau gauche */}
            <path d="M6 27 Q10 30.5 14 27" stroke="#E8A520" strokeWidth="2" fill="none" strokeLinecap="round"/>
            {/* Plateau droit */}
            <path d="M26 27 Q30 30.5 34 27" stroke="#E8A520" strokeWidth="2" fill="none" strokeLinecap="round"/>
        </svg>
    );
}

function LogoMark({ size = 'md', logoUrl, collapsed }) {
    if (logoUrl) {
        // Mode image : prend toute la hauteur du header, largeur auto
        const h = collapsed ? 'h-8 w-8' : 'h-10';
        return (
            <div className={cn('shrink-0 flex items-center justify-center', h)}>
                <img
                    src={logoUrl}
                    alt="Logo"
                    className={cn('object-contain', collapsed ? 'h-8 w-8 rounded-lg' : 'h-10 max-w-[120px]')}
                />
            </div>
        );
    }
    const s = size === 'sm' ? 'h-8 w-8' : 'h-9 w-9';
    return (
        <div className={cn('shrink-0 rounded-lg bg-ink-light/30 flex items-center justify-center overflow-hidden', s)}>
            <ScalesIcon className="h-6 w-6" />
        </div>
    );
}

function buildNavItems(can, notifications) {
    return [
        { href: '/dashboard',  label: 'Tableau de bord',  icon: LayoutDashboard, show: true },
        { href: '/dossiers',   label: 'Dossiers',          icon: FolderOpen,      show: true },
        { href: '/revisions',  label: 'Révisions',         icon: ClipboardCheck,  show: true, badge: notifications?.revisionCount || null },
        { href: '/formalites', label: 'Formalités',        icon: Building2,       show: true, badge: notifications?.urgentCount || null },
        { href: '/modeles',    label: "Modèles d'actes",   icon: FileText,        show: true },
        { href: '/repertoire', label: 'Répertoire',        icon: Users,           show: true },
        { href: '/courriers',  label: 'Courriers',         icon: Mail,            show: true },
    ].filter(item => item.show);
}

export default function AppLayout({ children, breadcrumbs = [] }) {
    const { auth, notifications, apparence } = usePage().props;
    const user = auth?.user;
    const can  = user?.can ?? {};
    const [collapsed, setCollapsed] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);

    useTheme(apparence);
    const currentPath = usePage().url;

    const navItems    = buildNavItems(can, notifications);
    const totalAlerts = (notifications?.urgentCount || 0) + (notifications?.revisionCount || 0);

    const initials = user?.initiales
        || (user?.name ? user.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase() : 'U');

    return (
        <TooltipProvider delayDuration={300}>
            <div className="flex h-screen overflow-hidden bg-app-bg">

                {/* Overlay mobile */}
                <AnimatePresence>
                    {mobileOpen && (
                        <motion.div
                            initial={{ opacity: 0 }}
                            animate={{ opacity: 1 }}
                            exit={{ opacity: 0 }}
                            className="fixed inset-0 z-40 bg-ink/40 lg:hidden"
                            onClick={() => setMobileOpen(false)}
                        />
                    )}
                </AnimatePresence>

                {/* Sidebar */}
                <motion.aside
                    animate={{ width: collapsed ? 64 : 240 }}
                    transition={{ duration: 0.2, ease: 'easeInOut' }}
                    className={cn(
                        'fixed inset-y-0 left-0 z-50 flex flex-col bg-ink border-r border-ink-medium overflow-hidden',
                        'lg:relative lg:translate-x-0',
                        mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
                    )}
                    style={{ width: collapsed ? 64 : 240 }}
                >
                    {/* Logo */}
                    <div className="flex items-center gap-3 px-3 py-3 border-b border-ink-medium h-14 shrink-0">
                        <LogoMark size={collapsed ? 'sm' : 'md'} logoUrl={apparence?.logo_url} collapsed={collapsed} />
                        <AnimatePresence>
                            {!collapsed && (
                                <motion.div
                                    initial={{ opacity: 0, x: -8 }}
                                    animate={{ opacity: 1, x: 0 }}
                                    exit={{ opacity: 0, x: -8 }}
                                    transition={{ duration: 0.15 }}
                                    className="overflow-hidden min-w-0"
                                >
                                    <span className="font-serif text-white font-semibold text-sm leading-tight block truncate">
                                        {apparence?.office_nom || 'Maître Ayelama Bah'}
                                    </span>
                                    <span className="text-seal text-[9px] tracking-[0.18em] uppercase font-medium block mt-0.5">
                                        {apparence?.office_sous_titre || 'Notaire'}
                                    </span>
                                </motion.div>
                            )}
                        </AnimatePresence>
                    </div>

                    {/* Navigation */}
                    <nav className="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
                        {navItems.map((item) => {
                            const Icon = item.icon;
                            const isActive = currentPath.startsWith(item.href);
                            return (
                                <Tooltip key={item.href} disabled={!collapsed}>
                                    <TooltipTrigger asChild>
                                        <Link
                                            href={item.href}
                                            className={cn(
                                                'relative flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors',
                                                isActive
                                                    ? 'bg-ink-medium text-white'
                                                    : 'text-slate-300 hover:bg-ink-medium/60 hover:text-white'
                                            )}
                                        >
                                            <Icon className={cn('h-4 w-4 shrink-0', isActive && 'text-seal')} />
                                            <AnimatePresence>
                                                {!collapsed && (
                                                    <motion.span
                                                        initial={{ opacity: 0 }}
                                                        animate={{ opacity: 1 }}
                                                        exit={{ opacity: 0 }}
                                                        className="flex-1 truncate"
                                                    >
                                                        {item.label}
                                                    </motion.span>
                                                )}
                                            </AnimatePresence>
                                            {!collapsed && item.badge > 0 && (
                                                <span className="ml-auto text-xs font-semibold bg-seal/20 text-seal px-1.5 py-0.5 rounded-full">
                                                    {item.badge}
                                                </span>
                                            )}
                                            {collapsed && item.badge > 0 && (
                                                <span className="absolute top-1 right-1 h-2 w-2 bg-seal rounded-full" />
                                            )}
                                        </Link>
                                    </TooltipTrigger>
                                    <TooltipContent side="right">
                                        {item.label}
                                        {item.badge > 0 && <span className="ml-2 font-semibold text-seal">({item.badge})</span>}
                                    </TooltipContent>
                                </Tooltip>
                            );
                        })}
                    </nav>

                    {/* Bas: paramètres + profil */}
                    <div className="border-t border-ink-medium px-2 py-2 space-y-0.5">
                        {can?.administrer && (
                            <Tooltip disabled={!collapsed}>
                                <TooltipTrigger asChild>
                                    <Link
                                        href="/parametres"
                                        className={cn(
                                            'flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm font-medium transition-colors',
                                            currentPath.startsWith('/parametres')
                                                ? 'bg-ink-medium text-white'
                                                : 'text-slate-400 hover:bg-ink-medium/60 hover:text-white'
                                        )}
                                    >
                                        <Settings className="h-4 w-4 shrink-0" />
                                        {!collapsed && <span className="truncate">Paramètres</span>}
                                    </Link>
                                </TooltipTrigger>
                                <TooltipContent side="right">Paramètres</TooltipContent>
                            </Tooltip>
                        )}

                        {/* Profil */}
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="flex items-center gap-3 w-full rounded-lg px-2.5 py-2 text-sm text-slate-300 hover:bg-ink-medium/60 hover:text-white transition-colors mt-1">
                                    <Avatar className="h-7 w-7 shrink-0">
                                        <AvatarFallback className="text-[10px] bg-seal text-white">{initials}</AvatarFallback>
                                    </Avatar>
                                    <AnimatePresence>
                                        {!collapsed && (
                                            <motion.div
                                                initial={{ opacity: 0 }}
                                                animate={{ opacity: 1 }}
                                                exit={{ opacity: 0 }}
                                                className="flex-1 text-left overflow-hidden"
                                            >
                                                <div className="text-xs font-medium text-white truncate">{user?.name || 'Utilisateur'}</div>
                                                <div className="text-[10px] text-slate-400 truncate">{user?.roleLabel || user?.role || ''}</div>
                                            </motion.div>
                                        )}
                                    </AnimatePresence>
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent side="right" align="end" className="w-48">
                                <DropdownMenuLabel className="font-normal">
                                    <div className="font-medium">{user?.name}</div>
                                    <div className="text-xs text-slate-500">{user?.roleLabel}</div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link href="/profile"><User className="h-4 w-4 mr-2" /> Mon profil</Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link href="/logout" method="post" as="button" className="w-full text-danger-text">
                                        <LogOut className="h-4 w-4 mr-2 text-danger" /> Déconnexion
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    {/* Collapse toggle */}
                    <button
                        onClick={() => setCollapsed(!collapsed)}
                        className="hidden lg:flex absolute -right-3 top-1/2 -translate-y-1/2 h-6 w-6 items-center justify-center rounded-full bg-ink border border-ink-medium text-slate-400 hover:text-white transition-colors shadow-sm z-10"
                    >
                        {collapsed ? <ChevronRight className="h-3 w-3" /> : <ChevronLeft className="h-3 w-3" />}
                    </button>
                </motion.aside>

                {/* Zone principale */}
                <div className="flex-1 flex flex-col min-w-0 overflow-hidden">

                    {/* Topbar */}
                    <header className="h-14 border-b border-slate-200 bg-white/95 backdrop-blur-sm flex items-center gap-3 px-4 shrink-0 z-30">
                        <button
                            onClick={() => setMobileOpen(!mobileOpen)}
                            className="lg:hidden p-1.5 rounded-md text-slate-500 hover:bg-slate-100 transition-colors"
                        >
                            <Menu className="h-5 w-5" />
                        </button>

                        {/* Fil d'Ariane */}
                        <nav className="flex items-center gap-1.5 text-sm min-w-0">
                            <Link href="/dashboard" className="text-slate-400 hover:text-slate-600 transition-colors shrink-0">
                                <LayoutDashboard className="h-3.5 w-3.5" />
                            </Link>
                            {breadcrumbs.map((crumb, i) => (
                                <React.Fragment key={i}>
                                    <span className="text-slate-300">/</span>
                                    {crumb.href ? (
                                        <Link href={crumb.href} className="text-slate-500 hover:text-slate-700 transition-colors truncate">
                                            {crumb.label}
                                        </Link>
                                    ) : (
                                        <span className="text-slate-700 font-medium truncate">{crumb.label}</span>
                                    )}
                                </React.Fragment>
                            ))}
                        </nav>

                        <div className="flex-1" />

                        {/* Recherche ⌘K */}
                        <button
                            id="global-search-trigger"
                            className="hidden md:flex items-center gap-2 h-8 px-3 rounded-lg border border-slate-200 bg-slate-50 text-sm text-slate-400 hover:border-slate-300 hover:bg-white transition-colors min-w-[160px]"
                        >
                            <Search className="h-3.5 w-3.5 shrink-0" />
                            <span className="flex-1 text-left">Rechercher…</span>
                            <kbd className="text-xs text-slate-300 font-mono">⌘K</kbd>
                        </button>

                        {/* Nouveau dossier (clercs et notaires) */}
                        {can?.creerDossier && (
                            <Button size="sm" variant="seal" asChild>
                                <Link href="/dossiers/create">
                                    <Plus className="h-3.5 w-3.5" />
                                    <span className="hidden sm:inline">Nouveau dossier</span>
                                </Link>
                            </Button>
                        )}

                        {/* Notifications */}
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button className="relative p-1.5 rounded-md text-slate-500 hover:bg-slate-100 transition-colors">
                                    <Bell className="h-5 w-5" />
                                    {totalAlerts > 0 && (
                                        <span className="absolute top-1 right-1 h-2 w-2 bg-warning rounded-full" />
                                    )}
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {totalAlerts > 0 ? `${totalAlerts} alerte(s) en attente` : 'Aucune alerte'}
                            </TooltipContent>
                        </Tooltip>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <button className="rounded-full focus:outline-none focus:ring-2 focus:ring-seal/40">
                                    <Avatar className="h-7 w-7 cursor-pointer">
                                        <AvatarFallback className="text-[10px] bg-ink text-white">{initials}</AvatarFallback>
                                    </Avatar>
                                </button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-48">
                                <DropdownMenuLabel className="font-normal">
                                    <div className="font-medium">{user?.name}</div>
                                    <div className="text-xs text-slate-500">{user?.roleLabel}</div>
                                </DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link href="/profile"><User className="h-4 w-4 mr-2" /> Mon profil</Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem asChild>
                                    <Link href="/logout" method="post" as="button" className="w-full text-danger-text">
                                        <LogOut className="h-4 w-4 mr-2 text-danger" /> Déconnexion
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </header>

                    {/* Recherche globale ⌘K */}
                    <GlobalSearch />

                    {/* Contenu */}
                    <main className="flex-1 overflow-y-auto">
                        <motion.div
                            initial={{ opacity: 0, y: 6 }}
                            animate={{ opacity: 1, y: 0 }}
                            transition={{ duration: 0.2 }}
                            className="h-full"
                        >
                            {children}
                        </motion.div>
                    </main>
                </div>
            </div>
        </TooltipProvider>
    );
}
