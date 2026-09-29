import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { motion } from 'framer-motion';
import AppLayout from '@/Layouts/AppLayout';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
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

/* ─── Ligne ──────────────────────────────────────────────────────────────── */

function LigneSociete({ societe, onRadier }) {
    return (
        <motion.tr
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="border-b border-slate-100 last:border-0 hover:bg-slate-50/60"
        >
            <td className="px-4 py-3">
                <div className="flex flex-col">
                    <span className="font-medium text-ink">{societe.denomination}</span>
                    <span className="text-xs text-slate-500">
                        {[societe.sigle, societe.forme_label, societe.rccm_numero].filter(Boolean).join(' · ') || '—'}
                    </span>
                    {/* Trace des données perdues avant que le retour de formalité ne les
                        capte : la société est immatriculée — son dossier a dépassé les
                        formalités — mais son numéro n'a jamais atteint le registre. Aucun
                        rattrapage automatique n'est possible, l'étude corrige en une saisie. */}
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

            <td className="px-4 py-3">
                <Echeance echeance={societe.echeance} />
            </td>

            <td className="px-4 py-3 text-sm text-slate-600">
                {societe.liquidateur ?? <span className="text-slate-400">—</span>}
            </td>

            <td className="px-4 py-3 text-sm text-slate-600">
                <span className="inline-flex items-center gap-1.5">
                    <FileText className="h-3.5 w-3.5 text-slate-400" />
                    {societe.dossiers_count}
                </span>
            </td>

            <td className="px-4 py-3 text-right">
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

export default function Index({ societes, statuts, filters, stats }) {
    const [q, setQ] = useState(filters.q ?? '');
    const [aRadier, setARadier] = useState(null);

    const filtrer = (patch) => router.get(
        '/societes',
        { q, statut: filters.statut, ...patch },
        { preserveState: true, replace: true },
    );

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
                            {stats.manqueRccm > 0 && (
                                <span className="text-amber-700">
                                    {' '}⚠️ {stats.manqueRccm} immatriculée{stats.manqueRccm > 1 ? 's' : ''} sans RCCM au registre.
                                </span>
                            )}
                        </p>
                    </div>
                </header>

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

                    {(filters.q || filters.statut) && (
                        <Button variant="ghost" size="sm" onClick={() => { setQ(''); filtrer({ q: '', statut: '' }); }}>
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
                                <th className="px-4 py-3">Prochaine échéance</th>
                                <th className="px-4 py-3">Liquidateur</th>
                                <th className="px-4 py-3">Dossiers</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {societes.data.map((s) => (
                                <LigneSociete key={s.id} societe={s} onRadier={setARadier} />
                            ))}
                            {societes.data.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-12 text-center text-sm text-slate-500">
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
