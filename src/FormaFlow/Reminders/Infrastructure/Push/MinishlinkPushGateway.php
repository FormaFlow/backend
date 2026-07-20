<?php

declare(strict_types=1);

namespace FormaFlow\Reminders\Infrastructure\Push;

use FormaFlow\Reminders\Application\PushGateway;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

final class MinishlinkPushGateway implements PushGateway
{
    public function send(array $subscriptions, array $payload): array
    {
        if ($subscriptions === []) {
            return [];
        }

        $publicKey = (string)config('webpush.vapid.public_key');
        $privateKey = (string)config('webpush.vapid.private_key');
        if ($publicKey === '' || $privateKey === '') {
            throw new RuntimeException('Web Push VAPID keys are not configured.');
        }

        $webPush = new WebPush(
            ['VAPID' => [
                'subject' => (string)config('webpush.vapid.subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ]],
            ['TTL' => 86400, 'urgency' => 'normal'],
            timeout: 10,
        );
        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(new Subscription(
                endpoint: $subscription['endpoint'],
                publicKey: $subscription['public_key'],
                authToken: $subscription['auth_token'],
                contentEncoding: $subscription['content_encoding'],
            ), $encodedPayload, ['topic' => substr(hash('sha256', $payload['tag']), 0, 32)]);
        }

        $expiredEndpoints = [];
        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                $expiredEndpoints[] = $report->getEndpoint();
                continue;
            }
            if (!$report->isSuccess()) {
                Log::warning('Web Push delivery failed', [
                    'endpoint_hash' => hash('sha256', $report->getEndpoint()),
                    'reason' => $report->getReason(),
                ]);
            }
        }

        return $expiredEndpoints;
    }
}
