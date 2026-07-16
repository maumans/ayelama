import React, { useState, useEffect } from 'react';
import { QUESTIONNAIRES, TYPE_ACTE_CODE_MAP, getVisibleFields } from '@/data/questionnaires';
import { RepeatableGroup } from '@/Components/ui/RepeatableGroup';
import { DateField } from '@/components/ui/date-field';
import { NumberField } from '@/components/ui/number-field';
import { PhoneField } from '@/components/ui/phone-field';
import { ClientPicker } from '@/Components/ui/client-picker';
import { ModalNouveauClient } from '@/Components/ModalNouveauClient';
import { mapClientToPrefixedFields, buildPartieFields, clientDisplayName } from '@/lib/clientFields';
import { groupFieldsBySection, buildPartiesPayload } from '@/lib/partiesPayload';
import { notifyValidationError } from '@/lib/toast';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Building2, Home, Scale, Briefcase, Heart, GitBranch,
    FileText, HeartHandshake, ScrollText, HandHeart, ChevronLeft, ChevronRight, Check,
    Users, ArrowRight, AlertCircle, PlusCircle, Edit2, Archive, Zap,
    UserCog, ClipboardCheck, Key, Landmark, Banknote, StickyNote, Trash2,
    CheckCircle2, Pencil, UserCircle2,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

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

// Métadonnées purement présentationnelles (icône/couleur/description courte) —
// la vraie liste des types sélectionnables vient de la prop `typesActes`
// (base de données), voir plus bas. Une catégorie n'est affichée que si elle a
// au moins un type actif configuré.
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
        desc: 'Déclaration ou partage de succession',
        color: 'border-rose-200 hover:border-rose-400 hover:bg-rose-50/50',
        activeColor: 'border-rose-400 bg-rose-50',
        iconColor: 'text-rose-600',
    },
    {
        id: 'mariage', label: 'Mariage', icon: HeartHandshake,
        desc: 'Contrat de mariage, convention de divorce',
        color: 'border-fuchsia-200 hover:border-fuchsia-400 hover:bg-fuchsia-50/50',
        activeColor: 'border-fuchsia-400 bg-fuchsia-50',
        iconColor: 'text-fuchsia-600',
    },
    {
        id: 'testament', label: 'Testament', icon: ScrollText,
        desc: 'Dispositions testamentaires',
        color: 'border-indigo-200 hover:border-indigo-400 hover:bg-indigo-50/50',
        activeColor: 'border-indigo-400 bg-indigo-50',
        iconColor: 'text-indigo-600',
    },
    {
        id: 'procuration', label: 'Procuration', icon: FileText,
        desc: 'Procuration spéciale ou générale',
        color: 'border-slate-200 hover:border-slate-400 hover:bg-slate-50/50',
        activeColor: 'border-slate-400 bg-slate-100',
        iconColor: 'text-slate-600',
    },
    {
        id: 'prise_en_charge', label: 'Prise en charge', icon: HandHeart,
        desc: "Prise en charge d'un mineur, d'un adulte ou de frais",
        color: 'border-teal-200 hover:border-teal-400 hover:bg-teal-50/50',
        activeColor: 'border-teal-400 bg-teal-50',
        iconColor: 'text-teal-600',
    },
];

// Particularité Société : les types reçus de la base sont répartis en 3
// procédures (Création/Modification/Dissolution) — seuls SOC-MOD et SOC-DIS
// sont des cas spéciaux, tout le reste (types actuels et futurs) est une
// création par défaut.
function societeGroupe(code) {
    if (code === 'SOC-MOD') return 'modification';
    if (code === 'SOC-DIS') return 'dissolution';
    return 'creation';
}

function getQuestionnaire(typeActe) {
    const key = TYPE_ACTE_CODE_MAP[typeActe?.code];
    return (key && QUESTIONNAIRES[key]) || [
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

const SECTION_ICON_RULES = [
    { test: /société|groupement/i,                                icon: Building2,       iconColor: 'text-blue-600',   iconBg: 'bg-blue-50' },
    { test: /associé|actionnaire|membre/i,                        icon: Users,           iconColor: 'text-blue-600',   iconBg: 'bg-blue-50' },
    { test: /gérant|administrat|direction|président|conseil/i,    icon: UserCog,         iconColor: 'text-indigo-600', iconBg: 'bg-indigo-50' },
    { test: /commissaire/i,                                       icon: ClipboardCheck,  iconColor: 'text-slate-600',  iconBg: 'bg-slate-100' },
    { test: /bailleur|locataire|preneur|bail/i,                   icon: Key,             iconColor: 'text-cyan-600',   iconBg: 'bg-cyan-50' },
    { test: /bien|terrain|local|vendeur|acquéreur|acheteur/i,     icon: Home,            iconColor: 'text-emerald-600', iconBg: 'bg-emerald-50' },
    { test: /banque|créancier|débiteur|emprunteur|hypoth/i,       icon: Landmark,        iconColor: 'text-violet-600', iconBg: 'bg-violet-50' },
    { test: /transaction/i,                                       icon: Banknote,        iconColor: 'text-amber-600',  iconBg: 'bg-amber-50' },
    { test: /liquidateur|dissolution/i,                           icon: Archive,         iconColor: 'text-slate-500',  iconBg: 'bg-slate-100' },
    { test: /modification/i,                                      icon: Edit2,           iconColor: 'text-amber-600',  iconBg: 'bg-amber-50' },
];

function getSectionMeta(name) {
    if (!name) return { icon: FileText, iconColor: 'text-slate-400', iconBg: 'bg-slate-100' };
    return SECTION_ICON_RULES.find(r => r.test.test(name)) ?? { icon: FileText, iconColor: 'text-slate-500', iconBg: 'bg-slate-100' };
}

function GroupHeader({ icon: Icon, iconColor, iconBg, children }) {
    return (
        <div className="flex items-center gap-2 mb-3">
            <span className={cn('flex h-6 w-6 items-center justify-center rounded-md shrink-0', iconBg)}>
                <Icon className={cn('h-3.5 w-3.5', iconColor)} />
            </span>
            <h4 className="text-xs font-semibold text-slate-600 uppercase tracking-wider">{children}</h4>
        </div>
    );
}

// ── Étape 3 (Récapitulatif) — composants de présentation ────────────────────

function RecapCard({ icon, iconColor = 'text-slate-500', iconBg = 'bg-slate-100', title, onEdit, children }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-white p-4">
            <div className="flex items-center justify-between mb-3">
                <GroupHeader icon={icon} iconColor={iconColor} iconBg={iconBg}>{title}</GroupHeader>
                {onEdit && (
                    <button
                        type="button"
                        onClick={onEdit}
                        className="flex items-center gap-1 text-xs text-slate-400 hover:text-seal transition-colors shrink-0"
                    >
                        <Pencil className="h-3 w-3" /> Modifier
                    </button>
                )}
            </div>
            {children}
        </div>
    );
}

function RecapField({ label, value, mono }) {
    return (
        <div>
            <dt className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">{label}</dt>
            <dd className={cn('text-sm text-slate-800 font-medium mt-0.5', mono && 'font-ref')}>{value}</dd>
        </div>
    );
}

function RecapPersonne({ person, role, color = 'bg-ink' }) {
    return (
        <div className="flex items-center gap-2">
            <Avatar className="h-8 w-8">
                <AvatarFallback className={cn('text-[10px] text-white', person ? color : 'bg-slate-300')}>
                    {person ? (person.initiales ?? person.name?.slice(0, 2)) : <UserCircle2 className="h-4 w-4" />}
                </AvatarFallback>
            </Avatar>
            <div>
                <div className={cn('text-sm font-medium', person ? 'text-slate-800' : 'text-slate-400 italic')}>
                    {person ? person.name : 'Non assigné'}
                </div>
                <div className="text-[10px] text-slate-400">{role}</div>
            </div>
        </div>
    );
}

export default function DossierCreate() {
    const { typesActes, notaires, reviseurs, formalistes, defauts } = usePage().props;

    const [step, setStep] = useState(0);
    const [categorie, setCategorie] = useState(null);
    const [sousGroupe, setSousGroupe] = useState(null);
    const [typeActe, setTypeActe] = useState(null);
    const [formValues, setFormValues] = useState({});
    const [clientLinks, setClientLinks] = useState({}); // { [clientRole]: clientObject }
    const [creatingClientForGroup, setCreatingClientForGroup] = useState(null);
    // Clients ajoutés en haut du formulaire (personnes physiques/morales créées ou choisies
    // dans le répertoire pour ce dossier). Certains reçoivent une qualité libre directement
    // (témoin, accompagnateur…), d'autres restent disponibles pour être réutilisés comme
    // gérant/associé/etc. via les sélecteurs de client des sections ci-dessous.
    const [dossierClients, setDossierClients] = useState([]); // [{ client, role }]
    const [creatingClientForDossierIndex, setCreatingClientForDossierIndex] = useState(null);
    const [objet, setObjet] = useState('');
    const [urgent, setUrgent] = useState(false);
    const [notes, setNotes] = useState('');
    // Pré-sélectionnés depuis Paramètres > Assignations (notaire/réviseur/formaliste par
    // défaut) — modifiable au cas par cas, voir décision correspondante dans le devbook.
    const [notaireId, setNotaireId] = useState(defauts?.notaire_id ? String(defauts.notaire_id) : '');
    const [reviseurId, setReviseurId] = useState(defauts?.reviseur_id ? String(defauts.reviseur_id) : '');
    const [formalisteId, setFormalisteId] = useState(defauts?.formaliste_id ? String(defauts.formaliste_id) : '');
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});

    // Remonte en haut du contenu défilable à chaque changement d'étape (ou de sous-groupe/type
    // d'acte à l'étape 2) — sans ça, le scroll reste où l'utilisateur l'a laissé sur l'étape
    // précédente et la nouvelle étape peut s'afficher en plein milieu, voire tout en bas.
    useEffect(() => {
        const scrollable = document.querySelector('main');
        if (scrollable) scrollable.scrollTop = 0;
    }, [step, sousGroupe, typeActe?.id]);

    // `typeActe` est directement la ligne TypeActe de la base (id, code, label,
    // description, modeles) — plus besoin de chercher son id serveur, il l'a déjà.
    const findTypeActeId = () => typeActe?.id ?? null;

    const wizardSteps = [
        { id: 'categorie', label: 'Catégorie' },
        { id: 'questionnaire', label: categorie ? `Détails — ${categorie.label}` : 'Détails' },
        { id: 'recapitulatif', label: 'Récapitulatif' },
    ];
    const typesActesCategorie = categorie ? (typesActes?.[categorie.id] ?? []) : [];
    const typesDisponibles = categorie?.id === 'societe' && sousGroupe
        ? typesActesCategorie.filter(t => societeGroupe(t.code) === sousGroupe)
        : typesActesCategorie;
    const questionnaire = typeActe ? getQuestionnaire(typeActe) : [];
    // Champs filtrés selon les valeurs actuelles (showIf)
    const visibleFields = getVisibleFields(questionnaire, formValues);
    const categorieSelected = categories.find(c => c.id === categorie?.id);
    const typeSelected = typeActe;

    const selectType = (type) => {
        setTypeActe(type);
        const key = TYPE_ACTE_CODE_MAP[type.code];
        const q = (key && QUESTIONNAIRES[key]) || [];
        setFormValues(initRepeatableFields(q));
        setClientLinks({});
    };

    const selectTypeById = (id) => {
        const type = typesDisponibles.find(t => String(t.id) === id);
        if (type) selectType(type);
        else setTypeActe(null);
    };

    // Procédure Société (Création/Modification/Dissolution) : Modification et
    // Dissolution n'ont chacune qu'un seul type possible — on le sélectionne
    // directement, pas besoin d'un second select pour un choix unique.
    const selectProcedure = (groupe) => {
        setSousGroupe(groupe || null);
        setTypeActe(null);
        if (!groupe) return;
        const filtered = typesActesCategorie.filter(t => societeGroupe(t.code) === groupe);
        if (filtered.length === 1) selectType(filtered[0]);
    };

    const applyClientToSection = (group, client) => {
        const prefix = group.fields[0].id.split('.')[0];
        const fieldIds = group.fields.map(f => f.id);
        const mapped = mapClientToPrefixedFields(client, prefix, fieldIds);
        setFormValues(prev => ({ ...prev, ...mapped }));
        setClientLinks(prev => ({ ...prev, [group.clientRole]: client }));
    };

    const unlinkClientFromSection = (role) => {
        setClientLinks(prev => {
            const next = { ...prev };
            delete next[role];
            return next;
        });
    };

    // "Clients du dossier" : ajoutés en haut du formulaire, avant de savoir précisément
    // à quel rôle ils correspondront. Une qualité libre est optionnelle (ex. accompagnateur,
    // témoin) — sans qualité, le client reste simplement disponible à la réutilisation.
    const addDossierClient = () => setDossierClients(prev => [...prev, { client: null, role: '' }]);
    const removeDossierClient = (i) => setDossierClients(prev => prev.filter((_, idx) => idx !== i));
    const setDossierClientClient = (i, client) => setDossierClients(prev => prev.map((p, idx) => idx === i ? { ...p, client } : p));
    const setDossierClientRole = (i, role) => setDossierClients(prev => prev.map((p, idx) => idx === i ? { ...p, role } : p));

    // Pool dédupliqué des clients déjà ajoutés au dossier — proposé en sélection rapide
    // dans chaque section liée à un rôle (gérant, associé…) pour réutilisation immédiate.
    const poolClients = Array.from(
        new Map(dossierClients.filter(p => p.client).map(p => [p.client.id, p.client])).values()
    );

    // Un client créé/choisi directement depuis une section de rôle (gérant, associé…)
    // rejoint aussi le pool du dossier, pour être réutilisable ailleurs sans le rechercher.
    const addClientToPool = (client) => {
        setDossierClients(prev => prev.some(p => p.client?.id === client.id)
            ? prev
            : [...prev, { client, role: '' }]);
    };

    const canNext = () => {
        if (step === 0) return !!categorie;
        if (step === 1) {
            if (!typeActe) return false;
            const required = visibleFields.filter(q => q.required && q.type !== 'repeatable');
            const scalarOk = required.every(q => formValues[q.id]);
            const repeatableOk = visibleFields
                .filter(q => q.type === 'repeatable')
                .every(q => (formValues[q.id]?.length ?? 0) >= (q.min ?? 1));
            return scalarOk && repeatableOk && objet.trim().length >= 10 && !!notaireId;
        }
        return false;
    };

    const next = () => { if (canNext() && step < 2) setStep(s => s + 1); };
    const prev = () => { if (step > 0) setStep(s => s - 1); };

    const handleSubmit = () => {
        const typeActeId = findTypeActeId();
        if (!typeActeId) {
            setErrors({ type_acte_id: 'Type d\'acte introuvable. Vérifiez la configuration.' });
            return;
        }
        const autresPartiesPayload = dossierClients
            .filter(p => p.client && p.role.trim())
            .map(p => ({ ...buildPartieFields(p.client, {}, ''), role: p.role.trim(), client_id: p.client.id }));
        setSubmitting(true);
        router.post('/dossiers', {
            type_acte_id: typeActeId,
            objet: objet,
            urgent: urgent,
            notes: notes || undefined,
            notaire_id: notaireId,
            reviseur_id: reviseurId || undefined,
            formaliste_id: formalisteId || undefined,
            donnees: formValues,
            parties: [...buildPartiesPayload(questionnaire, formValues, clientLinks), ...autresPartiesPayload],
        }, {
            onError: (errs) => { setErrors(errs); setSubmitting(false); notifyValidationError(errs); },
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
                    {wizardSteps.map((s, i) => (
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
                            {i < wizardSteps.length - 1 && (
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
                                    {categories.filter(cat => (typesActes?.[cat.id]?.length ?? 0) > 0).map((cat) => {
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

                        {/* Étape 2 : Type précis + reste du formulaire, sur un seul écran */}
                        {step === 1 && (
                            <>
                                <div className="space-y-3">
                                    <h2 className="font-serif text-heading text-ink">
                                        Type d'acte — <span className="text-slate-500">{categorieSelected?.label}</span>
                                    </h2>
                                    <div className="rounded-lg border border-slate-200 bg-white p-4 space-y-4">
                                        <div className={cn('grid gap-4', categorie?.id === 'societe' && 'sm:grid-cols-2')}>
                                            {categorie?.id === 'societe' && (
                                                <div className="space-y-1.5">
                                                    <Label htmlFor="procedure">Procédure</Label>
                                                    <select
                                                        id="procedure"
                                                        value={sousGroupe ?? ''}
                                                        onChange={e => selectProcedure(e.target.value)}
                                                        className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal"
                                                    >
                                                        <option value="">— Choisir —</option>
                                                        {SOCIETE_GROUPES.map(g => (
                                                            <option key={g.id} value={g.id}>{g.label}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                            )}

                                            {(categorie?.id !== 'societe' || sousGroupe) && typesDisponibles.length > 1 && (
                                                <div className="space-y-1.5">
                                                    <Label htmlFor="type_acte">{categorie?.id === 'societe' ? 'Type de société' : "Type d'acte"}</Label>
                                                    <select
                                                        id="type_acte"
                                                        value={typeActe?.id ?? ''}
                                                        onChange={e => selectTypeById(e.target.value)}
                                                        className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal"
                                                    >
                                                        <option value="">— Choisir —</option>
                                                        {typesDisponibles.map(t => (
                                                            <option key={t.id} value={t.id}>{t.label}</option>
                                                        ))}
                                                    </select>
                                                </div>
                                            )}
                                        </div>

                                        {typeActe && (
                                            <div className="pt-3 border-t border-slate-100 space-y-2">
                                                {typeActe.code === 'SOC-MOD' && (
                                                    <div className="flex items-center gap-1.5 text-xs text-warning-text">
                                                        <AlertCircle className="h-3 w-3" />
                                                        Fiche de modification obligatoire
                                                    </div>
                                                )}
                                                {typeActe.description && (
                                                    <p className="text-xs text-slate-500">{typeActe.description}</p>
                                                )}
                                                {typeActe.modeles.length > 0 && (
                                                    <div className="flex flex-wrap gap-1.5">
                                                        {typeActe.modeles.map(m => (
                                                            <span key={m} className="text-xs bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full">{m}</span>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                </div>

                                {/* Le reste du formulaire n'apparaît qu'une fois le type d'acte choisi */}
                                {typeActe && (
                                <div className="space-y-4 mt-4">
                                <h2 className="font-serif text-heading text-ink">
                                    Détails — <span className="text-slate-500">{typeSelected?.label}</span>
                                </h2>

                                {/* Clients du dossier — créés/choisis en premier, réutilisables ensuite pour un rôle précis */}
                                <Card>
                                    <CardContent className="p-5 space-y-3">
                                        <GroupHeader icon={Users} iconColor="text-indigo-600" iconBg="bg-indigo-50">
                                            Clients du dossier
                                        </GroupHeader>
                                        <p className="text-xs text-slate-400 -mt-2">
                                            Ajoutez ici les clients (personnes physiques ou morales) concernés par ce dossier.
                                            Vous pourrez ensuite les réutiliser directement comme gérant, associé, vendeur… dans
                                            les sections ci-dessous. Ne renseignez une qualité que si la personne n'a pas de rôle
                                            précis dans l'acte (témoin, accompagnateur…).
                                        </p>
                                        {dossierClients.map((p, i) => (
                                            <div key={i} className="flex items-start gap-2 rounded-lg border border-slate-200 p-3">
                                                <div className="flex-1 space-y-2">
                                                    <ClientPicker
                                                        placeholder="Rechercher un client existant…"
                                                        linked={p.client}
                                                        onSelect={(client) => setDossierClientClient(i, client)}
                                                        onUnlink={() => setDossierClientClient(i, null)}
                                                        onCreateNew={() => setCreatingClientForDossierIndex(i)}
                                                    />
                                                    <Input
                                                        placeholder="Qualité si sans rôle précis (ex : témoin, accompagnateur…) — optionnel"
                                                        value={p.role}
                                                        onChange={e => setDossierClientRole(i, e.target.value)}
                                                    />
                                                </div>
                                                <Button variant="ghost" size="icon-sm" className="text-slate-300 hover:text-danger mt-0.5"
                                                    onClick={() => removeDossierClient(i)} title="Retirer">
                                                    <Trash2 className="h-3.5 w-3.5" />
                                                </Button>
                                            </div>
                                        ))}
                                        <Button variant="outline" size="sm" className="h-8 gap-1.5" onClick={addDossierClient}>
                                            <PlusCircle className="h-3.5 w-3.5" /> Ajouter un client
                                        </Button>
                                    </CardContent>
                                </Card>

                                {/* Champs obligatoires du dossier, organisés par sections */}
                                <Card className="border-seal/30">
                                    <CardContent className="p-5 space-y-4">

                                        <div className="rounded-lg border border-slate-200 bg-white p-4">
                                            <GroupHeader icon={FileText} iconColor="text-blue-600" iconBg="bg-blue-50">Dossier</GroupHeader>
                                            <div className="space-y-4">
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
                                                <label htmlFor="urgent" className="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer w-fit">
                                                    <Checkbox
                                                        id="urgent"
                                                        checked={urgent}
                                                        onCheckedChange={(checked) => setUrgent(checked === true)}
                                                    />
                                                    <span className="flex items-center gap-1.5">
                                                        <Zap className="h-3.5 w-3.5 text-warning-text" />
                                                        Dossier urgent
                                                    </span>
                                                </label>
                                            </div>
                                        </div>

                                        <div className="rounded-lg border border-slate-200 bg-white p-4">
                                            <GroupHeader icon={Users} iconColor="text-indigo-600" iconBg="bg-indigo-50">Intervenants</GroupHeader>
                                            <div className="space-y-4">
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
                                            </div>
                                        </div>

                                        <div className="rounded-lg border border-slate-200 bg-white p-4">
                                            <GroupHeader icon={StickyNote} iconColor="text-amber-600" iconBg="bg-amber-50">Notes</GroupHeader>
                                            <div className="space-y-1.5">
                                                <Label htmlFor="notes">Notes initiales <span className="text-slate-400 text-xs">(optionnel)</span></Label>
                                                <textarea
                                                    id="notes"
                                                    rows={3}
                                                    placeholder="Contexte, remarques ou instructions particulières pour ce dossier…"
                                                    value={notes}
                                                    onChange={e => setNotes(e.target.value)}
                                                    className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seal resize-none"
                                                />
                                                {errors.notes && <p className="text-xs text-danger">{errors.notes}</p>}
                                            </div>
                                        </div>

                                    </CardContent>
                                </Card>

                                {/* Questionnaire spécifique, groupé par section (icône + grille 2 colonnes) */}
                                {visibleFields.length > 0 && (
                                    <Card>
                                        <CardContent className="p-5 space-y-4">
                                            {groupFieldsBySection(visibleFields).map((group, gi) => {
                                                const meta = getSectionMeta(group.name);
                                                const Icon = meta.icon;
                                                return (
                                                    <div key={gi} className="rounded-lg border border-slate-200 bg-white p-4">
                                                        {group.name && (
                                                            <GroupHeader icon={Icon} iconColor={meta.iconColor} iconBg={meta.iconBg}>
                                                                {group.name}
                                                            </GroupHeader>
                                                        )}

                                                        {group.clientRole && (
                                                            <div className="mb-3">
                                                                <ClientPicker
                                                                    placeholder={`Rechercher un client existant (${group.name})…`}
                                                                    linked={clientLinks[group.clientRole] ?? null}
                                                                    onSelect={(client) => applyClientToSection(group, client)}
                                                                    onUnlink={() => unlinkClientFromSection(group.clientRole)}
                                                                    onCreateNew={() => setCreatingClientForGroup(group)}
                                                                    poolClients={poolClients}
                                                                />
                                                            </div>
                                                        )}

                                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                                                            {group.fields.map(field => {
                                                                const isCheckbox = field.type === 'checkbox' || field.type === 'checkbox_required';
                                                                const isFullWidth = isCheckbox || field.type === 'textarea' || field.type === 'repeatable';
                                                                return (
                                                                    <div
                                                                        key={field.id}
                                                                        className={cn(
                                                                            isFullWidth && 'sm:col-span-2',
                                                                            field.showIf && 'pl-3 border-l-2 border-seal/30'
                                                                        )}
                                                                    >
                                                                        {isCheckbox ? (
                                                                            <div className="py-0.5">
                                                                                <div className="flex items-center gap-2.5">
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
                                                                                {field.note && (
                                                                                    <p className="text-xs text-warning-text flex items-center gap-1 mt-1 ml-6">
                                                                                        <AlertCircle className="h-3 w-3" />
                                                                                        {field.note}
                                                                                    </p>
                                                                                )}
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
                                                                                        poolClients={poolClients}
                                                                                        onClientCreated={addClientToPool}
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
                                                                                ) : field.type === 'date' ? (
                                                                                    <DateField
                                                                                        id={field.id}
                                                                                        value={formValues[field.id] || ''}
                                                                                        onValueChange={val => setFormValues(prev => ({ ...prev, [field.id]: val }))}
                                                                                    />
                                                                                ) : field.type === 'number' ? (
                                                                                    <NumberField
                                                                                        id={field.id}
                                                                                        decimals={field.decimals ?? 0}
                                                                                        placeholder={field.placeholder}
                                                                                        value={formValues[field.id] || ''}
                                                                                        onValueChange={val => setFormValues(prev => ({ ...prev, [field.id]: val }))}
                                                                                        className={cn(field.mono && 'font-ref')}
                                                                                    />
                                                                                ) : field.type === 'tel' ? (
                                                                                    <PhoneField
                                                                                        id={field.id}
                                                                                        placeholder={field.placeholder}
                                                                                        value={formValues[field.id] || ''}
                                                                                        onValueChange={val => setFormValues(prev => ({ ...prev, [field.id]: val }))}
                                                                                    />
                                                                                ) : (
                                                                                    <Input
                                                                                        id={field.id}
                                                                                        type={field.type === 'email' ? 'email' : 'text'}
                                                                                        placeholder={field.placeholder}
                                                                                        value={formValues[field.id] || ''}
                                                                                        onChange={e => setFormValues(prev => ({ ...prev, [field.id]: e.target.value }))}
                                                                                        className={cn(field.mono && 'font-ref')}
                                                                                    />
                                                                                )}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </CardContent>
                                    </Card>
                                )}

                                </div>
                                )}
                            </>
                        )}

                        {/* Étape 3 : Récapitulatif */}
                        {step === 2 && (() => {
                            const notaireSelected = (notaires ?? []).find(n => String(n.id) === String(notaireId));
                            const reviseurSelected = (reviseurs ?? []).find(r => String(r.id) === String(reviseurId));
                            const formalisteSelected = (formalistes ?? []).find(f => String(f.id) === String(formalisteId));
                            const dossierClientsAjoutes = dossierClients.filter(p => p.client);
                            const autresValides = dossierClientsAjoutes.filter(p => p.role.trim());
                            const dossierClientsDisponibles = dossierClientsAjoutes.filter(p => !p.role.trim());
                            const sections = groupFieldsBySection(visibleFields)
                                .map(group => ({
                                    ...group,
                                    fields: group.fields.filter(f => {
                                        const v = formValues[f.id];
                                        if (f.type === 'repeatable') return (v?.length ?? 0) > 0;
                                        if (f.type === 'checkbox') return !!v;
                                        return !!v || v === false;
                                    }),
                                }))
                                .filter(group => group.fields.length > 0);

                            return (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <h2 className="font-serif text-heading text-ink">Récapitulatif</h2>
                                    {!errors.type_acte_id && (
                                        <span className="flex items-center gap-1.5 text-xs text-success font-medium">
                                            <CheckCircle2 className="h-3.5 w-3.5" /> Prêt à créer — vérifiez les informations
                                        </span>
                                    )}
                                </div>
                                {errors.type_acte_id && (
                                    <div className="flex items-center gap-2 p-3 rounded-lg bg-danger-bg border border-red-200 text-danger-text text-sm">
                                        <AlertCircle className="h-4 w-4 shrink-0" />
                                        {errors.type_acte_id}
                                    </div>
                                )}

                                {/* Bandeau hero */}
                                <div className="rounded-xl border border-seal/30 bg-seal/5 p-5 flex items-center justify-between gap-3">
                                    <div className="flex items-center gap-3 min-w-0">
                                        {categorieSelected && (
                                            <div className="h-12 w-12 rounded-xl bg-white flex items-center justify-center shadow-sm shrink-0">
                                                <categorieSelected.icon className={cn('h-6 w-6', categorieSelected.iconColor)} />
                                            </div>
                                        )}
                                        <div className="min-w-0">
                                            <div className="font-serif text-lg text-ink truncate">{typeSelected?.label}</div>
                                            <div className="flex items-center gap-1.5 mt-1 flex-wrap">
                                                <span className={cn('text-[11px] font-medium px-2 py-0.5 rounded-full bg-white', categorieSelected?.iconColor)}>
                                                    {categorieSelected?.label}
                                                </span>
                                                {urgent && (
                                                    <Badge variant="warning" className="gap-1"><Zap className="h-2.5 w-2.5" /> Urgent</Badge>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => setStep(0)}
                                        className="flex items-center gap-1 text-xs text-slate-500 hover:text-seal transition-colors shrink-0"
                                    >
                                        <Pencil className="h-3 w-3" /> Modifier
                                    </button>
                                </div>

                                {/* Dossier */}
                                <RecapCard icon={FileText} iconColor="text-blue-600" iconBg="bg-blue-50" title="Dossier" onEdit={() => setStep(1)}>
                                    <div className="space-y-3">
                                        <RecapField label="Objet" value={objet || '—'} />
                                        {notes && <RecapField label="Notes" value={notes} />}
                                    </div>
                                </RecapCard>

                                {/* Intervenants */}
                                <RecapCard icon={Users} iconColor="text-indigo-600" iconBg="bg-indigo-50" title="Intervenants" onEdit={() => setStep(1)}>
                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <RecapPersonne person={notaireSelected} role="Notaire" color="bg-stone-600" />
                                        <RecapPersonne person={reviseurSelected} role="Réviseur" color="bg-seal" />
                                        <RecapPersonne person={formalisteSelected} role="Formaliste" color="bg-ink" />
                                    </div>
                                </RecapCard>

                                {/* Sections du questionnaire */}
                                {sections.map((group, gi) => {
                                    const meta = getSectionMeta(group.name);
                                    const repeatables = group.fields.filter(f => f.type === 'repeatable');
                                    const simples = group.fields.filter(f => f.type !== 'repeatable');
                                    return (
                                        <RecapCard
                                            key={gi}
                                            icon={meta.icon}
                                            iconColor={meta.iconColor}
                                            iconBg={meta.iconBg}
                                            title={group.name || 'Détails'}
                                            onEdit={() => setStep(1)}
                                        >
                                            <div className="space-y-4">
                                                {simples.length > 0 && (
                                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3">
                                                        {simples.map(field => {
                                                            const value = formValues[field.id];
                                                            return (
                                                                <RecapField
                                                                    key={field.id}
                                                                    label={field.label}
                                                                    mono={field.mono}
                                                                    value={typeof value === 'boolean' ? (value ? 'Oui' : 'Non') : value}
                                                                />
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                                {repeatables.map(field => (
                                                    <div key={field.id}>
                                                        <p className="text-[10px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">{field.label}</p>
                                                        <RepeatableGroup fieldDef={field} value={formValues[field.id] ?? []} onChange={() => {}} readOnly />
                                                    </div>
                                                ))}
                                            </div>
                                        </RecapCard>
                                    );
                                })}

                                {/* Clients du dossier */}
                                {dossierClientsAjoutes.length > 0 && (
                                    <RecapCard icon={Users} iconColor="text-indigo-600" iconBg="bg-indigo-50" title="Clients du dossier" onEdit={() => setStep(1)}>
                                        <div className="flex flex-wrap gap-2">
                                            {autresValides.map((p, i) => (
                                                <span key={`role-${i}`} className="inline-flex items-center gap-1.5 text-xs bg-slate-50 border border-slate-200 rounded-full px-3 py-1">
                                                    <span className="font-medium text-slate-700">{clientDisplayName(p.client)}</span>
                                                    <span className="text-slate-400">· {p.role}</span>
                                                </span>
                                            ))}
                                            {dossierClientsDisponibles.map((p, i) => (
                                                <span key={`dispo-${i}`} className="inline-flex items-center gap-1.5 text-xs bg-seal-light border border-seal/30 rounded-full px-3 py-1">
                                                    <span className="font-medium text-slate-700">{clientDisplayName(p.client)}</span>
                                                    <span className="text-slate-400">· réutilisé ci-dessus</span>
                                                </span>
                                            ))}
                                        </div>
                                    </RecapCard>
                                )}

                                {/* Actes à produire */}
                                <RecapCard icon={ClipboardCheck} iconColor="text-seal" iconBg="bg-seal-light" title="Actes à produire">
                                    {(typeSelected?.modeles?.length ?? 0) === 0 ? (
                                        <p className="text-xs text-slate-400">Aucun modèle actif configuré pour ce type d'acte.</p>
                                    ) : (
                                        <div className="flex flex-wrap gap-1.5">
                                            {typeSelected.modeles.map((nom, i) => (
                                                <span key={i} className="text-xs bg-slate-100 text-slate-600 px-2.5 py-1 rounded-full">{nom}</span>
                                            ))}
                                        </div>
                                    )}
                                </RecapCard>
                            </div>
                            );
                        })()}

                    </motion.div>
                </AnimatePresence>

                {/* Navigation */}
                <div className="flex items-center justify-between pt-2">
                    <Button variant="outline" onClick={prev} disabled={step === 0} size="lg">
                        <ChevronLeft className="h-4 w-4" />
                        Précédent
                    </Button>

                    {step < 2 ? (
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

            <ModalNouveauClient
                open={creatingClientForGroup !== null}
                onClose={() => setCreatingClientForGroup(null)}
                onCreated={(client) => {
                    applyClientToSection(creatingClientForGroup, client);
                    addClientToPool(client);
                    setCreatingClientForGroup(null);
                }}
            />

            <ModalNouveauClient
                open={creatingClientForDossierIndex !== null}
                onClose={() => setCreatingClientForDossierIndex(null)}
                onCreated={(client) => {
                    setDossierClientClient(creatingClientForDossierIndex, client);
                    setCreatingClientForDossierIndex(null);
                }}
            />
        </AppLayout>
    );
}
