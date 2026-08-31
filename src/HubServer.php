<?php

declare(strict_types=1);

namespace PhpModern\PushHub;

use Closure;
use RuntimeException;

/**
 * Minimal single-process SSE hub for the v0.1 proof of concept.
 *
 * Runs as its own long-lived CLI daemon (never inside a PHP-FPM worker, so a
 * slow/idle SSE connection can never starve the app's request pool). Any app
 * — legacy FPM script or kernel app — talks to it over plain HTTP:
 *
 *   GET  /subscribe?channel=X[&token=Y]   -> upgrades to text/event-stream, kept open
 *   POST /publish                          -> {"channel","id","html"} JSON body, broadcasts
 *
 * A channel is still just a string, but $authorizer (if given) turns it
 * into a private channel: subscribe requests a token, $authorizer decides
 * whether that token may subscribe to that channel — reject with 403
 * before ever upgrading to SSE. ChannelToken is the typed way to issue and
 * check those tokens; $authorizer can also be any other callable, e.g. one
 * backed by a session or database lookup.
 *
 * This is intentionally not a general-purpose HTTP server: it understands
 * only those two requests, enough to prove "server pushes HTML, browser never
 * polls" end to end. A WebSocket driver — genuine bidirectional
 * communication instead of server-to-client-only SSE — was never built: a
 * correct hand-rolled RFC 6455 implementation is real protocol work this
 * project has no way to validate against real browsers/client libraries
 * (unlike everything else here, checked against a real counterpart —
 * Postgres, MySQL, a live SMTP server, a real browser via CDP), and pulling
 * in Swoole would mean a genuinely heavy runtime dependency. Left open
 * honestly rather than shipped unverified.
 */
final class HubServer
{
    /** @var array<string, array<int, resource>> channel => [clientId => stream] */
    private array $subscribers = [];

    /** @var array<int, resource> clientId => stream, for every open connection */
    private array $clients = [];

    /** @var array<int, string> clientId => bytes received so far */
    private array $buffers = [];

    private readonly ?Closure $authorizer;

    /** @param (callable(string $channel, ?string $token): bool)|null $authorizer */
    public function __construct(
        private readonly string $host = '127.0.0.1',
        private readonly int $port = 8081,
        ?callable $authorizer = null,
    ) {
        $this->authorizer = $authorizer === null ? null : Closure::fromCallable($authorizer);
    }

    public function run(): void
    {
        $server = stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errstr);

        if ($server === false) {
            throw new RuntimeException("Unable to start push hub on {$this->host}:{$this->port}: {$errstr}");
        }

        stream_set_blocking($server, false);
        fwrite(STDOUT, "push-hub listening on http://{$this->host}:{$this->port}\n");

        while (true) {
            $read = array_merge([$server], array_values($this->clients));
            $write = [];
            $except = [];

            if (stream_select($read, $write, $except, 1) === false) {
                continue;
            }

            foreach ($read as $stream) {
                if ($stream === $server) {
                    $this->acceptClient($server);
                    continue;
                }

                $this->handleClientData($stream);
            }
        }
    }

    /** @param resource $server */
    private function acceptClient($server): void
    {
        $client = @stream_socket_accept($server, 0);

        if ($client === false) {
            return;
        }

        stream_set_blocking($client, false);
        $id = get_resource_id($client);
        $this->clients[$id] = $client;
        $this->buffers[$id] = '';
    }

    /** @param resource $client */
    private function handleClientData($client): void
    {
        $id = get_resource_id($client);
        $chunk = @fread($client, 8192);

        if ($chunk === false) {
            $this->dropClient($id);

            return;
        }

        if ($chunk === '') {
            if (feof($client)) {
                $this->dropClient($id);
            }

            return;
        }

        $this->buffers[$id] .= $chunk;

        if (!str_contains($this->buffers[$id], "\r\n\r\n")) {
            return;
        }

        [$head, $rest] = explode("\r\n\r\n", $this->buffers[$id], 2);
        $lines = explode("\r\n", $head);
        $requestLine = $lines[0];

        if (str_starts_with($requestLine, 'GET /subscribe')) {
            $this->handleSubscribe($id, $client, $requestLine);

            return;
        }

        if (str_starts_with($requestLine, 'POST /publish')) {
            $contentLength = self::headerValue($lines, 'Content-Length');

            if ($contentLength !== null && strlen($rest) < (int) $contentLength) {
                return; // wait for the rest of the body on the next readable event
            }

            $this->handlePublish($client, $rest);
            $this->dropClient($id);

            return;
        }

        $this->writeAndClose($client, "HTTP/1.1 404 Not Found\r\nConnection: close\r\n\r\n");
        $this->dropClient($id);
    }

    /** @param resource $client */
    private function handleSubscribe(int $id, $client, string $requestLine): void
    {
        $channel = self::parseChannel($requestLine);

        if ($channel === null) {
            $this->writeAndClose($client, "HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n");
            $this->dropClient($id);

            return;
        }

        if ($this->authorizer !== null && !($this->authorizer)($channel, self::parseToken($requestLine))) {
            $this->writeAndClose($client, "HTTP/1.1 403 Forbidden\r\nConnection: close\r\n\r\n");
            $this->dropClient($id);

            return;
        }

        fwrite(
            $client,
            "HTTP/1.1 200 OK\r\n" .
            "Content-Type: text/event-stream\r\n" .
            "Cache-Control: no-cache\r\n" .
            "Connection: keep-alive\r\n" .
            "Access-Control-Allow-Origin: *\r\n\r\n",
        );

        $this->subscribers[$channel][$id] = $client;
        $this->buffers[$id] = '';
    }

    /** @param resource $client */
    private function handlePublish($client, string $body): void
    {
        $payload = json_decode($body, true);

        if (!is_array($payload) || !isset($payload['channel'])) {
            $this->writeAndClose($client, "HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n");

            return;
        }

        $channel = (string) $payload['channel'];
        $message = json_encode([
            'id' => $payload['id'] ?? null,
            'html' => $payload['html'] ?? '',
            'reload' => (bool) ($payload['reload'] ?? false),
        ]);

        foreach ($this->subscribers[$channel] ?? [] as $subscriberId => $subscriber) {
            $sent = @fwrite($subscriber, "data: {$message}\n\n");

            if ($sent === false) {
                unset($this->subscribers[$channel][$subscriberId]);
            }
        }

        $this->writeAndClose($client, "HTTP/1.1 204 No Content\r\nConnection: close\r\n\r\n");
    }

    public static function parseChannel(string $requestLine): ?string
    {
        if (preg_match('#^GET /subscribe\?channel=([^\s&]+)#', $requestLine, $matches) !== 1) {
            return null;
        }

        return urldecode($matches[1]);
    }

    public static function parseToken(string $requestLine): ?string
    {
        if (preg_match('#[?&]token=([^\s&]+)#', $requestLine, $matches) !== 1) {
            return null;
        }

        return urldecode($matches[1]);
    }

    /** @param array<int, string> $lines */
    private static function headerValue(array $lines, string $name): ?string
    {
        foreach ($lines as $line) {
            if (stripos($line, $name . ':') === 0) {
                return trim(substr($line, strlen($name) + 1));
            }
        }

        return null;
    }

    /** @param resource $client */
    private function writeAndClose($client, string $response): void
    {
        @fwrite($client, $response);
    }

    private function dropClient(int $id): void
    {
        if (isset($this->clients[$id])) {
            @fclose($this->clients[$id]);
            unset($this->clients[$id]);
        }

        unset($this->buffers[$id]);

        foreach (array_keys($this->subscribers) as $channel) {
            unset($this->subscribers[$channel][$id]);
        }
    }
}
