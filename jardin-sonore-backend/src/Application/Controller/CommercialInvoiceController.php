<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Commercial\CommercialAmountParser;
use App\Application\Commercial\RecordCommercialInvoice;
use App\Application\Form\CommercialInvoiceType;
use App\Infrastructure\Doctrine\Entity\CommercialInvoiceEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommercialInvoiceController extends AbstractController
{
    #[Route('/commercial/invoices', name: 'commercial_invoice_index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $invoices = $entityManager->getRepository(CommercialInvoiceEntity::class)->findBy([], ['issuedOn' => 'DESC']);

        return $this->render('commercial/invoice/index.html.twig', ['invoices' => $invoices]);
    }

    #[Route('/commercial/projects/{id}/invoices/new', name: 'commercial_invoice_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function new(int $id, Request $request, EntityManagerInterface $entityManager, RecordCommercialInvoice $recorder): Response
    {
        $projectEntity = $entityManager->find(CommercialProjectEntity::class, $id);
        if (!$projectEntity instanceof CommercialProjectEntity || CommercialProjectEntity::STATUS_CONFIRMED !== $projectEntity->getStatus()) {
            throw $this->createNotFoundException();
        }
        $form = $this->createForm(CommercialInvoiceType::class, ['issuedOn' => new DateTimeImmutable('today')]);
        $form->handleRequest($request);
        $error = null;
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $reference = trim((string) ($data['reference'] ?? ''));
            $amountCents = CommercialAmountParser::euroCents((string) ($data['amount'] ?? ''));
            $existingInvoiceEntity = $entityManager->getRepository(CommercialInvoiceEntity::class)->findOneBy(['reference' => $reference]);
            if (null === $amountCents) {
                $error = 'invoice.amount_invalid';
            } elseif ($existingInvoiceEntity instanceof CommercialInvoiceEntity) {
                $error = 'invoice.reference_exists';
            } else {
                $recorder->issue($projectEntity, $reference, $amountCents, $data['issuedOn']);
                $entityManager->flush();

                return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
            }
        }

        return $this->render('commercial/invoice/new.html.twig', [
            'project' => $projectEntity,
            'form' => $form,
            'invoiceError' => $error,
        ], new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/commercial/invoices/{id}/pay', name: 'commercial_invoice_pay', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function pay(int $id, Request $request, EntityManagerInterface $entityManager, RecordCommercialInvoice $recorder): Response
    {
        if (!$this->isCsrfTokenValid("commercial_invoice_pay_{$id}", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $invoiceEntity = $entityManager->find(CommercialInvoiceEntity::class, $id);
        if (!$invoiceEntity instanceof CommercialInvoiceEntity) {
            throw $this->createNotFoundException();
        }
        $paidOn = DateTimeImmutable::createFromFormat('!Y-m-d', $request->request->getString('paidOn'));
        if (!$paidOn instanceof DateTimeImmutable) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $recorder->markPaid($invoiceEntity, $paidOn);
        $entityManager->flush();

        return $this->redirectToRoute('commercial_invoice_index');
    }
}
