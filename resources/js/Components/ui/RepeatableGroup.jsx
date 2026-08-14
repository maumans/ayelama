import { useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { DateField } from '@/Components/ui/date-field';
import { NumberField } from '@/Components/ui/number-field';
import { PhoneField } from '@/Components/ui/phone-field';
import { ClientRoleSection } from '@/Components/ui/client-role-section';
import { ModalNouveauClient } from '@/Components/ModalNouveauClient';
import { mapClientToRepeatableItem, estChampIdentite } from '@/lib/clientFields';
import { getVisibleFields } from '@/data/questionnaires';
import { PieceGedRow } from '@/Components/Formalites/PieceGedRow';
import { PieceStagedRow } from '@/Components/ui/PieceStagedRow';

/**
 * RepeatableGroup — bloc de formulaire répétable pour associés, gérants, administrateurs, etc.
 *
 * Props :
 *   fieldDef     — définition du champ repeatable depuis questionnaires.js (fieldDef.clientRole
 *                  active le sélecteur de client par item)
 *   value        — tableau des items actuels (ex. [{nom:'...', parts_chiffres:'100'}, ...])
 *   onChange     — callback(newArray) appelé à chaque modification
 *   readOnly     — si true, affiche sans contrôles d'édition
 *   partiesById  — map id → Partie (avec piecesChecklist), pour afficher la checklist de
 *                  pièces requises sous l'item déjà rattaché à une Partie réelle (item.partie_id
 *                  — voir attachPartieIds() dans Show.jsx). Absent/vide pour Create.jsx, où
 *                  aucune Partie n'existe encore avant la création du dossier.
 */
export function RepeatableGroup({ fieldDef, value = [], onChange, readOnly = false, poolClients = [], onClientCreated, partiesById = {}, piecesRequises = {}, stagedPieces = {}, onStagedPieceChange, piecesBrouillon = {}, onRetirerPieceBrouillon }) {
    const { fields = [], min = 1, max = 10, label, clientRole } = fieldDef;
    const [creatingForIndex, setCreatingForIndex] = useState(null);
    const [editingIndex, setEditingIndex] = useState(null);
    const [previewKey, setPreviewKey] = useState(null);
    const togglePreview = (partieId, categorie) => {
        const key = `${partieId}:${categorie}`;
        setPreviewKey(k => k === key ? null : key);
    };

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

    /**
     * Rattache une fiche client à cette ligne.
     *
     * On conserve l'objet client complet dans `item.client` — avant, seul
     * `client_id` était gardé et la carte du sélecteur devait reconstruire un
     * pseudo-client depuis les champs de la ligne, d'où un affichage appauvri.
     *
     * `mapClientToRepeatableItem` reste appliqué pour que `donnees` porte les clés
     * attendues par les modèles Word même avant l'enregistrement (aperçu, étape
     * récapitulative) ; côté serveur, ClientProjectionService fait autorité.
     */
    const applyClient = (idx, client) => {
        const fieldIds = fields.map(f => f.id);
        const mapped = mapClientToRepeatableItem(client, fieldIds);
        const next = value.map((item, i) =>
            i === idx ? { ...item, ...mapped, client, client_id: client.id, __saisieLibre: false } : item
        );
        onChange(next);
    };

    const unlinkClient = (idx) => {
        const next = value.map((item, i) => {
            if (i !== idx) return item;
            const { client_id, client, ...rest } = item;
            return rest;
        });
        onChange(next);
    };

    const toggleSaisieLibre = (idx, actif) => {
        const next = value.map((item, i) => {
            if (i !== idx) return item;
            if (!actif) return { ...item, __saisieLibre: false };
            // Renoncer à la fiche : garder le lien laisserait deux vérités pour
            // la même personne sur cette ligne.
            const { client_id, client, ...rest } = item;
            return { ...rest, __saisieLibre: true };
        });
        onChange(next);
    };

    /**
     * Champs à afficher pour une ligne. Dès qu'une fiche est rattachée, l'identité
     * disparaît (portée par la fiche, résumée dans la carte) et seules les données
     * propres à l'acte restent : nombre de parts, fonction au CA, apport…
     */
    const champsAffichables = (item) => {
        const visibles = getVisibleFields(fields, item);
        if (!clientRole || !item.client) return visibles;
        return visibles.filter(f => !estChampIdentite(f.id));
    };

    // Champs d'identité obligatoires que la fiche rattachée ne renseigne pas : ils
    // sont masqués, donc invisiblement bloquants sans ce rappel (voir Create.jsx).
    const champsManquants = (item) => {
        if (!clientRole || !item.client) return [];
        return getVisibleFields(fields, item)
            .filter(f => f.required && estChampIdentite(f.id) && !item[f.id])
            .map(f => f.label);
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
                            {getVisibleFields(fields, item).map(f => item[f.id] ? (
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

                        {(() => {
                        const grille = (
                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {champsAffichables(item).map(f => (
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
                                    ) : f.type === 'year' ? (
                                        <Input
                                            type="text"
                                            inputMode="numeric"
                                            maxLength={4}
                                            value={item[f.id] ?? ''}
                                            onChange={e => {
                                                const v = e.target.value.replace(/\D/g, '').slice(0, 4);
                                                updateItem(idx, f.id, v);
                                            }}
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
                        );

                        if (!clientRole) return grille;

                        // Chaque ligne désigne une personne du répertoire : son identité
                        // vient de sa fiche, seules les données propres à l'acte de cette
                        // ligne (nombre de parts, fonction…) restent saisissables ici.
                        return (
                            <ClientRoleSection
                                roleLabel={`${label} ${idx + 1}`}
                                linked={item.client ?? null}
                                onSelect={(client) => applyClient(idx, client)}
                                onUnlink={() => unlinkClient(idx)}
                                onCreateNew={() => setCreatingForIndex(idx)}
                                onEditClient={() => setEditingIndex(idx)}
                                poolClients={poolClients}
                                champsManquants={champsManquants(item)}
                                saisieLibre={!!item.__saisieLibre}
                                onToggleSaisieLibre={(v) => toggleSaisieLibre(idx, v)}
                                readOnly={readOnly}
                            >
                                {grille}
                            </ClientRoleSection>
                        );
                        })()}

                        {(() => {
                            const partie = item.partie_id ? partiesById[item.partie_id] : null;
                            const checklist = partie?.piecesChecklist ?? [];
                            
                            // Si partie_id existe, c'est une Partie déjà enregistrée en BDD, on utilise PieceGedRow.
                            if (checklist.length) {
                                return (
                                    <div className="mt-3 pt-3 border-t border-gray-100 divide-y divide-gray-50">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                                            Pièces justificatives requises
                                        </p>
                                        {checklist.map(piece => (
                                            <PieceGedRow
                                                key={piece.categorie}
                                                piece={piece}
                                                peutGerer
                                                isPreviewOpen={previewKey === `${partie.id}:${piece.categorie}`}
                                                onTogglePreview={() => togglePreview(partie.id, piece.categorie)}
                                                uploadUrl={`/parties/${partie.id}/pieces/${piece.categorie}/televerser`}
                                                downloadUrl={`/documents/${piece.id}/download`}
                                            />
                                        ))}
                                    </div>
                                );
                            }

                            // Si partie_id n'existe pas, on est en création (ou ajout de nouvel item).
                            // On vérifie le clientRole de la section pour savoir quelles pièces demander.
                            let categorieRole = '';
                            if (clientRole === 'associe' || clientRole === 'associe_unique') {
                                categorieRole = item.type_personne === 'Personne morale' ? 'associe_morale' : 'associe_physique';
                            } else if (clientRole === 'gerant') {
                                categorieRole = 'gerant';
                            }
                            
                            const piecesRequisesSection = piecesRequises[categorieRole] ?? {};
                            const piecesKeys = Object.keys(piecesRequisesSection);
                            
                            if (piecesKeys.length > 0) {
                                return (
                                    <div className="mt-3 pt-3 border-t border-gray-100 divide-y divide-gray-50">
                                        <p className="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">
                                            Pièces justificatives requises
                                        </p>
                                        {piecesKeys.map(cat => {
                                            const key = `${idx}:${cat}`;
                                            const file = stagedPieces?.[key];
                                            return (
                                                <PieceStagedRow
                                                    key={cat}
                                                    piece={{ label: piecesRequisesSection[cat] }}
                                                    file={file}
                                                    fichierBrouillon={piecesBrouillon?.[key]}
                                                    onFileSelected={(f) => onStagedPieceChange?.(key, f)}
                                                    onRetirerBrouillon={() => onRetirerPieceBrouillon?.(key)}
                                                    isPreviewOpen={previewKey === key}
                                                    onTogglePreview={() => setPreviewKey(k => k === key ? null : key)}
                                                />
                                            );
                                        })}
                                    </div>
                                );
                            }
                            return null;
                        })()}
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
                <>
                    <ModalNouveauClient
                        open={creatingForIndex !== null}
                        onClose={() => setCreatingForIndex(null)}
                        onCreated={(client) => {
                            applyClient(creatingForIndex, client);
                            setCreatingForIndex(null);
                            onClientCreated?.(client);
                        }}
                    />
                    {/* Correction en place de la fiche rattachée à cette ligne */}
                    <ModalNouveauClient
                        open={editingIndex !== null}
                        client={editingIndex !== null ? (value[editingIndex]?.client ?? null) : null}
                        onClose={() => setEditingIndex(null)}
                        onCreated={(client) => {
                            // Réapplique la fiche corrigée sur toutes les lignes qui la
                            // désignent, pas seulement celle éditée.
                            const fieldIds = fields.map(f => f.id);
                            const mapped = mapClientToRepeatableItem(client, fieldIds);
                            onChange(value.map(item => item.client?.id === client.id
                                ? { ...item, ...mapped, client }
                                : item));
                            setEditingIndex(null);
                            onClientCreated?.(client);
                        }}
                    />
                </>
            )}
        </div>
    );
}
