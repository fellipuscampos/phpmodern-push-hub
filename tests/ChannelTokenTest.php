<?php

declare(strict_types=1);

namespace PhpModern\PushHub\Tests;

use PhpModern\PushHub\ChannelToken;
use PHPUnit\Framework\TestCase;

final class ChannelTokenTest extends TestCase
{
    public function test_a_token_issued_for_a_channel_verifies_for_that_channel(): void
    {
        $tokens = new ChannelToken('secret');
        $token = $tokens->issue('orders.42');

        self::assertTrue($tokens->verify('orders.42', $token));
    }

    public function test_a_token_does_not_verify_for_a_different_channel(): void
    {
        $tokens = new ChannelToken('secret');
        $token = $tokens->issue('orders.42');

        self::assertFalse($tokens->verify('orders.99', $token));
    }

    public function test_a_token_signed_with_a_different_secret_does_not_verify(): void
    {
        $token = (new ChannelToken('secret-a'))->issue('orders.42');

        self::assertFalse((new ChannelToken('secret-b'))->verify('orders.42', $token));
    }

    public function test_a_null_or_empty_token_never_verifies(): void
    {
        $tokens = new ChannelToken('secret');

        self::assertFalse($tokens->verify('orders.42', null));
        self::assertFalse($tokens->verify('orders.42', ''));
    }
}
