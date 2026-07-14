// Métadonnées partagées entre Dossiers/Show.jsx (onglet Formalités) et
// Formalites/Index.jsx (liste globale) — évite que les deux pages divergent
// sur les libellés/couleurs affichés pour un même statut ou organisme.

export const STATUT_META = {
    a_deposer:   { label: 'À déposer',          badge: 'bg-slate-100 text-slate-600 border-slate-200', border: 'border-l-slate-400' },
    depose:      { label: 'Déposé',             badge: 'bg-blue-50 text-blue-700 border-blue-200',      border: 'border-l-blue-500' },
    en_attente:  { label: 'En attente retour',  badge: 'bg-amber-50 text-amber-700 border-amber-200',   border: 'border-l-amber-400' },
    retour_recu: { label: 'Retour reçu',        badge: 'bg-green-50 text-green-700 border-green-200',   border: 'border-l-green-500' },
    rejete:      { label: 'Rejeté — à corriger', badge: 'bg-danger-bg text-danger-text border-red-200', border: 'border-l-danger' },
    cloture:     { label: 'Clôturé',            badge: 'bg-ink/5 text-ink/40 border-ink/10',            border: 'border-l-ink/20' },
};

export const ORGANISME_META = {
    apip:                   { label: 'APIP',                  badge: 'bg-blue-50 text-blue-700 border-blue-200' },
    impots:                 { label: 'IMPÔTS',                badge: 'bg-sky-50 text-sky-700 border-sky-200' },
    conservation_fonciere:  { label: 'FONCIER',               badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    cnss:                   { label: 'CNSS',                  badge: 'bg-teal-50 text-teal-700 border-teal-200' },
    greffe:                 { label: 'GREFFE',                badge: 'bg-purple-50 text-purple-700 border-purple-200' },
};

export function organismeBadgeClass(organisme) {
    return ORGANISME_META[organisme]?.badge ?? 'bg-slate-100 text-slate-600 border-slate-200';
}

export function organismeShortLabel(organisme, fallback) {
    return ORGANISME_META[organisme]?.label ?? (fallback ? fallback.toUpperCase() : organisme?.toUpperCase());
}
