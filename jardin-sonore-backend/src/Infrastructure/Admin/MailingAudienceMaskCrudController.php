<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Application\Form\ChoiceLoader\MunicipalityInseeCodeChoiceLoader;
use App\Application\Mailing\NewsletterAudienceOptionsQueryInterface;
use App\Infrastructure\Doctrine\Entity\MailingAudienceMaskEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @extends AbstractCrudController<MailingAudienceMaskEntity>
 */
final class MailingAudienceMaskCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MunicipalityInseeCodeChoiceLoader $municipalityInseeCodeChoiceLoader,
        private readonly NewsletterAudienceOptionsQueryInterface $newsletterAudienceOptionsQuery,
        private readonly AdminContextProvider $adminContextProvider,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return MailingAudienceMaskEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('address_book.mailing_audience_mask.singular')
            ->setEntityLabelInPlural('address_book.mailing_audience_mask.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.mailing_audience_mask.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.mailing_audience_mask.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.mailing_audience_mask.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.mailing_audience_mask.page.detail')
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->setSearchFields(['name'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        $currentMailingAudienceMaskEntity = $this->currentMailingAudienceMaskEntity();
        $municipalityAutocompleteUrl = $this->urlGenerator->generate('admin_mailing_audience_mask_municipalities_autocomplete') . '?';
        $selectedMunicipalityInseeCodes = $this->selectedMunicipalityChoices(
            $currentMailingAudienceMaskEntity?->getMaterializedMunicipalityInseeCodes() ?? [],
        );

        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield TextField::new('name', 'admin.field.name');
        yield IntegerField::new('materializedMunicipalityCountForAdmin', 'admin.field.materialized_municipality_count')
            ->hideOnForm();
        yield TextField::new('municipalityLabelsForAdmin', 'admin.field.municipality_labels')
            ->formatValue(fn (mixed $value, ?MailingAudienceMaskEntity $mailingAudienceMaskEntity): string => $this->municipalityLabelsBadges($mailingAudienceMaskEntity?->getMaterializedMunicipalityInseeCodes() ?? []))
            ->renderAsHtml()
            ->hideOnForm();
        yield ChoiceField::new('municipalityInseeCodesForAdmin', 'admin.field.municipalities')
            ->setHelp('admin.help.mailing_audience_mask_municipalities')
            ->allowMultipleChoices()
            ->autocomplete()
            ->setFormTypeOption('attr.data-ea-autocomplete-endpoint-url', $municipalityAutocompleteUrl)
            ->setFormTypeOption('choice_loader', $this->createMunicipalityChoiceLoader($selectedMunicipalityInseeCodes))
            ->setFormTypeOption('autocomplete_url', $municipalityAutocompleteUrl)
            ->setFormTypeOption('choice_label', fn (?string $inseeCode): string => $this->municipalityChoiceLabel($inseeCode))
            ->setFormTypeOption('choice_value', static fn (?string $inseeCode): string => $inseeCode ?? '')
            ->setFormTypeOption('choice_translation_domain', false)
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('min_characters', 2)
            ->setFormTypeOption('max_results', 50)
            ->setFormTypeOption('preload', false)
            ->onlyOnForms();
        yield DateTimeField::new('updatedAt', 'admin.field.updated_at')->hideOnForm();
        yield DateTimeField::new('createdAt', 'admin.field.created_at')->onlyOnDetail();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof MailingAudienceMaskEntity) {
            parent::persistEntity($entityManager, $entityInstance);

            return;
        }

        $this->synchronizeAudienceMaskEntity($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof MailingAudienceMaskEntity) {
            parent::updateEntity($entityManager, $entityInstance);

            return;
        }

        $this->synchronizeAudienceMaskEntity($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function municipalityChoiceLabel(?string $inseeCode): string
    {
        if (null === $inseeCode || '' === trim($inseeCode)) {
            return '';
        }

        return $this->newsletterAudienceOptionsQuery->getMunicipalityLabelsByInseeCodes([$inseeCode])[$inseeCode] ?? $inseeCode;
    }

    private function municipalityLabelsSummary(mixed $inseeCodes): string
    {
        if (!is_array($inseeCodes) || [] === $inseeCodes) {
            return '';
        }

        $normalizedInseeCodes = $this->normalizeStringList($inseeCodes);
        $labelsByInseeCode = $this->newsletterAudienceOptionsQuery->getMunicipalityLabelsByInseeCodes($normalizedInseeCodes);
        $labels = [];

        foreach ($normalizedInseeCodes as $inseeCode) {
            $labels[] = $labelsByInseeCode[$inseeCode] ?? $inseeCode;
        }

        return implode(', ', $labels);
    }

    private function municipalityLabelsBadges(mixed $inseeCodes): string
    {
        $summary = $this->municipalityLabelsSummary($inseeCodes);

        if ('' === $summary) {
            return '';
        }

        $labels = explode(', ', $summary);
        $badges = array_map(
            static fn (string $label): string => sprintf(
                '<span class="badge badge-secondary" style="margin: 0 .35rem .35rem 0;">%s</span>',
                htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ),
            $labels,
        );

        return implode('', $badges);
    }

    private function synchronizeAudienceMaskEntity(MailingAudienceMaskEntity $mailingAudienceMaskEntity): void
    {
        $municipalityInseeCodes = $this->normalizeStringList($mailingAudienceMaskEntity->getMaterializedMunicipalityInseeCodes());
        $audienceFilter = $mailingAudienceMaskEntity->getAudienceFilter();

        $audienceFilter['organizationTypes'] = $this->normalizeStringList($audienceFilter['organizationTypes'] ?? []);
        $audienceFilter['organizationSectors'] = $this->normalizeStringList($audienceFilter['organizationSectors'] ?? []);
        $audienceFilter['customerStatuses'] = $this->normalizeStringList($audienceFilter['customerStatuses'] ?? []);
        $audienceFilter['organizationUuids'] = $this->normalizeStringList($audienceFilter['organizationUuids'] ?? []);
        $audienceFilter['tagUuids'] = $this->normalizeStringList($audienceFilter['tagUuids'] ?? []);
        $audienceFilter['regionCodes'] = [];
        $audienceFilter['departmentCodes'] = [];
        $audienceFilter['municipalityInseeCodes'] = $municipalityInseeCodes;
        $audienceFilter['radiusKilometers'] = null;
        $audienceFilter['radiusOrigin'] = null;
        $audienceFilter['radiusOriginMunicipalityInseeCode'] = null;
        $audienceFilter['radiusOriginCustomLatitude'] = null;
        $audienceFilter['radiusOriginCustomLongitude'] = null;

        $mailingAudienceMaskEntity
            ->setMaterializedMunicipalityInseeCodes($municipalityInseeCodes)
            ->setAudienceFilter($audienceFilter)
            ->setUpdatedAt(new DateTimeImmutable());
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $normalizedValues = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $normalizedValue = trim($value);

            if ('' === $normalizedValue || in_array($normalizedValue, $normalizedValues, true)) {
                continue;
            }

            $normalizedValues[] = $normalizedValue;
        }

        sort($normalizedValues);

        return $normalizedValues;
    }

    /**
     * @param list<string> $selectedInseeCodes
     */
    private function createMunicipalityChoiceLoader(array $selectedInseeCodes): ChoiceLoaderInterface
    {
        $newsletterAudienceOptionsQuery = $this->newsletterAudienceOptionsQuery;
        $municipalityInseeCodeChoiceLoader = $this->municipalityInseeCodeChoiceLoader;

        return new class($selectedInseeCodes, $newsletterAudienceOptionsQuery, $municipalityInseeCodeChoiceLoader) implements ChoiceLoaderInterface {
            /**
             * @param list<string> $selectedInseeCodes
             */
            public function __construct(
                private readonly array $selectedInseeCodes,
                private readonly NewsletterAudienceOptionsQueryInterface $newsletterAudienceOptionsQuery,
                private readonly MunicipalityInseeCodeChoiceLoader $municipalityInseeCodeChoiceLoader,
            ) {
            }

            public function loadChoiceList(?callable $value = null): ChoiceListInterface
            {
                return new ArrayChoiceList(
                    $this->newsletterAudienceOptionsQuery->getExistingMunicipalityInseeCodes($this->selectedInseeCodes),
                    $value,
                );
            }

            public function loadChoicesForValues(array $values, ?callable $value = null): array
            {
                return $this->municipalityInseeCodeChoiceLoader->loadChoicesForValues($values, $value);
            }

            public function loadValuesForChoices(array $choices, ?callable $value = null): array
            {
                return $this->municipalityInseeCodeChoiceLoader->loadValuesForChoices($choices, $value);
            }
        };
    }

    /**
     * @param list<string> $inseeCodes
     *
     * @return list<string>
     */
    private function selectedMunicipalityChoices(array $inseeCodes): array
    {
        return $this->newsletterAudienceOptionsQuery->getExistingMunicipalityInseeCodes($inseeCodes);
    }

    private function currentMailingAudienceMaskEntity(): ?MailingAudienceMaskEntity
    {
        $adminContext = $this->adminContextProvider->getContext();

        if (!$adminContext instanceof AdminContextInterface) {
            return null;
        }

        $entityInstance = $adminContext->getEntity()->getInstance();

        return $entityInstance instanceof MailingAudienceMaskEntity ? $entityInstance : null;
    }
}
