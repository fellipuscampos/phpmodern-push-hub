<?php

declare(strict_types=1);

namespace PhpModern\PushHub;

/**
 * Publishes an HTML update to the hub from any regular PHP request (a legacy
 * FPM script or a kernel action handler) — a plain HTTP POST, no PHP
 * extension or persistent connection required on the publisher's side.
 */
final class HubClientPublisher
{
    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 8081,
    ) {
    }

    public function publish(string $channel, string $id, string $html): void
    {
        $body = json_encode(['channel' => $channel, 'id' => $id, 'html' => $html], JSON_THROW_ON_ERROR);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents("http://{$this->host}:{$this->port}/publish", false, $context);
    }
}
