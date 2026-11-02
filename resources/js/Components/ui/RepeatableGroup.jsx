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
import { mapClientToRepeatableItem, estChampIdentite, personneDuChamp } from '@/lib/clientFields';
import { getVisibleFields, libelleRepresentant } from '@/data/questionnaires';
import { PieceGedRow } from '@/Components/Formalites/PieceGedRow';
import { PieceStagedRow } from '@/Components/ui/PieceStagedRow';
import { piecesRequisesPour } from '@/lib/piecesRequises';

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
/**
 * D'où vient cette ligne, et a-t-elle bougé depuis ?
 *
 * Muette sur une ligne saisie à la main : il n'y a rien à comparer.
 */
function MentionRegistre({ item }) {
    const origine = item?.__registre;

    if (!origine) return null;

    const actuelles = String(item.parts_chiffres ?? '');
    const reprises  = origine.nb_parts === null || origine.nb_parts === undefined ? '' : String(origine.nb_parts);

    if (actuelles === reprises) {
        return (
            <span className="rounded-full border border-slate-200 bg-slate-50 px-1.5 py-px text-[10px] font-normal text-slate-500">
                depuis le registre
            </span>
        );
    }

    return (
        <span className="rounded-full border border-amber-200 bg-amber-50 px-1.5 py-px text-[10px] font-normal text-amber-700">
            modifié — {reprises === '' ? 'absent' : reprises} au registre
        </span>
    );
}

export function RepeatableGroup({ fieldDef, value = [], onChange, readOnly = false, poolClients = [], onClientCreated, partiesById = {}, piecesRequises = {}, stagedPieces = {}, onStagedPieceChange, piecesBrouillon = {}, onRetirerPieceBrouillon, optionsDynamiques = {} }) {
    const { fields = [], min = 1, max = 10, label, clientRole } = fieldDef;

    /**
     * Les choix d'un champ : son tableau `options` figé, ou une liste `optionsDe` fournie par
     * l'écran — les associés que le registre connaît, par exemple.
     *
     * ⚠️ Une source inconnue rend une liste **vide** : le clerc verrait un sélecteur sans
     * aucun choix sans pouvoir comprendre pourquoi. Le contrôle H de
     * `verifier-sections-questionnaire.mjs` interdit qu'un `optionsDe` désigne une source que
     * personne ne fournit.
     */
    const optionsDe = (f) => {
        if (f.optionsDe) {
            return (optionsDynamiques[f.optionsDe] ?? []).map(o =>
                (typeof o === 'string' ? { valeur: o, label: o } : o));
        }

        return (f.options ?? []).map(o => ({ valeur: o, label: o }));
    };

    /**
     * Ce champ est-il en saisie libre sur cette ligne ?
     *
     * Un entrant ne figure dans aucune liste : il faut pouvoir sortir du sélecteur. Le
     * marqueur est posé **par ligne et par champ** (`__libre_associe`), comme
     * `__saisieLibre` l'est pour l'identité d'une personne.
     */
    const enSaisieLibre = (item, f) => !!item?.[`__libre_${f.id}`];
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
        if (!clientRole) return visibles;

        // Décidé **par personne** : une ligne peut en porter deux, l'associé et son
        // représentant, chacune avec ou sans fiche. Rattacher celle de l'associé ne doit pas
        // faire disparaître les champs du mandataire, qui n'en a pas. Même règle que dans
        // Create.jsx et dans la modale de Show.jsx — les trois moteurs de rendu doivent la
        // partager, c'est un point de contrôle du dépôt.
        const fichesLiees = {
            partie: item.client ?? null,
            representant: item.representant_client ?? null,
        };

        return visibles.filter(f => !estChampIdentite(f.id) || !fichesLiees[personneDuChamp(f.id)]);
    };

    // Champs d'identité obligatoires que la fiche rattachée ne renseigne pas : ils
    // sont masqués, donc invisiblement bloquants sans ce rappel (voir Create.jsx).
    const champsManquants = (item, personne = 'partie') => {
        const fiche = personne === 'representant' ? item.representant_client : item.client;
        if (!clientRole || !fiche) return [];

        return getVisibleFields(fields, item)
            .filter(f => f.required
                && estChampIdentite(f.id)
                && personneDuChamp(f.id) === personne
                && !item[f.id])
            .map(f => f.label);
    };

    /**
     * L'état des modales porte soit un index nu (la personne de la ligne), soit
     * `{ idx, cible }` (son représentant). Normalisé ici, à un seul endroit.
     */
    const cibleCreation = (etat) =>
        (typeof etat === 'object' && etat !== null) ? etat : { idx: etat, cible: 'partie' };

    const ficheEnEdition = () => {
        if (editingIndex === null) return null;
        const { idx, cible } = cibleCreation(editingIndex);
        return (cible === 'representant' ? value[idx]?.representant_client : value[idx]?.client) ?? null;
    };

    /** Rattache une fiche au représentant de CETTE ligne — miroir exact de `applyClient`. */
    const applyClientRepresentant = (idx, client) => {
        const mapped = mapClientToRepeatableItem(client, fields.map(f => f.id), 'repr_');
        onChange(value.map((item, i) =>
            i === idx
                ? { ...item, ...mapped, representant_client: client, representant_client_id: client.id }
                : item
        ));
    };

    const unlinkClientRepresentant = (idx) => {
        onChange(value.map((item, i) => {
            if (i !== idx) return item;
            const { representant_client, representant_client_id, ...reste } = item;
            return reste;
        }));
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
                            <span className="flex items-center gap-2 text-sm font-semibold text-seal">
                                {label} {idx + 1}
                                {/* ⚠️ La reprise se **voit**, et l'écart aussi. La composition du
                                    registre peut être périmée — elle n'est mise à jour qu'au
                                    retour de la formalité RCCM — et un préremplissage muet serait
                                    le réalignement silencieux que le dépôt refuse ailleurs. Dès
                                    que le clerc change les parts, la ligne rappelle ce que le
                                    registre disait : il n'a pas à s'en souvenir, et la relecture
                                    le voit aussi. */}
                                <MentionRegistre item={item} />
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
                                        {/* ⚠️ `readonly` n'était **pas lu ici**. `ChampQuestionnaire`
                                            l'honore pour les champs scalaires, ce rendu-ci l'ignorait :
                                            un champ calculé dans un bloc répétable restait librement
                                            éditable, et une saisie à la main écrasait le calcul sans
                                            rien dire. C'est ce qui empêchait de calculer le
                                            pourcentage de la répartition. */}
                                        {f.readonly && (
                                            <span className="ml-1.5 text-[10px] font-normal text-slate-400">calculé</span>
                                        )}
                                    </Label>
                                    {f.type === 'select' && !enSaisieLibre(item, f) ? (
                                        <>
                                            <select
                                                value={item[f.id] ?? ''}
                                                onChange={e => updateItem(idx, f.id, e.target.value)}
                                                disabled={!!f.readonly}
                                                className="w-full rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm focus:border-seal focus:outline-none focus:ring-1 focus:ring-seal disabled:bg-slate-50 disabled:text-slate-500"
                                            >
                                                <option value="">— Choisir —</option>
                                                {optionsDe(f).map(opt => (
                                                    <option key={opt.valeur} value={opt.valeur}>
                                                        {opt.label}{opt.origine === 'registre' ? ' — au registre' : ''}
                                                    </option>
                                                ))}
                                                {/* Une valeur héritée d'un dossier antérieur peut ne plus
                                                    figurer dans la liste. L'offrir quand même évite de
                                                    l'effacer en silence à la première ouverture. */}
                                                {item[f.id] && !optionsDe(f).some(o => o.valeur === item[f.id]) && (
                                                    <option value={item[f.id]}>{item[f.id]}</option>
                                                )}
                                            </select>
                                            {f.saisieLibreAutorisee && !readOnly && (
                                                <button
                                                    type="button"
                                                    onClick={() => updateItem(idx, `__libre_${f.id}`, true)}
                                                    className="mt-1 text-[11px] text-seal hover:underline"
                                                >
                                                    Saisir un autre nom
                                                </button>
                                            )}
                                        </>
                                    ) : f.type === 'select' ? (
                                        /* Échappement : la personne n'est dans aucune liste — un
                                           entrant, typiquement. On revient au texte, en le disant. */
                                        <>
                                            <Input
                                                type="text"
                                                value={item[f.id] ?? ''}
                                                onChange={e => updateItem(idx, f.id, e.target.value)}
                                                placeholder={f.placeholder ?? ''}
                                                className="text-sm"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => updateItem(idx, `__libre_${f.id}`, false)}
                                                className="mt-1 text-[11px] text-seal hover:underline"
                                            >
                                                Revenir à la liste
                                            </button>
                                        </>
                                    ) : f.type === 'date' ? (
                                        <DateField
                                            value={item[f.id] ?? ''}
                                            onValueChange={val => updateItem(idx, f.id, val)}
                                            disabled={!!f.readonly}
                                            className="text-sm"
                                        />
                                    ) : f.type === 'number' ? (
                                        <NumberField
                                            decimals={f.decimals ?? 0}
                                            value={item[f.id] ?? ''}
                                            onValueChange={val => updateItem(idx, f.id, val)}
                                            placeholder={f.placeholder ?? ''}
                                            disabled={!!f.readonly}
                                            className={`text-sm ${f.mono ? 'font-ref' : ''} ${f.readonly ? 'bg-slate-50 text-slate-500' : ''}`}
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
                                            disabled={!!f.readonly}
                                            className="text-sm"
                                        />
                                    ) : (
                                        <Input
                                            type={f.type === 'email' ? 'email' : 'text'}
                                            value={item[f.id] ?? ''}
                                            onChange={e => updateItem(idx, f.id, e.target.value)}
                                            placeholder={f.placeholder ?? ''}
                                            disabled={!!f.readonly}
                                            className={`text-sm ${f.mono ? 'font-ref' : ''} ${f.readonly ? 'bg-slate-50 text-slate-500' : ''}`}
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

                                {/* Le représentant de cette ligne. Imbriqué dans sa carte parce
                                    qu'il est un mode de comparution de cette personne-là, et non
                                    une ligne de plus au bloc : ajouter un associé et lui donner un
                                    mandataire sont deux gestes différents. */}
                                {item.est_represente && (
                                    <div className="mt-3 border-l-2 border-seal/25 pl-3">
                                        <ClientRoleSection
                                            roleLabel={libelleRepresentant(item.representation_motif)}
                                            linked={item.representant_client ?? null}
                                            onSelect={(client) => applyClientRepresentant(idx, client)}
                                            onUnlink={() => unlinkClientRepresentant(idx)}
                                            onCreateNew={() => setCreatingForIndex({ idx, cible: 'representant' })}
                                            onEditClient={() => setEditingIndex({ idx, cible: 'representant' })}
                                            poolClients={poolClients}
                                            champsManquants={champsManquants(item, 'representant')}
                                            readOnly={readOnly}
                                        />
                                    </div>
                                )}
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
                            // La règle « rôle (+ type de personne) → pièces » vient du serveur et
                            // n'est plus recomposée ici : cette copie ne couvrait que 3 rôles sur 7,
                            // laissant cédants, cessionnaires, souscripteurs et gérants entrants
                            // sans aucune pièce à l'écran alors que le serveur les exige ensuite.
                            const piecesRequisesSection = piecesRequisesPour(piecesRequises, {
                                role: clientRole,
                                typePersonne: item.type_personne,
                            });
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
                            // L'état porte soit un index (la personne de la ligne), soit
                            // `{ idx, cible }` (son représentant). Une seule modale pour les
                            // deux : le formulaire de fiche est identique, et deux états
                            // auraient été deux choses à tenir en phase.
                            const { idx, cible } = cibleCreation(creatingForIndex);
                            (cible === 'representant' ? applyClientRepresentant : applyClient)(idx, client);
                            setCreatingForIndex(null);
                            onClientCreated?.(client);
                        }}
                    />
                    {/* Correction en place de la fiche rattachée à cette ligne */}
                    <ModalNouveauClient
                        open={editingIndex !== null}
                        client={ficheEnEdition()}
                        onClose={() => setEditingIndex(null)}
                        onCreated={(client) => {
                            // Réapplique la fiche corrigée sur toutes les lignes qui la
                            // désignent, pas seulement celle éditée — et dans les deux rôles
                            // qu'une ligne peut lui donner : la personne, ou son représentant.
                            const fieldIds = fields.map(f => f.id);
                            onChange(value.map(item => {
                                let suivant = item;
                                if (item.client?.id === client.id) {
                                    suivant = { ...suivant, ...mapClientToRepeatableItem(client, fieldIds), client };
                                }
                                if (item.representant_client?.id === client.id) {
                                    suivant = {
                                        ...suivant,
                                        ...mapClientToRepeatableItem(client, fieldIds, 'repr_'),
                                        representant_client: client,
                                    };
                                }
                                return suivant;
                            }));
                            setEditingIndex(null);
                            onClientCreated?.(client);
                        }}
                    />
                </>
            )}
        </div>
    );
}
