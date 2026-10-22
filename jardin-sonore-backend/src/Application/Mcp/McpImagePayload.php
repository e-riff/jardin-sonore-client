<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class McpImagePayload
{
    private const int MAX_BYTES = 5 * 1024 * 1024;
    private const int MAX_BASE64_LENGTH = 4 * 1_747_627;

    public function __construct(private ValidatorInterface $validator)
    {
    }

    /** @template T
     * @param callable(UploadedFile): T $operation
     *
     * @return T
     */
    public function withUploadedFile(string $data, string $filename, callable $operation): mixed
    {
        if ('' === $data || self::MAX_BASE64_LENGTH < strlen($data) || 1 !== preg_match('/^[A-Za-z0-9+\/]*={0,2}$/D', $data)) {
            throw new InvalidArgumentException('Invalid image Base64 payload or image exceeds 5 MB.');
        }

        $bytes = base64_decode($data, true);
        if (false === $bytes || '' === $bytes || self::MAX_BYTES < strlen($bytes) || false === getimagesizefromstring($bytes)) {
            throw new InvalidArgumentException('Image is empty, malformed, or exceeds 5 MB.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'jardin-mcp-image-');
        if (false === $temporaryPath) {
            throw new RuntimeException('Unable to prepare image upload.');
        }

        try {
            if (strlen($bytes) !== file_put_contents($temporaryPath, $bytes)) {
                throw new RuntimeException('Unable to write temporary image.');
            }
            $uploadedFile = new UploadedFile($temporaryPath, basename($filename), test: true);
            $violations = $this->validator->validate($uploadedFile, new Assert\Image(
                maxSize: '5M',
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            ));
            if (0 < count($violations)) {
                throw new InvalidArgumentException('Image must be a valid JPEG, PNG or WebP of 5 MB or less.');
            }

            return $operation($uploadedFile);
        } finally {
            if (is_file($temporaryPath)) {
                unlink($temporaryPath);
            }
        }
    }
}
