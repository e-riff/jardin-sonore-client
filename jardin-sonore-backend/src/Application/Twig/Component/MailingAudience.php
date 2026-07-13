<?php

declare(strict_types=1);

namespace App\Application\Twig\Component;

use App\Application\Form\MailingAudienceType;
use App\Application\Form\Model\MailingAudienceFormModel;
use App\Application\Mailing\ExtendMailingCampaignAudience;
use App\Application\Mailing\GetMailingAudienceMask;
use App\Application\Mailing\GetMailingCampaign;
use App\Application\Mailing\MailingDeliveryQueueInterface;
use App\Application\Mailing\NewsletterAudienceMapQueryInterface;
use App\Application\Mailing\NewsletterAudienceMunicipalityMaterializerInterface;
use App\Application\Mailing\NewsletterAudienceResolution;
use App\Application\Mailing\NewsletterAudienceResolverInterface;
use App\Application\Mailing\UpdateMailingCampaignAudience;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterRecipient;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
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
use Symfony\UX\Map\Map;
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

    private const int MAP_MUNICIPALITY_SHAPE_LIMIT = 100;

    #[LiveProp]
    public string $campaignUuid = '';

    #[LiveProp]
    public bool $saved = false;

    #[LiveProp]
    public string $returnTo = '';

    #[LiveProp]
    public bool $locked = false;

    #[LiveProp]
    public bool $extensionMode = false;

    #[LiveProp]
    public string $initialAudienceMaskUuid = '';

    private ?MailingCampaign $mailingCampaign = null;

    private ?NewsletterAudienceResolution $audienceResolution = null;

    private bool $audienceResolutionLoaded = false;

    private ?string $audienceResolutionError = null;

    /**
     * @var array{
     *     matchedRecipientCount:int,
     *     alreadyLinkedRecipientCount:int,
     *     newRecipientCount:int,
     *     previewRecipients:list<NewsletterRecipient>
     * }|null
     */
    private ?array $audienceDelta = null;

    public function __construct(
        private readonly FormFactoryInterface $formFactory,
        private readonly GetMailingCampaign $getMailingCampaignQuery,
        private readonly GetMailingAudienceMask $getMailingAudienceMask,
        private readonly NewsletterAudienceResolverInterface $newsletterAudienceResolver,
        private readonly UpdateMailingCampaignAudience $updateMailingCampaignAudience,
        private readonly ExtendMailingCampaignAudience $extendMailingCampaignAudience,
        private readonly MailingDeliveryQueueInterface $mailingDeliveryQueue,
        private readonly NewsletterAudienceMunicipalityMaterializerInterface $newsletterAudienceMunicipalityMaterializer,
        private readonly NewsletterAudienceMapQueryInterface $newsletterAudienceMapQuery,
        #[Autowire('%app.mailing.home_latitude%')]
        private readonly string $homeLatitude,
        #[Autowire('%app.mailing.home_longitude%')]
        private readonly string $homeLongitude,
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[LiveAction]
    public function save(): void
    {
        if ($this->locked) {
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

        $mailingCampaign = $this->resolveMailingCampaign();

        try {
            if ($this->extensionMode) {
                $result = ($this->extendMailingCampaignAudience)($mailingCampaign, $audienceFilter);
                $this->addFlashMessage('success', 'mailing.flash.audience_extension_success');
                $this->addFlashMessage('info', 'mailing.flash.audience_extension_success_details');
                $this->currentSession()?->set('mailing.audience_extension_result', [
                    'matchedRecipientCount' => $result->matchedRecipientCount,
                    'alreadyLinkedRecipientCount' => $result->alreadyLinkedRecipientCount,
                    'newRecipientCount' => $result->newRecipientCount,
                ]);
            } else {
                if (!$mailingCampaign->isEditable()) {
                    return;
                }

                ($this->updateMailingCampaignAudience)($mailingCampaign, $audienceFilter);
                $this->addFlashMessage('success', 'mailing.flash.audience_saved');
            }
        } catch (InvalidArgumentException $invalidArgumentException) {
            $translationKey = match ($invalidArgumentException->getMessage()) {
                'Mailing campaign audience cannot be extended.' => 'mailing.flash.audience_extension_not_allowed',
                'Mailing campaign extension audience is empty.' => 'mailing.flash.audience_extension_empty',
                'Mailing campaign extension audience only contains already linked recipients.' => 'mailing.flash.audience_extension_no_delta',
                default => 'mailing.flash.audience_extension_failed',
            };
            $this->getForm()->addError(new FormError($translationKey));

            return;
        }

        $this->saved = true;
        $this->audienceResolution = null;
        $this->audienceResolutionLoaded = false;
        $this->audienceDelta = null;
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
                $this->extensionMode ? null : 10,
            );
            $this->audienceDelta = $this->extensionMode ? $this->buildAudienceDelta($this->audienceResolution) : null;
        } catch (InvalidArgumentException) {
            $this->audienceResolutionError = 'mailing.audience.result.invalid_filter';
            $this->audienceDelta = null;
        }

        return $this->audienceResolution;
    }

    public function getAudienceResolutionError(): ?string
    {
        $this->getAudienceResolution();

        return $this->audienceResolutionError;
    }

    /**
     * @return array{
     *     matchedRecipientCount:int,
     *     alreadyLinkedRecipientCount:int,
     *     newRecipientCount:int,
     *     previewRecipients:list<NewsletterRecipient>
     * }|null
     */
    public function getAudienceDelta(): ?array
    {
        $this->getAudienceResolution();

        return $this->audienceDelta;
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

    public function shouldDisplayMap(): bool
    {
        return true;
    }

    #[ExposeInTemplate(name: 'audienceMapHomeLatitude')]
    public function getAudienceMapHomeLatitude(): float
    {
        return (float) $this->homeLatitude;
    }

    #[ExposeInTemplate(name: 'audienceMapHomeLongitude')]
    public function getAudienceMapHomeLongitude(): float
    {
        return (float) $this->homeLongitude;
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
            $inseeCodes = $this->materializeCurrentMunicipalityInseeCodes($this->currentFormModel());
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
        try {
            $inseeCodes = $this->materializeCurrentMunicipalityInseeCodes($this->currentFormModel());
        } catch (InvalidArgumentException) {
            return [];
        }

        if (!$this->isAudienceMapMunicipalityShapesTruncated()) {
            $shapeInseeCodes = array_flip(array_map(
                static fn (array $shape): string => $shape['inseeCode'],
                $this->getAudienceMapMunicipalityShapes(),
            ));
            $inseeCodes = array_values(array_filter(
                $inseeCodes,
                static fn (string $inseeCode): bool => !isset($shapeInseeCodes[$inseeCode]),
            ));
        }

        if ([] === $inseeCodes) {
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
            return count($this->materializeCurrentMunicipalityInseeCodes($this->currentFormModel()));
        } catch (InvalidArgumentException) {
            return 0;
        }
    }

    public function isAudienceMapMunicipalityShapesTruncated(): bool
    {
        return self::MAP_MUNICIPALITY_SHAPE_LIMIT < $this->getAudienceMapMaterializedMunicipalityCount();
    }

    /**
     * @return FormInterface<MailingAudienceFormModel>
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(
            MailingAudienceType::class,
            $this->extensionMode
                ? $this->initialExtensionFormModel()
                : MailingAudienceFormModel::fromAudienceFilter(
                    $this->resolveMailingCampaign()->getAudienceFilter(),
                ),
            [
                'locked' => $this->locked || (!$this->extensionMode && !$this->resolveMailingCampaign()->isEditable()),
            ],
        );
    }

    #[ExposeInTemplate(name: 'audienceMap')]
    public function getAudienceMap(): Map
    {
        $defaultPoint = $this->homePoint();

        if (!$defaultPoint instanceof Point) {
            throw new InvalidArgumentException('Newsletter audience map requires a default point.');
        }

        $map = (new Map())
            ->center($defaultPoint)
            ->zoom(11.5)
            ->minZoom(5.0)
            ->maxZoom(17.0)
            ->options(new LeafletOptions());

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
        $rawAudienceFilter = $formModel->toAudienceFilter();

        return new NewsletterAudienceFilter(
            organizationTypes: $rawAudienceFilter->getOrganizationTypes(),
            organizationSectors: $rawAudienceFilter->getOrganizationSectors(),
            customerStatuses: $rawAudienceFilter->getCustomerStatuses(),
            organizationUuids: $rawAudienceFilter->getOrganizationUuids(),
            tagUuids: $rawAudienceFilter->getTagUuids(),
            municipalityInseeCodes: $this->materializeCurrentMunicipalityInseeCodes($formModel),
        );
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

    private function currentFormModel(): MailingAudienceFormModel
    {
        $formModel = $this->getForm()->getData();

        if ($formModel instanceof MailingAudienceFormModel) {
            return $formModel;
        }

        return $this->extensionMode
            ? $this->initialExtensionFormModel()
            : MailingAudienceFormModel::fromAudienceFilter(
                $this->resolveMailingCampaign()->getAudienceFilter(),
            );
    }

    private function initialExtensionFormModel(): MailingAudienceFormModel
    {
        if (!$this->extensionMode || !Uuid::isValid($this->initialAudienceMaskUuid)) {
            return new MailingAudienceFormModel();
        }

        $mailingAudienceMask = ($this->getMailingAudienceMask)(Uuid::fromString($this->initialAudienceMaskUuid));

        if (null === $mailingAudienceMask) {
            return new MailingAudienceFormModel();
        }

        return MailingAudienceFormModel::fromAudienceFilter($mailingAudienceMask->getAudienceFilter());
    }

    /**
     * @return list<string>
     */
    private function materializeCurrentMunicipalityInseeCodes(MailingAudienceFormModel $formModel): array
    {
        $materializedInseeCodes = $this->newsletterAudienceMunicipalityMaterializer->materialize($formModel->toAudienceFilter());
        $retainedInseeCodes = [];

        foreach (array_merge($formModel->municipalityInseeCodes, $materializedInseeCodes) as $inseeCode) {
            $normalizedInseeCode = trim($inseeCode);

            if ('' === $normalizedInseeCode) {
                continue;
            }

            if (in_array($normalizedInseeCode, $retainedInseeCodes, true)) {
                continue;
            }

            $retainedInseeCodes[] = $normalizedInseeCode;
        }

        sort($retainedInseeCodes);

        return $retainedInseeCodes;
    }

    private function addFlashMessage(string $type, string $message): void
    {
        $this->flashBag()?->add($type, $message);
    }

    private function currentSession(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }

    private function flashBag(): ?FlashBagInterface
    {
        $flashBag = $this->currentSession()?->getBag('flashes');

        return $flashBag instanceof FlashBagInterface ? $flashBag : null;
    }

    private function homePoint(): ?Point
    {
        if (!is_numeric($this->homeLatitude) || !is_numeric($this->homeLongitude)) {
            return null;
        }

        return new Point((float) $this->homeLatitude, (float) $this->homeLongitude);
    }

    /**
     * @return array{
     *     matchedRecipientCount:int,
     *     alreadyLinkedRecipientCount:int,
     *     newRecipientCount:int,
     *     previewRecipients:list<NewsletterRecipient>
     * }
     */
    private function buildAudienceDelta(NewsletterAudienceResolution $audienceResolution): array
    {
        $existingEmailAddressLookup = array_fill_keys(
            $this->mailingDeliveryQueue->findCampaignRecipientEmailAddresses(
                $this->resolveMailingCampaign()->getUuid()->toRfc4122(),
            ),
            true,
        );
        $newRecipientCount = 0;
        $previewRecipients = [];

        foreach ($audienceResolution->getRecipients() as $newsletterRecipient) {
            $normalizedEmailAddress = mb_strtolower(trim($newsletterRecipient->getEmailAddress()->value()));

            if ('' === $normalizedEmailAddress || isset($existingEmailAddressLookup[$normalizedEmailAddress])) {
                continue;
            }

            $existingEmailAddressLookup[$normalizedEmailAddress] = true;
            ++$newRecipientCount;

            $previewRecipients[] = $newsletterRecipient;
        }

        return [
            'matchedRecipientCount' => $audienceResolution->getTotal(),
            'alreadyLinkedRecipientCount' => $audienceResolution->getTotal() - $newRecipientCount,
            'newRecipientCount' => $newRecipientCount,
            'previewRecipients' => $previewRecipients,
        ];
    }
}
