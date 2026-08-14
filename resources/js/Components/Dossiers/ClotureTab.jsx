import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    Archive, CheckCircle2, Download, Eye, FileSignature, FileText,
    Inbox, Lock, Receipt, UserSquare2, Building2, AlertTriangle, ArrowRight, Wallet
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DocumentInlinePreview from '@/Components/documents/DocumentInlinePreview';
import { cn } from '@/lib/utils';
import { notifyValidationError } from '@/lib/toast';

/**
 * Onglet Clôture : l'inventaire complet des pièces du dossier, rangé par rubrique,
 * que le notaire ou le formaliste vérifie pièce par pièce avant de clôturer.
 *
 * Remplace l'écran « documents obligatoires configurés par type d'acte » : il n'y a
 * plus rien à déclarer à l'avance, l'inventaire est dérivé de ce que le workflow a
 * produit (voir app/Services/InventaireClotureService.php). Extrait de Show.jsx, qui
 * dépassait 3 000 lignes.
 */

// Une icône par rubrique — les valeurs viennent de App\Enums\RubriqueCloture.
const ICONES_RUBRIQUE = {
    actes:             FileText,
    accord_client:     FileSignature,
    pieces_parties:    UserSquare2,
    pieces_formalites: Building2,
    courriers:         Inbox,
    facturation:       Receipt,
};

function PieceRow({ dossierReference, piece, apercuOuvert, onToggleApercu }) {
    return (
        <div className="py-2.5">
        <div className="flex items-start gap-3">
            <div className="mt-0.5 shrink-0">
                <CheckCircle2 className="h-4 w-4 text-success" />
            </div>

            <div className="min-w-0 flex-1">
                <p className="text-sm text-slate-800">
                    {piece.nom}
                </p>
                <p className="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-slate-400">
                    {/* Provenance : sans elle, une liste de « CNI » sur un dossier à
                        quatre associés est illisible. */}
                    {piece.origine && <span>{piece.origine}</span>}
                    {piece.version && <span>v{piece.version}</span>}
                    {!piece.has_file && (
                        <span className="font-medium text-warning-text">Aucun fichier</span>
                    )}
                    {piece.est_signe_cachete && (
                        <span className="inline-flex items-center gap-1 text-success-text">
                            <Lock className="h-3 w-3" /> Signé/cacheté
                        </span>
                    )}
                </p>
            </div>

            <div className="flex shrink-0 items-center gap-3">
                {piece.has_file && piece.url_preview && (
                    <button
                        type="button"
                        onClick={onToggleApercu}
                        className="flex items-center gap-1 text-xs text-seal hover:underline"
                    >
                        <Eye className="h-3 w-3" /> {apercuOuvert ? 'Masquer' : 'Aperçu'}
                    </button>
                )}
                {piece.has_file && piece.url_download && (
                    <a
                        href={piece.url_download}
                        className="flex items-center gap-1 text-xs text-slate-400 hover:text-seal"
                    >
                        <Download className="h-3 w-3" /> Télécharger
                    </a>
                )}
            </div>
        </div>

        {/* Aperçu déplié sous la pièce concernée, et non dans un panneau en pied de
            page : sur un inventaire de vingt lignes, on perdait de vue quelle pièce on
            regardait. Même composant que l'accord client et les pièces des parties. */}
        <AnimatePresence initial={false}>
            {apercuOuvert && piece.has_file && (
                <motion.div
                    initial={{ opacity: 0, height: 0 }}
                    animate={{ opacity: 1, height: 'auto' }}
                    exit={{ opacity: 0, height: 0 }}
                    transition={{ duration: 0.2 }}
                    className="overflow-hidden"
                >
                    <div className="ml-7 mt-2">
                        <DocumentInlinePreview
                            doc={{
                                id: piece.id,
                                nom: piece.nom,
                                version: piece.version,
                                chemin_fichier: piece.chemin_fichier,
                            }}
                            previewUrl={piece.url_preview}
                            downloadUrl={piece.url_download}
                            onClose={onToggleApercu}
                        />
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
        </div>
    );
}

export function ClotureTab({ dossier, can }) {
    const rubriques = dossier.inventaireCloture ?? [];
    const { total = 0 } = dossier.clotureProgression ?? {};
    // Une seule pièce en aperçu à la fois : ouvrir la suivante referme la précédente,
    // sinon la page se remplit de panneaux et on ne s'y retrouve plus.
    const [apercuCle, setApercuCle] = useState(null);

    const factures = dossier?.factures ?? [];
    const resteAPayer = factures.reduce((acc, f) => acc + (f.soldeRestant ?? 0), 0);
    const fmtGNF = (n) => Number(n || 0).toLocaleString('fr-FR');

    if (total === 0) {
        return (
            <Card>
                <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                    <Archive className="mb-3 h-10 w-10 text-slate-200" />
                    <p className="text-sm font-medium text-slate-500">Aucune pièce au dossier</p>
                    <p className="mt-1 max-w-sm text-xs text-slate-400">
                        L'inventaire se remplit au fil des étapes : pièces des parties à la création,
                        actes à l'édition, justificatifs aux formalités, courriers à l'expédition.
                    </p>
                </CardContent>
            </Card>
        );
    }

    const dossierClos = dossier.etape?.value === 'cloture';
    const toutesFacturesSoldees = factures.length > 0 && resteAPayer <= 0;
    const aucuneFacture = factures.length === 0;

    return (
        <div className="space-y-4">
            {!dossierClos && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-2">
                    {/* Carte 1 : Inventaire GED */}
                    <div className="flex flex-col gap-2 rounded-lg border px-4 py-4 text-sm border-success/30 bg-success-bg text-success-text">
                        <div className="flex items-center gap-2 font-semibold">
                            <CheckCircle2 className="h-4 w-4" />
                            Inventaire GED
                        </div>
                        <span className="opacity-90">
                            {total} pièce{total > 1 ? 's' : ''} présente{total > 1 ? 's' : ''} au dossier.
                        </span>
                    </div>

                    {/* Carte 2 : Facturation */}
                    <div className={cn(
                        'flex flex-col gap-2 rounded-lg border px-4 py-4 text-sm relative',
                        toutesFacturesSoldees || aucuneFacture
                            ? 'border-success/30 bg-success-bg text-success-text'
                            : 'border-danger/30 bg-danger-bg text-danger-text'
                    )}>
                        <div className="flex items-center gap-2 font-semibold">
                            {toutesFacturesSoldees || aucuneFacture ? <CheckCircle2 className="h-4 w-4" /> : <AlertTriangle className="h-4 w-4" />}
                            Facturation
                        </div>
                        <span className="opacity-90">
                            {aucuneFacture 
                                ? 'Aucune facture associée à ce dossier.' 
                                : toutesFacturesSoldees 
                                    ? 'Toutes les factures sont soldées.' 
                                    : `${fmtGNF(resteAPayer)} GNF restent à payer avant de clôturer.`}
                        </span>
                    </div>
                </div>
            )}

            {/* Dire pourquoi les cases sont inertes, plutôt que de les désactiver en
                silence : sur un dossier clos, l'inventaire est figé côté serveur
                (DossierPolicy::estFige) et cocher n'a plus de sens. */}
            {dossierClos && (
                <div className="flex items-start gap-2 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <Lock className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                    <span>
                        Dossier clôturé — l'inventaire est figé. Les pièces restent consultables
                        et téléchargeables, mais ne peuvent plus être modifiées.
                    </span>
                </div>
            )}

            {rubriques.map(rubrique => {
                const Icone = ICONES_RUBRIQUE[rubrique.rubrique] ?? FileText;
                const nb = rubrique.pieces.length;

                return (
                    <Card key={rubrique.rubrique} className={cn(nb === 0 && 'opacity-60')}>
                        <CardHeader className="flex flex-row items-start justify-between gap-3 pb-3">
                            <div className="min-w-0">
                                <CardTitle className="flex items-center gap-2 text-sm">
                                    <Icone className="h-4 w-4 text-seal" />
                                    {rubrique.ordre}. {rubrique.label}
                                    <span className="text-xs font-normal text-slate-400">
                                        {nb === 0 ? 'aucune pièce' : `${nb} pièce${nb > 1 ? 's' : ''}`}
                                    </span>
                                </CardTitle>
                                <p className="mt-1 text-xs text-slate-400">{rubrique.description}</p>
                            </div>
                        </CardHeader>
                        {nb > 0 && (
                            <CardContent className="divide-y divide-slate-100 pt-0">
                                {rubrique.pieces.map(piece => {
                                    const cle = `${piece.type}-${piece.id}`;
                                    return (
                                        <PieceRow
                                            key={cle}
                                            dossierReference={dossier.reference}
                                            piece={piece}
                                            apercuOuvert={apercuCle === cle}
                                            onToggleApercu={() => setApercuCle(k => k === cle ? null : cle)}
                                        />
                                    );
                                })}
                            </CardContent>
                        )}
                    </Card>
                );
            })}
        </div>
    );
}
