<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Configurações',
    'slug' => 'configuracoes',
    'pages' => [
        [
            'title' => 'Onde ficam as configurações',
            'slug' => 'onde-configuracoes',
            'body' => <<<'MD'
# Onde ficam as configurações

No painel admin, abra **Configurações** (menu da plataforma).

Lá costumam existir abas/seções como:

- E-mail
- Storage (arquivos)
- Branding (marca / PWA)
- Segurança (Turnstile, etc.)
- Licença / cron
- Outras opções da sua versão

> **Dica:** Salve uma aba por vez e teste. Não mude tudo de uma vez se estiver com pressa.
MD
        ],
        [
            'title' => 'E-mail (SMTP) — para senha e avisos',
            'slug' => 'email-smtp',
            'body' => <<<'MD'
# E-mail (SMTP) — para senha e avisos

Sem e-mail configurado, o sistema **não consegue** enviar:

- Recuperação de senha
- Avisos importantes
- Algumas notificações

## O que você precisa do provedor de e-mail

Peça (ou copie do painel do provedor):

- Servidor (host), ex.: `smtp.seudominio.com`
- Porta (comum: `587` com TLS)
- Usuário (geralmente o e-mail completo)
- Senha
- Remetente (“De:”), ex.: `noreply@seudominio.com`

## Passos

1. Preencha os campos na aba de e-mail
2. Salve
3. Use o botão de **enviar teste**
4. Confira a caixa de entrada (e spam)

> **Atenção:** E-mail gratuito pessoal (tipo Gmail comum) muitas vezes **bloqueia** envio em massa. Prefira SMTP da hospedagem ou serviço transacional.
MD
        ],
        [
            'title' => 'Arquivos e storage',
            'slug' => 'storage-midia',
            'body' => <<<'MD'
# Arquivos e storage

Aqui o sistema guarda:

- Logos
- Documentos de KYC
- Comprovantes
- Outros uploads

## Disco local

É o padrão: arquivos ficam na pasta `storage` do servidor.

Em VPS/Docker, isso precisa estar em **volume persistente** (para não sumir ao reiniciar).

## Storage remoto (se disponível)

Alguns setups permitem S3 / compatível. Use se a equipe técnica já tiver bucket pronto.

> **Nota:** Faça backup dos arquivos junto com o backup do banco.
MD
        ],
        [
            'title' => 'Marca, logo e PWA',
            'slug' => 'branding-pwa',
            'body' => <<<'MD'
# Marca, logo e PWA

Nesta área você deixa o sistema com a cara da sua empresa:

1. Nome da plataforma
2. Logo (versão clara e escura, se pedir)
3. Favicon (ícone da aba do navegador)
4. Ícones para “instalar no celular” (PWA), se disponível

## Dicas práticas

- Use PNG com fundo transparente quando possível
- Evite logo muito larga (corta no menu)
- Teste no celular depois de salvar

> **Dica:** Isso também ajuda nos e-mails parecerem profissionais.
MD
        ],
        [
            'title' => 'Segurança e Turnstile',
            'slug' => 'seguranca-turnstile',
            'body' => <<<'MD'
# Segurança e Turnstile

**Turnstile** é um “não sou robô” da Cloudflare, mais leve que captchas antigos.

Você pode exigir em:

- Login
- Cadastro
- Checkout

## Como configurar

1. Crie as chaves no painel Cloudflare Turnstile
2. Cole a **site key** e a **secret key** nas configurações
3. Ative onde deseja
4. Teste em aba anônima

> **Atenção:** Chave de teste ≠ chave de produção. No ar para clientes, use o par do domínio certo.
MD
        ],
        [
            'title' => 'Cron, domínio e licença',
            'slug' => 'cron-licenca-settings',
            'body' => <<<'MD'
# Cron, domínio e licença

Revise periodicamente:

1. Se o **domínio** da instalação ainda é o mesmo da licença
2. Se o **cron** (hospedagem compartilhada) continua ativo
3. Se a licença aparece como válida

Se a licença cair:

1. Entre no [portal.honeypay.tech](https://portal.honeypay.tech)
2. Confira a chave e o domínio vinculado
3. Corrija DNS / `APP_URL` se o site mudou de endereço

> **Importante:** Mudar de domínio de produção sem revinculação pode bloquear vendas novas.
MD
        ],
    ],
];
