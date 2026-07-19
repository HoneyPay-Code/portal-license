#!/usr/bin/env bash
# Honey Pay — License Portal installer (Docker)
# Portas padrão: HTTP 8081 (gateway usa 80/443 na mesma VPS)
set -euo pipefail

REPO_URL="${HONEYPAY_PORTAL_REPO:-https://github.com/HoneyPay-Code/portal-license.git}"
BRANCH="${HONEYPAY_PORTAL_BRANCH:-main}"
INSTALL_DIR="${HONEYPAY_PORTAL_DIR:-/opt/honeypay-portal}"
HTTP_PORT="${LICENSE_API_HTTP_PORT:-8081}"
SWAP_MODE="${HONEYPAY_SWAP_MODE:-auto}"

if [ "$(uname -s)" != "Linux" ]; then
  echo "Este instalador é para Linux (Ubuntu/Debian)." >&2
  exit 1
fi

if ! command -v apt-get >/dev/null 2>&1; then
  echo "Distribuição não suportada (precisa de apt-get)." >&2
  exit 1
fi

SUDO=""
if [ "$(id -u)" -ne 0 ]; then
  if command -v sudo >/dev/null 2>&1; then
    SUDO="sudo"
  else
    echo "Rode como root ou instale sudo." >&2
    exit 1
  fi
fi

export DEBIAN_FRONTEND=noninteractive

echo ""
echo "=== Honey Pay License Portal — instalação Docker ==="
echo "Destino: ${INSTALL_DIR}"
echo "Porta HTTP do host: ${HTTP_PORT} (gateway continua em 80/443)"
echo ""

$SUDO apt-get update -y
$SUDO apt-get install -y ca-certificates curl git gnupg lsb-release openssl

if [ "$SWAP_MODE" != "off" ]; then
  MEM_KB="$(awk '/^MemTotal:/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
  SWAP_KB="$(awk '/^SwapTotal:/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
  MEM_GB=$(( (MEM_KB + 1048575) / 1048576 ))
  SHOULD_CREATE_SWAP=0
  if [ "$SWAP_MODE" = "on" ]; then
    SHOULD_CREATE_SWAP=1
  elif [ "$SWAP_MODE" = "auto" ] && [ "$SWAP_KB" -eq 0 ] && [ "$MEM_GB" -gt 0 ] && [ "$MEM_GB" -le 8 ]; then
    SHOULD_CREATE_SWAP=1
  fi
  if [ "$SHOULD_CREATE_SWAP" -eq 1 ] && [ ! -f /swapfile ]; then
    SWAP_GB=2
    if command -v fallocate >/dev/null 2>&1; then
      $SUDO fallocate -l "${SWAP_GB}G" /swapfile
    else
      $SUDO dd if=/dev/zero of=/swapfile bs=1M count=$((SWAP_GB * 1024)) status=progress
    fi
    $SUDO chmod 600 /swapfile
    $SUDO mkswap /swapfile >/dev/null
    $SUDO swapon /swapfile || true
    if ! grep -Eq '^\s*/swapfile\s+' /etc/fstab; then
      echo "/swapfile none swap sw 0 0" | $SUDO tee -a /etc/fstab >/dev/null
    fi
  fi
fi

if ! command -v docker >/dev/null 2>&1; then
  $SUDO install -m 0755 -d /etc/apt/keyrings
  $SUDO rm -f /etc/apt/keyrings/docker.gpg
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg | $SUDO gpg --dearmor -o /etc/apt/keyrings/docker.gpg
  $SUDO chmod a+r /etc/apt/keyrings/docker.gpg
  CODENAME="$(. /etc/os-release && echo "${VERSION_CODENAME:-}")"
  if [ -z "$CODENAME" ]; then
    CODENAME="$(lsb_release -cs 2>/dev/null || true)"
  fi
  ARCH="$(dpkg --print-architecture)"
  echo "deb [arch=$ARCH signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $CODENAME stable" | $SUDO tee /etc/apt/sources.list.d/docker.list >/dev/null
  $SUDO apt-get update -y
  $SUDO apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  $SUDO systemctl enable --now docker >/dev/null 2>&1 || true
fi

if [ -n "${SUDO_USER:-}" ] && ! id -nG "$SUDO_USER" 2>/dev/null | grep -qw docker; then
  $SUDO usermod -aG docker "$SUDO_USER" || true
fi

if [ -e "$INSTALL_DIR" ] && [ ! -d "$INSTALL_DIR" ]; then
  echo "Destino existe e não é diretório: $INSTALL_DIR" >&2
  exit 1
fi

if [ -d "$INSTALL_DIR/.git" ]; then
  echo "=== Atualizando repositório ==="
  $SUDO git -C "$INSTALL_DIR" remote set-url origin "$REPO_URL" >/dev/null 2>&1 || true
  $SUDO git -C "$INSTALL_DIR" fetch --all --prune
  $SUDO git -C "$INSTALL_DIR" checkout -B "$BRANCH" "origin/$BRANCH"
  $SUDO git -C "$INSTALL_DIR" reset --hard "origin/$BRANCH"
else
  echo "=== Clonando repositório ==="
  $SUDO mkdir -p "$(dirname "$INSTALL_DIR")"
  $SUDO rm -rf "$INSTALL_DIR"
  $SUDO git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$INSTALL_DIR"
fi

cd "$INSTALL_DIR"
$SUDO mkdir -p storage/releases storage/rate
$SUDO chmod -R 775 storage || true

IP="$(curl -fsSL https://api.ipify.org 2>/dev/null || true)"
if [ -z "$IP" ]; then
  IP="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
fi
if [ -z "$IP" ]; then
  IP="SEU_IP"
fi

DEFAULT_URL="http://${IP}:${HTTP_PORT}"
if [ -z "${APP_URL:-}" ]; then
  if [ -t 0 ]; then
    echo ""
    echo "URL pública do portal (clientes/gateways usam esta URL)."
    printf "Digite a URL [%s]: " "$DEFAULT_URL"
    read -r APP_URL_READ || APP_URL_READ=""
    APP_URL="${APP_URL_READ:-$DEFAULT_URL}"
  else
    APP_URL="$DEFAULT_URL"
  fi
fi
APP_URL="${APP_URL%/}"

ADMIN_EMAIL="${ADMIN_EMAIL:-admin@localhost}"
if [ -z "${ADMIN_PASSWORD:-}" ]; then
  ADMIN_PASSWORD="$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)"
  GENERATED_ADMIN_PASS=1
else
  GENERATED_ADMIN_PASS=0
fi

SIGNING_KEY="${LICENSE_SIGNING_KEY:-$(openssl rand -hex 32)}"

if [ ! -f .env ]; then
  $SUDO cp .env.example .env
fi

set_env() {
  local key="$1"
  local val="$2"
  local tmp
  tmp="$($SUDO mktemp)"
  if $SUDO grep -Eq "^[[:space:]]*${key}=" .env 2>/dev/null; then
    $SUDO awk -v k="$key" -v v="$val" '
      BEGIN { done=0 }
      $0 ~ "^[[:space:]]*"k"=" { print k"="v; done=1; next }
      { print }
      END { if (!done) print k"="v }
    ' .env > "$tmp"
    $SUDO mv "$tmp" .env
  else
    echo "${key}=${val}" | $SUDO tee -a .env >/dev/null
    $SUDO rm -f "$tmp"
  fi
}

# Aspas se houver espaços
quote_if_needed() {
  local v="$1"
  case "$v" in
    *[[:space:]]*|*\#*|*"'"*|*\"*) printf '"%s"' "${v//\"/\\\"}" ;;
    *) printf '%s' "$v" ;;
  esac
}

set_env APP_NAME "$(quote_if_needed "${APP_NAME:-Honey Pay Licenses}")"
set_env APP_URL "$(quote_if_needed "$APP_URL")"
set_env APP_ENV production
set_env SESSION_SECURE false
set_env TRUST_PROXY false
set_env LICENSE_SIGNING_KEY "$SIGNING_KEY"
set_env WEBHOOK_ACCEPT_TEST false
set_env ADMIN_EMAIL "$(quote_if_needed "$ADMIN_EMAIL")"
set_env ADMIN_PASSWORD "$(quote_if_needed "$ADMIN_PASSWORD")"
set_env DB_DRIVER sqlite
set_env DB_PATH storage/database.sqlite
set_env SUPPORT_CONTACT "$(quote_if_needed "${SUPPORT_CONTACT:-suporte@localhost}")"
set_env LICENSE_API_HTTP_PORT "$HTTP_PORT"

# stack.env para compose
{
  echo "LICENSE_API_HTTP_PORT=${HTTP_PORT}"
  echo "APP_URL=${APP_URL}"
} | $SUDO tee .env.compose >/dev/null

echo ""
echo "=== Build e start (Docker) ==="
if ss -ltn 2>/dev/null | awk '{print $4}' | grep -qE "(^|:)${HTTP_PORT}$"; then
  echo "Aviso: porta ${HTTP_PORT} parece em uso. Ajuste LICENSE_API_HTTP_PORT." >&2
fi

$SUDO docker compose --env-file .env --env-file .env.compose up -d --build

echo ""
echo "Portal de licenças no ar."
echo "  URL:      ${APP_URL}"
echo "  Cliente:  ${APP_URL}/login"
echo "  Admin:    ${APP_URL}/admin/login"
echo "  Health:   ${APP_URL}/api/health"
echo "  E-mail:   ${ADMIN_EMAIL}"
if [ "$GENERATED_ADMIN_PASS" = "1" ]; then
  echo "  Senha:    ${ADMIN_PASSWORD}"
  echo ""
  echo "Guarde a senha do admin — ela foi gerada automaticamente."
fi
echo ""
echo "Mesma VPS que o gateway: portal na porta ${HTTP_PORT}; gateway em 80/443."
if [[ "$APP_URL" == https://* ]] && [[ "$APP_URL" != *":${HTTP_PORT}"* ]]; then
  echo ""
  echo "⚠ Cloudflare / domínio HTTPS:"
  echo "  O container escuta só a porta ${HTTP_PORT}. Cloudflare (laranja) fala com a origem em 80/443."
  echo "  Sem proxy reverso → Error 521."
  echo "  Rode agora:"
  echo "    cd ${INSTALL_DIR} && sudo bash setup-caddy-proxy.sh"
  echo "  Depois no Cloudflare: SSL/TLS = Full (não Flexible)."
fi
echo "Atualizar depois: cd ${INSTALL_DIR} && sudo bash update.sh"
echo ""
