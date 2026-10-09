<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Commercial\QualifyCommercialRequest;
use App\Application\Form\CommercialQualificationType;
use App\Application\Form\CommercialRequestType;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commercial/requests', name: 'commercial_request_')]
final class CommercialRequestController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $requests = $entityManager->getRepository(CommercialRequestEntity::class)->findBy([], ['receivedAt' => 'DESC']);

        return $this->render('commercial/request/index.html.twig', ['requests' => $requests]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $postedData = $request->request->all('commercial_request');
        $selectedOrganizationId = filter_var($postedData['organization'] ?? null, FILTER_VALIDATE_INT);
        $selectedOrganizationEntity = false === $selectedOrganizationId ? null : $entityManager->find(OrganizationEntity::class, $selectedOrganizationId);
        $people = $selectedOrganizationEntity instanceof OrganizationEntity
            ? array_values(array_filter($selectedOrganizationEntity->getPeople()->toArray(), static fn (PersonEntity $personEntity): bool => $personEntity->isActive()))
            : [];
        $form = $this->createForm(CommercialRequestType::class, null, ['people' => $people]);
        $form->handleRequest($request);
        $error = null;
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $organizationEntity = $data['organization'] ?? null;
            $newOrganizationName = trim((string) ($data['newOrganizationName'] ?? ''));
            $personEntity = $data['person'] ?? null;
            $personMode = (string) ($data['personMode'] ?? 'existing');
            $newPersonFirstName = trim((string) ($data['newPersonFirstName'] ?? ''));
            $newPersonLastName = trim((string) ($data['newPersonLastName'] ?? ''));
            $newPersonRole = trim((string) ($data['newPersonRole'] ?? ''));
            $senderName = '';
            if (($organizationEntity instanceof OrganizationEntity) === ('' !== $newOrganizationName)) {
                $error = 'request.choose_organization';
            } elseif (!in_array($personMode, ['existing', 'new', 'unknown'], true)) {
                $error = 'request.person_mode_invalid';
            } elseif ('existing' === $personMode && !$personEntity instanceof PersonEntity) {
                $error = 'request.choose_person';
            } elseif ('existing' !== $personMode && $personEntity instanceof PersonEntity) {
                $error = 'request.choose_person_mode';
            } elseif ('new' === $personMode && ('' === $newPersonFirstName || '' === $newPersonLastName)) {
                $error = 'request.person_incomplete';
            } elseif ('new' !== $personMode && ('' !== $newPersonFirstName || '' !== $newPersonLastName || '' !== $newPersonRole)) {
                $error = 'request.choose_person_mode';
            } elseif ('existing' === $personMode && $personEntity instanceof PersonEntity && (!$personEntity->isActive() || $personEntity->getOrganization() !== $organizationEntity)) {
                $error = 'request.choose_person';
            } elseif ($organizationEntity instanceof OrganizationEntity && !$organizationEntity->isActive()) {
                $error = 'request.organization_inactive';
            } else {
                if (!$organizationEntity instanceof OrganizationEntity) {
                    $organizationEntity = (new OrganizationEntity())->setName($newOrganizationName);
                    $entityManager->persist($organizationEntity);
                }
                if ('new' === $personMode) {
                    $personEntity = (new PersonEntity())->setFirstName($newPersonFirstName)->setLastName($newPersonLastName)->setRole(self::optional($newPersonRole));
                    $organizationEntity->addPerson($personEntity);
                    $entityManager->persist($personEntity);
                }
                $senderName = $personEntity instanceof PersonEntity ? (string) $personEntity : '';
                $requestEntity = new CommercialRequestEntity(
                    'manual',
                    $senderName,
                    trim((string) ($data['emailAddress'] ?? '')),
                    trim((string) ($data['message'] ?? '')),
                    new DateTimeImmutable(),
                    $organizationEntity->getName(),
                    null,
                    self::optional((string) ($data['phone'] ?? '')),
                );
                $requestEntity->setOrganization($organizationEntity);
                $requestEntity->setPerson($personEntity instanceof PersonEntity ? $personEntity : null);
                $entityManager->persist($requestEntity);
                $entityManager->flush();

                return $this->redirectToRoute('commercial_request_show', ['id' => $requestEntity->getId()]);
            }
        }

        return $this->render('commercial/request/new.html.twig', ['form' => $form, 'requestError' => $error], new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/people', name: 'people', methods: ['GET'])]
    public function people(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $organizationId = $request->query->getInt('organizationId');
        $organizationEntity = $entityManager->find(OrganizationEntity::class, $organizationId);
        if (!$organizationEntity instanceof OrganizationEntity || !$organizationEntity->isActive()) {
            throw $this->createNotFoundException();
        }

        $people = [];
        foreach ($organizationEntity->getPeople() as $personEntity) {
            if (!$personEntity->isActive()) {
                continue;
            }
            $role = $personEntity->getRole();
            $people[] = [
                'id' => $personEntity->getId(),
                'label' => (string) $personEntity . (null === $role || '' === trim($role) ? '' : " — {$role}"),
            ];
        }

        return $this->json(['people' => $people]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request, EntityManagerInterface $entityManager, QualifyCommercialRequest $qualifier): Response
    {
        $requestEntity = $entityManager->find(CommercialRequestEntity::class, $id);
        if (!$requestEntity instanceof CommercialRequestEntity) {
            throw $this->createNotFoundException();
        }

        $requestOrganizationEntity = $requestEntity->getOrganization();
        $requestPersonEntity = $requestEntity->getPerson();
        $organizationLocked = $requestOrganizationEntity instanceof OrganizationEntity && $requestOrganizationEntity->isActive();
        $personLocked = $organizationLocked
            && $requestPersonEntity instanceof PersonEntity
            && $requestPersonEntity->isActive()
            && $requestPersonEntity->getOrganization() === $requestOrganizationEntity;
        $formData = [
            'projectTitle' => $requestEntity->getOrganizationName() ?: '',
        ];
        if (!$organizationLocked) {
            $formData['organizationMode'] = 'existing';
            $formData['organization'] = $requestOrganizationEntity;
        }
        $form = $this->createForm(CommercialQualificationType::class, $formData, [
            'organization_locked' => $organizationLocked,
            'person_locked' => $personLocked,
        ]);
        $form->handleRequest($request);
        $error = null;
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array<string, mixed> $data */
            $data = $form->getData();
            $organizationMode = (string) ($data['organizationMode'] ?? 'existing');
            $organizationEntity = $organizationLocked
                ? $requestOrganizationEntity
                : ('existing' === $organizationMode ? ($data['organization'] ?? null) : null);
            $newOrganizationName = trim((string) ($data['newOrganizationName'] ?? ''));
            $newPersonFirstName = trim((string) ($data['newPersonFirstName'] ?? ''));
            $newPersonLastName = trim((string) ($data['newPersonLastName'] ?? ''));
            $newPersonRole = trim((string) ($data['newPersonRole'] ?? ''));
            if (!$organizationLocked && (!in_array($organizationMode, ['existing', 'new'], true)
                || ('existing' === $organizationMode && (!$organizationEntity instanceof OrganizationEntity || '' !== $newOrganizationName))
                || ('new' === $organizationMode && ($organizationEntity instanceof OrganizationEntity || '' === $newOrganizationName)))) {
                $error = 'qualification.choose_one';
            } elseif (('' === $newPersonFirstName) !== ('' === $newPersonLastName) || ('' !== $newPersonRole && '' === $newPersonFirstName)) {
                $error = 'qualification.person_incomplete';
            } elseif (CommercialRequestEntity::STATUS_WITHOUT_RESULT === $requestEntity->getStatus()) {
                $error = 'qualification.closed';
            } else {
                if (!$organizationEntity instanceof OrganizationEntity) {
                    $organizationEntity = (new OrganizationEntity())->setName($newOrganizationName);
                    $entityManager->persist($organizationEntity);
                }
                if (!$organizationEntity->isActive()) {
                    $error = 'qualification.inactive';
                } else {
                    $people = [];
                    $primaryContact = $requestEntity->getPerson();
                    if ($primaryContact instanceof PersonEntity && $primaryContact->getOrganization() === $organizationEntity && $primaryContact->isActive()) {
                        $people[] = $primaryContact;
                    } else {
                        $primaryContact = null;
                    }
                    if ('' !== $newPersonFirstName) {
                        $primaryContact = (new PersonEntity())
                            ->setFirstName($newPersonFirstName)
                            ->setLastName($newPersonLastName)
                            ->setRole('' === $newPersonRole ? null : $newPersonRole);
                        $organizationEntity->addPerson($primaryContact);
                        $entityManager->persist($primaryContact);
                        $people[] = $primaryContact;
                    }
                    $qualifier->qualify($requestEntity, $organizationEntity, $people, (string) ($data['projectTitle'] ?? ''), $primaryContact);
                    $entityManager->flush();

                    return $this->redirectToRoute('commercial_request_show', ['id' => $id]);
                }
            }
        }

        return $this->render('commercial/request/show.html.twig', [
            'commercialRequest' => $requestEntity,
            'form' => $form,
            'qualificationError' => $error,
            'organizationLocked' => $organizationLocked,
            'personLocked' => $personLocked,
        ]);
    }

    #[Route('/{id}/close', name: 'close', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function close(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('commercial_request_close_' . $id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $requestEntity = $entityManager->find(CommercialRequestEntity::class, $id);
        if (!$requestEntity instanceof CommercialRequestEntity) {
            throw $this->createNotFoundException();
        }
        if (CommercialRequestEntity::STATUS_TO_QUALIFY !== $requestEntity->getStatus()) {
            throw $this->createNotFoundException();
        }
        $requestEntity->closeWithoutResult();
        $entityManager->flush();

        return $this->redirectToRoute('commercial_request_show', ['id' => $id]);
    }

    private static function optional(?string $value): ?string
    {
        $value = trim($value ?? '');

        return '' === $value ? null : $value;
    }
}
