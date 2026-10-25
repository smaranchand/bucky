<?php

namespace Bucky;

/**
 * The finding set for one bucket. Immutable.
 */
final class ProbeResult
{
    public function __construct(
        public readonly ?string $region,
        public readonly string $exists,
        public readonly string $listable,
        public readonly string $readable,
        public readonly string $cors,
        public readonly ?string $sampleKey = null,
    ) {
    }

    /**
     * A bucket that is referenced by a live page but does not exist can be registered by
     * someone else, turning the reference into attacker-controlled content.
     *
     * The reference is what makes it a finding. A name that simply does not exist —
     * typed into the manual form, or a typo — is nothing.
     *
     * @param bool $referenced Whether a real page was observed pointing at this bucket.
     */
    public function isTakeoverCandidate(bool $referenced = true): bool
    {
        return $this->exists === Finding::NO && $referenced;
    }

    /**
     * Worst finding present, for sorting and for the dashboard summary.
     */
    public function severity(bool $referenced = true): string
    {
        if ($this->isTakeoverCandidate($referenced)) {
            return 'high';
        }
        if ($this->exists === Finding::NO) {
            return 'none';
        }
        if ($this->listable === Finding::YES || $this->readable === Finding::YES) {
            return 'high';
        }
        if ($this->cors === Finding::YES) {
            return 'medium';
        }
        if ($this->exists === Finding::YES
            && $this->listable === Finding::NO
            && $this->cors === Finding::NO) {
            return 'none';
        }

        return 'unknown';
    }
}
