// Bucky — background service worker
//
// Two discovery sources feed one deduplicating queue:
//   1. webRequest observation of *.amazonaws.com — catches every bucket the page
//      actually contacts, including URLs that only exist inside a JS bundle.
//   2. The content script — catches buckets referenced in markup but never fetched.
//
// The worker also performs the HTTP POST to the backend, so mixed-content rules on an
// HTTPS page don't block it (service workers run in the extension origin).

importScripts("extract.js");

const DEFAULT_ENDPOINT = "http://127.0.0.1:13337/process";

// Buckets already reported this worker lifetime. Bounded so a long browsing session
// cannot grow it without limit.
const reported = new Set();
const MAX_REMEMBERED = 5000;

async function getConfig() {
  const { endpoint, token } = await chrome.storage.local.get(["endpoint", "token"]);
  return { endpoint: endpoint || DEFAULT_ENDPOINT, token: token || "" };
}

async function report(bucketName, pageUrl, hostname) {
  if (!bucketName || reported.has(bucketName)) return;

  if (reported.size >= MAX_REMEMBERED) reported.clear();
  reported.add(bucketName);

  const { endpoint, token } = await getConfig();
  if (!token) {
    console.warn("Bucky: no API token configured — open the extension options to set one.");
    return;
  }

  const body = new URLSearchParams({
    bucketname: bucketName,
    sourceurl: pageUrl || "-",
    hostname: hostname || "-",
  });

  try {
    await fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "X-Bucky-Token": token,
      },
      body: body.toString(),
    });
  } catch (e) {
    // Usually the backend is down, or the host permission for a remote endpoint was
    // never granted or has been revoked. Say so — a scanner that quietly stops
    // reporting is indistinguishable from one finding nothing.
    console.warn(
      `Bucky: could not reach ${endpoint} (${e.message}). ` +
      "If this is a remote backend, re-save it in the extension options to grant host permission."
    );
  }
}

// Source 1: everything the page actually requests.
chrome.webRequest.onBeforeRequest.addListener(
  (details) => {
    const bucketName = bucketFromUrl(details.url);
    if (bucketName) {
      report(bucketName, details.initiator || details.documentUrl || "-", hostnameOf(details.initiator));
    }
  },
  { urls: ["*://*.amazonaws.com/*"] }
);

function hostnameOf(url) {
  try {
    return new URL(url).hostname;
  } catch {
    return "-";
  }
}

// Source 2: references present in the markup.
chrome.runtime.onMessage.addListener((msg) => {
  if (msg?.type !== "bucky:check") return;
  report(msg.bucketName, msg.pageUrl, msg.hostname);
});
