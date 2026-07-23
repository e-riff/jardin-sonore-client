<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\MediaResourceType as MediaResourceFormType;
use App\Application\Form\Model\MediaResourceFormModel;
use App\Application\Session\CreateMediaResource;
use App\Application\Session\DeleteMediaResource;
use App\Application\Session\GetMediaResourceForEdit;
use App\Application\Session\GetRepertoireItemForEdit;
use App\Application\Session\SaveMediaResourceInput;
use App\Application\Session\UpdateMediaResource;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/sessions/media', name: 'media_resource_')]
final class MediaResourceCatalogController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('media_resource/index.html.twig', [
            'sessionQuery' => $request->query->getString('session'),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        CreateMediaResource $createMediaResource,
        GetRepertoireItemForEdit $getRepertoireItemForEdit,
        RepertoireItemRepositoryInterface $repertoireItemRepository,
    ): Response {
        $repertoireItem = $this->resolveRepertoireItem($request, $getRepertoireItemForEdit);
        $formModel = new MediaResourceFormModel();
        $form = $this->createForm(MediaResourceFormType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $mediaResource = $createMediaResource($this->createInput($formModel));
            $this->addFlash('success', ['message' => 'sessions.media.flash.created', 'domain' => 'sessions']);

            if (null !== $repertoireItem) {
                $repertoireItemDomain = $repertoireItemRepository->findByUuid($repertoireItem->uuid);

                if (null === $repertoireItemDomain) {
                    throw $this->createNotFoundException();
                }

                $linkedMediaUuids = $repertoireItemDomain->getLinkedMediaUuids();
                $mediaUuid = $mediaResource->getUuid()->toRfc4122();

                if (!in_array($mediaUuid, $linkedMediaUuids, true)) {
                    $linkedMediaUuids[] = $mediaUuid;
                    $repertoireItemDomain->updateContent(
                        title: $repertoireItemDomain->getTitle(),
                        source: $repertoireItemDomain->getSource(),
                        body: $repertoireItemDomain->getBody(),
                        contentBlocks: $repertoireItemDomain->getContentBlocks(),
                        notes: $repertoireItemDomain->getNotes(),
                        linkedMediaUuids: $linkedMediaUuids,
                    );
                    $repertoireItemRepository->save($repertoireItemDomain);
                }

                $redirectUrl = $this->generateUrl('repertoire_edit', ['uuid' => $repertoireItem->uuid->toRfc4122()]);

                $response = $this->redirect($redirectUrl, Response::HTTP_SEE_OTHER);
                $response->headers->set('Turbo-Location', $redirectUrl);

                return $response;
            }

            return $this->redirectToRoute('media_resource_index', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render(null !== $repertoireItem ? 'media_resource/new_frame.html.twig' : 'media_resource/form.html.twig', [
            'form' => $form->createView(),
            'hasErrors' => $form->isSubmitted() && !$form->isValid(),
            'item' => null,
            'repertoireItem' => $repertoireItem,
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{uuid}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        string $uuid,
        Request $request,
        GetMediaResourceForEdit $getMediaResourceForEdit,
        UpdateMediaResource $updateMediaResource,
    ): Response {
        $itemView = Uuid::isValid($uuid) ? $getMediaResourceForEdit(Uuid::fromString($uuid)) : null;

        if (null === $itemView) {
            throw $this->createNotFoundException();
        }

        $formModel = MediaResourceFormModel::fromView($itemView);
        $form = $this->createForm(MediaResourceFormType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updateMediaResource($itemView->uuid, $this->createInput($formModel));
            $this->addFlash('success', ['message' => 'sessions.media.flash.updated', 'domain' => 'sessions']);

            return $this->redirectToRoute('media_resource_index', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('media_resource/form.html.twig', [
            'form' => $form->createView(),
            'hasErrors' => $form->isSubmitted() && !$form->isValid(),
            'item' => $itemView,
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{uuid}/delete', name: 'delete', methods: ['POST'])]
    public function delete(string $uuid, Request $request, DeleteMediaResource $deleteMediaResource): Response
    {
        if (!Uuid::isValid($uuid)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('media_resource_delete_' . $uuid, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $deleteMediaResource(Uuid::fromString($uuid));
            $this->addFlash('success', ['message' => 'sessions.media.flash.deleted', 'domain' => 'sessions']);
        } catch (InvalidArgumentException) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('media_resource_index', status: Response::HTTP_SEE_OTHER);
    }

    private function createInput(MediaResourceFormModel $formModel): SaveMediaResourceInput
    {
        $primaryUrl = $this->preserveExistingValue($formModel->primaryUrl, $formModel->existingPrimaryUrl, null === $formModel->primaryFile);
        $imageUrl = $this->preserveExistingValue($formModel->imageUrl, $formModel->existingImageUrl, null === $formModel->imageFile);

        return new SaveMediaResourceInput(
            type: $formModel->type,
            title: $formModel->title,
            primaryUrl: $primaryUrl,
            primaryFile: $formModel->primaryFile,
            source: $formModel->source,
            description: $formModel->description,
            secondaryUrl: $formModel->secondaryUrl,
            imageUrl: $imageUrl,
            imageFile: $formModel->imageFile,
            themeUuids: $formModel->themeUuids,
            active: $formModel->active,
        );
    }

    private function resolveRepertoireItem(
        Request $request,
        GetRepertoireItemForEdit $getRepertoireItemForEdit,
    ): ?\App\Application\Session\RepertoireItemView {
        $repertoireUuid = $request->query->getString('repertoire');

        if ('' === $repertoireUuid) {
            return null;
        }

        if (!Uuid::isValid($repertoireUuid)) {
            throw $this->createNotFoundException();
        }

        $repertoireItem = $getRepertoireItemForEdit(Uuid::fromString($repertoireUuid));

        if (null === $repertoireItem) {
            throw $this->createNotFoundException();
        }

        return $repertoireItem;
    }

    private function preserveExistingValue(?string $submittedValue, ?string $existingValue, bool $keepExistingValue): ?string
    {
        $normalizedSubmittedValue = $this->normalizeNullableString($submittedValue);

        if (null !== $normalizedSubmittedValue || !$keepExistingValue) {
            return $normalizedSubmittedValue;
        }

        return $this->normalizeNullableString($existingValue);
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalizedValue = trim($value);

        return '' === $normalizedValue ? null : $normalizedValue;
    }
}
