<?php

declare(strict_types=1);

namespace App\Tests\Functional\Infrastructure\Command;

use App\Infrastructure\Doctrine\Entity\AddressContactEntity;
use App\Infrastructure\Doctrine\Entity\EmailContactLinkEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\PhoneContactLinkEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportDirectoryEstablishmentsCommandTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testManualReviewPreventsAutomaticMatchingOfAnOtherwiseConfidentCandidate(): void
    {
        self::bootKernel(['environment' => 'test']);
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $organization = (new OrganizationEntity())->setName('MJC Revue du rapprochement')->setWebsiteUrl('https://mjc-review.example.test/');
            $organization->getContactDetails()->addEmailContactLink((new EmailContactLinkEntity())->setEmailAddress('mjc-review-fixture@example.test'));
            $organization->getContactDetails()->addPhoneContactLink((new PhoneContactLinkEntity())->setPhoneNumber('04 76 12 34 56'));
            $organization->getContactDetails()->addAddressContact(
                (new AddressContactEntity())->setAddress('12, rue du Test, 38500 Commune revue')->setPostalCode('38500')->setCity('Commune revue'),
            );
            $entityManager->persist($organization);
            $entityManager->flush();

            $application = new Application(self::$kernel);
            $commandTester = new CommandTester($application->find('app:directory:import-establishments'));
            $arguments = [
                'file' => 'tests/Fixtures/Directory/review-mjc-match.json',
                '--source' => 'mjc-review-fixture',
            ];

            self::assertSame(0, $commandTester->execute($arguments, ['interactive' => false]));
            self::assertMatchesRegularExpression('/Organizations updated\s+1/', $commandTester->getDisplay());

            self::assertSame(0, $commandTester->execute([...$arguments, '--review-matches' => true], ['interactive' => false]));
            self::assertMatchesRegularExpression('/Organizations updated\s+0/', $commandTester->getDisplay());
            self::assertMatchesRegularExpression('/Ignored\s+1/', $commandTester->getDisplay());
        } finally {
            $connection->rollBack();
            $entityManager->clear();
        }
    }
}
