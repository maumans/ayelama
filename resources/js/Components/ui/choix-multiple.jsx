import { AlertCircle } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { motifBlocage, conflitsDansSelection } from '@/lib/exclusionsChoix';
import { cn } from '@/lib/utils';

/**
 * Champ à choix multiple (`type: 'checkbox_group'` des questionnaires).
 *
 * Stocke un **tableau de libellés**, convention de tous les `select` du projet : `donnees`
 * reste lisible tel quel dans les modèles Word, et côté PHP
 * `TypeModificationStatutaire::depuisLibelles()` reconnaît indifféremment le libellé affiché
 * ou la valeur technique.
 *
 * L'ordre suit celui des options, pas celui des clics : la liste des actes à produire, qui en
 * dérive, doit être stable d'un dossier à l'autre.
 *
 * Partagé par l'assistant de création (`Dossiers/Create.jsx`) et la modale d'édition du
 * questionnaire (`Dossiers/Show.jsx`) — un type de champ géré d'un seul côté rendrait la
 * valeur non modifiable après la création du dossier.
 *
 * `exclusions` — `{ [libellé]: { [libellé exclu]: 'motif' } }` — rend certaines options
 * mutuellement exclusives. Le composant reste **générique** : il ne connaît aucune notion de
 * société, et la logique d'exclusion elle-même vit dans `@/lib/exclusionsChoix` (fonctions pures,
 * vérifiables sans rendu).
 */
export function ChoixMultiple({ field, valeurs, onChange, idPrefix = '', exclusions = {} }) {
    const selection = Array.isArray(valeurs) ? valeurs : [];

    const basculer = (option) => {
        const suivant = selection.includes(option)
            ? selection.filter(v => v !== option)
            : [...selection, option];
        onChange((field.options ?? []).filter(o => suivant.includes(o)));
    };

    const conflits = conflitsDansSelection(selection, exclusions);

    return (
        <div className="space-y-2">
            <div className="grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                {(field.options ?? []).map(option => {
                    const actif = selection.includes(option);
                    const motif = motifBlocage(option, selection, exclusions);
                    const bloque = motif !== null;

                    return (
                        <label
                            key={option}
                            htmlFor={bloque ? undefined : `${idPrefix}${field.id}-${option}`}
                            title={motif ?? undefined}
                            className={cn(
                                'flex items-center gap-2.5 rounded-lg border px-3 py-2 text-sm transition-colors',
                                bloque
                                    ? 'cursor-not-allowed border-slate-100 bg-slate-50 text-slate-300'
                                    : actif
                                        ? 'cursor-pointer border-seal bg-seal-light text-slate-800'
                                        : 'cursor-pointer border-slate-200 bg-white text-slate-600 hover:border-slate-300'
                            )}
                        >
                            <Checkbox
                                id={`${idPrefix}${field.id}-${option}`}
                                checked={actif}
                                disabled={bloque}
                                onCheckedChange={() => basculer(option)}
                            />
                            <span className="leading-snug">{option}</span>
                        </label>
                    );
                })}
            </div>

            {conflits.map(({ paire, motif }) => (
                <p key={paire.join('|')} className="flex items-start gap-1.5 text-xs text-danger-text">
                    <AlertCircle className="mt-0.5 h-3 w-3 shrink-0" />
                    <span>
                        <strong>« {paire[0]} » et « {paire[1]} » ne peuvent pas être décidées ensemble.</strong>{' '}
                        {motif} Décochez l'une des deux.
                    </span>
                </p>
            ))}
        </div>
    );
}
