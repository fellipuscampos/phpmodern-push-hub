<?php

declare(strict_types=1);

namespace PhpModern\PushHub;

/**
 * Signs a "this channel may be subscribed to" token with HMAC-SHA256 and a
 * shared secret — the private-channel equivalent of Laravel Echo's channel
 * authorization endpoint, but stateless: no server round trip to check a
 * signature against a database, just a constant-time comparison against a
 * freshly recomputed HMAC.
 */
final class ChannelToken
{
    public function __construct(private readonly string $secret)
    {
    }

    public function issue(string $channel): string
    {
        return hash_hmac('sha256', $channel, $this->secret);
    }

    public function verify(string $channel, ?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        return hash_equals($this->issue($channel), $token);
    }
}
