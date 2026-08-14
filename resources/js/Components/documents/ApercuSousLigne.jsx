import { useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';
import DocumentInlinePreview from '@/Components/documents/DocumentInlinePreview';

/**
 * Aperçu déplié **directement sous la ligne concernée**.
 *
 * La fiche dossier avait un unique panneau d'aperçu en pied de page, alimenté par
 * quatre onglets (actes, lettres de transmission, reçus, documents de certification) :
 * on cliquait sur « Aperçu » d'une ligne et le document s'ouvrait très loin en bas, sans
 * qu'on sache plus à quelle ligne il correspondait. Sur une liste de vingt documents,
 * c'était intenable.
 *
 * Ce module fournit les deux pièces du motif, déjà appliqué à l'onglet Clôture et à la
 * carte d'accord client :
 *   - `useApercuEnLigne()` — quelle ligne est ouverte, une seule à la fois ;
 *   - `<ApercuSousLigne>`  — le panneau animé, à placer juste après la ligne.
 */

/**
 * Gère la ligne actuellement en aperçu. Une seule à la fois : ouvrir la suivante referme
 * la précédente, sinon la page se remplit de panneaux empilés.
 */
export function useApercuEnLigne() {
    const [cle, setCle] = useState(null);

    return {
        estOuvert: (k) => cle === k,
        basculer:  (k) => setCle(prev => (prev === k ? null : k)),
        fermer:    () => setCle(null),
    };
}

/**
 * @param {boolean} ouvert
 * @param {{id:number|string, nom:string, version?:number, chemin_fichier?:string}} doc
 * @param {string}  [previewUrl]   Défaut : /documents/{id}/preview (voir DocumentInlinePreview)
 * @param {string}  [downloadUrl]
 * @param {string}  [decalage]     Indentation, pour aligner l'aperçu sur le texte de la
 *                                 ligne plutôt que sur sa case à cocher ou son icône.
 */
export function ApercuSousLigne({ ouvert, doc, previewUrl, downloadUrl, onFermer, decalage = '' }) {
    return (
        <AnimatePresence initial={false}>
            {ouvert && doc && (
                <motion.div
                    initial={{ opacity: 0, height: 0 }}
                    animate={{ opacity: 1, height: 'auto' }}
                    exit={{ opacity: 0, height: 0 }}
                    transition={{ duration: 0.2 }}
                    className="overflow-hidden"
                >
                    <div className={`mt-2 mb-1 ${decalage}`}>
                        <DocumentInlinePreview
                            doc={doc}
                            previewUrl={previewUrl}
                            downloadUrl={downloadUrl}
                            onClose={onFermer}
                        />
                    </div>
                </motion.div>
            )}
        </AnimatePresence>
    );
}
