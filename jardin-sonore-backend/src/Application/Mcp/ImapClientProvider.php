<?php

declare(strict_types=1);

namespace App\Application\Mcp;

use Webklex\PHPIMAP\Client;

interface ImapClientProvider
{
    public function client(): Client;
}
