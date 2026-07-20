<?php

declare(strict_types=1);

namespace FormaFlow\Reminders\Application;

interface PushGateway
{
    /**
     * @param array<int, array{
     *     endpoint: string,
     *     public_key: string,
     *     auth_token: string,
     *     content_encoding: string
     * }> $subscriptions
     * @param array{title: string, body: string, url: string, tag: string} $payload
     * @return string[] Expired subscription endpoints.
     */
    public function send(array $subscriptions, array $payload): array;
}
