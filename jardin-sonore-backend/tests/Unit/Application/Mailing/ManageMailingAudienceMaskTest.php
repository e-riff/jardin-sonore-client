<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Mailing;

use App\Application\Mailing\DeleteMailingAudienceMask;
use App\Application\Mailing\UpdateMailingAudienceMask;
use App\Domain\Model\Mailing\MailingAudienceMask;
use App\Domain\Model\Mailing\NewsletterAudienceFilter;
use App\Domain\Repository\MailingAudienceMaskRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ManageMailingAudienceMaskTest extends TestCase
{
    public function testUpdatePersistsAllCriteria(): void
    {
        $repository = new InMemoryMailingAudienceMaskRepository();
        $mask = new MailingAudienceMask('Initial', new NewsletterAudienceFilter(includeFreeSubscribers: true));
        $repository->save($mask);
        $filter = new NewsletterAudienceFilter(municipalityInseeCodes: ['42000']);

        (new UpdateMailingAudienceMask($repository))($mask->getUuid(), 'Modifié', $filter, ['42000']);

        self::assertSame('Modifié', $repository->findByUuid($mask->getUuid())?->getName());
        self::assertSame($filter, $repository->findByUuid($mask->getUuid())?->getAudienceFilter());
    }

    public function testDeleteRemovesReusableMask(): void
    {
        $repository = new InMemoryMailingAudienceMaskRepository();
        $mask = new MailingAudienceMask('Initial', new NewsletterAudienceFilter(includeFreeSubscribers: true));
        $repository->save($mask);

        (new DeleteMailingAudienceMask($repository))($mask->getUuid());

        self::assertNull($repository->findByUuid($mask->getUuid()));
    }
}

final class InMemoryMailingAudienceMaskRepository implements MailingAudienceMaskRepositoryInterface
{
    /** @var array<string, MailingAudienceMask> */
    private array $masks = [];

    public function findByUuid(Uuid $uuid): ?MailingAudienceMask
    {
        return $this->masks[$uuid->toRfc4122()] ?? null;
    }

    public function findAllOrderedByUpdatedAtDesc(): array
    {
        return array_values($this->masks);
    }

    public function save(MailingAudienceMask $mailingAudienceMask): void
    {
        $this->masks[$mailingAudienceMask->getUuid()->toRfc4122()] = $mailingAudienceMask;
    }

    public function remove(MailingAudienceMask $mailingAudienceMask): void
    {
        unset($this->masks[$mailingAudienceMask->getUuid()->toRfc4122()]);
    }
}
