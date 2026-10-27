<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Backoffice\TableSort;
use App\Application\Form\Model\NewsletterSubscriberFormModel;
use App\Application\Form\NewsletterSubscriberType;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Infrastructure\Mailing\FreeNewsletterSubscriptionManager;
use App\Infrastructure\Mailing\NewsletterSubscriberCatalog;
use DomainException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/newsletter/subscribers', name: 'newsletter_subscriber_')]
final class NewsletterSubscriberAdminController extends AbstractController
{
    public function __construct(
        private readonly FreeNewsletterSubscriptionManager $subscriptionManager,
        private readonly EmailContactDoctrineRepository $emailContactRepository,
        private readonly NewsletterSubscriberCatalog $subscriberCatalog,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = trim(mb_substr($request->query->getString('query'), 0, 120));
        $kind = $request->query->getString('kind');
        $kind = in_array($kind, ['linked', 'free'], true) ? $kind : '';
        $status = $request->query->getString('status');
        $status = in_array($status, ['subscribed', 'not_subscribed', 'unsubscribed', 'inactive', 'pending'], true) ? $status : '';
        $perPage = 25;
        $total = $this->subscriberCatalog->count($query, $kind, $status);
        $pageCount = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $request->query->getInt('page', 1)), $pageCount);
        $tableSort = TableSort::fromQuery(
            $request->query->getString('sort'),
            $request->query->getString('direction'),
            ['email', 'type', 'linked_count', 'status'],
            'email',
            'asc',
        );

        return $this->render('newsletter/subscriber_index.html.twig', [
            'subscribers' => $this->subscriberCatalog->find($query, $kind, $status, $page, $perPage, $tableSort->column, strtoupper($tableSort->direction)),
            'query' => $query,
            'kindFilter' => $kind,
            'statusFilter' => $status,
            'total' => $total,
            'page' => $page,
            'pageCount' => $pageCount,
            'tableSort' => $tableSort,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        return $this->subscriptionForm($request);
    }

    #[Route('/{uuid}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $uuid): Response
    {
        return $this->subscriptionForm($request, $this->loadContact($uuid));
    }

    #[Route('/{uuid}/unsubscribe', name: 'unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request, string $uuid): Response
    {
        $emailContactEntity = $this->loadContact($uuid);
        if (!$this->isCsrfTokenValid('newsletter_unsubscribe_' . $uuid, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $this->subscriptionManager->unsubscribeFromBackoffice($uuid);
        $this->addFlash('success', $this->translator->trans('subscriber.withdrawn', [], 'newsletter'));

        return $this->redirectToRoute('newsletter_subscriber_index', ['query' => $emailContactEntity->getEmailAddress()]);
    }

    private function subscriptionForm(Request $request, ?EmailContactEntity $emailContactEntity = null): Response
    {
        $formModel = new NewsletterSubscriberFormModel();
        $formModel->emailAddress = $emailContactEntity?->getEmailAddress() ?? '';
        $form = $this->createForm(NewsletterSubscriberType::class, $formModel, ['locked_email' => null !== $emailContactEntity]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $subscribedEmailContactEntity = $this->subscriptionManager->subscribeFromBackoffice($formModel->emailAddress, $formModel->consentAttested);
                $this->addFlash('success', $this->translator->trans('subscriber.activated', [], 'newsletter'));

                return $this->redirectToRoute('newsletter_subscriber_index', ['query' => $subscribedEmailContactEntity->getEmailAddress()]);
            } catch (DomainException) {
                $form->addError(new FormError($this->translator->trans('subscriber.blocked', [], 'newsletter')));
            }
        }

        return $this->render('newsletter/subscriber_form.html.twig', [
            'form' => $form,
            'emailContact' => $emailContactEntity,
            'confirmationPending' => $emailContactEntity instanceof EmailContactEntity && $this->subscriberCatalog->hasPendingConfirmation($emailContactEntity->getId()),
            'emailsUrl' => $this->generateUrl('newsletter_subscriber_index'),
        ], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    private function loadContact(string $uuid): EmailContactEntity
    {
        if (!Uuid::isValid($uuid)) {
            throw $this->createNotFoundException();
        }
        $emailContactEntity = $this->emailContactRepository->findOneBy(['uuid' => Uuid::fromString($uuid)]);

        return $emailContactEntity ?? throw $this->createNotFoundException();
    }
}
