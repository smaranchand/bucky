<?php

namespace Bucky;

/**
 * Read-only reconnaissance against a bucket.
 *
 * Every request this issues is a GET or HEAD that an ordinary browser could have made
 * while loading the page the bucket was discovered on. Nothing here modifies the target;
 * the write proof-of-concept is a separate, explicitly invoked action.
 */
class BucketProbe
{
    /** Origin used to test whether the bucket reflects arbitrary CORS origins. */
    private const PROBE_ORIGIN = 'https://bucky.invalid';

    /** @var callable(string): ?string */
    private $regionResolver;

    /** @var callable(string, array): ?array */
    private $fetcher;

    /**
     * @param (callable(string): ?string)|null $regionResolver
     * @param (callable(string, array): ?array)|null $fetcher Returns
     *        ['status'=>int,'headers'=>string,'body'=>string] or null if the request failed.
     */
    public function __construct(?callable $regionResolver = null, ?callable $fetcher = null)
    {
        $this->regionResolver = $regionResolver ?? [new RegionResolver(), 'resolve'];
        $this->fetcher = $fetcher ?? [$this, 'fetch'];
    }

    public function run(string $bucket): ProbeResult
    {
        $region = ($this->regionResolver)($bucket);

        // No region means the bucket did not answer as an existing bucket. Nothing
        // downstream was measured, so those checks stay unknown rather than negative.
        if ($region === null) {
            return new ProbeResult(null, Finding::NO, Finding::UNKNOWN, Finding::UNKNOWN, Finding::UNKNOWN);
        }

        $listing = ($this->fetcher)($this->listUrl($bucket, $region), ['Origin' => self::PROBE_ORIGIN]);

        if ($listing === null) {
            return new ProbeResult($region, Finding::YES, Finding::UNKNOWN, Finding::UNKNOWN, Finding::UNKNOWN);
        }

        $cors = $this->corsFinding($listing['headers'] ?? '');
        $listable = ($listing['status'] ?? 0) === 200 ? Finding::YES : Finding::NO;

        if ($listable === Finding::NO) {
            return new ProbeResult($region, Finding::YES, Finding::NO, Finding::UNKNOWN, $cors);
        }

        $sampleKey = self::firstKey($listing['body'] ?? '');

        // An empty bucket is listable but offers nothing to test readability against.
        if ($sampleKey === null) {
            return new ProbeResult($region, Finding::YES, Finding::YES, Finding::UNKNOWN, $cors);
        }

        $object = ($this->fetcher)($this->objectUrl($bucket, $region, $sampleKey), []);
        $readable = $object === null
            ? Finding::UNKNOWN
            : (($object['status'] ?? 0) === 200 ? Finding::YES : Finding::NO);

        return new ProbeResult($region, Finding::YES, Finding::YES, $readable, $cors, $sampleKey);
    }

    /**
     * Reflecting an arbitrary origin, or allowing any origin, means a third-party page
     * can read responses from this bucket in a visitor's browser.
     */
    private function corsFinding(string $headers): string
    {
        if ($headers === '') {
            return Finding::NO;
        }

        if (preg_match('/^access-control-allow-origin:[ \t]*(.+?)[ \t\r]*$/im', $headers, $m)) {
            $value = trim($m[1]);
            if ($value === '*' || strcasecmp($value, self::PROBE_ORIGIN) === 0) {
                return Finding::YES;
            }
        }

        return Finding::NO;
    }

    /**
     * First object key in a ListBucketResult, or null if the listing has no contents.
     */
    public static function firstKey(string $xml): ?string
    {
        if (preg_match('#<Key>(.*?)</Key>#s', $xml, $m)) {
            $key = html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            return $key === '' ? null : $key;
        }

        return null;
    }

    private function listUrl(string $bucket, string $region): string
    {
        // Path-style keeps dotted bucket names off the TLS wildcard certificate.
        return sprintf('https://s3.%s.amazonaws.com/%s/?list-type=2&max-keys=1', $region, rawurlencode($bucket));
    }

    private function objectUrl(string $bucket, string $region, string $key): string
    {
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', $key)));
        return sprintf('https://s3.%s.amazonaws.com/%s/%s', $region, rawurlencode($bucket), $encodedKey);
    }

    /**
     * @return array{status:int,headers:string,body:string}|null
     */
    private function fetch(string $url, array $headers = []): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_HTTPGET        => true,
            CURLOPT_HEADER         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => $headerLines,
            // Objects can be large and none of it is needed beyond the status.
            CURLOPT_RANGE          => '0-8191',
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        if (!is_string($response)) {
            return null;
        }

        return [
            // A ranged request answers 206; for our purposes that is a successful read.
            'status'  => $status === 206 ? 200 : $status,
            'headers' => substr($response, 0, $headerSize),
            'body'    => substr($response, $headerSize),
        ];
    }
}
