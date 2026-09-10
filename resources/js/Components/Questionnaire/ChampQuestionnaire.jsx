import { AlertCircle } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { DateField } from '@/Components/ui/date-field';
import { NumberField } from '@/Components/ui/number-field';
import { PhoneField } from '@/Components/ui/phone-field';
import { LieuSelect } from '@/Components/ui/lieu-select';
import { ChampVerrouille } from '@/Components/ui/champ-verrouille';
import { roleGeo, patchGeo } from '@/data/questionnaires';
import { cn } from '@/lib/utils';

/**
 * Le rendu d'un champ de questionnaire — **un seul détenteur**, pour les trois écrans qui en
 * affichent : l'assistant de création, le modal « Modifier le questionnaire » et le formulaire
 * public d'intake.
 *
 * ⚠️ **Raison d'être : la duplication coûtait un défaut par manœuvre.** Ces ~380 lignes étaient
 * recopiées trois fois, et quatre comportements de suite n'ont été implémentés que d'un côté :
 *
 *   - `checkbox_group` — absent de deux écrans sur trois ;
 *   - la cascade géo — ajoutée à l'assistant seul ;
 *   - `readonly` — le champ « Pays » restait **modifiable dans l'assistant** alors qu'il était
 *     verrouillé dans le modal et sur le formulaire public ;
 *   - la cohérence des dates — nulle part, alors que la fiche client la contrôlait.
 *
 * Chaque fois, l'étude l'a découvert à l'usage. Un contrôle ajouté ici vaut désormais dans les trois
 * écrans par construction, et `PariteRenduQuestionnaireTest` interdit qu'un écran redéclare son
 * propre rendu.
 *
 * **Ce qui reste délégué**, et pourquoi : `repeatable` et `checkbox_group` portent un câblage propre
 * à chaque écran — pool de clients et pièces en attente dans l'assistant, exclusions de
 * modification, aplatissement d'un rôle unique à l'intake. Les uniformiser de force aurait produit
 * une abstraction qui mentirait sur son contenu ; ils passent donc par `rendreRepeatable` et
 * `rendreChoixMultiple`, explicitement.
 */
export function ChampQuestionnaire({
    field,
    valeurs = {},
    onPatch,
    /** Message de blocage à afficher sous le champ (voir `blocantsEtape`). */
    motif = null,
    /** Écrans authentifiés : l'ajout d'un lieu au référentiel y est offert, jamais sur l'intake. */
    peutAjouter = false,
    /** Intake : le référentiel arrive dans les props de la page, aucun point d'entrée n'étant exposé. */
    referentielHorsLigne = null,
    /** Remonte le nombre d'options d'un champ géo, pour que le blocant dise la même chose. */
    onNombreOptions = null,
    rendreChoixMultiple = null,
    rendreRepeatable = null,
    disabled = false,
    /**
     * Préfixe des `id` DOM. Le modal d'édition s'ouvre **au-dessus** de la fiche, qui rend déjà les
     * mêmes champs : sans préfixe, deux éléments porteraient le même `id` et les `<label>` de la
     * modale pointeraient vers les contrôles cachés derrière elle.
     */
    idPrefix = '',
}) {
    const value = valeurs[field.id];
    const geo = roleGeo(field.id);
    const domId = idPrefix + field.id;

    const changer = (v) => onPatch({ [field.id]: v });

    // ── Cases à cocher : le libellé est à droite, pas au-dessus ──────────────
    if (field.type === 'checkbox' || field.type === 'checkbox_required') {
        return (
            <div className="py-0.5">
                <div className="flex items-center gap-2.5">
                    <input
                        type="checkbox"
                        id={domId}
                        checked={!!value}
                        disabled={disabled}
                        onChange={(e) => changer(e.target.checked)}
                        className="h-4 w-4 rounded border-slate-300 text-seal focus:ring-seal"
                    />
                    <label htmlFor={domId} className="cursor-pointer text-sm leading-snug text-slate-700">
                        {field.label}
                        {field.required && <span className="ml-1 text-danger">*</span>}
                    </label>
                </div>
                <Note field={field} className="ml-6 mt-1" />
                <Motif motif={motif} className="ml-6" />
            </div>
        );
    }

    return (
        <div className="space-y-1.5">
            <Label htmlFor={domId}>
                {field.label}
                {field.required && <span className="ml-1 text-danger">*</span>}
            </Label>

            <Note field={field} />

            <Controle
                field={field}
                domId={domId}
                value={value}
                valeurs={valeurs}
                geo={geo}
                onPatch={onPatch}
                changer={changer}
                peutAjouter={peutAjouter}
                referentielHorsLigne={referentielHorsLigne}
                onNombreOptions={onNombreOptions}
                rendreChoixMultiple={rendreChoixMultiple}
                rendreRepeatable={rendreRepeatable}
                disabled={disabled}
            />

            <Motif motif={motif} />
        </div>
    );
}

/** Le contrôle de saisie proprement dit, par type. */
function Controle({
    field, domId, value, valeurs, geo, onPatch, changer, peutAjouter,
    referentielHorsLigne, onNombreOptions, rendreChoixMultiple, rendreRepeatable, disabled,
}) {
    // ── Lieu du référentiel ──────────────────────────────────────────────────
    // Le rôle vient de TRIPLETS_GEO, **jamais du suffixe du nom** :
    // `bien.livre_foncier_ville` finit par « ville » sans être un lieu du référentiel.
    if (geo) {
        const parentNom = geo.parentField ? (valeurs[geo.parentField] || null) : null;

        // Hors ligne, le référentiel est un dictionnaire par niveau : on y filtre le parent, ce que
        // le point d'entrée fait côté serveur pour les écrans authentifiés.
        const options = referentielHorsLigne
            ? (referentielHorsLigne[geo.niveau] ?? []).filter(l => (geo.parentField ? l.parent === parentNom : true))
            : null;

        return (
            <LieuSelect
                id={domId}
                niveau={geo.niveau}
                parentNom={parentNom}
                value={value || ''}
                // `patchGeo` remet à zéro les niveaux inférieurs : changer de ville ne doit pas
                // laisser en place une commune qui appartient à une autre.
                onChange={(val) => onPatch(patchGeo(field.id, val))}
                peutAjouter={peutAjouter}
                lieuxInitiaux={options}
                onNombreOptions={onNombreOptions}
                disabled={disabled}
            />
        );
    }

    if (field.type === 'checkbox_group') {
        return rendreChoixMultiple ? rendreChoixMultiple(field) : null;
    }

    if (field.type === 'repeatable') {
        return rendreRepeatable ? rendreRepeatable(field) : null;
    }

    if (field.type === 'textarea') {
        return (
            <textarea
                id={domId}
                rows={3}
                placeholder={field.placeholder}
                value={value || ''}
                disabled={disabled}
                onChange={(e) => changer(e.target.value)}
                className="w-full resize-none rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-seal"
            />
        );
    }

    if (field.type === 'select') {
        return (
            <select
                id={domId}
                value={value || ''}
                disabled={disabled}
                onChange={(e) => changer(e.target.value)}
                className="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-seal"
            >
                <option value="">— Choisir —</option>
                {(field.options ?? []).map(opt => <option key={opt} value={opt}>{opt}</option>)}
            </select>
        );
    }

    if (field.type === 'date') {
        return (
            <DateField
                id={domId}
                value={value || ''}
                onValueChange={changer}
                disabled={disabled}
            />
        );
    }

    if (field.type === 'number') {
        return (
            <NumberField
                id={domId}
                decimals={field.decimals ?? 0}
                placeholder={field.placeholder}
                value={value || ''}
                onValueChange={changer}
                className={cn(field.mono && 'font-ref', field.readonly && 'bg-slate-50 text-slate-500')}
                disabled={disabled || !!field.readonly}
            />
        );
    }

    if (field.type === 'year') {
        return (
            <Input
                id={domId}
                type="text"
                inputMode="numeric"
                maxLength={4}
                placeholder={field.placeholder}
                value={value || ''}
                disabled={disabled}
                onChange={(e) => changer(e.target.value.replace(/[^0-9]/g, '').slice(0, 4))}
                className="font-ref"
            />
        );
    }

    if (field.type === 'tel') {
        return (
            <PhoneField
                id={domId}
                placeholder={field.placeholder}
                value={value || ''}
                onValueChange={changer}
                disabled={disabled}
            />
        );
    }

    // ── Constante de fait (le pays de résidence) ─────────────────────────────
    // Verrouillée pour ne pas être modifiée par inadvertance, mais **déverrouillable** : un client
    // peut résider à l'étranger. C'est le comportement qui manquait à l'assistant de création,
    // lequel rendait un champ de saisie ordinaire.
    if (field.readonly) {
        return (
            <ChampVerrouille
                id={domId}
                value={value || ''}
                onChange={changer}
                placeholder={field.placeholder}
                className={cn(field.mono && 'font-ref')}
                disabled={disabled}
            />
        );
    }

    return (
        <Input
            id={domId}
            type={field.type === 'email' ? 'email' : 'text'}
            placeholder={field.placeholder}
            value={value || ''}
            disabled={disabled}
            onChange={(e) => changer(e.target.value)}
            className={cn(field.mono && 'font-ref')}
        />
    );
}

function Note({ field, className }) {
    if (!field.note) return null;

    return (
        <p className={cn('flex items-center gap-1 text-xs text-warning-text', className)}>
            <AlertCircle className="h-3 w-3 shrink-0" />
            {field.note}
        </p>
    );
}

function Motif({ motif, className }) {
    if (!motif) return null;

    return (
        <p className={cn('flex items-center gap-1 text-xs text-danger', className)}>
            <AlertCircle className="h-3 w-3 shrink-0" />
            {motif}
        </p>
    );
}

/**
 * Classes de mise en page d'un champ — largeur, retrait d'un champ conditionnel, bordure rouge.
 *
 * Séparées du champ lui-même parce que la grille appartient à l'écran : l'assistant et le modal
 * rangent en deux colonnes, l'intake en une seule sur mobile.
 */
export function classesChamp({ field, enDefaut = false }) {
    const pleineLargeur = field.type === 'checkbox'
        || field.type === 'checkbox_required'
        || field.type === 'textarea'
        || field.type === 'repeatable'
        || field.type === 'checkbox_group';

    return cn(
        pleineLargeur && 'sm:col-span-2',
        field.showIf && 'border-l-2 border-seal/30 pl-3',
        // Cible les contrôles descendants plutôt que d'ajouter une prop à chacun des types rendus.
        enDefaut && '[&_input]:border-danger [&_textarea]:border-danger [&_select]:border-danger [&_input]:ring-1 [&_input]:ring-danger/20',
    );
}
