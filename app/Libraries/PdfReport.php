<?php
namespace App\Libraries;

use RuntimeException;

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * PdfReport — rend le rapport en PDF via mPDF à partir du gabarit templates/report.php.
 *
 * Cette classe ne calcule aucune donnée : elle se contente d'exécuter le gabarit
 * d'affichage avec les données fournies par ReportData puis d'écrire le PDF.
 */
class PdfReport
{
    /**
     * @param array<string,mixed> $config Configuration complète (config.php)
     * @param string $templatePath Gabarit HTML à rendre
     * @param string $filePrefix   Préfixe du nom de fichier (ex. "rapport", "audit")
     * @param array<string,mixed> $mpdfOptions Options mPDF supplémentaires (ex. packTableData)
     */
    public function __construct(
        private array $config,
        private string $templatePath,
        private string $filePrefix = 'rapport',
        private array $mpdfOptions = []
    ) {}

    /**
     * Génère le PDF et renvoie son chemin sur disque.
     *
     * @param array<string,mixed> $data Données produites par ReportData::build()
     * @param string|null $token Jeton de nom de fichier partagé (ex. horodatage) ; auto si null.
     */
    public function generate(array $data, string $outputDir, ?string $token = null): string
    {
        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new RuntimeException("Impossible de créer le dossier de sortie : $outputDir");
        }

        $tmpDir = sys_get_temp_dir() . '/mpdf';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0775, true);
        }

        $html = $this->renderTemplate($data);

        // Le registre complet du document de traçabilité peut produire un HTML de
        // plusieurs Mo : mPDF le parse avec des regex et bute alors sur la limite
        // PCRE par défaut (1 Mo). On la relève pour la durée du rendu (sans coût
        // mémoire si elle n'est pas atteinte).
        if (strlen($html) > 900_000) {
            @ini_set('pcre.backtrack_limit', '100000000');
        }

        $mpdf = new Mpdf(array_merge([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'margin_top'     => 20,
            'margin_bottom'  => 20,
            'margin_left'    => 15,
            'margin_right'   => 15,
            'margin_header'  => 8,
            'margin_footer'  => 8,
            'tempDir'        => $tmpDir,
        ], $this->mpdfOptions));
        $mpdf->SetTitle($data['title'] ?? $this->config['report']['app_name']);
        $mpdf->SetAuthor('Rapport automatisé');
        $mpdf->WriteHTML($html);

        $file = rtrim($outputDir, '/') . '/' . $this->filePrefix . '-' . ($token ?? date('Ymd-His')) . '.pdf';
        $mpdf->Output($file, Destination::FILE);

        return $file;
    }

    /**
     * Exécute le gabarit PHP dans une portée isolée et capture son rendu HTML.
     *
     * @param array<string,mixed> $data
     */
    private function renderTemplate(array $data): string
    {
        $render = static function (string $__templatePath, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            include $__templatePath;
            return (string) ob_get_clean();
        };

        return $render($this->templatePath, $data);
    }
}
