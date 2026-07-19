#!/usr/bin/env bash
# Sobe Caddy na 80/443 apontando portal.dominio → 127.0.0.1:8081
# Resolve Cloudflare Error 521 quando o portal Docker só escuta 8081.
set -euo pipefail

SUDO=""
if [ "$(id -u)" -ne 0 ]; then
  if command -v sudo >/dev/null 2>&1; then
    SUDO="sudo"
  else
    echo "Rode como root ou com sudo." >&2
    exit 1
  fi
fi

INSTALL_DIR="${HONEYPAY_PORTAL_DIR:-/opt/honeypay-portal}"
HTTP_PORT="${LICENSE_API_HTTP_PORT:-8081}"
DOMAIN=""
EMAIL=""

if [ -f "${INSTALL_DIR}/.env" ]; then
  # shellcheck disable=SC1091
  set -a
  # only grab APP_URL without sourcing whole env (passwords etc.)
  APP_URL_LINE="$(grep -E '^APP_URL=' "${INSTALL_DIR}/.env" | tail -n1 || true)"
  set +a
  if [[ "$APP_URL_LINE" =~ APP_URL=(https?://([^/:]+)) ]]; then
    DOMAIN="${BASH_REMATCH[2]}"
  fi
fi

if [ -z "$DOMAIN" ]; then
  read -r -p "Domínio do portal (ex.: portal.honeypay.tech): " DOMAIN
fi
DOMAIN="${DOMAIN#http://}"
DOMAIN="${DOMAIN#https://}"
DOMAIN="${DOMAIN%%/*}"
DOMAIN="${DOMAIN%%:*}"

if [ -z "$DOMAIN" ]; then
  echo "Domínio obrigatório." >&2
  exit 1
fi

read -r -p "E-mail para Let's Encrypt [${EMAIL:-admin@$DOMAIN}]: " EMAIL_READ || true
EMAIL="${EMAIL_READ:-admin@$DOMAIN}"

echo ""
echo "=== Proxy Caddy: https://${DOMAIN} → 127.0.0.1:${HTTP_PORT} ==="
echo ""

# Sanity: portal container
if ! curl -fsS "http://127.0.0.1:${HTTP_PORT}/api/health" >/dev/null 2>&1; then
  echo "ERRO: portal não responde em http://127.0.0.1:${HTTP_PORT}/api/health" >&2
  echo "Suba o Docker primeiro: cd ${INSTALL_DIR} && docker compose up -d" >&2
  exit 1
fi
echo "OK: health local na porta ${HTTP_PORT}"

# Ports 80/443 free enough for Caddy?
for p in 80 443; do
  if ss -ltn 2>/dev/null | awk '{print $4}' | grep -qE "(^|:)${p}$"; then
    OWNER="$(ss -ltnp 2>/dev/null | grep -E ":${p}\\b" | head -n1 || true)"
    echo "Aviso: porta ${p} já está em uso. ${OWNER}"
    echo "Se for outro Caddy/nginx do gateway, adicione um site block para ${DOMAIN} → 127.0.0.1:${HTTP_PORT}."
  fi
done

# Install Caddy (official)
if ! command -v caddy >/dev/null 2>&1; then
  echo "Instalando Caddy..."
  $SUDO apt-get update -y
  $SUDO apt-get install -y debian-keyring debian-archive-keyring apt-transport-https curl
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | $SUDO gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | $SUDO tee /etc/apt/sources.list.d/caddy-stable.list >/dev/null
  $SUDO apt-get update -y
  $SUDO apt-get install -y caddy
fi

CADDY_DIR="/etc/caddy"
CADDY_FILE="${CADDY_DIR}/Caddyfile"
$SUDO mkdir -p "$CADDY_DIR" /var/log/caddy

# Backup existing
if [ -f "$CADDY_FILE" ]; then
  $SUDO cp -a "$CADDY_FILE" "${CADDY_FILE}.bak.$(date +%s)"
fi

# Write site snippet (standalone Caddyfile focused on portal)
$SUDO tee "$CADDY_FILE" >/dev/null <<EOF
{
  email ${EMAIL}
}

${DOMAIN} {
  encode gzip
  request_body {
    max_size 520MB
  }
  reverse_proxy 127.0.0.1:${HTTP_PORT}
  header {
    Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
  }
  log {
    output file /var/log/caddy/portal-access.log
  }
}
EOF

# Firewall
if command -v ufw >/dev/null 2>&1; then
  $SUDO ufw allow 80/tcp || true
  $SUDO ufw allow 443/tcp || true
fi

$SUDO systemctl enable caddy
$SUDO systemctl restart caddy
sleep 2
$SUDO systemctl --no-pager --full status caddy | head -n 20 || true

# TRUST_PROXY in portal .env
if [ -f "${INSTALL_DIR}/.env" ]; then
  if grep -q '^TRUST_PROXY=' "${INSTALL_DIR}/.env"; then
    $SUDO sed -i 's/^TRUST_PROXY=.*/TRUST_PROXY=true/' "${INSTALL_DIR}/.env"
  else
    echo 'TRUST_PROXY=true' | $SUDO tee -a "${INSTALL_DIR}/.env" >/dev/null
  fi
  (cd "$INSTALL_DIR" && $SUDO docker compose up -d) || true
fi

echo ""
echo "Pronto."
echo "  Teste local:  curl -I https://${DOMAIN}/api/health"
echo "  Cloudflare:   SSL/TLS → Full (ou Full Strict)"
echo "  DNS:          portal → IP desta VPS (proxied laranja OK)"
echo ""
echo "Não use a porta 8081 no Cloudflare — ela não é suportada no proxy."
echo "O Caddy escuta 80/443 e encaminha para o Docker na ${HTTP_PORT}."
echo ""
echo "Quando instalar o gateway depois, integre este host no Caddy do gateway"
echo "ou mantenha este Caddy só para ${DOMAIN}."
echo ""
