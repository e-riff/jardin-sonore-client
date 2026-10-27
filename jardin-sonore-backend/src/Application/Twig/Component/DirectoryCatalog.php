<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use App\Application\Form\DirectoryContactDetailsFormType;
use App\Application\Form\DirectoryOrganizationDetailsFormType;
use App\Application\Form\DirectoryOrganizationFormType;
use App\Application\Form\DirectoryPersonDetailsFormType;
use App\Application\Form\DirectoryPersonFormType;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'DirectoryCatalog', template: 'components/DirectoryCatalog.html.twig')]
final class DirectoryCatalog
{
    use DefaultActionTrait;

    private ?int $totalCache = null;

    /** @var list<OrganizationEntity|PersonEntity>|null */
    private ?array $entriesCache = null;

    private const int PER_PAGE = 10;

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $section = 'organizations';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $query = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $activeFilter = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $typeFilter = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $sectorFilter = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $departmentFilter = '';

    #[LiveProp(writable: true, url: true, onUpdated: 'resetPage')]
    public string $letter = '';

    #[LiveProp(writable: true, url: true)]
    public int $page = 1;

    #[LiveProp(writable: true)]
    public int $pageJump = 1;

    #[LiveProp(writable: true, url: true)]
    public ?int $selectedId = null;

    private string $dialogMode = '';

    private ?int $dialogEntityId = null;

    private ?int $dialogOrganizationId = null;

    private ?FormView $dialogFormCache = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /** @return list<OrganizationEntity|PersonEntity> */
    public function getEntries(): array
    {
        if (null !== $this->entriesCache) {
            return $this->entriesCache;
        }
        $queryBuilder = $this->createQueryBuilder();
        $this->applySearch($queryBuilder);
        $this->applyFilters($queryBuilder);

        $nameField = 'people' === $this->section ? 'lastName' : 'name';
        $queryBuilder->orderBy($this->normalizedNameExpression('entry', $nameField), 'ASC');
        if ('people' === $this->section) {
            $queryBuilder->addOrderBy('entry.firstName', 'ASC');
        }
        $queryBuilder->addOrderBy('entry.id', 'ASC')
            ->setFirstResult((max(1, $this->page) - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $entries = iterator_to_array((new Paginator($queryBuilder, fetchJoinCollection: true))->getIterator());
        if ($this->page > $this->getPageCount()) {
            $this->page = $this->getPageCount();

            return $this->getEntries();
        }

        return $this->entriesCache = $entries;
    }

    public function getSelectedEntry(): OrganizationEntity|PersonEntity|null
    {
        if (null === $this->selectedId) {
            $entries = $this->getEntries();
            if ([] === $entries) {
                return null;
            }
            $class = 'people' === $this->section ? PersonEntity::class : OrganizationEntity::class;
            $id = $entries[0]->getId();

            return null === $id ? null : $this->loadEntry($class, $id);
        }
        $class = 'people' === $this->section ? PersonEntity::class : OrganizationEntity::class;

        return $this->loadEntry($class, $this->selectedId);
    }

    /** @param class-string<OrganizationEntity|PersonEntity> $class */
    private function loadEntry(string $class, int $id): OrganizationEntity|PersonEntity|null
    {
        $alias = 'entry';
        $queryBuilder = $this->entityManager->getRepository($class)->createQueryBuilder($alias)
            ->leftJoin($alias . '.contactDetails', 'contactDetails')->addSelect('contactDetails')
            ->leftJoin('contactDetails.emailContactLinks', 'emailLink')->addSelect('emailLink')
            ->leftJoin('emailLink.emailContact', 'emailContact')->addSelect('emailContact')
            ->leftJoin('contactDetails.phoneContactLinks', 'phoneLink')->addSelect('phoneLink')
            ->leftJoin('phoneLink.phoneContact', 'phoneContact')->addSelect('phoneContact')
            ->leftJoin('contactDetails.addressContacts', 'address')->addSelect('address')
            ->leftJoin('address.municipality', 'municipality')->addSelect('municipality')
            ->leftJoin('municipality.department', 'department')->addSelect('department')
            ->andWhere($alias . '.id = :id')->setParameter('id', $id);

        if (OrganizationEntity::class === $class) {
            $queryBuilder->leftJoin('entry.people', 'person')->addSelect('person')
                ->leftJoin('person.contactDetails', 'personDetails')->addSelect('personDetails')
                ->leftJoin('personDetails.emailContactLinks', 'personEmailLink')->addSelect('personEmailLink')
                ->leftJoin('personEmailLink.emailContact', 'personEmail')->addSelect('personEmail')
                ->leftJoin('personDetails.phoneContactLinks', 'personPhoneLink')->addSelect('personPhoneLink')
                ->leftJoin('personPhoneLink.phoneContact', 'personPhone')->addSelect('personPhone');
        } else {
            $queryBuilder->leftJoin('entry.organization', 'organization')->addSelect('organization');
        }

        $entry = $queryBuilder->getQuery()->getOneOrNullResult();
        if (!$entry instanceof OrganizationEntity && !$entry instanceof PersonEntity) {
            return null;
        }

        return $entry;
    }

    public function getTotal(): int
    {
        if (null !== $this->totalCache) {
            return $this->totalCache;
        }
        $queryBuilder = $this->createQueryBuilder()->select('COUNT(DISTINCT entry.id)');
        $this->applySearch($queryBuilder);
        $this->applyFilters($queryBuilder);

        return $this->totalCache = (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function getPageCount(): int
    {
        return max(1, (int) ceil($this->getTotal() / self::PER_PAGE));
    }

    /** @return list<OrganizationType> */
    public function getOrganizationTypes(): array
    {
        return OrganizationType::cases();
    }

    /** @return list<OrganizationSector> */
    public function getOrganizationSectors(): array
    {
        return OrganizationSector::cases();
    }

    /** @return list<string> */
    public function getLetters(): array
    {
        $class = 'people' === $this->section ? PersonEntity::class : OrganizationEntity::class;
        $field = 'people' === $this->section ? 'lastName' : 'name';
        $normalizedName = $this->normalizedNameExpression('letterEntry', $field);
        $rows = $this->entityManager->getRepository($class)->createQueryBuilder('letterEntry')
            ->select("UPPER(SUBSTRING({$normalizedName}, 1, 1)) AS letter")
            ->where("{$normalizedName} IS NOT NULL")
            ->andWhere("{$normalizedName} <> ''")
            ->distinct()
            ->orderBy('letter', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_map(static fn (array $row): string => (string) ($row['letter'] ?? ''), $rows)));
    }

    public function getActiveFilterCount(): int
    {
        return count(array_filter([$this->activeFilter, $this->typeFilter, $this->sectorFilter, $this->departmentFilter], static fn (string $filter): bool => '' !== trim($filter)));
    }

    #[LiveAction]
    public function filterByLetter(#[LiveArg] string $letter): void
    {
        $letter = mb_strtoupper(trim($letter));
        $this->letter = '' === $letter || 'all' === mb_strtolower($letter) ? '' : mb_substr($letter, 0, 1);
        $this->resetPage();
    }

    #[LiveAction]
    public function goToPage(): void
    {
        $this->setPage($this->pageJump);
    }

    #[LiveAction]
    public function firstPage(): void
    {
        $this->setPage(1);
    }

    #[LiveAction]
    public function lastPage(): void
    {
        $this->setPage($this->getPageCount());
    }

    #[LiveAction]
    public function createEntry(): void
    {
        if (!in_array($this->section, ['organizations', 'people'], true)) {
            return;
        }

        $this->dialogMode = 'people' === $this->section ? 'person_new' : 'organization_new';
        $this->dialogOrganizationId = null;
        $this->dialogEntityId = null;
        $this->dialogFormCache = null;
    }

    #[LiveAction]
    public function addPersonToSelectedOrganization(): void
    {
        $selectedEntry = $this->getSelectedEntry();
        if (!$selectedEntry instanceof OrganizationEntity) {
            return;
        }

        $this->dialogMode = 'person_new';
        $this->dialogOrganizationId = $selectedEntry->getId();
        $this->dialogEntityId = null;
        $this->dialogFormCache = null;
    }

    #[LiveAction]
    public function editSelectedEntry(): void
    {
        $selectedEntry = $this->getSelectedEntry();
        if ($selectedEntry instanceof OrganizationEntity) {
            $this->dialogMode = 'organization_details_edit';
            $this->dialogEntityId = $selectedEntry->getId();
            $this->dialogOrganizationId = $selectedEntry->getId();
        } elseif ($selectedEntry instanceof PersonEntity) {
            $this->dialogMode = 'person_details_edit';
            $this->dialogEntityId = $selectedEntry->getId();
            $this->dialogOrganizationId = $selectedEntry->getOrganization()?->getId();
        }
        $this->dialogFormCache = null;
    }

    #[LiveAction]
    public function editOrganizationSection(#[LiveArg] string $section): void
    {
        $selectedEntry = $this->getSelectedEntry();
        if (!in_array($section, ['details', 'contacts', 'addresses'], true) || !$selectedEntry instanceof OrganizationEntity) {
            return;
        }

        $this->dialogMode = 'organization_' . $section . '_edit';
        $this->dialogEntityId = $selectedEntry->getId();
        $this->dialogOrganizationId = $selectedEntry->getId();
        $this->dialogFormCache = null;
    }

    #[LiveAction]
    public function editPerson(#[LiveArg] int $id): void
    {
        $person = $this->entityManager->find(PersonEntity::class, $id);
        if (!$person instanceof PersonEntity) {
            return;
        }

        $this->dialogMode = 'person_edit';
        $this->dialogEntityId = $person->getId();
        $selectedEntry = $this->getSelectedEntry();
        $this->dialogOrganizationId = $selectedEntry instanceof OrganizationEntity
            ? $selectedEntry->getId()
            : $person->getOrganization()?->getId();
        $this->dialogFormCache = null;
    }

    #[LiveAction]
    public function editPersonSection(#[LiveArg] int $id, #[LiveArg] string $section): void
    {
        $person = $this->entityManager->find(PersonEntity::class, $id);
        if (!in_array($section, ['details', 'contacts', 'addresses'], true) || !$person instanceof PersonEntity) {
            return;
        }

        $this->dialogMode = 'person_' . $section . '_edit';
        $this->dialogEntityId = $person->getId();
        $selectedEntry = $this->getSelectedEntry();
        $this->dialogOrganizationId = $selectedEntry instanceof OrganizationEntity
            ? $selectedEntry->getId()
            : $person->getOrganization()?->getId();
        $this->dialogFormCache = null;
    }

    #[LiveAction]
    public function closeDialog(): void
    {
        $this->dialogMode = '';
        $this->dialogEntityId = null;
        $this->dialogOrganizationId = null;
        $this->dialogFormCache = null;
    }

    public function getDialogMode(): string
    {
        return $this->dialogMode;
    }

    public function getDialogTitleKey(): string
    {
        return match ($this->dialogMode) {
            'organization_new' => 'technical.directory.form.organization_create',
            'organization_details_edit' => 'technical.directory.form.organization_details_edit',
            'organization_contacts_edit' => 'technical.directory.form.organization_contacts_edit',
            'organization_addresses_edit' => 'technical.directory.form.organization_addresses_edit',
            'person_new' => 'technical.directory.form.person_create',
            'person_edit', 'person_details_edit' => 'technical.directory.form.person_details_edit',
            'person_contacts_edit' => 'technical.directory.form.person_contacts_edit',
            'person_addresses_edit' => 'technical.directory.form.person_addresses_edit',
            default => '',
        };
    }

    public function getDialogForm(): ?FormView
    {
        if ('' === $this->dialogMode) {
            return null;
        }
        if (null !== $this->dialogFormCache) {
            return $this->dialogFormCache;
        }

        if ('organization_new' === $this->dialogMode) {
            $organization = new OrganizationEntity();

            return $this->dialogFormCache = $this->formFactory
                ->create(DirectoryOrganizationFormType::class, $organization, [
                    'action' => $this->urlGenerator->generate('internal_directory_organization_new'),
                    'method' => 'POST',
                ])->createView();
        }

        if (str_starts_with($this->dialogMode, 'organization_')) {
            $organization = $this->entityManager->find(OrganizationEntity::class, $this->dialogEntityId);
            if (!$organization instanceof OrganizationEntity) {
                return null;
            }
            $section = match ($this->dialogMode) {
                'organization_details_edit' => 'details',
                'organization_contacts_edit' => 'contacts',
                'organization_addresses_edit' => 'addresses',
                default => null,
            };
            if (null === $section) {
                return null;
            }

            $formType = 'details' === $section ? DirectoryOrganizationDetailsFormType::class : DirectoryContactDetailsFormType::class;
            $formData = 'details' === $section ? $organization : $organization->getContactDetails();
            $formOptions = ['action' => $this->urlGenerator->generate('internal_directory_organization_section_edit', ['id' => $organization->getId(), 'section' => $section]), 'method' => 'POST'];
            if ('contacts' === $section) {
                $formOptions['groups'] = ['email', 'phone'];
            } elseif ('addresses' === $section) {
                $formOptions['groups'] = ['address'];
            }

            return $this->dialogFormCache = $this->formFactory->create($formType, $formData, $formOptions)->createView();
        }

        $isNew = 'person_new' === $this->dialogMode;
        $person = $isNew ? new PersonEntity() : $this->entityManager->find(PersonEntity::class, $this->dialogEntityId);
        if (!$person instanceof PersonEntity) {
            return null;
        }

        $section = match ($this->dialogMode) {
            'person_details_edit', 'person_edit' => 'details',
            'person_contacts_edit' => 'contacts',
            'person_addresses_edit' => 'addresses',
            default => null,
        };
        if (!$isNew && null !== $section) {
            $routeParameters = ['id' => $person->getId(), 'section' => $section];

            return $this->dialogFormCache = $this->formFactory->create(
                'details' === $section ? DirectoryPersonDetailsFormType::class : DirectoryContactDetailsFormType::class,
                'details' === $section ? $person : $person->getContactDetails(),
                [
                    'action' => $this->urlGenerator->generate('internal_directory_person_section_edit', $routeParameters),
                    'method' => 'POST',
                    ...('details' === $section ? [] : ['groups' => 'contacts' === $section ? ['email', 'phone'] : ['address']]),
                ],
            )->createView();
        }
        $organization = null !== $this->dialogOrganizationId ? $this->entityManager->find(OrganizationEntity::class, $this->dialogOrganizationId) : null;
        if ($isNew && $organization instanceof OrganizationEntity) {
            $person->setOrganization($organization);
        }
        $route = $isNew ? 'internal_directory_person_new' : 'internal_directory_person_edit';
        $routeParameters = $isNew ? [] : ['id' => $person->getId()];
        if ($organization instanceof OrganizationEntity) {
            $routeParameters[$isNew ? 'organization' : 'returnOrganization'] = $organization->getId();
        }

        return $this->dialogFormCache = $this->formFactory
            ->create(DirectoryPersonFormType::class, $person, [
                'action' => $this->urlGenerator->generate($route, $routeParameters),
                'method' => 'POST',
            ])->createView();
    }

    #[LiveAction]
    public function select(#[LiveArg] int $id): void
    {
        $this->selectedId = $id;
    }

    #[LiveAction]
    public function clearSelection(): void
    {
        $this->selectedId = null;
    }

    #[LiveAction]
    public function selectSection(#[LiveArg] string $section): void
    {
        if (!in_array($section, ['organizations', 'people'], true)) {
            return;
        }
        $this->section = $section;
        $this->resetPage();
    }

    #[LiveAction]
    public function previousPage(): void
    {
        $this->setPage($this->page - 1);
    }

    #[LiveAction]
    public function nextPage(): void
    {
        $this->setPage($this->page + 1);
    }

    public function resetPage(): void
    {
        $this->page = 1;
        $this->pageJump = 1;
        $this->selectedId = null;
        $this->invalidateResults();
    }

    private function invalidateResults(): void
    {
        $this->totalCache = null;
        $this->entriesCache = null;
    }

    private function setPage(int $page): void
    {
        $this->page = min(max(1, $page), $this->getPageCount());
        $this->pageJump = $this->page;
        $this->selectedId = null;
        $this->invalidateResults();
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $class = 'people' === $this->section ? PersonEntity::class : OrganizationEntity::class;
        $queryBuilder = $this->entityManager->getRepository($class)->createQueryBuilder('entry')
            ->leftJoin('entry.contactDetails', 'details')
            ->leftJoin('details.addressContacts', 'address')
            ->leftJoin('address.municipality', 'municipality')
            ->leftJoin('municipality.department', 'department')
            ->leftJoin('details.emailContactLinks', 'emailLink')
            ->leftJoin('emailLink.emailContact', 'emailContact')
            ->leftJoin('details.phoneContactLinks', 'phoneLink')
            ->leftJoin('phoneLink.phoneContact', 'phoneContact');
        if ('people' === $this->section) {
            $queryBuilder->leftJoin('entry.organization', 'organization');
        }

        return $queryBuilder;
    }

    private function applySearch(QueryBuilder $queryBuilder): void
    {
        $nameField = 'people' === $this->section ? 'entry.lastName' : 'entry.name';
        if ('' !== $this->letter) {
            $normalizedName = $this->normalizedNameExpression('entry', 'people' === $this->section ? 'lastName' : 'name');
            $queryBuilder->andWhere("UPPER({$normalizedName}) LIKE :letterPrefix")
                ->setParameter('letterPrefix', mb_strtoupper(mb_substr($this->letter, 0, 1)) . '%');
        }

        $searchTerms = preg_split('/\s+/', trim($this->query), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($searchTerms as $index => $searchTerm) {
            $parameter = 'search' . $index;
            $searchFields = ["LOWER({$nameField}) LIKE LOWER(:{$parameter})", "LOWER(municipality.name) LIKE LOWER(:{$parameter})", "LOWER(municipality.inseeCode) LIKE LOWER(:{$parameter})", "LOWER(municipality.postalCode) LIKE LOWER(:{$parameter})", "LOWER(department.code) LIKE LOWER(:{$parameter})", "LOWER(department.name) LIKE LOWER(:{$parameter})", "LOWER(address.address) LIKE LOWER(:{$parameter})", "LOWER(emailContact.emailAddress) LIKE LOWER(:{$parameter})", "LOWER(phoneContact.phoneNumber) LIKE LOWER(:{$parameter})"];
            if ('people' === $this->section) {
                $searchFields[] = "LOWER(entry.firstName) LIKE LOWER(:{$parameter})";
                $searchFields[] = "LOWER(entry.role) LIKE LOWER(:{$parameter})";
                $searchFields[] = "LOWER(organization.name) LIKE LOWER(:{$parameter})";
            } else {
                $searchFields[] = "LOWER(entry.websiteUrl) LIKE LOWER(:{$parameter})";
            }
            $queryBuilder->andWhere($queryBuilder->expr()->orX(...$searchFields))
                ->setParameter($parameter, '%' . addcslashes($searchTerm, '%_\\') . '%');
        }
    }

    private function normalizedNameExpression(string $alias, string $field): string
    {
        return "TRIM(LEADING ' ' FROM TRIM(LEADING '\"' FROM TRIM(LEADING ' ' FROM {$alias}.{$field})))";
    }

    private function applyFilters(QueryBuilder $queryBuilder): void
    {
        if ('enabled' === $this->activeFilter || 'disabled' === $this->activeFilter) {
            $queryBuilder->andWhere('entry.active = :active')->setParameter('active', 'enabled' === $this->activeFilter);
        }

        $organizationAlias = 'people' === $this->section ? 'organization' : 'entry';
        if (null !== $type = OrganizationType::tryFrom($this->typeFilter)) {
            $queryBuilder->andWhere("{$organizationAlias}.type = :organizationType")->setParameter('organizationType', $type);
        }
        if (null !== $sector = OrganizationSector::tryFrom($this->sectorFilter)) {
            $queryBuilder->andWhere("{$organizationAlias}.sector = :organizationSector")->setParameter('organizationSector', $sector);
        }

        $departmentSearch = trim($this->departmentFilter);
        if ('' !== $departmentSearch) {
            $queryBuilder->andWhere($queryBuilder->expr()->orX(
                'LOWER(department.code) LIKE LOWER(:departmentSearch)',
                'LOWER(department.name) LIKE LOWER(:departmentSearch)',
            ))->setParameter('departmentSearch', '%' . addcslashes($departmentSearch, '%_\\') . '%');
        }
    }
}
