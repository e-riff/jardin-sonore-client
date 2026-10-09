<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Commercial\CommercialAmountParser;
use App\Application\Commercial\RecordCommercialQuote;
use App\Application\Form\CommercialQuoteType;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialQuoteEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommercialQuoteController extends AbstractController
{
    #[Route('/commercial/projects/{id}/quotes/new', name: 'commercial_quote_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(int $id, Request $request, EntityManagerInterface $entityManager, RecordCommercialQuote $recorder): Response
    {
        $projectEntity = $entityManager->find(CommercialProjectEntity::class, $id);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw $this->createNotFoundException();
        }
        $form = $this->createForm(CommercialQuoteType::class, ['sentOn' => new DateTimeImmutable('today')], [
            'quotes' => $projectEntity->getQuotes()->toArray(),
        ]);
        $form->handleRequest($request);
        $error = null;
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $reference = trim((string) ($data['reference'] ?? ''));
            $amountCents = CommercialAmountParser::euroCents((string) ($data['amount'] ?? ''));
            $existingQuoteEntity = $entityManager->getRepository(CommercialQuoteEntity::class)->findOneBy(['reference' => $reference]);
            if (null === $amountCents) {
                $error = 'quote.amount_invalid';
            } elseif ($existingQuoteEntity instanceof CommercialQuoteEntity) {
                $error = 'quote.reference_exists';
            } else {
                $sentOn = $data['sentOn'];
                $quoteEntity = $recorder->sent(
                    $projectEntity,
                    $reference,
                    (string) $data['filename'],
                    $amountCents,
                    $sentOn,
                    $data['replaces'] instanceof CommercialQuoteEntity ? $data['replaces'] : null,
                );
                if (true === ($data['followup'] ?? false)) {
                    $dueOn = $sentOn->modify('+7 days');
                    $projectEntity->addAction(new CommercialActionEntity($projectEntity, "Relancer le devis {$reference}", $dueOn, null, $projectEntity->getPrimaryContact()));
                    $projectEntity->recordEvent('action_created', new DateTimeImmutable(), "Relancer le devis {$reference}");
                }
                $entityManager->flush();

                return $this->redirectToRoute('commercial_project_show', ['id' => $quoteEntity->getProject()->getId()]);
            }
        }

        return $this->render('commercial/quote/new.html.twig', [
            'project' => $projectEntity,
            'form' => $form,
            'quoteError' => $error,
        ], new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/commercial/quotes/{id}/sign', name: 'commercial_quote_sign', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function sign(int $id, Request $request, EntityManagerInterface $entityManager, RecordCommercialQuote $recorder): Response
    {
        if (!$this->isCsrfTokenValid("commercial_quote_sign_{$id}", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $quoteEntity = $entityManager->find(CommercialQuoteEntity::class, $id);
        if (!$quoteEntity instanceof CommercialQuoteEntity) {
            throw $this->createNotFoundException();
        }
        $signedOn = DateTimeImmutable::createFromFormat('!Y-m-d', $request->request->getString('signedOn'));
        if (!$signedOn instanceof DateTimeImmutable) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $recorder->markSigned($quoteEntity, $signedOn);
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $quoteEntity->getProject()->getId()]);
    }
}
