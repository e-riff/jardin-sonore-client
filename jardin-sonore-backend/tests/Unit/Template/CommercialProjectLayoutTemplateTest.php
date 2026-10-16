<?php

declare(strict_types=1);

namespace App\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

final class CommercialProjectLayoutTemplateTest extends TestCase
{
    public function testProjectPutsTimelineImmediatelyAfterTasksAndStatusInHero(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/commercial/project/show.html.twig');

        self::assertIsString($template);
        self::assertLessThan(strpos($template, "include 'commercial/project/_timeline.html.twig'"), strpos($template, "include 'commercial/project/_tasks.html.twig'"));
        self::assertLessThan(strpos($template, "include 'commercial/project/_interventions.html.twig'"), strpos($template, "include 'commercial/project/_timeline.html.twig'"));
        self::assertLessThan(strpos($template, "include 'commercial/project/_documents.html.twig'"), strpos($template, "include 'commercial/project/_interventions.html.twig'"));
        self::assertLessThan(strpos($template, 'commercial-dossier__title-edit'), strpos($template, 'commercial-dossier__status'));
        self::assertSame(1, substr_count($template, '<details class="commercial-dossier__status">'));
    }

    public function testSessionsOpenForConfirmedProjectsAndTimelineOpensDuringDiscussion(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/commercial/project/_interventions.html.twig');
        $timelineTemplate = file_get_contents(__DIR__ . '/../../../templates/commercial/project/_timeline.html.twig');

        self::assertIsString($template);
        self::assertIsString($timelineTemplate);
        self::assertStringContainsString('<details class="commercial-dossier__interventions"', $template);
        self::assertStringContainsString('name="commercial-project-details"', $template);
        self::assertStringContainsString("project.status == 'confirmed'", $template);
        self::assertStringContainsString("project.status == 'discussion'", $timelineTemplate);
        self::assertStringContainsString('calendar.sessions.upcoming', $template);
        self::assertStringContainsString('commercial-dossier__intervention-menu', $template);
    }

    public function testTimelineIsCollapsedAndCreatePageHasOnlyOneProjectBackLink(): void
    {
        $timelineTemplate = file_get_contents(__DIR__ . '/../../../templates/commercial/project/_timeline.html.twig');
        $createTemplate = file_get_contents(__DIR__ . '/../../../templates/commercial/project/intervention_new.html.twig');

        self::assertIsString($timelineTemplate);
        self::assertIsString($createTemplate);
        self::assertStringContainsString('<details class="commercial-dossier__timeline"', $timelineTemplate);
        self::assertStringContainsString('name="commercial-project-details"', $timelineTemplate);
        self::assertStringContainsString("project.status == 'discussion'", $timelineTemplate);
        self::assertSame(2, substr_count($createTemplate, "path('commercial_project_show', {id: project.id})"));
        self::assertStringNotContainsString("<a href=\"{{ path('commercial_project_show', {id: project.id}) }}\">{{ project.title }}</a>", $createTemplate);
    }

    public function testCreateFormActionsShareACompactHorizontalActionGroup(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/commercial/project/intervention_new.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('commercial-session-create-form__actions', $template);
        self::assertSame(1, substr_count($template, 'commercial-session-create-form__actions'));
    }

    public function testCreateActionsArePlacedInsideTheirSections(): void
    {
        $tasksTemplate = file_get_contents(__DIR__ . '/../../../templates/commercial/project/_tasks.html.twig');
        $controlsTemplate = file_get_contents(__DIR__ . '/../../../templates/commercial/project/_create_controls.html.twig');
        $timelineTemplate = file_get_contents(__DIR__ . '/../../../templates/commercial/project/_timeline.html.twig');

        self::assertIsString($tasksTemplate);
        self::assertIsString($controlsTemplate);
        self::assertIsString($timelineTemplate);
        self::assertStringContainsString("'project.add_action'|trans", $tasksTemplate);
        self::assertStringNotContainsString("'project.add_action'|trans", $controlsTemplate);
        self::assertStringContainsString("include 'commercial/project/_create_controls.html.twig'", $timelineTemplate);
        self::assertStringContainsString('commercial-dossier__timeline-header', $timelineTemplate);
        self::assertLessThan(
            strpos($timelineTemplate, "include 'commercial/project/_create_controls.html.twig'"),
            strpos($timelineTemplate, '<details class="commercial-dossier__timeline"'),
        );
    }

    public function testProjectContactsDoNotLinkBackToTheInitialRequestPage(): void
    {
        $contactsTemplate = file_get_contents(__DIR__ . '/../../../templates/commercial/project/_contacts.html.twig');

        self::assertIsString($contactsTemplate);
        self::assertStringNotContainsString("path('commercial_request_show'", $contactsTemplate);
    }

    public function testProjectTitleIsEditedDirectlyInPlace(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/commercial/project/show.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('data-controller="commercial-title-edit"', $template);
        self::assertStringContainsString('commercial-title-edit-target="display"', $template);
        self::assertStringContainsString('commercial-title-edit-target="form"', $template);
        self::assertStringNotContainsString('Titre du dossier', $template);
        self::assertStringNotContainsString('<details class="commercial-dossier__title-edit">', $template);
    }
}
