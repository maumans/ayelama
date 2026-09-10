import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { AlertCircle, Check, Loader2, MapPin, Plus } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

/**
 * Sélecteur d'un lieu du référentiel, dépendant de son parent.
 *
 * Un composant **par niveau** plutôt qu'un bloc de trois : le triplet est déclaré comme trois champs
 * distincts (`pp.demeurant_ville`, `pp.commune`, `pp.quartier`) à dix endroits de l'application, et
 * les regrouper aurait imposé de réécrire les questionnaires — donc de migrer `donnees` et de
 * toucher aux balises `${…}` des modèles Word. Chaque champ garde son identifiant ; seule sa liste
 * d'options devient dépendante.
 *
 * ⚠️ **La valeur reste le nom**, jamais un identifiant : c'est ce que les actes reprennent.
 *
 * `parentNom` vide sur un niveau qui en attend un → le champ est neutralisé plutôt que rempli
 * d'options hors sujet. Proposer les quartiers de tout le pays avant de connaître la commune
 * recréerait l'incohérence que la cascade supprime.
 */
export function LieuSelect({
    niveau,
    parentNom = null,
    value = '',
    onChange,
    peutAjouter = false,
    lieuxInitiaux = null,
    disabled = false,
    placeholder,
    id,
    /**
     * Remonte le nombre d'options réellement disponibles, pour que **le blocant du formulaire dise
     * la même chose que le champ**.
     *
     * Sans cela, `blocantsEtape` répondait « Choisissez une valeur » devant une liste vide : le
     * message était juste dans sa formulation et faux dans son sens. Seul ce composant connaît le
     * résultat de la requête, d'où la remontée.
     */
    onNombreOptions = null,
}) {
    const attendUnParent = niveau !== 'ville';
    const parentManquant = attendUnParent && !parentNom;

    // `lieuxInitiaux` court-circuite l'appel réseau : c'est ainsi que le formulaire public d'intake
    // fonctionne, en recevant le référentiel dans ses props plutôt que par un point d'entrée
    // exposé sans authentification.
    const horsLigne = Array.isArray(lieuxInitiaux);
    const nbInitiaux = horsLigne ? lieuxInitiaux.length : 0;

    const [lieux, setLieux] = useState(horsLigne ? lieuxInitiaux : []);
    const [chargement, setChargement] = useState(false);
    const [ajoutOuvert, setAjoutOuvert] = useState(false);
    const [nouveauNom, setNouveauNom] = useState('');
    const [erreurAjout, setErreurAjout] = useState(null);
    const inputAjout = useRef(null);

    // Gardée dans une ref : la passer en dépendance de l'effet le relancerait à chaque rendu du
    // parent, et donc rechargerait la liste en boucle.
    const rapporter = useRef(onNombreOptions);
    rapporter.current = onNombreOptions;

    useEffect(() => {
        if (horsLigne || parentManquant) {
            if (parentManquant) setLieux([]);
            // `null` et non `0` : « parent pas encore choisi » n'est pas « référentiel vide », et
            // le blocant doit pouvoir les distinguer.
            rapporter.current?.(parentManquant ? null : nbInitiaux);
            return;
        }

        const controleur = new AbortController();
        setChargement(true);

        axios
            .get('/lieux', { params: { niveau, parent: parentNom }, signal: controleur.signal })
            .then(({ data }) => {
                const recus = data.lieux ?? [];
                setLieux(recus);
                rapporter.current?.(recus.length);
            })
            .catch((err) => { if (!axios.isCancel(err)) { setLieux([]); rapporter.current?.(0); } })
            .finally(() => setChargement(false));

        return () => controleur.abort();
    // `nbInitiaux` plutôt que `lieuxInitiaux` : un tableau est une référence neuve à chaque rendu du
    // parent, et l'effet se relancerait sans fin.
    }, [niveau, parentNom, horsLigne, parentManquant, nbInitiaux]);

    useEffect(() => {
        if (ajoutOuvert) inputAjout.current?.focus();
    }, [ajoutOuvert]);

    const ajouter = async () => {
        const nom = nouveauNom.trim();
        if (!nom) return;

        setErreurAjout(null);
        try {
            const { data } = await axios.post('/lieux', { niveau, nom, parent: parentNom });
            setLieux(l => [...l, data].sort((a, b) => a.nom.localeCompare(b.nom, 'fr')));
            onChange(data.nom);
            setAjoutOuvert(false);
            setNouveauNom('');
        } catch (err) {
            setErreurAjout(
                err.response?.status === 403
                    ? "Votre rôle ne permet pas d'ajouter un lieu au référentiel."
                    : err.response?.data?.message ?? "Ce lieu n'a pas pu être ajouté.",
            );
        }
    };

    // Valeur héritée d'une saisie antérieure au référentiel : elle doit rester visible et
    // sélectionnée, sinon ouvrir une fiche ancienne l'effacerait en silence.
    const horsReferentiel = value && !lieux.some(l => l.nom === value);

    /**
     * Le parent est choisi, mais le référentiel ne contient **rien** sous lui.
     *
     * Cas majoritaire et non anecdotique : 33 des 39 communes n'ont aucun quartier — seule Conakry
     * est peuplée. Le champ affichait alors « — Choisir — » devant une liste vide, et le blocant
     * répondait « Choisissez une valeur » : l'étude se retrouvait devant un champ obligatoire
     * impossible à satisfaire, sans savoir que la solution (ajouter le lieu) était juste en dessous
     * en petits caractères gris.
     */
    const referentielVide = !parentManquant && !chargement && lieux.length === 0 && !horsReferentiel;

    const libelleNiveau = niveau === 'ville' ? 'ville' : niveau === 'commune' ? 'commune' : 'quartier';

    return (
        <div className="space-y-1">
            <select
                id={id}
                value={value}
                disabled={disabled || parentManquant}
                onChange={(e) => onChange(e.target.value)}
                className={cn(
                    'flex h-10 w-full rounded-md border border-slate-200 bg-white px-3 py-2 text-sm',
                    'focus:border-seal focus:outline-none focus:ring-1 focus:ring-seal',
                    'disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400',
                )}
            >
                <option value="">
                    {parentManquant
                        ? `— choisissez d'abord ${niveau === 'commune' ? 'la ville' : 'la commune'} —`
                        : referentielVide
                            ? `— aucun ${libelleNiveau} au référentiel${parentNom ? ` pour ${parentNom}` : ''} —`
                            : (placeholder ?? '— Choisir —')}
                </option>

                {horsReferentiel && (
                    <option value={value}>{value} — hors référentiel</option>
                )}

                {lieux.map(l => (
                    <option key={l.nom} value={l.nom}>
                        {l.nom}{l.a_verifier ? ' (à vérifier)' : ''}
                    </option>
                ))}
            </select>

            {chargement && (
                <p className="flex items-center gap-1 text-xs text-slate-400">
                    <Loader2 className="h-3 w-3 animate-spin" /> Chargement…
                </p>
            )}

            {horsReferentiel && (
                <p className="flex items-start gap-1 text-xs text-warning-text">
                    <AlertCircle className="mt-0.5 h-3 w-3 shrink-0" />
                    Cette valeur ne figure pas au référentiel — conservée telle quelle, mais à reprendre.
                </p>
            )}

            {/* Référentiel vide : on le dit, et on nomme le recours. Sans ce message, le champ
                obligatoire paraissait cassé — la liste étant vide et le lien d'ajout discret. */}
            {referentielVide && (
                <p className="flex items-start gap-1 text-xs text-warning-text">
                    <AlertCircle className="mt-0.5 h-3 w-3 shrink-0" />
                    {peutAjouter
                        ? `Aucun ${libelleNiveau} n'est encore enregistré${parentNom ? ` pour ${parentNom}` : ''} — ajoutez-le ci-dessous, il servira aux dossiers suivants.`
                        : `Aucun ${libelleNiveau} n'est proposé${parentNom ? ` pour ${parentNom}` : ''} — laissez vide et signalez-le à l'étude.`}
                </p>
            )}

            {/* L'ajout n'est offert qu'aux écrans authentifiés : sur le formulaire public, un tiers
                ne doit pas pouvoir peupler le référentiel de l'étude.

                Mis en **évidence** quand la liste est vide : c'est précisément le moment où il est
                le seul recours, et c'est là qu'il passait inaperçu en petits caractères gris. */}
            {peutAjouter && !parentManquant && !ajoutOuvert && (
                <button
                    type="button"
                    onClick={() => setAjoutOuvert(true)}
                    className={cn(
                        'flex items-center gap-1 text-xs transition-colors',
                        referentielVide
                            ? 'rounded-md border border-seal/40 bg-seal-light px-2 py-1 font-medium text-seal-hover hover:border-seal'
                            : 'text-slate-400 hover:text-seal',
                    )}
                >
                    <Plus className="h-3 w-3" />
                    Ajouter {niveau === 'ville' ? 'une ville' : niveau === 'commune' ? 'une commune' : 'un quartier'}
                    {parentNom ? ` à ${parentNom}` : ''}
                </button>
            )}

            {peutAjouter && ajoutOuvert && (
                <div className="space-y-1">
                    <div className="flex items-center gap-1.5">
                        <MapPin className="h-3.5 w-3.5 shrink-0 text-slate-400" />
                        <Input
                            ref={inputAjout}
                            value={nouveauNom}
                            onChange={(e) => setNouveauNom(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') { e.preventDefault(); ajouter(); }
                                if (e.key === 'Escape') setAjoutOuvert(false);
                            }}
                            placeholder="Nom du lieu"
                            className="h-8 text-sm"
                        />
                        <button
                            type="button"
                            onClick={ajouter}
                            className="rounded-md border border-seal/40 bg-seal-light p-1.5 text-seal-hover hover:border-seal"
                            title="Ajouter au référentiel"
                        >
                            <Check className="h-3.5 w-3.5" />
                        </button>
                    </div>
                    {erreurAjout && <p className="text-xs text-danger-text">{erreurAjout}</p>}
                    <p className="text-xs text-slate-400">
                        Il sera marqué « à vérifier » et servira aux dossiers suivants.
                    </p>
                </div>
            )}
        </div>
    );
}
