<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use App\Application\Storage\MediaResourceFileStorageInterface;
use App\Domain\Model\ContentCatalog\Theme;
use App\Domain\Model\Session\MediaResource;
use App\Domain\Model\Session\MediaResourceType;
use App\Domain\Model\Session\SessionFamily;
use App\Domain\Model\Session\SessionSequence;
use App\Domain\Model\Session\SessionSequenceMedia;
use App\Domain\Model\Session\SessionSequenceSourceKind;
use App\Domain\Model\Session\SessionSequenceType;
use App\Domain\Model\Session\SessionSummary;
use App\Domain\Repository\InstrumentRepositoryInterface;
use App\Domain\Repository\MediaResourceRepositoryInterface;
use App\Domain\Repository\RepertoireItemRepositoryInterface;
use App\Domain\Repository\SessionFamilyRepositoryInterface;
use App\Domain\Repository\SessionRecommendationRepositoryInterface;
use App\Domain\Repository\SessionSummaryRepositoryInterface;
use App\Domain\Repository\ThemeRepositoryInterface;
use DateTimeImmutable;
use finfo;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;
use Throwable;

final readonly class McpSessionWriter
{
    private const string SESSION_NAMESPACE = '532616c4-651b-40e3-aa75-3f9fc8661c12';
    private const string FAMILY_NAMESPACE = 'd4216fa0-e4ea-4b75-af6f-b1d602c1db52';
    private const string MEDIA_NAMESPACE = 'e17279d5-492f-4d83-9579-f83259129342';
    private const int MAX_FILE_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private SessionFamilyRepositoryInterface $families,
        private SessionSummaryRepositoryInterface $sessions,
        private ?ThemeRepositoryInterface $themes = null,
        private ?RepertoireItemRepositoryInterface $repertoire = null,
        private ?MediaResourceRepositoryInterface $media = null,
        private ?MediaResourceFileStorageInterface $fileStorage = null,
        private ?InstrumentRepositoryInterface $instruments = null,
        private ?SessionRecommendationRepositoryInterface $recommendations = null,
    ) {
    }

    public function createFileMedia(string $requestId, string $title, string $type, string $filename, string $base64): MediaResource
    {
        if (null === $this->media || null === $this->fileStorage || '' === trim($requestId) || 128 < strlen($requestId) || '' === trim($title) || 255 < mb_strlen($title) || '' === $filename || 255 < mb_strlen($filename) || basename($filename) !== $filename || '' === $base64 || 6990508 < strlen($base64)) {
            throw new InvalidArgumentException('Invalid file arguments.');
        }
        $mediaType = MediaResourceType::tryFrom($type) ?? throw new InvalidArgumentException('Invalid media type.');
        $bytes = base64_decode($base64, true);
        if (false === $bytes || '' === $bytes || self::MAX_FILE_BYTES < strlen($bytes) || base64_encode($bytes) !== $base64) {
            throw new InvalidArgumentException('Invalid Base64 file or file exceeds 5 MiB.');
        }
        $uuid = Uuid::v5(Uuid::fromString(self::MEDIA_NAMESPACE), $requestId);
        if (null !== $this->media->findByUuid($uuid)) {
            throw new InvalidArgumentException('Request ID already used; search for the existing media.');
        }
        $stream = tmpfile();
        if (false === $stream) {
            throw new RuntimeException('Unable to prepare upload.');
        }
        try {
            if (strlen($bytes) !== fwrite($stream, $bytes)) {
                throw new RuntimeException('Unable to write upload.');
            }
            $path = stream_get_meta_data($stream)['uri'];
            $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($path);
            if (!in_array($mimeType, ['application/pdf', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/flac', 'audio/x-flac', 'image/jpeg', 'image/png', 'image/webp', 'video/mp4'], true)) {
                throw new InvalidArgumentException('Unsupported file content.');
            }
            $storedPath = $this->fileStorage->storePrimaryFile(new UploadedFile($path, $filename, test: true));
            try {
                $media = new MediaResource($mediaType, trim($title), $storedPath, uuid: $uuid);
                $this->media->save($media);
            } catch (Throwable $exception) {
                $this->fileStorage->delete($storedPath);
                throw $exception;
            }

            return $media;
        } finally {
            fclose($stream);
        }
    }

    /** @param list<string> $themeUuids */
    public function createFamily(string $requestId, string $name, array $themeUuids): SessionFamily
    {
        if ('' === trim($requestId) || 128 < strlen($requestId)) {
            throw new InvalidArgumentException('Invalid request ID.');
        }
        $themes = $this->resolveThemes($themeUuids);
        $uuid = Uuid::v5(Uuid::fromString(self::FAMILY_NAMESPACE), $requestId);
        $existing = $this->families->findByUuid($uuid);
        if (null !== $existing) {
            if ($existing->name !== trim($name) || self::familyRevision($existing) !== self::familyRevision(new SessionFamily($name, $themes, $uuid))) {
                throw new InvalidArgumentException('Request ID already used with different data.');
            }

            return $existing;
        }
        $family = new SessionFamily($name, $themes, $uuid);
        $this->families->save($family);

        return $family;
    }

    /** @param list<string> $themeUuids */
    public function updateFamily(string $uuid, string $name, array $themeUuids, string $revision): void
    {
        if (!Uuid::isValid($uuid)) {
            throw new InvalidArgumentException('Invalid family UUID.');
        }
        $id = Uuid::fromString($uuid);
        $family = $this->families->findByUuid($id) ?? throw new InvalidArgumentException('Family not found.');
        if (!hash_equals(self::familyRevision($family), $revision)) {
            throw new InvalidArgumentException('Family changed since it was read.');
        }
        $this->families->save(new SessionFamily($name, $this->resolveThemes($themeUuids), $id));
    }

    public static function familyRevision(SessionFamily $family): string
    {
        $ids = array_map(static fn ($theme): string => $theme->getUuid()->toRfc4122(), $family->themes);
        sort($ids);

        return hash('sha256', serialize([$family->uuid->toRfc4122(), $family->name, $ids]));
    }

    /** @param list<string> $uuids
     * @return list<Theme>
     */
    private function resolveThemes(array $uuids): array
    {
        if (50 < count($uuids)) {
            throw new InvalidArgumentException('Too many themes.');
        }
        $result = [];
        foreach (array_unique($uuids) as $uuid) {
            if (!is_string($uuid) || !Uuid::isValid($uuid) || null === $this->themes) {
                throw new InvalidArgumentException('Invalid theme UUID.');
            }
            $result[] = $this->themes->findByUuid(Uuid::fromString($uuid)) ?? throw new InvalidArgumentException('Theme not found.');
        }

        return $result;
    }

    public function createSession(string $requestId, string $title, string $date, ?string $familyUuid = null): SessionSummary
    {
        if ('' === trim($requestId) || 128 < strlen($requestId) || '' === trim($title) || 255 < mb_strlen(trim($title))) {
            throw new InvalidArgumentException('Invalid request ID or title.');
        }
        $sessionDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (false === $sessionDate || $sessionDate->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Invalid date.');
        }
        $familyId = null;
        if (null !== $familyUuid) {
            if (!Uuid::isValid($familyUuid)) {
                throw new InvalidArgumentException('Invalid family UUID.');
            }
            $familyId = Uuid::fromString($familyUuid);
            if (null === $this->families->findByUuid($familyId)) {
                throw new InvalidArgumentException('Family not found.');
            }
        }
        $uuid = Uuid::v5(Uuid::fromString(self::SESSION_NAMESPACE), $requestId);
        $existing = $this->sessions->findByUuid($uuid);
        if (null !== $existing) {
            if ($existing->getTitle() !== trim($title) || $existing->getSessionDate()->format('Y-m-d') !== $date || $existing->getFamilyUuid()?->toRfc4122() !== $familyId?->toRfc4122()) {
                throw new InvalidArgumentException('Request ID already used with different data.');
            }

            return $existing;
        }
        $session = new SessionSummary(trim($title), $sessionDate, uuid: $uuid, familyUuid: $familyId);
        $this->sessions->save($session, false);

        return $session;
    }

    public function updateSession(string $uuid, string $title, string $date, string $revision): void
    {
        $session = $this->editableSession($uuid, $revision);
        $sessionDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (false === $sessionDate || $sessionDate->format('Y-m-d') !== $date || '' === trim($title) || 255 < mb_strlen(trim($title))) {
            throw new InvalidArgumentException('Invalid title or date.');
        }
        $session->updateDetails(
            title: trim($title),
            sessionDate: $sessionDate,
            organizations: $session->getOrganizations(),
            theme: $session->getTheme(),
            generalNotes: $session->getGeneralNotes(),
            materialSummary: $session->getMaterialSummary(),
            furtherExploration: $session->getFurtherExploration(),
            instrumentUuids: $session->getInstrumentUuids(),
            recommendationUuids: $session->getRecommendationUuids(),
            themes: $session->getThemes(),
        );
        $this->sessions->save($session, false);
    }

    public function assignFamily(string $uuid, ?string $familyUuid, string $revision): void
    {
        $session = $this->editableSession($uuid, $revision);
        $familyId = null;
        if (null !== $familyUuid) {
            if (!Uuid::isValid($familyUuid)) {
                throw new InvalidArgumentException('Invalid family UUID.');
            }
            $familyId = Uuid::fromString($familyUuid);
            if (null === $this->families->findByUuid($familyId)) {
                throw new InvalidArgumentException('Family not found.');
            }
        }
        $session->setFamilyUuid($familyId);
        $this->sessions->save($session, false);
    }

    /** @param array<string, mixed> $changes */
    public function updateSessionContent(string $uuid, string $revision, array $changes): void
    {
        $session = $this->editableSession($uuid, $revision);
        $allowed = ['generalNotes', 'materialSummary', 'furtherExploration', 'themeUuids', 'instrumentUuids', 'recommendationUuids'];
        if ([] === $changes || [] !== array_diff(array_keys($changes), $allowed)) {
            throw new InvalidArgumentException('Invalid session changes.');
        }
        foreach (['generalNotes', 'materialSummary', 'furtherExploration'] as $key) {
            if (array_key_exists($key, $changes) && null !== $changes[$key] && (!is_string($changes[$key]) || 20000 < mb_strlen($changes[$key]))) {
                throw new InvalidArgumentException('Invalid session text.');
            }
        }
        foreach (['themeUuids', 'instrumentUuids', 'recommendationUuids'] as $key) {
            if (!array_key_exists($key, $changes)) {
                continue;
            }
            if (!is_array($changes[$key]) || !array_is_list($changes[$key]) || 50 < count($changes[$key])) {
                throw new InvalidArgumentException('Invalid session references.');
            }
            foreach ($changes[$key] as $id) {
                if (!is_string($id) || !Uuid::isValid($id)) {
                    throw new InvalidArgumentException('Invalid session reference UUID.');
                }
                $referenceUuid = Uuid::fromString($id);
                $reference = match ($key) {
                    'themeUuids' => $this->themes?->findByUuid($referenceUuid),
                    'instrumentUuids' => $this->instruments?->findByUuid($referenceUuid),
                    'recommendationUuids' => $this->recommendations?->findByUuid($referenceUuid),
                };
                if (null === $reference) {
                    throw new InvalidArgumentException('Session reference not found.');
                }
            }
        }
        $session->updateDetails(
            title: $session->getTitle(), sessionDate: $session->getSessionDate(), organizations: $session->getOrganizations(), theme: $session->getTheme(),
            generalNotes: array_key_exists('generalNotes', $changes) ? $changes['generalNotes'] : $session->getGeneralNotes(),
            materialSummary: array_key_exists('materialSummary', $changes) ? $changes['materialSummary'] : $session->getMaterialSummary(),
            furtherExploration: array_key_exists('furtherExploration', $changes) ? $changes['furtherExploration'] : $session->getFurtherExploration(),
            instrumentUuids: $changes['instrumentUuids'] ?? $session->getInstrumentUuids(),
            recommendationUuids: $changes['recommendationUuids'] ?? $session->getRecommendationUuids(),
            themes: isset($changes['themeUuids']) ? $this->resolveThemes($changes['themeUuids']) : $session->getThemes(),
        );
        $this->sessions->save($session, false);
    }

    public function addFreeElement(string $uuid, string $revision, string $title, string $type, string $body, ?string $lyrics = null, ?string $gestures = null, ?string $role = null): SessionSequence
    {
        $session = $this->editableSession($uuid, $revision);
        $sequenceType = SessionSequenceType::tryFrom($type);
        if (null === $sequenceType || '' === trim($title) || 255 < mb_strlen($title) || 20000 < mb_strlen($body) || (null !== $lyrics && 20000 < mb_strlen($lyrics)) || (null !== $gestures && 20000 < mb_strlen($gestures)) || (null !== $role && 255 < mb_strlen($role))) {
            throw new InvalidArgumentException('Invalid element.');
        }
        $sequence = new SessionSequence(
            uuid: Uuid::v4(), type: $sequenceType, title: trim($title), subtitle: null,
            body: $body, lyrics: $lyrics, gestures: $gestures,
            primaryUrl: null, secondaryUrl: null, imageUrl: null, showLyricsByDefault: false, role: $role,
        );
        $session->addSequence($sequence);
        $this->sessions->save($session, false);

        return $sequence;
    }

    public function addRepertoireElement(string $uuid, string $revision, string $repertoireUuid): SessionSequence
    {
        $session = $this->editableSession($uuid, $revision);
        if (!Uuid::isValid($repertoireUuid) || null === $this->repertoire) {
            throw new InvalidArgumentException('Invalid repertoire UUID.');
        }
        $item = $this->repertoire->findByUuid(Uuid::fromString($repertoireUuid));
        if (null === $item || !$item->isActive()) {
            throw new InvalidArgumentException('Repertoire item not found or inactive.');
        }
        $sequence = new SessionSequence(
            uuid: Uuid::v4(), type: SessionSequenceType::fromRepertoireItemType($item->getType()),
            title: $item->getTitle(), subtitle: $item->getSource(), body: '',
            lyrics: $item->getLyrics(), gestures: $item->getGestures(), primaryUrl: null,
            secondaryUrl: null, imageUrl: null, showLyricsByDefault: false,
            sourceUuid: $item->getUuid(), sourceKind: SessionSequenceSourceKind::REPERTOIRE_ITEM, sourceTitle: $item->getTitle(),
        );
        $session->addSequence($sequence);
        $this->sessions->save($session, false);

        return $sequence;
    }

    public function copyElement(string $uuid, string $revision, string $sourceSessionUuid, string $sourceSequenceUuid, string $sourceRevision): SessionSequence
    {
        $target = $this->editableSession($uuid, $revision);
        if (!Uuid::isValid($sourceSessionUuid) || !Uuid::isValid($sourceSequenceUuid)) {
            throw new InvalidArgumentException('Invalid source UUID.');
        }
        $source = $this->sessions->findByUuid(Uuid::fromString($sourceSessionUuid)) ?? throw new InvalidArgumentException('Source session not found.');
        if (!hash_equals(self::revision($source), $sourceRevision)) {
            throw new InvalidArgumentException('Source session changed since it was read.');
        }
        foreach ($source->getSequences() as $sequence) {
            if ($sequence->uuid->toRfc4122() !== $sourceSequenceUuid) {
                continue;
            }
            $payload = $sequence->toArray();
            $payload['uuid'] = Uuid::v4()->toRfc4122();
            if (null === $sequence->sourceUuid) {
                $payload['originUuid'] = ($sequence->originUuid ?? $sequence->uuid)->toRfc4122();
            }
            $copy = SessionSequence::fromArray($payload);
            $target->addSequence($copy);
            $this->sessions->save($target, false);

            return $copy;
        }

        throw new InvalidArgumentException('Source element not found.');
    }

    /** @param array<string, mixed> $changes */
    public function updateElement(string $uuid, string $revision, string $sequenceUuid, array $changes): void
    {
        $session = $this->editableSession($uuid, $revision);
        if (!Uuid::isValid($sequenceUuid) || [] === $changes || [] !== array_diff(array_keys($changes), ['title', 'subtitle', 'type', 'body', 'lyrics', 'gestures', 'role', 'showLyricsByDefault'])) {
            throw new InvalidArgumentException('Invalid element changes.');
        }
        foreach ($session->getSequences() as $sequence) {
            if ($sequence->uuid->toRfc4122() !== $sequenceUuid) {
                continue;
            }
            if (null !== $sequence->sourceUuid && [] !== array_intersect(array_keys($changes), ['title', 'subtitle', 'type', 'lyrics', 'gestures'])) {
                throw new InvalidArgumentException('Edit shared catalog content separately.');
            }
            foreach ($changes as $key => $value) {
                if ('showLyricsByDefault' === $key) {
                    if (!is_bool($value)) {
                        throw new InvalidArgumentException('Invalid lyrics visibility.');
                    }
                    continue;
                }
                if ('type' === $key && (!is_string($value) || null === SessionSequenceType::tryFrom($value))) {
                    throw new InvalidArgumentException('Invalid element type.');
                }
                $maxLength = in_array($key, ['title', 'subtitle', 'type', 'role'], true) ? 255 : 20000;
                if ((!is_string($value) && !(null === $value && in_array($key, ['subtitle', 'lyrics', 'gestures', 'role'], true))) || (is_string($value) && mb_strlen($value) > $maxLength) || ('title' === $key && '' === trim((string) $value))) {
                    throw new InvalidArgumentException('Invalid element text.');
                }
            }
            $updated = SessionSequence::fromArray(array_replace($sequence->toArray(), $changes));
            $session->replaceSequence($updated);
            $this->sessions->save($session, false);

            return;
        }

        throw new InvalidArgumentException('Element not found.');
    }

    public function attachMedia(string $uuid, string $revision, string $sequenceUuid, string $mediaUuid): void
    {
        $session = $this->editableSession($uuid, $revision);
        if (!Uuid::isValid($sequenceUuid) || !Uuid::isValid($mediaUuid) || null === $this->media) {
            throw new InvalidArgumentException('Invalid media or element UUID.');
        }
        $media = $this->media->findByUuid(Uuid::fromString($mediaUuid));
        if (null === $media || !$media->isActive()) {
            throw new InvalidArgumentException('Media not found or inactive.');
        }
        foreach ($session->getSequences() as $sequence) {
            if ($sequence->uuid->toRfc4122() !== $sequenceUuid) {
                continue;
            }
            $existing = $sequence->getMedia();
            foreach ($existing as $linked) {
                if ($linked->resourceUuid?->equals($media->getUuid())) {
                    throw new InvalidArgumentException('Media already attached.');
                }
            }
            $existing[] = new SessionSequenceMedia($media->getTitle(), $media->getType(), $media->getPrimaryUrl(), $media->getImageUrl(), [] === $existing, true, $media->getUuid());
            $payload = $sequence->toArray();
            $payload['media'] = array_map(static fn (SessionSequenceMedia $linked): array => $linked->toArray(), $existing);
            $session->replaceSequence(SessionSequence::fromArray($payload));
            $this->sessions->save($session, false);

            return;
        }

        throw new InvalidArgumentException('Element not found.');
    }

    public function attachInstrument(string $uuid, string $revision, string $sequenceUuid, string $instrumentUuid): void
    {
        $session = $this->editableSession($uuid, $revision);
        if (!Uuid::isValid($sequenceUuid) || !Uuid::isValid($instrumentUuid) || null === $this->instruments) {
            throw new InvalidArgumentException('Invalid instrument or element UUID.');
        }
        $instrument = $this->instruments->findByUuid(Uuid::fromString($instrumentUuid));
        if (null === $instrument || !$instrument->isActive()) {
            throw new InvalidArgumentException('Instrument not found or inactive.');
        }
        foreach ($session->getSequences() as $sequence) {
            if ($sequence->uuid->toRfc4122() !== $sequenceUuid) {
                continue;
            }
            if (in_array($instrumentUuid, $sequence->instrumentUuids, true)) {
                throw new InvalidArgumentException('Instrument already attached.');
            }
            $payload = $sequence->toArray();
            $payload['instrumentUuids'] = [...$sequence->instrumentUuids, $instrumentUuid];
            $session->replaceSequence(SessionSequence::fromArray($payload));
            $this->sessions->save($session, false);

            return;
        }

        throw new InvalidArgumentException('Element not found.');
    }

    /** @param list<string> $sequenceUuids */
    public function reorderElements(string $uuid, string $revision, array $sequenceUuids): void
    {
        $session = $this->editableSession($uuid, $revision);
        if (100 < count($sequenceUuids)) {
            throw new InvalidArgumentException('Too many elements.');
        }
        $ids = [];
        foreach ($sequenceUuids as $sequenceUuid) {
            if (!is_string($sequenceUuid) || !Uuid::isValid($sequenceUuid)) {
                throw new InvalidArgumentException('Invalid element UUID.');
            }
            $ids[] = Uuid::fromString($sequenceUuid);
        }
        $session->reorderSequences($ids);
        $this->sessions->save($session, false);
    }

    public function removeElement(string $uuid, string $revision, string $sequenceUuid): void
    {
        $session = $this->editableSession($uuid, $revision);
        if (!Uuid::isValid($sequenceUuid)) {
            throw new InvalidArgumentException('Invalid element UUID.');
        }
        foreach ($session->getSequences() as $sequence) {
            if ($sequence->uuid->toRfc4122() === $sequenceUuid) {
                $session->removeSequence($sequence->uuid);
                $this->sessions->save($session, false);

                return;
            }
        }

        throw new InvalidArgumentException('Element not found.');
    }

    private function editableSession(string $uuid, string $revision): SessionSummary
    {
        if (!Uuid::isValid($uuid)) {
            throw new InvalidArgumentException('Invalid session UUID.');
        }
        $session = $this->sessions->findByUuid(Uuid::fromString($uuid)) ?? throw new InvalidArgumentException('Session not found.');
        if ($session->isPublished()) {
            throw new InvalidArgumentException('Published sessions cannot be edited.');
        }
        if (!hash_equals(self::revision($session), $revision)) {
            throw new InvalidArgumentException('Session changed since it was read.');
        }

        return $session;
    }

    public static function revision(SessionSummary $session): string
    {
        $organizationUuids = array_map(static fn ($organization): string => $organization->getUuid()->toRfc4122(), $session->getOrganizations());
        $themeUuids = array_map(static fn ($theme): string => $theme->getUuid()->toRfc4122(), $session->getThemes());
        sort($organizationUuids);
        sort($themeUuids);

        return hash('sha256', serialize([
            $session->getUuid()->toRfc4122(),
            $session->getTitle(), $session->getSessionDate()->format('Y-m-d'), $session->isPublished(),
            $session->getFamilyUuid()?->toRfc4122(),
            $organizationUuids, $themeUuids,
            $session->getTheme(), $session->getGeneralNotes(), $session->getMaterialSummary(), $session->getFurtherExploration(),
            $session->getInstrumentUuids(), $session->getRecommendationUuids(),
            array_map(static fn ($sequence): array => $sequence->toArray(), $session->getSequences()),
        ]));
    }
}
