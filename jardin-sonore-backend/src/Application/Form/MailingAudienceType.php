<?php

declare(strict_types=1);

namespace App\Application\Form;

use App\Application\Form\ChoiceLoader\MunicipalityInseeCodeChoiceLoader;
use App\Application\Form\Model\MailingAudienceGeographicMode;
use App\Application\Form\Model\MailingAudienceFormModel;
use App\Application\Mailing\NewsletterAudienceOptionsQueryInterface;
use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ArrayChoiceList;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @extends AbstractType<MailingAudienceFormModel>
 */
final class MailingAudienceType extends AbstractType
{
    public function __construct(
        private readonly NewsletterAudienceOptionsQueryInterface $newsletterAudienceOptionsQuery,
        private readonly MunicipalityInseeCodeChoiceLoader $municipalityInseeCodeChoiceLoader,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param FormBuilderInterface<MailingAudienceFormModel|null> $builder
     * @param array<string, mixed>                                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $locked = true === $options['locked'];
        $formModel = $builder->getData();
        $selectedMunicipalityChoices = $this->selectedMunicipalityChoices(
            $formModel instanceof MailingAudienceFormModel ? $formModel->municipalityInseeCodes : [],
        );
        $expandedMultipleOptions = [
            'expanded' => true,
            'multiple' => true,
            'required' => false,
            'choice_translation_domain' => 'backoffice',
        ];
        $multipleOptions = [
            'multiple' => true,
            'required' => false,
            'autocomplete' => true,
            'max_results' => 50,
        ];
        $municipalityAutocompleteOptions = [
            'autocomplete' => true,
            'autocomplete_url' => $this->urlGenerator->generate('mailing_audience_municipalities_autocomplete'),
            'choice_label' => $this->municipalityChoiceLabel(...),
            'min_characters' => 2,
            'max_results' => 50,
            'preload' => false,
        ];

        $builder
            ->add('geographicMode', ChoiceType::class, [
                'label' => 'mailing.audience.form.geographic_mode',
                'help' => 'mailing.audience.form.geographic_mode_help',
                'disabled' => $locked,
                'expanded' => true,
                'choices' => [
                    'mailing.audience.form.geographic_mode_municipalities' => MailingAudienceGeographicMode::MUNICIPALITIES,
                    'mailing.audience.form.geographic_mode_home_radius' => MailingAudienceGeographicMode::HOME_RADIUS,
                    'mailing.audience.form.geographic_mode_custom_radius' => MailingAudienceGeographicMode::CUSTOM_RADIUS,
                ],
            ])
            ->add('organizationTypes', ChoiceType::class, [
                ...$expandedMultipleOptions,
                'label' => 'mailing.audience.form.organization_types',
                'help' => 'mailing.audience.form.organization_types_help',
                'disabled' => $locked,
                'choices' => $this->organizationTypeChoices(),
            ])
            ->add('organizationSectors', ChoiceType::class, [
                ...$expandedMultipleOptions,
                'label' => 'mailing.audience.form.organization_sectors',
                'disabled' => $locked,
                'choices' => $this->organizationSectorChoices(),
            ])
            ->add('customerStatuses', ChoiceType::class, [
                ...$expandedMultipleOptions,
                'label' => 'mailing.audience.form.customer_statuses',
                'disabled' => $locked,
                'choices' => $this->customerStatusChoices(),
            ])
            ->add('tagUuids', ChoiceType::class, [
                ...$multipleOptions,
                'label' => 'mailing.audience.form.tags',
                'disabled' => $locked,
                'choices' => $this->newsletterAudienceOptionsQuery->getTagChoices(),
            ])
            ->add('regionCodes', ChoiceType::class, [
                ...$multipleOptions,
                'label' => 'mailing.audience.form.regions',
                'help' => 'mailing.audience.form.regions_help',
                'disabled' => $locked,
                'choices' => $this->newsletterAudienceOptionsQuery->getRegionChoices(),
            ])
            ->add('departmentCodes', ChoiceType::class, [
                ...$multipleOptions,
                'label' => 'mailing.audience.form.departments',
                'help' => 'mailing.audience.form.departments_help',
                'disabled' => $locked,
                'choices' => $this->newsletterAudienceOptionsQuery->getDepartmentChoices(),
            ])
            ->add('municipalityInseeCodes', ChoiceType::class, [
                ...$municipalityAutocompleteOptions,
                'label' => 'mailing.audience.form.municipalities',
                'help' => 'mailing.audience.form.municipalities_help',
                'choice_loader' => $this->createMunicipalityChoiceLoader($selectedMunicipalityChoices),
                'multiple' => true,
                'required' => false,
                'disabled' => $locked,
            ])
            ->add('radiusKilometers', NumberType::class, [
                'label' => 'mailing.audience.form.radius',
                'help' => 'mailing.audience.form.radius_help',
                'required' => true,
                'disabled' => $locked,
                'empty_data' => '1',
                'scale' => 0,
                'html5' => true,
                'attr' => [
                    'min' => 1,
                    'step' => 1,
                ],
            ])
            ->add('radiusOriginMunicipalityInseeCode', ChoiceType::class, [
                ...$municipalityAutocompleteOptions,
                'label' => 'mailing.audience.form.radius_origin_municipality',
                'help' => 'mailing.audience.form.radius_origin_municipality_help',
                'choice_value' => static fn (?string $inseeCode): string => $inseeCode ?? '',
                'choice_loader' => $this->createMunicipalityChoiceLoader([]),
                'required' => false,
                'disabled' => true,
                'placeholder' => 'mailing.audience.form.radius_origin_municipality_placeholder',
            ])
            ->add('radiusOriginCustomLatitude', NumberType::class, [
                'required' => false,
                'disabled' => $locked,
                'html5' => false,
            ])
            ->add('radiusOriginCustomLongitude', NumberType::class, [
                'required' => false,
                'disabled' => $locked,
                'html5' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'mailing.audience.form.save',
                'disabled' => $locked,
                'attr' => [
                    'class' => 'internal-button',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => MailingAudienceFormModel::class,
            'locked' => false,
            'translation_domain' => 'mailing',
        ]);
    }

    /**
     * @return array<string, OrganizationType>
     */
    private function organizationTypeChoices(): array
    {
        return [
            'address_book.organization_type.creche' => OrganizationType::CRECHE,
            'address_book.organization_type.mairie' => OrganizationType::MAIRIE,
            'address_book.organization_type.ram' => OrganizationType::RAM,
            'address_book.organization_type.mam' => OrganizationType::MAM,
            'address_book.organization_type.mediatheque' => OrganizationType::MEDIATHEQUE,
            'address_book.organization_type.centre' => OrganizationType::CENTRE,
            'address_book.organization_type.garderie' => OrganizationType::GARDERIE,
            'address_book.organization_type.test' => OrganizationType::TEST,
        ];
    }

    /**
     * @return array<string, OrganizationSector>
     */
    private function organizationSectorChoices(): array
    {
        return [
            'address_book.organization_sector.association' => OrganizationSector::ASSOCIATION,
            'address_book.organization_sector.public' => OrganizationSector::PUBLIC,
            'address_book.organization_sector.private' => OrganizationSector::PRIVATE,
        ];
    }

    /**
     * @return array<string, CustomerStatus>
     */
    private function customerStatusChoices(): array
    {
        return [
            'address_book.customer_status.customer' => CustomerStatus::CUSTOMER,
            'address_book.customer_status.prospect' => CustomerStatus::PROSPECT,
            'address_book.customer_status.former_customer' => CustomerStatus::FORMER_CUSTOMER,
        ];
    }

    private function municipalityChoiceLabel(string $inseeCode): string
    {
        return $this->newsletterAudienceOptionsQuery->getMunicipalityLabelsByInseeCodes([$inseeCode])[$inseeCode] ?? $inseeCode;
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
}
