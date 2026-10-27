<?php

declare(strict_types=1);

namespace App\Infrastructure\Doctrine\Commercial;

use App\Application\Mcp\ProjectContextReader;
use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialAppointmentEntity;
use App\Infrastructure\Doctrine\Entity\CommercialEventEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\ContactDetailsEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class DoctrineProjectContextReader implements ProjectContextReader
{
    public function __construct(private ManagerRegistry $managerRegistry, private UrlGeneratorInterface $urlGenerator)
    {
    }

    /** @return array<string, mixed> */
    public function get(int $projectId): array
    {
        $projectEntity = $this->managerRegistry->getRepository(CommercialProjectEntity::class)->find($projectId);
        if (!$projectEntity instanceof CommercialProjectEntity) {
            throw new RuntimeException('Project not found.');
        }

        $eventEntities = $this->managerRegistry->getRepository(CommercialEventEntity::class)
            ->createQueryBuilder('event')
            ->where('event.project = :project')
            ->andWhere('event.type IN (:eventTypes)')
            ->setParameter('project', $projectEntity)
            ->setParameter('eventTypes', ['note', 'action_completed', 'project_status_changed', 'project_confirmed', 'project_without_result', 'project_completed'])
            ->orderBy('event.occurredAt', 'DESC')
            ->addOrderBy('event.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery()->getResult();
        $actionEntities = $this->managerRegistry->getRepository(CommercialActionEntity::class)
            ->createQueryBuilder('action')
            ->where('action.project = :project')
            ->andWhere('action.status = :status')
            ->setParameter('project', $projectEntity)
            ->setParameter('status', CommercialActionEntity::STATUS_OPEN)
            ->orderBy('action.dueOn', 'ASC')
            ->addOrderBy('action.id', 'ASC')
            ->setMaxResults(20)
            ->getQuery()->getResult();
        $appointmentEntities = $this->managerRegistry->getRepository(CommercialAppointmentEntity::class)
            ->createQueryBuilder('appointment')
            ->where('appointment.project = :project')
            ->setParameter('project', $projectEntity)
            ->orderBy('appointment.startsAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()->getResult();

        $primaryContactEntity = $projectEntity->getPrimaryContact();
        $organizationEntity = $projectEntity->getOrganization();
        $linkedPeople = [];
        foreach ($projectEntity->getPeople() as $projectPersonEntity) {
            if (!$projectPersonEntity->isCurrent()) {
                continue;
            }

            $personEntity = $projectPersonEntity->getPerson();
            $linkedPeople[$personEntity->getId()] = $this->formatPerson($personEntity);
        }

        return [
            'id' => $projectEntity->getId(),
            'title' => $projectEntity->getTitle(),
            'status' => $projectEntity->getStatus(),
            'organization' => $organizationEntity?->getName(),
            'organizationDetails' => null === $organizationEntity ? null : $this->formatOrganization($organizationEntity),
            'primaryContact' => null === $primaryContactEntity ? null : trim($primaryContactEntity->getFirstName() . ' ' . $primaryContactEntity->getLastName()),
            'primaryContactDetails' => null === $primaryContactEntity ? null : $this->formatPerson($primaryContactEntity),
            'linkedPeople' => array_values($linkedPeople),
            'recentEvents' => array_map(static fn (CommercialEventEntity $eventEntity): array => [
                'id' => $eventEntity->getId(),
                'type' => $eventEntity->getType(),
                'occurredAt' => $eventEntity->getOccurredAt()->format(DATE_ATOM),
                'content' => null === $eventEntity->getContent() ? null : mb_substr($eventEntity->getContent(), 0, 2000),
            ], $eventEntities),
            'openActions' => array_map(static fn (CommercialActionEntity $actionEntity): array => [
                'id' => $actionEntity->getId(),
                'title' => $actionEntity->getTitle(),
                'dueOn' => $actionEntity->getDueOn()->format('Y-m-d'),
                'dueTime' => $actionEntity->getDueTime(),
                'revision' => hash('sha256', implode('|', [$actionEntity->getTitle(), $actionEntity->getDueOn()->format('Y-m-d'), $actionEntity->getDueTime() ?? '', $actionEntity->getStatus(), $actionEntity->getDetails() ?? ''])),
            ], $actionEntities),
            'appointments' => array_map(static fn (CommercialAppointmentEntity $appointmentEntity): array => [
                'id' => $appointmentEntity->getId(),
                'title' => $appointmentEntity->getTitle(),
                'startsAt' => $appointmentEntity->getStartsAt()->format(DATE_ATOM),
                'endsAt' => $appointmentEntity->getEndsAt()->format(DATE_ATOM),
                'location' => $appointmentEntity->getLocation(),
                'status' => $appointmentEntity->getStatus(),
                'summary' => null === $appointmentEntity->getSummary() ? null : mb_substr($appointmentEntity->getSummary(), 0, 2000),
                'revision' => $appointmentEntity->getRevision(),
                'syncWarning' => null !== $appointmentEntity->getSyncError(),
            ], $appointmentEntities),
            'url' => $this->urlGenerator->generate('commercial_project_show', ['id' => $projectId], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
    }

    /** @return array<string, mixed> */
    private function formatOrganization(OrganizationEntity $organizationEntity): array
    {
        return [
            'id' => $organizationEntity->getId(),
            'name' => $organizationEntity->getName(),
            'website' => $organizationEntity->getWebsiteUrl(),
            'emails' => $this->formatEmails($organizationEntity->getContactDetails()),
            'phones' => $this->formatPhones($organizationEntity->getContactDetails()),
            'addresses' => $this->formatAddresses($organizationEntity->getContactDetails()),
        ];
    }

    /** @return array<string, mixed> */
    private function formatPerson(PersonEntity $personEntity): array
    {
        return [
            'id' => $personEntity->getId(),
            'name' => trim($personEntity->getFirstName() . ' ' . $personEntity->getLastName()),
            'firstName' => $personEntity->getFirstName(),
            'lastName' => $personEntity->getLastName(),
            'role' => $personEntity->getRole(),
            'organization' => $personEntity->getOrganization()?->getName(),
            'emails' => $this->formatEmails($personEntity->getContactDetails()),
            'phones' => $this->formatPhones($personEntity->getContactDetails()),
            'addresses' => $this->formatAddresses($personEntity->getContactDetails()),
        ];
    }

    /** @return list<array{label: ?string, type: string, address: string}> */
    private function formatEmails(?ContactDetailsEntity $contactDetailsEntity): array
    {
        if (null === $contactDetailsEntity) {
            return [];
        }

        $emails = [];
        foreach ($contactDetailsEntity->getEmailContactLinks() as $emailContactLinkEntity) {
            if (!$emailContactLinkEntity->isActive() || null === $emailContactLinkEntity->getEmailAddress()) {
                continue;
            }

            $emails[] = [
                'label' => $emailContactLinkEntity->getLabel(),
                'type' => $emailContactLinkEntity->getType()->value,
                'address' => $emailContactLinkEntity->getEmailAddress(),
            ];
        }

        return $emails;
    }

    /** @return list<array{label: ?string, type: string, number: string}> */
    private function formatPhones(?ContactDetailsEntity $contactDetailsEntity): array
    {
        if (null === $contactDetailsEntity) {
            return [];
        }

        $phones = [];
        foreach ($contactDetailsEntity->getPhoneContactLinks() as $phoneContactLinkEntity) {
            if (!$phoneContactLinkEntity->isActive() || null === $phoneContactLinkEntity->getPhoneNumber()) {
                continue;
            }

            $phones[] = [
                'label' => $phoneContactLinkEntity->getLabel(),
                'type' => $phoneContactLinkEntity->getType()->value,
                'number' => $phoneContactLinkEntity->getPhoneNumber(),
            ];
        }

        return $phones;
    }

    /** @return list<array{label: ?string, type: string, address: ?string, municipality: ?string, postalCode: ?string}> */
    private function formatAddresses(?ContactDetailsEntity $contactDetailsEntity): array
    {
        if (null === $contactDetailsEntity) {
            return [];
        }

        $addresses = [];
        foreach ($contactDetailsEntity->getAddressContacts() as $addressContactEntity) {
            if (!$addressContactEntity->isActive()) {
                continue;
            }

            $municipalityEntity = $addressContactEntity->getMunicipality();
            $addresses[] = [
                'label' => $addressContactEntity->getLabel(),
                'type' => $addressContactEntity->getType()->value,
                'address' => $addressContactEntity->getStreetAddress(),
                'municipality' => $municipalityEntity?->getName(),
                'postalCode' => $municipalityEntity?->getPostalCode(),
            ];
        }

        return $addresses;
    }
}
