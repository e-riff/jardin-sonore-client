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
    public string $title = '';

    #[Assert\Length(max: 255)]
    public ?string $subtitle = null;

    public string $body = '';

    public ?string $lyrics = null;

    public ?string $gestures = null;

    public ?string $notes = null;

    #[Assert\Url]
    public ?string $primaryUrl = null;

    #[Assert\Url]
    public ?string $secondaryUrl = null;

    #[Assert\Url]
    public ?string $imageUrl = null;

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
        $formModel->notes = $sessionSequenceView->notes;
        $formModel->primaryUrl = $sessionSequenceView->primaryUrl;
        $formModel->secondaryUrl = $sessionSequenceView->secondaryUrl;
        $formModel->imageUrl = $sessionSequenceView->imageUrl;
        $formModel->showLyricsByDefault = $sessionSequenceView->showLyricsByDefault;
        $formModel->role = $sessionSequenceView->role;
        $formModel->sourceUuid = $sessionSequenceView->sourceUuid?->toRfc4122();
        $formModel->sourceKind = $sessionSequenceView->sourceKind;
        $formModel->sourceTitle = $sessionSequenceView->sourceTitle;
        $formModel->instrumentUuids = $sessionSequenceView->instrumentUuids;

        return $formModel;
    }

    public static function fromRepertoireItemView(RepertoireItemView $repertoireItemView): self
    {
        $formModel = new self();
        $formModel->type = SessionSequenceType::fromRepertoireItemType($repertoireItemView->type);
        $formModel->title = $repertoireItemView->title;
        $formModel->subtitle = $repertoireItemView->source;
        $formModel->body = $repertoireItemView->body;
        $formModel->lyrics = $repertoireItemView->lyrics;
        $formModel->gestures = $repertoireItemView->gestures;
        $formModel->notes = $repertoireItemView->notes;
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
        $formModel->primaryUrl = $mediaResourceView->primaryUrl;
        $formModel->secondaryUrl = $mediaResourceView->secondaryUrl;
        $formModel->imageUrl = $mediaResourceView->imageUrl;
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
        $formModel->notes = $sessionRecommendationView->notes;
        $formModel->primaryUrl = $sessionRecommendationView->primaryUrl;
        $formModel->secondaryUrl = $sessionRecommendationView->secondaryUrl;
        $formModel->imageUrl = $sessionRecommendationView->imageUrl;
        $formModel->sourceUuid = $sessionRecommendationView->uuid->toRfc4122();
        $formModel->sourceKind = SessionSequenceSourceKind::SESSION_RECOMMENDATION;
        $formModel->sourceTitle = $sessionRecommendationView->title;

        return $formModel;
    }
}
