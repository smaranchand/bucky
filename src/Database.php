<?php

namespace Bucky;

use PDO;

class Database
{
    private PDO $pdo;

    public function __construct(?string $path = null)
    {
        $path = $path ?? dirname(__DIR__) . '/bucky.db';
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Create the schema, or bring an existing one up to date. Safe to call repeatedly.
     */
    public function initialize(): void
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS BUCKY (
            ID INTEGER PRIMARY KEY AUTOINCREMENT,
            BUCKETNAME VARCHAR NOT NULL,
            URL VARCHAR NOT NULL,
            IP VARCHAR NOT NULL,
            POC VARCHAR NOT NULL,
            VULNERABLE VARCHAR
        )');

        $this->addMissingColumns();
        $this->backfillLegacyRows();

        // One row per bucket. The addon reports a bucket on every page referencing it.
        $this->pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS IDX_BUCKY_NAME ON BUCKY (BUCKETNAME)');
    }

    /**
     * @return array<string,string> column name => column definition
     */
    private function schemaAdditions(): array
    {
        return [
            'REGION'     => "VARCHAR",
            'EXISTS_F'   => "VARCHAR NOT NULL DEFAULT '" . Finding::UNKNOWN . "'",
            'LISTABLE'   => "VARCHAR NOT NULL DEFAULT '" . Finding::UNKNOWN . "'",
            'READABLE'   => "VARCHAR NOT NULL DEFAULT '" . Finding::UNKNOWN . "'",
            'CORS'       => "VARCHAR NOT NULL DEFAULT '" . Finding::UNKNOWN . "'",
            'WRITABLE'   => "VARCHAR NOT NULL DEFAULT '" . Finding::UNKNOWN . "'",
            'SEVERITY'   => "VARCHAR NOT NULL DEFAULT 'unknown'",
            'SAMPLE_KEY' => "VARCHAR",
            'SIGHTINGS'  => "INTEGER NOT NULL DEFAULT 1",
            'CHECKED_AT' => "VARCHAR",
        ];
    }

    private function existingColumns(): array
    {
        $columns = [];
        foreach ($this->pdo->query('PRAGMA table_info(BUCKY)') as $row) {
            $columns[] = $row['name'];
        }
        return $columns;
    }

    private function addMissingColumns(): void
    {
        $existing = $this->existingColumns();
        foreach ($this->schemaAdditions() as $name => $definition) {
            if (!in_array($name, $existing, true)) {
                $this->pdo->exec("ALTER TABLE BUCKY ADD COLUMN $name $definition");
            }
        }
    }

    /**
     * Map rows written under the old single-column model onto the finding set. The old
     * VULNERABLE column only ever described writability, so that is all it can populate;
     * everything else stays unknown because it was never measured.
     */
    private function backfillLegacyRows(): void
    {
        $this->pdo->exec("UPDATE BUCKY SET WRITABLE = CASE VULNERABLE
                WHEN 'YES' THEN '" . Finding::YES . "'
                WHEN 'NO'  THEN '" . Finding::NO . "'
                ELSE '" . Finding::UNKNOWN . "' END,
            SEVERITY = CASE VULNERABLE
                WHEN 'YES' THEN 'high'
                WHEN 'NO'  THEN 'none'
                ELSE 'unknown' END
            WHERE VULNERABLE IS NOT NULL AND CHECKED_AT IS NULL AND WRITABLE = '" . Finding::UNKNOWN . "'");
    }

    /**
     * Record a passive probe. Re-probing a known bucket updates it and counts the sighting
     * rather than adding a duplicate row.
     */
    public function recordProbe(string $bucket, string $url, string $ip, ProbeResult $result): void
    {
        // A manual check carries no source page, so a missing bucket there is a typo,
        // not a dangling reference someone could take over.
        $referenced = $url !== '' && $url !== '-';
        $severity = $result->severity($referenced);

        $stmt = $this->pdo->prepare(
            'INSERT INTO BUCKY (BUCKETNAME, URL, IP, POC, VULNERABLE, REGION, EXISTS_F,
                                LISTABLE, READABLE, CORS, SEVERITY, SAMPLE_KEY, SIGHTINGS, CHECKED_AT)
             VALUES (:bucket, :url, :ip, :poc, :legacy, :region, :exists,
                     :listable, :readable, :cors, :severity, :key, 1, :now)
             ON CONFLICT(BUCKETNAME) DO UPDATE SET
                URL        = excluded.URL,
                IP         = excluded.IP,
                REGION     = excluded.REGION,
                EXISTS_F   = excluded.EXISTS_F,
                LISTABLE   = excluded.LISTABLE,
                READABLE   = excluded.READABLE,
                CORS       = excluded.CORS,
                SEVERITY   = excluded.SEVERITY,
                SAMPLE_KEY = excluded.SAMPLE_KEY,
                SIGHTINGS  = BUCKY.SIGHTINGS + 1,
                CHECKED_AT = excluded.CHECKED_AT'
        );

        $stmt->execute([
            ':bucket'   => $bucket,
            ':url'      => $url,
            ':ip'       => $ip,
            ':poc'      => '-',
            ':legacy'   => $severity,
            ':region'   => $result->region,
            ':exists'   => $result->exists,
            ':listable' => $result->listable,
            ':readable' => $result->readable,
            ':cors'     => $result->cors,
            ':severity' => $severity,
            ':key'      => $result->sampleKey,
            ':now'      => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Record the outcome of an explicitly requested write test.
     */
    public function recordWriteProof(string $bucket, ?string $pocUrl): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE BUCKY SET WRITABLE = :writable, POC = :poc,
                              SEVERITY = CASE WHEN :writable2 = :yes THEN :high ELSE SEVERITY END
             WHERE BUCKETNAME = :bucket'
        );
        $stmt->execute([
            ':writable'  => $pocUrl === null ? Finding::NO : Finding::YES,
            ':writable2' => $pocUrl === null ? Finding::NO : Finding::YES,
            ':yes'       => Finding::YES,
            ':high'      => 'high',
            ':poc'       => $pocUrl ?? '-',
            ':bucket'    => $bucket,
        ]);
    }

    public function getAllBuckets(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM BUCKY ORDER BY
                CASE SEVERITY WHEN 'high' THEN 0 WHEN 'medium' THEN 1 WHEN 'unknown' THEN 2 ELSE 3 END,
                ID DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findBucket(string $bucket): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM BUCKY WHERE BUCKETNAME = :bucket');
        $stmt->execute([':bucket' => $bucket]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
