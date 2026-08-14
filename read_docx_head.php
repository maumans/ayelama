<?php
$file = 'Facture_MAB_SARLU_v2 (1).docx';
$zip = new ZipArchive;
if ($zip->open($file) === TRUE) {
    $content = $zip->getFromName('word/document.xml');
    $content = str_replace('<w:p ', "\n<w:p ", $content);
    $content = str_replace('</w:p>', "\n", $content);
    
    // just first 2000 chars of text
    echo substr(strip_tags($content), 0, 2000);
    $zip->close();
}
