<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Application\Storage\SessionRecommendationImageStorageInterface;
use App\Infrastructure\Doctrine\Entity\SessionRecommendationEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\UX\Dropzone\Form\DropzoneType;

/**
 * @extends AbstractCrudController<SessionRecommendationEntity>
 */
final class SessionRecommendationCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly SessionRecommendationImageStorageInterface $sessionRecommendationImageStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SessionRecommendationEntity::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('session_catalog.session_recommendation.singular')
            ->setEntityLabelInPlural('session_catalog.session_recommendation.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.session_recommendation.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.session_recommendation.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.session_recommendation.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.session_recommendation.page.detail')
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['title', 'text', 'notes', 'primaryUrl', 'secondaryUrl']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('title', 'admin.field.title'))
            ->add(BooleanFilter::new('active', 'admin.field.active'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->hideOnForm();
        yield TextField::new('title', 'admin.field.title');
        yield TextareaField::new('text', 'admin.field.text');
        yield TextareaField::new('notes', 'admin.field.notes')
            ->setFormTypeOption('required', false)
            ->hideOnIndex();
        yield TextField::new('primaryUrl', 'admin.field.primary_url')
            ->formatValue(fn (mixed $value): string => $this->formatLink($value))
            ->renderAsHtml()
            ->hideOnForm()
            ->hideOnIndex();
        yield UrlField::new('primaryUrl', 'admin.field.primary_url')
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
            ->setHelp('admin.help.recommendation_image_url')
            ->setFormTypeOption('attr', [
                'data-controller' => 'exclusive-resource-fields',
                'data-exclusive-resource-fields-peer-selector-value' => '[name$="[imageFileUpload]"]',
            ])
            ->onlyOnForms();
        yield Field::new('imageFileUpload', 'admin.field.image_file')
            ->setFormType(DropzoneType::class)
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('required', false)
            ->setHelp('admin.help.recommendation_image_file')
            ->setFormTypeOption('constraints', [
                new Image(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp']),
            ])
            ->setFormTypeOption('attr', [
                'accept' => 'image/jpeg,image/png,image/webp',
                'data-controller' => 'exclusive-resource-fields',
                'data-exclusive-resource-fields-peer-selector-value' => '[name$="[imageUrl]"]',
            ])
            ->onlyOnForms();
        yield BooleanField::new('active', 'admin.field.active');
        yield DateTimeField::new('updatedAt', 'admin.field.updated_at')->hideOnForm();
        yield DateTimeField::new('createdAt', 'admin.field.created_at')->onlyOnDetail();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof SessionRecommendationEntity) {
            $this->prepareEntity($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof SessionRecommendationEntity) {
            $this->prepareEntity($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function prepareEntity(SessionRecommendationEntity $sessionRecommendationEntity): void
    {
        $imageFileUpload = $this->extractUploadedFile('imageFileUpload');

        if ($imageFileUpload instanceof UploadedFile) {
            $sessionRecommendationEntity->setImageUrl($this->sessionRecommendationImageStorage->store($imageFileUpload));
        }

        $sessionRecommendationEntity
            ->setTitle(trim($sessionRecommendationEntity->getTitle()))
            ->setText(trim($sessionRecommendationEntity->getText()))
            ->setNotes($this->normalizeNullableString($sessionRecommendationEntity->getNotes()))
            ->setPrimaryUrl($this->normalizeNullableString($sessionRecommendationEntity->getPrimaryUrl()))
            ->setSecondaryUrl($this->normalizeNullableString($sessionRecommendationEntity->getSecondaryUrl()))
            ->setImageUrl($this->normalizeNullableString($sessionRecommendationEntity->getImageUrl()))
            ->setUpdatedAt(new DateTimeImmutable());
    }

    private function extractUploadedFile(string $fieldName): ?UploadedFile
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return null;
        }

        return $this->findUploadedFile($request->files->all(), $fieldName);
    }

    /**
     * @param array<mixed> $payload
     */
    private function findUploadedFile(array $payload, string $fieldName): ?UploadedFile
    {
        $directMatch = $payload[$fieldName] ?? null;

        if ($directMatch instanceof UploadedFile) {
            return $directMatch;
        }

        foreach ($payload as $value) {
            if ($value instanceof UploadedFile) {
                continue;
            }

            if (!is_array($value)) {
                continue;
            }

            $uploadedFile = $this->findUploadedFile($value, $fieldName);

            if ($uploadedFile instanceof UploadedFile) {
                return $uploadedFile;
            }
        }

        return null;
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
        $label = str_starts_with($normalizedValue, 'uploads/') ? 'Ouvrir le visuel' : $normalizedValue;

        return sprintf('<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', htmlspecialchars($href, ENT_QUOTES), htmlspecialchars($label, ENT_QUOTES));
    }
}
