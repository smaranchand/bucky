// Shared endpoint parsing and validation.
// Loaded as a plain script by options.html and popup.html, and required directly
// by the Node test suite — hence the conditional export at the bottom.

const LOCAL_HOSTS = ["127.0.0.1", "localhost", "[::1]", "::1"];

/**
 * Validate a backend endpoint and derive what the extension needs from it.
 *
 * @throws {Error} with a message suitable for display when the input is unusable.
 * @returns {{url: string, origin: string, pattern: string, isLocal: boolean, isPlaintext: boolean}}
 */
function parseEndpoint(input) {
  const raw = (input || "").trim();
  if (!raw) {
    throw new Error("An endpoint is required.");
  }

  let url;
  try {
    url = new URL(raw);
  } catch {
    throw new Error("That is not a valid URL.");
  }

  if (url.protocol !== "http:" && url.protocol !== "https:") {
    throw new Error("The endpoint must start with http:// or https://");
  }

  return {
    url: url.href,
    origin: url.origin,
    // Match patterns must not carry a port; a host pattern covers every port on it.
    pattern: `${url.protocol}//${url.hostname}/*`,
    isLocal: LOCAL_HOSTS.includes(url.hostname),
    isPlaintext: url.protocol === "http:",
  };
}

if (typeof module !== "undefined" && module.exports) {
  module.exports = { parseEndpoint };
}
