import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { toast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import {
    Gift, Calendar, Mail, Phone, Clock, Copy, Check, MessageCircle,
    ExternalLink, Search, X,
} from 'lucide-react';

/**
 * Numéro au format international, pour un lien WhatsApp.
 *
 * ⚠️ L'indicatif vient du serveur (`indicatifPaysDefaut`), déclaré à un seul endroit : les
 * numéros du répertoire sont saisis en national (6XX XX XX XX) et wa.me exige l'international.
 * Un numéro déjà préfixé (`+224…`, `00224…`) est respecté tel quel — le reparsing d'un numéro
 * déjà correct est la façon courante de le casser.
 */
function numeroInternational(telephone, indicatif) {
    if (!telephone) return null;

    const chiffres = String(telephone).replace(/[^\d+]/g, '');

    if (chiffres.startsWith('+')) return chiffres.slice(1);
    if (chiffres.startsWith('00')) return chiffres.slice(2);
    if (chiffres.startsWith(indicatif)) return chiffres;

    return `${indicatif}${chiffres.replace(/^0+/, '')}`;
}

/** Message proposé — relu et modifiable avant envoi, jamais envoyé par l'application. */
function messageAnniversaire(nom) {
    const prenom = String(nom ?? '').trim().split(/\s+/)[0] || '';

    return `Cher(e) ${prenom}, l'Étude vous adresse ses vœux les plus chaleureux `
        + `à l'occasion de votre anniversaire. Nous vous souhaitons une excellente journée.`;
}

/**
 * Bouton de copie avec retour visible.
 *
 * La confirmation est **dans le bouton** et non seulement en toast : l'action est répétée ligne
 * après ligne, et il faut pouvoir dire d'un coup d'œil laquelle vient d'être copiée.
 */
function BoutonCopier({ valeur, libelle, children }) {
    const [copie, setCopie] = useState(false);

    const copier = async () => {
        try {
            await navigator.clipboard.writeText(valeur);
            setCopie(true);
            toast.success(`${libelle} copié.`);
            setTimeout(() => setCopie(false), 1800);
        } catch {
            // `navigator.clipboard` exige un contexte sécurisé : en HTTP simple, il n'existe pas.
            // Le dire plutôt que de laisser croire à une copie silencieuse.
            toast.error(`Copie impossible depuis ce navigateur — ${libelle} : ${valeur}`);
        }
    };

    return (
        <button
            type="button"
            onClick={copier}
            title={`Copier ${libelle.toLowerCase()}`}
            className="group inline-flex max-w-full items-center gap-1.5 rounded-md px-1.5 py-0.5 text-sm text-slate-600 transition-colors hover:bg-slate-100 hover:text-ink"
        >
            {children}
            {copie
                ? <Check className="h-3 w-3 shrink-0 text-emerald-600" />
                : <Copy className="h-3 w-3 shrink-0 text-slate-300 group-hover:text-slate-500" />}
        </button>
    );
}

/** Une puce de filtre — même rendu pour les quatre familles, pour qu'elles se lisent ensemble. */
function Puce({ actif, onClick, children }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                actif
                    ? 'border-seal bg-seal-light text-seal-hover'
                    : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 hover:text-slate-700',
            )}
        >
            {children}
        </button>
    );
}

const MOIS = [
    'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
    'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
];

export default function Index({ clients, filtres, fenetresDisponibles, indicatifPaysDefaut }) {
    const [recherche, setRecherche] = useState(filtres.q ?? '');

    /**
     * Les filtres passent par l'URL, pas par un état local.
     *
     * C'est ce qui rend une vue filtrée **partageable et rechargeable** : « les anniversaires
     * de novembre restant à souhaiter » est une adresse qu'on envoie à un collègue. Le serveur
     * reste seul à décider du cadrage — dupliquer la règle côté écran ferait diverger les deux.
     */
    const appliquer = (changements) => {
        const params = {
            fenetre: filtres.fenetre,
            mois: filtres.mois ?? undefined,
            statut: filtres.statut ?? undefined,
            q: filtres.q || undefined,
            ...changements,
        };

        Object.keys(params).forEach(k => {
            if (params[k] === undefined || params[k] === null || params[k] === '') delete params[k];
        });

        // URL en dur : Ziggy est installé côté PHP mais **pas** côté JS (absent de
        // package.json), et aucune page du dépôt n'appelle `route()`. Inventer ce helper ici
        // aurait cassé au premier rendu.
        router.get('/anniversaires', params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const basculerSouhait = (client, canal = null) => {
        router.post(
            `/anniversaires/${client.id}/souhait`,
            { annee: client.annee_occurrence, canal },
            { preserveScroll: true, preserveState: true },
        );
    };

    const filtreActif = filtres.mois !== null || filtres.statut !== null || (filtres.q ?? '') !== '';

    return (
        <AppLayout breadcrumbs={[{ label: 'Anniversaires' }]}>
            <Head title="Anniversaires" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 font-serif text-heading text-ink">
                            <Gift className="h-5 w-5 text-pink-500" />
                            Anniversaires
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {filtres.mois !== null
                                ? `Les clients nés en ${MOIS[filtres.mois - 1]}.`
                                : `Les clients dont l'anniversaire tombe dans les ${filtres.fenetre} prochains jours.`}
                        </p>
                    </div>
                    <Badge variant="outline" className="shrink-0">
                        {clients.length} client{clients.length > 1 ? 's' : ''}
                    </Badge>
                </div>

                {/* ── Filtres ──────────────────────────────────────────────────────── */}
                <div className="mb-4 space-y-3 rounded-lg border border-slate-200 bg-white p-4">
                    <div className="flex flex-wrap items-center gap-x-6 gap-y-3">
                        <div className="flex items-center gap-1.5">
                            <span className="mr-1 text-xs font-medium uppercase tracking-wider text-slate-400">Fenêtre</span>
                            {fenetresDisponibles.map(j => (
                                <Puce
                                    key={j}
                                    actif={filtres.mois === null && filtres.fenetre === j}
                                    onClick={() => appliquer({ fenetre: j, mois: undefined })}
                                >
                                    {j} jours
                                </Puce>
                            ))}
                        </div>

                        <div className="flex items-center gap-1.5">
                            <span className="mr-1 text-xs font-medium uppercase tracking-wider text-slate-400">Statut</span>
                            <Puce actif={filtres.statut === null} onClick={() => appliquer({ statut: undefined })}>
                                Tous
                            </Puce>
                            <Puce actif={filtres.statut === 'restants'} onClick={() => appliquer({ statut: 'restants' })}>
                                À souhaiter
                            </Puce>
                            <Puce actif={filtres.statut === 'souhaites'} onClick={() => appliquer({ statut: 'souhaites' })}>
                                Déjà souhaités
                            </Puce>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-x-6 gap-y-3">
                        <div className="flex flex-wrap items-center gap-1.5">
                            <span className="mr-1 text-xs font-medium uppercase tracking-wider text-slate-400">Mois</span>
                            {MOIS.map((nom, i) => (
                                <Puce
                                    key={nom}
                                    actif={filtres.mois === i + 1}
                                    onClick={() => appliquer({ mois: filtres.mois === i + 1 ? undefined : i + 1 })}
                                >
                                    {nom.slice(0, 3)}
                                </Puce>
                            ))}
                        </div>

                        <form
                            className="relative ml-auto"
                            onSubmit={(e) => { e.preventDefault(); appliquer({ q: recherche }); }}
                        >
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                            <Input
                                value={recherche}
                                onChange={e => setRecherche(e.target.value)}
                                placeholder="Rechercher un nom…"
                                className="h-8 w-56 pl-8 text-sm"
                            />
                        </form>

                        {filtreActif && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 gap-1 text-xs text-slate-400"
                                onClick={() => { setRecherche(''); appliquer({ mois: undefined, statut: undefined, q: undefined }); }}
                            >
                                <X className="h-3 w-3" /> Tout effacer
                            </Button>
                        )}
                    </div>
                </div>

                {/* ── Liste ────────────────────────────────────────────────────────── */}
                {clients.length === 0 ? (
                    <div className="rounded-lg border border-slate-200 bg-white p-12 text-center">
                        <Gift className="mx-auto mb-4 h-12 w-12 text-slate-200" />
                        <h3 className="font-medium text-ink">Aucun anniversaire</h3>
                        <p className="mt-1 text-sm text-slate-500">
                            {filtreActif
                                ? 'Aucun client ne correspond à ces filtres.'
                                : `Aucun client n'a son anniversaire dans les ${filtres.fenetre} prochains jours.`}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-2">
                        {clients.map(client => {
                            const jours = client.jours_restants;
                            const cEstAujourdhui = jours === 0;
                            const estPasse = jours < 0;
                            const tel = numeroInternational(client.telephone, indicatifPaysDefaut);
                            const message = messageAnniversaire(client.nom);
                            const souhaite = client.souhait !== null;

                            return (
                                <div
                                    key={client.id}
                                    className={cn(
                                        'rounded-lg border bg-white p-4 transition-colors',
                                        souhaite ? 'border-slate-200 opacity-70' : 'border-slate-200',
                                        cEstAujourdhui && !souhaite && 'border-pink-300 bg-pink-50/40',
                                    )}
                                >
                                    <div className="flex flex-wrap items-start gap-4">
                                        <Avatar className="h-10 w-10 shrink-0">
                                            <AvatarFallback className="bg-slate-100 text-xs text-slate-600">
                                                {String(client.nom ?? '?').slice(0, 2).toUpperCase()}
                                            </AvatarFallback>
                                        </Avatar>

                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-medium text-ink">{client.nom}</span>
                                                {cEstAujourdhui && (
                                                    <Badge className="border-pink-200 bg-pink-100 text-pink-700">
                                                        C'est aujourd'hui
                                                    </Badge>
                                                )}
                                                {souhaite && (
                                                    <Badge variant="outline" className="gap-1 border-emerald-200 text-emerald-700">
                                                        <Check className="h-3 w-3" /> Souhaité
                                                    </Badge>
                                                )}
                                            </div>

                                            <div className="mt-0.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                                <span className="inline-flex items-center gap-1">
                                                    <Calendar className="h-3 w-3" />
                                                    {new Date(client.prochain_anniversaire + 'T00:00:00')
                                                        .toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })}
                                                </span>
                                                <span className="inline-flex items-center gap-1">
                                                    <Clock className="h-3 w-3" />
                                                    {cEstAujourdhui
                                                        ? "aujourd'hui"
                                                        : estPasse
                                                            ? `il y a ${Math.abs(jours)} jour${Math.abs(jours) > 1 ? 's' : ''}`
                                                            : `dans ${jours} jour${jours > 1 ? 's' : ''}`}
                                                </span>
                                                <span>aura {client.age_a_venir} ans</span>
                                            </div>

                                            {/* Contacts : cliquer copie. Le plus demandé, et sans dépendance. */}
                                            <div className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                                                {client.email ? (
                                                    <BoutonCopier valeur={client.email} libelle="Email">
                                                        <Mail className="h-3 w-3 shrink-0 text-slate-400" />
                                                        <span className="truncate">{client.email}</span>
                                                    </BoutonCopier>
                                                ) : (
                                                    <span className="text-xs italic text-slate-300">Aucun email</span>
                                                )}
                                                {client.telephone ? (
                                                    <BoutonCopier valeur={client.telephone} libelle="Téléphone">
                                                        <Phone className="h-3 w-3 shrink-0 text-slate-400" />
                                                        <span>{client.telephone}</span>
                                                    </BoutonCopier>
                                                ) : (
                                                    <span className="text-xs italic text-slate-300">Aucun téléphone</span>
                                                )}
                                            </div>

                                            {souhaite && (
                                                <p className="mt-2 text-xs text-emerald-700">
                                                    Souhaité le {new Date(client.souhait.souhaite_at + 'T00:00:00').toLocaleDateString('fr-FR')}
                                                    {client.souhait.canal ? ` ${client.souhait.canal}` : ''}
                                                    {client.souhait.auteur ? ` par ${client.souhait.auteur}` : ''}.
                                                </p>
                                            )}
                                        </div>

                                        {/* ── Actions ──────────────────────────────────────── */}
                                        <div className="flex shrink-0 flex-wrap items-center gap-1.5">
                                            {client.email && (
                                                <Button variant="outline" size="sm" className="h-8 gap-1.5" asChild>
                                                    <a
                                                        href={`mailto:${client.email}?subject=${encodeURIComponent('Joyeux anniversaire')}&body=${encodeURIComponent(message)}`}
                                                        title="Ouvrir le message pré-rempli dans votre messagerie"
                                                    >
                                                        <Mail className="h-3.5 w-3.5" /> Email
                                                    </a>
                                                </Button>
                                            )}
                                            {tel && (
                                                <Button variant="outline" size="sm" className="h-8 gap-1.5" asChild>
                                                    <a
                                                        href={`https://wa.me/${tel}?text=${encodeURIComponent(message)}`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        title="Ouvrir la conversation WhatsApp avec un message pré-rédigé"
                                                    >
                                                        <MessageCircle className="h-3.5 w-3.5" /> WhatsApp
                                                    </a>
                                                </Button>
                                            )}
                                            <Button variant="ghost" size="sm" className="h-8 gap-1.5 text-slate-500" asChild>
                                                <a href={`/repertoire?client_id=${client.id}`} title="Voir les dossiers de ce client">
                                                    <ExternalLink className="h-3.5 w-3.5" /> Fiche
                                                </a>
                                            </Button>

                                            {/* La marque est **déclarative** : l'application n'envoie rien, elle
                                                retient ce que l'étude dit avoir fait. Le canal est celui du bouton
                                                utilisé juste avant, d'où le choix au clic plutôt qu'un formulaire. */}
                                            {souhaite ? (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-8 gap-1.5 text-slate-400"
                                                    onClick={() => basculerSouhait(client)}
                                                    title="Retirer la marque"
                                                >
                                                    <X className="h-3.5 w-3.5" /> Annuler
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant="seal"
                                                    size="sm"
                                                    className="h-8 gap-1.5"
                                                    onClick={() => basculerSouhait(client)}
                                                >
                                                    <Check className="h-3.5 w-3.5" /> Marquer souhaité
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
