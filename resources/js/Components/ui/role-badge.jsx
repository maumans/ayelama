import { ROLE_META } from '@/data/roles';
import { cn } from '@/lib/utils';

export function RoleBadge({ role, className = '' }) {
    const meta = ROLE_META[role] ?? { label: role, cls: 'bg-slate-100 text-slate-600 border-slate-200' };
    return (
        <span className={cn('text-[10px] px-2 py-0.5 rounded-full border', meta.cls, className)}>
            {meta.label}
        </span>
    );
}

export function RoleBadgeList({ roles = [] }) {
    if (!roles.length) return <span className="text-xs text-slate-400">—</span>;
    return (
        <div className="flex flex-wrap gap-1">
            {roles.map(r => <RoleBadge key={r} role={r} />)}
        </div>
    );
}
