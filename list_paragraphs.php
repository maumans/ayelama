<?php
/**
 * Extraire TOUS les textes du document de référence en INCLUANT les textes
 * qui contiennent du XML embarqué (tables). On veut vraiment tout voir.
 */
$zip = new ZipArchive;
$zip->open('Facture_MAB_SARLU_v2 (1).docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

// On va simplement imprimer le XML "aplati" (sans les nœuds non-texte)
// en séparant chaque paragraphe
$doc = new DOMDocument;
$doc->loadXML($xml);

$xpath = new DOMXPath($doc);
$xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

$paragraphs = $xpath->query('//w:p');

echo "=== Paragraphes du document ({$paragraphs->length} au total) ===\n\n";

$pIdx = 0;
foreach ($paragraphs as $p) {
    $texts = $xpath->query('.//w:t', $p);
    $fullText = '';
    foreach ($texts as $t) {
        $fullText .= $t->textContent;
    }
    if (trim($fullText) !== '') {
        echo "[P{$pIdx}] \"{$fullText}\"\n";
    }
    $pIdx++;
}

// Même chose pour les headers
echo "\n=== Header 2 ===\n";
$headerXml = $zip->getFromName('word/header2.xml');
if (!$headerXml) {
    $zip2 = new ZipArchive;
    $zip2->open('Facture_MAB_SARLU_v2 (1).docx');
    $headerXml = $zip2->getFromName('word/header2.xml');
    $zip2->close();
}
if ($headerXml) {
    $hdoc = new DOMDocument;
    $hdoc->loadXML($headerXml);
    $hxpath = new DOMXPath($hdoc);
    $hxpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $hparas = $hxpath->query('//w:p');
    foreach ($hparas as $hp) {
        $texts = $hxpath->query('.//w:t', $hp);
        $ft = '';
        foreach ($texts as $t) $ft .= $t->textContent;
        if (trim($ft) !== '') echo "  \"{$ft}\"\n";
    }
}

echo "\n=== Footer 2 ===\n";
$zip3 = new ZipArchive;
$zip3->open('Facture_MAB_SARLU_v2 (1).docx');
$footerXml = $zip3->getFromName('word/footer2.xml');
$zip3->close();
if ($footerXml) {
    $fdoc = new DOMDocument;
    $fdoc->loadXML($footerXml);
    $fxpath = new DOMXPath($fdoc);
    $fxpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $fparas = $fxpath->query('//w:p');
    foreach ($fparas as $fp) {
        $texts = $fxpath->query('.//w:t', $fp);
        $ft = '';
        foreach ($texts as $t) $ft .= $t->textContent;
        if (trim($ft) !== '') echo "  \"{$ft}\"\n";
    }
}
