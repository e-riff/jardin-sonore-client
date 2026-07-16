<?php

declare(strict_types=1);

namespace App\Infrastructure\Admin;

use App\Application\Mailing\NewsletterAudienceOptionsQueryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AdminDashboard(routePath: '/backoffice', routeName: 'admin')]
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
            ->setController(OrganizationCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    #[Route('/backoffice/mailing-audience-masks/municipalities/autocomplete', name: 'admin_mailing_audience_mask_municipalities_autocomplete', methods: ['GET'])]
    public function autocompleteMailingAudienceMaskMunicipalities(
        Request $request,
        NewsletterAudienceOptionsQueryInterface $newsletterAudienceOptionsQuery,
    ): JsonResponse {
        $page = max(1, $request->query->getInt('page', 1));
        $query = $request->query->getString('query');
        $limit = 50;
        $autocompleteChoices = $newsletterAudienceOptionsQuery->searchMunicipalityAutocompleteChoices(
            query: $query,
            page: $page,
            limit: $limit,
        );

        return $this->json([
            'results' => array_map(
                static fn (array $result): array => [
                    'entityId' => $result['value'],
                    'entityAsString' => $result['text'],
                ],
                $autocompleteChoices['results'],
            ),
            'next_page' => $autocompleteChoices['has_next_page']
                ? $this->generateUrl('admin_mailing_audience_mask_municipalities_autocomplete', [
                    'query' => $query,
                    'page' => $page + 1,
                ])
                : null,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle($this->translator->trans('admin.dashboard.title', [], 'backoffice'))
            ->setFaviconPath('/favicon/favicon.ico')
            ->setTranslationDomain('backoffice')
            ->useEntityTranslations();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('admin.menu.internal_dashboard', 'fa fa-house', 'internal_dashboard');
        yield MenuItem::section('admin.menu.address_book');
        yield MenuItem::linkTo(OrganizationCrudController::class, 'admin.menu.organizations', 'fa fa-building');
        yield MenuItem::linkTo(PersonCrudController::class, 'admin.menu.people', 'fa fa-user');
        yield MenuItem::linkTo(EmailContactCrudController::class, 'admin.menu.email_contacts', 'fa fa-envelope');
        yield MenuItem::linkTo(PhoneContactCrudController::class, 'admin.menu.phone_contacts', 'fa fa-phone');
        yield MenuItem::linkTo(AddressContactCrudController::class, 'admin.menu.address_contacts', 'fa fa-location-dot');
        yield MenuItem::linkTo(TagCrudController::class, 'admin.menu.tags', 'fa fa-tags');
        yield MenuItem::section('admin.menu.sessions');
        yield MenuItem::linkTo(InstrumentCrudController::class, 'admin.menu.instruments', 'fa fa-guitar');
        yield MenuItem::linkTo(InstrumentTagCrudController::class, 'admin.menu.instrument_tags', 'fa fa-tags');
        yield MenuItem::section('admin.menu.session_resources');
        yield MenuItem::linkTo(RepertoireItemCrudController::class, 'admin.menu.repertoire_items', 'fa fa-music');
        yield MenuItem::linkTo(MediaResourceCrudController::class, 'admin.menu.media_resources', 'fa fa-photo-film');
        yield MenuItem::linkTo(SessionRecommendationCrudController::class, 'admin.menu.session_recommendations', 'fa fa-lightbulb');
        yield MenuItem::section('admin.menu.geography');
        yield MenuItem::linkTo(RegionCrudController::class, 'admin.menu.regions', 'fa fa-map');
        yield MenuItem::linkTo(DepartmentCrudController::class, 'admin.menu.departments', 'fa fa-map-location-dot');
        yield MenuItem::linkTo(MunicipalityCrudController::class, 'admin.menu.municipalities', 'fa fa-city');
        yield MenuItem::section('admin.menu.newsletters');
        yield MenuItem::linkTo(MailingCampaignCrudController::class, 'admin.menu.mailing_campaigns', 'fa fa-paper-plane');
        yield MenuItem::linkTo(MailingAudienceMaskCrudController::class, 'admin.menu.mailing_audience_masks', 'fa fa-layer-group');
        yield MenuItem::linkTo(NewsletterRecommendationCrudController::class, 'admin.menu.mailing_recommendations', 'fa fa-newspaper');
        yield MenuItem::linkTo(MailingDeliveryRecipientCrudController::class, 'admin.menu.mailing_delivery_recipients', 'fa fa-envelope-circle-check');
    }
}
