const test = require("node:test");
const assert = require("node:assert");
const { parseEndpoint } = require("../../addon/endpoint.js");

test("accepts a local http endpoint", () => {
  const r = parseEndpoint("http://127.0.0.1:13337/process");
  assert.strictEqual(r.isLocal, true);
  assert.strictEqual(r.isPlaintext, true);
});

test("accepts a remote https endpoint", () => {
  const r = parseEndpoint("https://bucky.example.com/process");
  assert.strictEqual(r.isLocal, false);
  assert.strictEqual(r.isPlaintext, false);
});

test("flags a remote plaintext endpoint as insecure", () => {
  // The request carries the API token and every bucket name discovered while
  // browsing, so plaintext to a non-local host must be called out.
  const r = parseEndpoint("http://20.121.198.140:13337/process");
  assert.strictEqual(r.isLocal, false);
  assert.strictEqual(r.isPlaintext, true);
});

test("host permission pattern omits the port", () => {
  // MV3 match patterns must not contain a port; they match any port on the host.
  const r = parseEndpoint("http://20.121.198.140:13337/process");
  assert.strictEqual(r.pattern, "http://20.121.198.140/*");
});

test("host permission pattern keeps the scheme", () => {
  const r = parseEndpoint("https://bucky.example.com/process");
  assert.strictEqual(r.pattern, "https://bucky.example.com/*");
});

test("treats localhost and ::1 as local", () => {
  assert.strictEqual(parseEndpoint("http://localhost:13337/process").isLocal, true);
  assert.strictEqual(parseEndpoint("http://[::1]:13337/process").isLocal, true);
});

test("rejects an empty endpoint", () => {
  assert.throws(() => parseEndpoint(""), /required/i);
  assert.throws(() => parseEndpoint("   "), /required/i);
});

test("rejects a malformed url", () => {
  assert.throws(() => parseEndpoint("not a url"), /valid URL/i);
});

test("rejects non-http schemes", () => {
  assert.throws(() => parseEndpoint("ftp://example.com/x"), /http/i);
  assert.throws(() => parseEndpoint("javascript:alert(1)"), /http/i);
  assert.throws(() => parseEndpoint("file:///etc/passwd"), /http/i);
});

test("preserves the full url including path and port", () => {
  const r = parseEndpoint("https://bucky.example.com:8443/api/process");
  assert.strictEqual(r.url, "https://bucky.example.com:8443/api/process");
});
