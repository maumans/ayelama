import { useEffect, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Download, Search, Wallet, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { telechargerFichier } from '@/lib/telechargement';
import { cn } from '@/lib/utils';

/**
 * Comptabilité des dossiers — vue d'ensemble (2026-11-23).
 *
 * Une ligne par dossier ayant une facture ou un versement. Les chiffres sont calculés par le
 * serveur (`SituationFinanciere`), les mêmes que sur la fiche du dossier : rien n'est recalculé ici.
 */

const fmt = (n) => Number(n || 0).toLocaleString('fr-FR');

const COLONNES = [
    { cle: 'honorairesFactures', label: 'Honoraires' },
    { cle: 'deboursFactures',    label: 'Débours facturés' },
    { cle: 'encaisse',           label: 'Encaissé' },
    { cle: 'resteDu',            label: 'Reste dû', alerte: true },
    { cle: 'deboursVerses',      label: 'Versé' },
    { cle: 'deboursAVerser',     label: 'À verser', alerte: true },
    { cle: 'provision',          label: 'Provision' },
    { cle: 'avance',             label: 'Avance', alerte: true, rouge: true },
];

const CHAMP = 'h-8 rounded-md border border-slate-200 bg-white px-2 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-seal';

/** Les critères non vides, dans l'ordre de l'URL — la page et l'export lisent les mêmes. */
const nettoyer = (c) => Object.fromEntries(Object.entries(c).filter(([, v]) => v !== null && v !== undefined && v !== ''));

export default function ComptabiliteIndex({ lignes = [], totaux = {}, filtres = [], filtre = null, criteres = {}, options = {} }) {
    // Critères combinables (2026-11-25) : situation, type d'acte, étape, période d'ouverture, recherche.
    const actifs = nettoyer({ ...criteres, filtre });
    const appliquer = (changement) => router.get('/comptabilite', nettoyer({ ...actifs, ...changement }), { preserveScroll: true, preserveState: true });
    const choisir = (valeur) => appliquer({ filtre: valeur });

    // La recherche ne part qu'à la validation (Entrée ou sortie du champ), pas à chaque frappe.
    const [texte, setTexte] = useState(criteres.q ?? '');
    useEffect(() => setTexte(criteres.q ?? ''), [criteres.q]);
    const chercher = () => { if ((texte.trim() || null) !== (criteres.q ?? null)) appliquer({ q: texte.trim() }); };

    const nbCriteres = Object.keys(actifs).length;
    const requete = new URLSearchParams(actifs).toString();

    return (
        <AppLayout breadcrumbs={[{ label: 'Comptabilité des dossiers' }]}>
            <Head title="Comptabilité des dossiers — Ayelema" />
            <div className="p-6 max-w-7xl mx-auto space-y-5">
                <div className="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <h1 className="font-serif text-display text-ink flex items-center gap-2">
                            <Wallet className="h-5 w-5 text-seal shrink-0" />
                            Comptabilité des dossiers
                        </h1>
                        <p className="text-sm text-slate-500 mt-1 ml-7">
                            Ce que chaque client doit et a versé, ce que l'étude a reversé aux organismes, ce qu'elle détient ou avance.
                            Factures émises seulement.
                        </p>
                    </div>
                    <Button variant="outline" className="gap-1.5"
                        onClick={() => telechargerFichier(`/comptabilite/export.csv${requete ? `?${requete}` : ''}`)}>
                        <Download className="h-4 w-4" /> Exporter (Excel)
                    </Button>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    {[
                        { label: 'Encaissé', v: totaux.encaisse, cls: 'text-green-700' },
                        { label: 'Reste dû par les clients', v: totaux.resteDu, cls: totaux.resteDu > 0 ? 'text-amber-700' : 'text-ink' },
                        { label: 'Provisions détenues', v: totaux.provision, cls: 'text-ink' },
                        { label: "Avances de l'étude", v: totaux.avance, cls: totaux.avance > 0 ? 'text-red-700' : 'text-ink' },
                    ].map(({ label, v, cls }) => (
                        <Card key={label}><CardContent className="p-4">
                            <div className="text-[10px] uppercase tracking-wide text-slate-500">{label}</div>
                            <div className={cn('font-ref text-xl font-semibold tabular-nums', cls)}>{fmt(v)} GNF</div>
                        </CardContent></Card>
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <Search className="h-3.5 w-3.5 text-slate-400 absolute left-2 top-1/2 -translate-y-1/2" />
                        <input type="search" value={texte} placeholder="Référence ou objet…"
                            onChange={(e) => setTexte(e.target.value)} onBlur={chercher}
                            onKeyDown={(e) => e.key === 'Enter' && chercher()}
                            className={cn(CHAMP, 'pl-7 w-52')} />
                    </div>
                    <select value={criteres.type ?? ''} onChange={(e) => appliquer({ type: e.target.value })} className={CHAMP} aria-label="Type d'acte">
                        <option value="">Tous les types d'acte</option>
                        {(options.types ?? []).map((t) => <option key={t.valeur} value={t.valeur}>{t.label}</option>)}
                    </select>
                    <select value={criteres.etape ?? ''} onChange={(e) => appliquer({ etape: e.target.value })} className={CHAMP} aria-label="Étape">
                        <option value="">Toutes les étapes</option>
                        {(options.etapes ?? []).map((t) => <option key={t.valeur} value={t.valeur}>{t.label}</option>)}
                    </select>
                    <label className="flex items-center gap-1 text-xs text-slate-500">
                        Ouvert du
                        <input type="date" value={criteres.du ?? ''} max={criteres.au ?? undefined} onChange={(e) => appliquer({ du: e.target.value })} className={CHAMP} />
                        au
                        <input type="date" value={criteres.au ?? ''} min={criteres.du ?? undefined} onChange={(e) => appliquer({ au: e.target.value })} className={CHAMP} />
                    </label>
                    {nbCriteres > 0 && (
                        <button type="button" onClick={() => { setTexte(''); router.get('/comptabilite', {}, { preserveScroll: true }); }}
                            className="text-xs text-slate-500 hover:text-seal inline-flex items-center gap-1">
                            <X className="h-3.5 w-3.5" /> Réinitialiser ({nbCriteres})
                        </button>
                    )}
                </div>

                <div className="flex flex-wrap gap-2">
                    <button type="button" onClick={() => choisir(null)}
                        className={cn('text-xs rounded-full border px-3 py-1', !filtre ? 'bg-seal text-white border-seal' : 'bg-white text-slate-600 border-slate-200')}>
                        Tous
                    </button>
                    {filtres.map((f) => (
                        <button key={f.valeur} type="button" onClick={() => choisir(f.valeur)}
                            className={cn('text-xs rounded-full border px-3 py-1', filtre === f.valeur ? 'bg-seal text-white border-seal' : 'bg-white text-slate-600 border-slate-200')}>
                            {f.label}
                        </button>
                    ))}
                </div>

                <Card>
                    <CardContent className="p-0 overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-left text-[11px] uppercase tracking-wide text-slate-500 border-b border-slate-200 bg-slate-50">
                                    <th className="px-3 py-2.5 font-medium">Dossier</th>
                                    {COLONNES.map((c) => <th key={c.cle} className="px-3 py-2.5 font-medium text-right whitespace-nowrap">{c.label}</th>)}
                                </tr>
                            </thead>
                            <tbody>
                                {lignes.length === 0 && (
                                    <tr><td colSpan={COLONNES.length + 1} className="px-3 py-8 text-center text-slate-500">{nbCriteres > 0 ? 'Aucun dossier ne répond à ces critères.' : 'Aucun dossier.'}</td></tr>
                                )}
                                {lignes.map((l) => (
                                    <tr key={l.reference} className="border-b border-slate-100 align-top hover:bg-slate-50/60">
                                        <td className="px-3 py-2.5">
                                            <Link href={`/dossiers/${l.reference}?tab=facturation`} className="font-ref text-seal font-semibold">{l.reference}</Link>
                                            <div className="text-xs text-slate-500 max-w-xs truncate" title={l.objet}>{l.objet}</div>
                                            {l.alertes?.length > 0 && (
                                                <div className="mt-1 flex items-start gap-1 text-[11px] text-amber-700">
                                                    <AlertTriangle className="h-3 w-3 shrink-0 mt-0.5" />
                                                    <span>{l.alertes[0]}</span>
                                                </div>
                                            )}
                                        </td>
                                        {COLONNES.map((c) => (
                                            <td key={c.cle} className={cn(
                                                'px-3 py-2.5 text-right font-ref tabular-nums whitespace-nowrap',
                                                c.alerte && l[c.cle] > 0 ? (c.rouge ? 'text-red-700 font-semibold' : 'text-amber-700 font-semibold') : 'text-slate-700',
                                                !l[c.cle] && 'text-slate-300',
                                            )}>
                                                {fmt(l[c.cle])}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </tbody>
                            {lignes.length > 0 && (
                                <tfoot>
                                    <tr className="bg-slate-50 font-semibold">
                                        <td className="px-3 py-2.5 text-xs uppercase tracking-wide text-slate-500">Total ({lignes.length})</td>
                                        {COLONNES.map((c) => (
                                            <td key={c.cle} className="px-3 py-2.5 text-right font-ref tabular-nums whitespace-nowrap">{fmt(totaux[c.cle])}</td>
                                        ))}
                                    </tr>
                                </tfoot>
                            )}
                        </table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
