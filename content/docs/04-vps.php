<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Instalar (VPS / Caddy)',
    'slug' => 'instalar-vps',
    'pages' => [
        [
            'title' => 'O que é VPS (em português simples)',
            'slug' => 'o-que-e-vps',
            'body' => <<<'MD'
# O que é VPS (em português simples)

**VPS** é um computador alugado na nuvem, ligado 24h, com um número de IP.

Você entra nele por um programa de texto chamado **terminal** (ou “SSH”).

## Você precisa

- Um VPS (Ubuntu é o mais comum)
- Senha root ou usuário com `sudo`
- Domínio apontando para o IP do VPS (DNS tipo A)
- Portas **80** e **443** liberadas no firewall
- Sua conta no [portal.honeypay.tech](https://portal.honeypay.tech)

## O instalador oficial faz o quê?

O comando do portal baixa o Honey Pay e sobe com **Docker + Caddy**:

- Caddy cuida do HTTPS (cadeado)
- Os containers rodam o site, banco, redis, etc.

> **Atenção:** Se você nunca abriu um terminal, peça ajuda a alguém técnico **só** para colar o comando e liberar as portas. O resto das telas é no navegador.
MD
        ],
        [
            'title' => 'Passo a passo do comando do portal',
            'slug' => 'install-caddy',
            'body' => <<<'MD'
# Passo a passo do comando do portal

## 1) No portal

1. Entre em [https://portal.honeypay.tech/app/install](https://portal.honeypay.tech/app/install)
2. Copie o comando VPS completo (botão copiar, se houver)

Ele parece com:

```bash
curl -fsSL "https://portal.honeypay.tech/vps-install.sh?token=..." | sudo bash
```

## 2) No servidor

1. Conecte no VPS por SSH
2. Cole o comando
3. Pressione Enter
4. Espere baixar e instalar (pode demorar alguns minutos)

## 3) Liberar internet

No firewall do provedor (e no `ufw`, se usar):

- Porta 80 (HTTP)
- Porta 443 (HTTPS)

Sem isso o site e o certificado não funcionam.

## 4) Abrir o setup

No navegador:

`http://SEU-IP/docker-setup`  
ou  
`https://SEU-DOMINIO/docker-setup` (se o DNS já apontou)

> **Dica:** Se o comando falhar, copie a mensagem de erro completa. Não reinicie o processo no meio sem ler o erro.
MD
        ],
        [
            'title' => 'Tela /docker-setup',
            'slug' => 'docker-setup',
            'body' => <<<'MD'
# Tela /docker-setup

Esta tela “amarra” o servidor ao seu domínio e à sua licença.

## O que preencher

1. **Domínio** — exatamente o que as pessoas vão digitar (ex.: `pay.suaempresa.com.br`)
2. **Chave de licença** — do portal
3. Outros campos que a tela pedir (e-mail, etc.)

## Depois de salvar

1. Espere a confirmação de sucesso
2. Abra `/criar-admin` e crie o administrador
3. Entre em `/plataforma`

## Checklist rápido

- O domínio resolve para o IP do VPS? (pode testar em “what’s my DNS”)
- O site abre com HTTPS?
- Login do admin funciona?

> **Nota:** Não fique reabrindo o setup em produção sem necessidade. Depois de concluído, use o painel normal.
MD
        ],
        [
            'title' => 'Cloudflare (Flexible, Full, Strict)',
            'slug' => 'cloudflare-tls',
            'body' => <<<'MD'
# Cloudflare (Flexible, Full, Strict)

Se o domínio passa pelo **Cloudflare**, o modo SSL precisa combinar com o servidor.

| Modo no Cloudflare | Em português | O que fazer |
| --- | --- | --- |
| **Flexible** | Cloudflare com HTTPS, origem em HTTP | Mais simples; Caddy pode atender na porta 80 |
| **Full** | HTTPS nos dois lados | Servidor precisa de HTTPS (certificado válido ou interno) |
| **Full (Strict)** | Exige certificado confiável na origem | Use certificado Origin da Cloudflare em `.docker/certs/` |

Se estiver em Full sem certificado Origin, às vezes usa-se:

```bash
GETFY_CADDY_TLS_MODE=internal
```

> **Atenção:** Modo errado causa “erro 525/526” ou redirecionamento infinito. Se não tiver certeza, comece em **Full** com certificado correto, ou peça ajuda.

## Sem Cloudflare

O Caddy tenta emitir certificado Let’s Encrypt sozinho — desde que as portas 80/443 estejam abertas e o DNS aponte certo.
MD
        ],
        [
            'title' => 'Atualizar no VPS',
            'slug' => 'atualizar-vps',
            'body' => <<<'MD'
# Atualizar no VPS

## Antes de qualquer update

1. Backup do banco
2. Backup da pasta de arquivos (`storage`)
3. Anote a versão atual

## Como atualizar

1. Veja a release no [portal.honeypay.tech](https://portal.honeypay.tech)
2. Siga o script/instruções de update da versão (quando disponível)
3. Suba os containers de novo
4. Teste login + um pagamento

> **Importante:** Update não é “apagar tudo e instalar de novo”. Isso apagaria dados. Sempre prefira o fluxo oficial de atualização com backup.
MD
        ],
    ],
];
