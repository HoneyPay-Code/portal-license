# Honey Pay — License Portal

Portal de licenças (cliente + admin + webhooks + releases do gateway).

## Instalação na VPS (Docker)

Na **mesma VPS** do gateway: o portal usa a porta **8081** (o gateway fica em **80/443**).

```bash
bash -c "$(curl -fsSL https://raw.githubusercontent.com/HoneyPay-Code/portal-license/main/install.sh)"
```

Variáveis opcionais:

| Env | Padrão | Descrição |
|-----|--------|-----------|
| `LICENSE_API_HTTP_PORT` | `8081` | Porta HTTP no host |
| `HONEYPAY_PORTAL_DIR` | `/opt/honeypay-portal` | Diretório de instalação |
| `APP_URL` | `http://IP:8081` | URL pública (pule o prompt) |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | gerados | Credenciais do admin |

Atualizar:

```bash
cd /opt/honeypay-portal && sudo bash update.sh
```

## Subir local

```bash
cp .env.example .env
docker compose up -d --build
```

- Cliente: http://localhost:8081/login  
- Admin: http://localhost:8081/admin/login  
- Health: http://localhost:8081/api/health  

## Segurança (produção)

- `APP_ENV=production` recusa secrets fracos (`LICENSE_SIGNING_KEY`, `WEBHOOK_SECRET`, `ADMIN_PASSWORD`).
- O `install.sh` gera secrets fortes automaticamente.
- Logout só via `POST /logout` (CSRF).

## Releases (ZIP do gateway)

1. Admin → **Releases**: envie o `.zip` do gateway e marque como atual.
2. Cliente → **Instalação**: baixe o ZIP ou use:

```bash
curl -fsSL https://SEU-PORTAL:8081/vps-install.sh | sudo bash
```

## Webhook

`POST /webhooks/checkout` com `Authorization: Bearer <WEBHOOK_SECRET>`.
