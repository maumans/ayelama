import { useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { DateField } from '@/Components/ui/date-field';
import { NumberField } from '@/Components/ui/number-field';
import { PhoneField } from '@/Components/ui/phone-field';
import { ClientPicker } from '@/Components/ui/client-picker';
import { ModalNouveauClient } from '@/Components/ModalNouveauClient';
import { mapClientToRepeatableItem } from '@/lib/clientFields';

/**
 * RepeatableGroup — bloc de formulaire répétable pour associés, gérants, administrateurs, etc.
 *
 * Props :
 *   fieldDef   — définition du champ repeatable depuis questionnaires.js (fieldDef.clientRole
 *                active le sélecteur de client par item)
 *   value      — tableau des items actuels (ex. [{nom:'...', parts_chiffres:'100'}, ...])
 *   onChange   — callback(newArray) appelé à chaque modification
 *   readOnly   — si true, affiche sans contrôles d'édition
 */
export function RepeatableGroup({ fieldDef, value = [], onChange, readOnly = false }) {
    const { fields = [], min = 1, max = 10, label, clientRole } = fieldDef;
    const [creatingForIndex, setCreatingForIndex] = useState(null);

    const emptyItem = () => Object.fromEntries(fields.map(f => [f.id, '']));

    const addItem = () => {
        if (value.length >= max) return;
        onChange([...value, emptyItem()]);
    };

    const removeItem = (idx) => {
        if (value.length <= min) return;
        onChange(value.filter((_, i) => i !== idx));
    };

    const updateItem = (idx, fieldId, fieldValue) => {
        const next = value.map((item, i) =>
            i === idx ? { ...item, [fieldId]: fieldValue } : item
        );
        onChange(next);
    };

    const applyClient = (idx, client) => {
        const fieldIds = fields.map(f => f.id);
        const mapped = mapClientToRepeatableItem(client, fieldIds);
        const next = value.map((item, i) =>
            i === idx ? { ...item, ...mapped, client_id: client.id } : item
        );
        onChange(next);
    };

    const unlinkClient = (idx) => {
        const next = value.map((item, i) => {
            if (i !== idx) return item;
            const { client_id, ...rest } = item;
            return rest;
        });
        onChange(next);
    };

    if (readOnly) {
        return (
            <div className="space-y-3">
                {value.map((item, idx) => (
                    <div key={idx} className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-seal">
                            {label} {idx + 1}
                        </p>
                        <dl className="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                            {fields.map(f => item[f.id] ? (
                                <div key={f.id} className="contents">
                                    <dt className="text-gray-500">{f.label}</dt>
                                    <dd className={`text-gray-900 ${f.mono ? 'font-ref' : ''}`}>{item[f.id]}</dd>
                                </div>
                            ) : null)}
                        </dl>
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div className="space-y-3">
            <AnimatePresence initial={false}>
                {value.map((item, idx) => (
                    <motion.div
                        key={idx}
                        initial={{ opacity: 0, y: -6 }}
                        animate={{ opacity: 1, y: 0 }}
                        exit={{ opacity: 0, y: -6 }}
                        transition={{ duration: 0.18 }}
                        className="rounded-lg border border-seal-light bg-white p-4 shadow-sm"
                    >
                        <div className="mb-3 flex items-center justify-between">
                            <span className="text-sm font-semibold text-seal">
                                {label} {idx + 1}
                            </span>
                            {value.length > min && (
                                <button
                                    type="button"
                                    onClick={() => removeItem(idx)}
                                    className="flex items-center gap-1 rounded px-2 py-1 text-xs text-red-600 hover:bg-red-50"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                    Supprimer
                                </button>
                            )}
                        </div>

                        {clientRole && (
                            <div className="mb-3">
                                <ClientPicker
                                    placeholder="Rechercher un client existant pour cette personne…"
                                    linked={item.client_id ? { id: item.client_id, type: item.type_personne === 'Personne morale' ? 'morale' : 'physique', prenom_nom: item.nom, denomination: item.nom, piece_numero: item.cni, telephone: item.telephone } : null}
                                    onUnlink={() => unlinkClient(idx)}
                                    onSelect={(client) => applyClient(idx, client)}
                                    onCreateNew={() => setCreatingForIndex(idx)}
                                />
                            </div>
                        )}

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {fields.map(f => (
                                <div key={f.id} className={f.type === 'select' ? '' : ''}>
                                    <Label className="mb-1 block text-xs text-gray-600">
                                        {f.label}
                                        {f.required && <span className="ml-1 text-red-500">*</span>}
                                    </Label>
                                    {f.type === 'select' ? (
                                        <select
                                            value={item[f.id] ?? ''}
                                            onChange={e => updateItem(idx, f.id, e.target.value)}
                                            className="w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm focus:border-seal focus:outline-none focus:ring-1 focus:ring-seal"
                                        >
                                            <option value="">— Choisir —</option>
                                            {(f.options ?? []).map(opt => (
                                                <option key={opt} value={opt}>{opt}</option>
                                            ))}
                                        </select>
                                    ) : f.type === 'date' ? (
                                        <DateField
                                            value={item[f.id] ?? ''}
                                            onValueChange={val => updateItem(idx, f.id, val)}
                                            className="text-sm"
                                        />
                                    ) : f.type === 'number' ? (
                                        <NumberField
                                            decimals={f.decimals ?? 0}
                                            value={item[f.id] ?? ''}
                                            onValueChange={val => updateItem(idx, f.id, val)}
                                            placeholder={f.placeholder ?? ''}
                                            className={`text-sm ${f.mono ? 'font-ref' : ''}`}
                                        />
                                    ) : f.type === 'tel' ? (
                                        <PhoneField
                                            value={item[f.id] ?? ''}
                                            onValueChange={val => updateItem(idx, f.id, val)}
                                            placeholder={f.placeholder ?? ''}
                                            className="text-sm"
                                        />
                                    ) : (
                                        <Input
                                            type={f.type === 'email' ? 'email' : 'text'}
                                            value={item[f.id] ?? ''}
                                            onChange={e => updateItem(idx, f.id, e.target.value)}
                                            placeholder={f.placeholder ?? ''}
                                            className={`text-sm ${f.mono ? 'font-ref' : ''}`}
                                        />
                                    )}
                                </div>
                            ))}
                        </div>
                    </motion.div>
                ))}
            </AnimatePresence>

            {value.length < max && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={addItem}
                    className="flex items-center gap-2 border-dashed border-seal text-seal hover:bg-seal-light"
                >
                    <Plus className="h-4 w-4" />
                    Ajouter {label.toLowerCase()}
                </Button>
            )}

            {value.length >= max && (
                <p className="text-xs text-gray-400">Maximum {max} {label.toLowerCase()} atteint.</p>
            )}

            {clientRole && (
                <ModalNouveauClient
                    open={creatingForIndex !== null}
                    onClose={() => setCreatingForIndex(null)}
                    onCreated={(client) => {
                        applyClient(creatingForIndex, client);
                        setCreatingForIndex(null);
                    }}
                />
            )}
        </div>
    );
}
