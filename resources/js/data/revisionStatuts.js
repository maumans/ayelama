// Métadonnées de statut de révision partagées entre Revisions/Index.jsx,
// Dossiers/Revision.jsx et Dossiers/Show.jsx — les libellés reprennent mot pour
// mot App\Enums\StatutRevision::label() pour éviter le drift de formulation
// qui existait entre ces trois pages (ex. "Validée" vs "Validé").

export const STATUT_META = {
    en_attente: { label: 'En attente',               badge: 'bg-slate-100 text-slate-600 border-slate-200', border: 'border-l-slate-300' },
    en_cours:   { label: 'En cours',                 badge: 'bg-amber-50 text-amber-700 border-amber-200',  border: 'border-l-warning' },
    valide:     { label: 'Validé',                   badge: 'bg-green-50 text-green-700 border-green-200',  border: 'border-l-success' },
    renvoye:    { label: 'Renvoyé en correction',     badge: 'bg-red-50 text-red-700 border-red-200',        border: 'border-l-danger' },
};
