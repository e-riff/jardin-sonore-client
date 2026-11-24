<?php

declare(strict_types=1);

namespace App\Tests\Functional\Application\Controller;

use App\Infrastructure\Doctrine\Entity\AdminUserEntity;
use App\Infrastructure\Doctrine\Entity\OrganizationEntity;
use App\Infrastructure\Doctrine\Entity\SessionFamilyEntity;
use App\Infrastructure\Doctrine\Entity\SessionSummaryEntity;
use App\Infrastructure\Doctrine\Entity\ThemeEntity;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Uid\Uuid;

final class SessionFamilyControllerTest extends WebTestCase
{
    public function testCreateFamilyFromSessionAndAttachOtherSessions(): void
    {
        $client = static::createClient();
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));
        $admin = (new AdminUserEntity())->setEmail("family-flow-{$suffix}@example.test")->setPassword('unused');
        $first = (new SessionSummaryEntity())->setTitle("Première {$suffix}");
        $second = (new SessionSummaryEntity())->setTitle("Deuxième {$suffix}");
        foreach ([$admin, $first, $second] as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/sessions/' . $first->getUuid()->toRfc4122() . '/edit');
        $link = $crawler->filterXPath('//a[@aria-label="Créer une famille"]')->link();
        $crawler = $client->click($link);
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['session_family[name]'] = "Nouvelle {$suffix}";
        $client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $family = $manager->getRepository(SessionFamilyEntity::class)->findOneBy(['name' => "Nouvelle {$suffix}"]);
        self::assertSame($family->getUuid()->toRfc4122(), $manager->getRepository(SessionSummaryEntity::class)->findOneBy(['uuid' => $first->getUuid()])->getFamily()?->getUuid()->toRfc4122());
        $crawler = $client->followRedirect();
        $attach = $crawler->selectButton('Rattacher les séances')->form();
        $client->request('POST', $attach->getUri(), ['_token' => 'invalid', 'sessions' => [$second->getUuid()->toRfc4122()]]);
        self::assertResponseStatusCodeSame(403);
        $client->request('POST', $attach->getUri(), ['_token' => $attach['_token']->getValue(), 'sessions' => [$first->getUuid()->toRfc4122(), $second->getUuid()->toRfc4122()]]);
        self::assertResponseStatusCodeSame(422);
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($manager->getRepository(SessionSummaryEntity::class)->findOneBy(['uuid' => $second->getUuid()])->getFamily());
        $client->request('POST', $attach->getUri(), ['_token' => $attach['_token']->getValue(), 'sessions' => [$second->getUuid()->toRfc4122()]]);
        self::assertResponseStatusCodeSame(303);
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame($family->getUuid()->toRfc4122(), $manager->getRepository(SessionSummaryEntity::class)->findOneBy(['uuid' => $second->getUuid()])->getFamily()?->getUuid()->toRfc4122());
    }

    /** @param array<string, mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return new Kernel($options['environment'] ?? 'test', $options['debug'] ?? true);
    }

    public function testFamiliesRequireAdminAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/session-families');
        self::assertResponseRedirects('/login');
    }

    public function testCreateFamilyAndAssignMoveAndDetachSession(): void
    {
        $client = static::createClient();
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));
        $admin = (new AdminUserEntity())->setEmail("family-{$suffix}@example.test")->setPassword('unused');
        $theme = (new ThemeEntity())->setLabel("Forêt {$suffix}");
        $other = (new SessionFamilyEntity())->setName("Autre {$suffix}");
        $organization = (new OrganizationEntity())->setName("Structure {$suffix}");
        $session = (new SessionSummaryEntity())->setTitle("Séance {$suffix}")->setPublished(true)->setSequences([['uuid' => Uuid::v4()->toRfc4122(), 'type' => 'free', 'title' => 'Jeu conservé', 'body' => 'Consigne']]);
        $session->replaceOrganizations([$organization]);
        foreach ([$admin, $theme, $other, $organization, $session] as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();
        $uuid = $session->getUuid()->toRfc4122();
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/session-families/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['session_family[name]'] = "Famille {$suffix}";
        $form['session_family[themeUuids]'] = [$theme->getUuid()->toRfc4122()];
        $client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $family = static::getContainer()->get(EntityManagerInterface::class)->getRepository(SessionFamilyEntity::class)->findOneBy(['name' => "Famille {$suffix}"]);
        self::assertInstanceOf(SessionFamilyEntity::class, $family);
        self::assertCount(1, $family->getThemes());
        $crawler = $client->request('GET', '/session-families?query=' . urlencode("Forêt {$suffix}"));
        self::assertStringContainsString("Famille {$suffix}", $crawler->text());
        $crawler = $client->request('GET', '/session-families/' . $family->getUuid()->toRfc4122() . '/edit');
        $renameForm = $crawler->selectButton('Enregistrer')->form();
        $renameForm['session_family[name]'] = "Renommée {$suffix}";
        $client->submit($renameForm);
        self::assertResponseStatusCodeSame(303);
        $family = static::getContainer()->get(EntityManagerInterface::class)->getRepository(SessionFamilyEntity::class)->findOneBy(['name' => "Renommée {$suffix}"]);
        self::assertCount(1, $family->getThemes());
        $crawler = $client->request('GET', "/sessions/{$uuid}/edit");
        self::assertCount(1, $crawler->filterXPath('//select[contains(@name, "[family]")]'));
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($client->getRequest());
        $token = (string) static::getContainer()->get('security.csrf.token_manager')->getToken('session_family_assign_' . $uuid);
        $client->getRequest()->getSession()->save();
        $requestStack->pop();
        $client->request('POST', "/session-families/assign/{$uuid}", ['_token' => 'bad', 'family' => $family->getUuid()->toRfc4122()]);
        self::assertResponseStatusCodeSame(403);
        $client->request('POST', "/session-families/assign/{$uuid}", ['_token' => $token, 'family' => Uuid::v4()->toRfc4122()]);
        self::assertResponseStatusCodeSame(422);
        foreach ([$family->getUuid()->toRfc4122(), $other->getUuid()->toRfc4122(), ''] as $target) {
            $client->request('POST', "/session-families/assign/{$uuid}", ['_token' => $token, 'family' => $target]);
            self::assertResponseStatusCodeSame(303);
            $saved = static::getContainer()->get(EntityManagerInterface::class)->getRepository(SessionSummaryEntity::class)->findOneBy(['uuid' => $session->getUuid()]);
            self::assertSame('' === $target ? null : $target, $saved?->getFamily()?->getUuid()->toRfc4122());
            self::assertTrue($saved->isPublished());
            self::assertCount(1, $saved->getOrganizations());
            self::assertCount(0, $saved->getThemes());
            self::assertSame('Jeu conservé', $saved->getSequences()[0]['title']);
            $list = $client->request('GET', '/sessions?query=' . urlencode("Séance {$suffix}") . '&family=' . urlencode('' === $target ? 'none' : $target));
            self::assertStringContainsString("Séance {$suffix}", $list->text());
        }
        $crawler = $client->request('GET', "/sessions/{$uuid}/edit");
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['session_summary[family]'] = $family->getUuid()->toRfc4122();
        $form['session_summary[title]'] = "Modifiée {$suffix}";
        $client->submit($form);
        self::assertResponseStatusCodeSame(303);
        $saved = static::getContainer()->get(EntityManagerInterface::class)->getRepository(SessionSummaryEntity::class)->findOneBy(['uuid' => $session->getUuid()]);
        self::assertSame($family->getUuid()->toRfc4122(), $saved->getFamily()?->getUuid()->toRfc4122());
        self::assertSame("Modifiée {$suffix}", $saved->getTitle());
    }

    public function testComposeCreatesOneUnpublishedVariantInSelectionOrder(): void
    {
        $client = static::createClient();
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));
        $admin = (new AdminUserEntity())->setEmail("compose-{$suffix}@example.test")->setPassword('unused');
        $family = (new SessionFamilyEntity())->setName("Famille {$suffix}");
        $first = Uuid::v4()->toRfc4122();
        $second = Uuid::v4()->toRfc4122();
        $source = (new SessionSummaryEntity())->setTitle("Source {$suffix}")->setFamily($family)->setSequences([
            ['uuid' => $first, 'type' => 'free', 'title' => 'Un', 'body' => 'Premier', 'lyrics' => "\nBonjour <script>\n", 'gestures' => "Se lever\n\n"],
            ['uuid' => $second, 'type' => 'movement', 'title' => 'Deux', 'body' => 'Second'],
        ]);
        foreach ([$admin, $family, $source] as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();
        $client->loginUser($admin);
        $client->request('GET', '/sessions/' . $source->getUuid()->toRfc4122() . '/sequences/' . $first . '/edit');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString("\n\nBonjour &lt;script&gt;\n</textarea>", (string) $client->getResponse()->getContent());
        $path = '/session-families/' . $family->getUuid()->toRfc4122() . '/compose';
        $crawler = $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filterXPath('//input[@name="selection[]"]'));
        $token = $crawler->filterXPath('//input[@name="_token"]')->attr('value');
        $compositionId = $crawler->filterXPath('//input[@name="compositionId"]')->attr('value');
        $sourceUuid = $source->getUuid()->toRfc4122();
        $payload = ['_token' => $token, 'compositionId' => $compositionId, 'title' => "Copie {$suffix}", 'date' => '2026-12-01', 'selection' => ["{$sourceUuid}:{$second}", "{$sourceUuid}:{$first}"]];
        $invalidPayload = $payload;
        $invalidPayload['title'] = '';
        $invalid = $client->request('POST', $path, $invalidPayload);
        self::assertResponseStatusCodeSame(422);
        self::assertCount(2, $invalid->filterXPath('//input[@name="selection[]" and @checked]'));
        $client->request('POST', $path, $payload);
        self::assertResponseStatusCodeSame(303);
        $location = $client->getResponse()->headers->get('Location');
        $copy = static::getContainer()->get(EntityManagerInterface::class)->getRepository(SessionSummaryEntity::class)->findOneBy(['title' => "Copie {$suffix}"]);
        self::assertInstanceOf(SessionSummaryEntity::class, $copy);
        self::assertFalse($copy->isPublished());
        self::assertSame(['Deux', 'Un'], array_column($copy->getSequences(), 'title'));
        self::assertSame($family->getId(), $copy->getFamily()?->getId());
        $client->request('POST', $path, $payload);
        self::assertResponseRedirects($location, 303);
        self::assertCount(1, static::getContainer()->get(EntityManagerInterface::class)->getRepository(SessionSummaryEntity::class)->findBy(['title' => "Copie {$suffix}"]));
    }

    public function testOnlyKnownOriginsGroupFreeVersions(): void
    {
        $client = static::createClient();
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(4));
        $admin = (new AdminUserEntity())->setEmail("origins-{$suffix}@example.test")->setPassword('unused');
        $family = (new SessionFamilyEntity())->setName("Origines {$suffix}");
        $origin = Uuid::v4()->toRfc4122();
        $session = (new SessionSummaryEntity())->setTitle('Source')->setFamily($family)->setSequences([
            ['uuid' => $origin, 'type' => 'free', 'title' => 'Jeu identique', 'body' => 'Version initiale'],
            ['uuid' => Uuid::v4()->toRfc4122(), 'originUuid' => $origin, 'type' => 'free', 'title' => 'Jeu adapté', 'body' => 'Variante'],
            ['uuid' => Uuid::v4()->toRfc4122(), 'type' => 'free', 'title' => 'Jeu identique', 'body' => 'Ancien élément indépendant'],
        ]);
        foreach ([$admin, $family, $session] as $entity) {
            $manager->persist($entity);
        }
        $manager->flush();
        $client->loginUser($admin);
        $detail = $client->request('GET', '/session-families/' . $family->getUuid()->toRfc4122());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Éléments de la famille', $detail->text());
        self::assertStringContainsString('Jeu adapté', $detail->text());
        $crawler = $client->request('GET', '/session-families/' . $family->getUuid()->toRfc4122() . '/compose');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filterXPath('//fieldset[contains(@class, "family-composer__group")]'));
        self::assertCount(3, $crawler->filterXPath('//input[@name="selection[]"]'));
        self::assertStringContainsString('Jeu adapté', $crawler->text());
    }
}
