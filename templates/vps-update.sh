#!/usr/bin/env bash
# Honey Pay VPS updater — served by the license portal.
# Placeholders __PORTAL_URL__ and __APP_NAME__ are replaced when served.
# Atualiza /opt/getfy com o release mais recente SEM apagar dados (volumes Docker /.env).
set -euo pipefail

PORTAL_URL="${HONEYPAY_PORTAL_URL:-__PORTAL_URL__}"
APP_NAME="${HONEYPAY_APP_NAME:-__APP_NAME__}"
INSTALL_DIR="${GETFY_DIR:-/opt/getfy}"

PORTAL_URL="${PORTAL_URL%/}"

if [ "$(uname -s)" != "Linux" ]; then
  echo "Este atualizador é para Linux (Ubuntu/Debian)." >&2
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

if ! command -v docker >/dev/null 2>&1; then
  echo "docker não encontrado. O update exige a mesma stack Docker da instalação." >&2
  exit 1
fi

if [ ! -d "$INSTALL_DIR" ]; then
  echo "Instalação não encontrada em ${INSTALL_DIR}." >&2
  echo "Use o instalador: curl -fsSL ${PORTAL_URL}/vps-install.sh | sudo bash" >&2
  exit 1
fi

if [ ! -f "$INSTALL_DIR/artisan" ] && [ ! -f "$INSTALL_DIR/docker-compose.caddy.yml" ] && [ ! -f "$INSTALL_DIR/docker/up.sh" ]; then
  echo "${INSTALL_DIR} não parece uma instalação Honey Pay/Getfy." >&2
  exit 1
fi

echo ""
echo "=== ${APP_NAME} — atualização VPS ==="
echo "Portal: ${PORTAL_URL}"
echo "Destino: ${INSTALL_DIR}"
CURRENT_VERSION=""
if [ -f "$INSTALL_DIR/VERSION" ]; then
  CURRENT_VERSION="$(tr -d ' \r\n' < "$INSTALL_DIR/VERSION" || true)"
fi
if [ -n "$CURRENT_VERSION" ]; then
  echo "Versão atual: ${CURRENT_VERSION}"
fi
echo ""

# Com `curl | bash`, stdin = script — ler teclado de /dev/tty.
LICENSE_KEY="${HONEYPAY_LICENSE_KEY:-}"
if [ -z "$LICENSE_KEY" ] && [ -f "$INSTALL_DIR/.install-license.env" ]; then
  LICENSE_KEY="$(grep -E '^\s*LICENSE_KEY\s*=' "$INSTALL_DIR/.install-license.env" 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d ' \r\n\"' || true)"
fi
if [ -z "$LICENSE_KEY" ] && [ -f "$INSTALL_DIR/.docker/install-license.env" ]; then
  LICENSE_KEY="$(grep -E '^\s*LICENSE_KEY\s*=' "$INSTALL_DIR/.docker/install-license.env" 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d ' \r\n\"' || true)"
fi
if [ -z "$LICENSE_KEY" ]; then
  if [ -r /dev/tty ]; then
    printf "Cole a chave de licença (LIC-...): " > /dev/tty
    # shellcheck disable=SC2162
    read -r LICENSE_KEY < /dev/tty
  else
    echo "Não foi possível ler o teclado (sem /dev/tty)." >&2
    echo "Rode assim:" >&2
    echo "  curl -fsSL ${PORTAL_URL}/vps-update.sh -o /tmp/honeypay-vps-update.sh" >&2
    echo "  sudo bash /tmp/honeypay-vps-update.sh" >&2
    echo "Ou defina HONEYPAY_LICENSE_KEY=LIC-... antes do comando." >&2
    exit 1
  fi
fi
LICENSE_KEY="$(printf '%s' "$LICENSE_KEY" | tr -d '\r\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
if [ -z "$LICENSE_KEY" ]; then
  echo "Licença obrigatória." >&2
  exit 1
fi
if ! printf '%s' "$LICENSE_KEY" | grep -Eq '^LIC-'; then
  echo "A chave deve começar com LIC- (copiada em ${PORTAL_URL}/app/license)." >&2
  exit 1
fi

echo "Chave recebida. Continuando…"
echo ""

$SUDO apt-get update -y >/dev/null 2>&1 || true
$SUDO apt-get install -y ca-certificates curl unzip rsync >/dev/null 2>&1 || \
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

echo "=== Validando licença / obtendo release ==="
BODY="{\"license_key\":$(json_escape "$LICENSE_KEY")}"
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

echo "Release disponível: ${version}"
if [ -n "$CURRENT_VERSION" ] && [ "$CURRENT_VERSION" = "$version" ]; then
  if [ -r /dev/tty ]; then
    printf "Já está na versão %s. Reaplicar o mesmo release? [y/N]: " "$version" > /dev/tty
    # shellcheck disable=SC2162
    read -r CONFIRM < /dev/tty || CONFIRM=""
    case "$(printf '%s' "$CONFIRM" | tr '[:upper:]' '[:lower:]')" in
      y|yes|s|sim) ;;
      *) echo "Update cancelado."; exit 0 ;;
    esac
  fi
fi

TMP_DIR="$($SUDO mktemp -d /tmp/honeypay-update.XXXXXX)"
ZIP_PATH="${TMP_DIR}/release.zip"
EXTRACT_DIR="${TMP_DIR}/extract"
$SUDO mkdir -p "$EXTRACT_DIR"

cleanup() {
  $SUDO rm -rf "$TMP_DIR" >/dev/null 2>&1 || true
}
trap cleanup EXIT

echo ""
echo "=== Baixando release ${version} ==="
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

if [ ! -f "$ROOT_DIR/docker/up.sh" ] && [ ! -f "$ROOT_DIR/update-caddy.sh" ] && [ ! -f "$ROOT_DIR/artisan" ]; then
  echo "ZIP inválido: não parece um release do gateway." >&2
  exit 1
fi

echo ""
echo "=== Aplicando arquivos (preserva .env, storage, .docker) ==="
# Não apaga volumes Docker (postgres/redis/storage/.docker). Só sobrescreve código da app.
if command -v rsync >/dev/null 2>&1; then
  $SUDO rsync -a \
    --exclude '.env' \
    --exclude '.env.*' \
    --exclude 'storage/' \
    --exclude '.docker/' \
    --exclude 'node_modules/' \
    --exclude '.git/' \
    --exclude 'bootstrap/cache/*.php' \
    "$ROOT_DIR"/ "$INSTALL_DIR"/
else
  echo "rsync ausente — usando cp (menos fino)." >&2
  $SUDO cp -a "$ROOT_DIR"/. "$INSTALL_DIR"/
fi

# Mantém prefill de licença atualizado (não toca em dados).
$SUDO mkdir -p "$INSTALL_DIR/.docker"
{
  echo "LICENSE_KEY=${LICENSE_KEY}"
  echo "LICENSE_API_URL=${PORTAL_URL}"
} | $SUDO tee "$INSTALL_DIR/.docker/install-license.env" >/dev/null
$SUDO cp -f "$INSTALL_DIR/.docker/install-license.env" "$INSTALL_DIR/.install-license.env" 2>/dev/null || true
$SUDO chmod 600 "$INSTALL_DIR/.docker/install-license.env" "$INSTALL_DIR/.install-license.env" 2>/dev/null || true

cd "$INSTALL_DIR"

$SUDO chmod +x docker/up.sh docker/build-frontend.sh docker/install-composer-deps.sh \
  docker/ensure-upload-limits.sh docker/detect-compose-files.sh docker/verify-workers.sh \
  update-caddy.sh update.sh 2>/dev/null || true

if [ -f docker/ensure-upload-limits.sh ]; then
  echo ""
  echo "=== Limites de upload ==="
  $SUDO sh docker/ensure-upload-limits.sh
fi

if [ ! -f docker/build-frontend.sh ]; then
  echo "Erro: docker/build-frontend.sh ausente no release." >&2
  exit 1
fi
echo ""
echo "=== Build do frontend ==="
$SUDO sh docker/build-frontend.sh
if [ ! -f public/build/manifest.json ]; then
  echo "Erro: public/build/manifest.json não foi gerado." >&2
  exit 1
fi

if [ -f docker/install-composer-deps.sh ]; then
  echo ""
  echo "=== Dependências PHP (Composer) ==="
  $SUDO sh docker/install-composer-deps.sh
fi

echo ""
echo "=== Reiniciando stack Docker ==="
COMPOSE_FILES=""
if [ -f docker/detect-compose-files.sh ]; then
  COMPOSE_FILES="$($SUDO sh docker/detect-compose-files.sh 2>/dev/null || true)"
fi
if [ -z "$COMPOSE_FILES" ]; then
  if [ -f .docker/compose-profile ] && grep -qi caddy .docker/compose-profile 2>/dev/null; then
    COMPOSE_FILES="docker-compose.caddy.yml"
  elif [ -f docker-compose.caddy.yml ]; then
    COMPOSE_FILES="docker-compose.caddy.yml"
  else
    COMPOSE_FILES="docker-compose.yml"
  fi
fi
echo "Compose: $COMPOSE_FILES"

# Snapshot das credenciais DB antes do up (detectar rotação acidental).
STACK_ENV="$INSTALL_DIR/.docker/stack.env"
DB_USER_BEFORE=""
DB_PASS_BEFORE=""
if [ -f "$STACK_ENV" ]; then
  DB_USER_BEFORE="$(grep -E '^\s*GETFY_DB_USERNAME\s*=' "$STACK_ENV" 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d ' \r\n\"' || true)"
  DB_PASS_BEFORE="$(grep -E '^\s*GETFY_DB_PASSWORD\s*=' "$STACK_ENV" 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d ' \r\n\"' || true)"
fi

set +e
$SUDO env GETFY_COMPOSE_FILES="$COMPOSE_FILES" GETFY_APP_ENV=production GETFY_APP_DEBUG=false \
  GETFY_SKIP_DOCKER_BUILD="${GETFY_SKIP_DOCKER_BUILD:-0}" \
  sh docker/up.sh
UP_RC=$?
set -e

if [ -f "$STACK_ENV" ] && [ -n "$DB_PASS_BEFORE" ]; then
  DB_PASS_AFTER="$(grep -E '^\s*GETFY_DB_PASSWORD\s*=' "$STACK_ENV" 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d ' \r\n\"' || true)"
  if [ -n "$DB_PASS_AFTER" ] && [ "$DB_PASS_AFTER" != "$DB_PASS_BEFORE" ]; then
    echo "AVISO: GETFY_DB_PASSWORD em .docker/stack.env mudou durante o up — restaurando." >&2
    $SUDO sed -i "s|^GETFY_DB_USERNAME=.*|GETFY_DB_USERNAME=${DB_USER_BEFORE}|" "$STACK_ENV"
    $SUDO sed -i "s|^GETFY_DB_PASSWORD=.*|GETFY_DB_PASSWORD=${DB_PASS_BEFORE}|" "$STACK_ENV"
    $SUDO env GETFY_COMPOSE_FILES="$COMPOSE_FILES" GETFY_APP_ENV=production GETFY_APP_DEBUG=false \
      GETFY_SKIP_DOCKER_BUILD=1 \
      sh docker/up.sh || true
  fi
fi

COMPOSE_EXEC_ARGS=""
OLD_IFS="$IFS"
IFS=' '
# shellcheck disable=SC2086
for f in $COMPOSE_FILES; do
  if [ -n "$f" ]; then
    COMPOSE_EXEC_ARGS="$COMPOSE_EXEC_ARGS -f $f"
  fi
done
IFS="$OLD_IFS"

PROJECT_NAME="getfy"
if [ -f "$STACK_ENV" ]; then
  PROJECT_NAME="$(grep -E '^\s*GETFY_COMPOSE_PROJECT_NAME\s*=' "$STACK_ENV" 2>/dev/null | tail -n1 | cut -d= -f2- | tr -d ' \r\n\"' || true)"
  [ -z "$PROJECT_NAME" ] && PROJECT_NAME="getfy"
fi

COMPOSE_ENV_ARGS=""
if [ -f "$STACK_ENV" ]; then
  COMPOSE_ENV_ARGS="--env-file $STACK_ENV"
fi
if [ -f "$INSTALL_DIR/.env" ]; then
  COMPOSE_ENV_ARGS="$COMPOSE_ENV_ARGS --env-file $INSTALL_DIR/.env"
fi

# shellcheck disable=SC2086
dc() { $SUDO docker compose -p "$PROJECT_NAME" $COMPOSE_EXEC_ARGS $COMPOSE_ENV_ARGS "$@"; }

if [ "$UP_RC" -ne 0 ]; then
  echo "" >&2
  echo "=== FALHA ao subir a stack (exit ${UP_RC}) — logs do app ===" >&2
  dc logs app --tail 80 2>&1 || true
  echo "" >&2
  echo "Diagnóstico rápido:" >&2
  echo "  cd ${INSTALL_DIR}" >&2
  echo "  docker compose -p ${PROJECT_NAME} -f ${COMPOSE_FILES} --env-file .docker/stack.env ps" >&2
  echo "  docker compose -p ${PROJECT_NAME} -f ${COMPOSE_FILES} --env-file .docker/stack.env logs app --tail 100" >&2
  echo "Se aparecer 'Banco indisponível', as credenciais do Postgres no stack.env não batem com o volume." >&2
  exit "$UP_RC"
fi

echo ""
echo "=== Migrações ==="
dc exec -T app php artisan migrate --force 2>/dev/null \
  || echo "Aviso: migrate não executou (verifique o container app)." >&2

dc exec -T app php artisan config:clear 2>/dev/null || true
dc exec -T app php artisan pwa:ensure-vapid 2>/dev/null || true

if [ -f docker/verify-workers.sh ]; then
  echo ""
  echo "=== Verificação de workers ==="
  $SUDO sh docker/verify-workers.sh || true
fi

NEW_VERSION="$(tr -d ' \r\n' < "$INSTALL_DIR/VERSION" 2>/dev/null || echo "$version")"
echo ""
echo "Atualização concluída."
echo "Versão: ${NEW_VERSION}"
echo "Dados (banco / storage / .env) foram preservados."
echo "Abra o painel e confira login + um pagamento de teste."
