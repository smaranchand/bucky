<?php

namespace Bucky;

/**
 * Outcome of a bucket write check.
 *
 * VULNERABLE and NOT_VULNERABLE keep their historical 'YES'/'NO' storage values so
 * rows written before the indeterminate state existed still render correctly.
 */
final class CheckResult
{
    /** The PoC write succeeded — the bucket accepts writes from us. */
    public const VULNERABLE = 'YES';

    /** S3 explicitly refused the write. This is a real negative. */
    public const NOT_VULNERABLE = 'NO';

    /**
     * The check could not be completed: region redirect, network failure, throttling,
     * or a problem with the scanner's own credentials. Never report this as clean.
     */
    public const INDETERMINATE = 'UNKNOWN';

    /** The bucket does not exist. Not a finding. */
    public const NO_SUCH_BUCKET = 'MISSING';
}
