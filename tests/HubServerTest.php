<?php

declare(strict_types=1);

namespace PhpModern\PushHub\Tests;

use PhpModern\PushHub\HubServer;
use PHPUnit\Framework\TestCase;

final class HubServerTest extends TestCase
{
    public function test_parses_channel_from_subscribe_request_line(): void
    {
        self::assertSame(
            'order-status.42',
            HubServer::parseChannel('GET /subscribe?channel=order-status.42 HTTP/1.1'),
        );
    }

    public function test_url_decodes_channel_name(): void
    {
        self::assertSame(
            'order status',
            HubServer::parseChannel('GET /subscribe?channel=order%20status HTTP/1.1'),
        );
    }

    public function test_returns_null_for_non_subscribe_request(): void
    {
        self::assertNull(HubServer::parseChannel('POST /publish HTTP/1.1'));
    }

    public function test_parses_token_from_subscribe_request_line(): void
    {
        self::assertSame(
            'abc123',
            HubServer::parseToken('GET /subscribe?channel=orders.42&token=abc123 HTTP/1.1'),
        );
    }

    public function test_returns_null_when_no_token_is_present(): void
    {
        self::assertNull(HubServer::parseToken('GET /subscribe?channel=orders.42 HTTP/1.1'));
    }

    public function test_token_parses_regardless_of_query_parameter_order(): void
    {
        self::assertSame(
            'abc123',
            HubServer::parseToken('GET /subscribe?token=abc123&channel=orders.42 HTTP/1.1'),
        );
    }
}
