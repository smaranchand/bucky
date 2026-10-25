<?php

namespace Bucky;

final class Settings
{
    /**
     * Whether a successful probe should immediately attempt the write proof-of-concept.
     *
     * On by default: automatic proof is the tool's purpose. Set BUCKY_AUTO_WRITE=false
     * to probe read-only and drive writes by hand from the dashboard instead.
     *
     * Only an explicit negative turns it off. An unrecognised value keeps the default
     * rather than silently disabling the main behaviour on a typo.
     */
    public static function autoWriteEnabled(array $env): bool
    {
        $value = strtolower(trim((string) ($env['BUCKY_AUTO_WRITE'] ?? '')));

        return !in_array($value, ['false', '0', 'off', 'no'], true);
    }
}
