<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PdfGeneratorService
{
    private $twig;
    private $params;
    private $projectDir;

    public function __construct(Environment $twig, ParameterBagInterface $params, string $projectDir)
    {
        $this->twig = $twig;
        $this->params = $params;
        $this->projectDir = $projectDir;
    }

    public function generateRapportPdf(array $data): string
    {
        // Configuration optimisée pour DomPDF
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('chroot', $this->projectDir . '/public');
        $options->set('defaultPaperSize', 'A4');
        $options->set('defaultPaperOrientation', 'portrait');
        
        // Optimisations pour la performance
        $options->set('isFontSubsettingEnabled', true);
        $options->set('isJavascriptEnabled', false);

        $dompdf = new Dompdf($options);

        try {
            // Rendre le template Twig
            $html = $this->twig->render('rapport/pdf_template.html.twig', $data);

            $dompdf->loadHtml($html);
            $dompdf->render();

            return $dompdf->output();
            
        } catch (\Exception $e) {
            throw new \RuntimeException('Erreur lors de la génération du PDF: ' . $e->getMessage());
        }
    }
}