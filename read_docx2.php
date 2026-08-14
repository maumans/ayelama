<?php
$file = 'Facture_MAB_SARLU_v2 (1).docx';
$zip = new ZipArchive;
if ($zip->open($file) === TRUE) {
    $content = $zip->getFromName('word/document.xml');
    
    // Convert paragraphs to newlines
    $content = str_replace('<w:p ', "\n<w:p ", $content);
    $content = str_replace('</w:p>', "\n", $content);
    
    // Strip tags and print
    echo strip_tags($content);
    
    $zip->close();
} else {
    echo "Failed";
}
