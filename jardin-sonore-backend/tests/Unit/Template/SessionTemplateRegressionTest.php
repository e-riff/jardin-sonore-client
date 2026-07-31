<?php

declare(strict_types=1);

namespace App\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

final class SessionTemplateRegressionTest extends TestCase
{
    public function testActivityFormTargetsTheComposerOverlay(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_activity_form.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("'data-turbo-frame': 'session-composer-overlay'", $template);
    }

    public function testPreviewDoesNotRenderBodyWhenItDuplicatesLyrics(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/show.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('{% if sequence.body and sequence.body != sequence.lyrics %}', $template);
    }

    public function testSequenceInstrumentLabelIsTranslated(): void
    {
        $translations = file_get_contents(__DIR__ . '/../../../translations/sessions+intl-icu.fr.yaml');

        self::assertIsString($translations);
        self::assertMatchesRegularExpression('/^      instruments: "Instruments pour cette activité"$/m', $translations);
        self::assertMatchesRegularExpression('/^      notes_private_help: "Ces notes restent privées et ne figurent pas dans le rendu de la séance\."$/m', $translations);
    }

    public function testPreviewShowsTheSessionRoleAndGeneralInstructions(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/show.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('sequence.role', $template);
        self::assertStringContainsString('sequence.generalInstructions', $template);
    }

    public function testRepertoireFormExposesGeneralInstructionsSeparatelyFromPrivateNotes(): void
    {
        $formType = file_get_contents(__DIR__ . '/../../../src/Application/Form/RepertoireItemType.php');
        $formModel = file_get_contents(__DIR__ . '/../../../src/Application/Form/Model/RepertoireItemFormModel.php');

        self::assertIsString($formType);
        self::assertIsString($formModel);
        self::assertStringContainsString("->add('generalInstructions'", $formType);
        self::assertStringContainsString('public ?string $generalInstructions = null;', $formModel);
    }

    public function testSummaryFormLetsTheUserOrderSelectedRecommendations(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/_summary_form.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('data-controller="ordered-choice"', $template);
        self::assertStringContainsString('data-ordered-choice-target="list"', $template);
        self::assertStringContainsString('data-ordered-choice-target="add"', $template);
    }

    public function testOrderedChoicesRestoreThePersistedOrderOnPageLoad(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../assets/controllers/ordered_choice_controller.js');

        self::assertIsString($controller);
        self::assertStringContainsString('orderedSourceOptions()', $controller);
        self::assertStringContainsString('this.orderTarget.value', $controller);
    }

    public function testRepertoireSelectionUsesAnExplicitPostBeforeCreatingTheSessionSequence(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_add.html.twig');
        $controller = file_get_contents(__DIR__ . '/../../../src/Application/Controller/SessionSummaryController.php');

        self::assertIsString($template);
        self::assertIsString($controller);
        self::assertStringContainsString("action=\"{{ path('session_composer_add', { uuid: session.uuid, catalog: catalog }) }}\"", $template);
        self::assertStringContainsString('name="sourceUuid" value="{{ item.uuid }}"', $template);
        self::assertStringNotContainsString("session_sequence_new', { uuid: session.uuid, composer: 1, repertoire: item.uuid", $template);
        self::assertStringContainsString("if ('repertoire' === \$catalog)", $controller);
        self::assertStringContainsString("return \$this->redirectToRoute('session_sequence_edit'", $controller);
    }

    public function testCatalogSearchKeepsItsResultsOutsideOfTheSearchGrid(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_add.html.twig');
        $styles = file_get_contents(__DIR__ . '/../../../assets/styles/app.css');

        self::assertIsString($template);
        self::assertIsString($styles);
        self::assertStringContainsString("data-controller=\"catalog-filter\">\n                <div class=\"session-composer-overlay__search\">", $template);
        self::assertStringContainsString("</div>\n                <div class=\"session-composer-overlay__results\">", $template);
        self::assertStringContainsString(".session-composer-overlay__search {\n    display: grid;\n    grid-template-columns: 1fr;", $styles);
        self::assertStringNotContainsString('session-composer-overlay__activity', $template);
        self::assertStringNotContainsString('sessions.sequence.composer.configure', $template);
    }

    public function testRepertoireSessionFormOnlyExposesLocalSettings(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_repertoire_form.html.twig');
        $formType = file_get_contents(__DIR__ . '/../../../src/Application/Form/RepertoireSessionSequenceType.php');

        self::assertIsString($template);
        self::assertIsString($formType);
        self::assertStringContainsString('form.role', $template);
        self::assertStringContainsString('<twig:SessionSequenceLocalDetails', $template);
        self::assertStringContainsString('form.notes', $template);
        self::assertStringContainsString('form.instrumentUuids', $template);
        self::assertStringContainsString("action: path('session_sequence_edit'", $template);
        self::assertStringNotContainsString('form.lyrics', $template);
        self::assertStringNotContainsString('form.gestures', $template);
        self::assertStringContainsString('use Symfony\\Component\\Form\\Extension\\Core\\Type\\TextareaType;', $formType);
    }

    public function testComposerNumbersAreUpdatedAfterASequenceMove(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/_composer.html.twig');
        $controller = file_get_contents(__DIR__ . '/../../../assets/controllers/session_sequence_sorter_controller.js');

        self::assertIsString($template);
        self::assertIsString($controller);
        self::assertStringContainsString('data-session-sequence-sorter-target="index"', $template);
        self::assertStringContainsString('refreshIndexes()', $controller);
    }

    public function testComposerUsesTheConfirmationDialogForSequenceRemoval(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/_composer.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("stimulus_action('confirmation-dialog', 'open')", $template);
        self::assertStringContainsString('internal-icon-button--danger', $template);
        self::assertStringContainsString("'sessions.sequence.composer.edit'", $template);
    }

    public function testComposerShowsSourceAndTypeAlongsideTheTitle(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/_composer.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('session-composer__source', $template);
        self::assertStringContainsString('session-composer__type', $template);
        self::assertStringNotContainsString('sequence.sourceTitle', $template);
    }

    public function testActivityFormPostsToTheComposerEditionRouteForItsDraft(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_activity_form.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("action: path('session_sequence_edit', { uuid: session.uuid, sequenceUuid: sequence.uuid, composer: 1, draft: isDraft ? 1 : null })", $template);
    }

    public function testComposerCardsOpenTheActivityEditorInTheOverlay(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/_composer.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("path('session_sequence_edit'", $template);
        self::assertStringContainsString('data-turbo-frame="session-composer-overlay"', $template);
        self::assertStringContainsString('draggable="false"', $template);
        self::assertStringNotContainsString('{% if sequence.sourceUuid is null %}', $template);
    }

    public function testTurboIsStartedForComposerFrames(): void
    {
        $app = file_get_contents(__DIR__ . '/../../../assets/app.js');

        self::assertIsString($app);
        self::assertStringContainsString("import '@hotwired/turbo';", $app);
    }

    public function testActivityEditorOnlyRendersItsAllowedFields(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_activity_form.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('form.media', $template);
        self::assertStringContainsString('form.role', $template);
        self::assertStringContainsString('<twig:SessionSequenceLocalDetails', $template);
        self::assertStringContainsString('section="details"', $template);
        self::assertStringContainsString('form.notes', $template);
        self::assertStringContainsString('form.instrumentUuids', $template);
        self::assertStringNotContainsString('form.lyrics', $template);
        self::assertStringNotContainsString('form.gestures', $template);
        self::assertStringNotContainsString('instruction-list', $template);
    }

    public function testActivityMediaCollectionUsesDedicatedLayoutHooks(): void
    {
        $styles = file_get_contents(__DIR__ . '/../../../assets/styles/app.css');

        self::assertIsString($styles);
        self::assertStringContainsString('.session-activity-media > div > div > label', $styles);
        self::assertStringContainsString('.session-activity-media > div > div > div {', $styles);
    }

    public function testActivitySaveRefreshesTheGlobalSessionInstrumentsForm(): void
    {
        $editTemplate = file_get_contents(__DIR__ . '/../../../templates/session/edit.html.twig');
        $streamTemplate = file_get_contents(__DIR__ . '/../../../templates/session/composer_activity.stream.html.twig');
        $controller = file_get_contents(__DIR__ . '/../../../src/Application/Controller/SessionSummaryController.php');

        self::assertIsString($editTemplate);
        self::assertIsString($streamTemplate);
        self::assertIsString($controller);
        self::assertStringContainsString('id="session-summary-edit-form"', $editTemplate);
        self::assertStringContainsString('target="session-summary-edit-form"', $streamTemplate);
        self::assertStringContainsString("'summaryForm' =>", $controller);
    }

    public function testTurboActivityResponsesDoNotQueueFlashesForLaterPageLoads(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../src/Application/Controller/SessionSummaryController.php');

        self::assertIsString($controller);
        self::assertMatchesRegularExpression('/if \(\$openedFromComposer\) \{(?:(?!sessions\.sequence\.flash\.updated).)*?return \$this->render\(\'session\/composer_activity\.stream\.html\.twig\'.*?\n            \}\n\n            \$this->addFlash\(\'success\', \[\n                \'message\' => \'sessions\.sequence\.flash\.updated\'/s', $controller);
        self::assertMatchesRegularExpression('/if \(str_contains\(\$request->headers->get\(\'Accept\', \'\'\), \'text\/vnd\.turbo-stream\.html\'\)\) \{(?:(?!sessions\.sequence\.flash\.removed).)*?return \$this->render\(\'session\/composer_activity\.stream\.html\.twig\'.*?\n        \}\n\n        \$this->addFlash\(\'success\', \[\n            \'message\' => \'sessions\.sequence\.flash\.removed\'/s', $controller);
    }

    public function testActivityEditorProvidesCatalogSelectionAndQuickCreationForMedia(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_activity_form.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("path('session_sequence_media_picker'", $template);
        self::assertStringContainsString("path('session_sequence_media_create'", $template);
        self::assertStringContainsString('formaction="{{ path(\'session_sequence_media_picker\'', $template);
        self::assertStringContainsString('formaction="{{ path(\'session_sequence_media_create\'', $template);
        self::assertStringContainsString('formnovalidate', $template);
    }

    public function testDraftActivityCloseRemovesOnlyTheNewDraft(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_activity_form.html.twig');
        $controller = file_get_contents(__DIR__ . '/../../../src/Application/Controller/SessionSummaryController.php');

        self::assertIsString($template);
        self::assertIsString($controller);
        self::assertStringContainsString('{% if isDraft %}', $template);
        self::assertStringContainsString("path('session_sequence_remove'", $template);
        self::assertStringContainsString("'draft' => 1", $controller);
    }

    public function testDraftRepertoireConfigurationIsRemovedWhenTheOverlayCloses(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_repertoire_form.html.twig');
        $controller = file_get_contents(__DIR__ . '/../../../src/Application/Controller/SessionSummaryController.php');

        self::assertIsString($template);
        self::assertIsString($controller);
        self::assertStringContainsString('{% set isDraft = isDraft|default(false) %}', $template);
        self::assertStringContainsString("path('session_sequence_remove'", $template);
        self::assertStringContainsString("draft: isDraft ? 1 : null", $template);
        self::assertStringContainsString("'draft' => 1", $controller);
    }

    public function testOpeningMediaActionsPersistsTheCurrentActivityFormFirst(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../src/Application/Controller/SessionSummaryController.php');

        self::assertIsString($controller);
        self::assertStringContainsString("name: 'sequence_media_picker', methods: ['GET', 'POST']", $controller);
        self::assertStringContainsString("'open-media-create' === \$request->request->getString('activityMediaAction')", $controller);
    }

    public function testInstructionShortcutUsesTheLiveComponentLifecycleInsteadOfADeferredClick(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/components/SessionSequenceLocalDetails.html.twig');
        $controller = file_get_contents(__DIR__ . '/../../../assets/controllers/instruction_shortcut_controller.js');

        self::assertIsString($template);
        self::assertIsString($controller);
        self::assertStringContainsString('keydown->instruction-shortcut#addNextInstruction', $template);
        self::assertStringContainsString("getComponent(this.element.closest('[data-controller~=\"live\"]'))", $controller);
        self::assertStringContainsString("this.liveComponent.action('saveAndAddInstruction')", $controller);
        self::assertStringContainsString("this.liveComponent.on('render:finished'", $controller);
        self::assertStringNotContainsString('requestAnimationFrame', $controller);
    }

    public function testCatalogMediaSelectionReturnsTheUpdatedActivityEditorFrame(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_activity_media_picker.html.twig');
        $controller = file_get_contents(__DIR__ . '/../../../src/Application/Controller/SessionSummaryController.php');

        self::assertIsString($template);
        self::assertIsString($controller);
        self::assertStringNotContainsString('data-turbo-stream="true"', $template);
        self::assertStringContainsString("return \$this->render('session/composer_activity_form.html.twig'", $controller);
        self::assertStringContainsString('data-turbo-frame="session-composer-overlay"', $template);
        self::assertStringContainsString("'mediaResources' => \$searchMediaResources(query: \$request->query->getString('query'), activeOnly: true)", $controller);
    }

    public function testSequenceDeletionKeepsTheSharedConfirmationDialogContract(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/_composer.html.twig');
        $controller = file_get_contents(__DIR__ . '/../../../assets/controllers/confirmation_dialog_controller.js');

        self::assertIsString($template);
        self::assertIsString($controller);
        self::assertStringContainsString("stimulus_action('confirmation-dialog', 'open')", $template);
        self::assertStringContainsString('this.dialogTarget.showModal()', $controller);
        self::assertStringNotContainsString("@hotwired/turbo", $controller);
    }

    public function testConfirmationDialogClosesAfterASuccessfulTurboSubmission(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/internal/base.html.twig');
        $controller = file_get_contents(__DIR__ . '/../../../assets/controllers/confirmation_dialog_controller.js');

        self::assertIsString($template);
        self::assertIsString($controller);
        self::assertStringContainsString("stimulus_action('confirmation-dialog', 'closeAfterSubmit', 'turbo:submit-end')", $template);
        self::assertStringContainsString('closeAfterSubmit(event)', $controller);
    }

    public function testComposerStreamKeepsAnEmptyOverlayFrameAfterClosingIt(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_activity.stream.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('{% else %}', $template);
        self::assertStringContainsString('<turbo-stream action="update" target="session-composer-overlay"><template></template></turbo-stream>', $template);
    }

    public function testComposerEditsSessionInstructionsOneLineAtATime(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/components/SessionSequenceLocalDetails.html.twig');
        $component = file_get_contents(__DIR__ . '/../../../src/Application/Twig/Component/SessionSequenceLocalDetails.php');

        self::assertIsString($template);
        self::assertIsString($component);
        self::assertStringContainsString('this.instructions', $template);
        self::assertStringContainsString("'sessions.sequence.form.body'|trans({}, 'sessions')", $template);
        self::assertStringContainsString("live_action('startEditInstruction'", $template);
        self::assertStringContainsString('function getInstructions()', $component);
        self::assertStringContainsString('blur->live#action', $template);
        self::assertStringNotContainsString("'sessions.sequence.composer.save_instruction'", $template);
        self::assertStringNotContainsString("'sessions.sequence.composer.save_notes'", $template);
        self::assertStringNotContainsString("session-sequence-local-details__notes\" {% if editing == 'notes' or not notes", $template);
        self::assertStringNotContainsString("editing == 'body'", $template);
    }
}
