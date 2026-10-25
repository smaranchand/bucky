// S3 bucket extraction, shared by the content script and the service worker.
// Loaded as a plain script in the extension and required directly by the Node tests.

// AWS general-purpose bucket naming: 3-63 chars, starts and ends alphanumeric.
const BUCKET_RE = /^[a-z0-9][a-z0-9.\-]{1,61}[a-z0-9]$/;

// Hosts that address a bucket in the hostname:
//   <bucket>.s3.amazonaws.com
//   <bucket>.s3.<region>.amazonaws.com        (also .s3.dualstack.<region>.)
//   <bucket>.s3-<region>.amazonaws.com        (legacy hyphen form)
//   <bucket>.s3-accelerate.amazonaws.com
const VIRTUAL_HOST_RE = /^(.+?)\.s3(?:[.-][a-z0-9-]+)*\.amazonaws\.com$/i;

// Hosts that address the service itself, with the bucket as the first path segment.
const PATH_STYLE_HOST_RE = /^s3(?:[.-][a-z0-9-]+)*\.amazonaws\.com$/i;

function isBucketName(name) {
  return typeof name === "string" && BUCKET_RE.test(name);
}

/**
 * Extract the bucket name from a single URL, or null if it does not address one.
 */
function bucketFromUrl(input) {
  let url;
  try {
    url = new URL(input);
  } catch {
    return null;
  }

  if (url.protocol !== "http:" && url.protocol !== "https:") return null;

  const host = url.hostname.toLowerCase();

  const virtual = host.match(VIRTUAL_HOST_RE);
  if (virtual) {
    const candidate = virtual[1];
    return isBucketName(candidate) ? candidate : null;
  }

  if (PATH_STYLE_HOST_RE.test(host)) {
    const first = url.pathname.split("/").filter(Boolean)[0];
    // Path-style names arrive verbatim; an uppercase segment is not a bucket.
    return isBucketName(first) ? first : null;
  }

  return null;
}

/**
 * Every distinct bucket referenced in a blob of text, deduplicated.
 *
 * A global match is deliberate: a page commonly references several buckets, and
 * reporting only the first silently under-reports.
 */
function extractBuckets(text) {
  if (!text) return [];

  const found = new Set();
  const urlPattern = /https?:\/\/[a-zA-Z0-9.\-]*amazonaws\.com(?:\/[^\s"'<>\\)]*)?/g;

  for (const match of text.matchAll(urlPattern)) {
    const bucket = bucketFromUrl(match[0]);
    if (bucket) found.add(bucket);
  }

  return [...found];
}

if (typeof module !== "undefined" && module.exports) {
  module.exports = { bucketFromUrl, extractBuckets, isBucketName };
}
