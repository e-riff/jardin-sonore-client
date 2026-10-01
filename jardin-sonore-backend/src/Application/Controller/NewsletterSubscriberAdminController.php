<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\Model\NewsletterSubscriberFormModel;
use App\Application\Form\NewsletterSubscriberType;
use App\Infrastructure\Admin\EmailContactCrudController;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use App\Infrastructure\Doctrine\Repository\EmailContactDoctrineRepository;
use App\Infrastructure\Mailing\FreeNewsletterSubscriptionManager;
use DomainException;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
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
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
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

        return $this->redirect($this->contactUrl($emailContactEntity));
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

                return $this->redirect($this->contactUrl($subscribedEmailContactEntity));
            } catch (DomainException) {
                $form->addError(new FormError($this->translator->trans('subscriber.blocked', [], 'newsletter')));
            }
        }

        return $this->render('newsletter/subscriber_form.html.twig', [
            'form' => $form,
            'emailContact' => $emailContactEntity,
            'emailsUrl' => $this->adminUrlGenerator->unsetAll()->setController(EmailContactCrudController::class)->setAction(Action::INDEX)->generateUrl(),
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

    private function contactUrl(EmailContactEntity $emailContactEntity): string
    {
        return $this->adminUrlGenerator->unsetAll()->setController(EmailContactCrudController::class)->setAction(Action::DETAIL)
            ->setEntityId($emailContactEntity->getId())->generateUrl();
    }
}
