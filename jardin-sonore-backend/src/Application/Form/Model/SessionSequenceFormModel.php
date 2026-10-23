<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Application\Session\MediaResourceView;
use App\Application\Session\RepertoireItemView;
use App\Application\Session\SessionRecommendationView;
use App\Application\Session\SessionSequenceView;
use App\Domain\Model\Session\SessionSequenceSourceKind;
use App\Domain\Model\Session\SessionSequenceType;
use Symfony\Component\Validator\Constraints as Assert;

final class SessionSequenceFormModel
{
    public SessionSequenceType $type = SessionSequenceType::FREE;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public ?string $title = '';

    #[Assert\Length(max: 255)]
    public ?string $subtitle = null;

    public string $body = '';

    public ?string $lyrics = null;

    public ?string $gestures = null;

    #[Assert\Url]
    public ?string $primaryUrl = null;

    #[Assert\Url]
    public ?string $secondaryUrl = null;

    #[Assert\Url]
    public ?string $imageUrl = null;

    /** @var list<SessionSequenceMediaFormModel> */
    public array $media = [];

    /** @var list<string> */
    public array $catalogMediaUuids = [];

    public bool $showLyricsByDefault = false;

    #[Assert\Length(max: 255)]
    public ?string $role = null;

    public ?string $sourceUuid = null;

    public ?SessionSequenceSourceKind $sourceKind = null;

    public ?string $sourceTitle = null;

    /** @var list<string> */
    public array $instrumentUuids = [];

    public static function fromView(SessionSequenceView $sessionSequenceView): self
    {
        $formModel = new self();
        $formModel->type = $sessionSequenceView->type;
        $formModel->title = $sessionSequenceView->title;
        $formModel->subtitle = $sessionSequenceView->subtitle;
        $formModel->body = $sessionSequenceView->body;
        $formModel->lyrics = $sessionSequenceView->lyrics;
        $formModel->gestures = $sessionSequenceView->gestures;
        $formModel->primaryUrl = $sessionSequenceView->primaryUrl;
        $formModel->secondaryUrl = $sessionSequenceView->secondaryUrl;
        $formModel->imageUrl = $sessionSequenceView->imageUrl;
        $formModel->media = array_map(SessionSequenceMediaFormModel::fromDomain(...), [] !== $sessionSequenceView->storedMedia ? $sessionSequenceView->storedMedia : $sessionSequenceView->media);
        $formModel->catalogMediaUuids = array_values(array_filter(array_map(
            static fn (SessionSequenceMediaFormModel $media): ?string => $media->resourceUuid,
            $formModel->media,
        )));
        $formModel->showLyricsByDefault = $sessionSequenceView->showLyricsByDefault;
        $formModel->role = $sessionSequenceView->role;
        $formModel->sourceUuid = $sessionSequenceView->sourceUuid?->toRfc4122();
        $formModel->sourceKind = $sessionSequenceView->sourceKind;
        $formModel->sourceTitle = $sessionSequenceView->sourceTitle;
        $formModel->instrumentUuids = $sessionSequenceView->instrumentUuids;

        return $formModel;
    }

    public function addMediaResource(MediaResourceView $mediaResourceView): void
    {
        foreach ($this->media as $media) {
            if ($media->resourceUuid === $mediaResourceView->uuid->toRfc4122()) {
                return;
            }
        }

        $sessionSequenceMediaFormModel = new SessionSequenceMediaFormModel();
        $sessionSequenceMediaFormModel->label = $mediaResourceView->title;
        $sessionSequenceMediaFormModel->type = $mediaResourceView->type;
        $sessionSequenceMediaFormModel->url = $mediaResourceView->primaryUrl;
        $sessionSequenceMediaFormModel->imageUrl = $mediaResourceView->imageUrl;
        $sessionSequenceMediaFormModel->featured = [] === $this->media;
        $sessionSequenceMediaFormModel->resourceUuid = $mediaResourceView->uuid->toRfc4122();

        $this->media[] = $sessionSequenceMediaFormModel;
        if (!in_array($mediaResourceView->uuid->toRfc4122(), $this->catalogMediaUuids, true)) {
            $this->catalogMediaUuids[] = $mediaResourceView->uuid->toRfc4122();
        }
    }

    public function retainSelectedCatalogMedia(): void
    {
        $this->media = array_values(array_filter(
            $this->media,
            fn (SessionSequenceMediaFormModel $media): bool => null === $media->resourceUuid
                || in_array($media->resourceUuid, $this->catalogMediaUuids, true),
        ));
    }

    public static function fromRepertoireItemView(RepertoireItemView $repertoireItemView): self
    {
        $formModel = new self();
        $formModel->type = SessionSequenceType::fromRepertoireItemType($repertoireItemView->type);
        $formModel->title = $repertoireItemView->title;
        $formModel->subtitle = $repertoireItemView->source;
        $formModel->body = '';
        $formModel->lyrics = $repertoireItemView->lyrics;
        $formModel->gestures = $repertoireItemView->gestures;
        $formModel->sourceUuid = $repertoireItemView->uuid->toRfc4122();
        $formModel->sourceKind = SessionSequenceSourceKind::REPERTOIRE_ITEM;
        $formModel->sourceTitle = $repertoireItemView->title;

        return $formModel;
    }

    public static function fromMediaResourceView(MediaResourceView $mediaResourceView): self
    {
        $formModel = new self();
        $formModel->type = SessionSequenceType::SOUNDTRACK;
        $formModel->title = $mediaResourceView->title;
        $formModel->subtitle = $mediaResourceView->source;
        $formModel->body = $mediaResourceView->description ?? '';
        $formModel->addMediaResource($mediaResourceView);
        $formModel->sourceUuid = $mediaResourceView->uuid->toRfc4122();
        $formModel->sourceKind = SessionSequenceSourceKind::MEDIA_RESOURCE;
        $formModel->sourceTitle = $mediaResourceView->title;

        return $formModel;
    }

    public static function fromSessionRecommendationView(SessionRecommendationView $sessionRecommendationView): self
    {
        $formModel = new self();
        $formModel->type = SessionSequenceType::FREE;
        $formModel->title = $sessionRecommendationView->title;
        $formModel->body = $sessionRecommendationView->text;
        $formModel->primaryUrl = $sessionRecommendationView->primaryUrl;
        $formModel->secondaryUrl = $sessionRecommendationView->secondaryUrl;
        $formModel->imageUrl = $sessionRecommendationView->imagePath;
        $formModel->sourceUuid = $sessionRecommendationView->uuid->toRfc4122();
        $formModel->sourceKind = SessionSequenceSourceKind::SESSION_RECOMMENDATION;
        $formModel->sourceTitle = $sessionRecommendationView->title;

        return $formModel;
    }
}
