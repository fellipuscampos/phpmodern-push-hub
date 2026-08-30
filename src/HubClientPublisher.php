<?php

declare(strict_types=1);

namespace PhpModern\PushHub;

/**
 * Publishes an update to the hub from any regular PHP request (a legacy
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
        $this->send(['channel' => $channel, 'id' => $id, 'html' => $html]);
    }

    /**
     * Tells every browser subscribed to $channel to do a full page reload —
     * used by the dev-server's file watcher for hot reload, not by
     * component updates (those use publish() to morph in place instead).
     */
    public function publishReload(string $channel): void
    {
        $this->send(['channel' => $channel, 'reload' => true]);
    }

    /** @param array<string, mixed> $payload */
    private function send(array $payload): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

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
