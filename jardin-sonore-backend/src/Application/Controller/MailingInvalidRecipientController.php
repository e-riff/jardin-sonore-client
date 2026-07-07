<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\InvalidRecipientBatchType;
use App\Application\Form\Model\InvalidRecipientBatchFormModel;
use App\Infrastructure\Doctrine\Entity\EmailContactEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/mailing/invalid-recipients', name: 'mailing_invalid_recipients_', methods: ['GET', 'POST'])]
final class MailingInvalidRecipientController extends AbstractController
{
    private const string INVALID_RECIPIENT_LABEL = 'invalid_recipient';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'index')]
    public function index(Request $request): Response
    {
        $formModel = new InvalidRecipientBatchFormModel();
        $form = $this->createForm(InvalidRecipientBatchType::class, $formModel);
        $form->handleRequest($request);

        /** @var list<array{
         *     email:string,
         *     status:'updated'|'not_found',
         *     links_disabled:int,
         *     directory_entries_tagged:int,
         *     notes:list<string>
         * }> $results
         */
        $results = [];

        $hasProcessingError = false;

        if ($form->isSubmitted() && $form->isValid()) {
            $emails = $this->parseEmails($formModel->emails);

            if ([] === $emails) {
                $form->get('emails')->addError(new FormError($this->translator->trans(
                    'mailing.invalid_recipient.form.emails_empty',
                    [],
                    'mailing',
                )));
                $hasProcessingError = true;
            } else {
                foreach ($emails as $email) {
                    $results[] = $this->processEmail($email, $formModel->action);
                }

                $this->entityManager->flush();
            }
        }

        return $this->render(
            'mailing/invalid_recipients.html.twig',
            [
                'form' => $form->createView(),
                'results' => $results,
            ],
            ($form->isSubmitted() && (!$form->isValid() || $hasProcessingError))
                ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY)
                : null,
        );
    }

    /**
     * @return list<string>
     */
    private function parseEmails(string $emails): array
    {
        $tokens = preg_split('/[\s,;]+/', mb_strtolower(trim($emails))) ?: [];
        $tokens = array_values(array_filter(array_map('trim', $tokens)));

        return array_values(array_unique($tokens));
    }

    /**
     * @return array{
     *     email:string,
     *     status:'updated'|'not_found',
     *     links_disabled:int,
     *     labels_updated:int,
     *     notes:list<string>
     * }
     */
    private function processEmail(string $email, string $action): array
    {
        $emailContact = $this->entityManager->getRepository(EmailContactEntity::class)->findOneBy([
            'emailAddress' => $email,
        ]);

        if (!$emailContact instanceof EmailContactEntity) {
            return [
                'email' => $email,
                'status' => 'not_found',
                'links_disabled' => 0,
                'labels_updated' => 0,
                'notes' => ['mailing.invalid_recipient.result.not_found_note'],
            ];
        }

        $emailContact->setOptInNewsletter(false);

        $linksDisabled = 0;
        $labelsUpdated = 0;
        $notes = [];

        if ('unsubscribe' === $action) {
            $emailContact->setUnsubscribedAt(new DateTimeImmutable());
            $notes[] = 'mailing.invalid_recipient.result.unsubscribed_note';
        } else {
            $emailContact->setActive(false);
            $notes[] = 'mailing.invalid_recipient.result.invalid_recipient_note';

            foreach ($emailContact->getEmailContactLinks() as $emailContactLink) {
                $emailContactLink->setActive(false);
                ++$linksDisabled;
                $emailContactLink->setLabel($this->mergeInvalidRecipientLabel($emailContactLink->getLabel()));
                ++$labelsUpdated;
            }
        }

        $this->entityManager->persist($emailContact);

        return [
            'email' => $email,
            'status' => 'updated',
            'links_disabled' => $linksDisabled,
            'labels_updated' => $labelsUpdated,
            'notes' => $notes,
        ];
    }

    private function mergeInvalidRecipientLabel(?string $currentLabel): string
    {
        $currentLabel = null === $currentLabel ? null : trim($currentLabel);

        if (null === $currentLabel || '' === $currentLabel) {
            return self::INVALID_RECIPIENT_LABEL;
        }

        $labels = array_values(array_filter(array_map('trim', explode('|', $currentLabel))));

        foreach ($labels as $label) {
            if (self::INVALID_RECIPIENT_LABEL === mb_strtolower($label)) {
                return $currentLabel;
            }
        }

        $labels[] = self::INVALID_RECIPIENT_LABEL;

        return implode(' | ', $labels);
    }
}
