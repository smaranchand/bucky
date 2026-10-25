// Bucky — AWS S3 bucket discovery content script
//
// Complements the service worker's network observation: this catches buckets that are
// *referenced* in the markup but never actually requested (preload hints, data
// attributes, inline config blobs). The worker catches everything the page fetches,
// including URLs that only exist inside a JS bundle.

(() => {
  const seen = new Set();

  function report(source) {
    for (const bucketName of extractBuckets(source)) {
      if (seen.has(bucketName)) continue;
      seen.add(bucketName);

      chrome.runtime.sendMessage({
        type: "bucky:check",
        bucketName,
        pageUrl: document.URL,
        hostname: window.location.hostname,
      });
    }
  }

  report(document.documentElement.outerHTML);

  // Single-page apps insert most of their content after load, so a one-shot scan at
  // document_idle misses it. Re-scan on mutation, coalesced so a busy page does not
  // trigger a scan per DOM change.
  let pending = null;
  const observer = new MutationObserver(() => {
    if (pending) return;
    pending = setTimeout(() => {
      pending = null;
      report(document.documentElement.outerHTML);
    }, 2000);
  });

  observer.observe(document.documentElement, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ["src", "href", "data-src", "srcset", "content"],
  });
})();
