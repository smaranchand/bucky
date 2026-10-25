<?php

namespace Bucky\Tests;

use Bucky\BucketProbe;
use Bucky\Finding;
use PHPUnit\Framework\TestCase;

class BucketProbeTest extends TestCase
{
    /** Build a probe whose HTTP layer replays canned responses keyed by URL substring. */
    private function probe(?string $region, array $routes): BucketProbe
    {
        return new BucketProbe(
            fn(string $bucket): ?string => $region,
            function (string $url, array $headers = []) use ($routes) {
                foreach ($routes as $needle => $response) {
                    if (str_contains($url, $needle)) {
                        return $response;
                    }
                }
                return null;
            }
        );
    }

    private function listing(string ...$keys): array
    {
        $xml = '<?xml version="1.0"?><ListBucketResult>';
        foreach ($keys as $k) {
            $xml .= '<Contents><Key>' . $k . '</Key></Contents>';
        }
        $xml .= '</ListBucketResult>';

        return ['status' => 200, 'headers' => '', 'body' => $xml];
    }

    public function testBucketThatDoesNotExistIsReportedAsTakeoverCandidate(): void
    {
        $result = $this->probe(null, [])->run('ghost-bucket');

        $this->assertSame(Finding::NO, $result->exists);
        $this->assertTrue($result->isTakeoverCandidate());
    }

    public function testChecksAreUnknownWhenBucketDoesNotExist(): void
    {
        // Nothing was measured about a bucket that isn't there. Reporting "not listable"
        // would imply a check that never ran.
        $result = $this->probe(null, [])->run('ghost-bucket');

        $this->assertSame(Finding::UNKNOWN, $result->listable);
        $this->assertSame(Finding::UNKNOWN, $result->readable);
        $this->assertSame(Finding::UNKNOWN, $result->cors);
    }

    public function testPubliclyListableBucketIsDetected(): void
    {
        $result = $this->probe('us-east-1', [
            'list-type=2' => $this->listing('assets/logo.png'),
        ])->run('open-bucket');

        $this->assertSame(Finding::YES, $result->exists);
        $this->assertSame(Finding::YES, $result->listable);
        $this->assertSame('assets/logo.png', $result->sampleKey);
    }

    public function testPrivateBucketIsNotListable(): void
    {
        $result = $this->probe('us-east-1', [
            'list-type=2' => ['status' => 403, 'headers' => '', 'body' => '<Error/>'],
        ])->run('locked-bucket');

        $this->assertSame(Finding::YES, $result->exists);
        $this->assertSame(Finding::NO, $result->listable);
        $this->assertNull($result->sampleKey);
    }

    public function testObjectReadableWhenSampleKeyFetchesAnonymously(): void
    {
        $result = $this->probe('us-east-1', [
            'list-type=2'     => $this->listing('assets/logo.png'),
            'assets/logo.png' => ['status' => 200, 'headers' => '', 'body' => 'binary'],
        ])->run('open-bucket');

        $this->assertSame(Finding::YES, $result->readable);
    }

    public function testObjectNotReadableWhenSampleKeyIsForbidden(): void
    {
        $result = $this->probe('us-east-1', [
            'list-type=2'     => $this->listing('assets/logo.png'),
            'assets/logo.png' => ['status' => 403, 'headers' => '', 'body' => ''],
        ])->run('half-open');

        $this->assertSame(Finding::NO, $result->readable);
    }

    public function testReadableIsUnknownWhenListingIsEmpty(): void
    {
        // Nothing to sample, so object readability was never established.
        $result = $this->probe('us-east-1', [
            'list-type=2' => $this->listing(),
        ])->run('empty-bucket');

        $this->assertSame(Finding::YES, $result->listable);
        $this->assertSame(Finding::UNKNOWN, $result->readable);
    }

    public function testCorsMisconfigurationIsDetectedWhenOriginIsReflected(): void
    {
        $result = $this->probe('us-east-1', [
            'list-type=2' => [
                'status'  => 403,
                'headers' => "HTTP/1.1 403 Forbidden\r\nAccess-Control-Allow-Origin: https://bucky.invalid\r\n\r\n",
                'body'    => '',
            ],
        ])->run('cors-bucket');

        $this->assertSame(Finding::YES, $result->cors);
    }

    public function testWildcardCorsIsDetected(): void
    {
        $result = $this->probe('us-east-1', [
            'list-type=2' => [
                'status'  => 403,
                'headers' => "HTTP/1.1 403 Forbidden\r\nAccess-Control-Allow-Origin: *\r\n\r\n",
                'body'    => '',
            ],
        ])->run('wild-bucket');

        $this->assertSame(Finding::YES, $result->cors);
    }

    public function testNoCorsHeaderIsNotAFinding(): void
    {
        $result = $this->probe('us-east-1', [
            'list-type=2' => ['status' => 403, 'headers' => "HTTP/1.1 403 Forbidden\r\n\r\n", 'body' => ''],
        ])->run('plain-bucket');

        $this->assertSame(Finding::NO, $result->cors);
    }

    public function testTransportFailureIsUnknownNotNegative(): void
    {
        // A network failure must never read as "checked and clean".
        $result = $this->probe('us-east-1', [])->run('unreachable');

        $this->assertSame(Finding::UNKNOWN, $result->listable);
        $this->assertSame(Finding::UNKNOWN, $result->cors);
    }

    public function testProbeRequestsOnlyReadEndpointsOnTheBucketsOwnRegion(): void
    {
        $requested = [];
        $probe = new BucketProbe(
            fn(string $b): ?string => 'eu-west-1',
            function (string $url, array $headers = []) use (&$requested) {
                $requested[] = $url;
                return str_contains($url, 'list-type=2')
                    ? ['status' => 200, 'headers' => '', 'body' => '<Contents><Key>a/b.txt</Key></Contents>']
                    : ['status' => 200, 'headers' => '', 'body' => 'x'];
            }
        );

        $probe->run('target-bucket');

        $this->assertSame([
            'https://s3.eu-west-1.amazonaws.com/target-bucket/?list-type=2&max-keys=1',
            'https://s3.eu-west-1.amazonaws.com/target-bucket/a/b.txt',
        ], $requested);
    }

    public function testObjectKeysAreUrlEncodedPerSegment(): void
    {
        $requested = [];
        $probe = new BucketProbe(
            fn(string $b): ?string => 'us-east-1',
            function (string $url, array $headers = []) use (&$requested) {
                $requested[] = $url;
                return str_contains($url, 'list-type=2')
                    ? ['status' => 200, 'headers' => '', 'body' => '<Contents><Key>a b/c&amp;d.txt</Key></Contents>']
                    : ['status' => 200, 'headers' => '', 'body' => 'x'];
            }
        );

        $probe->run('b');

        // Slashes stay as path separators; everything else in a segment is escaped.
        $this->assertStringEndsWith('/b/a%20b/c%26d.txt', $requested[1]);
    }

    public function testSeverityRanksListableAboveExistenceOnly(): void
    {
        $listable = $this->probe('us-east-1', ['list-type=2' => $this->listing('a.txt')])->run('b');
        $private  = $this->probe('us-east-1', ['list-type=2' => ['status' => 403, 'headers' => '', 'body' => '']])->run('b');

        $this->assertSame('high', $listable->severity());
        $this->assertSame('none', $private->severity());
    }

    public function testSeverityIsUnknownWhenNothingCouldBeChecked(): void
    {
        $this->assertSame('unknown', $this->probe('us-east-1', [])->run('x')->severity());
    }

    /**
     * A missing bucket is only a takeover candidate when something actually points at
     * it. Typing a name that does not exist into the manual form is not a finding.
     */
    public function testMissingBucketIsNotATakeoverCandidateWithoutAReference(): void
    {
        $result = $this->probe(null, [])->run('typo-bucket');

        $this->assertFalse($result->isTakeoverCandidate(false));
        $this->assertSame('none', $result->severity(false));
    }

    public function testMissingBucketIsHighSeverityWhenReferencedByAPage(): void
    {
        $result = $this->probe(null, [])->run('dangling-bucket');

        $this->assertTrue($result->isTakeoverCandidate(true));
        $this->assertSame('high', $result->severity(true));
    }
}
