<?php
$file = 'Facture_MAB_SARLU_v2 (1).docx';
$zip = new ZipArchive;
if ($zip->open($file) === TRUE) {
    $content = $zip->getFromName('word/document.xml');
    $content = str_replace('<w:p ', "\n<w:p ", $content);
    $content = str_replace('</w:p>', "\n", $content);
    $text = strip_tags($content);
    echo trim($text);
    $zip->close();
} else {
    echo "Failed to open docx";
}
