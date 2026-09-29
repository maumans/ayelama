import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { CheckCircle2, AlertTriangle, Zap } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

import { Label } from '@/components/ui/label';
import { DateField } from '@/components/ui/date-field';
import { isoDateToFR, frDateToISO } from '@/lib/dates';
import { notifyValidationError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { PieceGedRow } from '@/Components/Formalites/PieceGedRow';

function todayISO() {
    return new Date().toISOString().slice(0, 10);
}

export function ModalRetourFormalite({ open, onClose, formalite }) {
    const [dateRetour, setDateRetour] = useState(todayISO());
    const [resultat, setResultat]     = useState('recu');
    const [motifRejet, setMotifRejet] = useState('');
    const [reference, setReference]   = useState('');
    // Clés = valeurs de `DonneeAuRetour`, servies par le serveur. **Aucun nom de donnée n'est
    // écrit en JavaScript** : le jour où l'étude en ajoute une, ce fichier ne bouge pas. Même
    // parti que `piecesRequises.js`, qui ne porte aucun nom de rôle.
    const [donnees, setDonnees]       = useState({});

    const attendues = formalite?.donnees_au_retour ?? [];

    useEffect(() => {
        if (open && formalite?.id) {
            setDateRetour(todayISO());
            setResultat('recu');
            setMotifRejet('');
            setReference(formalite.reference_document_recu ?? '');
            // ⚠️ On repart des valeurs **déjà captées**, et non d'un objet vide : une donnée
            // saisie lors d'un premier enregistrement doit être réémise, sinon la prochaine
            // soumission l'efface. Le cas se présente dès qu'un administrateur retire une
            // donnée du barème après coup — elle disparaît de `attendues` mais reste en base.
            setDonnees({ ...(formalite.donnees_recues ?? {}) });
        }
    }, [open, formalite?.id]);

    const [previewPieceId, setPreviewPieceId] = useState(null);

    if (!formalite) return null;

    const pieces = formalite.pieces ?? [];
    const piecesManquantes = pieces.filter(p => !p.est_fourni).length;
    // Un **rejet** n'apporte pas les pièces attendues : l'exiger le rendrait inenregistrable.
    const peutConfirmer = resultat === 'rejete'
        ? motifRejet.trim().length > 0
        : piecesManquantes === 0;

    // Données déjà captées qui ne sont plus déclarées : affichées en lecture, et réémises.
    const clesAttendues = attendues.map(d => d.valeur);
    const orphelines = Object.entries(donnees)
        .filter(([cle, valeur]) => !clesAttendues.includes(cle) && valeur);

    const submit = (e) => {
        e.preventDefault();
        router.post(`/formalites/${formalite.id}/retour`, {
            resultat,
            date_retour: dateRetour,
            motif_rejet: resultat === 'rejete' ? motifRejet : null,
            reference_document_recu: reference || null,
            donnees_recues: donnees,
        }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: notifyValidationError,
        });
    };

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Enregistrer un retour — {formalite.libelle}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4 pt-1">

                    {/* Ce que l'organisme doit rendre. `retour_attendu` était écrit par la
                        génération depuis juin 2026 et **jamais restitué** : le formaliste ne
                        voyait donc nulle part ce qu'il était censé recevoir. */}
                    {formalite.retour_attendu && (
                        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                            <span className="text-slate-500">Ce que l'organisme doit rendre : </span>
                            <span className="font-medium text-ink">{formalite.retour_attendu}</span>
                        </div>
                    )}

                    {/* Reçu ou rejeté — rétabli le 2026-09-29. Le basculement avait disparu le
                        14 août, rendant `StatutFormalite::Rejete` inatteignable alors que le
                        blocage d'étape gardait son message « À corriger et redéposer ». */}
                    <div className="grid grid-cols-2 gap-2">
                        {[
                            { valeur: 'recu',   label: 'Retour reçu',      icone: CheckCircle2,   actif: 'border-success bg-success-bg text-success-text' },
                            { valeur: 'rejete', label: 'Rejeté à corriger', icone: AlertTriangle, actif: 'border-danger bg-danger-bg text-danger' },
                        ].map(({ valeur, label, icone: Icone, actif }) => (
                            <button
                                key={valeur}
                                type="button"
                                onClick={() => setResultat(valeur)}
                                className={cn(
                                    'flex items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition-colors',
                                    resultat === valeur ? actif : 'border-slate-200 text-slate-500 hover:border-slate-300',
                                )}
                            >
                                <Icone className="h-4 w-4" />
                                {label}
                            </button>
                        ))}
                    </div>

                    <div className="space-y-1.5">
                        <Label>Date retour effectif <span className="text-danger">*</span></Label>
                        <DateField
                            value={isoDateToFR(dateRetour)}
                            onValueChange={val => setDateRetour(frDateToISO(val))}
                        />
                    </div>

                    {resultat === 'rejete' && (
                        <div className="space-y-1.5">
                            <Label>Motif du rejet <span className="text-danger">*</span></Label>
                            <textarea
                                value={motifRejet}
                                onChange={e => setMotifRejet(e.target.value)}
                                rows={3}
                                maxLength={500}
                                placeholder="Ce que l'organisme reproche — c'est ce qu'il faudra corriger avant de redéposer."
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            />
                        </div>
                    )}

                    {/* ── Données délivrées par l'autorité ─────────────────────────────
                        Rendues **depuis le serveur** : `donnees_au_retour` porte le libellé et
                        le type de chaque champ. Aucun nom de donnée n'est écrit ici.

                        Aucune n'est obligatoire : l'APIP peut rendre un extrait sans le NIF, et
                        l'exiger bloquerait l'étape sur une donnée que l'organisme n'a pas
                        fournie. On saisit ce qui est là, le manque est signalé. */}
                    {resultat === 'recu' && attendues.length > 0 && (
                        <div className="space-y-3 rounded-lg border border-seal/30 bg-seal/5 p-3">
                            <div className="text-xs font-medium uppercase tracking-wide text-slate-500">
                                Informations délivrées — reportées à la fiche société
                            </div>

                            {attendues.map(({ valeur, label, type, exemple }) => (
                                <div key={valeur} className="space-y-1.5">
                                    <Label>{label}</Label>
                                    {type === 'date' ? (
                                        <DateField
                                            value={isoDateToFR(donnees[valeur] ?? '')}
                                            onValueChange={val => setDonnees(d => ({ ...d, [valeur]: frDateToISO(val) }))}
                                        />
                                    ) : (
                                        <input
                                            type="text"
                                            value={donnees[valeur] ?? ''}
                                            onChange={e => setDonnees(d => ({ ...d, [valeur]: e.target.value }))}
                                            placeholder={exemple}
                                            maxLength={120}
                                            className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-mono"
                                        />
                                    )}
                                </div>
                            ))}

                            <p className="text-xs text-slate-500">
                                Laissez vide ce que l'organisme n'a pas fourni — rien n'est obligatoire ici.
                            </p>
                        </div>
                    )}

                    {/* Valeurs captées lors d'un enregistrement précédent, que le barème ne
                        déclare plus. Affichées en lecture et **réémises** : sans cela, cette
                        soumission les effacerait. */}
                    {resultat === 'recu' && orphelines.length > 0 && (
                        <div className="rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-500">
                            <div className="mb-1 font-medium">Déjà capté, plus attendu par la configuration :</div>
                            {orphelines.map(([cle, valeur]) => (
                                <div key={cle} className="font-mono">{cle} = {valeur}</div>
                            ))}
                        </div>
                    )}

                    {resultat === 'recu' && (
                        <div className="space-y-1.5">
                            <Label>Référence du document reçu</Label>
                            <input
                                type="text"
                                value={reference}
                                onChange={e => setReference(e.target.value)}
                                maxLength={200}
                                placeholder="Référence portée sur le document remis par l'organisme"
                                className="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"
                            />
                        </div>
                    )}

                    {resultat === 'recu' && pieces.length > 0 && (
                        <div className="rounded-lg border border-slate-200">
                            <div className="flex items-center justify-between px-3 py-2 border-b border-slate-100">
                                <span className="text-xs font-medium text-slate-500 uppercase tracking-wide">Pièces requises pour le retour</span>
                            </div>
                            <div className="px-2 py-1 divide-y divide-slate-50">
                                {pieces.map(p => (
                                    <PieceGedRow
                                        key={p.id}
                                        piece={p}
                                        peutGerer={true}
                                        isPreviewOpen={previewPieceId === p.id}
                                        onTogglePreview={(piece) => setPreviewPieceId(id => id === piece.id ? null : piece.id)}
                                    />
                                ))}
                            </div>
                            {piecesManquantes > 0 && (
                                <div className="flex items-center gap-2 px-3 py-2 border-t border-amber-100 bg-amber-50 text-xs text-amber-800">
                                    <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
                                    {piecesManquantes} pièce{piecesManquantes > 1 ? 's' : ''} manquante{piecesManquantes > 1 ? 's' : ''} — à téléverser avant de confirmer le retour
                                </div>
                            )}
                        </div>
                    )}

                    {resultat === 'recu' && formalite.aDesDependants && (
                        <div className="flex items-center gap-2 bg-success-bg border border-green-200 rounded-lg px-3 py-2">
                            <Zap className="h-4 w-4 text-success shrink-0" />
                            <span className="text-sm text-success-text">
                                Après validation : {formalite.dependantsLabels.join(', ')} se débloquera{formalite.dependantsLabels.length > 1 ? 'ont' : ''} automatiquement — {formalite.dependantsLabels.length > 1 ? 'elles dépendent' : 'elle dépend'} de ce retour.
                            </span>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="submit"
                            variant={resultat === 'rejete' ? 'destructive' : 'success'}
                            disabled={!peutConfirmer}
                            title={peutConfirmer ? '' : (resultat === 'rejete'
                                ? 'Précisez le motif du rejet'
                                : "Téléversez toutes les pièces requises avant d'enregistrer")}
                        >
                            {resultat === 'rejete' ? 'Enregistrer le rejet' : 'Enregistrer le retour'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
