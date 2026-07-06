import React, { useState } from 'react';
import { QUESTIONNAIRES, TYPE_ACTE_CODE_MAP, getVisibleFields } from '@/data/questionnaires';
import { RepeatableGroup } from '@/Components/ui/RepeatableGroup';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Building2, Home, Scale, Briefcase, Heart, GitBranch,
    FileText, Mail, ChevronLeft, ChevronRight, Check,
    Users, ArrowRight, AlertCircle, PlusCircle, Edit2, Archive
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const WIZARD_STEPS = [
    { id: 'categorie', label: 'Catégorie', short: '1' },
    { id: 'type', label: 'Type précis', short: '2' },
    { id: 'questionnaire', label: 'Questionnaire', short: '3' },
    { id: 'recapitulatif', label: 'Récapitulatif', short: '4' },
];

const SOCIETE_GROUPES = [
    {
        id: 'creation',
        label: 'Création',
        icon: PlusCircle,
        color: 'border-blue-200 hover:border-blue-400 hover:bg-blue-50/40',
        iconBg: 'bg-blue-50',
        iconColor: 'text-blue-600',
        desc: "Constitution d'une nouvelle société (SARLU, SARL, SA, SAS, SASU, SNC, GIE)",
    },
    {
        id: 'modification',
        label: 'Modification',
        icon: Edit2,
        color: 'border-amber-200 hover:border-amber-400 hover:bg-amber-50/40',
        iconBg: 'bg-amber-50',
        iconColor: 'text-amber-600',
        desc: "Modification des statuts d'une société existante (capital, gérant, siège, objet…)",
    },
    {
        id: 'dissolution',
        label: 'Dissolution / Liquidation',
        icon: Archive,
        color: 'border-slate-200 hover:border-slate-400 hover:bg-slate-50/40',
        iconBg: 'bg-slate-50',
        iconColor: 'text-slate-500',
        desc: 'Dissolution amiable ou judiciaire et liquidation',
    },
];

const categories = [
    {
        id: 'societe', label: 'Société', icon: Building2,
        desc: 'Création, modification ou dissolution de société',
        color: 'border-blue-200 hover:border-blue-400 hover:bg-blue-50/50',
        activeColor: 'border-blue-400 bg-blue-50',
        iconColor: 'text-blue-600',
    },
    {
        id: 'vente', label: "Vente d'immeubles", icon: Home,
        desc: 'Acte de vente, cession, promesse de vente',
        color: 'border-emerald-200 hover:border-emerald-400 hover:bg-emerald-50/50',
        activeColor: 'border-emerald-400 bg-emerald-50',
        iconColor: 'text-emerald-600',
    },
    {
        id: 'hypotheque', label: "Contrat d'hypothèque", icon: Scale,
        desc: 'Hypothèque conventionnelle, lettre de crédit',
        color: 'border-violet-200 hover:border-violet-400 hover:bg-violet-50/50',
        activeColor: 'border-violet-400 bg-violet-50',
        iconColor: 'text-violet-600',
    },
    {
        id: 'bail', label: 'Baux', icon: Briefcase,
        desc: 'Bail à construire, habitation, professionnel',
        color: 'border-cyan-200 hover:border-cyan-400 hover:bg-cyan-50/50',
        activeColor: 'border-cyan-400 bg-cyan-50',
        iconColor: 'text-cyan-600',
    },
    {
        id: 'donation', label: 'Donation', icon: Heart,
        desc: 'Acte de donation entre vifs',
        color: 'border-pink-200 hover:border-pink-400 hover:bg-pink-50/50',
        activeColor: 'border-pink-400 bg-pink-50',
        iconColor: 'text-pink-600',
    },
    {
        id: 'succession', label: 'Successions', icon: GitBranch,
        desc: 'Partage amiable ou judiciaire',
        color: 'border-rose-200 hover:border-rose-400 hover:bg-rose-50/50',
        activeColor: 'border-rose-400 bg-rose-50',
        iconColor: 'text-rose-600',
    },
    {
        id: 'procuration', label: 'Procuration', icon: FileText,
        desc: 'Procuration spéciale ou générale',
        color: 'border-slate-200 hover:border-slate-400 hover:bg-slate-50/50',
        activeColor: 'border-slate-400 bg-slate-100',
        iconColor: 'text-slate-600',
    },
    {
        id: 'courrier', label: 'Courrier', icon: Mail,
        desc: "Courrier de transmission, prise en charge",
        color: 'border-stone-200 hover:border-stone-400 hover:bg-stone-50/50',
        activeColor: 'border-stone-400 bg-stone-100',
        iconColor: 'text-stone-600',
    },
];

const typesByCategorie = {
    societe: [
        { id: 'creation_sarlu', groupe: 'creation',    label: 'Constitution SARLU', desc: 'Société à responsabilité limitée unipersonnelle', actes: ['Statuts SARLU', 'Page de garde', 'Attestation dépôt capital', "Déclaration sur l'honneur", 'DNSV', 'Insertion journal légal', 'RCCM'] },
        { id: 'creation_sarl',  groupe: 'creation',    label: 'Constitution SARL',  desc: 'Société à responsabilité limitée (multi-associés)', actes: ['Statuts SARL', 'Page de garde', 'Attestation dépôt capital', "Déclaration sur l'honneur", 'DNSV', 'Insertion journal légal', 'RCCM'] },
        { id: 'creation_sa',    groupe: 'creation',    label: 'Constitution SA',    desc: 'Société anonyme — capital min. 140 000 000 GNF', actes: ['Statuts SA', "PV AG constitutive", 'Attestation dépôt capital', 'DNSV', 'Insertion journal légal', 'RCCM'] },
        { id: 'creation_sas',   groupe: 'creation',    label: 'Constitution SAS',   desc: 'Société par actions simplifiées (multi-associés)', actes: ['Statuts SAS', 'Page de garde', 'Attestation dépôt capital', "Déclaration sur l'honneur", 'DNSV', 'Insertion journal légal', 'RCCM'] },
        { id: 'creation_sasu',  groupe: 'creation',    label: 'Constitution SASU',  desc: 'SAS unipersonnelle', actes: ['Statuts SASU', 'Page de garde', 'Attestation dépôt capital', "Déclaration sur l'honneur", 'DNSV', 'Insertion journal légal', 'RCCM'] },
        { id: 'creation_snc',   groupe: 'creation',    label: 'Constitution SNC',   desc: 'Société en nom collectif', actes: ['Statuts SNC', 'Déclaration sur l\'honneur', 'RCCM'] },
        { id: 'creation_gie',   groupe: 'creation',    label: 'Constitution GIE',   desc: "Groupement d'intérêt économique", actes: ['Statuts GIE', 'DNSV', 'RCCM'] },
        { id: 'modification',   groupe: 'modification', label: 'Modification de statuts', desc: 'Changement de capital, gérant, siège, objet…', actes: ['Fiche de modification (obligatoire)', "PV d'AGE", 'Statuts modifiés'] },
        { id: 'dissolution',    groupe: 'dissolution',  label: 'Dissolution / Liquidation', desc: 'Dissolution amiable ou judiciaire', actes: ["PV d'AGE de dissolution", 'Acte de liquidation', 'Publication journal légal'] },
    ],
    vente: [
        { id: 'vente_immeuble',   label: "Vente immobilière (avec TF)",  desc: 'Vente avec titre foncier',          actes: ['Contrat de vente', 'Page de garde', 'Tableau de bordereau', 'Facture', 'Facture plus-value'] },
        { id: 'vente_sans_titre', label: "Vente immobilière (sans TF)",  desc: 'Vente sans titre foncier',          actes: ['Contrat de vente', 'Page de garde', 'Facture', 'Facture plus-value'] },
        { id: 'vente_immeuble',   label: 'Cession fonds de commerce',    desc: 'Cession commerciale',               actes: ['Acte de cession', 'Bordereau'] },
    ],
    hypotheque: [
        { id: 'hypotheque_conv', label: 'Hypothèque conventionnelle', desc: "Constitution de garantie hypothécaire en faveur d'une banque", actes: ["Contrat d'hypothèque", 'Bordereau de la Conservation Foncière', 'Page de garde', 'Facture'] },
        { id: 'mainlevee',       label: "Mainlevée d'hypothèque",      desc: 'Radiation et mainlevée après remboursement du crédit',        actes: ["Acte de mainlevée", 'Bordereau de la Conservation Foncière', 'Facture'] },
    ],
    bail: [
        { id: 'bail_habitation',   label: "Bail d'habitation",   desc: 'Location de logement à usage résidentiel',                   actes: ["Contrat de bail d'habitation", 'Page de garde', 'Facture'] },
        { id: 'bail_commercial',   label: 'Bail commercial',     desc: 'Location de local à usage commercial ou professionnel',      actes: ['Contrat de bail commercial', 'Page de garde', 'Facture'] },
        { id: 'bail_construction', label: 'Bail à construction', desc: 'Bail longue durée avec obligation de construire sur terrain', actes: ['Contrat de bail à construction', 'Page de garde', 'Facture'] },
    ],
    donation: [
        { id: 'donation_entre_vifs', label: 'Donation entre vifs', desc: '', actes: ['Acte de donation', 'Acceptation du donataire'] },
    ],
    succession: [
        { id: 'partage_amiable',    label: 'Partage amiable',    desc: '', actes: ['Acte de partage', "État des héritiers", "Attestation d'héritiers"] },
        { id: 'partage_judiciaire', label: 'Partage judiciaire', desc: '', actes: ['Acte de partage judiciaire', 'Décision de justice'] },
    ],
    procuration: [
        { id: 'procuration_speciale', label: 'Procuration spéciale',  desc: '', actes: ['Acte de procuration'] },
        { id: 'procuration_generale', label: 'Procuration générale',  desc: '', actes: ['Acte de procuration générale'] },
    ],
    courrier: [
        { id: 'courrier_transmission', label: 'Courrier de transmission', desc: '', actes: ['Lettre de transmission'] },
    ],
};

function getQuestionnaire(typeId) {
    return QUESTIONNAIRES[typeId] || [
        { id: 'client', label: 'Nom du client / partie', type: 'text', placeholder: 'Nom complet', required: true },
        { id: 'details', label: 'Détails', type: 'textarea', placeholder: 'Informations complémentaires', required: false },
    ];
}


// Initialise uniquement les champs répétables (pures valeurs par défaut).
function initRepeatableFields(fields) {
    const init = {};
    fields.forEach(f => {
        if (f.type === 'repeatable') {
            const emptyItem = Object.fromEntries((f.fields ?? []).map(sf => [sf.id, '']));
            init[f.id] = Array.from({ length: f.min ?? 1 }, () => ({ ...emptyItem }));
        }
    });
    return init;
}

export default function DossierCreate() {
    const { typesActes, notaires, reviseurs, formalistes } = usePage().props;

    const [step, setStep] = useState(0);
    const [categorie, setCategorie] = useState(null);
    const [sousGroupe, setSousGroupe] = useState(null);
    const [typeActe, setTypeActe] = useState(null);
    const [formValues, setFormValues] = useState({});
    const [objet, setObjet] = useState('');
    const [notaireId, setNotaireId] = useState('');
    const [reviseurId, setReviseurId] = useState('');
    const [formalisteId, setFormalisteId] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});

    // Trouve le TypeActe serveur à partir de la clé questionnaire via TYPE_ACTE_CODE_MAP (inversé).
    const findTypeActeId = () => {
        if (!typeActe || !typesActes) return null;
        const allTypes = Object.values(typesActes).flat();
        // Codes DB associés à cette clé questionnaire
        const codes = Object.entries(TYPE_ACTE_CODE_MAP)
            .filter(([, qKey]) => qKey === typeActe.id)
            .map(([code]) => code);
        if (codes.length > 0) {
            const match = allTypes.find(t => codes.includes(t.code));
            if (match) return match.id;
        }
        // Fallback label matching
        const match = allTypes.find(t =>
            t.label.toLowerCase().includes(typeActe.label.toLowerCase().split(' ')[0])
        );
        return match?.id ?? null;
    };

    const stepName = WIZARD_STEPS[step].id;
    const typesDisponibles = categorie
        ? sousGroupe
            ? (typesByCategorie[categorie.id] || []).filter(t => t.groupe === sousGroupe)
            : (typesByCategorie[categorie.id] || [])
        : [];
    const questionnaire = typeActe ? getQuestionnaire(typeActe.id) : [];
    // Champs filtrés selon les valeurs actuelles (showIf)
    const visibleFields = getVisibleFields(questionnaire, formValues);
    const categorieSelected = categories.find(c => c.id === categorie?.id);
    const typeSelected = typeActe;

    const canNext = () => {
        if (step === 0) return !!categorie;
        if (step === 1) return !!typeActe;
        if (step === 2) {
            const required = visibleFields.filter(q => q.required && q.type !== 'repeatable');
            const scalarOk = required.every(q => formValues[q.id]);
            const repeatableOk = visibleFields
                .filter(q => q.type === 'repeatable')
                .every(q => (formValues[q.id]?.length ?? 0) >= (q.min ?? 1));
            return scalarOk && repeatableOk && objet.trim().length >= 10 && !!notaireId;
        }
        return false;
    };

    const next = () => { if (canNext() && step < 3) setStep(s => s + 1); };
    const prev = () => {
        if (step === 1 && sousGroupe !== null) {
            setSousGroupe(null);
            setTypeActe(null);
        } else if (step > 0) {
            setStep(s => s - 1);
            if (step === 1) { setSousGroupe(null); setTypeActe(null); }
        }
    };

    const handleSubmit = () => {
        const typeActeId = findTypeActeId();
        if (!typeActeId) {
            setErrors({ type_acte_id: 'Type d\'acte introuvable. Vérifiez la configuration.' });
            return;
        }
        setSubmitting(true);
        router.post('/dossiers', {
            type_acte_id: typeActeId,
            objet: objet,
            notaire_id: notaireId,
            reviseur_id: reviseurId || undefined,
            formaliste_id: formalisteId || undefined,
            donnees: formValues,
        }, {
            onError: (errs) => { setErrors(errs); setSubmitting(false); },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <AppLayout breadcrumbs={[
            { label: 'Dossiers', href: '/dossiers' },
            { label: 'Nouveau dossier' }
        ]}>
            <Head title="Nouveau dossier — Ayelema" />

            <div className="p-6 max-w-[800px] mx-auto space-y-6">

                {/* En-tête */}
                <div>
                    <h1 className="font-serif text-display text-ink">Nouveau dossier</h1>
                    <p className="text-slate-500 text-sm mt-1">Suivez les étapes pour créer un nouveau dossier d'acte</p>
                </div>

                {/* Stepper wizard */}
                <div className="flex items-center gap-2">
                    {WIZARD_STEPS.map((s, i) => (
                        <React.Fragment key={s.id}>
                            <div className={cn(
                                'flex items-center gap-2 cursor-default',
                                i <= step && 'cursor-pointer'
                            )} onClick={() => i < step && setStep(i)}>
                                <div className={cn(
                                    'h-7 w-7 rounded-full flex items-center justify-center text-xs font-semibold transition-all',
                                    i < step && 'bg-success text-white',
                                    i === step && 'bg-ink text-white',
                                    i > step && 'bg-slate-100 text-slate-400'
                                )}>
                                    {i < step ? <Check className="h-3.5 w-3.5" /> : i + 1}
                                </div>
                                <span className={cn(
                                    'text-sm font-medium hidden sm:block',
                                    i === step && 'text-ink',
                                    i < step && 'text-success',
                                    i > step && 'text-slate-400'
                                )}>
                                    {s.label}
                                </span>
                            </div>
                            {i < WIZARD_STEPS.length - 1 && (
                                <div className={cn('flex-1 h-px', i < step ? 'bg-success' : 'bg-slate-200')} />
                            )}
                        </React.Fragment>
                    ))}
                </div>

                {/* Contenu */}
                <AnimatePresence mode="wait">
                    <motion.div
                        key={step === 1 ? `${step}-${sousGroupe ?? 'root'}` : step}
                        initial={{ opacity: 0, x: 12 }}
                        animate={{ opacity: 1, x: 0 }}
                        exit={{ opacity: 0, x: -12 }}
                        transition={{ duration: 0.18 }}
                    >

                        {/* Étape 1 : Catégorie */}
                        {step === 0 && (
                            <div className="space-y-3">
                                <h2 className="font-serif text-heading text-ink">Sélectionnez une catégorie</h2>
                                <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                                    {categories.map((cat) => {
                                        const Icon = cat.icon;
                                        const isSelected = categorie?.id === cat.id;
                                        return (
                                            <button
                                                key={cat.id}
                                                onClick={() => { setCategorie(cat); setSousGroupe(null); setTypeActe(null); }}
                                                className={cn(
                                                    'flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all text-center',
                                                    isSelected ? cat.activeColor : `bg-white ${cat.color}`
                                                )}
                                            >
                                                <div className={cn('h-10 w-10 rounded-xl flex items-center justify-center', isSelected ? 'bg-white/80' : 'bg-slate-50')}>
                                                    <Icon className={cn('h-5 w-5', cat.iconColor)} />
                                                </div>
                                                <span className="text-sm font-medium text-slate-800 leading-tight">{cat.label}</span>
                                                <span className="text-xs text-slate-400 leading-tight hidden sm:block">{cat.desc}</span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {/* Étape 2 : Type précis */}
                        {step === 1 && (
                            <>
                                {/* Société sans procédure choisie → 3 grandes cartes */}
                                {categorie?.id === 'societe' && sousGroupe === null && (
                                    <div className="space-y-3">
                                        <h2 className="font-serif text-heading text-ink">
                                            Procédure — <span className="text-slate-500">Société</span>
                                        </h2>
                                        <div className="space-y-3">
                                            {SOCIETE_GROUPES.map((groupe) => {
                                                const Icon = groupe.icon;
                                                const count = typesByCategorie.societe.filter(t => t.groupe === groupe.id).length;
                                                return (
                                                    <button
                                                        key={groupe.id}
                                                        onClick={() => setSousGroupe(groupe.id)}
                                                        className={cn(
                                                            'w-full flex items-center gap-4 p-5 rounded-xl border-2 text-left transition-all bg-white',
                                                            groupe.color
                                                        )}
                                                    >
                                                        <div className={cn('h-12 w-12 rounded-xl flex items-center justify-center shrink-0', groupe.iconBg)}>
                                                            <Icon className={cn('h-6 w-6', groupe.iconColor)} />
                                                        </div>
                                                        <div className="flex-1 min-w-0">
                                                            <div className="font-semibold text-slate-800 text-base">{groupe.label}</div>
                                                            <div className="text-sm text-slate-500 mt-0.5 leading-snug">{groupe.desc}</div>
                                                        </div>
                                                        {groupe.id === 'creation' && (
                                                            <Badge variant="secondary" className="shrink-0">{count} types</Badge>
                                                        )}
                                                        <ChevronRight className="h-5 w-5 text-slate-400 shrink-0" />
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}

                                {/* Société avec procédure choisie → breadcrumb + liste filtrée */}
                                {categorie?.id === 'societe' && sousGroupe !== null && (
                                    <div className="space-y-3">
                                        <button
                                            onClick={() => { setSousGroupe(null); setTypeActe(null); }}
                                            className="flex items-center gap-1 text-sm text-slate-500 hover:text-slate-700 transition-colors"
                                        >
                                            <ChevronLeft className="h-3.5 w-3.5" />
                                            Retour aux procédures
                                        </button>
                                        <h2 className="font-serif text-heading text-ink">
                                            Type d'acte — <span className="text-slate-500">{SOCIETE_GROUPES.find(g => g.id === sousGroupe)?.label}</span>
                                        </h2>
                                        <div className="space-y-2">
                                            {typesDisponibles.map((type) => {
                                                const isSelected = typeActe?.id === type.id;
                                                return (
                                                    <button
                                                        key={type.id + type.label}
                                                        onClick={() => {
                                                            setTypeActe(type);
                                                            const q = QUESTIONNAIRES[type.id] ?? [];
                                                            setFormValues(initRepeatableFields(q));
                                                        }}
                                                        className={cn(
                                                            'w-full flex items-start gap-4 p-4 rounded-xl border-2 text-left transition-all',
                                                            isSelected
                                                                ? 'border-ink bg-ink/5'
                                                                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                                                        )}
                                                    >
                                                        <div className={cn(
                                                            'h-5 w-5 rounded-full border-2 flex items-center justify-center mt-0.5 shrink-0',
                                                            isSelected ? 'border-ink bg-ink' : 'border-slate-300'
                                                        )}>
                                                            {isSelected && <div className="h-2 w-2 rounded-full bg-white" />}
                                                        </div>
                                                        <div className="flex-1 min-w-0">
                                                            <div className="font-medium text-slate-800">{type.label}</div>
                                                            {type.id === 'modification' && (
                                                                <div className="flex items-center gap-1.5 mt-1 text-xs text-warning-text">
                                                                    <AlertCircle className="h-3 w-3" />
                                                                    Fiche de modification obligatoire
                                                                </div>
                                                            )}
                                                            <div className="flex flex-wrap gap-1.5 mt-2">
                                                                {type.actes.map(a => (
                                                                    <span key={a} className="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">{a}</span>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}

                                {/* Autre catégorie → liste plate */}
                                {categorie?.id !== 'societe' && (
                                    <div className="space-y-3">
                                        <h2 className="font-serif text-heading text-ink">
                                            Type d'acte — <span className="text-slate-500">{categorieSelected?.label}</span>
                                        </h2>
                                        <div className="space-y-2">
                                            {typesDisponibles.map((type) => {
                                                const isSelected = typeActe?.id === type.id;
                                                return (
                                                    <button
                                                        key={type.id + type.label}
                                                        onClick={() => {
                                                            setTypeActe(type);
                                                            const q = QUESTIONNAIRES[type.id] ?? [];
                                                            setFormValues(initRepeatableFields(q));
                                                        }}
                                                        className={cn(
                                                            'w-full flex items-start gap-4 p-4 rounded-xl border-2 text-left transition-all',
                                                            isSelected
                                                                ? 'border-ink bg-ink/5'
                                                                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                                                        )}
                                                    >
                                                        <div className={cn(
                                                            'h-5 w-5 rounded-full border-2 flex items-center justify-center mt-0.5 shrink-0',
                                                            isSelected ? 'border-ink bg-ink' : 'border-slate-300'
                                                        )}>
                                                            {isSelected && <div className="h-2 w-2 rounded-full bg-white" />}
                                                        </div>
                                                        <div className="flex-1 min-w-0">
                                                            <div className="font-medium text-slate-800">{type.label}</div>
                                                            <div className="flex flex-wrap gap-1.5 mt-2">
                                                                {type.actes.map(a => (
                                                                    <span key={a} className="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">{a}</span>
                                                                ))}
                                                            </div>
                                                        </div>
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                )}
                            </>
                        )}

                        {/* Étape 3 : Questionnaire */}
                        {step === 2 && (
                            <div className="space-y-4">
                                <h2 className="font-serif text-heading text-ink">
                                    Questionnaire — <span className="text-slate-500">{typeSelected?.label}</span>
                                </h2>

                                {/* Champs obligatoires du dossier */}
                                <Card className="border-seal/30">
                                    <CardContent className="p-5 space-y-4">
                                        <div className="space-y-1.5">
                                            <Label htmlFor="objet">
                                                Objet du dossier <span className="text-danger">*</span>
                                            </Label>
                                            <textarea
                                                id="objet"
                                                rows={2}
                                                placeholder="Description synthétique du dossier (min. 10 caractères)…"
                                                value={objet}
                                                onChange={e => setObjet(e.target.value)}
                                                className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seal resize-none"
                                            />
                                            {errors.objet && <p className="text-xs text-danger">{errors.objet}</p>}
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor="notaire_id">
                                                Notaire en charge <span className="text-danger">*</span>
                                            </Label>
                                            <select
                                                id="notaire_id"
                                                value={notaireId}
                                                onChange={e => setNotaireId(e.target.value)}
                                                className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal"
                                            >
                                                <option value="">Choisir un notaire…</option>
                                                {(notaires ?? []).map(n => (
                                                    <option key={n.id} value={n.id}>{n.name}{n.initiales ? ` (${n.initiales})` : ''}</option>
                                                ))}
                                            </select>
                                            {errors.notaire_id && <p className="text-xs text-danger">{errors.notaire_id}</p>}
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-1.5">
                                                <Label htmlFor="reviseur_id">Réviseur</Label>
                                                <select
                                                    id="reviseur_id"
                                                    value={reviseurId}
                                                    onChange={e => setReviseurId(e.target.value)}
                                                    className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal"
                                                >
                                                    <option value="">Aucun</option>
                                                    {(reviseurs ?? []).map(r => (
                                                        <option key={r.id} value={r.id}>{r.name}</option>
                                                    ))}
                                                </select>
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label htmlFor="formaliste_id">Formaliste</Label>
                                                <select
                                                    id="formaliste_id"
                                                    value={formalisteId}
                                                    onChange={e => setFormalisteId(e.target.value)}
                                                    className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal"
                                                >
                                                    <option value="">Aucun</option>
                                                    {(formalistes ?? []).map(f => (
                                                        <option key={f.id} value={f.id}>{f.name}</option>
                                                    ))}
                                                </select>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Questionnaire spécifique */}
                                {visibleFields.length > 0 && (
                                    <Card>
                                        <CardContent className="p-5 space-y-4">
                                            {visibleFields.map((field, idx) => {
                                                const showSection = field.section && (idx === 0 || visibleFields[idx - 1]?.section !== field.section);
                                                const isCheckbox = field.type === 'checkbox' || field.type === 'checkbox_required';
                                                return (
                                                    <React.Fragment key={field.id}>
                                                        {showSection && (
                                                            <div className={cn('pb-1', idx > 0 && 'pt-4 border-t border-slate-100 mt-2')}>
                                                                <h4 className="text-xs font-semibold text-slate-500 uppercase tracking-wider">{field.section}</h4>
                                                            </div>
                                                        )}
                                                        {isCheckbox ? (
                                                            <div className="flex items-center gap-2.5 py-0.5">
                                                                <input
                                                                    type="checkbox"
                                                                    id={field.id}
                                                                    checked={!!formValues[field.id]}
                                                                    onChange={e => setFormValues(prev => ({ ...prev, [field.id]: e.target.checked }))}
                                                                    className="h-4 w-4 rounded border-slate-300 text-seal focus:ring-seal"
                                                                />
                                                                <label htmlFor={field.id} className="text-sm text-slate-700 cursor-pointer leading-snug">
                                                                    {field.label}
                                                                    {field.required && <span className="text-danger ml-1">*</span>}
                                                                </label>
                                                            </div>
                                                        ) : (
                                                            <div className="space-y-1.5">
                                                                <Label htmlFor={field.id}>
                                                                    {field.label}
                                                                    {field.required && <span className="text-danger ml-1">*</span>}
                                                                </Label>
                                                                {field.note && (
                                                                    <p className="text-xs text-warning-text flex items-center gap-1">
                                                                        <AlertCircle className="h-3 w-3" />
                                                                        {field.note}
                                                                    </p>
                                                                )}
                                                                {field.type === 'repeatable' ? (
                                                                    <RepeatableGroup
                                                                        fieldDef={field}
                                                                        value={formValues[field.id] ?? []}
                                                                        onChange={val => setFormValues(prev => ({ ...prev, [field.id]: val }))}
                                                                    />
                                                                ) : field.type === 'textarea' ? (
                                                                    <textarea
                                                                        id={field.id}
                                                                        rows={3}
                                                                        placeholder={field.placeholder}
                                                                        value={formValues[field.id] || ''}
                                                                        onChange={e => setFormValues(prev => ({ ...prev, [field.id]: e.target.value }))}
                                                                        className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seal resize-none"
                                                                    />
                                                                ) : field.type === 'select' ? (
                                                                    <select
                                                                        id={field.id}
                                                                        value={formValues[field.id] || ''}
                                                                        onChange={e => setFormValues(prev => ({ ...prev, [field.id]: e.target.value }))}
                                                                        className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal"
                                                                    >
                                                                        <option value="">— Choisir —</option>
                                                                        {(field.options ?? []).map(opt => (
                                                                            <option key={opt} value={opt}>{opt}</option>
                                                                        ))}
                                                                    </select>
                                                                ) : (
                                                                    <Input
                                                                        id={field.id}
                                                                        placeholder={field.placeholder}
                                                                        value={formValues[field.id] || ''}
                                                                        onChange={e => setFormValues(prev => ({ ...prev, [field.id]: e.target.value }))}
                                                                        className={cn(field.mono && 'font-ref')}
                                                                    />
                                                                )}
                                                            </div>
                                                        )}
                                                    </React.Fragment>
                                                );
                                            })}
                                        </CardContent>
                                    </Card>
                                )}
                            </div>
                        )}

                        {/* Étape 4 : Récapitulatif */}
                        {step === 3 && (
                            <div className="space-y-4">
                                <h2 className="font-serif text-heading text-ink">Récapitulatif</h2>
                                {errors.type_acte_id && (
                                    <div className="flex items-center gap-2 p-3 rounded-lg bg-danger-bg border border-red-200 text-danger-text text-sm">
                                        <AlertCircle className="h-4 w-4 shrink-0" />
                                        {errors.type_acte_id}
                                    </div>
                                )}

                                <Card className="border-seal/30 bg-seal/5">
                                    <CardContent className="p-5 space-y-4">
                                        <div className="flex items-center gap-3">
                                            {categorieSelected && (
                                                <div className="h-10 w-10 rounded-xl bg-white flex items-center justify-center shadow-sm">
                                                    <categorieSelected.icon className={cn('h-5 w-5', categorieSelected.iconColor)} />
                                                </div>
                                            )}
                                            <div>
                                                <div className="font-medium text-slate-800">{typeSelected?.label}</div>
                                                <div className="text-xs text-slate-500">{categorieSelected?.label}</div>
                                            </div>
                                        </div>

                                        {visibleFields.map(field => {
                                            const value = formValues[field.id];
                                            if (field.type === 'checkbox' && !value) return null;
                                            if (!value && value !== false && value !== true) return null;
                                            if (field.type === 'repeatable') {
                                                return (
                                                    <div key={field.id} className="pt-1">
                                                        <p className="text-xs font-semibold text-seal uppercase tracking-wide mb-1">{field.label}</p>
                                                        <RepeatableGroup fieldDef={field} value={value ?? []} onChange={() => {}} readOnly />
                                                    </div>
                                                );
                                            }
                                            if (field.type === 'checkbox') return null;
                                            return (
                                                <div key={field.id} className="flex gap-3 text-sm">
                                                    <span className="text-slate-500 min-w-[140px] shrink-0">{field.label}</span>
                                                    <span className={cn('text-slate-800 font-medium', field.mono && 'font-ref')}>
                                                        {typeof value === 'boolean' ? (value ? 'Oui' : 'Non') : value}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardContent className="p-5">
                                        <h3 className="font-semibold text-slate-700 mb-3 flex items-center gap-2">
                                            <FileText className="h-4 w-4 text-slate-400" />
                                            Actes à produire
                                        </h3>
                                        <div className="space-y-2">
                                            {typeSelected?.actes.map((acte, i) => (
                                                <div key={i} className="flex items-center gap-2 text-sm">
                                                    <div className="h-1.5 w-1.5 rounded-full bg-seal shrink-0" />
                                                    <span className={cn(
                                                        'text-slate-700',
                                                        acte.includes('obligatoire') && 'font-semibold text-warning-text'
                                                    )}>
                                                        {acte}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                    </motion.div>
                </AnimatePresence>

                {/* Navigation */}
                <div className="flex items-center justify-between pt-2">
                    <Button variant="outline" onClick={prev} disabled={step === 0} size="lg">
                        <ChevronLeft className="h-4 w-4" />
                        Précédent
                    </Button>

                    {step < 3 ? (
                        <Button size="lg" onClick={next} disabled={!canNext()}>
                            Suivant
                            <ChevronRight className="h-4 w-4" />
                        </Button>
                    ) : (
                        <Button variant="seal" size="lg" onClick={handleSubmit} disabled={submitting}>
                            <Check className="h-4 w-4" />
                            {submitting ? 'Création en cours…' : 'Créer le dossier'}
                        </Button>
                    )}
                </div>

            </div>
        </AppLayout>
    );
}
