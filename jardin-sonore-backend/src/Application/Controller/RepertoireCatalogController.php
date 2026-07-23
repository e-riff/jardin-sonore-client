<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\Model\RepertoireItemFormModel;
use App\Application\Form\RepertoireItemType as RepertoireItemFormType;
use App\Application\Session\CreateRepertoireItem;
use App\Application\Session\DeleteRepertoireItem;
use App\Application\Session\GetMediaResourceForEdit;
use App\Application\Session\GetRepertoireItemForEdit;
use App\Application\Session\RepertoireBlockTextParser;
use App\Application\Session\SaveRepertoireBlockInput;
use App\Application\Session\SaveRepertoireItemInput;
use App\Application\Session\UpdateRepertoireItem;
use App\Domain\Model\Session\RepertoireBlockKind;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/sessions/repertoire', name: 'repertoire_')]
final class RepertoireCatalogController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('repertoire/index.html.twig', [
            'sessionQuery' => $request->query->getString('session'),
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, CreateRepertoireItem $createRepertoireItem): Response
    {
        $formModel = new RepertoireItemFormModel();
        $form = $this->createForm(RepertoireItemFormType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $createRepertoireItem($this->createInput($formModel));
            $this->addFlash('success', ['message' => 'sessions.repertoire.flash.created', 'domain' => 'sessions']);

            return $this->redirectToRoute('repertoire_index', ['type' => $formModel->type->value], Response::HTTP_SEE_OTHER);
        }

        return $this->render('repertoire/form.html.twig', [
            'form' => $form->createView(),
            'hasErrors' => $form->isSubmitted() && !$form->isValid(),
            'item' => null,
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{uuid}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        string $uuid,
        Request $request,
        GetRepertoireItemForEdit $getRepertoireItemForEdit,
        GetMediaResourceForEdit $getMediaResourceForEdit,
        UpdateRepertoireItem $updateRepertoireItem,
    ): Response {
        $itemView = Uuid::isValid($uuid) ? $getRepertoireItemForEdit(Uuid::fromString($uuid)) : null;

        if (null === $itemView) {
            throw $this->createNotFoundException();
        }

        $formModel = RepertoireItemFormModel::fromView($itemView);
        $mediaUuidToLink = $request->query->getString('addMedia');

        if ('' !== $mediaUuidToLink && Uuid::isValid($mediaUuidToLink) && !in_array($mediaUuidToLink, $formModel->linkedMediaUuids, true)) {
            $formModel->linkedMediaUuids[] = $mediaUuidToLink;
        }

        $form = $this->createForm(RepertoireItemFormType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updateRepertoireItem($itemView->uuid, $this->createInput($formModel));
            $this->addFlash('success', ['message' => 'sessions.repertoire.flash.updated', 'domain' => 'sessions']);

            return $this->redirectToRoute('repertoire_index', ['type' => $formModel->type->value], Response::HTTP_SEE_OTHER);
        }

        return $this->render('repertoire/form.html.twig', [
            'form' => $form->createView(),
            'hasErrors' => $form->isSubmitted() && !$form->isValid(),
            'item' => $itemView,
            'linkedMediaItems' => $this->resolveLinkedMediaItems($formModel->linkedMediaUuids, $getMediaResourceForEdit),
        ], $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/{uuid}/delete', name: 'delete', methods: ['POST'])]
    public function delete(string $uuid, Request $request, DeleteRepertoireItem $deleteRepertoireItem): Response
    {
        if (!Uuid::isValid($uuid)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('repertoire_delete_' . $uuid, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $deleteRepertoireItem(Uuid::fromString($uuid));
            $this->addFlash('success', ['message' => 'sessions.repertoire.flash.deleted', 'domain' => 'sessions']);
        } catch (InvalidArgumentException) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('repertoire_index', status: Response::HTTP_SEE_OTHER);
    }

    private function createInput(
        RepertoireItemFormModel $formModel,
        RepertoireBlockTextParser $repertoireBlockTextParser = new RepertoireBlockTextParser(),
    ): SaveRepertoireItemInput {
        $contentBlocks = [];

        foreach ($formModel->contentBlocks as $contentBlock) {
            if (!$contentBlock instanceof \App\Application\Form\Model\RepertoireBlockFormModel) {
                continue;
            }

            $contentBlocks[] = new SaveRepertoireBlockInput(
                kind: RepertoireBlockKind::SECTION === $contentBlock->kind ? RepertoireBlockKind::BREAK : $contentBlock->kind,
                text: $contentBlock->text,
                gesture: $contentBlock->gesture,
            );
        }

        if ([] === $contentBlocks && null !== $formModel->importText && '' !== trim($formModel->importText)) {
            $contentBlocks = $repertoireBlockTextParser->parse($formModel->importText);
        }

        return new SaveRepertoireItemInput(
            type: $formModel->type,
            title: $formModel->title,
            source: $formModel->source,
            body: $this->buildBodyFromContentBlocks($contentBlocks),
            contentBlocks: $contentBlocks,
            notes: $formModel->notes,
            linkedMediaUuids: $formModel->linkedMediaUuids,
            themeUuids: $formModel->themeUuids,
            active: $formModel->active,
        );
    }

    /**
     * @param list<SaveRepertoireBlockInput> $contentBlocks
     */
    private function buildBodyFromContentBlocks(array $contentBlocks): string
    {
        $lines = [];

        foreach ($contentBlocks as $contentBlock) {
            if (RepertoireBlockKind::LINE === $contentBlock->kind) {
                $lines[] = trim((string) $contentBlock->text);
                continue;
            }

            if (RepertoireBlockKind::BREAK === $contentBlock->kind) {
                $lines[] = '';
            }
        }

        while ([] !== $lines && '' === end($lines)) {
            array_pop($lines);
        }

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $linkedMediaUuids
     *
     * @return list<\App\Application\Session\MediaResourceView>
     */
    private function resolveLinkedMediaItems(array $linkedMediaUuids, GetMediaResourceForEdit $getMediaResourceForEdit): array
    {
        $linkedMediaItems = [];

        foreach ($linkedMediaUuids as $linkedMediaUuid) {
            if (!Uuid::isValid($linkedMediaUuid)) {
                continue;
            }

            $mediaResourceView = $getMediaResourceForEdit(Uuid::fromString($linkedMediaUuid));

            if (null !== $mediaResourceView) {
                $linkedMediaItems[] = $mediaResourceView;
            }
        }

        return $linkedMediaItems;
    }
}
