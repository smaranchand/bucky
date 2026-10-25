const DEFAULT_ENDPOINT = "http://127.0.0.1:13337/process";

const endpointEl = document.getElementById("endpoint");
const stateEl = document.getElementById("state");
const noteEl = document.getElementById("note");

function setState(text, cls, note) {
  stateEl.textContent = text;
  stateEl.className = "pill " + cls;
  noteEl.textContent = note || "";
}

async function render() {
  const { endpoint, token } = await chrome.storage.local.get(["endpoint", "token"]);
  const configured = endpoint || DEFAULT_ENDPOINT;
  endpointEl.textContent = configured;

  if (!token) {
    setState("Not configured", "bad", "No API token set. Bucky is not sending anything — open settings and paste the token from your .env.");
    return;
  }

  let parsed;
  try {
    parsed = parseEndpoint(configured);
  } catch {
    setState("Invalid endpoint", "bad", "The saved endpoint is not a valid URL. Open settings to correct it.");
    return;
  }

  // A saved endpoint is useless if its host permission was later revoked.
  const allowed = await chrome.permissions.contains({ origins: [parsed.pattern] });
  if (!allowed) {
    setState("Permission missing", "bad", "The browser is blocking requests to this host. Re-save the endpoint in settings to grant permission.");
    return;
  }

  if (parsed.isPlaintext && !parsed.isLocal) {
    setState("Active — insecure", "warn", "Sending over plaintext HTTP to a remote host. The token and discovered bucket names are unencrypted in transit.");
    return;
  }

  setState("Active", "ok", "");
}

document.getElementById("edit").addEventListener("click", () => {
  chrome.runtime.openOptionsPage();
  window.close();
});

document.getElementById("dashboard").addEventListener("click", async () => {
  const { endpoint } = await chrome.storage.local.get(["endpoint"]);
  let dashboard = "http://127.0.0.1:13337/buckets";
  try {
    dashboard = new URL("/buckets", parseEndpoint(endpoint || DEFAULT_ENDPOINT).origin).href;
  } catch {
    // fall back to the local default
  }
  chrome.tabs.create({ url: dashboard });
  window.close();
});

render();
