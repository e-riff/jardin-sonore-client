<?php

declare(strict_types=1);

namespace App\Application\Commercial;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CommercialContactRequestInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $name;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    public string $emailAddress;

    #[Assert\NotBlank]
    #[Assert\Length(max: 10000)]
    public string $message;

    #[Assert\Regex(pattern: '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D')]
    public string $submissionKey;

    #[Assert\Length(max: 255)]
    public string $organizationName;

    #[Assert\Length(max: 255)]
    public string $city;

    #[Assert\Length(max: 64)]
    public string $phone;

    public function __construct(string $name, string $emailAddress, string $message, string $submissionKey, string $organizationName = '', string $city = '', string $phone = '')
    {
        $this->name = trim($name);
        $this->emailAddress = mb_strtolower(trim($emailAddress));
        $this->message = trim($message);
        $this->submissionKey = trim($submissionKey);
        $this->organizationName = trim($organizationName);
        $this->city = trim($city);
        $this->phone = trim($phone);
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            $this->name,
            $this->emailAddress,
            $this->message,
            $this->organizationName,
            $this->city,
            $this->phone,
        ], JSON_THROW_ON_ERROR));
    }
}
