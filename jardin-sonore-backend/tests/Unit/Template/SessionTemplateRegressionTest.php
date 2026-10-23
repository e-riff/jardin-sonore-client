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

    public function testPreviewOffersTheGeneratedPdfAndDoesNotUseBrowserPrinting(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/show.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("path('session_document_download', { uuid: session.uuid })", $template);
        self::assertStringContainsString('{% if documentAvailable %}', $template);
        self::assertStringNotContainsString('window.print()', $template);
        self::assertStringContainsString('turbo-cache-control" content="no-cache"', $template);
    }

    public function testSequenceInstrumentLabelIsTranslated(): void
    {
        $translations = file_get_contents(__DIR__ . '/../../../translations/sessions+intl-icu.fr.yaml');

        self::assertIsString($translations);
        self::assertMatchesRegularExpression('/^      instruments: "Instruments pour cette activité"$/m', $translations);
        self::assertMatchesRegularExpression('/^      private_notes_help: "Ces notes restent dans l’édition et ne figurent pas dans le rendu imprimable\."$/m', $translations);
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

    public function testRepertoireFormUsesTheSharedMediaDialogWithoutYoutubeShortcut(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/repertoire/form.html.twig');
        $formType = file_get_contents(__DIR__ . '/../../../src/Application/Form/RepertoireItemType.php');
        $formModel = file_get_contents(__DIR__ . '/../../../src/Application/Form/Model/RepertoireItemFormModel.php');
        $controller = file_get_contents(__DIR__ . '/../../../src/Application/Controller/RepertoireCatalogController.php');

        self::assertIsString($template);
        self::assertIsString($formType);
        self::assertIsString($formModel);
        self::assertIsString($controller);
        self::assertStringContainsString('media_resource/_association_picker.html.twig', $template);
        self::assertStringNotContainsString('form.youtubeVideoUrl', $template);
        self::assertStringNotContainsString("->add('youtubeVideoUrl'", $formType);
        self::assertStringNotContainsString('public ?string $youtubeVideoUrl', $formModel);
        self::assertStringNotContainsString('createYoutubeVideoAndLink', $controller);
    }

    public function testRepertoireTextImportRemovesTheInitialEmptyFormRow(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../../assets/controllers/repertoire_blocks_controller.js');

        self::assertIsString($controller);
        self::assertStringContainsString('this.removeLeadingEmptyBlock();', $controller);
    }

    public function testPdfKeepsMediaPreviewsBesideTheirSequenceWhileAllowingContentToFlow(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/document.pdf.twig');

        self::assertIsString($template);
        self::assertStringNotContainsString('.sequence { margin: 0 0 6mm; page-break-inside: avoid; }', $template);
        self::assertStringContainsString('.sequence-layout { display: block; }', $template);
        self::assertStringContainsString('.sequence-layout--with-media { display: table; width: 100%; }', $template);
        self::assertStringContainsString('.sequence-layout--with-media .sequence-main { display: table-cell;', $template);
        self::assertStringContainsString('.sequence-layout--with-media .media-rail { display: table-cell;', $template);
        self::assertStringContainsString('.instructions-list li { display: table; width: 100%;', $template);
        self::assertStringContainsString('height: 1.4mm; margin-top: 2.7mm;', $template);
        self::assertStringContainsString('class="instruction-bullet"><span class="instruction-bullet-dot"></span>', $template);
        self::assertStringContainsString('class="instruction-text"', $template);
        self::assertStringContainsString('page-break-after: avoid;', $template);
    }

    public function testSessionPreviewsHideDatesAndPdfDisplaysTheSubtitleBelowTheTitle(): void
    {
        $htmlPreview = file_get_contents(__DIR__ . '/../../../templates/session/show.html.twig');
        $pdfPreview = file_get_contents(__DIR__ . '/../../../templates/session/document.pdf.twig');

        self::assertIsString($htmlPreview);
        self::assertIsString($pdfPreview);
        self::assertStringNotContainsString('session.sessionDate|date', $htmlPreview);
        self::assertStringNotContainsString('document.session.sessionDate|date', $pdfPreview);
        self::assertStringContainsString('<p class="cover-subtitle">{{ document.session.theme }}</p>', $pdfPreview);
        self::assertStringContainsString('.cover-subtitle {', $pdfPreview);
    }

    public function testPdfDisplaysTheSequencePurposeWithLegacyTypeAsFallback(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/document.pdf.twig');

        self::assertIsString($template);
        self::assertStringContainsString('<p class="sequence-heading-meta">{% if sequence.role %}<span class="sequence-heading-role">{{ sequence.role }}</span>{% else %}<span class="sequence-heading-type">{{ sequence.type.translationKey()|trans({}, \'sessions\') }}</span>{% endif %}</p>', $template);
        self::assertStringContainsString('<div class="sequence-title-row"><h2>{{ sequence.title }}</h2>{% if sequence.subtitle %}<span class="subtitle"> — {{ sequence.subtitle }}</span>{% endif %}</div>', $template);
        self::assertStringContainsString('.sequence-heading-role {', $template);
        self::assertStringContainsString('.sequence-heading-type {', $template);
        self::assertStringContainsString('.subtitle { display: inline;', $template);
    }

    public function testSessionDocumentActionsKeepDownloadReadableAndExposeRegenerationFromTheIndex(): void
    {
        $editTemplate = file_get_contents(__DIR__ . '/../../../templates/session/edit.html.twig');
        $indexTemplate = file_get_contents(__DIR__ . '/../../../templates/session/index.html.twig');

        self::assertIsString($editTemplate);
        self::assertIsString($indexTemplate);
        self::assertStringContainsString('session-pdf-menu__panel', $editTemplate);
        self::assertStringContainsString("path('session_document_download'", $indexTemplate);
        self::assertStringContainsString("path('session_document_regenerate'", $indexTemplate);
        self::assertStringContainsString('title="{{ \'sessions.summary.document.regenerate\'|trans({}, \'sessions\') }}"', $indexTemplate);
    }

    public function testSessionInstructionsUseVisibleBulletsAndPdfMediaLabelsStayBounded(): void
    {
        $htmlPreview = file_get_contents(__DIR__ . '/../../../templates/session/show.html.twig');
        $pdfPreview = file_get_contents(__DIR__ . '/../../../templates/session/document.pdf.twig');

        self::assertIsString($htmlPreview);
        self::assertIsString($pdfPreview);
        self::assertStringContainsString('session-document__instruction-bullet', $htmlPreview);
        self::assertStringContainsString('instruction-bullet', $pdfPreview);
        self::assertStringContainsString('media.label|slice(0, 54)', $pdfPreview);
        self::assertStringContainsString('.media-primary-link { display: block;', $pdfPreview);
        self::assertStringContainsString('max-height: 9mm;', $pdfPreview);
    }

    public function testMultilineSequenceBodiesRenderAsVisibleInstructionLists(): void
    {
        $htmlPreview = file_get_contents(__DIR__ . '/../../../templates/session/show.html.twig');
        $pdfPreview = file_get_contents(__DIR__ . '/../../../templates/session/document.pdf.twig');

        self::assertIsString($htmlPreview);
        self::assertIsString($pdfPreview);
        self::assertStringContainsString("sequence.body|split('\\n')", $htmlPreview);
        self::assertStringContainsString("sequence.body|split('\\n')", $pdfPreview);
        self::assertStringContainsString('session-document__instruction-bullet', $htmlPreview);
        self::assertStringContainsString('instruction-bullet', $pdfPreview);
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
        self::assertStringContainsString('$formModel = match ($catalog)', $controller);
        self::assertStringContainsString("return \$this->render('session/composer_add.stream.html.twig'", $controller);
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
        self::assertStringNotContainsString('form.notes', $template);
        self::assertStringContainsString('form.instrumentUuids', $template);
        self::assertStringContainsString("action: path('session_sequence_edit'", $template);
        self::assertStringNotContainsString('form.lyrics', $template);
        self::assertStringNotContainsString('form.gestures', $template);
        self::assertStringNotContainsString('TextareaType::class', $formType);
    }

    public function testRepertoireSessionEditorKeepsMediaManagementInTheRepertoire(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_repertoire_form.html.twig');

        self::assertIsString($template);
        self::assertStringNotContainsString("path('session_sequence_media_picker'", $template);
        self::assertStringNotContainsString("path('session_sequence_media_create'", $template);
        self::assertStringContainsString('form.instrumentUuids', $template);
    }

    public function testRepertoireSessionEditorHasNoYoutubeShortcut(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_repertoire_form.html.twig');

        self::assertIsString($template);
        self::assertStringNotContainsString("path('session_sequence_repertoire_youtube_create'", $template);
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

    public function testComposerLinksToTheRepertoireSource(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/_composer.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('session-composer__source', $template);
        self::assertStringContainsString("path('repertoire_edit'", $template);
        self::assertStringNotContainsString('session-composer__type', $template);
        self::assertStringNotContainsString('sequence.sourceTitle', $template);
    }

    public function testSessionPreviewEmbedsFeaturedYoutubeMedia(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/show.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('media.featured', $template);
        self::assertStringContainsString('youtube-nocookie.com/embed/', $template);
        self::assertStringContainsString('<iframe', $template);
    }

    public function testPdfUsesAQuietNotebookLayoutWithLinkedActivityHeadings(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/document.pdf.twig');
        $generator = file_get_contents(__DIR__ . '/../../../src/Infrastructure/Session/DompdfSessionDocumentGenerator.php');

        self::assertIsString($template);
        self::assertIsString($generator);
        self::assertStringContainsString('.document-background', $template);
        self::assertStringNotContainsString('.paper-motif', $template);
        self::assertStringContainsString('.cover--title', $template);
        self::assertStringContainsString('.running-header', $template);
        self::assertStringContainsString('.sequence-index', $template);
        self::assertStringContainsString('.sequence-heading-copy', $template);
        self::assertStringContainsString('.sequence-heading-role', $template);
        self::assertStringContainsString('.sequence-heading-type', $template);
        self::assertStringContainsString('.sequence-shell', $template);
        self::assertStringContainsString('.sequence-layout', $template);
        self::assertStringContainsString('.media-rail', $template);
        self::assertStringContainsString('.instructions-list', $template);
        self::assertStringContainsString('.lyrics-panel', $template);
        self::assertStringContainsString('sequence.contentBlocks', $template);
        self::assertStringContainsString('contentBlock.gesture', $template);
        self::assertStringContainsString('document.session.furtherExploration', $template);
        self::assertStringContainsString('.sequence--soundtrack', $template);
        self::assertStringContainsString('#28312f', $template);
        self::assertStringContainsString('#db765d', $template);
        self::assertStringContainsString('page_text(', $generator);
        self::assertStringContainsString('{PAGE_NUM}', $generator);
        self::assertStringContainsString('.cover--title { background: #f7f1e7; border-bottom: .4mm solid #e6e1d8; }', $template);
        self::assertStringContainsString('border-left: .7mm solid #d7ad59', $template);
        self::assertStringNotContainsString('badge badge--instrument">♫', $template);
        self::assertStringContainsString('.cover + .summary { margin-top: -7mm; }', $template);
        self::assertStringContainsString('.sequence-title-row', $template);
        self::assertStringContainsString('.sequence-title-row { display: block; width: 100%; }', $template);
        self::assertStringContainsString('.subtitle { display: inline;', $template);
        self::assertStringContainsString('white-space: normal;', $template);
        self::assertStringContainsString('.lyric-pair { margin-bottom: .8mm; }', $template);
        self::assertStringContainsString('.sequence .badge--instrument', $template);
        self::assertStringContainsString('.media-primary-link { display: block;', $template);
        self::assertStringContainsString('overflow-wrap: anywhere;', $template);
        self::assertStringContainsString('.gesture { margin: 0; color: #66736e; font-size: 8.5pt;', $template);
        self::assertStringContainsString('.recommendations h2 { margin-bottom: 3mm; color: #9e6049;', $template);
        self::assertStringNotContainsString('<p class="eyebrow">Prolonger la séance</p>', $template);
        self::assertStringContainsString('.page-side-rule { position: fixed;', $template);
        self::assertStringContainsString('right: -16mm;', $template);
        self::assertStringContainsString('.lyrics-panel { margin: 2mm 0 0; padding: 2mm 4mm; background: #f6faf6; border: .3mm solid #d9ddd4;', $template);
        self::assertStringNotContainsString('.lyrics-panel { margin: 2mm 0 0; padding: 2mm 4mm; background: #f6faf6; border-left:', $template);
        self::assertStringContainsString('.lyrics-panel p { margin-bottom: 0; }', $template);
        self::assertStringContainsString('.lyrics-break { height: 2mm; }', $template);
        self::assertStringContainsString('border: .5mm solid #dca58d', $template);
    }

    public function testPdfKeepsLongLyricsCompactWithoutKeepingTheWholePanelOnOnePage(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/document.pdf.twig');

        self::assertIsString($template);
        self::assertStringContainsString('.lyrics-panel { margin: 2mm 0 0; padding: 2mm 4mm;', $template);
        self::assertStringContainsString('.lyric-line { margin: 0; font-family: DejaVu Serif, serif; font-size: 10pt; line-height: 1.16; }', $template);
        self::assertStringNotContainsString('.lyrics-panel { margin: 2mm 0 0; padding: 2mm 4mm; background: #f6faf6; border: .3mm solid #d9ddd4; text-align: center; page-break-inside: avoid; }', $template);
    }

    public function testSessionPreviewRendersRepertoireGesturesAlongsideTheirLyricsWithoutMediaListMarkers(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/show.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('sequence.contentBlocks', $template);
        self::assertStringContainsString('contentBlock.gesture', $template);
        self::assertStringContainsString('session-document__gesture', $template);
        self::assertStringNotContainsString('<ul style="margin-top: .75rem;">', $template);
    }

    public function testComposerRendersRepertoireLyricsAndGesturesAlongsideInstructions(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/_composer.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString('sequence.contentBlocks', $template);
        self::assertStringContainsString('contentBlock.gesture', $template);
        self::assertStringContainsString('sequence.lyrics', $template);
    }

    public function testSessionPreviewGroupsLyricsAndGesturesInTheSharedLyricsPanel(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/show.html.twig');
        $styles = file_get_contents(__DIR__ . '/../../../assets/styles/app.css');

        self::assertIsString($template);
        self::assertIsString($styles);
        self::assertStringContainsString('session-document__lyrics-panel', $template);
        self::assertStringContainsString('session-document__lyric-pair', $template);
        self::assertStringContainsString('session.furtherExploration', $template);
        self::assertStringContainsString('.session-document__lyrics-panel', $styles);
        self::assertStringContainsString('text-align: center', $styles);
    }

    public function testActivityFormCreatesASequenceOnlyWhenTheNewActivityIsSubmitted(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_activity_form.html.twig');

        self::assertIsString($template);
        self::assertStringContainsString("? path('session_sequence_new', { uuid: session.uuid, composer: 1 })", $template);
        self::assertStringContainsString(": path('session_sequence_edit', { uuid: session.uuid, sequenceUuid: sequence.uuid, composer: 1 })", $template);
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
        self::assertStringContainsString('form.catalogMediaUuids', $template);
        self::assertStringContainsString('form.role', $template);
        self::assertStringContainsString('form.body', $template);
        self::assertStringNotContainsString('form.notes', $template);
        self::assertStringContainsString('form.instrumentUuids', $template);
        self::assertStringContainsString('form.lyrics', $template);
        self::assertStringContainsString('form.gestures', $template);
        self::assertStringContainsString('<details class="internal-disclosure">', $template);
        self::assertStringNotContainsString('instruction-list', $template);
    }

    public function testActivityMediaCollectionUsesDedicatedLayoutHooks(): void
    {
        $styles = file_get_contents(__DIR__ . '/../../../assets/styles/app.css');
        $formTheme = file_get_contents(__DIR__ . '/../../../templates/form/internal_form_theme.html.twig');

        self::assertIsString($styles);
        self::assertIsString($formTheme);
        self::assertStringContainsString('.session-activity-media__item', $styles);
        self::assertStringContainsString('form_parent(form).vars.ea_vars is not defined', $formTheme);
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
        self::assertStringContainsString('media_resource/_association_picker.html.twig', $template);
        self::assertStringContainsString('media-resource-create', $template);
        self::assertStringNotContainsString("path('session_sequence_media_picker'", $template);
    }

    public function testNewActivityClosesWithoutCreatingASequence(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_activity_form.html.twig');
        self::assertIsString($template);
        self::assertStringContainsString('{% set isNewActivity = sequence is null %}', $template);
        self::assertStringNotContainsString("path('session_sequence_remove'", $template);
        self::assertStringContainsString("path('session_edit'", $template);
    }

    public function testRepertoireSequenceClosesWithoutDraftFlag(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../templates/session/composer_repertoire_form.html.twig');
        self::assertIsString($template);
        self::assertStringNotContainsString('draft:', $template);
        self::assertStringNotContainsString("path('session_sequence_remove'", $template);
        self::assertStringContainsString("path('session_edit'", $template);
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
        self::assertStringContainsString('return $this->render($this->composerSequenceTemplate($updatedSequenceView)', $controller);
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
        self::assertStringNotContainsString('@hotwired/turbo', $controller);
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
        self::assertStringContainsString('blur->instruction-shortcut#saveInstruction', $template);
        self::assertStringContainsString('keydown->instruction-shortcut#addNextInstruction', $template);
        self::assertStringNotContainsString('blur->live#action', $template);
        self::assertStringNotContainsString("'sessions.sequence.composer.save_instruction'", $template);
        self::assertStringNotContainsString("'sessions.sequence.composer.save_notes'", $template);
        self::assertStringNotContainsString('section == \'notes\'', $template);
        self::assertStringNotContainsString("editing == 'body'", $template);
    }
}
