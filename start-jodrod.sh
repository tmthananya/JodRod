#!/usr/bin/env bash
# ==============================================================================
# JodRod (จอดรถ) - Single-Command Startup Script
# Starts PHP backend on 0.0.0.0:8088 and Expo Dev Server on port 8083.
# Safe Ctrl+C shutdown, automatic LAN IP detection, and process reuse.
# ==============================================================================

set -e

# Project root directory (where this script lives)
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

PHP_PORT=8088
EXPO_PORT=8083
PHP_PID=""
PHP_STARTED_BY_SCRIPT=0

echo "=========================================================="
echo "          🚗 JodRod - Single-Command Starter"
echo "=========================================================="
echo "Project Directory: $PROJECT_DIR"

# 1. Detect Mac's Current LAN IP Address
get_lan_ip() {
  local ip=""
  # Try Wi-Fi interface en0 first
  ip=$(ipconfig getifaddr en0 2>/dev/null || true)
  if [ -z "$ip" ]; then
    # Try secondary interface en1
    ip=$(ipconfig getifaddr en1 2>/dev/null || true)
  fi
  if [ -z "$ip" ]; then
    # Fallback to general ifconfig
    ip=$(ifconfig 2>/dev/null | grep "inet " | grep -v 127.0.0.1 | awk '{print $2}' | head -n 1 || true)
  fi
  echo "$ip"
}

LAN_IP=$(get_lan_ip)

if [ -n "$LAN_IP" ]; then
  echo "Current Mac Wi-Fi IP: $LAN_IP"
else
  LAN_IP="127.0.0.1"
  echo "Warning: Could not detect Wi-Fi IP. Defaulting to 127.0.0.1."
fi

# 2. Check & Sync mobile/App.js with Current LAN IP
APP_JS="$PROJECT_DIR/mobile/App.js"
if [ -f "$APP_JS" ] && [ "$LAN_IP" != "127.0.0.1" ]; then
  CONFIGURED_IP=$(grep -E "^const CURRENT_MAC_IP =" "$APP_JS" | head -n 1 | sed -E "s/.*'([^']+)'.*/\1/" || true)
  if [ -n "$CONFIGURED_IP" ] && [ "$CONFIGURED_IP" != "$LAN_IP" ]; then
    echo "Syncing mobile/App.js fallback IP from $CONFIGURED_IP to $LAN_IP..."
    sed -i '' "s/const CURRENT_MAC_IP = '$CONFIGURED_IP';/const CURRENT_MAC_IP = '$LAN_IP';/" "$APP_JS"
    echo "[✓] mobile/App.js updated with active Wi-Fi IP: $LAN_IP"
  else
    echo "[✓] mobile/App.js is configured for IP: $CONFIGURED_IP"
  fi
fi

# 3. Graceful Cleanup Function (Ctrl+C Handler)
cleanup() {
  local exit_code=$?
  if [ "$exit_code" -ne 0 ] && [ "$PHP_STARTED_BY_SCRIPT" -eq 0 ]; then
    exit $exit_code
  fi

  echo ""
  echo "----------------------------------------------------------"
  echo "Shutting down JodRod..."

  if [ "$PHP_STARTED_BY_SCRIPT" -eq 1 ] && [ -n "$PHP_PID" ]; then
    if kill -0 "$PHP_PID" 2>/dev/null; then
      echo "Stopping PHP server (PID: $PHP_PID)..."
      kill "$PHP_PID" 2>/dev/null || true
    fi
  else
    echo "Leaving pre-existing PHP server running untouched."
  fi

  echo "All servers stopped safely. Have a great day! 🚗"
  echo "----------------------------------------------------------"
  exit $exit_code
}

trap cleanup INT TERM EXIT

# 4. Check & Start PHP Server (Port 8088)
echo ""
echo "Checking PHP server on port $PHP_PORT..."

if lsof -i :$PHP_PORT -sTCP:LISTEN >/dev/null 2>&1; then
  # Port 8088 is already occupied
  EXISTING_PID=$(lsof -ti :$PHP_PORT -sTCP:LISTEN | head -n 1)
  HTTP_CHECK=$(curl -s -m 2 -o /dev/null -w "%{http_code}" http://127.0.0.1:$PHP_PORT/ || true)

  if [ "$HTTP_CHECK" = "200" ]; then
    echo "[✓] Found existing JodRod PHP server on port $PHP_PORT (PID: $EXISTING_PID)."
    echo "    Reusing active server without starting a duplicate."
    PHP_STARTED_BY_SCRIPT=0
  else
    echo "[!] Warning: Port $PHP_PORT is in use by PID $EXISTING_PID, but not responding with HTTP 200."
    echo "    Will proceed reusing existing process (will not kill unrelated processes)."
    PHP_STARTED_BY_SCRIPT=0
  fi
else
  echo "Starting PHP server bound to 0.0.0.0:$PHP_PORT..."
  php -S 0.0.0.0:$PHP_PORT -t "$PROJECT_DIR" > "$PROJECT_DIR/.php_server.log" 2>&1 &
  PHP_PID=$!
  PHP_STARTED_BY_SCRIPT=1

  # Wait briefly and verify startup
  sleep 1
  if kill -0 "$PHP_PID" 2>/dev/null; then
    echo "[✓] PHP server started successfully (PID: $PHP_PID)."
  else
    echo "[✗] Error: Failed to start PHP server. Log output:"
    cat "$PROJECT_DIR/.php_server.log"
    exit 1
  fi
fi

echo "    • Desktop URL: http://127.0.0.1:$PHP_PORT/#map"
echo "    • Mobile URL:  http://$LAN_IP:$PHP_PORT/#map"

# 5. Check & Start Expo Dev Server (Port 8083)
echo ""
echo "Checking Expo server on port $EXPO_PORT..."

if lsof -i :$EXPO_PORT -sTCP:LISTEN >/dev/null 2>&1; then
  EXISTING_EXPO_PID=$(lsof -ti :$EXPO_PORT -sTCP:LISTEN | head -n 1)
  echo "[!] Port $EXPO_PORT is currently in use by process PID: $EXISTING_EXPO_PID."
  echo "    If you have a previous Expo terminal running, stop it (press Ctrl+C in that terminal)"
  echo "    or run: kill $EXISTING_EXPO_PID"
  echo ""
  # Prompt user or exit cleanly
  exit 1
fi

EXPO_CLI="$PROJECT_DIR/mobile/node_modules/expo/bin/cli"
if [ ! -f "$EXPO_CLI" ]; then
  echo "[✗] Error: Expo CLI not found at mobile/node_modules/expo/bin/cli."
  echo "    Please check your mobile/ directory."
  exit 1
fi

echo "=========================================================="
echo "  Starting Expo Development Server (Port $EXPO_PORT)..."
echo "  Scan the QR code below using Expo Go on your iPhone."
echo "  Press Ctrl+C anytime to stop."
echo "=========================================================="
echo ""

cd "$PROJECT_DIR/mobile"
exec node node_modules/expo/bin/cli start --lan --go --port $EXPO_PORT
