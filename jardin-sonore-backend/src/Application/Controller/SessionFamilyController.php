<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\Model\SessionFamilyFormModel;
use App\Application\Form\SessionFamilyType;
use App\Application\Session\BuildFamilyElementGroups;
use App\Application\Session\ComposeFamilySession;
use App\Application\Session\SessionSummaryView;
use App\Domain\Model\Session\SessionFamily;
use App\Domain\Repository\SessionFamilyRepositoryInterface;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use App\Domain\Repository\ThemeRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/session-families', name: 'session_family_')]
final class SessionFamilyController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SessionFamilyRepositoryInterface $families): Response
    {
        $query = trim($request->query->getString('query'));
        $results = $families->listFamilies();
        if ('' !== $query) {
            $results = array_values(array_filter($results, static function (SessionFamily $family) use ($query): bool {
                if (false !== mb_stripos($family->name, $query)) {
                    return true;
                }
                foreach ($family->themes as $theme) {
                    if (false !== mb_stripos($theme->getLabel(), $query)) {
                        return true;
                    }
                }

                return false;
            }));
        }

        $direction = 'desc' === $request->query->getString('direction') ? 'desc' : 'asc';
        if ('desc' === $direction) {
            $results = array_reverse($results);
        }

        return $this->render('session_family/index.html.twig', ['families' => $results, 'query' => $query, 'direction' => $direction]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    #[Route('/{uuid}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SessionFamilyRepositoryInterface $families, SessionSummaryRepositoryInterface $sessionRepository, ThemeRepositoryInterface $themes, ?string $uuid = null): Response
    {
        $sourceUuid = null === $uuid ? $request->query->getString('session') : '';
        $source = '' !== $sourceUuid && Uuid::isValid($sourceUuid) ? $sessionRepository->findByUuid(Uuid::fromString($sourceUuid)) : null;
        if ('' !== $sourceUuid && null === $source) {
            throw $this->createNotFoundException();
        }
        $family = null;
        if (null !== $uuid) {
            $family = Uuid::isValid($uuid) ? $families->findByUuid(Uuid::fromString($uuid)) : null;
            if (null === $family) {
                throw $this->createNotFoundException();
            }
        }
        $model = new SessionFamilyFormModel();
        if (null !== $family) {
            $model->name = $family->name;
            $model->themeUuids = array_map(static fn ($theme): string => $theme->getUuid()->toRfc4122(), $family->themes);
        }
        $form = $this->createForm(SessionFamilyType::class, $model);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $selectedThemes = [];
            foreach ($model->themeUuids as $themeUuid) {
                $theme = Uuid::isValid($themeUuid) ? $themes->findByUuid(Uuid::fromString($themeUuid)) : null;
                if (null !== $theme) {
                    $selectedThemes[] = $theme;
                }
            }
            $family = new SessionFamily($model->name, $selectedThemes, $family?->uuid);
            $families->save($family);
            if (null !== $source) {
                $families->assignSession($source->getUuid(), $family->uuid);
            }

            return $this->redirectToRoute('session_family_show', ['uuid' => $family->uuid->toRfc4122()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('session_family/form.html.twig', ['form' => $form->createView(), 'family' => $family], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{uuid}', name: 'show', methods: ['GET'])]
    public function show(string $uuid, SessionFamilyRepositoryInterface $families, BuildFamilyElementGroups $buildGroups): Response
    {
        $family = Uuid::isValid($uuid) ? $families->findByUuid(Uuid::fromString($uuid)) : null;
        if (null === $family) {
            throw $this->createNotFoundException();
        }

        $sessions = $families->sessions($family->uuid);

        return $this->render('session_family/show.html.twig', [
            'family' => $family,
            'sessions' => array_map(SessionSummaryView::fromDomain(...), $sessions),
            'groups' => $buildGroups($sessions),
            'availableSessions' => $families->availableSessions(),
        ]);
    }

    #[Route('/{uuid}/attach', name: 'attach', methods: ['POST'])]
    public function attach(string $uuid, Request $request, SessionFamilyRepositoryInterface $families, SessionSummaryRepositoryInterface $sessionRepository): Response
    {
        if (!Uuid::isValid($uuid) || !$this->isCsrfTokenValid('session_family_attach_' . $uuid, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $family = $families->findByUuid(Uuid::fromString($uuid));
        if (null === $family) {
            throw $this->createNotFoundException();
        }
        $sessions = [];
        foreach ($request->request->all('sessions') as $sessionUuid) {
            $session = is_string($sessionUuid) && Uuid::isValid($sessionUuid) ? $sessionRepository->findByUuid(Uuid::fromString($sessionUuid)) : null;
            if (null === $session || null !== $session->getFamilyUuid()) {
                return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $sessions[] = $session;
        }
        foreach ($sessions as $session) {
            $families->assignSession($session->getUuid(), $family->uuid);
        }

        return $this->redirectToRoute('session_family_show', ['uuid' => $uuid], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{uuid}/compose', name: 'compose', methods: ['GET', 'POST'])]
    public function compose(string $uuid, Request $request, SessionFamilyRepositoryInterface $families, BuildFamilyElementGroups $buildGroups, ComposeFamilySession $composer): Response
    {
        $family = Uuid::isValid($uuid) ? $families->findByUuid(Uuid::fromString($uuid)) : null;
        if (null === $family) {
            throw $this->createNotFoundException();
        }
        $error = false;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('session_family_compose_' . $uuid, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $compositionId = $request->request->getString('compositionId');
            if (!Uuid::isValid($compositionId)) {
                return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $existingUuid = $request->getSession()->get('family_composition_' . $compositionId);
            if (is_string($existingUuid) && Uuid::isValid($existingUuid)) {
                return $this->redirectToRoute('session_edit', ['uuid' => $existingUuid], Response::HTTP_SEE_OTHER);
            }
            $dateValue = $request->request->getString('date');
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
            $rawSelection = $request->request->all('selection');
            $instructions = $request->request->all('instructions');
            $selection = [];
            foreach ($rawSelection as $entry) {
                if (!is_string($entry) || 2 !== count($parts = explode(':', $entry))) {
                    $error = true;
                    break;
                }
                $selection[] = ['session' => $parts[0], 'sequence' => $parts[1], 'instructions' => isset($instructions[$entry])];
            }
            if (false === $date || $date->format('Y-m-d') !== $dateValue || 200 < count($selection)) {
                $error = true;
            }
            if (!$error) {
                try {
                    $session = $composer($family->uuid, $request->request->getString('title'), $date, $selection);
                    $request->getSession()->set('family_composition_' . $compositionId, $session->getUuid()->toRfc4122());

                    return $this->redirectToRoute('session_edit', ['uuid' => $session->getUuid()], Response::HTTP_SEE_OTHER);
                } catch (InvalidArgumentException) {
                    $error = true;
                }
            }
        }

        return $this->render('session_family/compose.html.twig', [
            'family' => $family,
            'groups' => $buildGroups($families->sessions($family->uuid)),
            'error' => $error,
            'compositionId' => Uuid::v4(),
            'selected' => $request->isMethod('POST') ? array_values(array_filter($request->request->all('selection'), is_string(...))) : [],
            'selectedInstructions' => $request->isMethod('POST') ? $request->request->all('instructions') : [],
        ], new Response(status: $error ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/assign/{uuid}', name: 'assign', methods: ['POST'])]
    public function assign(string $uuid, Request $request, SessionFamilyRepositoryInterface $families): Response
    {
        if (!Uuid::isValid($uuid) || !$this->isCsrfTokenValid('session_family_assign_' . $uuid, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $familyUuid = $request->request->getString('family');
        if ('' !== $familyUuid && (!Uuid::isValid($familyUuid) || null === $families->findByUuid(Uuid::fromString($familyUuid)))) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $families->assignSession(Uuid::fromString($uuid), '' === $familyUuid ? null : Uuid::fromString($familyUuid));
        } catch (LogicException) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('session_edit', ['uuid' => $uuid], Response::HTTP_SEE_OTHER);
    }
}
