import { Building2, PlusCircle, Search } from 'lucide-react';
import { SocietePicker } from '@/Components/ui/societe-picker';
import { cn } from '@/lib/utils';

/**
 * Choix de la société concernée : **du registre** ou **hors registre**.
 *
 * Remplace la modale « Nouvelle société au registre ». Le choix était implicite — il fallait
 * chercher, ne rien trouver, puis penser à ouvrir une modale — alors que le clerc sait dès le départ
 * s'il traite une société que l'étude a constituée ou celle d'un confrère. Le poser en premier rend
 * le parcours lisible et évite une fenêtre qui masque le reste de la saisie.
 *
 * Les deux chemins n'aboutissent pas au même endroit :
 *   - **du registre** → une fiche est rattachée (`societe_id`), ses champs sont projetés et masqués,
 *     et son dossier constitutif est réutilisable ;
 *   - **hors registre** → les champs `soc.*` sont saisis dans le dossier, et une fiche est créée au
 *     registre à l'enregistrement, pour que le prochain dossier la retrouve.
 */
export function ChoixSociete({ mode, onModeChange, societeLink, onSelect, onUnlink, onImporterPersonnes, children }) {
    // Une fiche rattachée impose son mode : proposer « hors registre » alors qu'une société est liée
    // n'aurait aucun sens, et détacher est déjà offert par le picker.
    const modeEffectif = societeLink ? 'registre' : mode;

    return (
        <div className="space-y-3">
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                <OptionMode
                    actif={modeEffectif === 'registre'}
                    onClick={() => onModeChange('registre')}
                    icone={<Search className="h-4 w-4" />}
                    titre="Société de notre registre"
                    detail="L'étude la connaît déjà — ses informations et son dossier constitutif sont repris."
                />
                <OptionMode
                    actif={modeEffectif === 'hors_registre'}
                    onClick={() => onModeChange('hors_registre')}
                    desactive={!!societeLink}
                    icone={<PlusCircle className="h-4 w-4" />}
                    titre="Société hors registre"
                    detail="Constituée par un autre office — saisissez ses informations, elles entrent au registre."
                />
            </div>

            {modeEffectif === 'registre' ? (
                <>
                    <SocietePicker
                        linked={societeLink}
                        onSelect={onSelect}
                        onUnlink={onUnlink}
                        onImporterPersonnes={onImporterPersonnes}
                    />
                    {!societeLink && (
                        <p className="flex items-start gap-1.5 text-xs text-slate-400">
                            <Building2 className="mt-0.5 h-3 w-3 shrink-0" />
                            Introuvable ? Choisissez « Société hors registre » : vous saisirez ses informations
                            ci-dessous, et elle sera ajoutée au registre à l'enregistrement du dossier.
                        </p>
                    )}
                </>
            ) : (
                <p className="flex items-start gap-1.5 text-xs text-slate-400">
                    <Building2 className="mt-0.5 h-3 w-3 shrink-0" />
                    Renseignez les informations de la société ci-dessous. Une fiche sera créée au registre
                    à l'enregistrement du dossier — le prochain dossier la retrouvera sans ressaisie.
                </p>
            )}

            {children}
        </div>
    );
}

function OptionMode({ actif, onClick, desactive, icone, titre, detail }) {
    return (
        <button
            type="button"
            onClick={desactive ? undefined : onClick}
            disabled={desactive}
            aria-pressed={actif}
            className={cn(
                'flex items-start gap-2.5 rounded-lg border p-3 text-left transition-colors',
                actif
                    ? 'border-seal bg-seal-light'
                    : 'border-slate-200 bg-white hover:border-slate-300',
                desactive && 'cursor-not-allowed opacity-50 hover:border-slate-200',
            )}
        >
            <span
                className={cn(
                    'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border',
                    actif ? 'border-seal bg-seal' : 'border-slate-300 bg-white',
                )}
            >
                {actif && <span className="h-1.5 w-1.5 rounded-full bg-white" />}
            </span>
            <span className="min-w-0">
                <span className={cn('flex items-center gap-1.5 text-sm font-medium', actif ? 'text-ink' : 'text-slate-700')}>
                    {icone}
                    {titre}
                </span>
                <span className="mt-0.5 block text-xs text-slate-500">{detail}</span>
            </span>
        </button>
    );
}
