import React, { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import GuestPublicLayout from '@/Layouts/GuestPublicLayout';
import { QUESTIONNAIRES, TYPE_ACTE_CODE_MAP } from '@/data/questionnaires';
import { getPublicIntakeFields, groupFieldsBySection } from '@/lib/partiesPayload';
import { buildPartieFields } from '@/lib/clientFields';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { DateField } from '@/components/ui/date-field';
import { NumberField } from '@/components/ui/number-field';
import { PhoneField } from '@/components/ui/phone-field';
import {
    Upload, Send, CheckCircle2, AlertTriangle, PenLine, ScanLine, Loader2,
} from 'lucide-react';
import { cn } from '@/lib/utils';

function Field({ field, value, onChange }) {
    if (field.type === 'select') {
        return (
            <select
                value={value || ''}
                onChange={e => onChange(e.target.value)}
                className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-seal"
            >
                <option value="">— Choisir —</option>
                {(field.options ?? []).map(opt => <option key={opt} value={opt}>{opt}</option>)}
            </select>
        );
    }
    if (field.type === 'date') {
        return <DateField value={value || ''} onValueChange={onChange} />;
    }
    if (field.type === 'number') {
        return (
            <NumberField
                decimals={field.decimals ?? 0}
                placeholder={field.placeholder}
                value={value || ''}
                onValueChange={onChange}
                className={cn(field.mono && 'font-ref')}
            />
        );
    }
    if (field.type === 'tel') {
        return <PhoneField placeholder={field.placeholder} value={value || ''} onValueChange={onChange} />;
    }
    if (field.type === 'textarea') {
        return (
            <textarea
                rows={3}
                placeholder={field.placeholder}
                value={value || ''}
                onChange={e => onChange(e.target.value)}
                className="w-full text-sm rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seal resize-none"
            />
        );
    }
    return (
        <Input
            type={field.type === 'email' ? 'email' : 'text'}
            placeholder={field.placeholder}
            value={value || ''}
            onChange={e => onChange(e.target.value)}
            className={cn(field.mono && 'font-ref')}
        />
    );
}

function FieldGroup({ fields, formValues, setFormValues }) {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
            {fields.map(field => (
                <div key={field.id} className={cn(field.type === 'textarea' && 'sm:col-span-2')}>
                    <Label htmlFor={field.id}>
                        {field.label}
                        {field.required && <span className="text-danger ml-1">*</span>}
                    </Label>
                    <div className="mt-1.5">
                        <Field
                            field={field}
                            value={formValues[field.id]}
                            onChange={val => setFormValues(prev => ({ ...prev, [field.id]: val }))}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

// Construit l'objet "partie" (nom, cni, telephone, adresse, email) à partir des
// valeurs saisies pour la section du rôle client — gère les deux formes de
// champs : préfixés (pp.*, acq.*…) ou aplatis depuis un bloc répétable
// (nom, cni, telephone… sans préfixe, ex. un associé de SARL).
function buildPartieFromRole(roleFields, formValues, role) {
    if (!roleFields.length) return null;
    const hasPrefix = roleFields[0].id.includes('.');
    const base = hasPrefix
        ? buildPartieFields(null, formValues, roleFields[0].id.split('.')[0])
        : {
            nom: formValues.nom || formValues.prenom_nom || '',
            cni: formValues.cni || formValues.piece_numero || null,
            telephone: formValues.telephone || null,
            adresse: formValues.adresse || formValues.domicile || null,
            email: formValues.email || null,
        };
    return { ...base, role };
}

export default function IntakeShow() {
    const { etat, demande, token } = usePage().props;

    const [mode, setMode] = useState('manuel');
    const [formValues, setFormValues] = useState({});
    const [objet, setObjet] = useState('');
    const [ocrLoading, setOcrLoading] = useState(false);
    const [ocrError, setOcrError] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});

    const questionnaireKey = demande ? TYPE_ACTE_CODE_MAP[demande.typeActe.code] : null;
    const questionnaire = questionnaireKey ? (QUESTIONNAIRES[questionnaireKey] ?? []) : [];
    const { roleFields, roleLabel, extraFields, repeatableFieldId } = getPublicIntakeFields(questionnaire, demande?.clientRole);
    const extraGroups = groupFieldsBySection(extraFields);

    const officeNom = 'Ayelema'; // branding statique côté page publique (pas de session pour lire les Settings)

    if (etat !== 'active') {
        const messages = {
            invalide: { icon: AlertTriangle, title: 'Lien invalide', text: "Ce lien de demande n'existe pas ou a été révoqué." },
            expiree:  { icon: AlertTriangle, title: 'Lien expiré', text: "Ce lien n'est plus valide. Merci de contacter l'office pour en obtenir un nouveau." },
            soumise:  { icon: CheckCircle2,  title: 'Demande transmise', text: "Merci, vos informations ont bien été transmises à l'office. Nous reviendrons vers vous prochainement." },
        };
        const m = messages[etat] ?? messages.invalide;
        const Icon = m.icon;
        return (
            <GuestPublicLayout officeNom={officeNom}>
                <Head title={m.title} />
                <div className="text-center py-6">
                    <Icon className={cn('h-10 w-10 mx-auto mb-3', etat === 'soumise' ? 'text-success' : 'text-amber-500')} />
                    <h1 className="font-serif text-heading text-ink mb-1">{m.title}</h1>
                    <p className="text-sm text-slate-500">{m.text}</p>
                </div>
            </GuestPublicLayout>
        );
    }

    const handleFichier = async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        setOcrLoading(true);
        setOcrError(false);

        const champs = [...roleFields, ...extraFields].map(f => ({ id: f.id, label: f.label }));
        const fd = new FormData();
        fd.append('fichier', file);
        champs.forEach((c, i) => {
            fd.append(`champs[${i}][id]`, c.id);
            fd.append(`champs[${i}][label]`, c.label);
        });

        try {
            const { data } = await axios.post(`/intake/${token}/ocr`, fd);
            if (data.donnees && Object.keys(data.donnees).length > 0) {
                setFormValues(prev => ({ ...prev, ...data.donnees }));
            } else {
                setOcrError(true);
            }
        } catch (err) {
            setOcrError(true);
        } finally {
            setOcrLoading(false);
        }
    };

    const canSubmit = objet.trim().length > 0 && (roleFields.length === 0 || roleFields.filter(f => f.required).every(f => formValues[f.id]));

    const handleSubmit = () => {
        setSubmitting(true);

        const pick = (fields) => Object.fromEntries(
            fields.map(f => [f.id, formValues[f.id]]).filter(([, v]) => v !== undefined && v !== '' && v !== null)
        );

        const donnees = pick(extraFields);
        const roleValues = pick(roleFields);
        if (repeatableFieldId) {
            donnees[repeatableFieldId] = [roleValues];
        } else {
            Object.assign(donnees, roleValues);
        }

        const partie = buildPartieFromRole(roleFields, formValues, demande.clientRole);

        router.post(`/intake/${token}`, {
            objet,
            donnees,
            parties: partie && partie.nom ? [partie] : [],
            source: mode,
        }, {
            onError: (errs) => { setErrors(errs); setSubmitting(false); },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <GuestPublicLayout officeNom={officeNom}>
            <Head title="Compléter votre dossier" />

            <div className="space-y-5">
                <div>
                    <h1 className="font-serif text-heading text-ink">Compléter vos informations</h1>
                    <p className="text-sm text-slate-500 mt-1">
                        {demande.typeActe.label}
                        {roleLabel && <> — <span className="font-medium">{roleLabel}</span></>}
                    </p>
                </div>

                {/* Bascule saisie manuelle / scan */}
                <div className="flex items-center gap-1 border border-slate-200 rounded-lg p-1 bg-slate-50 w-fit">
                    <button
                        type="button"
                        onClick={() => setMode('manuel')}
                        className={cn('flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-md transition-colors', mode === 'manuel' ? 'bg-white shadow-sm text-ink font-medium' : 'text-slate-500')}
                    >
                        <PenLine className="h-3.5 w-3.5" /> Saisir moi-même
                    </button>
                    <button
                        type="button"
                        onClick={() => setMode('ocr')}
                        className={cn('flex items-center gap-1.5 text-sm px-3 py-1.5 rounded-md transition-colors', mode === 'ocr' ? 'bg-white shadow-sm text-ink font-medium' : 'text-slate-500')}
                    >
                        <ScanLine className="h-3.5 w-3.5" /> Scanner un document
                    </button>
                </div>

                {mode === 'ocr' && (
                    <div className="rounded-lg border border-dashed border-slate-300 p-4 bg-slate-50/50">
                        <label className="flex flex-col items-center gap-2 cursor-pointer text-center">
                            {ocrLoading ? (
                                <Loader2 className="h-6 w-6 text-seal animate-spin" />
                            ) : (
                                <Upload className="h-6 w-6 text-slate-400" />
                            )}
                            <span className="text-sm text-slate-600">
                                {ocrLoading ? 'Analyse en cours…' : 'Photographiez votre pièce d\'identité ou une fiche déjà remplie'}
                            </span>
                            <span className="text-xs text-slate-400">JPG, PNG ou PDF — 10 Mo max</span>
                            <input type="file" accept=".jpg,.jpeg,.png,.pdf" className="hidden" onChange={handleFichier} disabled={ocrLoading} />
                        </label>
                        {ocrError && (
                            <p className="text-xs text-danger-text mt-2 text-center">
                                La lecture automatique a échoué — vérifiez les champs ci-dessous et complétez-les manuellement.
                            </p>
                        )}
                        {!ocrLoading && Object.keys(formValues).length > 0 && (
                            <p className="text-xs text-success mt-2 text-center">
                                Champs pré-remplis — merci de les relire avant d'envoyer.
                            </p>
                        )}
                    </div>
                )}

                {roleFields.length > 0 && (
                    <div className="rounded-lg border border-slate-200 bg-white p-4">
                        <FieldGroup fields={roleFields} formValues={formValues} setFormValues={setFormValues} />
                    </div>
                )}

                {extraGroups.map((group, gi) => (
                    <div key={gi} className="rounded-lg border border-slate-200 bg-white p-4">
                        {group.name && <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">{group.name}</p>}
                        <FieldGroup fields={group.fields} formValues={formValues} setFormValues={setFormValues} />
                    </div>
                ))}

                <div className="rounded-lg border border-slate-200 bg-white p-4">
                    <Label htmlFor="objet">Objet de votre demande <span className="text-danger">*</span></Label>
                    <textarea
                        id="objet"
                        rows={3}
                        placeholder="Décrivez brièvement ce que vous souhaitez faire…"
                        value={objet}
                        onChange={e => setObjet(e.target.value)}
                        className="mt-1.5 w-full text-sm rounded-lg border border-slate-200 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-seal resize-none"
                    />
                    {errors.objet && <p className="text-xs text-danger mt-1">{errors.objet}</p>}
                </div>

                <Button variant="seal" size="lg" className="w-full" onClick={handleSubmit} disabled={!canSubmit || submitting}>
                    <Send className="h-4 w-4" />
                    {submitting ? 'Envoi en cours…' : 'Envoyer ma demande'}
                </Button>
            </div>
        </GuestPublicLayout>
    );
}
