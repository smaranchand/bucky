<?php

namespace Bucky\Tests;

use Bucky\Database;
use Bucky\Finding;
use Bucky\ProbeResult;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'bucky-test-') . '.db';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    private function db(): Database
    {
        $db = new Database($this->path);
        $db->initialize();
        return $db;
    }

    private function probeResult(string $listable = Finding::YES): ProbeResult
    {
        return new ProbeResult('us-west-2', Finding::YES, $listable, Finding::YES, Finding::NO, 'a/b.txt');
    }

    public function testStoresAndReturnsAFindingSet(): void
    {
        $db = $this->db();
        $db->recordProbe('flaws.cloud', 'http://flaws.cloud/', '1.2.3.4', $this->probeResult());

        $rows = $db->getAllBuckets();
        $this->assertCount(1, $rows);
        $this->assertSame('flaws.cloud', $rows[0]['BUCKETNAME']);
        $this->assertSame('us-west-2', $rows[0]['REGION']);
        $this->assertSame(Finding::YES, $rows[0]['LISTABLE']);
        $this->assertSame(Finding::YES, $rows[0]['READABLE']);
        $this->assertSame(Finding::NO, $rows[0]['CORS']);
        $this->assertSame('high', $rows[0]['SEVERITY']);
        $this->assertSame('a/b.txt', $rows[0]['SAMPLE_KEY']);
    }

    public function testRecordingTheSameBucketTwiceUpdatesRatherThanDuplicates(): void
    {
        // The addon reports a bucket on every page that references it; the dashboard
        // should show one row per bucket, not one per sighting.
        $db = $this->db();
        $db->recordProbe('dup', 'http://a/', '-', $this->probeResult(Finding::NO));
        $db->recordProbe('dup', 'http://b/', '-', $this->probeResult(Finding::YES));

        $rows = $db->getAllBuckets();
        $this->assertCount(1, $rows);
        $this->assertSame(Finding::YES, $rows[0]['LISTABLE'], 'later probe should win');
    }

    public function testSightingCountTracksRepeatDiscoveries(): void
    {
        $db = $this->db();
        $db->recordProbe('seen', 'http://a/', '-', $this->probeResult());
        $db->recordProbe('seen', 'http://b/', '-', $this->probeResult());
        $db->recordProbe('seen', 'http://c/', '-', $this->probeResult());

        $this->assertSame(3, (int) $db->getAllBuckets()[0]['SIGHTINGS']);
    }

    public function testRecordsWriteProofSeparatelyFromPassiveFindings(): void
    {
        $db = $this->db();
        $db->recordProbe('w', 'http://a/', '-', $this->probeResult());
        $db->recordWriteProof('w', 'https://w.s3.amazonaws.com/bucky.txt');

        $row = $db->getAllBuckets()[0];
        $this->assertSame(Finding::YES, $row['WRITABLE']);
        $this->assertSame('https://w.s3.amazonaws.com/bucky.txt', $row['POC']);
    }

    public function testWritableIsUnknownUntilExplicitlyTested(): void
    {
        // Passive probing never establishes writability, and must not imply it did.
        $db = $this->db();
        $db->recordProbe('w', 'http://a/', '-', $this->probeResult());

        $this->assertSame(Finding::UNKNOWN, $db->getAllBuckets()[0]['WRITABLE']);
    }

    public function testMigratesLegacyRowsFromTheOldSchema(): void
    {
        // A pre-existing database has the old single-column shape.
        $pdo = new \PDO('sqlite:' . $this->path);
        $pdo->exec('CREATE TABLE BUCKY (ID INTEGER PRIMARY KEY AUTOINCREMENT,
            BUCKETNAME VARCHAR NOT NULL, URL VARCHAR NOT NULL, IP VARCHAR NOT NULL,
            POC VARCHAR NOT NULL, VULNERABLE VARCHAR)');
        $pdo->exec("INSERT INTO BUCKY (BUCKETNAME,URL,IP,POC,VULNERABLE)
            VALUES ('old-yes','http://a/','1.1.1.1','https://p/bucky.txt','YES'),
                   ('old-no','http://b/','2.2.2.2','-','NO'),
                   ('old-unknown','http://c/','3.3.3.3','-','UNKNOWN')");
        unset($pdo);

        $db = new Database($this->path);
        $db->initialize();

        $byName = [];
        foreach ($db->getAllBuckets() as $r) {
            $byName[$r['BUCKETNAME']] = $r;
        }

        $this->assertCount(3, $byName, 'legacy rows must survive migration');
        $this->assertSame(Finding::YES, $byName['old-yes']['WRITABLE']);
        $this->assertSame('high', $byName['old-yes']['SEVERITY']);
        $this->assertSame(Finding::NO, $byName['old-no']['WRITABLE']);
        $this->assertSame(Finding::UNKNOWN, $byName['old-unknown']['WRITABLE']);
        $this->assertSame('unknown', $byName['old-unknown']['SEVERITY']);
    }

    public function testInitializeIsIdempotent(): void
    {
        $db = $this->db();
        $db->recordProbe('keep', 'http://a/', '-', $this->probeResult());

        $db->initialize();
        $db->initialize();

        $this->assertCount(1, $db->getAllBuckets(), 're-initialising must not lose data');
    }
}
