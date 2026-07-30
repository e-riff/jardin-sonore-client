<?php

declare(strict_types=1);

namespace App\Tests\Unit\Template;

use PHPUnit\Framework\TestCase;

final class SessionTemplateRegressionTest extends TestCase
{
    public function testActivityFormExplicitlyRequestsTurboStreamResponses(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_activity_form.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("'data-turbo-stream': 'true'", $template);
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

    public function testRepertoireSelectionOpensTheSessionSpecificActivityForm(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_add.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("path('session_sequence_new', { uuid: session.uuid, composer: 1, repertoire: item.uuid })", $template);
    }

    public function testRepertoireSessionFormOnlyExposesLocalSettings(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_repertoire_form.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('form.role', $template);
        self::assertStringContainsString('form.body', $template);
        self::assertStringContainsString('form.notes', $template);
        self::assertStringContainsString('form.instrumentUuids', $template);
        self::assertStringContainsString('notes_private_help', $template);
        self::assertStringContainsString("action: path('session_sequence_new'", $template);
        self::assertStringNotContainsString('form.lyrics', $template);
        self::assertStringNotContainsString('form.gestures', $template);
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
        self::assertStringNotContainsString("'sessions.sequence.composer.edit'", $template);
    }

    public function testComposerShowsSourceAndTypeAlongsideTheTitle(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/_composer.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('session-composer__source', $template);
        self::assertStringContainsString('session-composer__type', $template);
        self::assertStringNotContainsString('sequence.sourceTitle', $template);
    }

    public function testActivityFormPostsToTheComposerCreationRoute(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_activity_form.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("action: path('session_sequence_new', { uuid: session.uuid, composer: 1 })", $template);
    }

    public function testComposerEditsSessionInstructionsOneLineAtATime(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/components/SessionSequenceLocalDetails.html.twig');
        $component = file_get_contents(__DIR__ . '/../../../src/Application/Twig/Component/SessionSequenceLocalDetails.php');

        self::assertIsString($template);
        self::assertIsString($component);
        self::assertStringContainsString('this.instructions', $template);
        self::assertStringContainsString("live_action('startEditInstruction'", $template);
        self::assertStringContainsString('function getInstructions()', $component);
        self::assertStringContainsString('blur->live#action', $template);
        self::assertStringNotContainsString("'sessions.sequence.composer.save_instruction'", $template);
        self::assertStringNotContainsString("'sessions.sequence.composer.save_notes'", $template);
        self::assertStringNotContainsString("session-sequence-local-details__notes\" {% if editing == 'notes' or not notes", $template);
        self::assertStringNotContainsString("editing == 'body'", $template);
    }
}
