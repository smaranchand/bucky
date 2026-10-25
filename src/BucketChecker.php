<?php

namespace Bucky;

use Aws\Exception\AwsException;
use Aws\S3\Exception\PermanentRedirectException;
use Aws\S3\S3Client;

class BucketChecker
{
    /** @var callable(string): ?string */
    private $regionResolver;

    /** @var callable(string, string): void Throws AwsException when the write is refused. */
    private $writer;

    /**
     * Both collaborators default to the real implementations; tests substitute them to
     * exercise the classification logic without network access.
     *
     * @param (callable(string): ?string)|null    $regionResolver
     * @param (callable(string, string): void)|null $writer
     */
    public function __construct(?callable $regionResolver = null, ?callable $writer = null)
    {
        $this->regionResolver = $regionResolver ?? [new RegionResolver(), 'resolve'];
        $this->writer = $writer ?? [$this, 'putPocObject'];
    }

    /**
     * Attempt the PoC write and classify the outcome.
     *
     * @return string One of the CheckResult constants.
     */
    public function check(string $bucket): string
    {
        $region = ($this->regionResolver)($bucket);

        // Without the home region a write would be signed for the wrong endpoint and
        // rejected for reasons unrelated to the bucket's permissions.
        if ($region === null) {
            return CheckResult::INDETERMINATE;
        }

        try {
            ($this->writer)($bucket, $region, true);
            return CheckResult::VULNERABLE;
        } catch (AwsException $e) {
            // Buckets created since April 2023 default to Object Ownership "bucket owner
            // enforced", which disables ACLs; a PutObject carrying one is rejected even
            // when the bucket accepts writes. Retry plainly to tell the two apart.
            if ($e->getAwsErrorCode() === 'AccessControlListNotSupported'
                || $e->getAwsErrorCode() === 'InvalidBucketAclWithObjectOwnership') {
                try {
                    ($this->writer)($bucket, $region, false);
                    return CheckResult::VULNERABLE;
                } catch (AwsException $retry) {
                    return $this->classify($retry);
                }
            }

            return $this->classify($e);
        }
    }

    /**
     * Only an explicit refusal by S3 counts as "not vulnerable". Everything else —
     * redirects, throttling, network failures, problems with our own credentials —
     * is indeterminate, because reporting it as clean creates false confidence.
     */
    private function classify(AwsException $e): string
    {
        if ($e instanceof PermanentRedirectException || $e->isConnectionError()) {
            return CheckResult::INDETERMINATE;
        }

        switch ($e->getAwsErrorCode()) {
            case 'AccessDenied':
            case 'AllAccessDisabled':
                return CheckResult::NOT_VULNERABLE;

            case 'NoSuchBucket':
                return CheckResult::NO_SUCH_BUCKET;

            default:
                return CheckResult::INDETERMINATE;
        }
    }

    private function putPocObject(string $bucket, string $region, bool $withAcl): void
    {
        $client = new S3Client([
            'version'     => 'latest',
            'region'      => $region,
            'credentials' => [
                'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
            ],
        ]);

        $params = [
            'Bucket'      => $bucket,
            'Key'         => 'bucky.txt',
            'Body'        => "S3 bucket misconfiguration is discovered.\nDiscovered by Bucky.\nDeveloped by: https://twitter.com/smaranchand",
            'ContentType' => 'text/plain',
        ];

        // Only requested on the first attempt; it makes the PoC URL publicly fetchable
        // on buckets that still have ACLs enabled.
        if ($withAcl) {
            $params['ACL'] = 'public-read';
        }

        $client->putObject($params);
    }
}
