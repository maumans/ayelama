<?php
/**
 * Diagnostique le XML brut pour trouver comment sont stockés le numéro et la date.
 */
$zip = new ZipArchive;
$zip->open('Facture_MAB_SARLU_v2 (1).docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();

// Chercher les blocs autour de "045" (numéro de facture) et "Octobre" (date)
$patterns = ['045', 'MAB', '2026', 'Octobre', 'N°', 'FACTURE', 'Société', 'MAB SARLU'];

foreach ($patterns as $p) {
    echo "\n=== Contexte autour de '{$p}' ===\n";
    $pos = 0;
    $count = 0;
    while (($pos = strpos($xml, $p, $pos)) !== false && $count < 3) {
        $start = max(0, $pos - 100);
        $end = min(strlen($xml), $pos + strlen($p) + 100);
        $excerpt = substr($xml, $start, $end - $start);
        // Simplifier l'affichage
        $excerpt = preg_replace('/<w:rPr>.*?<\/w:rPr>/s', '<rPr/>', $excerpt);
        echo "  pos={$pos}: ...{$excerpt}...\n";
        $pos += strlen($p);
        $count++;
    }
}
