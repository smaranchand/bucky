<?php

namespace Bucky;

/**
 * Outcome of a single check.
 *
 * UNKNOWN is not a soft NO. It means the check did not complete — the request failed,
 * or a precondition for it was never met. A scanner that collapses the two reports
 * coverage it does not have.
 */
final class Finding
{
    public const YES = 'yes';
    public const NO = 'no';
    public const UNKNOWN = 'unknown';
}
