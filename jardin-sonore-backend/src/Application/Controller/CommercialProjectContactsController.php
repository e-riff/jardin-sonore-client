<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectPersonEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commercial/projects/{id}/contacts', name: 'commercial_project_contact_', requirements: ['id' => '\d+'])]
final class CommercialProjectContactsController extends AbstractController
{
    #[Route('/link', name: 'link', methods: ['POST'])]
    public function link(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->validateToken("commercial_project_contact_{$id}_link", $request);
        $projectEntity = $this->findProject($id, $entityManager);
        $personEntity = $entityManager->find(PersonEntity::class, $request->request->getInt('personId'));
        if (!$personEntity instanceof PersonEntity || !$personEntity->isActive() || $personEntity->getOrganization() !== $projectEntity->getOrganization()) {
            throw $this->createNotFoundException();
        }

        $projectPersonEntity = $this->findLink($projectEntity, $personEntity);
        if (null === $projectPersonEntity) {
            $projectEntity->addPerson(new CommercialProjectPersonEntity($projectEntity, $personEntity, new DateTimeImmutable()));
        } else {
            $projectPersonEntity->markCurrent();
        }
        if ($request->request->getBoolean('primary')) {
            $projectEntity->setPrimaryContact($personEntity);
        }
        $projectEntity->recordEvent('contact_linked', new DateTimeImmutable(), (string) $personEntity, [], $personEntity);
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->validateToken("commercial_project_contact_{$id}_create", $request);
        $projectEntity = $this->findProject($id, $entityManager);
        $firstName = trim($request->request->getString('firstName'));
        $lastName = trim($request->request->getString('lastName'));
        if ('' === $firstName || '' === $lastName) {
            throw $this->createNotFoundException();
        }

        $personEntity = (new PersonEntity())->setFirstName($firstName)->setLastName($lastName)->setRole(self::optional($request->request->getString('role')));
        $projectEntity->getOrganization()->addPerson($personEntity);
        $entityManager->persist($personEntity);
        $projectEntity->addPerson(new CommercialProjectPersonEntity($projectEntity, $personEntity, new DateTimeImmutable()));
        if ($request->request->getBoolean('primary')) {
            $projectEntity->setPrimaryContact($personEntity);
        }
        $projectEntity->recordEvent('contact_created', new DateTimeImmutable(), (string) $personEntity, [], $personEntity);
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    #[Route('/{personId}/edit', name: 'edit', requirements: ['personId' => '\d+'], methods: ['POST'])]
    public function edit(int $id, int $personId, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->validateToken("commercial_project_contact_{$id}_{$personId}_edit", $request);
        $projectEntity = $this->findProject($id, $entityManager);
        $personEntity = $entityManager->find(PersonEntity::class, $personId);
        if (!$personEntity instanceof PersonEntity || null === $this->findLink($projectEntity, $personEntity) || !$personEntity->isActive()) {
            throw $this->createNotFoundException();
        }
        $firstName = trim($request->request->getString('firstName'));
        $lastName = trim($request->request->getString('lastName'));
        if ('' === $firstName || '' === $lastName) {
            throw $this->createNotFoundException();
        }
        $personEntity->setFirstName($firstName)->setLastName($lastName)->setRole(self::optional($request->request->getString('role')));
        $projectEntity->recordEvent('contact_edited', new DateTimeImmutable(), (string) $personEntity, [], $personEntity);
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    #[Route('/{personId}/primary', name: 'primary', requirements: ['personId' => '\d+'], methods: ['POST'])]
    public function primary(int $id, int $personId, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->validateToken("commercial_project_contact_{$id}_{$personId}_primary", $request);
        $projectEntity = $this->findProject($id, $entityManager);
        $personEntity = $entityManager->find(PersonEntity::class, $personId);
        if (!$personEntity instanceof PersonEntity || !$personEntity->isActive() || !$this->findLink($projectEntity, $personEntity)?->isCurrent()) {
            throw $this->createNotFoundException();
        }
        $projectEntity->setPrimaryContact($personEntity);
        $projectEntity->recordEvent('contact_primary', new DateTimeImmutable(), (string) $personEntity, [], $personEntity);
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    #[Route('/{personId}/former', name: 'former', requirements: ['personId' => '\d+'], methods: ['POST'])]
    public function former(int $id, int $personId, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->validateToken("commercial_project_contact_{$id}_{$personId}_former", $request);
        $projectEntity = $this->findProject($id, $entityManager);
        $personEntity = $entityManager->find(PersonEntity::class, $personId);
        if (!$personEntity instanceof PersonEntity) {
            throw $this->createNotFoundException();
        }
        $projectPersonEntity = $this->findLink($projectEntity, $personEntity);
        if (null === $projectPersonEntity || !$projectPersonEntity->isCurrent()) {
            throw $this->createNotFoundException();
        }
        $projectPersonEntity->markFormer();
        if ($projectEntity->getPrimaryContact() === $personEntity) {
            $projectEntity->setPrimaryContact(null);
        }
        $projectEntity->recordEvent('contact_former', new DateTimeImmutable(), (string) $personEntity, [], $personEntity);
        $entityManager->flush();

        return $this->redirectToRoute('commercial_project_show', ['id' => $id]);
    }

    private function validateToken(string $tokenId, Request $request): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private function findProject(int $id, EntityManagerInterface $entityManager): CommercialProjectEntity
    {
        $projectEntity = $entityManager->find(CommercialProjectEntity::class, $id);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw $this->createNotFoundException();
        }

        return $projectEntity;
    }

    private function findLink(CommercialProjectEntity $projectEntity, PersonEntity $personEntity): ?CommercialProjectPersonEntity
    {
        foreach ($projectEntity->getPeople() as $projectPersonEntity) {
            if ($projectPersonEntity->getPerson() === $personEntity) {
                return $projectPersonEntity;
            }
        }

        return null;
    }

    private static function optional(string $value): ?string
    {
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
