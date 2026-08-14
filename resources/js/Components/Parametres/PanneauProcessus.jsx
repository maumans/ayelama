import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { AlertCircle, CheckCircle2, ChevronRight, Link2, RotateCcw } from 'lucide-react';

/**
 * Carte du processus d'un type d'acte : ce qu'il attend, et ce qui peut le produire.
 *
 * Ce que l'étude n'avait nulle part. Un gabarit manquant se découvrait en générant un dossier, et le
 * message d'erreur ne disait pas lequel — d'où le « Aucun modèle actif » incompréhensible affiché sur
 * un dossier qui portait déjà deux actes.
 *
 * Un type d'acte **sans variante** (vente, bail, hypothèque) affiche simplement ses gabarits actifs :
 * on ne lui invente pas de liste imposée, faute de règle écrite qui la fonde.
 */
export function PanneauProcessus({ typeActe }) {
    const [ouvert, setOuvert] = useState(false);
    const p = typeActe.processus;

    if (!p) return null;

    const reinitialiser = (variante) => {
        router.post(
            `/parametres/types-actes/${typeActe.id}/documents-attendus/reinitialiser`,
            { variante },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <div className="border-t border-slate-100 bg-slate-50/60">
            <button
                onClick={() => setOuvert(o => !o)}
                className="flex w-full items-center gap-2 px-4 py-2 text-left text-xs text-slate-600 hover:bg-slate-100/60"
            >
                <ChevronRight className={`h-3.5 w-3.5 transition-transform ${ouvert ? 'rotate-90' : ''}`} />
                <span className="font-medium">Processus et gabarits</span>
                {p.seDecline && (
                    <span className="text-slate-400">
                        {p.variantes.length} variante{p.variantes.length > 1 ? 's' : ''}
                    </span>
                )}
                {p.incomplets > 0 ? (
                    <span className="ml-auto flex items-center gap-1 rounded-full border border-amber-200 bg-warning-bg px-2 py-0.5 text-[11px] text-warning-text">
                        <AlertCircle className="h-3 w-3" />
                        {p.incomplets} gabarit{p.incomplets > 1 ? 's' : ''} manquant{p.incomplets > 1 ? 's' : ''}
                    </span>
                ) : (
                    <span className="ml-auto flex items-center gap-1 rounded-full border border-success/30 bg-success-bg px-2 py-0.5 text-[11px] text-success-text">
                        <CheckCircle2 className="h-3 w-3" /> Complet
                    </span>
                )}
            </button>

            <AnimatePresence initial={false}>
                {ouvert && (
                    <motion.div
                        initial={{ opacity: 0, height: 0 }}
                        animate={{ opacity: 1, height: 'auto' }}
                        exit={{ opacity: 0, height: 0 }}
                        transition={{ duration: 0.18 }}
                        className="overflow-hidden"
                    >
                        <div className="space-y-3 px-4 pb-4 pt-1">
                            {!p.seDecline
                                ? <SansVariante gabarits={p.gabarits} />
                                : p.variantes.map(v => (
                                    <Variante
                                        key={v.valeur}
                                        variante={v}
                                        typeActeId={typeActe.id}
                                        onReinitialiser={() => reinitialiser(v.valeur)}
                                    />
                                ))}
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}

function SansVariante({ gabarits }) {
    return (
        <div className="text-xs text-slate-500">
            Ce type d'acte n'a pas de variante : ses actes sont ceux de ses gabarits actifs.
            {gabarits.length === 0 ? ' Aucun gabarit actif pour le moment.' : (
                <ul className="mt-1.5 space-y-1">
                    {gabarits.map(g => (
                        <li key={g.id} className="flex items-center gap-1.5 text-slate-600">
                            <CheckCircle2 className="h-3 w-3 shrink-0 text-success" />
                            {g.nom}
                            <span className="text-slate-400">— {g.roles.join(', ')}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Variante({ variante, typeActeId, onReinitialiser }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-3">
            <div className="mb-2 flex items-center gap-2">
                <span className="text-xs font-semibold text-slate-700">{variante.label}</span>
                {variante.complet
                    ? <CheckCircle2 className="h-3.5 w-3.5 text-success" />
                    : <AlertCircle className="h-3.5 w-3.5 text-warning-text" />}

                {/* Une configuration qui s'écarte de la règle écrite doit se voir : elle décide du
                    contenu d'actes authentiques. Et elle doit être restaurable d'un clic. */}
                {variante.diverge && (
                    <>
                        <span className="rounded-full border border-amber-200 bg-warning-bg px-1.5 py-0.5 text-[10px] text-warning-text">
                            modifié par rapport à la référence légale
                        </span>
                        <button
                            onClick={onReinitialiser}
                            className="ml-auto flex items-center gap-1 text-[11px] text-slate-400 hover:text-seal"
                            title="Restaurer la liste du CR de juillet 2026"
                        >
                            <RotateCcw className="h-3 w-3" /> Réinitialiser
                        </button>
                    </>
                )}
            </div>

            <ul className="space-y-1">
                {variante.documents.map(doc => (
                    <li key={doc.slug} className="flex items-start gap-1.5 text-xs">
                        {doc.source
                            ? <CheckCircle2 className="mt-0.5 h-3 w-3 shrink-0 text-success" />
                            : <AlertCircle className="mt-0.5 h-3 w-3 shrink-0 text-warning-text" />}
                        <span className={doc.source ? 'text-slate-600' : 'text-warning-text'}>
                            {doc.label}
                            {doc.source ? (
                                <span className="text-slate-400"> — {doc.source}</span>
                            ) : (
                                <>
                                    {' — aucun gabarit '}
                                    {/* Le lien porte le type d'acte, la variante et le rôle attendu :
                                        l'admin n'a plus à deviner le slug, ce qui était le blocage réel. */}
                                    <a
                                        href={`/modeles?type_acte=${typeActeId}&variante=${variante.valeur}&role=${doc.slug}`}
                                        className="inline-flex items-center gap-0.5 underline hover:no-underline"
                                    >
                                        <Link2 className="h-3 w-3" /> rattacher
                                    </a>
                                </>
                            )}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
