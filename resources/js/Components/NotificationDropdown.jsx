import { useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { Bell, Check } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export default function NotificationDropdown({ unreadCount = 0 }) {
    const [items, setItems] = useState(null);
    const [loading, setLoading] = useState(false);

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
                        <span className="absolute top-1 right-1 h-2 w-2 bg-warning rounded-full" />
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
                    {!loading && items?.map((item) => (
                        <button
                            key={item.id}
                            onClick={() => openItem(item)}
                            className={cn(
                                'w-full text-left px-3 py-2.5 border-b border-slate-50 hover:bg-slate-50 transition-colors',
                                !item.read_at && 'bg-seal-light/40'
                            )}
                        >
                            <p className="text-sm text-ink">{item.data?.message}</p>
                            <p className="text-xs text-slate-400 mt-0.5">
                                {new Date(item.created_at).toLocaleString('fr-FR')}
                            </p>
                        </button>
                    ))}
                </div>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
