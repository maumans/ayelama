import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import AppLayout from '@/Layouts/AppLayout';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { toast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter,
} from '@/components/ui/dialog';
import { classesStatutSociete } from '@/data/statutsSociete';
import {
    AlertTriangle, Building2, CalendarClock, FileText, Gavel, Search, X,
    Copy, Check, Mail, Phone, MapPin, ExternalLink,
} from 'lucide-react';

/**
 * Registre des sociétés — la page où l'étude suit ses liquidations.
 *
 * Volontairement une liste, pas un module : ni page de détail, ni édition complète, ni
 * historique. La correction d'une fiche se fait déjà depuis l'assistant de dossier.
 *
 * Elle existe surtout pour rendre les alertes de liquidation **actionnables** : une alerte
 * sans bouton est une alerte qu'on apprend à ignorer.
 */

/* ─── Échéance ───────────────────────────────────────────────────────────── */

/**
 * ⚠️ La mention « délai à vérifier » n'est pas décorative.
 *
 * Aucun des délais de liquidation ne vient d'une source validée — le compte rendu de juillet
 * 2026 ne mentionne ni dissolution, ni liquidation, ni radiation. Afficher une échéance sans
 * dire qu'elle est hypothétique la ferait prendre pour une règle, et l'étude agirait sur une
 * contrainte qui n'existe peut-être pas. Voir `App\Enums\JalonLiquidation`.
 */
function Echeance({ echeance }) {
    if (!echeance) {
        return <span className="text-sm text-slate-400">—</span>;
    }

    const retard = echeance.enRetard;

    return (
        <div className="flex flex-col gap-0.5">
            <span className={`inline-flex items-center gap-1.5 text-sm font-medium ${retard ? 'text-red-700' : 'text-slate-700'}`}>
                {retard ? <AlertTriangle className="h-3.5 w-3.5" /> : <CalendarClock className="h-3.5 w-3.5" />}
                {echeance.label}
            </span>
            <span className={`text-xs ${retard ? 'text-red-600' : 'text-slate-500'}`}>
                {retard
                    ? `dépassée depuis le ${echeance.echeance}`
                    : `au ${echeance.echeance} (${echeance.joursRestants} j)`}
            </span>
            {echeance.aVerifier && (
                <span className="text-xs italic text-amber-700" title={echeance.source}>
                    délai à vérifier
                </span>
            )}
        </div>
    );
}

/* ─── Radiation ──────────────────────────────────────────────────────────── */

/**
 * Seule transition du cycle de vie qui ne soit pas automatique.
 *
 * Les trois autres sont posées à l'entrée en Expédition du dossier correspondant. La
 * radiation, elle, n'est prouvée que par la pièce du greffe — qu'aucune étape de dossier ne
 * constate, le dossier de clôture étant terminé bien avant la réponse du greffe.
 *
 * La date est envoyée en **ISO** : `radiation_at` est une colonne castée. Poster du
 * JJ/MM/AAAA vers une colonne castée est le défaut que ce projet a déjà commis cinq fois —
 * le contrat est en tête de `resources/js/lib/dates.js`.
 */
function DialogRadiation({ societe, onClose }) {
    const { data, setData, patch, processing, errors } = useForm({
        radiation_at: new Date().toISOString().slice(0, 10),
    });

    const soumettre = (e) => {
        e.preventDefault();
        patch(`/societes/${societe.id}/radiation`, { onSuccess: onClose, preserveScroll: true });
    };

    return (
        <Dialog open onOpenChange={onClose}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Constater la radiation</DialogTitle>
                </DialogHeader>

                <form onSubmit={soumettre} className="space-y-4">
                    <p className="text-sm text-slate-600">
                        La société <strong>{societe.denomination}</strong> sera portée au registre comme
                        radiée du RCCM. La personne morale cesse alors d'exister.
                    </p>

                    <div className="space-y-1.5">
                        <label htmlFor="radiation_at" className="text-sm font-medium text-slate-700">
                            Date de radiation au RCCM
                        </label>
                        <Input
                            id="radiation_at"
                            type="date"
                            value={data.radiation_at}
                            max={new Date().toISOString().slice(0, 10)}
                            onChange={(e) => setData('radiation_at', e.target.value)}
                        />
                        <p className="text-xs text-slate-500">
                            Celle qui figure sur la pièce délivrée par le greffe.
                        </p>
                        {errors.radiation_at && (
                            <p className="text-xs text-red-600">{errors.radiation_at}</p>
                        )}
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annuler</Button>
                        <Button type="submit" disabled={processing}>Enregistrer la radiation</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/* ─── Copie ──────────────────────────────────────────────────────────────── */

/**
 * Une valeur du registre, copiable d'un clic.
 *
 * Le RCCM et le NIF se recopient sans cesse — dans une formalité, un courrier, un formulaire du
 * greffe — et se relisent mal : `GN.TCC.2021.B09995` se retape de travers. La confirmation est
 * **dans le bouton** et non seulement en toast : l'action se répète ligne après ligne, il faut
 * voir laquelle vient d'être copiée.
 */
function ValeurCopiable({ valeur, libelle, icone: Icone, mono = false }) {
    const [copie, setCopie] = useState(false);

    if (!valeur) return null;

    const copier = async () => {
        try {
            await navigator.clipboard.writeText(valeur);
            setCopie(true);
            toast.success(`${libelle} copié.`);
            setTimeout(() => setCopie(false), 1800);
        } catch {
            // `navigator.clipboard` exige un contexte sécurisé : en HTTP simple il n'existe pas.
            // Le dire, plutôt que de laisser croire à une copie silencieuse.
            toast.error(`Copie impossible depuis ce navigateur — ${libelle} : ${valeur}`);
        }
    };

    return (
        <button
            type="button"
            onClick={copier}
            title={`Copier ${libelle.toLowerCase()}`}
            className="group inline-flex max-w-full items-center gap-1 rounded px-1 py-0.5 text-xs text-slate-500 transition-colors hover:bg-slate-100 hover:text-ink"
        >
            {Icone && <Icone className="h-3 w-3 shrink-0 text-slate-400" />}
            <span className={cn('truncate', mono && 'font-mono')}>{valeur}</span>
            {copie
                ? <Check className="h-3 w-3 shrink-0 text-emerald-600" />
                : <Copy className="h-3 w-3 shrink-0 text-slate-300 group-hover:text-slate-500" />}
        </button>
    );
}

/**
 * Une lacune du registre, cliquable.
 *
 * ⚠️ Affichée **seulement si elle compte au moins une fiche**. Une puce à zéro mènerait à une
 * liste vide : elle occuperait la place sans rien apprendre, et l'en-tête doit dire ce qui reste
 * à faire, pas énumérer ce qui va bien.
 */
function Lacune({ nombre, actif, onClick, children }) {
    if (!nombre) return null;

    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-colors',
                actif
                    ? 'border-amber-400 bg-amber-100 text-amber-900'
                    : 'border-amber-200 bg-amber-50 text-amber-700 hover:border-amber-300 hover:bg-amber-100',
            )}
        >
            <AlertTriangle className="h-3 w-3" />
            {nombre} {children}
        </button>
    );
}

/* ─── Ligne ──────────────────────────────────────────────────────────────── */

function LigneSociete({ societe, onRadier, afficherLiquidation }) {
    return (
        <motion.tr
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="border-b border-slate-100 align-top last:border-0 hover:bg-slate-50/60"
        >
            <td className="px-4 py-3">
                <div className="flex flex-col gap-0.5">
                    {/* La denomination mene a la fiche : composition, historique, diff des
                        operations. Le registre liste, la fiche raconte. */}
                    <a
                        href={`/societes/${societe.id}/fiche`}
                        className="font-medium text-ink transition-colors hover:text-seal-hover hover:underline"
                    >
                        {societe.denomination}
                    </a>
                    <span className="flex items-center gap-1.5 text-xs text-slate-500">
                        {[societe.sigle, societe.forme_label].filter(Boolean).join(' · ') || '—'}
                        {/* Badge « externe » (addendum v1.2 § 7.1) : l'état de cette fiche vient
                            de pièces fournies par le client, pas d'un dossier de l'étude. Ce
                            n'est pas un défaut — c'est ce qui explique pourquoi la checklist
                            des pièces constitutives s'affiche sur elle et pas sur ses voisines. */}
                        {societe.externe && (
                            <span
                                className="rounded-full border border-slate-200 bg-slate-50 px-1.5 py-px text-[10px] font-medium text-slate-500"
                                title="Constituée par un autre office : son état est initialisé depuis les statuts et le RCCM fournis."
                            >
                                externe
                            </span>
                        )}
                    </span>

                    {/* Les identifiants officiels, copiables : ce sont eux qu'on recopie dans
                        les formalités et les courriers, et qu'on retape de travers. */}
                    <div className="mt-0.5 flex flex-wrap items-center gap-x-1 gap-y-0.5">
                        <ValeurCopiable valeur={societe.rccm_numero} libelle="RCCM" mono />
                        <ValeurCopiable valeur={societe.nif} libelle="NIF" mono />
                    </div>

                    <div className="flex flex-wrap items-center gap-x-1 gap-y-0.5">
                        <ValeurCopiable valeur={societe.email_societe} libelle="Email" icone={Mail} />
                        <ValeurCopiable valeur={societe.telephone_societe} libelle="Téléphone" icone={Phone} />
                    </div>

                    {/* Trace des données perdues avant que le retour de formalité ne les
                        capte : la société est immatriculée — son dossier a dépassé les
                        formalités — mais son numéro n'a jamais atteint le registre. Aucun
                        rattrapage automatique n'est possible, l'étude corrige en une saisie. */}
                    {/* ⚠️ Un **constat**, et non un bouton. La fiche ne se corrige pas depuis
                        le registre : une modification de société est l'objet d'un dossier, et
                        elle n'est portée au registre qu'à l'**Expédition** — par
                        `SocieteMutationService`, après le PV, la certification et les
                        formalités. Un formulaire d'édition ici contournerait ce circuit.
                        ⚠️ Conséquence assumée : ce message n'a **pas de sortie**. Le numéro
                        manquant se rattrape au retour de la formalité ({@see DonneeAuRetour}),
                        ou dans le prochain dossier de la société. */}
                    {societe.manque_rccm && (
                        <span className="mt-1 inline-flex w-fit items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">
                            <AlertTriangle className="h-3 w-3" />
                            immatriculée — RCCM absent du registre
                        </span>
                    )}
                </div>
            </td>

            <td className="px-4 py-3">
                <Badge variant="outline" className={classesStatutSociete(societe.statut)}>
                    {societe.statut_label}
                </Badge>
                {societe.dissolution_at && (
                    <div className="mt-1 text-xs text-slate-500">
                        dissoute le {societe.dissolution_at}
                    </div>
                )}
            </td>

            <td className="px-4 py-3 text-sm text-slate-600">
                {societe.siege ? (
                    <span className="inline-flex items-start gap-1.5">
                        <MapPin className="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />
                        <span>{societe.siege}</span>
                    </span>
                ) : <span className="text-slate-400">—</span>}
            </td>

            <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                {societe.capital_chiffres !== null && societe.capital_chiffres !== undefined
                    ? `${Number(societe.capital_chiffres).toLocaleString('fr-FR')} GNF`
                    : <span className="text-slate-400">—</span>}
            </td>

            {/* Colonnes de liquidation : rendues seulement quand la page a de quoi les
                remplir. Voir `afficherLiquidation` côté serveur. */}
            {afficherLiquidation && (
                <>
                    <td className="px-4 py-3">
                        <Echeance echeance={societe.echeance} />
                    </td>
                    <td className="px-4 py-3 text-sm text-slate-600">
                        {societe.liquidateur ?? <span className="text-slate-400">—</span>}
                    </td>
                </>
            )}

            <td className="px-4 py-3 text-sm text-slate-600">
                {societe.dossiers_count > 0 ? (
                    <a
                        href={`/dossiers?societe=${societe.id}`}
                        title="Voir les dossiers de cette société"
                        className="inline-flex items-center gap-1.5 rounded px-1 py-0.5 transition-colors hover:bg-slate-100 hover:text-ink"
                    >
                        <FileText className="h-3.5 w-3.5 text-slate-400" />
                        {societe.dossiers_count}
                    </a>
                ) : (
                    <span className="inline-flex items-center gap-1.5 text-slate-400">
                        <FileText className="h-3.5 w-3.5" />0
                    </span>
                )}
                {societe.dossier_origine && (
                    <a
                        href={`/dossiers/${societe.dossier_origine}`}
                        title="Ouvrir le dossier constitutif — statuts, PV, RCCM, DNSV"
                        className="mt-0.5 flex items-center gap-1 text-[11px] text-slate-400 hover:text-seal-hover hover:underline"
                    >
                        <ExternalLink className="h-3 w-3" />
                        {societe.dossier_origine}
                    </a>
                )}
            </td>

            <td className="px-4 py-3 text-right">
                {/* Seule action du registre : constater une radiation. Les autres changements
                    d'état viennent des dossiers, et la radiation seule ne peut pas en venir —
                    elle n'est prouvée que par la pièce du greffe, qui arrive après la clôture
                    ({@see SocieteController::radier}). */}
                {societe.peut_etre_radiee && (
                    <Button size="sm" variant="outline" onClick={() => onRadier(societe)}>
                        <Gavel className="mr-1.5 h-3.5 w-3.5" />
                        Radier
                    </Button>
                )}
            </td>
        </motion.tr>
    );
}

/* ─── Page ───────────────────────────────────────────────────────────────── */

export default function Index({ societes, statuts, filters, stats, afficherLiquidation }) {
    const [q, setQ] = useState(filters.q ?? '');
    const [aRadier, setARadier] = useState(null);

    const filtrer = (patch) => router.get(
        '/societes',
        { q, statut: filters.statut, manque: filters.manque, ...patch },
        { preserveState: true, replace: true },
    );

    /** Cliquer une lacune déjà active la retire : la puce est une bascule, pas un aller simple. */
    const basculerLacune = (quoi) => filtrer({ manque: filters.manque === quoi ? '' : quoi });

    const filtreActif = filters.q || filters.statut || filters.manque;

    // Le nombre de colonnes varie avec `afficherLiquidation` : la ligne « aucun résultat »
    // doit suivre, sinon le tableau se décale.
    const nbColonnes = afficherLiquidation ? 8 : 6;

    return (
        <AppLayout>
            <Head title="Registre des sociétés" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold text-ink">
                            <Building2 className="h-6 w-6 text-slate-400" />
                            Registre des sociétés
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {stats.total} fiche{stats.total > 1 ? 's' : ''} — {stats.enLiquidation} en liquidation,
                            {' '}{stats.aRadier} en attente de radiation.
                        </p>
                    </div>
                </header>

                {/* ⚠️ **Les lacunes mènent quelque part.** Elles étaient annoncées en texte
                    simple — « 5 immatriculées sans RCCM » — sans aucun moyen de voir lesquelles :
                    un chiffre sans sortie se contemple. Chaque puce filtre la liste, et un
                    second clic la relâche. Une lacune à zéro n'est pas rendue : elle mènerait à
                    une liste vide et occuperait la place sans rien apprendre. */}
                <div className="flex flex-wrap items-center gap-2">
                    <Lacune
                        nombre={stats.manqueRccm}
                        actif={filters.manque === 'rccm'}
                        onClick={() => basculerLacune('rccm')}
                    >
                        immatriculée{stats.manqueRccm > 1 ? 's' : ''} sans RCCM
                    </Lacune>
                    <Lacune
                        nombre={stats.manqueNif}
                        actif={filters.manque === 'nif'}
                        onClick={() => basculerLacune('nif')}
                    >
                        sans NIF
                    </Lacune>
                    <Lacune
                        nombre={stats.manqueDate}
                        actif={filters.manque === 'date_constitution'}
                        onClick={() => basculerLacune('date_constitution')}
                    >
                        sans date d'immatriculation
                    </Lacune>
                    <Lacune
                        nombre={stats.sansDossier}
                        actif={filters.manque === 'sans_dossier'}
                        onClick={() => basculerLacune('sans_dossier')}
                    >
                        sans dossier constitutif
                    </Lacune>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <form
                        onSubmit={(e) => { e.preventDefault(); filtrer({ q }); }}
                        className="relative flex-1 min-w-[240px]"
                    >
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <Input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Dénomination, sigle, RCCM, NIF…"
                            className="pl-9"
                        />
                    </form>

                    <Select
                        value={filters.statut || 'tous'}
                        onValueChange={(v) => filtrer({ statut: v === 'tous' ? '' : v })}
                    >
                        <SelectTrigger className="w-[220px]">
                            <SelectValue placeholder="Tous les statuts" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="tous">Tous les statuts</SelectItem>
                            {statuts.map((s) => (
                                <SelectItem key={s.valeur} value={s.valeur}>{s.label}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {filtreActif && (
                        <Button variant="ghost" size="sm" onClick={() => { setQ(''); filtrer({ q: '', statut: '', manque: '' }); }}>
                            <X className="mr-1.5 h-3.5 w-3.5" />
                            Réinitialiser
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                    <table className="w-full">
                        <thead className="border-b border-slate-200 bg-slate-50/80">
                            <tr className="text-left text-xs font-medium uppercase tracking-wide text-slate-500">
                                <th className="px-4 py-3">Société</th>
                                <th className="px-4 py-3">Statut</th>
                                <th className="px-4 py-3">Siège</th>
                                <th className="px-4 py-3">Capital</th>
                                {afficherLiquidation && <th className="px-4 py-3">Prochaine échéance</th>}
                                {afficherLiquidation && <th className="px-4 py-3">Liquidateur</th>}
                                <th className="px-4 py-3">Dossiers</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {societes.data.map((s) => (
                                <LigneSociete
                                    key={s.id}
                                    societe={s}
                                    onRadier={setARadier}
                                    afficherLiquidation={afficherLiquidation}
                                />
                            ))}
                            {societes.data.length === 0 && (
                                <tr>
                                    <td colSpan={nbColonnes} className="px-4 py-12 text-center text-sm text-slate-500">
                                        Aucune société ne correspond à cette recherche.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {societes.last_page > 1 && (
                    <div className="flex justify-center gap-1">
                        {societes.links.map((lien, i) => (
                            <Button
                                key={i}
                                size="sm"
                                variant={lien.active ? 'default' : 'ghost'}
                                disabled={!lien.url}
                                onClick={() => lien.url && router.visit(lien.url, { preserveState: true })}
                                dangerouslySetInnerHTML={{ __html: lien.label }}
                            />
                        ))}
                    </div>
                )}
            </div>

            {aRadier && <DialogRadiation societe={aRadier} onClose={() => setARadier(null)} />}

        </AppLayout>
    );
}
