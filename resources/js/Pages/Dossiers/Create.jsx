import React, { useState, useEffect, useRef } from 'react';
import { QUESTIONNAIRES, TYPE_ACTE_CODE_MAP, getVisibleFields, purgerChampsInvisibles } from '@/data/questionnaires';
import { RepeatableGroup } from '@/Components/ui/RepeatableGroup';
import { ClientPicker } from '@/Components/ui/client-picker';
import { ClientRoleSection } from '@/Components/ui/client-role-section';
import { ChoixMultiple } from '@/Components/ui/choix-multiple';
import { tableExclusionsModification } from '@/lib/exclusionsChoix';
import { ModalNouveauClient } from '@/Components/ModalNouveauClient';
import { PiecesConstitutivesCard } from '@/Components/Societes/PiecesConstitutivesCard';
import { ChoixSociete } from '@/Components/Societes/ChoixSociete';
import { mapClientToPrefixedFields, buildPartieFields, clientDisplayName, estChampIdentite } from '@/lib/clientFields';
import { mapSocieteToQuestionnaire, estChampSociete, ficheRenseigneChamp, societeDisplayName } from '@/lib/societeFields';
import { groupFieldsBySection, buildPartiesPayload } from '@/lib/partiesPayload';
import { construireFormDataBrouillon, libelleBrouillon, compterPieces } from '@/lib/brouillonDossier';
import { allerAuBlocant, ancreSection, blocantsEtape, compterParSection, motifsParChamp, OBJET_LONGUEUR_MIN } from '@/lib/blocantsEtape';
import { ChampQuestionnaire, classesChamp } from '@/Components/Questionnaire/ChampQuestionnaire';
import { BadgeSection, BlocantsPanel, CompteurBlocants } from '@/Components/Dossiers/BlocantsPanel';
import { notifyValidationError, toast } from '@/lib/toast';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import axios from 'axios';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { motion, AnimatePresence } from 'framer-motion';
import {
    Building2, Home, Scale, Briefcase, Heart, GitBranch,
    FileText, HeartHandshake, ScrollText, HandHeart, ChevronLeft, ChevronRight, Check,
    Users, ArrowRight, AlertCircle, PlusCircle, Edit2, Archive, Zap,
    UserCog, ClipboardCheck, Key, Landmark, Banknote, StickyNote, Trash2,
    CheckCircle2, Pencil, UserCircle2, X, Save, FileClock, Paperclip
} from 'lucide-react';
import { PieceStagedRow } from '@/Components/ui/PieceStagedRow';
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

function GroupHeader({ icon: Icon, iconColor, iconBg, children, badge = null }) {
    return (
        <div className="flex items-center gap-2 mb-3">
            <span className={cn('flex h-6 w-6 items-center justify-center rounded-md shrink-0', iconBg)}>
                <Icon className={cn('h-3.5 w-3.5', iconColor)} />
            </span>
            <h4 className="text-xs font-semibold text-slate-600 uppercase tracking-wider">{children}</h4>
            {badge}
        </div>
    );
}

/**
 * Conséquences des modifications cochées : impact statutaire, actes qui seront produits,
 * droits d'enregistrement.
 *
 * Répond au reproche que le formulaire « s'arrêtait au formulaire » : les règles 8 à 11 du CR
 * de juillet 2026 étaient calculées côté serveur (TypeModificationStatutaire) mais ne
 * s'affichaient nulle part — on ne découvrait les documents et les coûts qu'après création.
 * Toutes les données viennent de l'enum via la prop `typesModification` : rien n'est
 * redéclaré en JavaScript.
 */
function ConsequencesModification({ typesModification, selection, valeurPartsCedees }) {
    const choisis = (typesModification ?? []).filter(t => selection.includes(t.label));

    if (choisis.length === 0) {
        return (
            <div className="rounded-md border border-slate-200 bg-slate-50/70 p-3 text-xs text-slate-500">
                Cochez au moins une modification : elle détermine les actes à produire, les formalités
                à engager et les droits d'enregistrement à percevoir.
            </div>
        );
    }

    // Sélection contradictoire (seulement héritée d'un dossier ou d'un brouillon antérieur au
    // garde-fou : les cases s'excluent désormais à la saisie). Annoncer des actes et des coûts
    // pour une combinaison que le serveur refusera serait trompeur — on s'arrête là.
    const conflit = choisis.find(t => (t.incompatibles ?? []).some(v => choisis.some(c => c.valeur === v)));
    if (conflit) {
        return (
            <div className="rounded-md border border-red-200 bg-danger-bg p-3 text-xs text-danger-text">
                Deux modifications incompatibles sont sélectionnées : ni les actes ni les droits ne
                peuvent être déterminés tant que le conflit persiste. Décochez l'une des deux
                ci-dessus.
            </div>
        );
    }

    // Union des documents — un seul procès-verbal quel que soit le nombre de résolutions.
    const documents = {};
    choisis.forEach(t => Object.assign(documents, t.documentsRequis));

    const droits = [];
    if (Object.keys(documents).includes('statuts_maj')) droits.push(['Enregistrement des statuts mis à jour', 500_000]);
    if (Object.keys(documents).includes('pv_modification')) droits.push(['Enregistrement du procès-verbal', 100_000]);
    if (choisis.some(t => t.exigeDnsv)) droits.push(['Enregistrement DNSV', 100_000]);
    if (choisis.some(t => t.impacteRccm)) droits.push(['Tribunal de Commerce (RCCM)', 180_000]);

    const parts = Number(valeurPartsCedees) || 0;
    const cession = choisis.some(t => t.exigeValeurParts);
    if (cession) droits.push(['Droit de cession — 2 % des parts cédées', parts > 0 ? Math.round(parts * 0.02) : null]);

    const total = droits.every(([, m]) => m !== null)
        ? droits.reduce((s, [, m]) => s + m, 0)
        : null;

    const statuts = choisis.filter(t => t.impacteStatuts).length;

    return (
        <div className="space-y-3 rounded-md border border-seal/30 bg-seal-light/50 p-3">
            <div>
                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">Impact</p>
                <p className="mt-0.5 text-xs text-slate-700">
                    {statuts > 0
                        ? 'Statuts à mettre à jour et enregistrement au RCCM.'
                        : 'RCCM seulement — les statuts ne sont pas modifiés.'}
                </p>
            </div>

            <div>
                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    Actes qui seront produits
                </p>
                <div className="mt-1 flex flex-wrap gap-1.5">
                    {Object.entries(documents).map(([slug, label]) => (
                        <span key={slug} className="rounded-full border border-seal/30 bg-white px-2.5 py-0.5 text-xs text-slate-700">
                            {label}
                        </span>
                    ))}
                </div>
            </div>

            <div>
                <p className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
                    Droits d'enregistrement estimés
                </p>
                <ul className="mt-1 space-y-0.5">
                    {droits.map(([label, montant]) => (
                        <li key={label} className="flex items-baseline justify-between gap-3 text-xs text-slate-600">
                            <span>{label}</span>
                            <span className="font-ref shrink-0 text-slate-700">
                                {montant === null
                                    ? 'valeur des parts à renseigner'
                                    : `${montant.toLocaleString('fr-FR')} GNF`}
                            </span>
                        </li>
                    ))}
                    {total !== null && (
                        <li className="mt-1 flex items-baseline justify-between gap-3 border-t border-seal/20 pt-1 text-xs font-medium text-slate-800">
                            <span>Total</span>
                            <span className="font-ref shrink-0">{total.toLocaleString('fr-FR')} GNF</span>
                        </li>
                    )}
                </ul>
                <p className="mt-1 text-[11px] text-slate-400">
                    Hors honoraires de l'office — la note de frais complète est générée avec le dossier.
                </p>
            </div>
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
    const { typesActes, notaires, reviseurs, formalistes, defauts, brouillons: brouillonsInitiaux, reglesParTypeActe, typesModification } = usePage().props;

    const [step, setStep] = useState(0);
    const [categorie, setCategorie] = useState(null);
    const [sousGroupe, setSousGroupe] = useState(null);
    const [typeActe, setTypeActe] = useState(null);
    const [formValues, setFormValues] = useState({});
    const [clientLinks, setClientLinks] = useState({}); // { [clientRole]: clientObject }
    const [creatingClientForGroup, setCreatingClientForGroup] = useState(null);
    // { client, group } — édition en place d'une fiche déjà rattachée à un rôle.
    const [editingClient, setEditingClient] = useState(null);
    // Rôles saisis en texte libre plutôt que via une fiche client (tiers ponctuel
    // qu'on ne verse pas au répertoire) — { [clientRole]: true }.
    const [saisieLibreRoles, setSaisieLibreRoles] = useState({});
    // Clients ajoutés en haut du formulaire (personnes physiques/morales créées ou choisies
    // dans le répertoire pour ce dossier). Certains reçoivent une qualité libre directement
    // (témoin, accompagnateur…), d'autres restent disponibles pour être réutilisés comme
    // gérant/associé/etc. via les sélecteurs de client des sections ci-dessous.
    const [dossierClients, setDossierClients] = useState([]); // [{ client, role }]
    const [creatingClientForDossierIndex, setCreatingClientForDossierIndex] = useState(null);
    // Société du registre sur laquelle porte le dossier (modification, dissolution) : sa fiche
    // préremplit les champs `soc.*` et devient `dossiers.societe_id`. Distincte de
    // `clientLinks` — une société n'est pas une partie à l'acte, c'est son objet.
    const [societeLink, setSocieteLink] = useState(null);
    /**
     * Société du registre, ou société hors registre saisie à la main.
     *
     * Posé en premier plutôt que déduit : le clerc sait dès le départ s'il traite une société que
     * l'étude a constituée ou celle d'un confrère. L'ancienne modale rendait ce choix implicite —
     * il fallait chercher, ne rien trouver, puis penser à l'ouvrir.
     */
    const [modeSociete, setModeSociete] = useState('registre');

    /**
     * Actes que cette procédure produira — calculés par le serveur.
     *
     * `null` tant que la réponse n'est pas là. Le calcul reste côté PHP : la résolution
     * rôle × variante × « applicable à tous » est la règle métier, et la réécrire ici garantirait
     * que l'annonce et la production divergent — c'est précisément le défaut corrigé.
     */
    const [actesPrevus, setActesPrevus] = useState(null);
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
    // Une tentative d'avancement a-t-elle eu lieu sur cette étape ? Tant que non, aucun champ n'est
    // marqué en rouge : un formulaire vierge intégralement rouge est agressif et n'informe pas.
    const [validationTentee, setValidationTentee] = useState(false);
    const [blocantsOuverts, setBlocantsOuverts] = useState(false);
    
    // Ajout état pour les pièces justificatives "staged" (upload direct à la création)
    const [stagedPieces, setStagedPieces] = useState({});
    const [previewKey, setPreviewKey] = useState(null);

    // ── Brouillon ────────────────────────────────────────────────────────────
    // Pièces déjà téléversées au brouillon : { groupe: { cle: {chemin, nom} } }.
    // Distinctes de stagedPieces (des File de la session courante) — voir
    // resources/js/lib/brouillonDossier.js.
    const [piecesBrouillon, setPiecesBrouillon] = useState({});
    const [brouillonId, setBrouillonId] = useState(null);
    const [brouillons, setBrouillons] = useState(brouillonsInitiaux ?? []);
    const [enregistrementBrouillon, setEnregistrementBrouillon] = useState(false);
    const [brouillonEnregistreA, setBrouillonEnregistreA] = useState(null);
    const [brouillonASupprimer, setBrouillonASupprimer] = useState(null);

    const handleStagedPieceChange = (groupName, key, file) => {
        setStagedPieces(prev => ({
            ...prev,
            [groupName]: {
                ...(prev[groupName] || {}),
                [key]: file,
            }
        }));
        // Un nouveau fichier remplace la pièce héritée du brouillon : la garder
        // ferait resurgir l'ancienne au prochain enregistrement.
        if (file) retirerPieceBrouillon(groupName, key);
    };

    /**
     * Retire une pièce héritée du brouillon. Le fichier n'est réellement supprimé du
     * disque qu'au prochain enregistrement : le serveur nettoie tout fichier que
     * l'état reçu ne mentionne plus (voir DossierBrouillonController::rangerPieces).
     */
    const retirerPieceBrouillon = (groupName, key) => {
        setPiecesBrouillon(prev => {
            if (!prev[groupName]?.[key]) return prev;
            const groupe = { ...prev[groupName] };
            delete groupe[key];
            const next = { ...prev, [groupName]: groupe };
            if (Object.keys(groupe).length === 0) delete next[groupName];
            return next;
        });
    };

    // Remonte en haut du contenu défilable à chaque changement d'étape (ou de sous-groupe/type
    // d'acte à l'étape 2) — sans ça, le scroll reste où l'utilisateur l'a laissé sur l'étape
    // précédente et la nouvelle étape peut s'afficher en plein milieu, voire tout en bas.
    useEffect(() => {
        const scrollable = document.querySelector('main');
        if (scrollable) scrollable.scrollTop = 0;
    }, [step, sousGroupe, typeActe?.id]);

    // ── Calcul automatique Capital ↔ Parts ↔ Valeur nominale ─────────────────
    // Relation : Capital = Nombre de parts × Valeur nominale d'une part.
    // Dès que 2 des 3 valeurs sont renseignées, la 3ème est déduite.
    // Le champ des parts est 'soc.nombre_parts' pour les SARL/SARLU et
    // 'soc.nombre_actions' pour les SA/SAS/SASU.
    const lastEditedCapitalField = useRef(null);

    // Wrapper pour setFormValues qui traque le dernier champ Capital/Parts/VN modifié.
    const capitalTriadFields = ['soc.capital_chiffres', 'soc.nombre_parts', 'soc.nombre_actions', 'soc.valeur_nominale_chiffres'];

    // Intercepter les modifications via un setter enrichi : quand l'utilisateur
    // change l'un des 3 champs, on note lequel pour ne pas le recalculer.
    const setFormValuesTracked = (updater) => {
        setFormValues(prev => {
            const next = typeof updater === 'function' ? updater(prev) : updater;
            // Détecter quel champ de la triade a changé.
            for (const fid of capitalTriadFields) {
                if (next[fid] !== prev[fid]) {
                    lastEditedCapitalField.current = fid;
                    break;
                }
            }
            return next;
        });
    };

    useEffect(() => {
        const edited = lastEditedCapitalField.current;
        if (!edited) return;

        // Détermine quel champ 'parts' est pertinent selon le type d'acte.
        const partsField = formValues['soc.nombre_actions'] !== undefined
            ? 'soc.nombre_actions'
            : 'soc.nombre_parts';

        const capital = parseFloat(formValues['soc.capital_chiffres']) || 0;
        const parts = parseFloat(formValues[partsField]) || 0;
        const valeurNominale = parseFloat(formValues['soc.valeur_nominale_chiffres']) || 0;

        // Si le champ édité ne fait pas partie de la triade active, ne rien faire.
        const activeTriad = ['soc.capital_chiffres', partsField, 'soc.valeur_nominale_chiffres'];
        if (!activeTriad.includes(edited)) return;

        // Calculer le 3ème champ quand les 2 autres sont non nuls.
        if (edited === 'soc.capital_chiffres') {
            // L'utilisateur a modifié le capital : recalculer soit VN soit parts.
            if (parts > 0 && capital > 0) {
                const computed = Math.round(capital / parts);
                if (computed !== valeurNominale) {
                    setFormValues(prev => ({ ...prev, 'soc.valeur_nominale_chiffres': String(computed) }));
                }
            }
        } else if (edited === partsField) {
            // L'utilisateur a modifié le nombre de parts : recalculer VN.
            if (capital > 0 && parts > 0) {
                const computed = Math.round(capital / parts);
                if (computed !== valeurNominale) {
                    setFormValues(prev => ({ ...prev, 'soc.valeur_nominale_chiffres': String(computed) }));
                }
            }
        } else if (edited === 'soc.valeur_nominale_chiffres') {
            // L'utilisateur a modifié la valeur nominale : recalculer le capital.
            if (parts > 0 && valeurNominale > 0) {
                const computed = Math.round(parts * valeurNominale);
                if (computed !== capital) {
                    setFormValues(prev => ({ ...prev, 'soc.capital_chiffres': String(computed) }));
                }
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        formValues['soc.capital_chiffres'],
        formValues['soc.nombre_parts'],
        formValues['soc.nombre_actions'],
        formValues['soc.valeur_nominale_chiffres'],
    ]);

    // ── Capital après modification (augmentation / diminution) ───────────────
    // Le capital « après » n'est jamais saisi : il se déduit du capital actuel de la société
    // et du montant de l'opération. Le laisser en saisie libre laisserait passer une
    // incohérence entre les statuts mis à jour, le PV et la DNSV — trois documents qui
    // doivent porter le même chiffre.
    useEffect(() => {
        const capital = parseFloat(formValues['soc.capital_chiffres']) || 0;
        if (capital <= 0) return;

        const majAttendue = (champMontant, champApres, signe) => {
            const montant = parseFloat(formValues[champMontant]) || 0;
            if (montant <= 0) return null;
            return String(Math.round(capital + signe * montant));
        };

        const augmentation = majAttendue('modif.augmentation_montant', 'modif.augmentation_capital_apres', 1);
        const diminution   = majAttendue('modif.diminution_montant', 'modif.diminution_capital_apres', -1);

        setFormValues(prev => {
            const next = { ...prev };
            let change = false;
            if (augmentation !== null && next['modif.augmentation_capital_apres'] !== augmentation) {
                next['modif.augmentation_capital_apres'] = augmentation;
                change = true;
            }
            if (diminution !== null && next['modif.diminution_capital_apres'] !== diminution) {
                next['modif.diminution_capital_apres'] = diminution;
                change = true;
            }
            return change ? next : prev;
        });
    }, [
        formValues['soc.capital_chiffres'],
        formValues['modif.augmentation_montant'],
        formValues['modif.diminution_montant'],
    ]);

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
    // Modifications statutaires mutuellement exclusives — dérivé de l'enum côté serveur, la règle
    // n'est jamais redéclarée ici (voir TypeModificationStatutaire::incompatiblesAvec).
    const exclusionsModification = tableExclusionsModification(typesModification);
    const categorieSelected = categories.find(c => c.id === categorie?.id);
    const typeSelected = typeActe;

    const selectType = (type) => {
        setTypeActe(type);
        const key = TYPE_ACTE_CODE_MAP[type.code];
        const q = (key && QUESTIONNAIRES[key]) || [];
        setFormValues(initRepeatableFields(q));
        setClientLinks({});
        setSaisieLibreRoles({});
        // Changer de type d'acte remet à zéro le questionnaire : garder la société
        // rattachée laisserait `societe_id` pointer sur une fiche dont plus aucun champ
        // `soc.*` n'est projeté.
        setSocieteLink(null);
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

    // ── Société du registre ──────────────────────────────────────────────────

    /**
     * Rattache une société et projette sa fiche dans les champs `soc.*`.
     *
     * La liste de l'autocomplétion ne porte pas les personnes du dossier de constitution
     * (inutile de les charger pour quinze résultats) : on rappelle la fiche complète pour
     * pouvoir proposer les associés et gérants connus. L'échec de ce second appel ne doit pas
     * annuler le rattachement — la société reste choisie, seule la commodité est perdue.
     */
    const applySociete = async (societe) => {
        setSocieteLink(societe);
        setFormValues(prev => ({ ...prev, ...mapSocieteToQuestionnaire(societe) }));

        if (!societe?.id) return;
        try {
            const { data } = await axios.get(`/societes/${societe.id}`);
            setSocieteLink(data);
            setFormValues(prev => ({ ...prev, ...mapSocieteToQuestionnaire(data) }));
        } catch {
            /* fiche de base déjà rattachée — on n'interrompt pas la saisie */
        }
    };

    // Variantes décidées, sous leur forme technique : `modif.types` stocke les libellés affichés
    // (convention de tous les `select` du projet), le serveur accepte les deux.
    const variantesChoisies = Array.isArray(formValues['modif.types']) ? formValues['modif.types'] : [];

    useEffect(() => {
        const typeId = typeSelected?.id;

        if (!typeId || step !== 2) {
            return;
        }

        const controleur = new AbortController();
        setActesPrevus(null);

        axios
            .get(`/types-actes/${typeId}/actes-prevus`, {
                params: { variantes: variantesChoisies },
                signal: controleur.signal,
            })
            .then(({ data }) => setActesPrevus(data.actes ?? []))
            // Annulation d'une requête devancée par une autre : ce n'est pas une erreur.
            .catch((err) => { if (!axios.isCancel(err)) setActesPrevus([]); });

        return () => controleur.abort();
    }, [typeSelected?.id, step, JSON.stringify(variantesChoisies)]);

    const unlinkSociete = () => setSocieteLink(null);

    /**
     * Recharge la fiche rattachée depuis le serveur — après un dépôt de pièce constitutive, pour
     * que la checklist reflète l'état réel sans recharger la page (une visite Inertia perdrait
     * toute la saisie de l'assistant).
     */
    const rafraichirSociete = async () => {
        if (!societeLink?.id) return;
        try {
            const { data } = await axios.get(`/societes/${societeLink.id}`);
            setSocieteLink(data);
        } catch {
            /* la checklist reste sur son état précédent — le dépôt, lui, a bien eu lieu */
        }
    };

    /**
     * Verse une personne connue de la société aux « clients du dossier », d'où elle est
     * réutilisable comme cédant, gérant sortant, souscripteur… Ne l'affecte à aucun rôle
     * d'office : un associé d'origine peut avoir déjà cédé toutes ses parts, c'est au clerc
     * de dire à quel titre il intervient.
     */
    const importerPersonneConnue = (personne) => {
        if (!personne?.client) {
            toast.error(`${personne?.nom ?? 'Cette personne'} n'a pas de fiche client — créez-la depuis la section concernée.`);
            return;
        }
        addClientToPool(personne.client);
        toast.success(`${clientDisplayName(personne.client)} ajouté aux clients du dossier.`);
    };

    /**
     * Champs `soc.*` à afficher quand une fiche du registre est rattachée.
     *
     * Masqués **seulement s'ils sont renseignés dans la fiche** : ils restent alors dans
     * `formValues` — c'est la projection attendue par les balises `${soc.*}` des modèles Word — et
     * les réafficher en saisie libre recréerait deux vérités concurrentes pour la même donnée.
     *
     * Un champ que la fiche **ne renseigne pas** reste saisissable. La première version masquait
     * tout `soc.*` sans distinction, si bien qu'une société au registre sans numéro RCCM rendait ce
     * champ **obligatoire et impossible à remplir** : le seul recours affiché était de détacher la
     * société, donc de perdre tout le préremplissage. La saisie complète alors la fiche au registre.
     */
    const champsSocieteAffichables = (group) => {
        if (!group.societePicker) return group.fields;

        // Mode « registre » sans fiche encore choisie : le sélecteur **est** la saisie. Afficher les
        // champs vides sous lui inviterait à une double saisie dont l'une serait perdue.
        if (!societeLink) {
            return modeSociete === 'hors_registre' ? group.fields : [];
        }

        return group.fields.filter(f => !ficheRenseigneChamp(societeLink, f.id));
    };

    /** Ce champ complètera-t-il la fiche du registre plutôt que de la refléter ? */
    const completeLaFicheSociete = (fieldId) =>
        !!societeLink && estChampSociete(fieldId) && !ficheRenseigneChamp(societeLink, fieldId);

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

    const toggleSaisieLibre = (role, actif) => {
        setSaisieLibreRoles(prev => ({ ...prev, [role]: actif }));
        // Passer en saisie libre implique de renoncer à la fiche : garder le lien
        // laisserait deux vérités concurrentes pour le même rôle.
        if (actif) unlinkClientFromSection(role);
    };

    /**
     * Champs à afficher pour une section. Dès qu'un client est rattaché, les champs
     * d'identité disparaissent — ils sont portés par la fiche, réaffichés dans la
     * carte de synthèse, et reprojetés côté serveur (ClientProjectionService).
     * Ne restent que les données propres à l'acte (nombre de parts, fonction…) et
     * les cases de contrôle (« le gérant est une personne différente »).
     */
    const champsAffichables = (group) => {
        // Une section « société » masque les champs portés par la fiche du registre — même
        // principe, appliqué à la personne morale objet du dossier plutôt qu'à une partie.
        if (group.societePicker) return champsSocieteAffichables(group);

        if (!group.clientRole || !clientLinks[group.clientRole]) return group.fields;
        return group.fields.filter(f =>
            f.type === 'repeatable'
            || f.type === 'checkbox'
            || f.type === 'checkbox_required'
            || f.type === 'checkbox_group'
            || !estChampIdentite(f.id)
        );
    };

    /**
     * Champs d'identité obligatoires que la fiche rattachée ne renseigne pas.
     *
     * Sans ça, le parcours se bloquait en silence : le champ est masqué (porté par la fiche) mais
     * reste obligatoire, donc « Suivant » restait inactif sans qu'aucun champ visible ne soit en
     * défaut. On les remonte pour inviter à compléter la fiche — et non à saisir la valeur à côté,
     * ce qui recréerait la double vérité que cette refonte supprime.
     *
     * Cet avertissement **dans la section** complète la liste globale des blocants, qui signale le
     * même cas via `estMasque` et renvoie vers cette carte ; ici on nomme les champs sur place.
     */
    const champsIdentiteManquants = (group) => {
        if (!group.clientRole || !clientLinks[group.clientRole]) return [];
        return group.fields
            .filter(f => f.required && estChampIdentite(f.id) && !formValues[f.id])
            .map(f => f.label);
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

    // ── Brouillon : enregistrer, reprendre, abandonner ───────────────────────

    const enregistrerBrouillon = async () => {
        setEnregistrementBrouillon(true);
        try {
            const form = construireFormDataBrouillon({
                etat: {
                    step, categorie, sousGroupe, typeActe, formValues, clientLinks,
                    dossierClients, saisieLibreRoles, objet, urgent, notes,
                    notaireId, reviseurId, formalisteId, piecesBrouillon,
                    // Sans elle, reprendre un brouillon de modification perdait la société
                    // choisie : les champs `soc.*` restaient remplis mais `societe_id` était
                    // vide, donc le dossier naissait sans lien au registre.
                    societeLink,
                },
                stagedPieces,
                brouillonId,
                typeActeId: typeActe?.id,
                libelle: libelleBrouillon({ objet, typeActeLabel: typeActe?.label }),
            });

            const { data } = await axios.post('/dossiers/brouillons', form);

            setBrouillonId(data.id);
            // Le serveur renvoie l'emplacement des fichiers qu'il vient de ranger :
            // les File locaux deviennent inutiles, on bascule sur les références.
            setPiecesBrouillon(data.etat?.piecesBrouillon ?? {});
            setStagedPieces({});
            setBrouillonEnregistreA(new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }));
            setBrouillons(prev => [data, ...prev.filter(b => b.id !== data.id)]);
            toast.success('Brouillon enregistré.');
        } catch (err) {
            toast.error(err.response?.data?.message || "Le brouillon n'a pas pu être enregistré.");
        } finally {
            setEnregistrementBrouillon(false);
        }
    };

    const reprendreBrouillon = (brouillon) => {
        const e = brouillon.etat ?? {};
        // Le type d'acte est retrouvé par id dans les types actifs : s'il a été
        // désactivé depuis, on laisse l'utilisateur le resélectionner plutôt que de
        // restaurer un type qui n'est plus proposable.
        const type = Object.values(typesActes ?? {})
            .flat()
            .find(t => String(t.id) === String(e.typeActeId)) ?? null;

        setCategorie(e.categorie ?? null);
        setSousGroupe(e.sousGroupe ?? null);
        setTypeActe(type);
        setFormValues(e.formValues ?? {});
        setClientLinks(e.clientLinks ?? {});
        setDossierClients(e.dossierClients ?? []);
        setSaisieLibreRoles(e.saisieLibreRoles ?? {});
        setSocieteLink(e.societeLink ?? null);
        setObjet(e.objet ?? '');
        setUrgent(!!e.urgent);
        setNotes(e.notes ?? '');
        setNotaireId(e.notaireId ?? '');
        setReviseurId(e.reviseurId ?? '');
        setFormalisteId(e.formalisteId ?? '');
        setPiecesBrouillon(e.piecesBrouillon ?? {});
        setStagedPieces({});
        setBrouillonId(brouillon.id);
        setErrors({});
        // Ne pas revenir à l'étape enregistrée si le type d'acte a disparu : sans
        // type, l'étape 2 n'a aucun questionnaire à afficher.
        setStep(type ? (e.step ?? 1) : 0);
        toast.success('Brouillon repris.');
    };

    const supprimerBrouillon = async (id) => {
        try {
            await axios.delete(`/dossiers/brouillons/${id}`);
            setBrouillons(prev => prev.filter(b => b.id !== id));
            if (brouillonId === id) {
                setBrouillonId(null);
                setPiecesBrouillon({});
            }
            toast.success('Brouillon supprimé.');
        } catch {
            toast.error("Le brouillon n'a pas pu être supprimé.");
        } finally {
            setBrouillonASupprimer(null);
        }
    };

    // Auto-reprise si un id de brouillon est passé dans l'URL
    useEffect(() => {
        if (typeof window === 'undefined') return;
        const params = new URLSearchParams(window.location.search);
        const autoResumeId = params.get('brouillon');
        if (autoResumeId && brouillons.length > 0 && !brouillonId) {
            const b = brouillons.find(x => String(x.id) === autoResumeId);
            if (b) {
                reprendreBrouillon(b);
                // Nettoyer l'URL sans recharger la page
                window.history.replaceState({}, '', window.location.pathname);
            }
        }
    }, [brouillons, brouillonId]);

    /**
     * Bouton d'enregistrement du brouillon, rendu à trois endroits : en tête de
     * l'assistant, dans la barre de navigation (rendue collante pour rester
     * accessible pendant le défilement d'un questionnaire long) et dans le
     * récapitulatif. Factorisé plutôt que triplé pour que l'état de chargement et
     * la condition d'affichage restent uniques.
     */
    const BoutonBrouillon = ({ size = 'sm', className, label = 'Enregistrer le brouillon' }) => {
        if (!typeActe) return null;

        return (
            <Button
                type="button"
                variant="outline"
                size={size}
                className={className}
                onClick={enregistrerBrouillon}
                disabled={enregistrementBrouillon}
            >
                <Save className="h-3.5 w-3.5" />
                {enregistrementBrouillon ? 'Enregistrement…' : label}
            </Button>
        );
    };

    // Ce qui manque pour avancer — une **liste**, plus un booléen : le bouton grisé sans explication
    // était impossible à diagnostiquer sur un questionnaire de trente champs. Voir blocantsEtape.js.
    /**
     * Nombre d'options chargées par champ géo, remonté par `LieuSelect`.
     *
     * Sans lui, le blocant répondait « Choisissez une valeur » devant une liste vide — or 33 des
     * 39 communes n'ont aucun quartier au référentiel, et le seul recours (ajouter le lieu) n'était
     * pas nommé.
     */
    const [optionsGeo, setOptionsGeo] = useState({});
    const noterOptionsGeo = (id, nb) => setOptionsGeo(p => (p[id] === nb ? p : { ...p, [id]: nb }));

    const blocants = blocantsEtape({
        step, categorie, typeActe, visibleFields, formValues, objet, notaireId, optionsGeo,
        // `champsAffichables` retire les champs portés par une fiche liée : ils restent
        // obligatoires mais ne sont plus à l'écran. Le dire, plutôt que de renvoyer vers un
        // champ qui n'existe pas dans le DOM.
        estMasque: (field, groupe) => !champsAffichables(groupe).some(f => f.id === field.id),
    });
    const blocantsParChamp = motifsParChamp(blocants);
    const blocantsParNomSection = compterParSection(blocants);

    /**
     * Tenter d'avancer.
     *
     * Le bouton n'est jamais grisé : cliquer avec des manques ne bloque pas silencieusement, ça
     * affiche les champs fautifs en rouge, déroule la liste et emmène au premier. C'est le bouton qui
     * guide, au lieu d'être une porte fermée sans écriteau.
     */
    const next = () => {
        if (blocants.length > 0) {
            setValidationTentee(true);
            setBlocantsOuverts(true);
            allerAuBlocant(blocants[0].ancre);
            return;
        }

        if (step < 2) {
            setStep(s => s + 1);
            // Repartir vierge sur la nouvelle étape : ses champs n'ont pas encore été soumis, les
            // afficher d'emblée en rouge serait une accusation sans faute.
            setValidationTentee(false);
            setBlocantsOuverts(false);
        }
    };

    const prev = () => {
        if (step > 0) {
            setStep(s => s - 1);
            setValidationTentee(false);
            setBlocantsOuverts(false);
        }
    };

    const handleSubmit = () => {
        const typeActeId = findTypeActeId();
        if (!typeActeId) {
            setErrors({ type_acte_id: 'Type d\'acte introuvable. Vérifiez la configuration.' });
            return;
        }
        const autresPartiesPayload = dossierClients
            .filter(p => p.client && p.role.trim())
            .map(p => ({ ...buildPartieFields(p.client, {}, ''), role: p.role.trim(), client_id: p.client.id }));

        // Purgé des champs des blocs décochés : une valeur laissée par une modification finalement
        // abandonnée serait projetée dans les actes et, pour `modif.valeur_parts_cedees`, servirait
        // d'assiette à la facture (voir purgerChampsInvisibles). Sert aussi à construire les
        // `parties` — sans quoi une cession décochée créerait encore ses cédants et cessionnaires.
        // Le brouillon, lui, conserve tout volontairement.
        const donneesSoumises = purgerChampsInvisibles(questionnaire, formValues);

        setSubmitting(true);
        router.post('/dossiers', {
            type_acte_id: typeActeId,
            // Société du registre sur laquelle porte le dossier — la fiche reste la source de
            // vérité, `donnees.soc.*` n'en est que la projection destinée aux modèles Word.
            societe_id: societeLink?.id || undefined,
            objet: objet,
            urgent: urgent,
            notes: notes || undefined,
            notaire_id: notaireId,
            reviseur_id: reviseurId || undefined,
            formaliste_id: formalisteId || undefined,
            donnees: donneesSoumises,
            // Le brouillon détient les pièces déjà téléversées : le serveur les
            // rattache aux parties puis le supprime (voir DossierController).
            brouillon_id: brouillonId || undefined,
            parties: [
                ...buildPartiesPayload(questionnaire, donneesSoumises, clientLinks, stagedPieces, piecesBrouillon),
                ...autresPartiesPayload,
            ],
        }, {
            forceFormData: true,
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
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <h1 className="font-serif text-display text-ink">Nouveau dossier</h1>
                        <p className="text-slate-500 text-sm mt-1">Suivez les étapes pour créer un nouveau dossier d'acte</p>
                    </div>
                    {/* Enregistrement du brouillon : proposé dès qu'un type d'acte est
                        choisi — avant, il n'y a rien à reprendre qu'un clic ne referait. */}
                    {typeActe && (
                        <div className="shrink-0 text-right">
                            <BoutonBrouillon className="h-8 gap-1.5" />
                            {brouillonEnregistreA && (
                                <p className="mt-1 text-[11px] text-slate-400">
                                    Brouillon enregistré à {brouillonEnregistreA}
                                </p>
                            )}
                        </div>
                    )}
                </div>

                {/* Reprise d'une saisie inachevée — proposée seulement avant d'avoir
                    commencé à remplir, pour ne jamais écraser une saisie en cours. */}
                {brouillons.length > 0 && !brouillonId && !typeActe && (
                    <div className="rounded-lg border border-seal/30 bg-seal-light/60 p-4">
                        <div className="flex items-center gap-2">
                            <FileClock className="h-4 w-4 text-seal-hover" />
                            <h2 className="text-sm font-semibold text-ink">
                                {brouillons.length === 1
                                    ? 'Vous avez un dossier en cours de saisie'
                                    : `Vous avez ${brouillons.length} dossiers en cours de saisie`}
                            </h2>
                        </div>
                        <div className="mt-3 space-y-2">
                            {brouillons.map(b => (
                                <div key={b.id} className="flex items-center gap-3 rounded-md border border-seal/20 bg-white px-3 py-2">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium text-slate-800">
                                            {b.libelle || b.typeActeLabel || 'Dossier sans objet'}
                                        </p>
                                        <p className="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-400">
                                            {b.typeActeLabel && <span>{b.typeActeLabel}</span>}
                                            <span>Modifié le {b.modifie_le}</span>
                                            {b.nbPieces > 0 && (
                                                <span className="inline-flex items-center gap-1 text-slate-500">
                                                    <Paperclip className="h-3 w-3" />
                                                    {b.nbPieces} pièce{b.nbPieces > 1 ? 's' : ''} conservée{b.nbPieces > 1 ? 's' : ''}
                                                </span>
                                            )}
                                        </p>
                                    </div>
                                    <Button variant="seal" size="sm" className="h-7 shrink-0 gap-1" onClick={() => reprendreBrouillon(b)}>
                                        <ArrowRight className="h-3 w-3" /> Reprendre
                                    </Button>
                                    <button
                                        type="button"
                                        onClick={() => setBrouillonASupprimer(b)}
                                        title="Supprimer ce brouillon"
                                        className="shrink-0 rounded p-1 text-slate-300 transition-colors hover:text-danger"
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

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
                                                {/* Règles légales de la forme choisie (CR juillet 2026) :
                                                    affichées ici pour guider la saisie, plutôt que de se
                                                    découvrir au moment où l'avancement est refusé. */}
                                                {reglesParTypeActe?.[typeActe.code] && (() => {
                                                    const r = reglesParTypeActe[typeActe.code];
                                                    return (
                                                        <div className="rounded-md border border-slate-200 bg-slate-50/70 p-2.5 space-y-1">
                                                            <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                                                Règles légales — {r.valeur}
                                                            </p>
                                                            <p className="text-xs text-slate-600">{r.natureLabel} · {r.responsabilite}</p>
                                                            <ul className="space-y-0.5 text-xs text-slate-500">
                                                                <li>
                                                                    Capital minimum :{' '}
                                                                    <span className="font-medium text-slate-700">
                                                                        {r.capitalMinimum
                                                                            ? `${r.capitalMinimum.toLocaleString('fr-FR')} GNF`
                                                                            : 'aucun'}
                                                                    </span>
                                                                </li>
                                                                <li>
                                                                    Associé unique :{' '}
                                                                    <span className="font-medium text-slate-700">
                                                                        {r.admetAssocieUnique ? 'admis (un seul associé)' : 'non admis'}
                                                                    </span>
                                                                </li>
                                                                {r.exigeCommissaire && (
                                                                    <li className="text-warning-text">Commissaire aux comptes obligatoire</li>
                                                                )}
                                                                {r.exigeMajoriteAssocies && (
                                                                    <li className="text-warning-text">Associés majeurs obligatoires</li>
                                                                )}
                                                            </ul>
                                                        </div>
                                                    );
                                                })()}
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
                                                        placeholder={`Description synthétique du dossier (min. ${OBJET_LONGUEUR_MIN} caractères)…`}
                                                        value={objet}
                                                        onChange={e => setObjet(e.target.value)}
                                                        className={cn(
                                                            'w-full scroll-mt-24 resize-none rounded-lg border px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-seal',
                                                            validationTentee && blocantsParChamp.has('objet')
                                                                ? 'border-danger'
                                                                : 'border-slate-200',
                                                        )}
                                                    />
                                                    {/* Compteur vivant : le placeholder qui énonçait la règle disparaissait à la
                                                        première frappe, si bien que « 10 caractères minimum » devenait invisible
                                                        au moment précis où elle commençait à compter. */}
                                                    {objet.trim().length < OBJET_LONGUEUR_MIN && (
                                                        <p className={cn(
                                                            'text-xs',
                                                            validationTentee && blocantsParChamp.has('objet') ? 'text-danger' : 'text-slate-400',
                                                        )}>
                                                            {OBJET_LONGUEUR_MIN} caractères minimum — {objet.trim().length} saisi{objet.trim().length > 1 ? 's' : ''}
                                                        </p>
                                                    )}
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
                                                        className={cn(
                                                            'w-full scroll-mt-24 rounded-lg border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-seal',
                                                            validationTentee && blocantsParChamp.has('notaire_id')
                                                                ? 'border-danger'
                                                                : 'border-slate-200',
                                                        )}
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
                                                        <Label htmlFor="reviseur_id">Certificateur</Label>
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
                                                    <div
                                                        key={gi}
                                                        // Cible de défilement pour un champ masqué par une fiche liée, qu'on
                                                        // ne peut pas viser directement (voir ancreSection).
                                                        id={ancreSection(group.name) ?? undefined}
                                                        className="rounded-lg border border-slate-200 bg-white p-4 scroll-mt-24"
                                                    >
                                                        {group.name && (
                                                            <GroupHeader
                                                                icon={Icon}
                                                                iconColor={meta.iconColor}
                                                                iconBg={meta.iconBg}
                                                                // Repérer d'un coup d'œil où ça coince : un questionnaire de
                                                                // modification compte jusqu'à dix sections, toutes dépliées.
                                                                badge={(
                                                                    <BadgeSection
                                                                        nb={blocantsParNomSection[group.name] ?? 0}
                                                                        // Une coche verte n'a de sens que si la section portait
                                                                        // quelque chose d'obligatoire : sur une section entièrement
                                                                        // facultative, elle ne dirait rien.
                                                                        complete={group.fields.some(f => f.required || f.type === 'repeatable')}
                                                                    />
                                                                )}
                                                            >
                                                                {group.name}
                                                            </GroupHeader>
                                                        )}

                                                        {/* group.fields.length > 1 exclut les groupes qui ne sont encore que la case à
                                                            cocher "personne différente de…" (ger.est_different, etc.) — les autres
                                                            champs, masqués tant qu'elle n'est pas cochée, sont filtrés de visibleFields
                                                            en amont ; proposer un client à lier n'a de sens qu'une fois la section
                                                            dépliée. */}
                                                        {(() => {
                                                        const estSectionClient = group.clientRole && group.fields.length > 1;
                                                        const grille = (
                                                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                                                            {champsAffichables(group).map(field => {
                                                                // En rouge seulement après une tentative d'avancement, et le rouge
                                                                // s'efface dès la saisie (le champ quitte alors la liste des blocants).
                                                                const enDefaut = validationTentee && blocantsParChamp.has(field.id);

                                                                return (
                                                                    <div key={field.id} className={classesChamp({ field, enDefaut })}>
                                                                        {/* Moteur unique, partagé avec le modal d'édition et le
                                                                            formulaire public d'intake : c'est ce qui garantit que
                                                                            `readonly`, la cascade géo et la cohérence des dates
                                                                            valent partout — quatre comportements avaient été
                                                                            implémentés d'un seul côté. */}
                                                                        <ChampQuestionnaire
                                                                            field={field}
                                                                            valeurs={formValues}
                                                                            onPatch={(patch) => setFormValuesTracked(prev => ({ ...prev, ...patch }))}
                                                                            motif={enDefaut ? blocantsParChamp.get(field.id) : null}
                                                                            peutAjouter
                                                                            onNombreOptions={(nb) => noterOptionsGeo(field.id, nb)}
                                                                            rendreChoixMultiple={(f) => (
                                                                                <ChoixMultiple
                                                                                    field={f}
                                                                                    valeurs={formValues[f.id] ?? []}
                                                                                    onChange={val => setFormValues(prev => ({ ...prev, [f.id]: val }))}
                                                                                    exclusions={exclusionsModification}
                                                                                />
                                                                            )}
                                                                            rendreRepeatable={(f) => (
                                                                                <RepeatableGroup
                                                                                    fieldDef={f}
                                                                                    value={formValues[f.id] ?? []}
                                                                                    onChange={val => setFormValues(prev => ({ ...prev, [f.id]: val }))}
                                                                                    poolClients={poolClients}
                                                                                    onClientCreated={addClientToPool}
                                                                                    piecesRequises={usePage().props.piecesRequises}
                                                                                    stagedPieces={stagedPieces[f.id] || {}}
                                                                                    onStagedPieceChange={(key, file) => handleStagedPieceChange(f.id, key, file)}
                                                                                    piecesBrouillon={piecesBrouillon[f.id] || {}}
                                                                                    onRetirerPieceBrouillon={(key) => retirerPieceBrouillon(f.id, key)}
                                                                                />
                                                                            )}
                                                                        />

                                                                        {/* Champ visible malgré la fiche rattachée : elle ne le
                                                                            renseigne pas. Le dire évite de laisser croire à une
                                                                            double saisie — et annonce l'enrichissement du registre. */}
                                                                        {completeLaFicheSociete(field.id) && (
                                                                            <p className="mt-1 flex items-center gap-1 text-xs text-slate-400">
                                                                                <Building2 className="h-3 w-3 shrink-0" />
                                                                                Absent de la fiche du registre — votre saisie la complétera
                                                                            </p>
                                                                        )}
                                                                    </div>
                                                                );
                                                            })}
                                                        </div>
                                                        );

                                                        // Section « société concernée » : la fiche du
                                                        // registre est la source, la saisie manuelle le
                                                        // recours. Les champs `soc.*` sont retirés de
                                                        // `grille` dès qu'une société est rattachée.
                                                        if (group.societePicker) {
                                                            return (
                                                                <ChoixSociete
                                                                    mode={modeSociete}
                                                                    onModeChange={setModeSociete}
                                                                    societeLink={societeLink}
                                                                    onSelect={applySociete}
                                                                    onUnlink={unlinkSociete}
                                                                    onImporterPersonnes={importerPersonneConnue}
                                                                >
                                                                    {/* Dossier constitutif : la fiche société existe déjà en base
                                                                        dès qu'elle est rattachée, donc le dépôt est direct — aucun
                                                                        mécanisme de brouillon nécessaire, et les fichiers
                                                                        appartiennent à la société, qui persiste même si ce dossier
                                                                        est abandonné. */}
                                                                    {societeLink && (
                                                                        <PiecesConstitutivesCard
                                                                            societe={societeLink}
                                                                            compact
                                                                            onRafraichir={rafraichirSociete}
                                                                        />
                                                                    )}
                                                                    {/* Les champs `soc.*` ne s'affichent qu'en saisie hors registre,
                                                                        ou pour ceux que la fiche rattachée ne renseigne pas. */}
                                                                    {champsAffichables(group).length > 0 && grille}
                                                                </ChoixSociete>
                                                            );
                                                        }

                                                        if (!estSectionClient) return grille;

                                                        // La section ne saisit plus l'identité : elle désigne une fiche
                                                        // client, qui reste la source de vérité. Les champs d'identité
                                                        // sont retirés de `grille` par champsAffichables() dès qu'un
                                                        // client est lié — seules les données propres à l'acte restent.
                                                        return (
                                                            <ClientRoleSection
                                                                roleLabel={group.name}
                                                                linked={clientLinks[group.clientRole] ?? null}
                                                                onSelect={(client) => applyClientToSection(group, client)}
                                                                onUnlink={() => unlinkClientFromSection(group.clientRole)}
                                                                onCreateNew={() => setCreatingClientForGroup(group)}
                                                                onEditClient={(client) => setEditingClient({ client, group })}
                                                                poolClients={poolClients}
                                                                champsManquants={champsIdentiteManquants(group)}
                                                                saisieLibre={!!saisieLibreRoles[group.clientRole]}
                                                                onToggleSaisieLibre={(v) => toggleSaisieLibre(group.clientRole, v)}
                                                            >
                                                                {grille}
                                                            </ClientRoleSection>
                                                        );
                                                        })()}

                                                        {/* Conséquences d'un champ à choix multiple qui les déclare
                                                            (`consequences`) : ce que la sélection va produire, avant de
                                                            valider — et non après création. */}
                                                        {group.fields.filter(f => f.consequences === 'modification').map(f => (
                                                            <div key={`csq-${f.id}`} className="mt-3">
                                                                <ConsequencesModification
                                                                    typesModification={typesModification}
                                                                    selection={formValues[f.id] ?? []}
                                                                    valeurPartsCedees={formValues['modif.valeur_parts_cedees']}
                                                                />
                                                            </div>
                                                        ))}

                                                        {/* Pièces justificatives pour les rôles simples (non-répétables) */}
                                                        {(() => {
                                                            if (!group.clientRole) return null;
                                                            // On vérifie s'il y a un repeatable group dans cette section, si oui on skip car c'est RepeatableGroup qui s'en charge.
                                                            if (group.fields.some(f => f.type === 'repeatable')) return null;

                                                            let categorieRole = '';
                                                            if (group.clientRole === 'associe_unique') {
                                                                categorieRole = 'associe_physique'; // Par défaut, on ne gère pas encore PP_ASSOCIE_UNIQUE avec type_personne variable ici. Mais pour l'instant ça suffit.
                                                            } else if (group.clientRole === 'bailleur' || group.clientRole === 'locataire' || group.clientRole === 'vendeur' || group.clientRole === 'acheteur' || group.clientRole === 'liquidateur' || group.clientRole === 'creancier' || group.clientRole === 'debiteur') {
                                                                categorieRole = group.clientRole; // On l'utilise tel quel si des pièces sont définies dans Partie.php
                                                            }
                                                            
                                                            const piecesRequisesSection = usePage().props.piecesRequises[categorieRole] ?? {};
                                                            const piecesKeys = Object.keys(piecesRequisesSection);
                                                            
                                                            if (piecesKeys.length === 0) return null;

                                                            return (
                                                                <div className="mt-4 pt-4 border-t border-slate-100 divide-y divide-slate-50/80">
                                                                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2 px-1">
                                                                        Pièces justificatives requises
                                                                    </p>
                                                                    {piecesKeys.map(cat => {
                                                                        const key = cat;
                                                                        const previewId = `${group.clientRole}:${key}`;
                                                                        return (
                                                                            <PieceStagedRow
                                                                                key={key}
                                                                                piece={{ label: piecesRequisesSection[key] }}
                                                                                file={stagedPieces[group.clientRole]?.[key]}
                                                                                fichierBrouillon={piecesBrouillon[group.clientRole]?.[key]}
                                                                                onFileSelected={(f) => handleStagedPieceChange(group.clientRole, key, f)}
                                                                                onRetirerBrouillon={() => retirerPieceBrouillon(group.clientRole, key)}
                                                                                isPreviewOpen={previewKey === previewId}
                                                                                onTogglePreview={() => setPreviewKey(k => k === previewId ? null : previewId)}
                                                                            />
                                                                        );
                                                                    })}
                                                                </div>
                                                            );
                                                        })()}
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
                                        // Un tableau vide est truthy : sans ce cas, un choix
                                        // multiple sans case cochée s'afficherait au récapitulatif
                                        // avec une valeur vide.
                                        if (Array.isArray(v)) return v.length > 0;
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
                                        <RecapPersonne person={reviseurSelected} role="Certificateur" color="bg-seal" />
                                        <RecapPersonne person={formalisteSelected} role="Formaliste" color="bg-ink" />
                                    </div>
                                </RecapCard>

                                {/* Société concernée — affichée avant les sections du questionnaire :
                                    c'est l'objet du dossier, et le récapitulatif doit permettre de
                                    vérifier qu'on part bien de la bonne fiche. */}
                                {societeLink && (
                                    <RecapCard icon={Building2} iconColor="text-blue-600" iconBg="bg-blue-50" title="Société concernée" onEdit={() => setStep(1)}>
                                        <div className="grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                                            <RecapField label="Dénomination" value={societeDisplayName(societeLink)} />
                                            <RecapField label="Forme juridique" value={societeLink.forme || '—'} />
                                            <RecapField label="RCCM" value={societeLink.rccm_numero || '—'} mono />
                                            <RecapField
                                                label="Capital avant modification"
                                                value={societeLink.capital_chiffres
                                                    ? `${Math.round(Number(societeLink.capital_chiffres)).toLocaleString('fr-FR')} GNF`
                                                    : '—'}
                                                mono
                                            />
                                            <RecapField
                                                label="Siège avant modification"
                                                value={[societeLink.siege_quartier, societeLink.siege_commune, societeLink.siege_ville].filter(Boolean).join(', ') || '—'}
                                            />
                                            <RecapField
                                                label="Fiche du registre"
                                                value={societeLink.dossier_origine?.reference
                                                    ? `Constituée par ${societeLink.dossier_origine.reference}`
                                                    : 'Ajoutée manuellement'}
                                            />
                                        </div>
                                    </RecapCard>
                                )}

                                {/* Conséquences des modifications décidées — actes à produire et
                                    droits d'enregistrement, revus une dernière fois avant création. */}
                                {(formValues['modif.types']?.length ?? 0) > 0 && (
                                    <RecapCard icon={Edit2} iconColor="text-amber-600" iconBg="bg-amber-50" title="Ce que la modification va produire" onEdit={() => setStep(1)}>
                                        <ConsequencesModification
                                            typesModification={typesModification}
                                            selection={formValues['modif.types']}
                                            valeurPartsCedees={formValues['modif.valeur_parts_cedees']}
                                        />
                                    </RecapCard>
                                )}

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
                                                                    value={
                                                                        typeof value === 'boolean' ? (value ? 'Oui' : 'Non')
                                                                        // Choix multiple : sans séparateur explicite, React
                                                                        // concatènerait les libellés bout à bout.
                                                                        : Array.isArray(value) ? value.join(' · ')
                                                                        : value
                                                                    }
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
                                                    <span className="font-medium text-slate-700">{clientDisplayName(p.client) || 'Client sans nom'}</span>
                                                    <span className="text-slate-400">· {p.role}</span>
                                                </span>
                                            ))}
                                            {dossierClientsDisponibles.map((p, i) => (
                                                <span key={`dispo-${i}`} className="inline-flex items-center gap-1.5 text-xs bg-seal-light border border-seal/30 rounded-full px-3 py-1">
                                                    <span className="font-medium text-slate-700">{clientDisplayName(p.client) || 'Client sans nom'}</span>
                                                    <span className="text-slate-400">· réutilisé ci-dessus</span>
                                                </span>
                                            ))}
                                        </div>
                                    </RecapCard>
                                )}

                                {/* Actes à produire — calculés par le serveur pour ce type d'acte ET
                                    les variantes décidées. Cette carte listait auparavant tous les
                                    modèles du type d'acte : elle annonçait donc des actes qui ne
                                    seraient pas produits, et taisait les gabarits manquants. */}
                                <RecapCard icon={ClipboardCheck} iconColor="text-seal" iconBg="bg-seal-light" title="Actes à produire">
                                    {actesPrevus === null ? (
                                        <p className="text-xs text-slate-400">Calcul en cours…</p>
                                    ) : actesPrevus.length === 0 ? (
                                        <p className="text-xs text-slate-400">
                                            Aucun acte ne sera produit pour cette procédure.
                                        </p>
                                    ) : (
                                        <div className="space-y-1">
                                            {actesPrevus.map((a, i) => (
                                                <div key={i} className="flex items-start gap-1.5 text-xs">
                                                    {a.a_gabarit
                                                        ? <CheckCircle2 className="mt-0.5 h-3 w-3 shrink-0 text-success" />
                                                        : <AlertCircle className="mt-0.5 h-3 w-3 shrink-0 text-warning-text" />}
                                                    <span className={a.a_gabarit ? 'text-slate-600' : 'text-warning-text'}>
                                                        {a.nom}
                                                        {!a.a_gabarit && (
                                                            <span className="text-slate-400"> — aucun gabarit configuré</span>
                                                        )}
                                                    </span>
                                                </div>
                                            ))}
                                            {actesPrevus.some(a => !a.a_gabarit) && (
                                                <p className="pt-1 text-xs text-slate-400">
                                                    Les documents sans gabarit ne seront pas produits.{' '}
                                                    <a href="/parametres/types-actes" target="_blank" rel="noreferrer" className="text-seal-hover underline">
                                                        Configurer les modèles
                                                    </a>
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </RecapCard>

                                {/* Dernière sortie avant création : le récapitulatif est
                                    l'endroit où l'on constate qu'une information manque
                                    encore — il faut pouvoir mettre de côté sans perdre
                                    la saisie ni créer un dossier incomplet. */}
                                <div className="flex flex-col gap-3 rounded-lg border border-slate-200 bg-slate-50/70 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium text-slate-700">Pas encore prêt à créer le dossier ?</p>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            Enregistrez un brouillon : la saisie et les pièces déjà téléversées
                                            sont conservées, et aucune référence de dossier n'est attribuée.
                                        </p>
                                    </div>
                                    <div className="shrink-0 sm:text-right">
                                        <BoutonBrouillon className="gap-1.5" label="Enregistrer et reprendre plus tard" />
                                        {brouillonEnregistreA && (
                                            <p className="mt-1 text-[11px] text-slate-400">
                                                Enregistré à {brouillonEnregistreA}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                            );
                        })()}

                    </motion.div>
                </AnimatePresence>

                {/* Navigation — collante en bas du conteneur défilant (<main> dans
                    AppLayout) : sur un questionnaire long, le bouton d'enregistrement
                    du brouillon disparaissait dès qu'on faisait défiler la page. */}
                <div className="sticky bottom-0 -mx-6 border-t border-slate-200 bg-app-bg/95 px-6 py-3 backdrop-blur">
                    {/* Ce qui manque, au-dessus de la barre : déroulé sur clic du compteur, ou
                        automatiquement quand on tente d'avancer sans avoir tout complété. */}
                    <BlocantsPanel
                        blocants={blocants}
                        ouvert={blocantsOuverts}
                        onFermer={() => setBlocantsOuverts(false)}
                    />

                    <div className="flex items-center justify-between gap-3">
                        <Button variant="outline" onClick={prev} disabled={step === 0} size="lg">
                            <ChevronLeft className="h-4 w-4" />
                            Précédent
                        </Button>

                        <div className="flex flex-1 items-center justify-center gap-4">
                            <BoutonBrouillon className="gap-1.5" />
                            {step < 2 && (
                                <CompteurBlocants
                                    blocants={blocants}
                                    ouvert={blocantsOuverts}
                                    onBasculer={() => setBlocantsOuverts(o => !o)}
                                />
                            )}
                        </div>

                        {step < 2 ? (
                            // Jamais `disabled` : un bouton grisé muet est précisément ce qui rendait
                            // le blocage indéchiffrable. Cliquer avec des manques ne fait pas avancer,
                            // mais dit lesquels et emmène au premier.
                            <Button
                                size="lg"
                                onClick={next}
                                variant={blocants.length > 0 ? 'outline' : 'default'}
                                title={blocants.length > 0
                                    ? `${blocants.length} élément(s) à compléter — cliquez pour voir lesquels`
                                    : 'Passer à l\'étape suivante'}
                            >
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

            <ConfirmDialog
                open={brouillonASupprimer !== null}
                onClose={() => setBrouillonASupprimer(null)}
                title="Supprimer ce brouillon ?"
                description={brouillonASupprimer
                    ? `« ${brouillonASupprimer.libelle || brouillonASupprimer.typeActeLabel || 'Dossier sans objet'} » sera définitivement supprimé`
                      + (brouillonASupprimer.nbPieces > 0
                          ? `, ainsi que ${brouillonASupprimer.nbPieces} pièce(s) justificative(s) déjà téléversée(s).`
                          : '.')
                    : ''}
                confirmLabel="Supprimer"
                onConfirm={() => supprimerBrouillon(brouillonASupprimer.id)}
            />

            {/* Correction en place d'une fiche déjà rattachée à un rôle — « si on veut
                le modifier, on le modifie de suite », sans quitter l'assistant. */}
            <ModalNouveauClient
                open={editingClient !== null}
                client={editingClient?.client ?? null}
                onClose={() => setEditingClient(null)}
                onCreated={(client) => {
                    // Réapplique la fiche corrigée partout où elle est référencée :
                    // le pool du dossier et tous les rôles qui la désignent.
                    setClientLinks(prev => Object.fromEntries(
                        Object.entries(prev).map(([role, c]) => [role, c?.id === client.id ? client : c])
                    ));
                    setDossierClients(prev => prev.map(p =>
                        p.client?.id === client.id ? { ...p, client } : p
                    ));
                    setEditingClient(null);
                }}
            />
        </AppLayout>
    );
}
