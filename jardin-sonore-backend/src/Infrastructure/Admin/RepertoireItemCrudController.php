<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Domain\Model\Session\RepertoireBlockKind;
use App\Domain\Model\Session\RepertoireItemType;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use App\Infrastructure\Admin\Form\RepertoireContentBlockType;
use App\Infrastructure\Doctrine\Entity\RepertoireItemEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<RepertoireItemEntity>
 */
final class RepertoireItemCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MediaResourceRepositoryInterface $mediaResourceRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return RepertoireItemEntity::class;
    }

    public function createEntity(string $entityFqcn): RepertoireItemEntity
    {
        $repertoireItemEntity = new RepertoireItemEntity();
        $repertoireItemEntity->setContentBlocks([$this->createEmptyBlock()]);

        return $repertoireItemEntity;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('session_catalog.repertoire_item.singular')
            ->setEntityLabelInPlural('session_catalog.repertoire_item.plural')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.repertoire_item.page.index')
            ->setPageTitle(Crud::PAGE_NEW, 'admin.repertoire_item.page.new')
            ->setPageTitle(Crud::PAGE_EDIT, 'admin.repertoire_item.page.edit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'admin.repertoire_item.page.detail')
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->showEntityActionsInlined()
            ->setSearchFields(['title', 'source', 'body', 'notes']);
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
                ->setChoices($this->repertoireTypeChoices())
                ->setFormTypeOption('value_type_options.translation_domain', 'sessions'))
            ->add(BooleanFilter::new('active', 'admin.field.active'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'admin.field.id')->hideOnForm();
        yield TextField::new('uuid', 'admin.field.uuid')->onlyOnDetail();
        yield ChoiceField::new('type', 'admin.field.type')
            ->setChoices($this->repertoireTypeChoices())
            ->setFormTypeOption('choice_translation_domain', 'sessions')
            ->formatValue(fn (mixed $value): string => $this->translateRepertoireType($value));
        yield TextField::new('title', 'admin.field.title');
        yield TextField::new('source', 'admin.field.source')
            ->setFormTypeOption('required', false);
        yield CollectionField::new('contentBlocks', 'admin.field.content_blocks')
            ->setEntryType(RepertoireContentBlockType::class)
            ->setEntryIsComplex()
            ->setColumns('col-md-12 col-xxl-12')
            ->setFormTypeOption('by_reference', false)
            ->setFormTypeOption('delete_empty', static fn (mixed $contentBlock): bool => self::isEmptyBlock($contentBlock))
            ->setFormTypeOption('prototype_data', $this->createEmptyBlock())
            ->setFormTypeOption('entry_options.empty_data', fn (): array => $this->createEmptyBlock())
            ->allowAdd()
            ->allowDelete()
            ->onlyOnForms();
        yield TextareaField::new('body', 'admin.field.body')->hideOnForm();
        yield TextareaField::new('notes', 'admin.field.notes')
            ->setFormTypeOption('required', false)
            ->hideOnIndex();
        yield ChoiceField::new('linkedMediaUuids', 'admin.field.linked_media')
            ->setChoices($this->linkedMediaChoices())
            ->allowMultipleChoices()
            ->renderExpanded(false)
            ->setFormTypeOption('required', false)
            ->onlyOnForms();
        yield IntegerField::new('linkedMediaCount', 'admin.field.linked_media_count')
            ->onlyOnIndex();
        yield ArrayField::new('linkedMediaUuids', 'admin.field.linked_media')
            ->onlyOnDetail();
        yield BooleanField::new('active', 'admin.field.active');
        yield DateTimeField::new('updatedAt', 'admin.field.updated_at')->hideOnForm();
        yield DateTimeField::new('createdAt', 'admin.field.created_at')->onlyOnDetail();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof RepertoireItemEntity) {
            $this->prepareEntity($entityInstance);
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof RepertoireItemEntity) {
            $this->prepareEntity($entityInstance);
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * @return array<string, RepertoireItemType>
     */
    private function repertoireTypeChoices(): array
    {
        $choices = [];

        foreach (RepertoireItemType::cases() as $repertoireItemType) {
            $choices[$repertoireItemType->translationKey()] = $repertoireItemType;
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    private function linkedMediaChoices(): array
    {
        $choices = [];

        foreach ($this->mediaResourceRepository->search(activeOnly: true) as $mediaResource) {
            $choices[$mediaResource->getTitle()] = $mediaResource->getUuid()->toRfc4122();
        }

        return $choices;
    }

    private function translateRepertoireType(mixed $value): string
    {
        if ($value instanceof RepertoireItemType) {
            return $this->translator->trans($value->translationKey(), [], 'sessions');
        }

        if (is_string($value) && '' !== $value) {
            return $this->translator->trans(RepertoireItemType::from($value)->translationKey(), [], 'sessions');
        }

        return '';
    }

    private function prepareEntity(RepertoireItemEntity $repertoireItemEntity): void
    {
        $normalizedBlocks = array_values(array_filter(array_map(
            fn (mixed $contentBlock): ?array => $this->normalizeBlock($contentBlock),
            $repertoireItemEntity->getContentBlocks(),
        )));

        if ([] === $normalizedBlocks) {
            $normalizedBlocks[] = $this->createEmptyBlock();
        }

        $repertoireItemEntity
            ->setTitle(trim($repertoireItemEntity->getTitle()))
            ->setSource($this->normalizeNullableString($repertoireItemEntity->getSource()))
            ->setNotes($this->normalizeNullableString($repertoireItemEntity->getNotes()))
            ->setContentBlocks($normalizedBlocks)
            ->setBody($this->buildBodyFromContentBlocks($normalizedBlocks))
            ->setUpdatedAt(new DateTimeImmutable());
    }

    /**
     * @return array{kind: string, text?: string, gesture?: string}|null
     */
    private function normalizeBlock(mixed $contentBlock): ?array
    {
        if (!is_array($contentBlock)) {
            return null;
        }

        $kind = (string) ($contentBlock['kind'] ?? RepertoireBlockKind::LINE->value);
        $text = $this->normalizeNullableString($contentBlock['text'] ?? null);
        $gesture = $this->normalizeNullableString($contentBlock['gesture'] ?? null);

        if (RepertoireBlockKind::LINE->value === $kind && null === $text) {
            return null;
        }

        if (RepertoireBlockKind::BREAK->value === $kind && null === $text && null === $gesture) {
            return ['kind' => RepertoireBlockKind::BREAK->value];
        }

        $normalizedBlock = ['kind' => $kind];

        if (null !== $text) {
            $normalizedBlock['text'] = $text;
        }

        if (null !== $gesture) {
            $normalizedBlock['gesture'] = $gesture;
        }

        return $normalizedBlock;
    }

    /**
     * @param list<array<string, mixed>> $contentBlocks
     */
    private function buildBodyFromContentBlocks(array $contentBlocks): string
    {
        $lines = [];

        foreach ($contentBlocks as $contentBlock) {
            $kind = (string) ($contentBlock['kind'] ?? RepertoireBlockKind::LINE->value);

            if (RepertoireBlockKind::LINE->value === $kind) {
                $lines[] = trim((string) ($contentBlock['text'] ?? ''));
                continue;
            }

            if (RepertoireBlockKind::BREAK->value === $kind) {
                $lines[] = '';
            }
        }

        while ([] !== $lines && '' === end($lines)) {
            array_pop($lines);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array{kind: string, text: null, gesture: null}
     */
    private function createEmptyBlock(): array
    {
        return ['kind' => RepertoireBlockKind::LINE->value, 'text' => null, 'gesture' => null];
    }

    private static function isEmptyBlock(mixed $contentBlock): bool
    {
        if (!is_array($contentBlock)) {
            return true;
        }

        $kind = (string) ($contentBlock['kind'] ?? RepertoireBlockKind::LINE->value);
        $text = trim((string) ($contentBlock['text'] ?? ''));
        $gesture = trim((string) ($contentBlock['gesture'] ?? ''));

        return '' === $text && '' === $gesture && RepertoireBlockKind::BREAK->value !== $kind;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmedValue = trim($value);

        return '' === $trimmedValue ? null : $trimmedValue;
    }
}
