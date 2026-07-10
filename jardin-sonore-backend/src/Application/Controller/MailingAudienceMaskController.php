<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\MailingAudienceMaskType;
use App\Application\Form\Model\MailingAudienceGeographicMode;
use App\Application\Form\Model\MailingAudienceMaskFormModel;
use App\Application\Mailing\ApplyMailingAudienceMaskToCampaign;
use App\Application\Mailing\CreateMailingAudienceMask;
use App\Application\Mailing\CreateMailingAudienceMaskInput;
use App\Application\Mailing\GetMailingAudienceMask;
use App\Application\Mailing\GetMailingCampaign;
use App\Application\Mailing\ListMailingAudienceMasks;
use App\Application\Mailing\NewsletterAudienceMunicipalityMaterializerInterface;
use App\Application\Mailing\UpdateMailingCampaignAudience;
use App\Domain\Model\AddressBook\CustomerStatus;
use App\Domain\Model\AddressBook\OrganizationSector;
use App\Domain\Model\AddressBook\OrganizationType;
use App\Domain\Model\Mailing\MailingCampaign;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Model\Mailing\NewsletterAudienceRadiusOrigin;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/mailing/audience-masks', name: 'mailing_audience_mask_')]
final class MailingAudienceMaskController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        ListMailingAudienceMasks $listMailingAudienceMasks,
        GetMailingCampaign $getMailingCampaign,
    ): Response {
        return $this->render('mailing/audience_mask/index.html.twig', [
            'campaign' => $this->resolveMailingCampaignFromQuery($request, $getMailingCampaign),
            'audienceMasks' => $listMailingAudienceMasks(),
        ]);
    }

    #[Route('/from-campaign/{campaignUuid}', name: 'save_from_campaign', methods: ['POST'])]
    public function saveFromCampaign(
        string $campaignUuid,
        Request $request,
        GetMailingCampaign $getMailingCampaign,
        CreateMailingAudienceMask $createMailingAudienceMask,
        NewsletterAudienceMunicipalityMaterializerInterface $newsletterAudienceMunicipalityMaterializer,
        UpdateMailingCampaignAudience $updateMailingCampaignAudience,
        LoggerInterface $logger,
    ): Response {
        $mailingCampaign = $this->resolveMailingCampaign($campaignUuid, $getMailingCampaign);
        $form = $this->createForm(MailingAudienceMaskType::class, new MailingAudienceMaskFormModel());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formModel = $form->getData();

            if (!$formModel instanceof MailingAudienceMaskFormModel) {
                throw new InvalidArgumentException('Mailing audience mask form data is invalid.');
            }

            try {
                [$audienceFilter, $materializedMunicipalityInseeCodes] = $this->resolveAudienceSnapshot(
                    $formModel,
                    $newsletterAudienceMunicipalityMaterializer,
                    $mailingCampaign->getAudienceFilter(),
                );

                $createMailingAudienceMask(new CreateMailingAudienceMaskInput(
                    name: $formModel->name,
                    audienceFilter: $audienceFilter,
                    materializedMunicipalityInseeCodes: $materializedMunicipalityInseeCodes,
                ));
                $updateMailingCampaignAudience($mailingCampaign, $audienceFilter);
                $this->addFlash('success', 'mailing.flash.audience_mask_created');
            } catch (InvalidArgumentException $invalidArgumentException) {
                $logger->warning('Mailing audience mask creation failed.', [
                    'campaign_uuid' => $campaignUuid,
                    'message' => $invalidArgumentException->getMessage(),
                ]);
                $this->addFlash('error', 'mailing.flash.audience_mask_create_failed');
            }
        } else {
            $formErrors = [];

            foreach ($form->getErrors(true, true) as $formError) {
                $fieldName = $formError->getOrigin()?->getName() ?? 'form';
                $formErrors[] = "{$fieldName}: {$formError->getMessage()}";
            }

            $logger->warning('Mailing audience mask form is invalid.', [
                'campaign_uuid' => $campaignUuid,
                'errors' => $formErrors,
                'submitted_payload' => $request->request->all('mailing_audience_mask'),
            ]);
            $this->addFlash('error', 'mailing.flash.audience_mask_create_invalid');
        }

        return $this->redirectToRoute('mailing_audience', [
            'uuid' => $mailingCampaign->getUuid(),
        ], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{maskUuid}/apply-to-campaign/{campaignUuid}', name: 'apply_to_campaign', methods: ['POST'])]
    public function applyToCampaign(
        string $maskUuid,
        string $campaignUuid,
        Request $request,
        GetMailingAudienceMask $getMailingAudienceMask,
        GetMailingCampaign $getMailingCampaign,
        ApplyMailingAudienceMaskToCampaign $applyMailingAudienceMaskToCampaign,
    ): Response {
        $mailingAudienceMask = Uuid::isValid($maskUuid) ? $getMailingAudienceMask(Uuid::fromString($maskUuid)) : null;
        $mailingCampaign = $this->resolveMailingCampaign($campaignUuid, $getMailingCampaign);

        if (null === $mailingAudienceMask) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid(
            'mailing_apply_audience_mask_' . $mailingAudienceMask->getUuid()->toRfc4122() . '_' . $mailingCampaign->getUuid()->toRfc4122(),
            (string) $request->request->get('_token'),
        )) {
            throw $this->createAccessDeniedException();
        }

        try {
            $applyMailingAudienceMaskToCampaign($mailingCampaign, $mailingAudienceMask);
            $this->addFlash('success', 'mailing.flash.audience_mask_applied');
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'mailing.flash.audience_mask_apply_failed');
        }

        return $this->redirectToRoute('mailing_audience', [
            'uuid' => $mailingCampaign->getUuid(),
        ], Response::HTTP_SEE_OTHER);
    }

    private function resolveMailingCampaign(string $campaignUuid, GetMailingCampaign $getMailingCampaign): MailingCampaign
    {
        if (!Uuid::isValid($campaignUuid)) {
            throw $this->createNotFoundException();
        }

        $mailingCampaign = $getMailingCampaign(Uuid::fromString($campaignUuid));

        if (!$mailingCampaign instanceof MailingCampaign) {
            throw $this->createNotFoundException();
        }

        return $mailingCampaign;
    }

    private function resolveMailingCampaignFromQuery(Request $request, GetMailingCampaign $getMailingCampaign): ?MailingCampaign
    {
        $campaignUuid = $request->query->getString('campaign');

        if ('' === $campaignUuid) {
            return null;
        }

        return $this->resolveMailingCampaign($campaignUuid, $getMailingCampaign);
    }

    /**
     * @return array{0: NewsletterAudienceFilter, 1: list<string>}
     */
    private function resolveAudienceSnapshot(
        MailingAudienceMaskFormModel $formModel,
        NewsletterAudienceMunicipalityMaterializerInterface $newsletterAudienceMunicipalityMaterializer,
        NewsletterAudienceFilter $fallbackAudienceFilter,
    ): array {
        $snapshot = trim($formModel->currentAudienceSnapshot);

        if ('' === $snapshot) {
            return [
                $fallbackAudienceFilter,
                $newsletterAudienceMunicipalityMaterializer->materialize($fallbackAudienceFilter),
            ];
        }

        $submittedAudienceData = json_decode($snapshot, true);

        if (!is_array($submittedAudienceData)) {
            throw new InvalidArgumentException('Mailing audience snapshot is invalid.');
        }
        $audienceFilter = $this->buildAudienceFilterFromSnapshot($submittedAudienceData);
        $materializedMunicipalityInseeCodes = $this->materializeCurrentMunicipalityInseeCodes(
            $audienceFilter,
            $newsletterAudienceMunicipalityMaterializer,
        );

        return [$audienceFilter, $materializedMunicipalityInseeCodes];
    }

    /**
     * @return list<string>
     */
    private function materializeCurrentMunicipalityInseeCodes(
        NewsletterAudienceFilter $audienceFilter,
        NewsletterAudienceMunicipalityMaterializerInterface $newsletterAudienceMunicipalityMaterializer,
    ): array {
        $materializedInseeCodes = $newsletterAudienceMunicipalityMaterializer->materialize($audienceFilter);
        $retainedInseeCodes = [];

        foreach (array_merge($audienceFilter->getMunicipalityInseeCodes(), $materializedInseeCodes) as $inseeCode) {
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

    /**
     * @param array<string, mixed> $submittedAudienceData
     */
    private function buildAudienceFilterFromSnapshot(array $submittedAudienceData): NewsletterAudienceFilter
    {
        $geographicMode = MailingAudienceGeographicMode::tryFrom((string) ($submittedAudienceData['geographicMode'] ?? ''))
            ?? MailingAudienceGeographicMode::MUNICIPALITIES;
        $organizationTypes = $this->normalizeEnumList(
            $submittedAudienceData['organizationTypes'] ?? [],
            OrganizationType::class,
            'organizationTypes',
        );
        $organizationSectors = $this->normalizeEnumList(
            $submittedAudienceData['organizationSectors'] ?? [],
            OrganizationSector::class,
            'organizationSectors',
        );
        $customerStatuses = $this->normalizeEnumList(
            $submittedAudienceData['customerStatuses'] ?? [],
            CustomerStatus::class,
            'customerStatuses',
        );
        $tagUuids = $this->normalizeStringList($submittedAudienceData['tagUuids'] ?? [], 'tagUuids');
        $organizationUuids = $this->normalizeStringList($submittedAudienceData['organizationUuids'] ?? [], 'organizationUuids');
        $municipalityInseeCodes = $this->normalizeStringList($submittedAudienceData['municipalityInseeCodes'] ?? [], 'municipalityInseeCodes');
        $radiusKilometers = $this->nullablePositiveFloat($submittedAudienceData['radiusKilometers'] ?? null, 'radiusKilometers');
        $customLatitude = $this->nullableFloat($submittedAudienceData['radiusOriginCustomLatitude'] ?? null, 'radiusOriginCustomLatitude');
        $customLongitude = $this->nullableFloat($submittedAudienceData['radiusOriginCustomLongitude'] ?? null, 'radiusOriginCustomLongitude');

        return new NewsletterAudienceFilter(
            organizationTypes: $organizationTypes,
            organizationSectors: $organizationSectors,
            customerStatuses: $customerStatuses,
            organizationUuids: $organizationUuids,
            tagUuids: $tagUuids,
            municipalityInseeCodes: MailingAudienceGeographicMode::MUNICIPALITIES === $geographicMode ? $municipalityInseeCodes : [],
            radiusKilometers: MailingAudienceGeographicMode::HOME_RADIUS === $geographicMode
                || MailingAudienceGeographicMode::MUNICIPALITY_RADIUS === $geographicMode
                || MailingAudienceGeographicMode::CUSTOM_RADIUS === $geographicMode
                ? ($radiusKilometers ?? 1.0)
                : null,
            radiusOrigin: match ($geographicMode) {
                MailingAudienceGeographicMode::HOME_RADIUS => NewsletterAudienceRadiusOrigin::HOME,
                MailingAudienceGeographicMode::MUNICIPALITY_RADIUS => NewsletterAudienceRadiusOrigin::MUNICIPALITY,
                MailingAudienceGeographicMode::CUSTOM_RADIUS => NewsletterAudienceRadiusOrigin::CUSTOM,
                default => null,
            },
            radiusOriginMunicipalityInseeCode: MailingAudienceGeographicMode::MUNICIPALITY_RADIUS === $geographicMode
                ? (is_string($submittedAudienceData['radiusOriginMunicipalityInseeCode'] ?? null)
                    ? trim((string) $submittedAudienceData['radiusOriginMunicipalityInseeCode']) ?: null
                    : null)
                : null,
            radiusOriginCustomLatitude: MailingAudienceGeographicMode::CUSTOM_RADIUS === $geographicMode ? $customLatitude : null,
            radiusOriginCustomLongitude: MailingAudienceGeographicMode::CUSTOM_RADIUS === $geographicMode ? $customLongitude : null,
        );
    }

    /**
     * @return list<string>
     */
    private function normalizeStringList(mixed $value, string $fieldName): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException("Mailing audience snapshot field {$fieldName} must be a list.");
        }

        $normalizedValues = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new InvalidArgumentException("Mailing audience snapshot field {$fieldName} must contain strings only.");
            }

            $normalizedItem = trim($item);

            if ('' === $normalizedItem) {
                continue;
            }

            if (in_array($normalizedItem, $normalizedValues, true)) {
                continue;
            }

            $normalizedValues[] = $normalizedItem;
        }

        return $normalizedValues;
    }

    /**
     * @template T of \BackedEnum
     *
     * @param class-string<T> $enumClass
     *
     * @return list<T>
     */
    private function normalizeEnumList(mixed $value, string $enumClass, string $fieldName): array
    {
        $normalizedValues = [];
        $enumCases = $enumClass::cases();

        foreach ($this->normalizeStringList($value, $fieldName) as $item) {
            $normalizedEnum = $enumClass::tryFrom($item);

            if (null === $normalizedEnum && ctype_digit($item)) {
                $normalizedEnum = $enumCases[(int) $item] ?? null;
            }

            if (null === $normalizedEnum) {
                throw new InvalidArgumentException("Mailing audience snapshot field {$fieldName} contains an invalid enum value.");
            }

            if (in_array($normalizedEnum, $normalizedValues, true)) {
                continue;
            }

            $normalizedValues[] = $normalizedEnum;
        }

        return $normalizedValues;
    }

    private function nullableFloat(mixed $value, string $fieldName): ?float
    {
        if (null === $value || '' === trim((string) $value)) {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Mailing audience snapshot field {$fieldName} must be numeric.");
        }

        return (float) $value;
    }

    private function nullablePositiveFloat(mixed $value, string $fieldName): ?float
    {
        $normalizedValue = $this->nullableFloat($value, $fieldName);

        if (null !== $normalizedValue && 0 >= $normalizedValue) {
            throw new InvalidArgumentException("Mailing audience snapshot field {$fieldName} must be greater than zero.");
        }

        return $normalizedValue;
    }
}
