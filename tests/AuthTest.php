<?php

namespace Bucky\Tests;

use Bucky\Auth;
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    public function testAcceptsValidApiToken(): void
    {
        $method = Auth::authenticate(
            ['HTTP_X_BUCKY_TOKEN' => 'sekrit'],
            ['BUCKY_API_TOKEN' => 'sekrit', 'BUCKY_AUTH_USER' => 'admin', 'BUCKY_AUTH_PASS' => 'pw']
        );

        $this->assertSame(Auth::METHOD_TOKEN, $method);
    }

    public function testRejectsWrongApiToken(): void
    {
        $method = Auth::authenticate(
            ['HTTP_X_BUCKY_TOKEN' => 'wrong'],
            ['BUCKY_API_TOKEN' => 'sekrit', 'BUCKY_AUTH_USER' => 'admin', 'BUCKY_AUTH_PASS' => 'pw']
        );

        $this->assertNull($method);
    }

    public function testAcceptsValidBasicCredentials(): void
    {
        $method = Auth::authenticate(
            ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'pw'],
            ['BUCKY_AUTH_USER' => 'admin', 'BUCKY_AUTH_PASS' => 'pw']
        );

        $this->assertSame(Auth::METHOD_BASIC, $method);
    }

    public function testRejectsWrongPassword(): void
    {
        $method = Auth::authenticate(
            ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'nope'],
            ['BUCKY_AUTH_USER' => 'admin', 'BUCKY_AUTH_PASS' => 'pw']
        );

        $this->assertNull($method);
    }

    public function testRejectsRequestWithNoCredentials(): void
    {
        $this->assertNull(Auth::authenticate([], ['BUCKY_AUTH_USER' => 'admin', 'BUCKY_AUTH_PASS' => 'pw']));
    }

    /**
     * Falling back to a built-in default password means an operator who never set one
     * is silently running a guessable instance. Fail closed instead.
     */
    public function testFailsClosedWhenPasswordIsNotConfigured(): void
    {
        $method = Auth::authenticate(
            ['PHP_AUTH_USER' => 'admin', 'PHP_AUTH_PW' => 'bucky_changeme'],
            ['BUCKY_AUTH_USER' => 'admin']
        );

        $this->assertNull($method);
    }

    public function testEmptyTokenHeaderDoesNotMatchUnconfiguredToken(): void
    {
        $method = Auth::authenticate(
            ['HTTP_X_BUCKY_TOKEN' => ''],
            ['BUCKY_API_TOKEN' => '', 'BUCKY_AUTH_USER' => 'admin', 'BUCKY_AUTH_PASS' => 'pw']
        );

        $this->assertNull($method);
    }
}
