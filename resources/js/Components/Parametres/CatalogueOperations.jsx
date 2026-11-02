import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { AlertTriangle, Building2, Check, RotateCcw, ShieldCheck, X } from 'lucide-react';
import { Switch } from '@/components/ui/switch';
import { Button } from '@/components/ui/button';

/**
 * Catalogue des opérations sur les sociétés — addendum v1.2 § 5, test T13.
 *
 * Même parti pris que les documents attendus : **l'enum est la référence**, la table en porte
 * une copie que l'étude règle sans redéploiement, et l'écran montre les deux. Un réglage qu'on
 * ne peut pas comparer à sa valeur d'origine se corrige une fois, puis se perd — c'est pour
 * cela que chaque ligne qui s'écarte le dit, et porte son bouton de restauration.
 *
 * Deux marqueurs à ne pas confondre :
 *
 *  - **⚠ Règle déduite** — la spécification marque cette ligne « à confirmer » : personne à
 *    l'étude ne l'a validée, elle a été posée par symétrie avec ses voisines. L'étude peut la
 *    corriger sans contredire une décision prise.
 *  - **Écart à la référence** — l'étude *a* corrigé la règle, et la base ne dit plus ce que le
 *    code dit. C'est l'état normal d'un réglage assumé, pas une anomalie.
 *
 * Une ligne sans référence au code (`aUneReference` faux) ne se restaure pas : il n'y a pas de
 * valeur d'origine à retrouver. Le bouton n'est alors pas proposé, plutôt que proposé et inerte.
 */
export function CatalogueOperations({ operations = [], assemblees = [] }) {
    const [enCours, setEnCours] = useState(null);

    const groupes = useMemo(() => {
        const map = new Map();
        for (const o of operations) {
            if (!map.has(o.type_acte_code)) map.set(o.type_acte_code, []);
            map.get(o.type_acte_code).push(o);
        }
        return [...map.entries()];
    }, [operations]);

    const divergentes = operations.filter(o => o.diverge).length;
    const deduites    = operations.filter(o => !o.regle_validee).length;

    const regler = (operation, champs) => {
        setEnCours(operation.id);
        router.patch(
            `/parametres/operations-societe/${operation.id}`,
            {
                assemblee:       operation.assemblee,
                impacte_statuts: operation.impacte_statuts,
                impacte_rccm:    operation.impacte_rccm,
                publication_jal: operation.publication_jal,
                exclusive:       operation.exclusive,
                regle_validee:   operation.regle_validee,
                est_actif:       operation.est_actif,
                ...champs,
            },
            { preserveScroll: true, onFinish: () => setEnCours(null) },
        );
    };

    const restaurer = (operation) => {
        setEnCours(operation.id);
        router.post(
            `/parametres/operations-societe/${operation.id}/reinitialiser`,
            {},
            { preserveScroll: true, onFinish: () => setEnCours(null) },
        );
    };

    if (operations.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500">
                Le catalogue est vide. Lancez&nbsp;
                <code className="font-mono text-xs text-seal">php artisan db:seed --class=ReglesGestionOperationsSeeder</code>
                &nbsp;pour y déposer la référence.
            </div>
        );
    }

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                <span className="inline-flex items-center gap-1.5">
                    <Building2 className="h-3.5 w-3.5 text-seal" />
                    {operations.length} opération{operations.length > 1 ? 's' : ''} au catalogue
                </span>
                {deduites > 0 && (
                    <span className="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700">
                        <AlertTriangle className="h-3 w-3" />
                        {deduites} règle{deduites > 1 ? 's' : ''} déduite{deduites > 1 ? 's' : ''}, à confirmer
                    </span>
                )}
                {divergentes > 0 && (
                    <span className="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-blue-700">
                        <RotateCcw className="h-3 w-3" />
                        {divergentes} réglage{divergentes > 1 ? 's' : ''} de l'étude
                    </span>
                )}
            </div>

            <AnimatePresence mode="popLayout">
                {groupes.map(([code, lignes]) => (
                    <motion.div
                        key={code}
                        initial={{ opacity: 0, y: 6 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -4 }}
                        transition={{ duration: 0.2 }}
                    >
                        <div className="mb-2 flex items-center gap-2 px-1">
                            <div className="h-3 w-1 rounded-full bg-emerald-500" />
                            <span className="font-mono text-xs font-semibold uppercase tracking-wider text-slate-600">
                                {code}
                            </span>
                            <span className="text-[10px] text-slate-400">
                                {lignes.filter(l => l.est_actif).length}/{lignes.length} proposées à la saisie
                            </span>
                        </div>

                        <div className="mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                            <table className="table-notarial w-full">
                                <thead>
                                    <tr>
                                        <th>Opération</th>
                                        <th className="text-center">Assemblée</th>
                                        <th className="text-center">Statuts</th>
                                        <th className="text-center">RCCM</th>
                                        <th className="text-center">JAL</th>
                                        <th className="text-center">Exclusive</th>
                                        <th className="text-center">Proposée</th>
                                        <th className="text-right">Référence</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {lignes.map(o => (
                                        <tr key={o.id} className={o.est_actif ? '' : 'opacity-50'}>
                                            <td>
                                                <div className="flex flex-col">
                                                    <span className="text-sm font-medium">{o.libelle}</span>
                                                    <span className="font-mono text-[10px] text-slate-400">{o.code}</span>
                                                    {!o.regle_validee && (
                                                        <span className="mt-0.5 inline-flex items-center gap-1 text-[10px] text-amber-700">
                                                            <AlertTriangle className="h-3 w-3 shrink-0" />
                                                            {o.description_statut ?? 'Règle déduite, à confirmer'}
                                                        </span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="text-center">
                                                <select
                                                    value={o.assemblee}
                                                    disabled={enCours === o.id}
                                                    onChange={e => regler(o, { assemblee: e.target.value })}
                                                    className="rounded-md border border-slate-200 bg-white px-1.5 py-0.5 text-xs"
                                                >
                                                    {assemblees.map(a => (
                                                        <option key={a.valeur} value={a.valeur}>{a.valeur}</option>
                                                    ))}
                                                </select>
                                            </td>
                                            {['impacte_statuts', 'impacte_rccm', 'publication_jal', 'exclusive'].map(champ => (
                                                <td key={champ} className="text-center">
                                                    <button
                                                        type="button"
                                                        disabled={enCours === o.id}
                                                        onClick={() => regler(o, { [champ]: !o[champ] })}
                                                        title={o[champ] ? 'Oui — cliquer pour passer à non' : 'Non — cliquer pour passer à oui'}
                                                        className="inline-flex h-5 w-5 items-center justify-center rounded-md border border-slate-200 hover:border-seal"
                                                    >
                                                        {o[champ]
                                                            ? <Check className="h-3 w-3 text-emerald-600" />
                                                            : <X className="h-3 w-3 text-slate-300" />}
                                                    </button>
                                                </td>
                                            ))}
                                            <td className="text-center">
                                                <Switch
                                                    checked={o.est_actif}
                                                    disabled={enCours === o.id || !o.aUneReference}
                                                    onCheckedChange={() => regler(o, { est_actif: !o.est_actif })}
                                                />
                                            </td>
                                            <td className="text-right">
                                                {!o.aUneReference ? (
                                                    <span
                                                        className="text-[10px] italic text-slate-400"
                                                        title="Aucun cas d'enum ne porte ce code : le dépôt ne sait pas exécuter cette opération."
                                                    >
                                                        hors référence
                                                    </span>
                                                ) : o.diverge ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        disabled={enCours === o.id}
                                                        onClick={() => restaurer(o)}
                                                        className="h-6 gap-1 px-2 text-[10px] text-blue-700"
                                                        title={Object.entries(o.differences)
                                                            .map(([c, d]) => `${c} : ${String(d.actuel)} au lieu de ${String(d.reference)}`)
                                                            .join('\n')}
                                                    >
                                                        <RotateCcw className="h-3 w-3" />
                                                        Restaurer
                                                    </Button>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 text-[10px] text-slate-400">
                                                        <ShieldCheck className="h-3 w-3" />
                                                        conforme
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </motion.div>
                ))}
            </AnimatePresence>

            <p className="px-1 text-[11px] leading-relaxed text-slate-400">
                Ces réglages décident de l'assemblée proposée, des actes produits et des formalités
                engagées. Ils s'appliquent aux dossiers ouverts <strong>après</strong> la
                modification : un dossier déjà constitué garde les actes qu'il a produits.
            </p>
        </div>
    );
}
