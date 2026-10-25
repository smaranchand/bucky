<?php

namespace Bucky;

class Csrf
{
    /**
     * Compare a session token against a submitted one.
     *
     * An absent token on either side is a failure, never a match. hash_equals('', '')
     * returns true, so comparing straight through would accept a request that carries
     * no token at all against a session that never issued one.
     */
    public static function matches(?string $session, ?string $submitted): bool
    {
        if ($session === null || $session === '' || $submitted === null || $submitted === '') {
            return false;
        }

        return hash_equals($session, $submitted);
    }
}
