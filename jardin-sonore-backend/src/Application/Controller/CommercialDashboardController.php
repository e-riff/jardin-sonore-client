<?php

declare(strict_types=1);

namespace App\Application\Controller;

use App\Infrastructure\Doctrine\Entity\CommercialActionEntity;
use App\Infrastructure\Doctrine\Entity\CommercialProjectEntity;
use App\Infrastructure\Doctrine\Entity\CommercialRequestEntity;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommercialDashboardController extends AbstractController
{
    #[Route('/commercial', name: 'commercial_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager, ClockInterface $clock): Response
    {
        $today = $clock->now()->setTimezone(new DateTimeZone('Europe/Paris'))->format('Y-m-d');
        $requests = $entityManager->getRepository(CommercialRequestEntity::class)->findBy(
            ['status' => CommercialRequestEntity::STATUS_TO_QUALIFY],
            ['receivedAt' => 'ASC'],
        );
        $actions = $entityManager->getRepository(CommercialActionEntity::class)->findBy(
            ['status' => CommercialActionEntity::STATUS_OPEN],
            ['dueOn' => 'ASC'],
        );
        $overdue = [];
        $dueToday = [];
        $upcoming = [];
        foreach ($actions as $actionEntity) {
            $dueOn = $actionEntity->getDueOn()->format('Y-m-d');
            if ($dueOn < $today) {
                $overdue[] = $actionEntity;
            } elseif ($dueOn === $today) {
                $dueToday[] = $actionEntity;
            } else {
                $upcoming[] = $actionEntity;
            }
        }

        $projects = $entityManager->getRepository(CommercialProjectEntity::class)->findBy(
            ['status' => [CommercialProjectEntity::STATUS_DISCUSSION, CommercialProjectEntity::STATUS_CONFIRMED]],
            ['createdAt' => 'DESC'],
        );
        $withoutAction = [];
        foreach ($projects as $projectEntity) {
            $hasOpenAction = false;
            foreach ($projectEntity->getActions() as $actionEntity) {
                if (CommercialActionEntity::STATUS_OPEN === $actionEntity->getStatus()) {
                    $hasOpenAction = true;
                    break;
                }
            }
            if (!$hasOpenAction) {
                $withoutAction[] = $projectEntity;
            }
        }

        return $this->render('commercial/dashboard.html.twig', [
            'requests' => $requests,
            'overdue' => $overdue,
            'dueToday' => $dueToday,
            'upcoming' => $upcoming,
            'withoutAction' => $withoutAction,
        ]);
    }
}
