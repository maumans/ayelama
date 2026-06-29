import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Building2, Home, Scale, Briefcase, Heart, GitBranch,
    FileText, Mail, ChevronLeft, ChevronRight, Check,
    Users, ArrowRight, AlertCircle
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
        { id: 'creation_sarl', label: 'Création de SARL', actes: ['Statuts', 'DNSV', "Déclaration sur l'honneur", 'Casier judiciaire', 'RCCM'] },
        { id: 'creation_sa', label: 'Création de SA', actes: ['Statuts', 'DNSV', "Procès-verbal AG constitutive", 'RCCM'] },
        { id: 'modification', label: 'Modification de statuts', actes: ['Fiche de modification (obligatoire)', "PV d'AGE", 'Statuts modifiés'] },
        { id: 'dissolution', label: 'Dissolution / Liquidation', actes: ["PV d'AGE de dissolution", 'Acte de clôture de liquidation'] },
    ],
    vente: [
        { id: 'vente_immeuble', label: "Vente d'immeuble", actes: ['Acte de vente', 'Titre foncier'] },
        { id: 'promesse_vente', label: 'Promesse de vente', actes: ['Promesse de vente', 'Clause résolutoire'] },
        { id: 'cession_parts', label: 'Cession de parts sociales', actes: ["Acte de cession de parts", 'PV de cession'] },
    ],
    hypotheque: [
        { id: 'hypotheque_conv', label: 'Hypothèque conventionnelle', actes: ["Contrat d'hypothèque", 'Lettre de crédit', 'Bordereau CF'] },
        { id: 'mainlevee', label: "Mainlevée d'hypothèque", actes: ['Acte de mainlevée'] },
    ],
    bail: [
        { id: 'bail_construire', label: 'Bail à construire', actes: ['Contrat de bail à construire'] },
        { id: 'bail_habitation', label: "Bail d'habitation", actes: ["Contrat de bail d'habitation"] },
        { id: 'bail_professionnel', label: 'Bail professionnel', actes: ['Contrat de bail professionnel'] },
    ],
    donation: [
        { id: 'donation_entre_vifs', label: 'Donation entre vifs', actes: ['Acte de donation', 'Acceptation du donataire'] },
    ],
    succession: [
        { id: 'partage_amiable', label: 'Partage amiable', actes: ['Acte de partage', "État des héritiers", "Attestation d'héritiers"] },
        { id: 'partage_judiciaire', label: 'Partage judiciaire', actes: ['Acte de partage judiciaire', 'Décision de justice'] },
    ],
    procuration: [
        { id: 'procuration_speciale', label: 'Procuration spéciale', actes: ['Acte de procuration'] },
    ],
    courrier: [
        { id: 'courrier_transmission', label: 'Courrier de transmission', actes: ['Lettre de transmission'] },
    ],
};

const questionnaires = {
    creation_sarl: [
        { id: 'denomination', label: 'Dénomination sociale', type: 'text', placeholder: 'Ex : Faya Distribution', required: true },
        { id: 'capital', label: 'Capital social (en GNF)', type: 'text', placeholder: '50 000 000', required: true, mono: true },
        { id: 'siege', label: 'Siège social', type: 'text', placeholder: 'Kaloum, Conakry', required: true },
        { id: 'objet', label: 'Objet social', type: 'textarea', placeholder: 'Commerce général, distribution…', required: true },
        { id: 'gerant', label: 'Nom du gérant', type: 'text', placeholder: 'M. Ibrahim Diallo', required: true },
        { id: 'date_debut', label: "Date de début d'activité", type: 'text', placeholder: '01/07/2026', required: false },
    ],
    vente_immeuble: [
        { id: 'vendeur', label: 'Vendeur', type: 'text', placeholder: 'Nom complet du vendeur', required: true },
        { id: 'acquereur', label: 'Acquéreur', type: 'text', placeholder: "Nom complet de l'acquéreur", required: true },
        { id: 'bien', label: 'Description du bien', type: 'textarea', placeholder: 'Immeuble sis à…', required: true },
        { id: 'prix', label: 'Prix de vente (GNF)', type: 'text', placeholder: '250 000 000', required: true, mono: true },
        { id: 'titre_foncier', label: 'Numéro titre foncier', type: 'text', placeholder: 'TF-2018-KAL-004521', required: true, mono: true },
    ],
    modification: [
        { id: 'denomination', label: 'Dénomination de la société', type: 'text', placeholder: 'Raison sociale exacte', required: true },
        { id: 'rccm', label: 'Numéro RCCM actuel', type: 'text', placeholder: 'GN-CON-2020-B-XXXX', required: true, mono: true },
        { id: 'objet_modification', label: 'Objet de la modification', type: 'textarea', placeholder: 'Décrire les changements apportés (capital, gérant, siège…)', required: true },
        { id: 'fiche_modification', label: 'Fiche de modification rédigée', type: 'checkbox_required', placeholder: '', required: true, note: 'Obligatoire — la procédure écrite l\'exige' },
    ],
};

function getQuestionnaire(typeId) {
    return questionnaires[typeId] || [
        { id: 'client', label: 'Nom du client / partie', type: 'text', placeholder: 'Nom complet', required: true },
        { id: 'details', label: 'Détails', type: 'textarea', placeholder: 'Informations complémentaires', required: false },
    ];
}

export default function DossierCreate() {
    const { typesActes, notaires, reviseurs, formalistes } = usePage().props;

    const [step, setStep] = useState(0);
    const [categorie, setCategorie] = useState(null);
    const [typeActe, setTypeActe] = useState(null);
    const [formValues, setFormValues] = useState({});
    const [objet, setObjet] = useState('');
    const [notaireId, setNotaireId] = useState('');
    const [reviseurId, setReviseurId] = useState('');
    const [formalisteId, setFormalisteId] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});

    // Find matching server type by category + label
    const findTypeActeId = () => {
        if (!categorie || !typeActe || !typesActes) return null;
        const catKey = categorie.id;
        const serverTypes = typesActes[catKey] ?? Object.values(typesActes).flat();
        const match = serverTypes.find(t =>
            t.label.toLowerCase().includes(typeActe.label.toLowerCase().split(' ')[0]) ||
            typeActe.label.toLowerCase().includes(t.label.toLowerCase().split(' ')[0])
        );
        return match?.id ?? serverTypes[0]?.id ?? null;
    };

    const stepName = WIZARD_STEPS[step].id;
    const typesDisponibles = categorie ? (typesByCategorie[categorie.id] || []) : [];
    const questionnaire = typeActe ? getQuestionnaire(typeActe.id) : [];
    const categorieSelected = categories.find(c => c.id === categorie?.id);
    const typeSelected = typeActe;

    const canNext = () => {
        if (step === 0) return !!categorie;
        if (step === 1) return !!typeActe;
        if (step === 2) {
            const required = questionnaire.filter(q => q.required);
            return required.every(q => formValues[q.id]) && objet.trim().length >= 10 && !!notaireId;
        }
        return false;
    };

    const next = () => { if (canNext() && step < 3) setStep(s => s + 1); };
    const prev = () => { if (step > 0) setStep(s => s - 1); };

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
                        key={step}
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
                                                onClick={() => setCategorie(cat)}
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
                            <div className="space-y-3">
                                <h2 className="font-serif text-heading text-ink">
                                    Type d'acte — <span className="text-slate-500">{categorieSelected?.label}</span>
                                </h2>
                                <div className="space-y-2">
                                    {typesDisponibles.map((type) => {
                                        const isSelected = typeActe?.id === type.id;
                                        return (
                                            <button
                                                key={type.id}
                                                onClick={() => setTypeActe(type)}
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
                                {questionnaire.length > 0 && (
                                    <Card>
                                        <CardContent className="p-5 space-y-4">
                                            {questionnaire.map((field) => (
                                                <div key={field.id} className="space-y-1.5">
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
                                                    {field.type === 'textarea' ? (
                                                        <textarea
                                                            id={field.id}
                                                            rows={3}
                                                            placeholder={field.placeholder}
                                                            value={formValues[field.id] || ''}
                                                            onChange={e => setFormValues(prev => ({ ...prev, [field.id]: e.target.value }))}
                                                            className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seal resize-none"
                                                        />
                                                    ) : field.type === 'checkbox_required' ? (
                                                        <div className="flex items-center gap-2">
                                                            <input
                                                                type="checkbox"
                                                                id={field.id}
                                                                checked={!!formValues[field.id]}
                                                                onChange={e => setFormValues(prev => ({ ...prev, [field.id]: e.target.checked }))}
                                                                className="h-4 w-4 rounded border-slate-300 text-seal focus:ring-seal"
                                                            />
                                                            <label htmlFor={field.id} className="text-sm text-slate-700 cursor-pointer">
                                                                Fiche de modification rédigée et jointe
                                                            </label>
                                                        </div>
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
                                            ))}
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

                                        {Object.entries(formValues).filter(([_, v]) => v).map(([key, value]) => {
                                            const field = questionnaire.find(q => q.id === key);
                                            return (
                                                <div key={key} className="flex gap-3 text-sm">
                                                    <span className="text-slate-500 min-w-[140px] shrink-0">{field?.label || key}</span>
                                                    <span className={cn('text-slate-800 font-medium', field?.mono && 'font-ref')}>
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
