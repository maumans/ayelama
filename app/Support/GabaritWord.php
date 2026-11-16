<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Le gabarit Word d'un acte, avec ce que `TemplateProcessor` ne sait pas faire : **mettre une
 * balise en gras sans toucher au reste de sa ligne** (retour R15, 2026-11-16 — « Dénomination
 * GRAS, Siège GRAS, Sigle GRAS » dans les actes de constitution).
 *
 * Pourquoi ici et non dans les fichiers `.docx` : mesuré sur les onze gabarits actifs de
 * constitution, le gras était incohérent — la DNSV et l'attestation de capital SARL/SAS/SASU
 * l'avaient, les statuts SARLU à moitié, le RCCM et l'attestation SARLU pas du tout. Corriger les
 * fichiers un à un aurait laissé le défaut revenir à chaque nouveau gabarit, déposé ou hérité.
 *
 * Pourquoi pas `setComplexValue()` : il remplace le run par un élément PhpWord neuf, qui perd la
 * police et la taille du gabarit — la dénomination en 22 pts d'une page de garde retomberait au
 * corps par défaut. Ici, on **clone les propriétés du run** et on n'y ajoute que `<w:b/>`.
 */
class GabaritWord extends TemplateProcessor
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * Met en gras chaque occurrence de ces balises, dans le corps, les en-têtes et les pieds.
     * À appeler **avant** le remplissage : on repère les balises `${…}` elles-mêmes.
     *
     * @param array<int, string> $balises noms sans `${}` (`soc.denomination`…)
     */
    public function mettreEnGras(array $balises): void
    {
        $this->tempDocumentMainPart = $this->engraisser($this->tempDocumentMainPart, $balises);

        foreach ($this->tempDocumentHeaders as $i => $xml) {
            $this->tempDocumentHeaders[$i] = $this->engraisser($xml, $balises);
        }
        foreach ($this->tempDocumentFooters as $i => $xml) {
            $this->tempDocumentFooters[$i] = $this->engraisser($xml, $balises);
        }
    }

    private function engraisser(string $xml, array $balises): string
    {
        $motif = '/(\$\{(?:' . implode('|', array_map(fn ($b) => preg_quote($b, '/'), $balises)) . ')\})/';

        if (! preg_match($motif, $xml)) {
            return $xml;
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        if (! @$dom->loadXML($xml)) {
            return $xml; // XML inattendu : ne rien abîmer, le texte reste correct, seul le gras manque.
        }
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::W);

        foreach (iterator_to_array($xp->query('//w:r[w:t]')) as $run) {
            /** @var DOMElement $run */
            $textes = $xp->query('w:t', $run);
            // Run à un seul texte (le cas des balises après `fixBrokenMacros`) : on le découpe.
            // Run composite (texte + tabulation…) : on le met en gras en entier plutôt que de
            // risquer d'en déplacer les éléments.
            $simple = $textes->length === 1 && $xp->query('*[not(self::w:rPr) and not(self::w:t)]', $run)->length === 0;
            $contenu = '';
            foreach ($textes as $t) $contenu .= $t->textContent;

            if (! preg_match($motif, $contenu)) {
                continue;
            }

            if (! $simple) {
                $this->ajouterGras($dom, $run);
                continue;
            }

            $morceaux = preg_split($motif, $contenu, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            foreach ($morceaux as $morceau) {
                $nouveau = $run->cloneNode(true);
                $t = $xp->query('w:t', $nouveau)->item(0);
                $t->textContent = $morceau;
                $t->setAttribute('xml:space', 'preserve');
                if (preg_match($motif, $morceau)) {
                    $this->ajouterGras($dom, $nouveau);
                }
                $run->parentNode->insertBefore($nouveau, $run);
            }
            $run->parentNode->removeChild($run);
        }

        return $dom->saveXML($dom->documentElement) === false ? $xml : $this->avecEntete($xml, $dom);
    }

    private function ajouterGras(DOMDocument $dom, DOMElement $run): void
    {
        $rPr = null;
        foreach ($run->childNodes as $enfant) {
            if ($enfant instanceof DOMElement && $enfant->localName === 'rPr') {
                $rPr = $enfant;
                break;
            }
        }
        if (! $rPr) {
            $rPr = $dom->createElementNS(self::W, 'w:rPr');
            $run->insertBefore($rPr, $run->firstChild);
        }

        // Retirer un gras explicitement désactivé, puis poser le nôtre en tête (l'ordre des
        // propriétés compte pour Word : `w:b` vient avant la police et la taille).
        foreach (['b', 'bCs'] as $nom) {
            foreach (iterator_to_array($rPr->getElementsByTagNameNS(self::W, $nom)) as $existant) {
                $rPr->removeChild($existant);
            }
        }
        $apres = null;
        foreach ($rPr->childNodes as $enfant) {
            if ($enfant instanceof DOMElement && ! in_array($enfant->localName, ['rStyle', 'rFonts'], true)) {
                $apres = $enfant;
                break;
            }
        }
        $rPr->insertBefore($dom->createElementNS(self::W, 'w:bCs'), $apres);
        $rPr->insertBefore($dom->createElementNS(self::W, 'w:b'), $rPr->getElementsByTagNameNS(self::W, 'bCs')->item(0));
    }

    /** Conserve la déclaration XML d'origine : Word la lit, `saveXML($element)` ne la rend pas. */
    private function avecEntete(string $origine, DOMDocument $dom): string
    {
        $entete = preg_match('/^<\?xml[^>]*\?>\s*/', $origine, $m) ? $m[0] : '';

        return $entete . $dom->saveXML($dom->documentElement);
    }
}
