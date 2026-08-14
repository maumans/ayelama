// Métadonnées de couleur par étape de dossier, partagées entre Dossiers/Index.jsx
// et Dossiers/Show.jsx — un seul endroit pour éviter le drift de couleur entre
// la liste et la fiche dossier (même esprit que data/revisionStatuts.js).

export const ETAPE_META = {
    initialisation:    { dot: 'bg-slate-400',  badge: 'bg-slate-100 text-slate-700 border-slate-200',  bar: 'bg-slate-300'  },
    edition:           { dot: 'bg-blue-400',   badge: 'bg-blue-50 text-blue-700 border-blue-200',      bar: 'bg-blue-300'   },
    revision:          { dot: 'bg-amber-400',  badge: 'bg-amber-50 text-amber-700 border-amber-200',   bar: 'bg-amber-300'  },
    signature:         { dot: 'bg-purple-400', badge: 'bg-purple-50 text-purple-700 border-purple-200',bar: 'bg-purple-300' },
    formalites:        { dot: 'bg-orange-400', badge: 'bg-orange-50 text-orange-700 border-orange-200',bar: 'bg-orange-300' },
    expedition:        { dot: 'bg-cyan-400',   badge: 'bg-cyan-50 text-cyan-700 border-cyan-200',      bar: 'bg-cyan-300'   },
    cloture:           { dot: 'bg-green-500',  badge: 'bg-green-50 text-green-700 border-green-200',   bar: 'bg-green-400'  },
};

// ⚠️ Miroir de EtapeDossier::ordered() côté PHP. Contrairement aux `match` exhaustifs
// du serveur, un oubli ici échoue en SILENCE : le stepper affiche une étape en moins et
// `indexOf()` renvoie -1. Voir décision #38.
export const ETAPE_ORDER = [
    'initialisation', 'edition', 'revision', 'signature',
    'formalites', 'expedition', 'cloture',
];
