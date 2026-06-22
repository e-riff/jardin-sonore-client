<?php

declare(strict_types=1);

namespace App\Application\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class InternalDashboardController extends AbstractController
{
    #[Route('/', name: 'internal_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('internal/dashboard.html.twig');
    }
}
