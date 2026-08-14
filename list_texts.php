<?php
/**
 * Extraire TOUS les textes (w:t) du document de référence dans l'ordre,
 * pour comprendre la structure complète.
 */
$zip = new ZipArchive;
$zip->open('Facture_MAB_SARLU_v2 (1).docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xml, $matches, PREG_OFFSET_CAPTURE);

echo "=== Tous les textes <w:t> dans l'ordre ===\n\n";
foreach ($matches[1] as $i => $match) {
    $text = $match[0];
    $offset = $match[1];
    // Ignorer les textes qui sont en fait du XML imbriqué (tables mal parsées)
    if (strpos($text, '<w:') !== false) continue;
    if (trim($text) === '') continue;
    
    printf("[%03d] (offset %6d) \"%s\"\n", $i, $offset, $text);
}
