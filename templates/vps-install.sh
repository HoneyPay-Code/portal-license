#!/usr/bin/env bash
# Honey Pay VPS installer — served by the license portal.
# Placeholders __PORTAL_URL__ and __APP_NAME__ are replaced when served.
set -euo pipefail

PORTAL_URL="${HONEYPAY_PORTAL_URL:-__PORTAL_URL__}"
APP_NAME="${HONEYPAY_APP_NAME:-__APP_NAME__}"
INSTALL_DIR="${GETFY_DIR:-/opt/getfy}"
HTTP_PORT="${GETFY_HTTP_PORT:-80}"
HTTPS_PORT="${GETFY_HTTPS_PORT:-443}"
SWAP_MODE="${GETFY_SWAP_MODE:-auto}"

PORTAL_URL="${PORTAL_URL%/}"

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
echo "=== ${APP_NAME} — instalação VPS (Caddy) ==="
echo "Portal: ${PORTAL_URL}"
echo "Destino: ${INSTALL_DIR}"
echo "Portas: HTTP ${HTTP_PORT} / HTTPS ${HTTPS_PORT}"
echo ""

LICENSE_KEY="${HONEYPAY_LICENSE_KEY:-}"
if [ -z "$LICENSE_KEY" ]; then
  printf "Cole a chave de licença (LIC-...): "
  read -r LICENSE_KEY
fi
LICENSE_KEY="$(printf '%s' "$LICENSE_KEY" | tr -d '\r\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
if [ -z "$LICENSE_KEY" ]; then
  echo "Licença obrigatória." >&2
  exit 1
fi

$SUDO apt-get update -y
$SUDO apt-get install -y ca-certificates curl unzip

json_escape() {
  if command -v php >/dev/null 2>&1; then
    php -r 'echo json_encode(stream_get_contents(STDIN), JSON_UNESCAPED_UNICODE);' <<< "$1"
  elif command -v python3 >/dev/null 2>&1; then
    python3 -c 'import json,sys; print(json.dumps(sys.argv[1]))' "$1"
  else
    echo "Instale php-cli ou python3." >&2
    exit 1
  fi
}

json_get() {
  local key="$1"
  local raw="$2"
  if command -v php >/dev/null 2>&1; then
    php -r '$j=json_decode(stream_get_contents(STDIN), true); $k=$argv[1]; $v=$j[$k]??null; if(is_bool($v)){echo $v?"1":"0";} elseif($v===null){echo "";} else {echo $v;}' "$key" <<< "$raw"
  else
    python3 -c 'import json,sys; j=json.load(sys.stdin); v=j.get(sys.argv[1]); print("1" if v is True else ("0" if v is False else ("" if v is None else v)))' "$key" <<< "$raw"
  fi
}

echo ""
echo "=== Validando licença ==="
BODY="{\"license_key\":$(json_escape "$LICENSE_KEY")}"
HTTP_CODE=0
AUTH_JSON="$(curl -sS -w '\n%{http_code}' -X POST "${PORTAL_URL}/api/v1/install/authorize" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d "$BODY")" || {
  echo "Falha ao contactar o portal de licenças." >&2
  exit 1
}
HTTP_CODE="$(printf '%s' "$AUTH_JSON" | tail -n 1)"
AUTH_JSON="$(printf '%s' "$AUTH_JSON" | sed '$d')"

ok="$(json_get ok "$AUTH_JSON")"
msg="$(json_get message "$AUTH_JSON")"
download_url="$(json_get download_url "$AUTH_JSON")"
version="$(json_get version "$AUTH_JSON")"
sha256="$(json_get sha256 "$AUTH_JSON")"

if [ "$HTTP_CODE" != "200" ] || [ "$ok" != "1" ] || [ -z "$download_url" ]; then
  echo "Licença rejeitada (HTTP ${HTTP_CODE}): ${msg:-erro desconhecido}" >&2
  exit 1
fi

echo "Licença OK — release ${version}"

TMP_DIR="$($SUDO mktemp -d /tmp/honeypay-install.XXXXXX)"
ZIP_PATH="${TMP_DIR}/release.zip"
EXTRACT_DIR="${TMP_DIR}/extract"
$SUDO mkdir -p "$EXTRACT_DIR"

cleanup() {
  $SUDO rm -rf "$TMP_DIR" >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo ""
echo "=== Baixando código-fonte ==="
$SUDO curl -fsSL "$download_url" -o "$ZIP_PATH"

if [ -n "$sha256" ]; then
  GOT="$($SUDO sha256sum "$ZIP_PATH" | awk '{print $1}')"
  if [ "$GOT" != "$sha256" ]; then
    echo "Checksum SHA256 inválido." >&2
    echo "esperado: $sha256" >&2
    echo "obtido:   $GOT" >&2
    exit 1
  fi
  echo "Checksum OK"
fi

$SUDO unzip -q "$ZIP_PATH" -d "$EXTRACT_DIR"

ROOT_COUNT="$($SUDO find "$EXTRACT_DIR" -mindepth 1 -maxdepth 1 | wc -l | tr -d ' ')"
ROOT_DIR="$EXTRACT_DIR"
if [ "$ROOT_COUNT" = "1" ]; then
  ONLY="$($SUDO find "$EXTRACT_DIR" -mindepth 1 -maxdepth 1 | head -n 1)"
  if [ -d "$ONLY" ]; then
    ROOT_DIR="$ONLY"
  fi
fi

if [ ! -f "$ROOT_DIR/install.sh" ] && [ ! -f "$ROOT_DIR/docker/up.sh" ]; then
  echo "ZIP inválido: não encontrei install.sh nem docker/up.sh." >&2
  exit 1
fi

$SUDO mkdir -p "$(dirname "$INSTALL_DIR")"
if [ -e "$INSTALL_DIR" ] && [ ! -d "$INSTALL_DIR" ]; then
  echo "Destino existe e não é diretório: $INSTALL_DIR" >&2
  exit 1
fi

if [ -d "$INSTALL_DIR" ] && [ -n "$(ls -A "$INSTALL_DIR" 2>/dev/null || true)" ]; then
  echo "Aviso: $INSTALL_DIR já contém arquivos. Conteúdo será mesclado/sobrescrito." >&2
fi

$SUDO mkdir -p "$INSTALL_DIR"
$SUDO cp -a "$ROOT_DIR"/. "$INSTALL_DIR"/

$SUDO mkdir -p "$INSTALL_DIR/.docker"
{
  echo "LICENSE_KEY=${LICENSE_KEY}"
  echo "LICENSE_API_URL=${PORTAL_URL}"
} | $SUDO tee "$INSTALL_DIR/.docker/install-license.env" >/dev/null
$SUDO chmod 600 "$INSTALL_DIR/.docker/install-license.env"

echo ""
echo "=== Executando install-caddy.sh (Docker + Caddy) ==="
cd "$INSTALL_DIR"
$SUDO chmod +x install-caddy.sh install.sh 2>/dev/null || true
$SUDO env \
  GETFY_LEGACY_GIT_UPDATE=0 \
  GETFY_DIR="$INSTALL_DIR" \
  GETFY_HTTP_PORT="$HTTP_PORT" \
  GETFY_HTTPS_PORT="$HTTPS_PORT" \
  GETFY_SWAP_MODE="$SWAP_MODE" \
  GETFY_APP_URL="${GETFY_APP_URL:-}" \
  GETFY_WEBHOOK_PUBLIC_URL="${GETFY_WEBHOOK_PUBLIC_URL:-}" \
  GETFY_COMPOSE_FILES="docker-compose.caddy.yml" \
  bash ./install-caddy.sh

echo ""
echo "Instalação com Caddy concluída."
echo "Abra /docker-setup no navegador (domínio + licença)."
echo "TLS: Let's Encrypt no domínio (direto / Flexible); Origin Cert em .docker/certs/ para Full Strict."
