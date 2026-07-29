import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { Bell, Check, ClipboardCheck, Building2, Clock, Inbox, FolderCheck, FileSignature, Bell as BellFallback } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

// Un type par classe de Notification backend (voir data.type dans chaque
// app/Notifications/*.php) — permet de distinguer d'un coup d'œil une
// révision d'une formalité, échéance ou nouvelle demande.
const TYPE_META = {
    revision: { icon: ClipboardCheck, label: 'Certification',  bg: 'bg-seal-light',  text: 'text-seal' },
    formalite: { icon: Building2,      label: 'Formalité', bg: 'bg-info-bg',     text: 'text-info-text' },
    echeance: { icon: Clock,          label: 'Échéance',  bg: 'bg-warning-bg',  text: 'text-warning-text' },
    demande: { icon: Inbox,           label: 'Demande',   bg: 'bg-success-bg',  text: 'text-success-text' },
    demande_convertie: { icon: FolderCheck, label: 'Dossier créé', bg: 'bg-slate-100', text: 'text-ink' },
    signature_client: { icon: FileSignature, label: 'Signature client', bg: 'bg-purple-50', text: 'text-purple-700' },
    signature_notaire: { icon: FileSignature, label: 'Signature notaire', bg: 'bg-violet-50', text: 'text-violet-700' },
};
const DEFAULT_TYPE_META = { icon: BellFallback, label: null, bg: 'bg-slate-100', text: 'text-slate-500' };

export default function NotificationDropdown({ unreadCount = 0, incoming }) {
    const [items, setItems] = useState(null);
    const [loading, setLoading] = useState(false);

    // Notification reçue en temps réel (Pusher) — l'insère en tête de liste si
    // celle-ci a déjà été chargée, pour ne pas attendre une réouverture du menu.
    useEffect(() => {
        if (!incoming) return;
        setItems((prev) => (prev === null ? null : [
            {
                id: incoming.id,
                data: incoming,
                read_at: null,
                created_at: new Date().toISOString(),
            },
            ...prev,
        ]));
    }, [incoming]);

    const load = async () => {
        setLoading(true);
        try {
            const { data } = await axios.get(route('notifications.index'));
            setItems(data.data ?? []);
        } finally {
            setLoading(false);
        }
    };

    const openItem = (item) => {
        if (!item.read_at) {
            axios.post(route('notifications.read', item.id)).catch(() => {});
        }
        if (item.data?.href) {
            router.visit(item.data.href);
        }
    };

    const markAllAsRead = () => {
        axios.post(route('notifications.readAll')).then(() => {
            setItems((prev) => prev?.map((i) => ({ ...i, read_at: i.read_at ?? new Date().toISOString() })));
        });
    };

    return (
        <DropdownMenu onOpenChange={(open) => { if (open && items === null) load(); }}>
            <DropdownMenuTrigger asChild>
                <button className="relative p-1.5 rounded-md text-slate-500 hover:bg-slate-100 transition-colors">
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-1 -right-1 min-w-[16px] h-4 px-1 flex items-center justify-center rounded-full bg-seal text-white text-[10px] font-semibold leading-none">
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    )}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80 p-0">
                <div className="flex items-center justify-between px-3 py-2 border-b border-slate-100">
                    <span className="text-sm font-semibold text-ink">Notifications</span>
                    <button
                        onClick={markAllAsRead}
                        className="flex items-center gap-1 text-xs text-seal hover:underline"
                    >
                        <Check className="h-3 w-3" /> Tout marquer lu
                    </button>
                </div>
                <div className="max-h-96 overflow-y-auto">
                    {loading && (
                        <p className="text-sm text-slate-400 px-3 py-4 text-center">Chargement…</p>
                    )}
                    {!loading && items?.length === 0 && (
                        <p className="text-sm text-slate-400 px-3 py-4 text-center">Aucune notification.</p>
                    )}
                    {!loading && items?.map((item) => {
                        const meta = TYPE_META[item.data?.type] ?? DEFAULT_TYPE_META;
                        const TypeIcon = meta.icon;
                        return (
                            <button
                                key={item.id}
                                onClick={() => openItem(item)}
                                className={cn(
                                    'w-full text-left px-3 py-2.5 border-b border-slate-50 hover:bg-slate-50 transition-colors flex items-start gap-2.5',
                                    !item.read_at && 'bg-seal-light/40'
                                )}
                            >
                                <span className={cn('shrink-0 h-7 w-7 rounded-full flex items-center justify-center mt-0.5', meta.bg, meta.text)}>
                                    <TypeIcon className="h-3.5 w-3.5" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    {meta.label && (
                                        <span className={cn('text-[10px] font-semibold uppercase tracking-wide', meta.text)}>
                                            {meta.label}
                                        </span>
                                    )}
                                    <p className="text-sm text-ink">{item.data?.message}</p>
                                    <p className="text-xs text-slate-400 mt-0.5">
                                        {new Date(item.created_at).toLocaleString('fr-FR')}
                                    </p>
                                </span>
                            </button>
                        );
                    })}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
