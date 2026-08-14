import { useRef, useState } from 'react';
import axios from 'axios';
import { AlertCircle, CheckCircle2, Eye, ExternalLink, FileText, RefreshCw, Square, Upload, X } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ApercuSousLigne, useApercuEnLigne } from '@/Components/documents/ApercuSousLigne';
import { toast } from '@/lib/toast';
import { cn } from '@/lib/utils';

/**
 * Dossier constitutif d'une société du registre : statuts en vigueur, RCCM, NIF…
 *
 * N'a de sens que pour une société que l'étude **n'a pas constituée** (`exige_pieces`) : pour les
 * autres, le dossier d'origine contient déjà tout, et la carte se contente d'y renvoyer.
 *
 * Un seul composant pour deux emplacements — l'assistant de création et la fiche dossier : c'est le
 * même geste au même objet, et la fiche société existe déjà en base dans les deux cas, donc le
 * téléversement est direct (aucun mécanisme de *staging* comme pour les pièces d'identité, qui elles
 * dépendent d'un dossier pas encore créé).
 *
 * L'aperçu s'ouvre **sous la ligne** concernée — motif imposé partout depuis le 2026-08-04.
 *
 * ⚠️ Téléversement en **axios** et non par une visite Inertia : dans l'assistant, une visite
 * rechargerait `/dossiers/create` et perdrait toute la saisie en cours. Le rafraîchissement est donc
 * délégué à l'appelant via `onRafraichir` — `router.reload({ only: ['societe'] })` dans la fiche
 * dossier, un rappel de `/societes/{id}` dans l'assistant.
 */
export function PiecesConstitutivesCard({ societe, dossierReference, modifiable = true, compact = false, onRafraichir }) {
    const apercu = useApercuEnLigne();
    const [enCours, setEnCours] = useState(null);

    if (!societe) return null;

    // Société constituée par l'étude : rien à fournir, mais on dit où trouver son dossier.
    if (!societe.exige_pieces) {
        const origine = societe.dossier_origine?.reference;

        return (
            <Encadre compact={compact}>
                <p className="flex items-start gap-2 text-xs text-slate-500">
                    <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-success" />
                    <span>
                        Société constituée par l'étude — son dossier constitutif (statuts, PV, RCCM,
                        DNSV) se trouve dans son dossier d'origine.
                        {origine && (
                            <>
                                {' '}
                                <a
                                    href={`/dossiers/${origine}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="inline-flex items-center gap-1 text-seal-hover hover:underline"
                                >
                                    <ExternalLink className="h-3 w-3" />
                                    {origine}
                                </a>
                            </>
                        )}
                    </span>
                </p>
            </Encadre>
        );
    }

    const pieces = societe.pieces ?? [];
    const manquantes = pieces.filter(p => p.requis && !p.aUnFichier);
    const statuts = pieces.find(p => p.categorie === 'statuts');

    const televerser = async (categorie, fichier) => {
        if (!fichier) return;

        const form = new FormData();
        form.append('fichier', fichier);
        if (dossierReference) form.append('dossier_reference', dossierReference);

        setEnCours(categorie);
        try {
            await axios.post(`/societes/${societe.id}/pieces/${categorie}`, form);
            await onRafraichir?.();
            toast.success('Pièce versée au dossier constitutif.');
        } catch (err) {
            toast.error(
                err.response?.status === 422
                    ? 'Format refusé — acceptés : .docx, .doc, .pdf, .jpg, .png (20 Mo max).'
                    : "La pièce n'a pas pu être versée — réessayez.",
            );
        } finally {
            setEnCours(null);
        }
    };

    const retirer = async (piece) => {
        setEnCours(piece.categorie);
        try {
            await axios.delete(`/societes/pieces/${piece.id}`, {
                data: { dossier_reference: dossierReference },
            });
            await onRafraichir?.();
            toast.success('Pièce retirée.');
        } catch {
            toast.error("La pièce n'a pas pu être retirée — réessayez.");
        } finally {
            setEnCours(null);
        }
    };

    return (
        <Encadre compact={compact}>
            <p className="mb-3 text-xs text-slate-500">
                Cette société n'a pas été constituée par l'étude : versez son dossier constitutif au
                registre. Il sera réutilisé à chacune de ses modifications futures.
            </p>

            {manquantes.length > 0 && (
                <p className="mb-3 flex items-start gap-1.5 rounded-md border border-red-200 bg-danger-bg p-2.5 text-xs text-danger-text">
                    <AlertCircle className="mt-0.5 h-3 w-3 shrink-0" />
                    <span>
                        <strong>Pièces obligatoires manquantes :</strong>{' '}
                        {manquantes.map(p => p.label).join(', ')}. Le dossier ne pourra pas quitter
                        l'Initialisation sans elles.
                    </span>
                </p>
            )}

            <div className="divide-y divide-slate-100">
                {pieces.map(piece => (
                    <LignePiece
                        key={piece.categorie}
                        piece={piece}
                        modifiable={modifiable}
                        occupe={enCours === piece.categorie}
                        apercuOuvert={apercu.estOuvert(piece.categorie)}
                        onBasculerApercu={() => apercu.basculer(piece.categorie)}
                        onFichier={(f) => televerser(piece.categorie, f)}
                        onRetirer={() => retirer(piece)}
                    />
                ))}
            </div>

            {/* Le clerc doit savoir AVANT la génération si les statuts serviront de gabarit — pas
                le découvrir en ouvrant l'acte produit. */}
            {statuts?.aUnFichier && (
                <p className={cn(
                    'mt-3 flex items-start gap-1.5 rounded-md p-2.5 text-xs',
                    statuts.exploitableCommeGabarit
                        ? 'border border-success/30 bg-success-bg text-success-text'
                        : 'border border-amber-200 bg-warning-bg text-warning-text',
                )}>
                    <FileText className="mt-0.5 h-3 w-3 shrink-0" />
                    <span>
                        {statuts.exploitableCommeGabarit
                            ? 'Les statuts déposés serviront de base au document « Statuts mis à jour » : vous partirez du texte réel de la société, à reprendre article par article.'
                            : "Les statuts déposés ne sont pas un .docx : ils ne peuvent pas servir de gabarit. Le document « Statuts mis à jour » sera produit depuis le modèle de l'étude. Déposez une version .docx pour partir du texte réel."}
                    </span>
                </p>
            )}
        </Encadre>
    );
}

function Encadre({ compact, children }) {
    if (compact) {
        return <div className="rounded-lg border border-slate-200 bg-white p-4">{Entete()}{children}</div>;
    }

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2">
                    <FileText className="h-4 w-4 text-blue-600" />
                    Dossier constitutif de la société
                </CardTitle>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );

    function Entete() {
        return (
            <div className="mb-3 flex items-center gap-2">
                <span className="flex h-6 w-6 items-center justify-center rounded-md bg-blue-50">
                    <FileText className="h-3.5 w-3.5 text-blue-600" />
                </span>
                <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Dossier constitutif de la société
                </h4>
            </div>
        );
    }
}

function LignePiece({ piece, modifiable, occupe, apercuOuvert, onBasculerApercu, onFichier, onRetirer }) {
    const inputRef = useRef(null);
    const fournie = piece.aUnFichier;

    return (
        <div className="py-1.5">
            <div className="flex items-center gap-2">
                <span className="shrink-0" title={fournie ? 'Pièce fournie' : 'Pièce manquante'}>
                    {fournie
                        ? <CheckCircle2 className="h-4 w-4 text-success" />
                        : <Square className={cn('h-4 w-4', piece.requis ? 'text-danger/50' : 'text-slate-300')} />}
                </span>

                <span className={cn('flex-1 text-sm', fournie ? 'text-slate-500' : 'text-slate-700')}>
                    {piece.label}
                    {piece.requis && <span className="ml-1 text-danger">*</span>}
                    {fournie && piece.nom_original && (
                        <span className="ml-1.5 text-xs text-slate-400">
                            — {piece.nom_original}
                            {piece.version > 1 && <span className="text-slate-300"> (v{piece.version})</span>}
                        </span>
                    )}
                </span>

                <div className="flex shrink-0 items-center gap-3">
                    {fournie && (
                        <button
                            type="button"
                            onClick={onBasculerApercu}
                            className="flex items-center gap-1 text-xs text-seal hover:underline"
                        >
                            <Eye className="h-3 w-3" /> {apercuOuvert ? 'Masquer' : 'Aperçu'}
                        </button>
                    )}

                    {modifiable && (
                        <>
                            <input
                                ref={inputRef}
                                type="file"
                                className="hidden"
                                accept=".docx,.doc,.pdf,.jpg,.jpeg,.png"
                                onChange={(e) => {
                                    onFichier(e.target.files?.[0] || null);
                                    e.target.value = '';
                                }}
                            />
                            <button
                                type="button"
                                disabled={occupe}
                                onClick={() => inputRef.current?.click()}
                                className="flex items-center gap-1 text-xs text-slate-400 transition-colors hover:text-seal disabled:opacity-50"
                            >
                                {fournie
                                    ? <><RefreshCw className="h-3 w-3" /> Remplacer</>
                                    : <><Upload className="h-3 w-3" /> Téléverser</>}
                            </button>
                            {fournie && (
                                <button
                                    type="button"
                                    disabled={occupe}
                                    onClick={onRetirer}
                                    className="flex items-center gap-1 text-xs text-danger-text hover:underline disabled:opacity-50"
                                >
                                    <X className="h-3 w-3" /> Retirer
                                </button>
                            )}
                        </>
                    )}
                </div>
            </div>

            <ApercuSousLigne
                ouvert={apercuOuvert && fournie}
                doc={{ id: piece.id, nom: piece.label, version: piece.version, chemin_fichier: piece.chemin_fichier }}
                onFermer={onBasculerApercu}
                decalage="ml-6"
            />
        </div>
    );
}
