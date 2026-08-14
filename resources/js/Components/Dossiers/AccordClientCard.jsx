import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Download, Eye, Printer, RefreshCw, Upload } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import DocumentInlinePreview from '@/Components/documents/DocumentInlinePreview';
import { notifyValidationError } from '@/lib/toast';
import { cn } from '@/lib/utils';

/** Ancre DOM partagée avec Dossiers/Show.jsx (panneau des conditions requises). */
export const ANCRE_ACCORD_CLIENT = 'accord-client';

/**
 * Dépôt de la fiche de recueil signée par le client.
 *
 * Étape **bloquante** pour quitter l'Édition (voir
 * DossierStepService::verifierEdition), et pourtant reléguée en bas de l'onglet
 * Informations où elle passait inaperçue. Deux corrections :
 *   - remontée juste après la fiche dossier, l'enchaînement réel étant
 *     « imprimer la fiche → la faire signer → téléverser l'accord » ;
 *   - l'état « en attente » est signalé comme bloquant (anneau ambré + mention),
 *     tandis que l'état « reçu » reste discret : une fois l'action faite, elle n'a
 *     plus à occuper l'attention.
 *
 * Extraite d'InformationsTab, qui portait son état local alors qu'aucun autre
 * élément de l'onglet ne s'en sert.
 */
export function AccordClientCard({ dossier, can }) {
    const accordClient = dossier.accordClient;
    const accordRecu   = !!accordClient?.est_signe_cachete;

    // Titre, consigne et bouton d'impression dépendent du type de dossier : une modification de
    // statuts attend la décision écrite des associés — ce qui engage réellement l'opération — et
    // non une fiche de recueil que l'étude imprimerait. Le repli garde l'écran fonctionnel si la
    // prop manque (page ouverte avant le déploiement).
    const attendu = dossier.accordAttendu ?? {
        titre: 'Accord client sur le questionnaire',
        instructions: "Imprimez la fiche dossier, faites-la signer par le client, puis téléversez ici le document signé. Le dossier ne pourra pas passer en certification sans cet accord.",
        imprimable: true,
    };

    const [preview, setPreview] = useState(false);
    const [uploading, setUploading] = useState(false);
    const inputRef = useRef(null);
    const cardRef  = useRef(null);

    // ?focus=accord — permet à un lien (message de blocage, notification) d'amener
    // directement l'attention ici, sur le même principe que ?focus=pieces.
    useEffect(() => {
        if (new URLSearchParams(window.location.search).get('focus') === 'accord') {
            cardRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, []);

    const televerser = (file) => {
        if (!file) return;
        setUploading(true);
        router.post(`/dossiers/${dossier.reference}/accord-client`, { fichier: file }, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onError: notifyValidationError,
            onFinish: () => setUploading(false),
        });
    };

    const champFichier = can?.modifierQuestionnaire && (
        <input
            ref={inputRef}
            type="file"
            className="hidden"
            onChange={(e) => televerser(e.target.files?.[0])}
        />
    );

    // `id` : cible du lien depuis le panneau « Conditions requises » de l'en-tête,
    // qui bascule sur cet onglet puis fait défiler jusqu'ici.
    return (
        <Card id={ANCRE_ACCORD_CLIENT} ref={cardRef} className={cn(!accordRecu && 'border-warning/40 ring-2 ring-warning/40')}>
            <CardHeader className="pb-3">
                <CardTitle className="flex flex-wrap items-center gap-2">
                    {accordRecu
                        ? <CheckCircle2 className="h-4 w-4 text-success" />
                        : <AlertTriangle className="h-4 w-4 text-warning-text" />}
                    {attendu.titre}
                    {!accordRecu && (
                        <span className="rounded-full bg-warning-bg px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-warning-text">
                            Requis pour passer à la certification
                        </span>
                    )}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                {accordRecu ? (
                    <>
                        <div className="flex items-start gap-3 rounded-lg border border-green-200 bg-success-bg p-3">
                            <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-success" />
                            <div className="min-w-0 flex-1 text-sm text-success-text">
                                Accord reçu le {accordClient.signe_cachete_at} par {accordClient.signe_cachete_par}
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => setPreview(v => !v)}
                                    className="flex items-center gap-1 text-xs text-seal hover:underline"
                                >
                                    <Eye className="h-3 w-3" /> {preview ? 'Masquer' : 'Voir'}
                                </button>
                                <a
                                    href={accordClient.url_download}
                                    className="flex items-center gap-1 text-xs text-slate-500 hover:text-seal"
                                >
                                    <Download className="h-3 w-3" /> Télécharger
                                </a>
                                {can?.modifierQuestionnaire && (
                                    <>
                                        {champFichier}
                                        <button
                                            type="button"
                                            onClick={() => inputRef.current?.click()}
                                            disabled={uploading}
                                            className="flex items-center gap-1 text-xs text-slate-500 hover:text-seal"
                                        >
                                            <RefreshCw className="h-3 w-3" /> {uploading ? 'Envoi…' : 'Remplacer'}
                                        </button>
                                    </>
                                )}
                            </div>
                        </div>
                        {preview && (
                            <DocumentInlinePreview
                                doc={{
                                    id: accordClient.id,
                                    nom: accordClient.nom,
                                    version: accordClient.version,
                                    chemin_fichier: accordClient.chemin_fichier,
                                }}
                                previewUrl={accordClient.url_preview}
                                downloadUrl={accordClient.url_download}
                                onClose={() => setPreview(false)}
                            />
                        )}
                    </>
                ) : (
                    <div className="flex flex-col gap-3 rounded-lg border border-amber-200 bg-warning-bg p-3 sm:flex-row sm:items-start">
                        <AlertTriangle className="mt-0.5 hidden h-4 w-4 shrink-0 text-warning-text sm:block" />
                        {/* Pas de repère de position (« ci-dessus » / « ci-dessous ») : la
                            carte a déjà changé de place une fois, et le bouton d'impression
                            est directement offert ici. */}
                        <div className="min-w-0 flex-1 text-sm text-warning-text">
                            {attendu.instructions}
                        </div>
                        <div className="flex shrink-0 items-center gap-2">
                            {/* Les deux actions de la boucle sont offertes ici : la carte
                                étant en tête d'onglet, on ne doit pas avoir à redescendre
                                jusqu'à la fiche pour l'imprimer. Rien à imprimer en revanche
                                pour une modification : c'est le client qui apporte la décision
                                de son assemblée. */}
                            {attendu.imprimable && (
                                <Button size="sm" variant="outline" className="h-8 gap-1.5" asChild>
                                    <a
                                        href={`/dossiers/${dossier.reference}/fiche-recueil`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        <Printer className="h-3.5 w-3.5" />
                                        Imprimer la fiche
                                    </a>
                                </Button>
                            )}
                            {can?.modifierQuestionnaire && (
                                <>
                                    {champFichier}
                                    <Button
                                        size="sm"
                                        variant="warning"
                                        className="h-8 gap-1.5"
                                        onClick={() => inputRef.current?.click()}
                                        disabled={uploading}
                                    >
                                        <Upload className="h-3.5 w-3.5" />
                                        {uploading
                                            ? 'Envoi…'
                                            : attendu.imprimable ? 'Téléverser l’accord signé' : 'Téléverser la décision'}
                                    </Button>
                                </>
                            )}
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
