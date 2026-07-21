<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\ContentCatalog\CreateInstrument;
use App\Application\ContentCatalog\GetInstrumentForEdit;
use App\Application\ContentCatalog\SaveInstrumentInput;
use App\Application\ContentCatalog\UpdateInstrument;
use App\Application\Form\InstrumentType;
use App\Application\Form\Model\InstrumentFormModel;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Uid\Uuid;

#[Route('/instruments', name: 'instrument_')]
final class InstrumentController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('instrument/index.html.twig');
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        CreateInstrument $createInstrument,
        TranslatorInterface $translator,
    ): Response {
        $formModel = new InstrumentFormModel();
        $form = $this->createForm(InstrumentType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $instrument = $createInstrument($this->createInput($formModel));
            } catch (InvalidArgumentException $exception) {
                if (!$this->isUnknownInstrumentTagException($exception)) {
                    throw $exception;
                }

                $form->get('tagUuids')->addError(new FormError($translator->trans(
                    'catalog.instrument.form.tags_unavailable',
                    domain: 'catalog',
                )));

                return $this->render(
                    'instrument/new.html.twig',
                    [
                        'form' => $form->createView(),
                    ],
                    new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY),
                );
            }

            $this->addFlash('success', [
                'message' => 'catalog.instrument.flash.created',
                'domain' => 'catalog',
            ]);

            return $this->redirectToRoute('instrument_edit', [
                'uuid' => $instrument->getUuid()->toRfc4122(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render(
            'instrument/new.html.twig',
            [
                'form' => $form->createView(),
            ],
            $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null,
        );
    }

    #[Route('/{uuid}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        string $uuid,
        Request $request,
        GetInstrumentForEdit $getInstrumentForEdit,
        UpdateInstrument $updateInstrument,
        TranslatorInterface $translator,
    ): Response {
        if (!Uuid::isValid($uuid)) {
            throw $this->createNotFoundException();
        }

        $instrumentEditView = $getInstrumentForEdit(Uuid::fromString($uuid));

        if (null === $instrumentEditView) {
            throw $this->createNotFoundException();
        }

        $formModel = InstrumentFormModel::fromEditView($instrumentEditView);
        $form = $this->createForm(InstrumentType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $updateInstrument($instrumentEditView->uuid, $this->createInput($formModel));
            } catch (InvalidArgumentException $exception) {
                if (!$this->isUnknownInstrumentTagException($exception)) {
                    throw $exception;
                }

                $form->get('tagUuids')->addError(new FormError($translator->trans(
                    'catalog.instrument.form.tags_unavailable',
                    domain: 'catalog',
                )));

                return $this->render(
                    'instrument/edit.html.twig',
                    [
                        'form' => $form->createView(),
                        'instrument' => $instrumentEditView,
                    ],
                    new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY),
                );
            }

            $this->addFlash('success', [
                'message' => 'catalog.instrument.flash.updated',
                'domain' => 'catalog',
            ]);

            return $this->redirectToRoute('instrument_edit', [
                'uuid' => $instrumentEditView->uuid->toRfc4122(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render(
            'instrument/edit.html.twig',
            [
                'form' => $form->createView(),
                'instrument' => $instrumentEditView,
            ],
            $form->isSubmitted() ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null,
        );
    }

    private function createInput(InstrumentFormModel $formModel): SaveInstrumentInput
    {
        return new SaveInstrumentInput(
            name: $formModel->name,
            tuning: $formModel->tuning,
            quantity: $formModel->quantity,
            notes: $formModel->notes,
            tagUuids: $formModel->tagUuids,
            active: $formModel->active,
        );
    }

    private function isUnknownInstrumentTagException(InvalidArgumentException $exception): bool
    {
        return in_array($exception->getMessage(), [
            'One or more selected instrument tags could not be found.',
            'One or more selected instrument tags are no longer available.',
        ], true)
            || str_starts_with($exception->getMessage(), 'Unknown instrument tag "');
    }
}
