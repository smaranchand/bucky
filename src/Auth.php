<?php

namespace Bucky;

class Auth
{
    public const METHOD_TOKEN = 'token';
    public const METHOD_BASIC = 'basic';

    /**
     * Decide how (or whether) a request authenticated.
     *
     * Kept free of side effects so the decision can be tested directly; check() applies
     * the response behaviour.
     *
     * @param array $server Request superglobal ($_SERVER).
     * @param array $env    Environment ($_ENV).
     * @return string|null METHOD_TOKEN, METHOD_BASIC, or null if unauthenticated.
     */
    public static function authenticate(array $server, array $env): ?string
    {
        $apiToken = $env['BUCKY_API_TOKEN'] ?? '';
        $token = $server['HTTP_X_BUCKY_TOKEN'] ?? '';

        if ($apiToken !== '' && $token !== '' && hash_equals($apiToken, $token)) {
            return self::METHOD_TOKEN;
        }

        // No defaults. An unconfigured password must not authenticate anyone, or an
        // operator who skipped setup is running a guessable instance without knowing it.
        $authUser = $env['BUCKY_AUTH_USER'] ?? '';
        $authPass = $env['BUCKY_AUTH_PASS'] ?? '';

        if ($authUser === '' || $authPass === '') {
            return null;
        }

        $user = $server['PHP_AUTH_USER'] ?? '';
        $pass = $server['PHP_AUTH_PW'] ?? '';

        if (hash_equals($authUser, $user) && hash_equals($authPass, $pass)) {
            return self::METHOD_BASIC;
        }

        return null;
    }

    /**
     * Enforce authentication, terminating the request if it fails.
     *
     * @return string The method that succeeded (METHOD_TOKEN or METHOD_BASIC).
     */
    public static function check(): string
    {
        $method = self::authenticate($_SERVER, $_ENV);

        if ($method === null) {
            header('WWW-Authenticate: Basic realm="Bucky"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'Authentication required.';
            exit;
        }

        return $method;
    }
}
