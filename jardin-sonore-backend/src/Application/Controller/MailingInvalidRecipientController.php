<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\InvalidRecipientBatchType;
use App\Application\Form\Model\InvalidRecipientBatchFormModel;
use App\Application\Mailing\InvalidRecipientBatchProcessorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/mailing/invalid-recipients', name: 'mailing_invalid_recipients_', methods: ['GET', 'POST'])]
final class MailingInvalidRecipientController extends AbstractController
{
    public function __construct(
        private InvalidRecipientBatchProcessorInterface $invalidRecipientBatchProcessor,
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
                $results = array_map(
                    static fn (\App\Application\Mailing\InvalidRecipientProcessResult $result): array => [
                        'email' => $result->email,
                        'status' => $result->status,
                        'links_disabled' => $result->linksDisabled,
                        'labels_updated' => $result->labelsUpdated,
                        'notes' => $result->notes,
                    ],
                    $this->invalidRecipientBatchProcessor->process($emails, $formModel->action),
                );
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
}
