<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Application\Mcp\ImapClientProvider;
use App\Application\Mcp\MailReadException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

final readonly class WebklexImapClientProvider implements ImapClientProvider
{
    public function __construct(
        #[Autowire('%app.mcp.imap_host%')]
        private string $host,
        #[Autowire('%app.mcp.imap_port%')]
        private int $port,
        #[Autowire('%app.mcp.imap_username%')]
        private string $username,
        #[Autowire('%app.mcp.imap_password%')]
        private string $password,
    ) {
    }

    public function client(): Client
    {
        if ('' === $this->host || '' === $this->username || '' === $this->password || 1 > $this->port || 65_535 < $this->port) {
            throw new MailReadException('Mailbox read failed.');
        }

        try {
            $clientManager = new ClientManager([
                'default' => 'jardin_sonore',
                'accounts' => [
                    'jardin_sonore' => [
                        'host' => $this->host,
                        'port' => $this->port,
                        'encryption' => 'ssl',
                        'validate_cert' => true,
                        'username' => $this->username,
                        'password' => $this->password,
                        'protocol' => 'imap',
                        'timeout' => 10,
                        'options' => ['debug' => false, 'uid_cache' => true],
                    ],
                ],
            ]);

            return $clientManager->account('jardin_sonore')->connect();
        } catch (Throwable) {
            throw new MailReadException('Mailbox read failed.');
        }
    }
}
