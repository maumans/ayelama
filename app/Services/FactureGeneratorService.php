<?php

namespace App\Services;

use App\Models\Facture;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\TemplateProcessor;
use ZipArchive;

/**
 * Génère la note de frais/facture au format .docx à partir du modèle
 * storage/app/private/modeles/facture-notariale.docx — reproduction fidèle du
 * document fourni par le cabinet (mise en page, tableau, filigrane), avec le
 * logo et le filigrane remplacés par le logo actuellement configuré
 * (Paramètres > Apparence, Setting::logo_path) plutôt que celui figé dans le modèle.
 *
 * Rendu à la demande (jamais persisté en GED) :
 * tant qu'aucun paiement n'existe, les lignes de la facture restent modifiables
 * (voir FactureController::assertLignesModifiables) — un fichier stocké deviendrait
 * silencieusement obsolète à la moindre modification de ligne.
 */
class FactureGeneratorService
{
    private const TEMPLATE = 'modeles/facture-notariale.docx';

    public function genererDocument(Facture $facture): string
    {
        $facture->loadMissing(['lignes', 'dossier']);

        $templateAbsPath = Storage::disk('local')->path(self::TEMPLATE);
        $tp = new TemplateProcessor($templateAbsPath);

        $lignes = $facture->lignes;
        $tp->cloneRow('ligne.numero', max($lignes->count(), 1));

        foreach ($lignes as $i => $ligne) {
            $n = $i + 1;
            $tp->setValue("ligne.numero#{$n}", (string) $n);
            $tp->setValue("ligne.designation#{$n}", $this->echapper($ligne->designation));
            $tp->setValue("ligne.quantite#{$n}", (string) $ligne->quantite);
            $tp->setValue("ligne.montant#{$n}", $this->formaterMontant((float) $ligne->montant));
        }

        $tp->setValue('fac.numero', $facture->note_numero);
        $tp->setValue('fac.date', 'Le ' . $facture->note_date?->translatedFormat('d F Y'));
        $tp->setValue('fac.compte_numero', $facture->compte_numero ?: '—');
        $tp->setValue('fac.objet', $this->echapper($facture->objet));
        $tp->setValue('fac.detail', $this->detailPrestations($lignes));
        $tp->setValue('fac.num_timbres', (string) ($lignes->count() + 1));
        $tp->setValue('fac.num_roles', (string) ($lignes->count() + 2));
        $tp->setValue('fac.total', $this->formaterMontant((float) $facture->total_chiffres));
        $tp->setValue('fac.total_lettres', $this->totalEnLettres((float) $facture->total_chiffres));

        $dossier = $facture->dossier;
        $outputDir = storage_path('app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $dossier->reference);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            throw new \RuntimeException("Impossible de créer le répertoire : {$outputDir}");
        }

        $filename = 'facture_' . Str::slug($facture->note_numero) . '_' . time() . '.docx';
        $outputAbsPath = $outputDir . DIRECTORY_SEPARATOR . $filename;
        $tp->saveAs($outputAbsPath);

        $this->remplacerLogoEtFiligrane($outputAbsPath);

        return 'documents/' . $dossier->reference . '/' . $filename;
    }

    /**
     * Remplace les deux images du modèle (image1.png = filigrane, image2.png = logo
     * d'en-tête) par le logo configuré dans Paramètres > Apparence. L'effet de
     * filigrane (délavé) est appliqué par Word via des attributs VML (gain/blacklevel)
     * sur la forme, pas sur les pixels de l'image — remplacer le fichier suffit donc à
     * obtenir un filigrane délavé du nouveau logo, sans traitement d'image de notre côté.
     *
     * Les deux emplacements sont nommés `image1.png`/`image2.png` dans le docx et
     * [Content_Types].xml ne déclare que l'extension "png" — un logo administrateur
     * uploadé en .jpg doit donc être ré-encodé en PNG avant injection, sinon Word
     * refuse d'afficher l'image (extension/contenu incohérents).
     */
    private function remplacerLogoEtFiligrane(string $docxAbsPath): void
    {
        $logoPath = Setting::get('logo_path');
        if (!$logoPath || !Storage::disk('public')->exists($logoPath)) {
            return;
        }

        // Un logo SVG ne peut pas être rasterisé par GD ni injecté tel quel dans un
        // slot d'image .png du docx — le filigrane/logo du modèle reste alors inchangé.
        if (Str::endsWith(strtolower($logoPath), '.svg')) {
            return;
        }

        $logoPng = $this->logoEnPng(Storage::disk('public')->path($logoPath));
        if ($logoPng === null) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($docxAbsPath) !== true) {
            return;
        }

        foreach (['word/media/image1.png', 'word/media/image2.png'] as $entree) {
            if ($zip->locateName($entree) !== false) {
                $zip->addFromString($entree, $logoPng);
            }
        }

        $zip->close();
    }

    private function logoEnPng(string $absPath): ?string
    {
        $image = @imagecreatefromstring(file_get_contents($absPath));
        if ($image === false) {
            return null;
        }

        imagesavealpha($image, true);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return $png ?: null;
    }

    /**
     * Résumé des prestations facturées (hors lignes forfaitaires "montant variable" —
     * Timbres fiscaux/Rôles, qui restent des mentions génériques du modèle, jamais
     * calculées) — reproduit la ligne d'explication entre parenthèses du modèle original,
     * de façon générique pour n'importe quel type d'acte plutôt que figée sur "SARLU".
     */
    private function detailPrestations($lignes): string
    {
        $noms = $lignes->pluck('designation')
            ->map(fn ($d) => Str::before($d, ' ('))
            ->implode(', ');

        return $noms !== '' ? "({$noms})" : '';
    }

    private function totalEnLettres(float $total): string
    {
        $lettres = NombreEnLettres::convertir($total, '');
        $lettres = mb_convert_case(mb_strtolower($lettres, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

        return "{$lettres} Francs Guinéens ({$this->formaterMontant($total)} GNF)";
    }

    private function formaterMontant(float $montant): string
    {
        return number_format($montant, 0, ',', ' ');
    }

    private function echapper(?string $texte): string
    {
        return htmlspecialchars($texte ?? '', ENT_QUOTES, 'UTF-8');
    }
}
