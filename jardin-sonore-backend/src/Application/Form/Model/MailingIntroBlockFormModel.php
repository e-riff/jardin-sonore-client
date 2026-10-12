<?php

declare(strict_types=1);

namespace App\Application\Form\Model;

use App\Application\Mailing\MailingIntroBlockInput;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Image;

final class MailingIntroBlockFormModel
{
    public string $id;

    public string $kind = 'paragraph';

    public string $text = '';

    public ?string $imagePath = null;

    public ?string $imageAlt = null;

    public bool $imageDecorative = false;

    public string $imageSide = 'right';

    #[Image(
        maxSize: '5M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        mimeTypesMessage: 'mailing.form.intro_image_invalid_type',
        maxSizeMessage: 'mailing.form.intro_image_invalid_size',
    )]
    public ?UploadedFile $imageFile = null;

    public function __construct()
    {
        $this->id = Uuid::v7()->toRfc4122();
    }

    /** @param array<string, mixed> $block */
    public static function fromBlock(array $block): self
    {
        $model = new self();
        $model->id = (string) ($block['id'] ?? $model->id);
        $model->kind = (string) ($block['kind'] ?? 'paragraph');
        $model->text = (string) ($block['text'] ?? '');
        $model->imagePath = is_string($block['imagePath'] ?? null) ? $block['imagePath'] : null;
        $model->imageAlt = is_string($block['imageAlt'] ?? null) ? $block['imageAlt'] : null;
        $model->imageDecorative = true === ($block['imageDecorative'] ?? false);
        $model->imageSide = is_string($block['imageSide'] ?? null) ? $block['imageSide'] : 'right';

        return $model;
    }

    public function toInput(): MailingIntroBlockInput
    {
        return new MailingIntroBlockInput(
            id: $this->id,
            kind: $this->kind,
            text: $this->text,
            imageFile: $this->imageFile,
            imageAlt: $this->imageAlt,
            imageDecorative: $this->imageDecorative,
            imageSide: $this->imageSide,
        );
    }
}
