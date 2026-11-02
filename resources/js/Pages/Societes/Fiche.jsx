import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { classesStatutSociete } from '@/data/statutsSociete';
import { cn } from '@/lib/utils';
import {
    Building2, Users, History, FileText, MapPin, AlertTriangle,
    ArrowRight, ExternalLink, CalendarClock,
} from 'lucide-react';

const montant = (v) => (v === null || v === undefined || v === ''
    ? '—'
    : `${Number(v).toLocaleString('fr-FR')} GNF`);

/**
 * Rend une valeur d'instantané lisible.
 *
 * Les instantanés traversent JSON : un décimal casté revient en chaîne, un entier en nombre.
 * Les afficher bruts donnerait « Capital : 10000000.00 → 25000000 » — illisible, et faussement
 * différent à l'œil.
 */
function valeur(cle, v) {
    if (v === null || v === undefined || v === '') return '—';
    if (cle === 'capital_chiffres' || cle === 'valeur_nominale') return montant(v);
    if (cle === 'duree') return `${v} ans`;
    return String(v);
}

function Onglet({ actif, onClick, icone: Icone, children, compte }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'inline-flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                actif
                    ? 'border-seal text-ink'
                    : 'border-transparent text-slate-500 hover:border-slate-200 hover:text-slate-700',
            )}
        >
            <Icone className="h-4 w-4" />
            {children}
            {compte !== undefined && (
                <span className="rounded-full bg-slate-100 px-1.5 text-[11px] text-slate-500">{compte}</span>
            )}
        </button>
    );
}

/* ─── État actuel ─────────────────────────────────────────────────────── */

function Donnee({ label, children, alerte = false }) {
    return (
        <div>
            <p className="text-[11px] font-medium uppercase tracking-wider text-slate-400">{label}</p>
            <p className={cn('mt-0.5 text-sm', alerte ? 'text-amber-700' : 'text-ink')}>{children}</p>
        </div>
    );
}

/* ─── Page ────────────────────────────────────────────────────────────── */

export default function Fiche({ societe, associes, gerants, historique, libelles }) {
    const [onglet, setOnglet] = useState('composition');

    const etat = societe.etat ?? {};
    const actuels = associes.filter(a => a.actuel);
    const anciens = associes.filter(a => !a.actuel);
    const enFonction = gerants.filter(g => g.en_fonction);
    const ancienssGerants = gerants.filter(g => !g.en_fonction);

    // Le nombre de parts statutaire et celui réellement réparti doivent coïncider. L'écart est
    // précisément ce que le contrôle bloquant de la répartition interdit ; le taire sur la
    // fiche reviendrait à laisser une société mal composée passer inaperçue.
    const ecartParts = societe.parts_reparties !== Number(etat.nombre_parts ?? 0);

    return (
        <AppLayout breadcrumbs={[{ label: 'Sociétés', href: '/societes' }, { label: societe.denomination }]}>
            <Head title={societe.denomination} />

            <div className="mx-auto max-w-6xl space-y-6 px-4 py-8">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="flex items-center gap-2 font-serif text-heading text-ink">
                            <Building2 className="h-5 w-5 text-slate-400" />
                            {societe.denomination}
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            {[societe.sigle, societe.forme_label].filter(Boolean).join(' · ')}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline" className={classesStatutSociete(societe.statut)}>
                            {societe.statut_label}
                        </Badge>
                        {societe.dossiers_count > 0 && (
                            <Button variant="outline" size="sm" asChild>
                                <a href={`/dossiers?societe=${societe.id}`}>
                                    <FileText className="mr-1.5 h-3.5 w-3.5" />
                                    {societe.dossiers_count} dossier{societe.dossiers_count > 1 ? 's' : ''}
                                </a>
                            </Button>
                        )}
                    </div>
                </header>

                {/* ── État actuel ───────────────────────────────────────────── */}
                <div className="rounded-xl border border-slate-200 bg-white p-5">
                    <div className="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3 lg:grid-cols-4">
                        <Donnee label="Capital">{montant(etat.capital_chiffres)}</Donnee>
                        <Donnee label="Parts">{etat.nombre_parts ?? '—'}</Donnee>
                        <Donnee label="Valeur nominale">{montant(etat.valeur_nominale)}</Donnee>
                        <Donnee label="Durée">{etat.duree ? `${etat.duree} ans` : '—'}</Donnee>
                        <Donnee label="RCCM" alerte={societe.manque_rccm}>
                            {etat.rccm_numero || (societe.manque_rccm ? 'absent du registre' : '—')}
                        </Donnee>
                        <Donnee label="NIF">{etat.nif || '—'}</Donnee>
                        <div className="col-span-2">
                            <Donnee label="Siège social">
                                <span className="inline-flex items-start gap-1.5">
                                    <MapPin className="mt-0.5 h-3.5 w-3.5 shrink-0 text-slate-400" />
                                    {etat.siege || '—'}
                                </span>
                            </Donnee>
                        </div>
                    </div>

                    {etat.objet_social && (
                        <div className="mt-4 border-t border-slate-100 pt-4">
                            <Donnee label="Objet social">{etat.objet_social}</Donnee>
                        </div>
                    )}

                    {societe.echeance && (
                        <div className="mt-4 flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            <CalendarClock className="h-4 w-4 shrink-0" />
                            {societe.echeance.label} — au {societe.echeance.echeance}
                        </div>
                    )}
                </div>

                {/* ── Onglets ───────────────────────────────────────────────── */}
                <div className="rounded-xl border border-slate-200 bg-white">
                    <div className="flex flex-wrap border-b border-slate-200 px-2">
                        <Onglet actif={onglet === 'composition'} onClick={() => setOnglet('composition')} icone={Users} compte={actuels.length + enFonction.length}>
                            Associés &amp; gérants
                        </Onglet>
                        <Onglet actif={onglet === 'historique'} onClick={() => setOnglet('historique')} icone={History} compte={historique.length}>
                            Historique
                        </Onglet>
                    </div>

                    <div className="p-5">
                        {onglet === 'composition' ? (
                            <div className="space-y-6">
                                <section>
                                    <h2 className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                        Associés actuels
                                    </h2>

                                    {ecartParts && (
                                        <p className="mb-3 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                                            <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                            La répartition porte {societe.parts_reparties} part(s) pour un capital
                                            qui en compte {etat.nombre_parts}. Chaque part doit être attribuée —
                                            la prochaine opération le refusera.
                                        </p>
                                    )}

                                    {actuels.length === 0 ? (
                                        <p className="text-sm italic text-slate-400">
                                            Aucun associé enregistré — la reprise n'a pas encore été appliquée à cette fiche.
                                        </p>
                                    ) : (
                                        <ul className="divide-y divide-slate-100">
                                            {actuels.map(a => (
                                                <li key={a.id} className="flex flex-wrap items-baseline justify-between gap-2 py-2">
                                                    <span className="text-sm font-medium text-ink">{a.nom}</span>
                                                    <span className="text-sm text-slate-600">
                                                        {a.nb_parts} part{a.nb_parts > 1 ? 's' : ''}
                                                        {a.pourcentage !== null && (
                                                            <span className="ml-2 text-slate-400">{a.pourcentage} %</span>
                                                        )}
                                                        <span className="ml-3 text-xs text-slate-400">depuis le {a.date_entree}</span>
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </section>

                                <section>
                                    <h2 className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                        Gérance
                                    </h2>
                                    {enFonction.length === 0 ? (
                                        <p className="text-sm italic text-slate-400">Aucun gérant enregistré.</p>
                                    ) : (
                                        <ul className="divide-y divide-slate-100">
                                            {enFonction.map(g => (
                                                <li key={g.id} className="flex flex-wrap items-baseline justify-between gap-2 py-2">
                                                    <span className="text-sm font-medium text-ink">
                                                        {g.nom}
                                                        <span className="ml-2 text-xs font-normal text-slate-400">
                                                            {g.statutaire ? 'statutaire' : 'non statutaire'}
                                                        </span>
                                                    </span>
                                                    <span className="text-xs text-slate-400">depuis le {g.date_nomination}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </section>

                                {/* Les anciens ne disparaissent pas : c'est ce que la table des
                                    associés datés permet, et ce qu'une colonne JSON interdisait. */}
                                {(anciens.length > 0 || ancienssGerants.length > 0) && (
                                    <section className="border-t border-slate-100 pt-4">
                                        <h2 className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                            Anciens
                                        </h2>
                                        <ul className="divide-y divide-slate-100">
                                            {anciens.map(a => (
                                                <li key={`a${a.id}`} className="flex flex-wrap items-baseline justify-between gap-2 py-2 text-slate-400">
                                                    <span className="text-sm">{a.nom} — associé</span>
                                                    <span className="text-xs">
                                                        {a.nb_parts} part(s) · du {a.date_entree} au {a.date_sortie}
                                                    </span>
                                                </li>
                                            ))}
                                            {ancienssGerants.map(g => (
                                                <li key={`g${g.id}`} className="flex flex-wrap items-baseline justify-between gap-2 py-2 text-slate-400">
                                                    <span className="text-sm">
                                                        {g.nom} — gérant{g.motif_fin ? ` (${g.motif_fin})` : ''}
                                                    </span>
                                                    <span className="text-xs">du {g.date_nomination} au {g.date_fin}</span>
                                                </li>
                                            ))}
                                        </ul>
                                    </section>
                                )}
                            </div>
                        ) : (
                            <div>
                                {historique.length === 0 ? (
                                    <p className="text-sm italic text-slate-400">Aucune opération enregistrée.</p>
                                ) : (
                                    <ol className="space-y-4">
                                        {historique.map(h => (
                                            <li key={h.id} className="relative border-l-2 border-slate-200 pl-4">
                                                <span className="absolute -left-[5px] top-1.5 h-2 w-2 rounded-full bg-seal" />
                                                <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                                    <span className="text-sm font-medium text-ink">{h.date_effet}</span>
                                                    {h.dossier && (
                                                        <a
                                                            href={`/dossiers/${h.dossier}`}
                                                            className="inline-flex items-center gap-1 text-xs text-seal-hover hover:underline"
                                                        >
                                                            <ExternalLink className="h-3 w-3" />{h.dossier}
                                                        </a>
                                                    )}
                                                    {h.auteur && <span className="text-xs text-slate-400">par {h.auteur}</span>}
                                                </div>

                                                <p className="mt-0.5 text-xs text-slate-500">
                                                    {h.operations.join(' · ')}
                                                </p>

                                                {/* Le diff lisible que la spec réclame (E2) :
                                                    « Capital : 10 000 000 → 25 000 000 GNF ». */}
                                                {Object.keys(h.differences).length > 0 && (
                                                    <ul className="mt-2 space-y-1">
                                                        {Object.entries(h.differences).map(([cle, d]) => (
                                                            <li key={cle} className="flex flex-wrap items-center gap-1.5 text-sm">
                                                                <span className="text-slate-400">{libelles[cle] ?? cle} :</span>
                                                                <span className="text-slate-500 line-through">{valeur(cle, d.avant)}</span>
                                                                <ArrowRight className="h-3 w-3 text-slate-300" />
                                                                <span className="font-medium text-ink">{valeur(cle, d.apres)}</span>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                )}
                                            </li>
                                        ))}
                                    </ol>
                                )}
                            </div>
                        )}
                    </div>
                </div>

                <Link href="/societes" className="inline-block text-sm text-slate-400 hover:text-slate-600 hover:underline">
                    ← Retour au registre
                </Link>
            </div>
        </AppLayout>
    );
}
