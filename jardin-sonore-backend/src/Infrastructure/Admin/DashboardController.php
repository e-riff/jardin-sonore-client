<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function index(): Response
    {
        return $this->redirect($this->adminUrlGenerator
            ->setController(RegionCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle($this->translator->trans('admin.dashboard.title'))
            ->setTranslationDomain('messages')
            ->useEntityTranslations();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('admin.menu.dashboard', 'fa fa-home');
        yield MenuItem::section('admin.menu.geography');
        yield MenuItem::linkTo(RegionCrudController::class, 'admin.menu.regions', 'fa fa-map');
        yield MenuItem::linkTo(DepartmentCrudController::class, 'admin.menu.departments', 'fa fa-map-location-dot');
        yield MenuItem::linkTo(MunicipalityCrudController::class, 'admin.menu.municipalities', 'fa fa-city');
    }
}
