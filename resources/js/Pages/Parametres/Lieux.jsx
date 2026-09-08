import { useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { AlertTriangle, Check, ChevronDown, ChevronRight, MapPin, Pencil, Plus, Trash2, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { cn } from '@/lib/utils';

/**
 * Référentiel des lieux — ville → commune → quartier.
 *
 * Écran calqué sur *Paramètres > Barèmes* : accordéon par ville, communes dépliables, quartiers en
 * dessous.
 *
 * Sa raison d'être : les quartiers amorcés au seeder portent `a_verifier`, parce que leur liste
 * n'est ni garantie exhaustive ni garantie à jour. Le filtre « à vérifier » est donc la **liste de
 * travail** de l'étude — corriger un nom ou le valider le fait sortir du filtre.
 *
 * Pas de suppression : un nom peut figurer dans des dossiers et des actes déjà produits, et le
 * référentiel ne porte aucune clé étrangère vers eux. Désactiver retire le lieu des listes
 * proposées sans réécrire le passé.
 */
export default function ParametresLieux() {
    const { villes = [], aVerifier = 0 } = usePage().props;

    const [depliees, setDepliees] = useState({});
    const [filtreAVerifier, setFiltreAVerifier] = useState(false);
    const [recherche, setRecherche] = useState('');
    const [enEdition, setEnEdition] = useState(null);
    const [nomEdite, setNomEdite] = useState('');

    const basculer = (id) => setDepliees(d => ({ ...d, [id]: !d[id] }));

    const correspond = (nom) => nom.toLowerCase().includes(recherche.trim().toLowerCase());

    /**
     * Filtrage descendant : une ville reste visible si elle-même, une de ses communes ou un de ses
     * quartiers correspond. Sans cela, chercher « Nongo » ne montrerait rien — le quartier étant
     * deux niveaux plus bas.
     */
    const villesFiltrees = useMemo(() => villes
        .map(ville => {
            const communes = ville.communes
                .map(commune => {
                    const quartiers = commune.quartiers.filter(q =>
                        (!filtreAVerifier || q.a_verifier) && (!recherche || correspond(q.nom)));

                    const communeRetenue = (!filtreAVerifier || commune.a_verifier)
                        && (!recherche || correspond(commune.nom));

                    return (communeRetenue || quartiers.length > 0)
                        ? { ...commune, quartiers: communeRetenue && !recherche ? commune.quartiers.filter(q => !filtreAVerifier || q.a_verifier) : quartiers }
                        : null;
                })
                .filter(Boolean);

            const villeRetenue = (!filtreAVerifier || ville.a_verifier) && (!recherche || correspond(ville.nom));

            return (villeRetenue || communes.length > 0)
                ? { ...ville, communes: villeRetenue && !recherche && !filtreAVerifier ? ville.communes : communes }
                : null;
        })
        .filter(Boolean), [villes, filtreAVerifier, recherche]);

    const enregistrer = (lieu, patch) => {
        router.patch(`/parametres/lieux/${lieu.id}`, patch, { preserveScroll: true, preserveState: true });
        setEnEdition(null);
    };

    // Quel formulaire d'ajout est ouvert : `'ville'` pour le niveau racine, `c<id>` pour une
    // commune sous une ville, `q<id>` pour un quartier sous une commune.
    const [ajoutOuvert, setAjoutOuvert] = useState(null);
    const [erreurAjout, setErreurAjout] = useState(null);

    /**
     * Ajoute un lieu au référentiel.
     *
     * Passe par le **même point d'entrée** que la cascade des formulaires (`POST /lieux`) : la
     * validation des niveaux et le rattachement au parent n'ont ainsi qu'une implémentation. Le
     * serveur décide seul du drapeau « à vérifier » — un ajout par l'administrateur depuis cet
     * écran est déjà la vérification.
     */
    const ajouter = async (niveau, nom, parentNom) => {
        const propre = nom.trim();
        if (!propre) return;

        setErreurAjout(null);
        try {
            await axios.post('/lieux', { niveau, nom: propre, parent: parentNom ?? null });
            setAjoutOuvert(null);
            // Recharge la seule prop utile : l'arborescence complète vient du serveur, et la
            // recalculer côté client dupliquerait le tri et le regroupement.
            router.reload({ only: ['villes', 'aVerifier'], preserveScroll: true });
        } catch (err) {
            setErreurAjout(err.response?.data?.message ?? "Ce lieu n'a pas pu être ajouté.");
        }
    };

    const [aSupprimer, setASupprimer] = useState(null);

    const supprimer = () => {
        router.delete(`/parametres/lieux/${aSupprimer.id}`, {
            preserveScroll: true,
            onFinish: () => setASupprimer(null),
        });
    };

    const total = villes.reduce((n, v) => n + 1 + v.communes.reduce((m, c) => m + 1 + c.quartiers.length, 0), 0);

    return (
        <AppLayout>
            <Head title="Lieux — Paramètres" />

            <div className="mx-auto max-w-4xl space-y-4 p-6">
                <div>
                    <h1 className="font-serif text-display text-ink">Référentiel des lieux</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Ville → commune → quartier. Ces listes alimentent la saisie des adresses dans
                        les fiches clients, les fiches sociétés et les questionnaires.
                    </p>
                </div>

                {aVerifier > 0 && (
                    <Card className="border-amber-200 bg-warning-bg">
                        <CardContent className="flex items-start gap-3 p-4">
                            <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-warning-text" />
                            <div className="min-w-0 flex-1 text-sm text-warning-text">
                                <strong>{aVerifier} lieu{aVerifier > 1 ? 'x' : ''} à vérifier.</strong>{' '}
                                Ces entrées ont été amorcées automatiquement : leur liste n'est ni garantie
                                exhaustive ni garantie à jour. Corrigez un nom ou validez-le pour le retirer
                                de cette liste.
                            </div>
                            <Button
                                size="sm"
                                variant={filtreAVerifier ? 'seal' : 'outline'}
                                onClick={() => setFiltreAVerifier(v => !v)}
                                className="shrink-0"
                            >
                                {filtreAVerifier ? 'Tout afficher' : 'Ne voir que ceux-là'}
                            </Button>
                        </CardContent>
                    </Card>
                )}

                <div className="flex items-center gap-3">
                    <Input
                        value={recherche}
                        onChange={(e) => setRecherche(e.target.value)}
                        placeholder="Rechercher une ville, une commune, un quartier…"
                        className="max-w-sm"
                    />
                    <span className="text-xs text-slate-400">{total} lieux au référentiel</span>
                </div>

                <FormeAjout
                    ouvert={ajoutOuvert === 'ville'}
                    onOuvrir={() => { setAjoutOuvert('ville'); setErreurAjout(null); }}
                    onFermer={() => setAjoutOuvert(null)}
                    onValider={(nom) => ajouter('ville', nom)}
                    libelle="Ajouter une ville ou une préfecture"
                    placeholder="Ex : Dubréka"
                    erreur={erreurAjout}
                />

                <div className="space-y-2">
                    {villesFiltrees.length === 0 && (
                        <p className="py-8 text-center text-sm text-slate-400">Aucun lieu ne correspond.</p>
                    )}

                    {villesFiltrees.map(ville => (
                        <Card key={ville.id} className={cn(!ville.actif && 'opacity-60')}>
                            <CardContent className="p-0">
                                <div className="flex items-center gap-2 px-4 py-3">
                                    <button
                                        type="button"
                                        onClick={() => basculer(ville.id)}
                                        className="text-slate-400 hover:text-ink"
                                    >
                                        {depliees[ville.id]
                                            ? <ChevronDown className="h-4 w-4" />
                                            : <ChevronRight className="h-4 w-4" />}
                                    </button>

                                    <LigneLieu
                                        lieu={ville}
                                        niveau="ville"
                                        enEdition={enEdition}
                                        setEnEdition={setEnEdition}
                                        nomEdite={nomEdite}
                                        setNomEdite={setNomEdite}
                                        onEnregistrer={enregistrer}
                                        onSupprimer={setASupprimer}
                                    />

                                    <span className="shrink-0 text-xs text-slate-400">
                                        {ville.communes.length} commune{ville.communes.length > 1 ? 's' : ''}
                                    </span>
                                </div>

                                {depliees[ville.id] && (
                                    <div className="divide-y divide-slate-50 border-t border-slate-100">
                                        <div className="pl-10 pr-4 py-1">
                                            <FormeAjout
                                                ouvert={ajoutOuvert === `c${ville.id}`}
                                                onOuvrir={() => { setAjoutOuvert(`c${ville.id}`); setErreurAjout(null); }}
                                                onFermer={() => setAjoutOuvert(null)}
                                                onValider={(nom) => ajouter('commune', nom, ville.nom)}
                                                libelle={`Ajouter une commune à ${ville.nom}`}
                                                placeholder="Ex : Kaloum"
                                                erreur={erreurAjout}
                                                discret
                                            />
                                        </div>
                                        {ville.communes.map(commune => (
                                            <div key={commune.id} className={cn('pl-10', !commune.actif && 'opacity-60')}>
                                                <div className="flex items-center gap-2 py-2 pr-4">
                                                    <button
                                                        type="button"
                                                        onClick={() => basculer(`c${commune.id}`)}
                                                        className="text-slate-400 hover:text-ink"
                                                    >
                                                        {depliees[`c${commune.id}`]
                                                            ? <ChevronDown className="h-3.5 w-3.5" />
                                                            : <ChevronRight className="h-3.5 w-3.5" />}
                                                    </button>

                                                    <LigneLieu
                                                        lieu={commune}
                                                        niveau="commune"
                                                        enEdition={enEdition}
                                                        setEnEdition={setEnEdition}
                                                        nomEdite={nomEdite}
                                                        setNomEdite={setNomEdite}
                                                        onEnregistrer={enregistrer}
                                                        onSupprimer={setASupprimer}
                                                    />

                                                    <span className="shrink-0 text-xs text-slate-400">
                                                        {commune.quartiers.length} quartier{commune.quartiers.length > 1 ? 's' : ''}
                                                    </span>
                                                </div>

                                                {depliees[`c${commune.id}`] && (
                                                    <div className="pb-2 pl-8">
                                                        <FormeAjout
                                                            ouvert={ajoutOuvert === `q${commune.id}`}
                                                            onOuvrir={() => { setAjoutOuvert(`q${commune.id}`); setErreurAjout(null); }}
                                                            onFermer={() => setAjoutOuvert(null)}
                                                            onValider={(nom) => ajouter('quartier', nom, commune.nom)}
                                                            libelle={`Ajouter un quartier à ${commune.nom}`}
                                                            placeholder="Ex : Nongo"
                                                            erreur={erreurAjout}
                                                            discret
                                                        />
                                                        {commune.quartiers.map(q => (
                                                            <div key={q.id} className={cn('flex items-center gap-2 py-1.5 pr-4', !q.actif && 'opacity-60')}>
                                                                <LigneLieu
                                                                    lieu={q}
                                                                    niveau="quartier"
                                                                    enEdition={enEdition}
                                                                    setEnEdition={setEnEdition}
                                                                    nomEdite={nomEdite}
                                                                    setNomEdite={setNomEdite}
                                                                    onEnregistrer={enregistrer}
                                                                    onSupprimer={setASupprimer}
                                                                />
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>

            {/* Confirmation : la suppression est définitive, contrairement à la désactivation. */}
            <ConfirmDialog
                open={!!aSupprimer}
                onClose={() => setASupprimer(null)}
                onConfirm={supprimer}
                title={`Retirer « ${aSupprimer?.nom ?? ''} » du référentiel ?`}
                description="Ce lieu n'est employé dans aucune fiche ni aucun questionnaire. Il disparaîtra des listes de saisie. Pour un lieu déjà employé, préférez la désactivation."
                confirmLabel="Retirer"
                variant="destructive"
            />
        </AppLayout>
    );
}

/** Une ligne du référentiel : nom, badge « à vérifier », renommage, activation. */
function LigneLieu({ lieu, niveau, enEdition, setEnEdition, nomEdite, setNomEdite, onEnregistrer, onSupprimer }) {
    const editeIci = enEdition === lieu.id;

    if (editeIci) {
        return (
            <div className="flex min-w-0 flex-1 items-center gap-1.5">
                <Input
                    autoFocus
                    value={nomEdite}
                    onChange={(e) => setNomEdite(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') onEnregistrer(lieu, { nom: nomEdite, a_verifier: false });
                        if (e.key === 'Escape') setEnEdition(null);
                    }}
                    className="h-8 max-w-xs text-sm"
                />
                <button
                    type="button"
                    onClick={() => onEnregistrer(lieu, { nom: nomEdite, a_verifier: false })}
                    className="rounded-md border border-seal/40 bg-seal-light p-1.5 text-seal-hover hover:border-seal"
                    title="Enregistrer et marquer comme vérifié"
                >
                    <Check className="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    onClick={() => setEnEdition(null)}
                    className="p-1.5 text-slate-400 hover:text-danger"
                >
                    <X className="h-3.5 w-3.5" />
                </button>
            </div>
        );
    }

    return (
        <div className="flex min-w-0 flex-1 items-center gap-2">
            {niveau === 'quartier' && <MapPin className="h-3 w-3 shrink-0 text-slate-300" />}

            <span className={cn(
                'truncate',
                niveau === 'ville' ? 'font-medium text-ink' : 'text-sm text-slate-700',
            )}>
                {lieu.nom}
            </span>

            {lieu.a_verifier && (
                <Badge variant="outline" className="shrink-0 border-amber-200 bg-warning-bg text-warning-text">
                    à vérifier
                </Badge>
            )}

            <button
                type="button"
                onClick={() => { setEnEdition(lieu.id); setNomEdite(lieu.nom); }}
                className="shrink-0 text-slate-300 transition-colors hover:text-seal"
                title="Renommer"
            >
                <Pencil className="h-3 w-3" />
            </button>

            <Switch
                checked={lieu.actif}
                onCheckedChange={(actif) => onEnregistrer(lieu, { actif })}
                className="shrink-0"
                title={lieu.actif ? 'Retirer des listes proposées' : 'Remettre dans les listes'}
            />

            {/* La corbeille n'apparaît que si le lieu n'est référencé nulle part — sinon son nom
                figure dans une fiche, et possiblement dans un acte produit : la désactivation est
                alors la bonne réponse, et l'infobulle le dit. */}
            {lieu.supprimable ? (
                <button
                    type="button"
                    onClick={() => onSupprimer?.(lieu)}
                    className="shrink-0 text-slate-300 transition-colors hover:text-danger"
                    title="Retirer du référentiel"
                >
                    <Trash2 className="h-3 w-3" />
                </button>
            ) : (
                <span
                    className="shrink-0 cursor-help text-slate-200"
                    title="Employé dans une fiche ou contenant d'autres lieux — désactivez-le plutôt que de le supprimer."
                >
                    <Trash2 className="h-3 w-3" />
                </span>
            )}
        </div>
    );
}

/**
 * Formulaire d'ajout d'un lieu, replié en un simple lien tant qu'on ne s'en sert pas.
 *
 * Replié par défaut : cet écran sert d'abord à **valider** les 54 quartiers amorcés, l'ajout est le
 * geste secondaire. Trois boutons dépliés en permanence auraient noyé la liste.
 *
 * `discret` réduit l'échelle pour les niveaux imbriqués (commune, quartier), où le lien cohabite
 * avec les lignes existantes.
 */
function FormeAjout({ ouvert, onOuvrir, onFermer, onValider, libelle, placeholder, erreur, discret = false }) {
    const [nom, setNom] = useState('');

    const valider = () => {
        onValider(nom);
        setNom('');
    };

    if (!ouvert) {
        return (
            <button
                type="button"
                onClick={onOuvrir}
                className={cn(
                    'flex items-center gap-1.5 text-slate-400 transition-colors hover:text-seal',
                    discret ? 'py-1 text-xs' : 'px-1 py-2 text-sm',
                )}
            >
                <Plus className={discret ? 'h-3 w-3' : 'h-3.5 w-3.5'} />
                {libelle}
            </button>
        );
    }

    return (
        <div className={cn('space-y-1', discret ? 'py-1' : 'py-2')}>
            <div className="flex items-center gap-1.5">
                <Input
                    autoFocus
                    value={nom}
                    onChange={(e) => setNom(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') { e.preventDefault(); valider(); }
                        if (e.key === 'Escape') onFermer();
                    }}
                    placeholder={placeholder}
                    className={cn('max-w-xs', discret ? 'h-8 text-sm' : 'h-9')}
                />
                <button
                    type="button"
                    onClick={valider}
                    className="rounded-md border border-seal/40 bg-seal-light p-1.5 text-seal-hover hover:border-seal"
                    title="Ajouter"
                >
                    <Check className="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    onClick={onFermer}
                    className="p-1.5 text-slate-400 hover:text-danger"
                    title="Annuler"
                >
                    <X className="h-3.5 w-3.5" />
                </button>
            </div>
            {erreur && <p className="text-xs text-danger-text">{erreur}</p>}
        </div>
    );
}
