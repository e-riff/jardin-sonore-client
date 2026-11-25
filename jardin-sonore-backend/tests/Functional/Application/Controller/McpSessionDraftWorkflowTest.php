<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Application\Mcp\MailReader;
use App\Application\Mcp\McpSessionReader;
use App\Application\Mcp\McpSessionWriteCoordinator;
use App\Application\Mcp\McpSessionWriter;
use App\Application\Mcp\ProjectFollowupWriter;
use App\Application\Mcp\WriteToolRegistry;
use App\Domain\Model\Session\RepertoireBlock;
use App\Domain\Model\Session\RepertoireBlockKind;
use App\Domain\Model\Session\RepertoireItem;
use App\Domain\Model\Session\RepertoireItemType;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use App\Infrastructure\Mcp\DoctrineMcpSessionWriteCoordinator;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

final class McpSessionDraftWorkflowTest extends KernelTestCase
{
    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel('test', true);
    }

    public function testCreatesFamilyAndDraftAndAddsElementWithoutEstablishment(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $writer = $container->get(McpSessionWriter::class);
        $reader = $container->get(McpSessionReader::class);
        $registry = new WriteToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectFollowupWriter::class), false, mcpSessionWriter: $writer, sessionWriteEnabled: true, sessionWriteCoordinator: $container->get(McpSessionWriteCoordinator::class));
        $suffix = bin2hex(random_bytes(8));
        $familyResult = $registry->call('create_session_family', ['requestId' => 'family-' . $suffix, 'name' => 'Famille test', 'themeUuids' => []]);
        self::assertFalse($familyResult['isError']);
        $family = json_decode($familyResult['content'][0]['text'], true, 512, JSON_THROW_ON_ERROR);
        $sessionResult = $registry->call('create_session_draft', ['requestId' => 'session-' . $suffix, 'title' => 'Séance test', 'date' => '2026-12-01', 'familyUuid' => $family['uuid']]);
        self::assertFalse($sessionResult['isError']);
        $created = json_decode($sessionResult['content'][0]['text'], true, 512, JSON_THROW_ON_ERROR);
        $session = $reader->session($created['uuid']);
        self::assertFalse($session['published']);
        self::assertSame($family['uuid'], $session['familyUuid']);
        $result = $registry->call('add_session_free_element', ['uuid' => $created['uuid'], 'revision' => $session['revision'], 'title' => 'Jeu libre', 'type' => 'free', 'body' => 'Explorer']);
        self::assertFalse($result['isError'], json_encode($result, JSON_THROW_ON_ERROR));
        $updated = $reader->session($created['uuid']);
        self::assertSame('Jeu libre', $updated['sequences'][0]['title']);
        self::assertTrue($registry->call('update_session_content', ['uuid' => $created['uuid'], 'revision' => $updated['revision'], 'changes' => ['organizations' => []]])['isError']);
        self::assertTrue($registry->call('update_session_content', ['uuid' => $created['uuid'], 'revision' => $session['revision'], 'changes' => ['generalNotes' => 'Ancien']])['isError']);
        $sessions = $container->get(SessionSummaryRepositoryInterface::class);
        $published = $sessions->findByUuid(Uuid::fromString($created['uuid']));
        self::assertNotNull($published);
        $published->setPublished(true);
        $sessions->save($published, false);
        self::assertTrue($registry->call('update_session_content', ['uuid' => $created['uuid'], 'revision' => $updated['revision'], 'changes' => ['generalNotes' => 'Interdit']])['isError']);
        self::assertNull($sessions->findByUuid(Uuid::fromString($created['uuid']))?->getGeneralNotes());
    }

    public function testReadsCurrentLinkedRepertoireContent(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $writer = $container->get(McpSessionWriter::class);
        $reader = $container->get(McpSessionReader::class);
        $repertoire = $container->get(RepertoireItemRepositoryInterface::class);
        $item = new RepertoireItem(RepertoireItemType::NURSERY_RHYME, 'Ancien titre', contentBlocks: [new RepertoireBlock(RepertoireBlockKind::LINE, 'Anciennes paroles')]);
        $repertoire->save($item);
        $session = $writer->createSession('repertoire-' . bin2hex(random_bytes(8)), 'Séance', '2026-12-01');
        $current = $reader->session($session->getUuid()->toRfc4122());
        $writer->addRepertoireElement($session->getUuid()->toRfc4122(), $current['revision'], $item->getUuid()->toRfc4122());
        $item->updateContent('Titre actuel', null, '', [new RepertoireBlock(RepertoireBlockKind::LINE, 'Paroles actuelles')], null, []);
        $repertoire->save($item);
        $current = $reader->session($session->getUuid()->toRfc4122());
        self::assertSame('Titre actuel', $current['sequences'][0]['title']);
        self::assertSame('Paroles actuelles', $current['sequences'][0]['lyrics']);
    }

    public function testCreationReportsSavedWhenDocumentDispatchFails(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new RuntimeException('Queue unavailable'));
        $coordinator = new DoctrineMcpSessionWriteCoordinator($container->get(EntityManagerInterface::class), $bus);
        $registry = new WriteToolRegistry($this->createStub(MailReader::class), $this->createStub(ProjectFollowupWriter::class), false, mcpSessionWriter: $container->get(McpSessionWriter::class), sessionWriteEnabled: true, sessionWriteCoordinator: $coordinator);
        $result = $registry->call('create_session_draft', ['requestId' => 'queue-failure-' . bin2hex(random_bytes(8)), 'title' => 'Test PDF', 'date' => '2026-12-01']);
        self::assertFalse($result['isError']);
        self::assertStringContainsString('Saved, but PDF scheduling failed', $result['content'][1]['text']);
        $created = json_decode($result['content'][0]['text'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Test PDF', $container->get(McpSessionReader::class)->session($created['uuid'])['title']);
    }
}
