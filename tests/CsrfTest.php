<?php

namespace Bucky\Tests;

use Bucky\Csrf;
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    public function testMatchingTokensAreAccepted(): void
    {
        $this->assertTrue(Csrf::matches('abc123', 'abc123'));
    }

    public function testDifferentTokensAreRejected(): void
    {
        $this->assertFalse(Csrf::matches('abc123', 'wrong'));
    }

    /**
     * hash_equals('', '') is true. A request with no session token and no submitted
     * token must not be treated as a valid match.
     */
    public function testTwoEmptyTokensAreRejected(): void
    {
        $this->assertFalse(Csrf::matches('', ''));
    }

    public function testMissingSubmittedTokenIsRejected(): void
    {
        $this->assertFalse(Csrf::matches('abc123', ''));
        $this->assertFalse(Csrf::matches('abc123', null));
    }

    public function testMissingSessionTokenIsRejected(): void
    {
        $this->assertFalse(Csrf::matches('', 'abc123'));
        $this->assertFalse(Csrf::matches(null, 'abc123'));
    }
}
