<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\ContentCatalog\DeleteTechnicalTaxonomyItem;
use App\Application\ContentCatalog\SaveTechnicalTaxonomyItem;
use App\Application\Form\Model\TechnicalTaxonomyFormModel;
use App\Application\Form\TechnicalTaxonomyType;
use App\Domain\Model\ContentCatalog\InstrumentTag;
use App\Domain\Model\ContentCatalog\Theme;
use App\Domain\Repository\InstrumentTagRepositoryInterface;
use App\Domain\Repository\ThemeRepositoryInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/technical/taxonomies', name: 'technical_taxonomy_')]
final class TechnicalTaxonomyController extends AbstractController
{
    public function __construct(
        private readonly ThemeRepositoryInterface $themeRepository,
        private readonly InstrumentTagRepositoryInterface $instrumentTagRepository,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    #[Route('/{catalog}', name: 'index', requirements: ['catalog' => 'themes|instrument-tags'], methods: ['GET'])]
    public function index(string $catalog): Response
    {
        return $this->renderIndex($catalog);
    }

    #[Route('/{catalog}/new', name: 'new', requirements: ['catalog' => 'themes|instrument-tags'], methods: ['POST'])]
    public function create(
        string $catalog,
        Request $request,
        SaveTechnicalTaxonomyItem $saveTechnicalTaxonomyItem,
    ): Response {
        $form = $this->createTaxonomyForm($catalog, null);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formModel = $form->getData();
            if (!$formModel instanceof TechnicalTaxonomyFormModel) {
                throw new InvalidArgumentException('Taxonomy form data is invalid.');
            }

            try {
                $saveTechnicalTaxonomyItem($catalog, null, $formModel->label, $formModel->color);

                return $this->redirectToRoute('technical_taxonomy_index', ['catalog' => $catalog], Response::HTTP_SEE_OTHER);
            } catch (InvalidArgumentException $exception) {
                $form->get('label')->addError(new FormError($this->taxonomyErrorMessage($exception)));
            }
        }

        return $this->renderIndex($catalog, 'new', $form);
    }

    #[Route('/{catalog}/{uuid}/edit', name: 'edit', requirements: ['catalog' => 'themes|instrument-tags', 'uuid' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    public function update(
        string $catalog,
        string $uuid,
        Request $request,
        SaveTechnicalTaxonomyItem $saveTechnicalTaxonomyItem,
    ): Response {
        $uuidObject = Uuid::isValid($uuid) ? Uuid::fromString($uuid) : null;
        $item = null === $uuidObject ? null : $this->findItem($catalog, $uuidObject);
        if (null === $item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createTaxonomyForm($catalog, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formModel = $form->getData();
            if (!$formModel instanceof TechnicalTaxonomyFormModel) {
                throw new InvalidArgumentException('Taxonomy form data is invalid.');
            }

            try {
                $saveTechnicalTaxonomyItem($catalog, $uuidObject, $formModel->label, $formModel->color);

                return $this->redirectToRoute('technical_taxonomy_index', ['catalog' => $catalog], Response::HTTP_SEE_OTHER);
            } catch (InvalidArgumentException $exception) {
                $form->get('label')->addError(new FormError($this->taxonomyErrorMessage($exception)));
            }
        }

        return $this->renderIndex($catalog, $uuidObject->toRfc4122(), $form);
    }

    #[Route('/{catalog}/{uuid}/delete', name: 'delete', requirements: ['catalog' => 'themes|instrument-tags', 'uuid' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    public function delete(
        string $catalog,
        string $uuid,
        Request $request,
        DeleteTechnicalTaxonomyItem $deleteTechnicalTaxonomyItem,
    ): Response {
        $uuidObject = Uuid::isValid($uuid) ? Uuid::fromString($uuid) : null;
        if (null === $uuidObject || !$this->isCsrfTokenValid("technical_taxonomy_{$catalog}_{$uuid}", $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $deleteTechnicalTaxonomyItem($catalog, $uuidObject);
        } catch (InvalidArgumentException) {
            throw $this->createNotFoundException();
        }

        $this->addFlash('success', 'technical.taxonomy.flash.deleted');

        return $this->redirectToRoute('technical_taxonomy_index', ['catalog' => $catalog], Response::HTTP_SEE_OTHER);
    }

    /** @param FormInterface<TechnicalTaxonomyFormModel>|null $failedForm */
    private function renderIndex(string $catalog, ?string $openDialog = null, ?FormInterface $failedForm = null): Response
    {
        $isThemeCatalog = 'themes' === $catalog;
        $items = $isThemeCatalog ? $this->themeRepository->findAllOrderedByLabel() : $this->instrumentTagRepository->findAllOrderedByLabel();
        $usageCounts = $isThemeCatalog ? $this->themeRepository->usageCountsByUuid() : $this->instrumentTagRepository->usageCountsByUuid();
        $forms = [];

        foreach ($items as $item) {
            $uuid = $item->getUuid()->toRfc4122();
            $forms[$uuid] = $this->createTaxonomyForm($catalog, $item, $failedForm && $uuid === $openDialog ? $failedForm : null)->createView();
        }

        $newForm = $this->createTaxonomyForm($catalog, null, 'new' === $openDialog ? $failedForm : null)->createView();

        return $this->render('technical/taxonomy/index.html.twig', [
            'catalog' => $catalog,
            'items' => $items,
            'usageCounts' => $usageCounts,
            'formsByUuid' => $forms,
            'newForm' => $newForm,
            'openDialog' => $openDialog,
        ], null !== $failedForm ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    /**
     * @param FormInterface<TechnicalTaxonomyFormModel>|null $existingForm
     *
     * @return FormInterface<TechnicalTaxonomyFormModel>
     */
    private function createTaxonomyForm(string $catalog, InstrumentTag|Theme|null $item, ?FormInterface $existingForm = null): FormInterface
    {
        if (null !== $existingForm) {
            return $existingForm;
        }

        $formModel = new TechnicalTaxonomyFormModel();
        if ($item instanceof Theme || $item instanceof InstrumentTag) {
            $formModel->label = $item->getLabel();
            $formModel->color = $item->getColor();
        }

        $formName = null === $item ? "technical_taxonomy_{$catalog}_new" : 'technical_taxonomy_' . str_replace('-', '_', $item->getUuid()->toRfc4122());
        $action = null === $item
            ? $this->generateUrl('technical_taxonomy_new', ['catalog' => $catalog])
            : $this->generateUrl('technical_taxonomy_edit', ['catalog' => $catalog, 'uuid' => $item->getUuid()->toRfc4122()]);

        return $this->formFactory->createNamed($formName, TechnicalTaxonomyType::class, $formModel, [
            'action' => $action,
            'method' => 'POST',
            'translation_domain' => 'technical',
        ]);
    }

    private function findItem(string $catalog, Uuid $uuid): InstrumentTag|Theme|null
    {
        return match ($catalog) {
            'themes' => $this->themeRepository->findByUuid($uuid),
            'instrument-tags' => $this->instrumentTagRepository->findByUuid($uuid),
            default => null,
        };
    }

    private function taxonomyErrorMessage(InvalidArgumentException $exception): string
    {
        return match ($exception->getMessage()) {
            'A theme with this label already exists.' => 'technical.taxonomy.error.theme_duplicate',
            'An instrument tag with this label already exists.' => 'technical.taxonomy.error.instrument_tag_duplicate',
            default => 'technical.taxonomy.error.invalid',
        };
    }
}
