<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

final class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:generate-vapid';
    protected $description = 'Generate a VAPID key pair for local Web Push configuration';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('VAPID_SUBJECT=mailto:local@formaflow.test');
        $this->line('VAPID_PUBLIC_KEY=' . $keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY=' . $keys['privateKey']);
        return self::SUCCESS;
    }
}
