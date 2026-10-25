const test = require("node:test");
const assert = require("node:assert");
const { bucketFromUrl, extractBuckets } = require("../../addon/extract.js");

test("recognises every S3 URL form seen in the wild", () => {
  const forms = {
    "https://my-bucket.s3.amazonaws.com/logo.png": "my-bucket",
    "https://my-bucket.s3.us-west-2.amazonaws.com/x.png": "my-bucket",
    "https://my-bucket.s3-us-west-2.amazonaws.com/x": "my-bucket",
    "https://my-bucket.s3.dualstack.us-east-1.amazonaws.com/x": "my-bucket",
    "https://my-bucket.s3-accelerate.amazonaws.com/x": "my-bucket",
    "https://my-bucket.s3.amazonaws.com": "my-bucket",
    "http://my-bucket.s3.amazonaws.com/x": "my-bucket",
    "https://s3.amazonaws.com/my-bucket/logo.png": "my-bucket",
    "https://s3.amazonaws.com/my-bucket": "my-bucket",
    "https://s3.us-west-2.amazonaws.com/my-bucket/x": "my-bucket",
    "https://s3-eu-west-1.amazonaws.com/my-bucket/x": "my-bucket",
    "https://my.dotted.bucket.s3.amazonaws.com/a": "my.dotted.bucket",
  };

  for (const [url, expected] of Object.entries(forms)) {
    assert.strictEqual(bucketFromUrl(url), expected, `failed on ${url}`);
  }
});

test("ignores non-S3 amazonaws hosts", () => {
  assert.strictEqual(bucketFromUrl("https://dynamodb.us-east-1.amazonaws.com/"), null);
  assert.strictEqual(bucketFromUrl("https://example.com/s3.amazonaws.com/fake"), null);
  assert.strictEqual(bucketFromUrl("https://sqs.us-east-1.amazonaws.com/123/queue"), null);
});

test("ignores the bare s3 endpoint with no bucket", () => {
  assert.strictEqual(bucketFromUrl("https://s3.amazonaws.com/"), null);
  assert.strictEqual(bucketFromUrl("https://s3.us-east-1.amazonaws.com/"), null);
});

test("rejects path segments that are not valid bucket names", () => {
  assert.strictEqual(bucketFromUrl("https://s3.amazonaws.com/AB/"), null, "too short + uppercase");
  assert.strictEqual(bucketFromUrl("https://s3.amazonaws.com/" + "a".repeat(64) + "/"), null, "too long");
});

test("finds every distinct bucket in a page, not just the first", () => {
  const html = `
    <img src="https://alpha-assets.s3.amazonaws.com/a.png">
    <img src="https://beta-media.s3.us-east-1.amazonaws.com/b.png">
    <script src="https://gamma-cdn.s3-eu-west-1.amazonaws.com/c.js"></script>
    <link href="https://s3.amazonaws.com/delta-static/d.css">`;

  assert.deepStrictEqual(
    extractBuckets(html).sort(),
    ["alpha-assets", "beta-media", "delta-static", "gamma-cdn"]
  );
});

test("deduplicates repeated references", () => {
  const html = `
    <img src="https://same-bucket.s3.amazonaws.com/1.png">
    <img src="https://same-bucket.s3.amazonaws.com/2.png">
    <img src="https://same-bucket.s3.us-east-1.amazonaws.com/3.png">`;

  assert.deepStrictEqual(extractBuckets(html), ["same-bucket"]);
});

test("returns nothing for a page with no S3 references", () => {
  assert.deepStrictEqual(extractBuckets("<p>hello world</p>"), []);
  assert.deepStrictEqual(extractBuckets(""), []);
});

test("normalises case so one bucket is not reported twice", () => {
  // Hostnames are case-insensitive; bucket names are always lowercase.
  assert.strictEqual(bucketFromUrl("https://MY-BUCKET.s3.amazonaws.com/x"), "my-bucket");
});
