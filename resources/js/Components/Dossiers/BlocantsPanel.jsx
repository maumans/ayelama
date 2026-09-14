import { AnimatePresence, motion } from 'framer-motion';
import { AlertCircle, ArrowRight, CheckCircle2, ChevronDown, UserPen } from 'lucide-react';
import { allerAuBlocant, blocantsParSection } from '@/lib/blocantsEtape';
import { cn } from '@/lib/utils';

/**
 * Ce qui manque pour passer à l'étape suivante de l'assistant, énuméré et cliquable.
 *
 * Remplace un bouton « Suivant » grisé sans explication : le compteur reste visible en permanence
 * dans la barre de navigation, et se déroule sur clic — ou automatiquement quand on tente d'avancer
 * alors qu'il manque quelque chose.
 *
 * Chaque ligne emmène là où l'on peut agir : sur un questionnaire de modification à dix sections,
 * savoir *qu'il* manque quelque chose ne suffit pas.
 *
 * ⚠️ **Deux défauts corrigés le 2026-09-10**, signalés à l'usage :
 *
 * 1. Le panneau **restait ouvert** et recouvrait le champ vers lequel il venait de faire défiler :
 *    il fallait le refermer à la main pour voir où l'on avait atterri. Il se replie désormais, et le
 *    défilement attend la fin du repli — sans quoi il viserait une position que le retrait du
 *    panneau décale.
 * 2. Un champ porté par une **fiche client** ne se remplit pas dans la page : il vit dans la modale
 *    de la fiche. Défiler jusqu'à la carte de la section n'y menait pas ; la ligne propose
 *    maintenant « Ouvrir la fiche » et ouvre la bonne.
 *
 * Le halo posé par `allerAuBlocant` complète le geste : il dit *où* l'on vient d'arriver.
 */
export function CompteurBlocants({ blocants, ouvert, onBasculer }) {
    const nb = blocants.length;

    if (nb === 0) {
        return (
            <span className="flex items-center gap-1.5 text-xs font-medium text-success-text">
                <CheckCircle2 className="h-3.5 w-3.5" />
                Prêt à continuer
            </span>
        );
    }

    return (
        <button
            type="button"
            onClick={onBasculer}
            aria-expanded={ouvert}
            className="flex items-center gap-1.5 rounded-md border border-amber-200 bg-warning-bg px-2.5 py-1.5 text-xs font-medium text-warning-text transition-colors hover:border-amber-300"
        >
            <AlertCircle className="h-3.5 w-3.5 shrink-0" />
            {nb} à compléter
            <ChevronDown className={cn('h-3.5 w-3.5 transition-transform', ouvert && 'rotate-180')} />
        </button>
    );
}

/** Durée du repli du panneau, en millisecondes — doit suivre la transition ci-dessous. */
const DUREE_REPLI = 240;

export function BlocantsPanel({ blocants, ouvert, onFermer, onOuvrirFiche = null }) {
    const groupes = blocantsParSection(blocants);

    /**
     * Va au champ **après** avoir replié le panneau.
     *
     * ⚠️ Le défaut corrigé : le panneau restait ouvert et **recouvrait le champ** vers lequel il
     * venait de faire défiler. L'étude devait le refermer à la main pour voir où elle avait atterri
     * — le geste « Aller » ne menait donc nulle part de visible.
     *
     * Le délai attend la fin du repli : défiler avant viserait une position que le retrait du
     * panneau va décaler de toute sa hauteur.
     */
    const allerEtFermer = (ancre) => {
        onFermer();
        window.setTimeout(() => allerAuBlocant(ancre), DUREE_REPLI);
    };

    /**
     * Un champ porté par une fiche client ne se remplit pas dans la page : il vit **dans la modale
     * de la fiche**. Défiler jusqu'à la carte de la section n'y menait pas — on ouvre donc la bonne
     * fiche directement.
     */
    const ouvrirFiche = (roleClient) => {
        onFermer();
        window.setTimeout(() => onOuvrirFiche(roleClient), DUREE_REPLI);
    };

    return (
        <AnimatePresence initial={false}>
            {ouvert && blocants.length > 0 && (
                <motion.div
                    initial={{ opacity: 0, height: 0 }}
                    animate={{ opacity: 1, height: 'auto' }}
                    exit={{ opacity: 0, height: 0 }}
                    transition={{ duration: 0.2 }}
                    className="overflow-hidden"
                >
                    <div className="mb-3 rounded-lg border border-amber-200 bg-warning-bg p-3">
                        <div className="mb-2 flex items-start justify-between gap-3">
                            <p className="flex items-center gap-1.5 text-xs font-semibold text-warning-text">
                                <AlertCircle className="h-3.5 w-3.5 shrink-0" />
                                {blocants.length} élément{blocants.length > 1 ? 's' : ''} à compléter avant de continuer
                            </p>
                            <button
                                type="button"
                                onClick={onFermer}
                                className="shrink-0 text-[11px] text-warning-text/70 hover:underline"
                            >
                                Masquer
                            </button>
                        </div>

                        <div className="space-y-2">
                            {groupes.map(groupe => (
                                <div key={groupe.section}>
                                    <p className="text-[11px] font-semibold uppercase tracking-wider text-warning-text/70">
                                        {groupe.section}
                                    </p>
                                    <ul className="mt-0.5 divide-y divide-amber-200/50">
                                        {groupe.lignes.map(ligne => (
                                            <li key={ligne.cle}>
                                                {(() => {
                                                    // Trois cas, trois actions : ouvrir la fiche du
                                                    // client qui porte le champ, aller au champ dans
                                                    // la page, ou rien quand il n'y a nulle part où
                                                    // aller (choix d'une catégorie, par exemple).
                                                    const versFiche = !!ligne.roleClient && !!onOuvrirFiche;
                                                    const cliquable = versFiche || !!ligne.ancre;

                                                    return (
                                                        <button
                                                            type="button"
                                                            onClick={versFiche
                                                                ? () => ouvrirFiche(ligne.roleClient)
                                                                : () => allerEtFermer(ligne.ancre)}
                                                            disabled={!cliquable}
                                                            className={cn(
                                                                'group flex w-full items-baseline gap-2 py-1 text-left text-xs',
                                                                cliquable ? 'cursor-pointer' : 'cursor-default',
                                                            )}
                                                        >
                                                            <span className="font-medium text-slate-700">{ligne.label}</span>
                                                            <span className="text-slate-500">— {ligne.raison}</span>
                                                            {cliquable && (
                                                                <span className="ml-auto flex shrink-0 items-center gap-1 whitespace-nowrap text-seal-hover opacity-60 transition-opacity group-hover:opacity-100">
                                                                    {versFiche ? (
                                                                        <>Ouvrir la fiche <UserPen className="h-3 w-3" /></>
                                                                    ) : (
                                                                        <>Aller <ArrowRight className="h-3 w-3" /></>
                                                                    )}
                                                                </span>
                                                            )}
                                                        </button>
                                                    );
                                                })()}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}

/**
 * Badge d'en-tête de section : le nombre de manques, ou une coche quand tout est complet.
 *
 * Sur les dix sections d'un questionnaire de modification, c'est ce qui évite de tout dérouler pour
 * chercher où ça coince. `complete` n'est vrai que si la section porte au moins un champ requis —
 * une coche verte sur une section entièrement facultative ne voudrait rien dire.
 */
export function BadgeSection({ nb = 0, complete = false }) {
    if (nb > 0) {
        return (
            <span className="ml-auto flex shrink-0 items-center gap-1 rounded-full border border-amber-200 bg-warning-bg px-2 py-0.5 text-[11px] font-medium text-warning-text">
                <AlertCircle className="h-3 w-3" />
                {nb} à compléter
            </span>
        );
    }

    if (!complete) return null;

    return (
        <span className="ml-auto shrink-0" title="Section complète">
            <CheckCircle2 className="h-4 w-4 text-success" />
        </span>
    );
}
