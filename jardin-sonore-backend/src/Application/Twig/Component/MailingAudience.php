<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use App\Application\Form\MailingAudienceType;
use App\Application\Form\Model\MailingAudienceFormModel;
use App\Application\Mailing\NewsletterAudienceMapQueryInterface;
use App\Application\Mailing\NewsletterAudienceMunicipalityMaterializerInterface;
use App\Application\Mailing\GetMailingCampaign;
use App\Application\Mailing\NewsletterAudienceResolution;
use App\Application\Mailing\NewsletterAudienceResolverInterface;
use App\Application\Mailing\UpdateMailingCampaignAudience;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterAudienceRadiusOrigin;
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
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsLiveComponent(
    name: 'MailingAudience',
    template: 'components/MailingAudience.html.twig',
)]
final class MailingAudience
{
    use ComponentToolsTrait;
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    private const int MAP_MUNICIPALITY_SHAPE_LIMIT = 60;

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
        private readonly NewsletterAudienceMunicipalityMaterializerInterface $newsletterAudienceMunicipalityMaterializer,
        private readonly NewsletterAudienceMapQueryInterface $newsletterAudienceMapQuery,
        #[Autowire('%app.mailing.home_latitude%')]
        private readonly string $homeLatitude,
        #[Autowire('%app.mailing.home_longitude%')]
        private readonly string $homeLongitude,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
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

    public function isCustomRadiusOrigin(): bool
    {
        return $this->currentFormModel()->isCustomRadiusOrigin();
    }

    public function isMunicipalitiesMode(): bool
    {
        return $this->currentFormModel()->isMunicipalitiesMode();
    }

    public function isAdministrativeLocationModeActive(): bool
    {
        return $this->currentFormModel()->hasAdministrativeLocationCriteria();
    }

    public function isRadiusModeActive(): bool
    {
        return $this->currentFormModel()->hasSelectedRadiusOrigin();
    }

    /**
     * @return list<array{inseeCode: string, label: string, geoShape: array<string, mixed>|list<mixed>}>
     */
    #[ExposeInTemplate(name: 'audienceMapMunicipalityShapes')]
    public function getAudienceMapMunicipalityShapes(): array
    {
        if ($this->isAudienceMapMunicipalityShapesTruncated()) {
            return [];
        }

        try {
            $inseeCodes = $this->newsletterAudienceMunicipalityMaterializer->materialize(
                $this->currentFormModel()->toAudienceFilter(),
            );
        } catch (InvalidArgumentException) {
            return [];
        }

        $shapes = $this->newsletterAudienceMapQuery->findMunicipalityShapesByInseeCodes(
            $inseeCodes,
            self::MAP_MUNICIPALITY_SHAPE_LIMIT,
        );
        $serializedShapes = [];

        foreach ($shapes as $shape) {
            $serializedShapes[] = [
                'inseeCode' => $shape->inseeCode,
                'label' => $shape->label,
                'geoShape' => $shape->geoShape,
            ];
        }

        return $serializedShapes;
    }

    /**
     * @return list<array{inseeCode: string, label: string, latitude: float, longitude: float}>
     */
    #[ExposeInTemplate(name: 'audienceMapMunicipalityPoints')]
    public function getAudienceMapMunicipalityPoints(): array
    {
        if (!$this->isAudienceMapMunicipalityShapesTruncated()) {
            return [];
        }

        try {
            $inseeCodes = $this->newsletterAudienceMunicipalityMaterializer->materialize(
                $this->currentFormModel()->toAudienceFilter(),
            );
        } catch (InvalidArgumentException) {
            return [];
        }

        $points = $this->newsletterAudienceMapQuery->findMunicipalityPointsByInseeCodes(
            $inseeCodes,
            self::MAP_MUNICIPALITY_SHAPE_LIMIT,
        );
        $serializedPoints = [];

        foreach ($points as $point) {
            $serializedPoints[] = [
                'inseeCode' => $point->inseeCode,
                'label' => $point->label,
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
            ];
        }

        return $serializedPoints;
    }

    public function getAudienceMapMaterializedMunicipalityCount(): int
    {
        try {
            return count($this->newsletterAudienceMunicipalityMaterializer->materialize(
                $this->currentFormModel()->toAudienceFilter(),
            ));
        } catch (InvalidArgumentException) {
            return 0;
        }
    }

    public function isAudienceMapMunicipalityShapesTruncated(): bool
    {
        return $this->getAudienceMapMaterializedMunicipalityCount() > self::MAP_MUNICIPALITY_SHAPE_LIMIT;
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

        return NewsletterAudienceRadiusOrigin::HOME->value === $radiusOrigin || null === $radiusOrigin
            ? $this->homePoint()
            : null;
    }

    private function radiusPreviewTitle(): string
    {
        return match ($this->radiusOriginValue()) {
            NewsletterAudienceRadiusOrigin::CUSTOM->value => $this->translator->trans('mailing.audience.map.origin_custom', [], 'mailing'),
            default => $this->translator->trans('mailing.audience.map.origin_home', [], 'mailing'),
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
        return $this->currentFormModel()->getRadiusOriginValue();
    }

    private function radiusKilometersValue(): ?float
    {
        return $this->currentFormModel()->radiusKilometers;
    }

    private function customLatitudeValue(): ?float
    {
        return $this->currentFormModel()->radiusOriginCustomLatitude;
    }

    private function customLongitudeValue(): ?float
    {
        return $this->currentFormModel()->radiusOriginCustomLongitude;
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
}
