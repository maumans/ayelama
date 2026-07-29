import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Lock, ChevronDown, ChevronUp, X, FileText, Mail } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { cn } from '@/lib/utils';

const TYPE_DOC_LABELS = {
    acte_principal: 'Acte principal', page_garde: 'Page de garde', attestation: 'Attestation',
    declaration: 'Déclaration', dnsv: 'DNSV', insertion: 'Insertion au JORG', rccm: 'RCCM',
    note_frais: 'Note de frais', bordereau: 'Bordereau / Tableau', annexe: 'Annexe',
    procedure: 'Procédure', lettre: 'Lettre / Transmission', recepisse: 'Récépissé',
};

function TypeActeRow({ typeActe }) {
    const modeles = typeActe.modeles ?? [];
    const obligatoires = modeles.filter(m => m.obligatoire_cloture).length;
    const [open, setOpen] = useState(obligatoires > 0);

    const toggleModele = (modele) => {
        router.patch(`/modeles/${modele.id}`, { obligatoire_cloture: !modele.obligatoire_cloture }, { preserveScroll: true });
    };

    const bulkType = (valeur) => {
        router.post('/parametres/cloture/bulk', { type_acte_id: typeActe.id, obligatoire_cloture: valeur }, { preserveScroll: true });
    };

    return (
        <Card className="overflow-hidden">
            <button
                onClick={() => setOpen(o => !o)}
                className="w-full flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition-colors text-left"
            >
                <div className="flex items-center gap-3">
                    <span className="font-medium text-sm text-ink">{typeActe.label}</span>
                    <span className="text-[10px] text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                        {typeActe.categorieLabel}
                    </span>
                    <span className="text-[10px] text-slate-400">
                        {obligatoires}/{modeles.length} obligatoire{obligatoires > 1 ? 's' : ''}
                    </span>
                </div>
                {open ? <ChevronUp className="h-4 w-4 text-slate-300" /> : <ChevronDown className="h-4 w-4 text-slate-300" />}
            </button>

            {open && (
                <div className="border-t border-slate-100">
                    {modeles.length === 0 ? (
                        <p className="text-sm text-slate-400 text-center py-6">Aucun modèle configuré pour ce type d'acte.</p>
                    ) : (
                        <>
                            <table className="table-notarial w-full">
                                <thead>
                                    <tr>
                                        <th>Document</th>
                                        <th>Type</th>
                                        <th>Actif</th>
                                        <th>Obligatoire à la clôture</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {modeles.map(m => (
                                        <tr key={m.id} className={!m.est_actif ? 'opacity-50' : ''}>
                                            <td className="font-medium text-sm text-slate-700">
                                                <div className="flex items-center gap-1.5">
                                                    <FileText className="h-3.5 w-3.5 text-slate-300 shrink-0" />
                                                    {m.nom}
                                                </div>
                                            </td>
                                            <td>
                                                <span className="text-[10px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                                                    {TYPE_DOC_LABELS[m.type_document] ?? m.type_document}
                                                </span>
                                            </td>
                                            <td className="text-xs text-slate-400">{m.est_actif ? 'Actif' : 'Inactif'}</td>
                                            <td>
                                                <Switch
                                                    checked={m.obligatoire_cloture}
                                                    disabled={!m.est_actif}
                                                    onCheckedChange={() => toggleModele(m)}
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <div className="flex items-center gap-2 px-4 py-2.5 border-t border-slate-100 bg-slate-50/50">
                                <Lock className="h-3 w-3 text-slate-400" />
                                <span className="text-xs text-slate-400">Pour tout ce type d'acte :</span>
                                <Button variant="ghost" size="sm" className="h-6 text-xs text-seal" onClick={() => bulkType(true)}>
                                    Tout obligatoire
                                </Button>
                                <Button variant="ghost" size="sm" className="h-6 text-xs text-slate-400" onClick={() => bulkType(false)}>
                                    Tout désactiver
                                </Button>
                            </div>
                        </>
                    )}
                </div>
            )}
        </Card>
    );
}

function CourriersSection({ modelesCourriers }) {
    const [open, setOpen] = useState(modelesCourriers.some(m => m.obligatoire_cloture));

    const toggle = (modele) => {
        router.patch(`/modeles-courriers/${modele.id}`, { obligatoire_cloture: !modele.obligatoire_cloture }, { preserveScroll: true });
    };

    const obligatoires = modelesCourriers.filter(m => m.obligatoire_cloture).length;

    return (
        <Card className="overflow-hidden">
            <button
                onClick={() => setOpen(o => !o)}
                className="w-full flex items-center justify-between px-4 py-3 hover:bg-slate-50 transition-colors text-left"
            >
                <div className="flex items-center gap-3">
                    <Mail className="h-4 w-4 text-slate-400" />
                    <span className="font-medium text-sm text-ink">Courriers de transmission</span>
                    <span className="text-[10px] text-slate-400">
                        {obligatoires}/{modelesCourriers.length} obligatoire{obligatoires > 1 ? 's' : ''}
                    </span>
                </div>
                {open ? <ChevronUp className="h-4 w-4 text-slate-300" /> : <ChevronDown className="h-4 w-4 text-slate-300" />}
            </button>

            {open && (
                <div className="border-t border-slate-100">
                    {modelesCourriers.length === 0 ? (
                        <p className="text-sm text-slate-400 text-center py-6">Aucun modèle de courrier configuré.</p>
                    ) : (
                        <table className="table-notarial w-full">
                            <thead>
                                <tr>
                                    <th>Courrier</th>
                                    <th>Types d'actes liés</th>
                                    <th>Actif</th>
                                    <th>Obligatoire à la clôture</th>
                                </tr>
                            </thead>
                            <tbody>
                                {modelesCourriers.map(m => (
                                    <tr key={m.id} className={!m.est_actif ? 'opacity-50' : ''}>
                                        <td className="font-medium text-sm text-slate-700">
                                            <div className="flex items-center gap-1.5">
                                                <Mail className="h-3.5 w-3.5 text-slate-300 shrink-0" />
                                                {m.nom}
                                            </div>
                                        </td>
                                        <td>
                                            {m.applicable_tous ? (
                                                <span className="text-[10px] font-medium px-2 py-0.5 rounded-full bg-seal-light text-seal">
                                                    Tous types
                                                </span>
                                            ) : (
                                                <div className="flex flex-wrap gap-1">
                                                    {(m.typesActesLabels ?? []).map((label, i) => (
                                                        <span key={i} className="text-[10px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">
                                                            {label}
                                                        </span>
                                                    ))}
                                                </div>
                                            )}
                                        </td>
                                        <td className="text-xs text-slate-400">{m.est_actif ? 'Actif' : 'Inactif'}</td>
                                        <td>
                                            <Switch
                                                checked={m.obligatoire_cloture}
                                                disabled={!m.est_actif}
                                                onCheckedChange={() => toggle(m)}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            )}
        </Card>
    );
}

export default function ParametresCloture() {
    const { typesActes = [], modelesCourriers = [], categories = [], filters = {}, stats = {} } = usePage().props;
    const [categorie, setCategorie] = useState(filters.categorie ?? '');

    const bulkCategorie = (valeur) => {
        if (!categorie) return;
        router.post('/parametres/cloture/bulk', { categorie, obligatoire_cloture: valeur }, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={[{ label: 'Paramètres', href: '/parametres' }, { label: 'Clôture' }]}>
            <Head title="Clôture — Ayelema" />

            <div className="p-6 max-w-[1000px] mx-auto space-y-5">

                <div>
                    <h1 className="font-serif text-display text-ink flex items-center gap-2">
                        <Lock className="h-6 w-6 text-seal" /> Documents obligatoires à la clôture
                    </h1>
                    <p className="text-slate-500 text-sm mt-1">
                        Pour chaque type d'acte, choisissez quels documents doivent avoir leur version signée/cachetée
                        déposée avant de pouvoir clôturer un dossier. Une fois déposés, ces documents sont verrouillés
                        et consultables dans la GED.
                    </p>
                </div>

                <div className="grid grid-cols-3 gap-3">
                    <Card className="p-4 text-center">
                        <div className="text-2xl font-bold text-ink">{stats.totalModeles ?? 0}</div>
                        <div className="text-xs text-slate-500 mt-0.5">Modèles configurés</div>
                    </Card>
                    <Card className="p-4 text-center">
                        <div className="text-2xl font-bold text-seal">{stats.obligatoires ?? 0}</div>
                        <div className="text-xs text-slate-500 mt-0.5">Documents obligatoires</div>
                    </Card>
                    <Card className="p-4 text-center">
                        <div className="text-2xl font-bold text-seal">{stats.obligatoiresCourriers ?? 0}</div>
                        <div className="text-xs text-slate-500 mt-0.5">Courriers obligatoires</div>
                    </Card>
                </div>

                <div className="flex items-center gap-2 flex-wrap">
                    <Select
                        value={categorie}
                        onValueChange={v => {
                            setCategorie(v);
                            router.get('/parametres/cloture', { categorie: v }, { preserveState: true, replace: true });
                        }}
                    >
                        <SelectTrigger className="h-8 text-sm w-52">
                            <SelectValue placeholder="Toutes catégories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">Toutes catégories</SelectItem>
                            {categories.map(c => <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>)}
                        </SelectContent>
                    </Select>

                    {categorie && (
                        <>
                            <Button variant="ghost" size="sm" className="h-8 text-slate-400"
                                onClick={() => { setCategorie(''); router.get('/parametres/cloture', {}, { preserveState: true, replace: true }); }}>
                                <X className="h-3.5 w-3.5 mr-1" /> Tout voir
                            </Button>
                            <div className="h-4 w-px bg-slate-200" />
                            <span className="text-xs text-slate-400">Pour cette catégorie :</span>
                            <Button variant="outline" size="sm" className="h-8 text-xs" onClick={() => bulkCategorie(true)}>
                                Tout obligatoire
                            </Button>
                            <Button variant="outline" size="sm" className="h-8 text-xs" onClick={() => bulkCategorie(false)}>
                                Tout désactiver
                            </Button>
                        </>
                    )}

                    <span className="ml-auto text-xs text-slate-400">{typesActes.length} type{typesActes.length > 1 ? 's' : ''} d'actes</span>
                </div>

                {typesActes.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-20 text-center">
                            <Lock className="h-12 w-12 text-slate-200 mb-4" />
                            <h3 className="font-serif text-heading text-slate-500">Aucun type d'acte</h3>
                            <p className="text-sm text-slate-400 mt-1">Configurez d'abord des types d'actes.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-2">
                        {typesActes.map((t, i) => (
                            <motion.div key={t.id} initial={{ opacity: 0, y: 5 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.03 }}>
                                <TypeActeRow typeActe={t} />
                            </motion.div>
                        ))}
                    </div>
                )}

                <CourriersSection modelesCourriers={modelesCourriers} />
            </div>
        </AppLayout>
    );
}
