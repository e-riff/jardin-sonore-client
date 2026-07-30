<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Application\Session\SessionDocumentGeneratorInterface;
use App\Domain\Model\Session\SessionSummary;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

final readonly class DompdfSessionDocumentGenerator implements SessionDocumentGeneratorInterface
{
    public function __construct(
        private Environment $twig,
        #[Autowire('%kernel.project_dir%/var/session-documents')]
        private string $sessionDocumentDirectory,
    ) {
    }

    public function generate(SessionSummary $sessionSummary): string
    {
        if (!is_dir($this->sessionDocumentDirectory) && !mkdir($this->sessionDocumentDirectory, 0775, true) && !is_dir($this->sessionDocumentDirectory)) {
            throw new RuntimeException("Unable to create {$this->sessionDocumentDirectory}.");
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->twig->render('session/document.pdf.twig', ['session' => $sessionSummary]));
        $dompdf->setPaper('A4');
        $dompdf->render();

        $documentPath = $this->sessionDocumentDirectory . '/' . $sessionSummary->getUuid()->toRfc4122() . '.pdf';
        $temporaryPath = $documentPath . '.tmp';
        if (false === file_put_contents($temporaryPath, $dompdf->output()) || !rename($temporaryPath, $documentPath)) {
            throw new RuntimeException("Unable to store {$documentPath}.");
        }

        return $documentPath;
    }
}
