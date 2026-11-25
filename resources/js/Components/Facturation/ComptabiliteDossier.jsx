import { useState, useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import { AlertTriangle, Pencil, Plus, Trash2, Wallet } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NumberField } from '@/components/ui/number-field';
import { DateField } from '@/components/ui/date-field';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { isoDateToFR, frDateToISO } from '@/lib/dates';
import { notifyValidationError } from '@/lib/toast';
import { cn } from '@/lib/utils';

/**
 * La comptabilité du dossier (2026-11-23) — ce que le client doit et a versé, ce que l'étude a
 * reversé aux organismes, et ce qu'elle détient ou avance pour lui. Les chiffres viennent du
 * serveur (`App\Services\SituationFinanciere`) : l'écran n'en recalcule aucun.
 */

const fmtGNF = (n) => Number(n || 0).toLocaleString('fr-FR');

function Montant({ label, valeur, sous, ton = 'neutre' }) {
    const couleur = { neutre: 'text-ink', vert: 'text-green-700', ambre: 'text-amber-700', rouge: 'text-red-700' }[ton];
    return (
        <div className="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
            <div className="text-[10px] uppercase tracking-wide text-slate-500">{label}</div>
            <div className={cn('font-ref text-base font-semibold tabular-nums', couleur)}>{fmtGNF(valeur)} GNF</div>
            {sous && <div className="text-[11px] text-slate-400 mt-0.5">{sous}</div>}
        </div>
    );
}

export function SituationFinanciereCard({ situation }) {
    if (!situation) return null;
    const s = situation;

    return (
        <Card>
            <CardHeader className="pb-3 border-b border-slate-100">
                <CardTitle className="flex items-center gap-2">
                    <Wallet className="h-4 w-4 text-seal" />
                    Situation financière du dossier
                </CardTitle>
                <p className="text-xs text-slate-500 mt-1">
                    Factures émises seulement{s.enProforma > 0 ? ` — ${fmtGNF(s.enProforma)} GNF encore en proforma, non dus` : ''}.
                </p>
            </CardHeader>
            <CardContent className="pt-4 space-y-4">
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                    <Montant label="Honoraires facturés" valeur={s.honorairesFactures} sous="revient à l'étude" />
                    <Montant label="Débours facturés" valeur={s.deboursFactures} sous="dus aux organismes" />
                    <Montant label="Encaissé du client" valeur={s.encaisse} ton="vert" />
                    <Montant label="Reste dû par le client" valeur={s.resteDu} ton={s.resteDu > 0 ? 'ambre' : 'neutre'} />
                    <Montant label="Versé aux organismes" valeur={s.deboursVerses} />
                    <Montant label="Débours restant à verser" valeur={s.deboursAVerser} ton={s.deboursAVerser > 0 ? 'ambre' : 'neutre'} />
                </div>

                <div className={cn(
                    'rounded-lg px-3 py-2.5 text-sm flex items-center justify-between gap-3',
                    s.avance > 0 ? 'bg-red-50 border border-red-200 text-red-800' : 'bg-slate-50 border border-slate-200 text-slate-700',
                )}>
                    <span>
                        {s.avance > 0
                            ? "Avance de l'étude — versé aux organismes au-delà de l'encaissé"
                            : "Provision détenue pour le client — encaissé non encore reversé"}
                    </span>
                    <strong className="font-ref tabular-nums">{fmtGNF(s.avance > 0 ? s.avance : s.provision)} GNF</strong>
                </div>

                {s.alertes?.length > 0 && (
                    <ul className="space-y-1.5">
                        {s.alertes.map((a, i) => (
                            <li key={i} className="flex items-start gap-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                <AlertTriangle className="h-3.5 w-3.5 shrink-0 mt-0.5" />
                                {a}
                            </li>
                        ))}
                    </ul>
                )}
            </CardContent>
        </Card>
    );
}

const VIDE = { libelle: '', organisme: '', date_decaissement: '', montant: '', moyen: '', reference: '', notes: '' };

function ModalVersement({ open, onClose, dossierReference, versement }) {
    const moyensPaiement = usePage().props.referentiels?.moyensPaiement ?? [];
    const [form, setForm] = useState(VIDE);
    const [envoi, setEnvoi] = useState(false);

    useEffect(() => {
        if (!open) return;
        setForm(versement ? {
            libelle: versement.libelle ?? '',
            organisme: versement.organisme ?? '',
            date_decaissement: versement.date_iso ?? '',
            montant: String(versement.montant ?? ''),
            moyen: versement.moyen ?? '',
            reference: versement.reference ?? '',
            notes: versement.notes ?? '',
            formalite_id: versement.formalite_id ?? null,
        } : { ...VIDE, date_decaissement: new Date().toISOString().slice(0, 10) });
    }, [open, versement?.id]);

    const champ = (cle) => (e) => setForm((f) => ({ ...f, [cle]: e.target.value }));

    const enregistrer = (e) => {
        e.preventDefault();
        setEnvoi(true);
        const payload = { ...form, moyen: form.moyen || null, organisme: form.organisme || null };
        const options = { preserveScroll: true, onSuccess: onClose, onError: notifyValidationError, onFinish: () => setEnvoi(false) };
        versement
            ? router.patch(`/decaissements/${versement.id}`, payload, options)
            : router.post(`/dossiers/${dossierReference}/decaissements`, payload, options);
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>{versement ? 'Corriger le versement' : 'Enregistrer un versement à un organisme'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={enregistrer} className="space-y-3">
                    <div className="space-y-1.5">
                        <Label htmlFor="v-libelle">Objet</Label>
                        <Input id="v-libelle" value={form.libelle} onChange={champ('libelle')} maxLength={255}
                            placeholder="ex. Insertion au journal d'annonces légales" />
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="v-organisme">Organisme</Label>
                        <Input id="v-organisme" value={form.organisme} onChange={champ('organisme')} maxLength={60}
                            placeholder="ex. Impôts, Greffe, APIP, journal" />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label>Date du versement</Label>
                            <DateField value={isoDateToFR(form.date_decaissement)}
                                onValueChange={(v) => setForm((f) => ({ ...f, date_decaissement: frDateToISO(v) }))} />
                        </div>
                        <div className="space-y-1.5">
                            <Label>Montant (GNF)</Label>
                            <NumberField value={form.montant} onValueChange={(v) => setForm((f) => ({ ...f, montant: v }))} placeholder="0" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="v-moyen">Moyen</Label>
                            <select id="v-moyen" value={form.moyen} onChange={champ('moyen')}
                                className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal">
                                <option value="">Non précisé</option>
                                {moyensPaiement.map((m) => <option key={m.valeur} value={m.valeur}>{m.label}</option>)}
                            </select>
                        </div>
                        <div className="space-y-1.5">
                            <Label htmlFor="v-reference">Quittance / référence</Label>
                            <Input id="v-reference" value={form.reference} onChange={champ('reference')} maxLength={100} />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose} disabled={envoi}>Annuler</Button>
                        <Button type="submit" variant="seal" disabled={envoi || !form.libelle.trim() || !(Number(form.montant) > 0) || !form.date_decaissement}>
                            {envoi ? 'Enregistrement…' : 'Enregistrer'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function VersementsCard({ dossier, peutGerer }) {
    const versements = dossier.decaissements ?? [];
    const [edition, setEdition] = useState(null); // null fermé · {} ajout · versement
    const [aSupprimer, setASupprimer] = useState(null);

    const total = versements.reduce((s, v) => s + (v.montant || 0), 0);

    return (
        <Card>
            <CardHeader className="pb-3 border-b border-slate-100 flex flex-row items-center justify-between gap-3">
                <div>
                    <CardTitle>Versements aux organismes</CardTitle>
                    <p className="text-xs text-slate-500 mt-1">
                        Ce que l'étude a payé pour ce dossier. Le montant payé au dépôt d'une formalité s'y inscrit seul.
                    </p>
                </div>
                {peutGerer && (
                    <Button size="sm" variant="outline" className="h-8 gap-1" onClick={() => setEdition({})}>
                        <Plus className="h-3.5 w-3.5" /> Ajouter
                    </Button>
                )}
            </CardHeader>
            <CardContent className="pt-3">
                {versements.length === 0 ? (
                    <p className="text-sm text-slate-500 py-3">Aucun versement enregistré.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="text-left text-[11px] uppercase tracking-wide text-slate-500 border-b border-slate-100">
                                <th className="py-2 font-medium">Date</th>
                                <th className="py-2 font-medium">Objet</th>
                                <th className="py-2 font-medium">Moyen · réf.</th>
                                <th className="py-2 font-medium text-right">Montant</th>
                                {peutGerer && <th className="py-2" />}
                            </tr>
                        </thead>
                        <tbody>
                            {versements.map((v) => (
                                <tr key={v.id} className="border-b border-slate-50 align-top">
                                    <td className="py-2 font-ref text-xs text-slate-600 whitespace-nowrap">{v.date}</td>
                                    <td className="py-2">
                                        <div className="text-slate-800">{v.libelle}</div>
                                        <div className="text-[11px] text-slate-400">
                                            {v.organisme}
                                            {v.via_depot && ' · saisi au dépôt'}
                                            {v.reprise && ' · repris (date du dépôt, moyen inconnu)'}
                                        </div>
                                    </td>
                                    <td className="py-2 text-xs text-slate-600">
                                        {v.moyenLabel ?? '—'}{v.reference ? ` · ${v.reference}` : ''}
                                    </td>
                                    <td className="py-2 text-right font-ref tabular-nums">{fmtGNF(v.montant)}</td>
                                    {peutGerer && (
                                        <td className="py-2 text-right whitespace-nowrap">
                                            <button type="button" className="p-1 text-slate-400 hover:text-seal" aria-label="Corriger"
                                                onClick={() => setEdition(v)}><Pencil className="h-3.5 w-3.5" /></button>
                                            <button type="button" className="p-1 text-slate-400 hover:text-danger" aria-label="Supprimer"
                                                onClick={() => setASupprimer(v)}><Trash2 className="h-3.5 w-3.5" /></button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colSpan={3} className="pt-2 text-right text-xs text-slate-500">Total versé</td>
                                <td className="pt-2 text-right font-ref font-semibold tabular-nums">{fmtGNF(total)} GNF</td>
                                {peutGerer && <td />}
                            </tr>
                        </tfoot>
                    </table>
                )}
            </CardContent>

            <ModalVersement
                open={edition !== null}
                onClose={() => setEdition(null)}
                dossierReference={dossier.reference}
                versement={edition?.id ? edition : null}
            />
            <ConfirmDialog
                open={!!aSupprimer}
                onClose={() => setASupprimer(null)}
                title="Supprimer ce versement ?"
                description={aSupprimer ? `${aSupprimer.libelle} — ${fmtGNF(aSupprimer.montant)} GNF du ${aSupprimer.date}. La suppression est inscrite au journal du dossier.` : ''}
                confirmLabel="Supprimer"
                onConfirm={() => router.delete(`/decaissements/${aSupprimer.id}`, { preserveScroll: true, onError: notifyValidationError })}
            />
        </Card>
    );
}
