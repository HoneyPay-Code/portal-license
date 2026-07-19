#!/usr/bin/env bash
# Atualiza o portal de licenças no disco e reconstrói o container.
set -euo pipefail

INSTALL_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$INSTALL_DIR"

SUDO=""
if [ "$(id -u)" -ne 0 ]; then
  if command -v sudo >/dev/null 2>&1; then
    SUDO="sudo"
  else
    echo "Rode como root ou instale sudo." >&2
    exit 1
  fi
fi

BRANCH="${HONEYPAY_PORTAL_BRANCH:-main}"
REPO_URL="${HONEYPAY_PORTAL_REPO:-https://github.com/HoneyPay-Code/portal-license.git}"

if [ -d .git ]; then
  $SUDO git remote set-url origin "$REPO_URL" >/dev/null 2>&1 || true
  $SUDO git fetch --all --prune
  $SUDO git checkout -B "$BRANCH" "origin/$BRANCH"
  $SUDO git reset --hard "origin/$BRANCH"
fi

$SUDO mkdir -p storage/releases storage/rate
$SUDO chmod -R 775 storage || true

COMPOSE_ENV=()
if [ -f .env.compose ]; then
  COMPOSE_ENV+=(--env-file .env.compose)
fi
if [ -f .env ]; then
  COMPOSE_ENV+=(--env-file .env)
fi

$SUDO docker compose "${COMPOSE_ENV[@]}" up -d --build

echo "Portal atualizado."
