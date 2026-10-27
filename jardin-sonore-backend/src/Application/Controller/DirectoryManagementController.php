<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\DirectoryContactDetailsFormType;
use App\Application\Form\DirectoryOrganizationDetailsFormType;
use App\Application\Form\DirectoryOrganizationFormType;
use App\Application\Form\DirectoryPersonDetailsFormType;
use App\Application\Form\DirectoryPersonFormType;
use App\Infrastructure\Directory\SharedContactLinkResolver;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PersonEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/directory', name: 'internal_directory_')]
final class DirectoryManagementController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SharedContactLinkResolver $sharedContactLinkResolver,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('internal/directory/index.html.twig');
    }

    #[Route('/organization/new', name: 'organization_new', methods: ['GET', 'POST'])]
    #[Route('/organization/{id}/edit', name: 'organization_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function organizationForm(Request $request, ?int $id = null): Response
    {
        $organization = null === $id ? new OrganizationEntity() : ($this->entityManager->find(OrganizationEntity::class, $id) ?? throw $this->createNotFoundException());
        $form = $this->createForm(DirectoryOrganizationFormType::class, $organization)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $contactDetails = $organization->getContactDetails();
            if (null !== $contactDetails) {
                $this->sharedContactLinkResolver->resolveContactDetails($contactDetails);
            }
            $this->entityManager->persist($organization);
            $this->entityManager->flush();
            $this->addFlash('success', ['message' => null === $id ? 'technical.directory.flash.created' : 'technical.directory.flash.updated', 'domain' => 'technical']);

            return $this->redirectToRoute('internal_directory_index', ['selectedId' => $organization->getId()]);
        }

        return $this->render('internal/directory/organization_form.html.twig', ['form' => $form, 'organization' => $organization, 'isNew' => null === $id]);
    }

    #[Route('/organization/{id}/edit/{section}', name: 'organization_section_edit', requirements: ['id' => '\\d+', 'section' => 'details|contacts|addresses'], methods: ['GET', 'POST'])]
    public function organizationSectionForm(Request $request, int $id, string $section): Response
    {
        $organization = $this->entityManager->find(OrganizationEntity::class, $id) ?? throw $this->createNotFoundException();
        if (!$organization instanceof OrganizationEntity) {
            throw $this->createNotFoundException();
        }

        if ('details' === $section) {
            $form = $this->createForm(DirectoryOrganizationDetailsFormType::class, $organization);
        } else {
            $groups = 'contacts' === $section ? ['email', 'phone'] : ['address'];
            $form = $this->createForm(DirectoryContactDetailsFormType::class, $organization->getContactDetails(), ['groups' => $groups]);
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ('details' !== $section) {
                $this->sharedContactLinkResolver->resolveContactDetails($organization->getContactDetails());
            }
            $this->entityManager->persist($organization);
            $this->entityManager->flush();
            $this->addFlash('success', ['message' => 'technical.directory.flash.updated', 'domain' => 'technical']);

            return $this->redirectToRoute('internal_directory_index', ['selectedId' => $organization->getId()]);
        }

        return $this->render('internal/directory/organization_section_form.html.twig', [
            'form' => $form,
            'organization' => $organization,
            'section' => $section,
        ]);
    }

    #[Route('/person/new', name: 'person_new', methods: ['GET', 'POST'])]
    #[Route('/person/{id}/edit', name: 'person_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function personForm(Request $request, ?int $id = null): Response
    {
        $person = null === $id ? new PersonEntity() : ($this->entityManager->find(PersonEntity::class, $id) ?? throw $this->createNotFoundException());
        if (null === $id && $request->query->has('organization')) {
            $organizationId = $request->query->getInt('organization');
            if (0 < $organizationId) {
                $person->setOrganization($this->entityManager->find(OrganizationEntity::class, $organizationId));
            }
        }
        $form = $this->createForm(DirectoryPersonFormType::class, $person)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $contactDetails = $person->getContactDetails();
            if (null !== $contactDetails) {
                $this->sharedContactLinkResolver->resolveContactDetails($contactDetails);
            }
            $this->entityManager->persist($person);
            $this->entityManager->flush();
            $this->addFlash('success', ['message' => null === $id ? 'technical.directory.flash.created' : 'technical.directory.flash.updated', 'domain' => 'technical']);

            $returnOrganizationId = $request->query->getInt('returnOrganization');
            if (0 < $returnOrganizationId && $this->entityManager->find(OrganizationEntity::class, $returnOrganizationId) instanceof OrganizationEntity) {
                return $this->redirectToRoute('internal_directory_index', ['section' => 'organizations', 'selectedId' => $returnOrganizationId]);
            }

            $organizationId = $request->query->getInt('organization');
            if (0 < $organizationId) {
                return $this->redirectToRoute('internal_directory_index', ['section' => 'organizations', 'selectedId' => $organizationId]);
            }

            return $this->redirectToRoute('internal_directory_index', ['section' => 'people', 'selectedId' => $person->getId()]);
        }

        return $this->render('internal/directory/person_form.html.twig', ['form' => $form, 'person' => $person, 'isNew' => null === $id]);
    }

    #[Route('/person/{id}/edit/{section}', name: 'person_section_edit', requirements: ['id' => '\\d+', 'section' => 'details|contacts|addresses'], methods: ['GET', 'POST'])]
    public function personSectionForm(Request $request, int $id, string $section): Response
    {
        $person = $this->entityManager->find(PersonEntity::class, $id);
        if (!$person instanceof PersonEntity) {
            throw $this->createNotFoundException();
        }

        if ('details' === $section) {
            $form = $this->createForm(DirectoryPersonDetailsFormType::class, $person);
        } else {
            $groups = 'contacts' === $section ? ['email', 'phone'] : ['address'];
            $form = $this->createForm(DirectoryContactDetailsFormType::class, $person->getContactDetails(), ['groups' => $groups]);
        }
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ('details' !== $section) {
                $this->sharedContactLinkResolver->resolveContactDetails($person->getContactDetails());
            }
            $this->entityManager->persist($person);
            $this->entityManager->flush();
            $this->addFlash('success', ['message' => 'technical.directory.flash.updated', 'domain' => 'technical']);

            return $this->redirectToRoute('internal_directory_index', [
                'section' => null === $person->getOrganization() ? 'people' : 'organizations',
                'selectedId' => null === $person->getOrganization() ? $person->getId() : $person->getOrganization()->getId(),
            ]);
        }

        return $this->render('internal/directory/person_section_form.html.twig', [
            'form' => $form,
            'person' => $person,
            'section' => $section,
        ]);
    }
}
