<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use App\Application\Form\MailingAudienceType;
use App\Application\Form\Model\MailingAudienceFormModel;
use App\Application\Mailing\GetMailingCampaign;
use App\Application\Mailing\NewsletterAudienceResolution;
use App\Application\Mailing\NewsletterAudienceResolverInterface;
use App\Application\Mailing\UpdateMailingCampaignAudience;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterAudienceRadiusOrigin;
use App\Domain\Model\ValueObject\InseeCode;
use App\Domain\Repository\MunicipalityRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\Map\Bridge\Leaflet\LeafletOptions;
use Symfony\UX\Map\Circle;
use Symfony\UX\Map\Map;
use Symfony\UX\Map\Marker;
use Symfony\UX\Map\Point;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

#[AsLiveComponent(
    name: 'MailingAudience',
    template: 'components/MailingAudience.html.twig',
)]
final class MailingAudience
{
    use ComponentToolsTrait;
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public string $campaignUuid = '';

    #[LiveProp]
    public bool $saved = false;

    #[LiveProp]
    public string $returnTo = '';

    #[LiveProp]
    public bool $locked = false;

    private ?MailingCampaign $mailingCampaign = null;

    private ?NewsletterAudienceResolution $audienceResolution = null;

    private bool $audienceResolutionLoaded = false;

    private ?string $audienceResolutionError = null;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly GetMailingCampaign $getMailingCampaignQuery,
        private readonly NewsletterAudienceResolverInterface $newsletterAudienceResolver,
        private readonly UpdateMailingCampaignAudience $updateMailingCampaignAudience,
        private readonly MunicipalityRepositoryInterface $municipalityRepository,
        #[Autowire('%app.mailing.home_latitude%')]
        private readonly string $homeLatitude,
        #[Autowire('%app.mailing.home_longitude%')]
        private readonly string $homeLongitude,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[LiveAction]
    public function save(): void
    {
        if ($this->locked || !$this->resolveMailingCampaign()->isEditable()) {
            return;
        }

        $this->submitForm();
        $formModel = $this->getForm()->getData();

        if (!$formModel instanceof MailingAudienceFormModel) {
            throw new InvalidArgumentException('Mailing audience form data is invalid.');
        }

        try {
            $audienceFilter = $this->buildAudienceFilter($formModel);
        } catch (InvalidArgumentException) {
            $this->getForm()->addError(new FormError('mailing.audience.validation.invalid_configuration'));

            return;
        }

        ($this->updateMailingCampaignAudience)($this->resolveMailingCampaign(), $audienceFilter);

        $this->saved = true;
        $this->audienceResolution = null;
        $this->audienceResolutionLoaded = false;
        $this->dispatchBrowserEvent('mailing:audience-saved', [
            'url' => $this->resolveReturnTo(),
        ]);
        $this->resetForm();
    }

    public function getAudienceResolution(): ?NewsletterAudienceResolution
    {
        if ($this->audienceResolutionLoaded) {
            return $this->audienceResolution;
        }

        $this->audienceResolutionLoaded = true;
        $this->audienceResolutionError = null;
        $formModel = $this->getForm()->getData();

        if (!$formModel instanceof MailingAudienceFormModel) {
            return null;
        }

        try {
            $this->audienceResolution = $this->newsletterAudienceResolver->resolve(
                $this->buildAudienceFilter($formModel),
                10,
            );
        } catch (InvalidArgumentException) {
            $this->audienceResolutionError = 'mailing.audience.result.invalid_filter';
        }

        return $this->audienceResolution;
    }

    public function getAudienceResolutionError(): ?string
    {
        $this->getAudienceResolution();

        return $this->audienceResolutionError;
    }

    public function isMunicipalityRadiusOrigin(): bool
    {
        return NewsletterAudienceRadiusOrigin::MUNICIPALITY->value === $this->radiusOriginValue();
    }

    public function isCustomRadiusOrigin(): bool
    {
        return NewsletterAudienceRadiusOrigin::CUSTOM->value === $this->radiusOriginValue();
    }

    public function isAdministrativeLocationModeActive(): bool
    {
        return [] !== $this->arrayValues('municipalityInseeCodes')
            || [] !== $this->arrayValues('departmentCodes')
            || [] !== $this->arrayValues('regionCodes');
    }

    public function isRadiusModeActive(): bool
    {
        return null !== $this->radiusOriginValue();
    }

    /**
     * @return FormInterface<MailingAudienceFormModel>
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(
            MailingAudienceType::class,
            MailingAudienceFormModel::fromAudienceFilter(
                $this->resolveMailingCampaign()->getAudienceFilter(),
            ),
            [
                'locked' => $this->locked || !$this->resolveMailingCampaign()->isEditable(),
            ],
        );
    }

    #[ExposeInTemplate(name: 'audienceMap')]
    public function getAudienceMap(): Map
    {
        $defaultPoint = $this->homePoint();
        $previewPoint = $this->radiusPreviewPoint() ?? $defaultPoint;

        if (!$previewPoint instanceof Point) {
            throw new InvalidArgumentException('Newsletter audience map requires a default point.');
        }

        $map = (new Map())
            ->center($previewPoint)
            ->zoom($this->isRadiusModeActive() ? 10.0 : 11.5)
            ->minZoom(5.0)
            ->maxZoom(17.0)
            ->options(new LeafletOptions())
            ->addMarker(new Marker(
                position: $previewPoint,
                title: $this->radiusPreviewTitle(),
                extra: [
                    'origin' => $this->radiusOriginValue() ?? NewsletterAudienceRadiusOrigin::HOME->value,
                ],
            ));

        $radiusKilometers = $this->radiusKilometersValue();

        if ($this->isRadiusModeActive() && null !== $radiusKilometers && 0 < $radiusKilometers) {
            $map->addCircle(new Circle(
                center: $previewPoint,
                radius: $radiusKilometers * 1000,
            ));
        }

        return $map;
    }

    private function resolveMailingCampaign(): MailingCampaign
    {
        if ($this->mailingCampaign instanceof MailingCampaign) {
            return $this->mailingCampaign;
        }

        if (!Uuid::isValid($this->campaignUuid)) {
            throw new NotFoundHttpException();
        }

        $mailingCampaign = ($this->getMailingCampaignQuery)(Uuid::fromString($this->campaignUuid));

        if (!$mailingCampaign instanceof MailingCampaign) {
            throw new NotFoundHttpException();
        }

        return $this->mailingCampaign = $mailingCampaign;
    }

    private function buildAudienceFilter(MailingAudienceFormModel $formModel): NewsletterAudienceFilter
    {
        return $formModel->toAudienceFilter();
    }

    private function radiusPreviewPoint(): ?Point
    {
        $radiusOrigin = $this->radiusOriginValue();

        if (NewsletterAudienceRadiusOrigin::CUSTOM->value === $radiusOrigin) {
            $latitude = $this->customLatitudeValue();
            $longitude = $this->customLongitudeValue();

            return null !== $latitude && null !== $longitude ? new Point($latitude, $longitude) : null;
        }

        if (NewsletterAudienceRadiusOrigin::MUNICIPALITY->value === $radiusOrigin) {
            $municipalityInseeCode = $this->radiusOriginMunicipalityInseeCodeValue();

            if (null === $municipalityInseeCode) {
                return null;
            }

            $municipality = $this->municipalityRepository->findByInseeCode(new InseeCode($municipalityInseeCode));

            if (null === $municipality || null === $municipality->getCenterLatitude() || null === $municipality->getCenterLongitude()) {
                return null;
            }

            return new Point($municipality->getCenterLatitude(), $municipality->getCenterLongitude());
        }

        return NewsletterAudienceRadiusOrigin::HOME->value === $radiusOrigin || null === $radiusOrigin
            ? $this->homePoint()
            : null;
    }

    private function radiusPreviewTitle(): string
    {
        return match ($this->radiusOriginValue()) {
            NewsletterAudienceRadiusOrigin::CUSTOM->value => 'Point personnalisé',
            NewsletterAudienceRadiusOrigin::MUNICIPALITY->value => 'Commune de départ',
            default => 'Jardin Sonore',
        };
    }

    private function resolveReturnTo(): string
    {
        $returnTo = trim($this->returnTo);

        if ('' !== $returnTo && str_starts_with($returnTo, '/')) {
            return $returnTo;
        }

        return $this->urlGenerator->generate('mailing_content', [
            'uuid' => $this->resolveMailingCampaign()->getUuid(),
        ]);
    }

    private function radiusOriginValue(): ?string
    {
        $radiusOrigin = $this->currentFormModel()->radiusOrigin;

        if ($radiusOrigin instanceof NewsletterAudienceRadiusOrigin) {
            return $radiusOrigin->value;
        }

        return $this->stringValue($radiusOrigin);
    }

    private function radiusKilometersValue(): ?float
    {
        return $this->floatValue($this->currentFormModel()->radiusKilometers);
    }

    private function radiusOriginMunicipalityInseeCodeValue(): ?string
    {
        return $this->stringValue($this->currentFormModel()->radiusOriginMunicipalityInseeCode);
    }

    private function customLatitudeValue(): ?float
    {
        return $this->floatValue($this->currentFormModel()->radiusOriginCustomLatitude);
    }

    private function customLongitudeValue(): ?float
    {
        return $this->floatValue($this->currentFormModel()->radiusOriginCustomLongitude);
    }

    /**
     * @return list<string>
     */
    private function arrayValues(string $key): array
    {
        $values = match ($key) {
            'municipalityInseeCodes' => $this->currentFormModel()->municipalityInseeCodes,
            'departmentCodes' => $this->currentFormModel()->departmentCodes,
            'regionCodes' => $this->currentFormModel()->regionCodes,
            default => null,
        };

        if (!is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $value): ?string => $this->stringValue($value), $values),
            static fn (?string $value): bool => null !== $value,
        ));
    }

    private function currentFormModel(): MailingAudienceFormModel
    {
        $formModel = $this->getForm()->getData();

        if ($formModel instanceof MailingAudienceFormModel) {
            return $formModel;
        }

        return MailingAudienceFormModel::fromAudienceFilter(
            $this->resolveMailingCampaign()->getAudienceFilter(),
        );
    }

    private function homePoint(): ?Point
    {
        if (!is_numeric($this->homeLatitude) || !is_numeric($this->homeLongitude)) {
            return null;
        }

        return new Point((float) $this->homeLatitude, (float) $this->homeLongitude);
    }

    private function floatValue(mixed $value): ?float
    {
        if (null === $value || '' === trim((string) $value) || !is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function stringValue(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
