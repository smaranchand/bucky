<?php

namespace Bucky;

/**
 * Discovers which region a bucket actually lives in.
 *
 * S3 buckets are globally addressable but regionally hosted, and a request signed for
 * the wrong region is rejected rather than transparently forwarded. S3 reports the home
 * region in the x-amz-bucket-region header without requiring credentials.
 */
class RegionResolver
{
    private int $timeout;

    /** @var callable(string): (string|false) */
    private $fetcher;

    /**
     * @param (callable(string): (string|false))|null $fetcher Returns the raw HTTP
     *        response for a bucket probe, or false on failure. Injected by tests.
     */
    public function __construct(int $timeout = 5, ?callable $fetcher = null)
    {
        $this->timeout = $timeout;
        $this->fetcher = $fetcher ?? [$this, 'fetch'];
    }

    /**
     * @return string|null The bucket's region, or null if it could not be determined.
     */
    public function resolve(string $bucket): ?string
    {
        $response = ($this->fetcher)($bucket);

        return is_string($response) ? self::parseRegionHeader($response) : null;
    }

    /**
     * Path-style addressing is deliberate. The virtual-host form for a bucket whose name
     * contains a dot (flaws.cloud.s3.amazonaws.com) does not match the
     * *.s3.amazonaws.com wildcard certificate and fails TLS verification outright.
     *
     * max-keys=0 keeps the response body empty on buckets that permit listing.
     */
    public static function probeUrl(string $bucket): string
    {
        return 'https://s3.amazonaws.com/' . rawurlencode($bucket) . '/?max-keys=0';
    }

    private function fetch(string $bucket): string|false
    {
        $ch = curl_init(self::probeUrl($bucket));
        if ($ch === false) {
            return false;
        }

        curl_setopt_array($ch, [
            // A GET is required: S3 answers a HEAD on this endpoint with a bare 404 that
            // carries no x-amz-bucket-region header.
            CURLOPT_NOBODY         => false,
            CURLOPT_HEADER         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $response = curl_exec($ch);

        return is_string($response) ? $response : false;
    }

    /**
     * Extract the region from a raw HTTP response. Only the header block is searched,
     * so a response body cannot spoof the header.
     */
    public static function parseRegionHeader(string $rawResponse): ?string
    {
        if ($rawResponse === '') {
            return null;
        }

        $headerBlock = preg_split("/\r?\n\r?\n/", $rawResponse, 2)[0];

        // \r must be allowed before the line end: headers arrive CRLF-terminated and
        // PCRE's $ in multiline mode anchors before \n only.
        if (preg_match('/^x-amz-bucket-region:[ \t]*([a-z0-9-]+)[ \t\r]*$/im', $headerBlock, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }
}
