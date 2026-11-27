<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Mailing\Query\OutreachJournalFilter;
use App\Application\Mailing\Query\OutreachJournalReaderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class OutreachJournalController extends AbstractController
{
    #[Route('/mailing/outreach-journal', name: 'mailing_outreach_journal', methods: ['GET'])]
    public function __invoke(
        OutreachJournalReaderInterface $reader,
        Request $request,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
    ): Response {
        $filter = new OutreachJournalFilter(
            organizationUuid: $request->query->getString('organizationUuid') ?: null,
            campaignUuid: $request->query->getString('campaignUuid') ?: null,
            organizationType: $request->query->getString('organizationType') ?: null,
            since: $request->query->getString('since') ?: null,
            until: $request->query->getString('until') ?: null,
            page: $request->query->getInt('page', 1),
        );
        if (0 < count($validator->validate($filter))) {
            return new Response($translator->trans('mailing.outreach.invalid_filters', domain: 'mailing'), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('mailing/outreach_journal/index.html.twig', ['journal' => $reader->search($filter), 'filter' => $filter, 'choices' => $reader->getFilterChoices()]);
    }
}
