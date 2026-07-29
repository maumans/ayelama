// Métadonnées de couleur par étape de dossier, partagées entre Dossiers/Index.jsx
// et Dossiers/Show.jsx — un seul endroit pour éviter le drift de couleur entre
// la liste et la fiche dossier (même esprit que data/revisionStatuts.js).

export const ETAPE_META = {
    initialisation:    { dot: 'bg-slate-400',  badge: 'bg-slate-100 text-slate-600 border-slate-200',  bar: 'bg-slate-300'  },
    edition:           { dot: 'bg-blue-400',   badge: 'bg-blue-50 text-blue-700 border-blue-200',      bar: 'bg-blue-300'   },
    revision:          { dot: 'bg-amber-400',  badge: 'bg-amber-50 text-amber-700 border-amber-200',   bar: 'bg-amber-300'  },
    signature_client:  { dot: 'bg-purple-400', badge: 'bg-purple-50 text-purple-700 border-purple-200',bar: 'bg-purple-300' },
    signature_notaire: { dot: 'bg-violet-400', badge: 'bg-violet-50 text-violet-700 border-violet-200',bar: 'bg-violet-300' },
    formalites:        { dot: 'bg-orange-400', badge: 'bg-orange-50 text-orange-700 border-orange-200',bar: 'bg-orange-300' },
    expedition:        { dot: 'bg-cyan-400',   badge: 'bg-cyan-50 text-cyan-700 border-cyan-200',      bar: 'bg-cyan-300'   },
    cloture:           { dot: 'bg-green-500',  badge: 'bg-green-50 text-green-700 border-green-200',   bar: 'bg-green-400'  },
};

export const ETAPE_ORDER = [
    'initialisation', 'edition', 'revision', 'signature_client', 'signature_notaire',
    'formalites', 'expedition', 'cloture',
];
