// Cycle de vie d'une fiche société — miroir assumé de `App\Enums\StatutSociete`.
//
// ⚠️ Duplication **gardée** : `StatutsSocieteTest` compare les deux listes valeur par valeur
// et libellé par libellé. Un statut ajouté d'un seul côté échoue à l'intégration, là où le
// `match` exhaustif côté PHP n'a aucun équivalent ici (même situation que `ETAPE_ORDER`,
// décision #38).
//
// La liste est écrite **en clair**, jamais dérivée : le test l'extrait ligne par ligne par
// expression régulière, comme `ListesMatrimonialesTest` le fait pour les régimes.

export const STATUTS_SOCIETE = [
    { valeur: 'active',               label: 'Active',               couleur: 'emerald' },
    { valeur: 'en_liquidation',       label: 'En liquidation',       couleur: 'amber'   },
    { valeur: 'liquidation_cloturee', label: 'Liquidation clôturée', couleur: 'orange'  },
    { valeur: 'radiee',               label: 'Radiée',               couleur: 'slate'   },
];

// Classes Tailwind par couleur de statut. Séparé de la liste ci-dessus pour que le test de
// parité ne porte que sur ce que PHP connaît — le serveur nomme une couleur, il n'a pas à
// connaître les classes utilitaires.
const CLASSES = {
    emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    amber:   'bg-amber-50 text-amber-700 border-amber-200',
    orange:  'bg-orange-50 text-orange-700 border-orange-200',
    slate:   'bg-slate-100 text-slate-700 border-slate-200',
};

export function statutSociete(valeur) {
    return STATUTS_SOCIETE.find((s) => s.valeur === valeur) ?? null;
}

export function classesStatutSociete(valeur) {
    return CLASSES[statutSociete(valeur)?.couleur] ?? CLASSES.slate;
}

export function libelleStatutSociete(valeur) {
    return statutSociete(valeur)?.label ?? valeur ?? '';
}
