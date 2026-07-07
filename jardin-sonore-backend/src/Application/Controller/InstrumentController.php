<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\ContentCatalog\CreateInstrument;
use App\Application\ContentCatalog\GetInstrument;
use App\Application\ContentCatalog\SaveInstrumentInput;
use App\Application\ContentCatalog\UpdateInstrument;
use App\Application\Form\InstrumentType;
use App\Application\Form\Model\InstrumentFormModel;
use App\Domain\Model\ContentCatalog\Instrument;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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
    ): Response {
        $formModel = new InstrumentFormModel();
        $form = $this->createForm(InstrumentType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $instrument = $createInstrument($this->createInput($formModel));
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
        GetInstrument $getInstrument,
        UpdateInstrument $updateInstrument,
    ): Response {
        if (!Uuid::isValid($uuid)) {
            throw $this->createNotFoundException();
        }

        $instrument = $getInstrument(Uuid::fromString($uuid));

        if (!$instrument instanceof Instrument) {
            throw $this->createNotFoundException();
        }

        $formModel = InstrumentFormModel::fromInstrument($instrument);
        $form = $this->createForm(InstrumentType::class, $formModel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $updateInstrument($instrument, $this->createInput($formModel));
            $this->addFlash('success', [
                'message' => 'catalog.instrument.flash.updated',
                'domain' => 'catalog',
            ]);

            return $this->redirectToRoute('instrument_edit', [
                'uuid' => $instrument->getUuid()->toRfc4122(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render(
            'instrument/edit.html.twig',
            [
                'form' => $form->createView(),
                'instrument' => $instrument,
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
}
