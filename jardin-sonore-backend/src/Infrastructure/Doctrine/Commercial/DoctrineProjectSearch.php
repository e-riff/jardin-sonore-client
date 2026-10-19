<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Commercial;

use App\Application\Mcp\ProjectSearch;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class DoctrineProjectSearch implements ProjectSearch
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /** @return list<array<string, scalar|null>> */
    public function search(?string $email, ?string $organization, ?string $terms, int $limit): array
    {
        $projectRepository = $this->managerRegistry->getRepository(CommercialProjectEntity::class);
        $queryBuilder = $projectRepository->createQueryBuilder('project')
            ->select('DISTINCT project')
            ->leftJoin('project.organization', 'organization')
            ->leftJoin('project.primaryContact', 'primaryContact')
            ->leftJoin('primaryContact.contactDetails', 'primaryContactDetails')
            ->leftJoin('primaryContactDetails.emailContactLinks', 'primaryEmailLink')
            ->leftJoin('primaryEmailLink.emailContact', 'primaryEmail')
            ->leftJoin('project.people', 'projectPerson')
            ->leftJoin('projectPerson.person', 'participant')
            ->leftJoin('participant.contactDetails', 'participantDetails')
            ->leftJoin('participantDetails.emailContactLinks', 'participantEmailLink')
            ->leftJoin('participantEmailLink.emailContact', 'participantEmail');

        if (null !== $email) {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(
                'LOWER(primaryEmail.emailAddress) = :email',
                'LOWER(participantEmail.emailAddress) = :email',
            ))->setParameter('email', mb_strtolower($email));
        }

        if (null !== $organization) {
            $queryBuilder->andWhere('LOWER(organization.name) LIKE :organization')
                ->setParameter('organization', '%' . mb_strtolower($organization) . '%');
        }

        if (null !== $terms) {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(
                'LOWER(project.title) LIKE :terms',
                'LOWER(organization.name) LIKE :terms',
            ))->setParameter('terms', '%' . mb_strtolower($terms) . '%');
        }

        $projects = $queryBuilder
            ->orderBy('project.createdAt', 'DESC')
            ->setMaxResults(max(1, min($limit, 20)))
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($projects as $projectEntity) {
            if (!$projectEntity instanceof CommercialProjectEntity) {
                continue;
            }

            $primaryContactEntity = $projectEntity->getPrimaryContact();
            $results[] = [
                'id' => $projectEntity->getId(),
                'title' => $projectEntity->getTitle(),
                'status' => $projectEntity->getStatus(),
                'organization' => $projectEntity->getOrganization()?->getName(),
                'contact' => null === $primaryContactEntity ? null : trim($primaryContactEntity->getFirstName() . ' ' . $primaryContactEntity->getLastName()),
                'createdAt' => $projectEntity->getCreatedAt()->format(DATE_ATOM),
                'url' => $this->urlGenerator->generate('commercial_project_show', ['id' => $projectEntity->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        return $results;
    }
}
