<?php

namespace Bucky\Tests;

use Bucky\RegionResolver;
use PHPUnit\Framework\TestCase;

class RegionResolverTest extends TestCase
{
    public function testParsesRegionFromHeaderOnSuccessfulResponse(): void
    {
        $raw = "HTTP/1.1 200 OK\r\n"
             . "x-amz-bucket-region: us-west-2\r\n"
             . "Content-Type: application/xml\r\n\r\n";

        $this->assertSame('us-west-2', RegionResolver::parseRegionHeader($raw));
    }

    public function testParsesRegionFromHeaderOnForbiddenResponse(): void
    {
        // S3 returns the region header even when it denies the request —
        // this is what makes unauthenticated region discovery possible.
        $raw = "HTTP/1.1 403 Forbidden\r\n"
             . "x-amz-bucket-region: eu-central-1\r\n\r\n";

        $this->assertSame('eu-central-1', RegionResolver::parseRegionHeader($raw));
    }

    public function testHeaderMatchIsCaseInsensitive(): void
    {
        $raw = "HTTP/1.1 403 Forbidden\r\nX-Amz-Bucket-Region: AP-SOUTH-1\r\n\r\n";

        $this->assertSame('ap-south-1', RegionResolver::parseRegionHeader($raw));
    }

    public function testReturnsNullWhenHeaderAbsent(): void
    {
        $raw = "HTTP/1.1 404 Not Found\r\nContent-Type: application/xml\r\n\r\n";

        $this->assertNull(RegionResolver::parseRegionHeader($raw));
    }

    public function testReturnsNullOnEmptyResponse(): void
    {
        $this->assertNull(RegionResolver::parseRegionHeader(''));
    }

    public function testResolveReturnsRegionFromFetchedResponse(): void
    {
        $resolver = new RegionResolver(5, fn(string $bucket): string =>
            "HTTP/1.1 301 Moved Permanently\r\nx-amz-bucket-region: us-west-2\r\n\r\n");

        $this->assertSame('us-west-2', $resolver->resolve('flaws.cloud'));
    }

    public function testResolveReturnsNullWhenFetchFails(): void
    {
        $resolver = new RegionResolver(5, fn(string $bucket): bool => false);

        $this->assertNull($resolver->resolve('unreachable'));
    }

    public function testResolveRequestsPathStyleUrlSoDottedBucketsDoNotBreakTls(): void
    {
        // A virtual-host URL for a dotted bucket (flaws.cloud.s3.amazonaws.com) fails
        // TLS verification against the *.s3.amazonaws.com wildcard certificate.
        $requested = null;
        $resolver = new RegionResolver(5, function (string $bucket) use (&$requested): string {
            $requested = $bucket;
            return "HTTP/1.1 301\r\nx-amz-bucket-region: us-west-2\r\n\r\n";
        });

        $resolver->resolve('flaws.cloud');

        $this->assertSame('flaws.cloud', $requested);
        $this->assertStringContainsString(
            's3.amazonaws.com/flaws.cloud',
            RegionResolver::probeUrl('flaws.cloud'),
            'must use path-style addressing'
        );
    }

    public function testDoesNotMatchRegionHeaderNameAppearingInBody(): void
    {
        // A header-looking string in the body must not be mistaken for the real header.
        $raw = "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\n\r\n"
             . "<p>x-amz-bucket-region: attacker-controlled</p>";

        $this->assertNull(RegionResolver::parseRegionHeader($raw));
    }
}
