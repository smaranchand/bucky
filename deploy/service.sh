#!/bin/bash
# Install, remove or inspect the Bucky launchd agent.
#
# A LaunchAgent (not a LaunchDaemon) is deliberate: Bucky reads .env from the repo
# and must run as you, not as root. That means it starts at *login* rather than at
# boot proper — on a single-user Mac the practical difference is the login screen.
set -euo pipefail

LABEL="com.smaranchand.bucky"
REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLIST="$HOME/Library/LaunchAgents/$LABEL.plist"
PHP="$(command -v php || true)"
PORT="13337"

die() { echo "error: $*" >&2; exit 1; }

domain() { echo "gui/$(id -u)"; }

cmd_install() {
  [ -n "$PHP" ] || die "php not found on PATH"
  [ -f "$REPO/.env" ] || die "no .env in $REPO — copy .env.example and set your credentials first"
  [ -d "$REPO/vendor" ] || die "dependencies missing — run 'make install' first"

  mkdir -p "$HOME/Library/LaunchAgents" "$REPO/logs"

  # Absolute paths throughout: launchd starts with a minimal environment and does
  # not resolve anything relative to the repo.
  cat > "$PLIST" <<PLISTEOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>$LABEL</string>

    <key>ProgramArguments</key>
    <array>
        <string>$PHP</string>
        <string>-S</string>
        <string>127.0.0.1:$PORT</string>
        <string>-t</string>
        <string>$REPO/public</string>
        <string>$REPO/public/index.php</string>
    </array>

    <key>WorkingDirectory</key>
    <string>$REPO</string>

    <key>RunAtLoad</key>
    <true/>

    <!-- Restart if the server dies. Use 'service.sh stop' to shut it down, or
         launchd will simply bring it straight back. -->
    <key>KeepAlive</key>
    <true/>

    <key>StandardOutPath</key>
    <string>$REPO/logs/service.log</string>
    <key>StandardErrorPath</key>
    <string>$REPO/logs/service.log</string>

    <key>ProcessType</key>
    <string>Background</string>
</dict>
</plist>
PLISTEOF

  cmd_stop >/dev/null 2>&1 || true
  launchctl bootstrap "$(domain)" "$PLIST" 2>/dev/null || launchctl load "$PLIST"

  echo "installed  $PLIST"
  echo "listening  http://127.0.0.1:$PORT/buckets"
  echo "logs       $REPO/logs/service.log"
  echo
  echo "Starts automatically at every login. 'deploy/service.sh uninstall' removes it."
}

cmd_uninstall() {
  cmd_stop >/dev/null 2>&1 || true
  [ -f "$PLIST" ] && rm -f "$PLIST" && echo "removed $PLIST" || echo "nothing installed"
}

cmd_stop() {
  launchctl bootout "$(domain)/$LABEL" 2>/dev/null \
    || launchctl unload "$PLIST" 2>/dev/null \
    || return 1
  echo "stopped"
}

cmd_start() {
  [ -f "$PLIST" ] || die "not installed — run 'deploy/service.sh install'"
  launchctl bootstrap "$(domain)" "$PLIST" 2>/dev/null || launchctl load "$PLIST"
  echo "started"
}

cmd_status() {
  if [ ! -f "$PLIST" ]; then
    echo "agent      not installed"
  else
    echo "agent      $PLIST"
    launchctl print "$(domain)/$LABEL" 2>/dev/null \
      | awk '/^\tstate = /{print "state     ", $3} /^\tpid = /{print "pid       ", $3}' \
      || echo "state      not loaded"
  fi
  if curl -s -o /dev/null --max-time 3 "http://127.0.0.1:$PORT/buckets"; then
    echo "http       responding on 127.0.0.1:$PORT"
  else
    echo "http       not responding on 127.0.0.1:$PORT"
  fi
}

case "${1:-}" in
  install)   cmd_install ;;
  uninstall) cmd_uninstall ;;
  start)     cmd_start ;;
  stop)      cmd_stop ;;
  status)    cmd_status ;;
  *) echo "usage: $0 {install|uninstall|start|stop|status}" >&2; exit 2 ;;
esac
