<?php
/**
 * Bucky Front Controller
 *
 * All requests are routed through this file.
 * Run with: php -S 127.0.0.1:13337 -t public/
 */
// Serve static files directly when using PHP built-in server
if (php_sapi_name() === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require_once __DIR__ . '/../bootstrap.php';

use Bucky\Auth;
use Bucky\Database;
use Bucky\BucketChecker;
use Bucky\BucketProbe;
use Bucky\CheckResult;
use Bucky\Finding;
use Bucky\Settings;
use Bucky\Logger;
use Bucky\Validator;

$logger = new Logger();

// Global error handler
set_exception_handler(function (Throwable $e) use ($logger) {
    $logger->error($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo 'An internal error occurred.';
});

// Simple router
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// CORS: Allow addon (content script) to POST to /process
if ($path === '/process') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Bucky-Token');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

switch ($path) {
    case '/':
        Auth::check();
        require __DIR__ . '/../views/splash.php';
        break;

    case '/buckets':
        Auth::check();
        // Started before any output: the view embeds a CSRF token for the write-test
        // form, and session_start() cannot send its cookie once headers are flushed.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $db = new Database();
        $db->initialize();
        $buckets = $db->getAllBuckets();
        require __DIR__ . '/../views/all_buckets.php';
        break;

    case '/check':
        Auth::check();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        require __DIR__ . '/../views/manual_check.php';
        break;

    case '/process':
        $authMethod = Auth::check();
        handleProcess($logger, $authMethod);
        break;

    case '/write-poc':
        // Re-runs the write test on demand — for buckets whose automatic attempt was
        // inconclusive, or when BUCKY_AUTO_WRITE is off. Still requires a browser
        // session with a CSRF token so a hostile page cannot drive it.
        Auth::check();
        require_once __DIR__ . '/../csrf.php';
        if (!csrf_validate()) {
            http_response_code(403);
            die('Invalid CSRF token.');
        }
        handleWritePoc($logger);
        break;

    default:
        http_response_code(404);
        echo 'Not found.';
        break;
}

function handleProcess(Logger $logger, string $authMethod): void {
    // Browsers attach cached Basic Auth credentials to cross-site form posts, so a
    // session-authenticated request must prove intent with a CSRF token. This must not
    // be conditional on the field being present — an attacker simply omits it.
    if ($authMethod === Auth::METHOD_BASIC) {
        require_once __DIR__ . '/../csrf.php';
        if (!csrf_validate()) {
            http_response_code(403);
            die('Invalid CSRF token.');
        }
    }

    $bucket   = $_POST['bucketname'] ?? '';
    $url      = $_POST['sourceurl'] ?? '';
    $hostname = $_POST['hostname'] ?? '';

    if (!Validator::bucketName($bucket)) {
        http_response_code(400);
        die('Invalid bucket name.');
    }
    if (!Validator::sourceUrl($url)) {
        http_response_code(400);
        die('Invalid source URL.');
    }
    if (!Validator::hostname($hostname)) {
        http_response_code(400);
        die('Invalid hostname.');
    }

    $ip = Validator::resolveIp($hostname);
    $db = new Database();
    $db->initialize();

    $logger->info("Probing bucket: $bucket (source: $url)");

    // Read-only reconnaissance first: it establishes the region and whether the bucket
    // is even real, which the write test needs, and it yields findings on buckets that
    // refuse writes.
    $result = (new BucketProbe())->run($bucket);
    $db->recordProbe($bucket, $url, $ip, $result);

    $logger->info(sprintf(
        '%s: severity=%s region=%s exists=%s listable=%s readable=%s cors=%s',
        $bucket, $result->severity(), $result->region ?? '-',
        $result->exists, $result->listable, $result->readable, $result->cors
    ));

    // Automatic proof-of-concept. Only meaningful against a bucket that exists.
    if ($result->exists === Finding::YES && Settings::autoWriteEnabled($_ENV)) {
        attemptWrite($bucket, $db, $logger);
    }

    header('refresh:3; url=/buckets');
    renderProbeResult(h($bucket), $result);
}

/**
 * Run the write test and record it. Shared by the automatic path and the explicit
 * per-bucket action, so both classify and store the outcome identically.
 */
function attemptWrite(string $bucket, Database $db, Logger $logger): string {
    $outcome = (new BucketChecker())->check($bucket);

    if ($outcome === CheckResult::VULNERABLE) {
        $poc = 'https://' . $bucket . '.s3.amazonaws.com/bucky.txt';
        $db->recordWriteProof($bucket, $poc);
        $logger->info("WRITABLE: $bucket — PoC at $poc");
    } elseif ($outcome === CheckResult::NOT_VULNERABLE) {
        $db->recordWriteProof($bucket, null);
        $logger->info("Not writable: $bucket");
    } else {
        // Left as unknown deliberately: a failed write test is not a clean result.
        $logger->error("Write test did not complete for $bucket ($outcome) — not a clean result");
    }

    return $outcome;
}

function handleWritePoc(Logger $logger): void {
    $bucket = $_POST['bucketname'] ?? '';
    if (!Validator::bucketName($bucket)) {
        http_response_code(400);
        die('Invalid bucket name.');
    }

    $db = new Database();
    $db->initialize();

    $logger->info("Write PoC requested by hand for $bucket");
    attemptWrite($bucket, $db, $logger);

    header('Location: /buckets');
    exit;
}

function renderProbeResult(string $bucket, \Bucky\ProbeResult $result): void {
    $presentation = [
        'high'    => ['#e74c3c', 'Finding'],
        'medium'  => ['#f39c12', 'Finding'],
        'none'    => ['#27ae60', 'Nothing found'],
        'unknown' => ['#f39c12', 'Inconclusive'],
    ];
    [$color, $label] = $presentation[$result->severity()] ?? $presentation['unknown'];

    if ($result->isTakeoverCandidate()) {
        $msg = "<strong>$bucket</strong> is referenced but does not exist — a takeover candidate.";
    } elseif ($result->severity() === 'unknown') {
        $msg = "The probe of <strong>$bucket</strong> did not complete. This is <em>not</em> a clean result.";
    } else {
        $parts = [];
        if ($result->listable === \Bucky\Finding::YES) { $parts[] = 'contents are publicly listable'; }
        if ($result->readable === \Bucky\Finding::YES) { $parts[] = 'objects are publicly readable'; }
        if ($result->cors === \Bucky\Finding::YES)     { $parts[] = 'CORS reflects arbitrary origins'; }
        $msg = $parts === []
            ? "<strong>$bucket</strong> exposed nothing to an unauthenticated probe."
            : "<strong>$bucket</strong>: " . implode(', ', $parts) . '.';
    }

    $redirect = '/buckets';
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bucky — Result</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: Arial, Helvetica, sans-serif; background: #f5f7fa; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
.card { background: #fff; border-radius: 8px; padding: 36px 44px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); text-align: center; max-width: 460px; }
.badge { display: inline-block; padding: 4px 14px; border-radius: 12px; font-size: 12px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: {$color}; background: {$color}18; margin-bottom: 16px; }
.card p { font-size: 16px; color: #333; line-height: 1.5; margin-bottom: 14px; }
.card .sub { font-size: 13px; color: #999; }
.card .sub a { color: #0dbce7; }
</style>
</head>
<body>
<div class="card">
    <div class="badge">{$label}</div>
    <p>{$msg}</p>
    <p class="sub">Redirecting... or <a href="{$redirect}">go now</a></p>
</div>
</body>
</html>
HTML;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
