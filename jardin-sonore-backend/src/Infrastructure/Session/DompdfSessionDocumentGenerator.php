<?php

declare(strict_types=1);

namespace App\Infrastructure\Session;

use App\Application\Session\SessionDocumentGeneratorInterface;
use App\Application\Session\SessionDocumentView;
use App\Application\Session\SessionSummaryView;
use App\Application\Session\YoutubeThumbnailProviderInterface;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Domain\Repository\SessionRecommendationRepositoryInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

final readonly class DompdfSessionDocumentGenerator implements SessionDocumentGeneratorInterface
{
    public function __construct(
        private Environment $twig,
        private RepertoireItemRepositoryInterface $repertoireItemRepository,
        private InstrumentRepositoryInterface $instrumentRepository,
        private SessionRecommendationRepositoryInterface $sessionRecommendationRepository,
        private YoutubeThumbnailProviderInterface $youtubeThumbnailProvider,
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
        $sessionSummaryView = SessionSummaryView::fromDomain(
            $sessionSummary,
            $this->repertoireItemRepository,
            $this->instrumentRepository,
            $this->sessionRecommendationRepository,
        );
        $sessionDocumentView = SessionDocumentView::fromSessionSummaryView($sessionSummaryView, $this->youtubeThumbnailProvider);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->twig->render('session/document.pdf.twig', ['document' => $sessionDocumentView]));
        $dompdf->setPaper('A4');
        $dompdf->render();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text(475, 814, 'Page {PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.4, 0.45, 0.43]);

        $documentPath = $this->sessionDocumentDirectory . '/' . $sessionSummary->getUuid()->toRfc4122() . '.pdf';
        $temporaryPath = $documentPath . '.tmp';
        if (false === file_put_contents($temporaryPath, $dompdf->output()) || !rename($temporaryPath, $documentPath)) {
            throw new RuntimeException("Unable to store {$documentPath}.");
        }

        return $documentPath;
    }
}
