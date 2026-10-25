const DEFAULT_ENDPOINT = "http://127.0.0.1:13337/process";

const endpointInput = document.getElementById("endpoint");
const tokenInput = document.getElementById("token");
const status = document.getElementById("status");
const insecureWarning = document.getElementById("insecure-warning");

function show(message, color) {
  status.style.color = color;
  status.textContent = message;
}

// Warn as the user types, not only on save, so the risk is visible before committing.
function refreshInsecureWarning() {
  let parsed;
  try {
    parsed = parseEndpoint(endpointInput.value);
  } catch {
    insecureWarning.style.display = "none";
    return;
  }

  if (parsed.isPlaintext && !parsed.isLocal) {
    insecureWarning.textContent =
      "This endpoint uses plaintext HTTP to a remote host. Your API token and every " +
      "bucket name discovered while you browse will travel unencrypted, and anyone on " +
      "the network path can read or alter them. Use HTTPS for a cloud deployment.";
    insecureWarning.style.display = "block";
  } else {
    insecureWarning.style.display = "none";
  }
}

endpointInput.addEventListener("input", refreshInsecureWarning);

document.querySelectorAll("[data-preset]").forEach((btn) => {
  btn.addEventListener("click", () => {
    endpointInput.value = btn.dataset.preset;
    refreshInsecureWarning();
  });
});

chrome.storage.local.get(["endpoint", "token"]).then(({ endpoint, token }) => {
  endpointInput.value = endpoint || DEFAULT_ENDPOINT;
  tokenInput.value = token || "";
  refreshInsecureWarning();
});

document.getElementById("save").addEventListener("click", async () => {
  let parsed;
  try {
    parsed = parseEndpoint(endpointInput.value);
  } catch (e) {
    show(e.message, "#e74c3c");
    return;
  }

  const token = tokenInput.value.trim();
  if (!token) {
    show("A token is required — Bucky sends nothing without one.", "#e74c3c");
    return;
  }

  // Requesting from inside the click handler keeps the user gesture the browser
  // requires. Without the permission the fetch is blocked and nothing is sent.
  let granted;
  try {
    granted = await chrome.permissions.request({ origins: [parsed.pattern] });
  } catch (e) {
    show("Could not request permission for that host: " + e.message, "#e74c3c");
    return;
  }

  if (!granted) {
    show(
      "Permission denied for " + parsed.pattern + ". The addon cannot contact that " +
      "host until you allow it, so the settings were not saved.",
      "#e74c3c"
    );
    return;
  }

  await chrome.storage.local.set({ endpoint: parsed.url, token });
  show("Saved. Bucky will send discoveries to " + parsed.url, "#27ae60");
  setTimeout(() => { status.textContent = ""; }, 4000);
});
