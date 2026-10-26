<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Application\Form\DepartmentManagementType;
use App\Application\Form\MunicipalityManagementType;
use App\Application\Form\RegionManagementType;
use App\Infrastructure\Doctrine\Entity\DepartmentEntity;
use App\Infrastructure\Doctrine\Entity\MunicipalityEntity;
use App\Infrastructure\Doctrine\Entity\RegionEntity;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/technical/geography', name: 'technical_geography_')]
final class GeographyManagementController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly Connection $connection)
    {
    }

    #[Route('/municipalities', name: 'municipalities', methods: ['GET'])]
    public function municipalities(): Response
    {
        return $this->render('technical/geography/municipalities.html.twig');
    }

    #[Route('/municipality/new', name: 'municipality_new', methods: ['GET', 'POST'])]
    public function newMunicipality(Request $request): Response
    {
        $entity = new MunicipalityEntity();
        $form = $this->createForm(MunicipalityManagementType::class, $entity)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
            $this->addFlash('success', 'technical.geography.flash.created');

            return $this->redirectToRoute('technical_geography_municipalities');
        }

        return $this->render('technical/geography/municipality_edit.html.twig', ['form' => $form, 'municipality' => $entity, 'isNew' => true]);
    }

    #[Route('/municipality/{id}/edit', name: 'municipality_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function editMunicipality(int $id, Request $request): Response
    {
        $entity = $this->entityManager->find(MunicipalityEntity::class, $id) ?? throw $this->createNotFoundException();
        $form = $this->createForm(MunicipalityManagementType::class, $entity)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'technical.geography.flash.updated');

            return $this->redirectToRoute('technical_geography_municipalities');
        }

        return $this->render('technical/geography/municipality_edit.html.twig', ['form' => $form, 'municipality' => $entity]);
    }

    #[Route('/municipality/{id}/delete', name: 'municipality_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deleteMunicipality(int $id, Request $request): Response
    {
        $entity = $this->entityManager->find(MunicipalityEntity::class, $id) ?? throw $this->createNotFoundException();
        if ($this->isCsrfTokenValid('technical_geography_municipality_' . $id, $request->request->getString('_token'))) {
            if (0 < (int) $this->connection->createQueryBuilder()->select('COUNT(id)')->from('address_contact')->where('municipality_id = :id')->setParameter('id', $id)->executeQuery()->fetchOne()) {
                $this->addFlash('error', 'technical.geography.error.delete_referenced');
            } else {
                $this->entityManager->remove($entity);
                $this->entityManager->flush();
                $this->addFlash('success', 'technical.geography.flash.deleted');
            }
        }

        return $this->redirectToRoute('technical_geography_municipalities');
    }

    #[Route('/departments', name: 'departments', methods: ['GET'])]
    public function departments(): Response
    {
        return $this->render('technical/geography/departments.html.twig');
    }

    #[Route('/department/new', name: 'department_new', methods: ['GET', 'POST'])]
    #[Route('/department/{id}/edit', name: 'department_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function departmentForm(Request $request, ?int $id = null): Response
    {
        $entity = null === $id ? new DepartmentEntity() : ($this->entityManager->find(DepartmentEntity::class, $id) ?? throw $this->createNotFoundException());
        $form = $this->createForm(DepartmentManagementType::class, $entity)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
            $this->addFlash('success', null === $id ? 'technical.geography.flash.created' : 'technical.geography.flash.updated');

            return $this->redirectToRoute('technical_geography_departments');
        }

        return $this->render('technical/geography/reference_edit.html.twig', ['form' => $form, 'kind' => 'departments', 'isNew' => null === $id]);
    }

    #[Route('/department/{id}/delete', name: 'department_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deleteDepartment(int $id, Request $request): Response
    {
        $entity = $this->entityManager->find(DepartmentEntity::class, $id) ?? throw $this->createNotFoundException();
        if ($this->isCsrfTokenValid('technical_geography_department_' . $id, $request->request->getString('_token'))) {
            if (0 < (int) $this->connection->createQueryBuilder()->select('COUNT(id)')->from('municipality')->where('department_id = :id')->setParameter('id', $id)->executeQuery()->fetchOne()) {
                $this->addFlash('error', 'technical.geography.error.delete_referenced');
            } else {
                $this->entityManager->remove($entity);
                $this->entityManager->flush();
                $this->addFlash('success', 'technical.geography.flash.deleted');
            }
        }

        return $this->redirectToRoute('technical_geography_departments');
    }

    #[Route('/regions', name: 'regions', methods: ['GET'])]
    public function regions(): Response
    {
        return $this->render('technical/geography/regions.html.twig');
    }

    #[Route('/region/new', name: 'region_new', methods: ['GET', 'POST'])]
    #[Route('/region/{id}/edit', name: 'region_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function regionForm(Request $request, ?int $id = null): Response
    {
        $entity = null === $id ? new RegionEntity() : ($this->entityManager->find(RegionEntity::class, $id) ?? throw $this->createNotFoundException());
        $form = $this->createForm(RegionManagementType::class, $entity)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
            $this->addFlash('success', null === $id ? 'technical.geography.flash.created' : 'technical.geography.flash.updated');

            return $this->redirectToRoute('technical_geography_regions');
        }

        return $this->render('technical/geography/reference_edit.html.twig', ['form' => $form, 'kind' => 'regions', 'isNew' => null === $id]);
    }

    #[Route('/region/{id}/delete', name: 'region_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function deleteRegion(int $id, Request $request): Response
    {
        $entity = $this->entityManager->find(RegionEntity::class, $id) ?? throw $this->createNotFoundException();
        if ($this->isCsrfTokenValid('technical_geography_region_' . $id, $request->request->getString('_token'))) {
            if (0 < (int) $this->connection->createQueryBuilder()->select('COUNT(id)')->from('department')->where('region_id = :id')->setParameter('id', $id)->executeQuery()->fetchOne()) {
                $this->addFlash('error', 'technical.geography.error.delete_referenced');
            } else {
                $this->entityManager->remove($entity);
                $this->entityManager->flush();
                $this->addFlash('success', 'technical.geography.flash.deleted');
            }
        }

        return $this->redirectToRoute('technical_geography_regions');
    }
}
