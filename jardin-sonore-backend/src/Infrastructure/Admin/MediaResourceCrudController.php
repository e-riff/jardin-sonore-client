<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Application\Storage\MediaResourceFileStorageInterface;
use App\Domain\Model\Session\MediaResourceType;
use App\Infrastructure\Doctrine\Entity\MediaResourceEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use ReflectionClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Dropzone\Form\DropzoneType;

/**
 * @extends AbstractCrudController<MediaResourceEntity>
 */
final class MediaResourceCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MediaResourceFileStorageInterface $mediaResourceFileStorage,
        private readonly RequestStack $requestStack,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return MediaResourceEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('session_catalog.media_resource.singular')
            ->setEntityLabelInPlural('session_catalog.media_resource.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.media_resource.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.media_resource.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.media_resource.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.media_resource.page.detail')
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['title', 'source', 'description', 'primaryUrl', 'secondaryUrl']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('title', 'admin.field.title'))
            ->add(ChoiceFilter::new('type', 'admin.field.type')
                ->setChoices($this->mediaTypeChoices())
                ->setFormTypeOption('value_type_options.translation_domain', 'sessions'))
            ->add(BooleanFilter::new('active', 'admin.field.active'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield ChoiceField::new('type', 'admin.field.type')
            ->setChoices($this->mediaTypeChoices())
            ->setFormTypeOption('choice_translation_domain', 'sessions')
            ->formatValue(fn (mixed $value): string => $this->translateMediaType($value));
        yield TextField::new('title', 'admin.field.title');
        yield TextField::new('source', 'admin.field.source')
            ->setFormTypeOption('required', false);
        yield TextareaField::new('description', 'admin.field.description')
            ->setFormTypeOption('required', false)
            ->hideOnIndex();
        yield TextField::new('primaryUrl', 'admin.field.primary_url')
            ->formatValue(fn (mixed $value): string => $this->formatLink($value))
            ->renderAsHtml()
            ->hideOnForm();
        yield UrlField::new('primaryUrl', 'admin.field.primary_url')
            ->setFormTypeOption('required', false)
            ->onlyOnForms();
        yield Field::new('primaryFileUpload', 'admin.field.primary_file')
            ->setFormType(DropzoneType::class)
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('required', false)
            ->onlyOnForms();
        yield TextField::new('secondaryUrl', 'admin.field.secondary_url')
            ->formatValue(fn (mixed $value): string => $this->formatLink($value))
            ->renderAsHtml()
            ->hideOnForm()
            ->hideOnIndex();
        yield UrlField::new('secondaryUrl', 'admin.field.secondary_url')
            ->setFormTypeOption('required', false)
            ->onlyOnForms();
        yield TextField::new('imageUrl', 'admin.field.image_url')
            ->formatValue(fn (mixed $value): string => $this->formatLink($value))
            ->renderAsHtml()
            ->hideOnForm()
            ->hideOnIndex();
        yield UrlField::new('imageUrl', 'admin.field.image_url')
            ->setFormTypeOption('required', false)
            ->onlyOnForms();
        yield Field::new('imageFileUpload', 'admin.field.image_file')
            ->setFormType(DropzoneType::class)
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('required', false)
            ->setFormTypeOption('constraints', [
                new Image(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp']),
            ])
            ->setFormTypeOption('attr', [
                'accept' => 'image/jpeg,image/png,image/webp',
            ])
            ->onlyOnForms();
        yield BooleanField::new('active', 'admin.field.active');
        yield DateTimeField::new('updatedAt', 'admin.field.updated_at')->hideOnForm();
        yield DateTimeField::new('createdAt', 'admin.field.created_at')->onlyOnDetail();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof MediaResourceEntity) {
            $this->prepareEntity($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof MediaResourceEntity) {
            $this->prepareEntity($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * @return array<string, MediaResourceType>
     */
    private function mediaTypeChoices(): array
    {
        $choices = [];

        foreach (MediaResourceType::cases() as $mediaResourceType) {
            $choices[$mediaResourceType->translationKey()] = $mediaResourceType;
        }

        return $choices;
    }

    private function translateMediaType(mixed $value): string
    {
        if ($value instanceof MediaResourceType) {
            return $this->translator->trans($value->translationKey(), [], 'sessions');
        }

        if (is_string($value) && '' !== $value) {
            return $this->translator->trans(MediaResourceType::from($value)->translationKey(), [], 'sessions');
        }

        return '';
    }

    private function prepareEntity(MediaResourceEntity $mediaResourceEntity): void
    {
        $primaryFileUpload = $this->extractUploadedFile('primaryFileUpload');
        $imageFileUpload = $this->extractUploadedFile('imageFileUpload');

        if ($primaryFileUpload instanceof UploadedFile) {
            $mediaResourceEntity->setPrimaryUrl($this->mediaResourceFileStorage->storePrimaryFile($primaryFileUpload));
        }

        if ($imageFileUpload instanceof UploadedFile) {
            $mediaResourceEntity->setImageUrl($this->mediaResourceFileStorage->storeImageFile($imageFileUpload));
        }

        $mediaResourceEntity
            ->setTitle(trim($mediaResourceEntity->getTitle()))
            ->setSource($this->normalizeNullableString($mediaResourceEntity->getSource()))
            ->setDescription($this->normalizeNullableString($mediaResourceEntity->getDescription()))
            ->setPrimaryUrl(trim($mediaResourceEntity->getPrimaryUrl()))
            ->setSecondaryUrl($this->normalizeNullableString($mediaResourceEntity->getSecondaryUrl()))
            ->setImageUrl($this->normalizeNullableString($mediaResourceEntity->getImageUrl()))
            ->setUpdatedAt(new DateTimeImmutable());
    }

    private function extractUploadedFile(string $fieldName): ?UploadedFile
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return null;
        }

        $formName = (new ReflectionClass(self::getEntityFqcn()))->getShortName();
        $uploadedFile = $request->files->all()[$formName][$fieldName] ?? null;

        return $uploadedFile instanceof UploadedFile ? $uploadedFile : null;
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmedValue = trim($value);

        return '' === $trimmedValue ? null : $trimmedValue;
    }

    private function formatLink(mixed $value): string
    {
        if (!is_string($value) || '' === trim($value)) {
            return '';
        }

        $normalizedValue = trim($value);
        $href = str_starts_with($normalizedValue, 'http://') || str_starts_with($normalizedValue, 'https://')
            ? $normalizedValue
            : '/' . ltrim($normalizedValue, '/');
        $label = str_starts_with($normalizedValue, 'uploads/') ? 'Ouvrir le fichier' : $normalizedValue;

        return sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', htmlspecialchars($href, ENT_QUOTES), htmlspecialchars($label, ENT_QUOTES));
    }
}
