<?php

namespace Bucky\Tests;

use Aws\Command;
use Aws\Exception\AwsException;
use Aws\S3\Exception\PermanentRedirectException;
use Bucky\BucketChecker;
use Bucky\CheckResult;
use PHPUnit\Framework\TestCase;

class BucketCheckerTest extends TestCase
{
    private function awsError(string $code): AwsException
    {
        return new AwsException('denied', new Command('PutObject'), ['code' => $code]);
    }

    /**
     * The regression test for docs/bugs/2026-05-14-region-mismatch-false-negatives.md.
     * The write must be attempted against the bucket's own region, not the configured one.
     */
    public function testWritesToBucketHomeRegionNotTheConfiguredRegion(): void
    {
        $usedRegion = null;
        $checker = new BucketChecker(
            fn(string $bucket): ?string => 'us-west-2',
            function (string $bucket, string $region, bool $withAcl) use (&$usedRegion): void {
                $usedRegion = $region;
            }
        );

        $checker->check('flaws.cloud');

        $this->assertSame('us-west-2', $usedRegion);
    }

    public function testReportsVulnerableWhenWriteSucceeds(): void
    {
        $checker = new BucketChecker(
            fn(string $bucket): ?string => 'us-east-1',
            function (string $bucket, string $region, bool $withAcl): void {
                // a successful write throws nothing
            }
        );

        $this->assertSame(CheckResult::VULNERABLE, $checker->check('open-bucket'));
    }

    /**
     * Buckets created since April 2023 default to Object Ownership "bucket owner
     * enforced", which disables ACLs. A PutObject specifying an ACL then fails even
     * though the bucket accepts writes. Retrying without the ACL is what distinguishes
     * "ACLs are off" from "you cannot write here".
     */
    public function testRetriesWithoutAclWhenTheBucketHasAclsDisabled(): void
    {
        $attempts = [];
        $checker = new BucketChecker(
            fn(string $bucket): ?string => 'us-east-1',
            function (string $bucket, string $region, bool $withAcl) use (&$attempts): void {
                $attempts[] = $withAcl;
                if ($withAcl) {
                    throw $this->awsError('AccessControlListNotSupported');
                }
            }
        );

        $this->assertSame(CheckResult::VULNERABLE, $checker->check('modern-bucket'));
        $this->assertSame([true, false], $attempts, 'should try with the ACL, then without');
    }

    public function testAclDisabledAndWriteRefusedIsNotVulnerable(): void
    {
        $checker = new BucketChecker(
            fn(string $bucket): ?string => 'us-east-1',
            function (string $bucket, string $region, bool $withAcl): void {
                throw $withAcl
                    ? $this->awsError('AccessControlListNotSupported')
                    : $this->awsError('AccessDenied');
            }
        );

        $this->assertSame(CheckResult::NOT_VULNERABLE, $checker->check('modern-locked'));
    }

    public function testDoesNotRetryWhenTheFirstFailureIsAPlainRefusal(): void
    {
        $attempts = 0;
        $checker = new BucketChecker(
            fn(string $bucket): ?string => 'us-east-1',
            function (string $bucket, string $region, bool $withAcl) use (&$attempts): void {
                $attempts++;
                throw $this->awsError('AccessDenied');
            }
        );

        $checker->check('locked');
        $this->assertSame(1, $attempts, 'a refusal is conclusive; no second write attempt');
    }

    public function testReportsNotVulnerableWhenAccessDenied(): void
    {
        $checker = new BucketChecker(
            fn(string $bucket): ?string => 'us-east-1',
            fn(string $bucket, string $region, bool $withAcl) => throw $this->awsError('AccessDenied')
        );

        $this->assertSame(CheckResult::NOT_VULNERABLE, $checker->check('locked-bucket'));
    }

    public function testReportsNotVulnerableWhenAllAccessDisabled(): void
    {
        $checker = new BucketChecker(
            fn(string $bucket): ?string => 'us-east-1',
            fn(string $bucket, string $region, bool $withAcl) => throw $this->awsError('AllAccessDisabled')
        );

        $this->assertSame(CheckResult::NOT_VULNERABLE, $checker->check('disabled-bucket'));
    }

    /**
     * This is the exact failure that used to be reported as "not vulnerable".
     */
    public function testReportsIndeterminateOnPermanentRedirect(): void
    {
        $checker = new BucketChecker(
            fn(string $bucket): ?string => 'us-east-1',
            fn(string $bucket, string $region, bool $withAcl) => throw new PermanentRedirectException(
                'Encountered a permanent redirect',
                new Command('PutObject')
            )
        );

        $this->assertSame(CheckResult::INDETERMINATE, $checker->check('elsewhere-bucket'));
    }

    public function testReportsIndeterminateWhenRegionCannotBeResolved(): void
    {
        $writeAttempted = false;
        $checker = new BucketChecker(
            fn(string $bucket): ?string => null,
            function (string $bucket, string $region, bool $withAcl) use (&$writeAttempted): void {
                $writeAttempted = true;
            }
        );

        $this->assertSame(CheckResult::INDETERMINATE, $checker->check('unresolvable'));
        $this->assertFalse($writeAttempted, 'must not attempt a write without a resolved region');
    }

    public function testReportsIndeterminateOnThrottling(): void
    {
        $checker = new BucketChecker(
            fn(string $bucket): ?string => 'us-east-1',
            fn(string $bucket, string $region, bool $withAcl) => throw $this->awsError('SlowDown')
        );

        $this->assertSame(CheckResult::INDETERMINATE, $checker->check('busy-bucket'));
    }

    public function testReportsIndeterminateWhenScannerCredentialsAreInvalid(): void
    {
        // A problem with *our* credentials must never read as a clean target.
        $checker = new BucketChecker(
            fn(string $bucket): ?string => 'us-east-1',
            fn(string $bucket, string $region, bool $withAcl) => throw $this->awsError('InvalidAccessKeyId')
        );

        $this->assertSame(CheckResult::INDETERMINATE, $checker->check('some-bucket'));
    }

    public function testReportsMissingWhenBucketDoesNotExist(): void
    {
        $checker = new BucketChecker(
            fn(string $bucket): ?string => 'us-east-1',
            fn(string $bucket, string $region, bool $withAcl) => throw $this->awsError('NoSuchBucket')
        );

        $this->assertSame(CheckResult::NO_SUCH_BUCKET, $checker->check('ghost-bucket'));
    }
}
