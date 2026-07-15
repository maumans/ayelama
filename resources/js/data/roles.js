// Source unique des rôles utilisateur — consommée par Parametres/Utilisateurs.jsx
// et Parametres/Index.jsx pour éviter la duplication de ROLE_META entre les deux pages.
export const ROLES = [
    { value: 'administrateur', label: 'Admin',      cls: 'bg-amber-50 text-amber-700 border-amber-200' },
    { value: 'notaire',        label: 'Notaire',    cls: 'bg-ink/5 text-ink border-ink/20' },
    { value: 'reviseur',       label: 'Réviseur',   cls: 'bg-purple-50 text-purple-700 border-purple-200' },
    { value: 'clerc',          label: 'Clerc',      cls: 'bg-blue-50 text-blue-700 border-blue-200' },
    { value: 'formaliste',     label: 'Formaliste', cls: 'bg-orange-50 text-orange-700 border-orange-200' },
    { value: 'comptable',      label: 'Comptable',  cls: 'bg-teal-50 text-teal-700 border-teal-200' },
];

export const ROLE_META = Object.fromEntries(ROLES.map(r => [r.value, r]));
