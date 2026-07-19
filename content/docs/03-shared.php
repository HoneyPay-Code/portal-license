<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Instalar (hospedagem compartilhada)',
    'slug' => 'instalar-shared',
    'pages' => [
        [
            'title' => 'O que você precisa ter',
            'slug' => 'requisitos-shared',
            'body' => <<<'MD'
# O que você precisa ter (hospedagem compartilhada)

Antes de começar, confira com a hospedagem (ou no painel dela):

## Checklist técnico (peça ajuda do suporte se não achar)

- PHP **8.3 ou superior**
- Banco **MySQL** (ou MariaDB)
- Extensões PHP comuns: pdo, mbstring, openssl, curl, json, fileinfo
- Possibilidade de apontar o domínio para a pasta `public`
- Permissão de escrever arquivos (para `.env` e pasta `storage`)
- Agendador de tarefas (cron) por URL

## O que você vai usar na prática

1. Painel da hospedagem (hPanel, cPanel…)
2. Gerenciador de arquivos ou FTP
3. phpMyAdmin (para importar o banco)
4. Navegador
5. Sua chave do [portal.honeypay.tech](https://portal.honeypay.tech)

> **Atenção:** Nesta instalação o assistente **não cria as tabelas sozinho**. Você **importa** o arquivo `database.sql` manualmente. Isso é normal.
MD
        ],
        [
            'title' => 'Passo 1 — Baixar e enviar os arquivos',
            'slug' => 'pacote-database-sql',
            'body' => <<<'MD'
# Passo 1 — Baixar e enviar os arquivos

## 1) Baixar o ZIP

1. Entre em [https://portal.honeypay.tech/app/install](https://portal.honeypay.tech/app/install)
2. Clique para baixar o ZIP
3. Espere o download terminar

## 2) Enviar para a hospedagem

No gerenciador de arquivos da hospedagem:

1. Abra a pasta do seu domínio (muitas vezes `public_html` ou uma pasta do subdomínio)
2. Envie o ZIP
3. Use a opção **Extrair / Unzip**
4. Confira se apareceram pastas como `app`, `public`, `vendor`, `storage`

## 3) Apontar o domínio para a pasta `public`

O site precisa “abrir” pela pasta **`public`**, não pela raiz do projeto.

Como fazer (varia por hospedagem):

- Em alguns painéis: “Document root” → escolha `.../public`
- Em outros: colocar o conteúdo de `public` no `public_html` (só faça se souber o que está fazendo; o ideal é apontar o root)

> **Dica:** Se ao abrir o domínio aparecer lista de pastas ou erro estranho, quase sempre o document root **não** está em `public`.

## 4) Achar o arquivo do banco

Dentro do pacote existe:

`public/install/database.sql`

Você vai importar esse arquivo no próximo passo.
MD
        ],
        [
            'title' => 'Passo 2 — Importar o banco (phpMyAdmin)',
            'slug' => 'importar-banco-shared',
            'body' => <<<'MD'
# Passo 2 — Importar o banco (phpMyAdmin)

Pense no banco como uma “caderneta” vazia. O arquivo SQL cria as páginas dessa caderneta.

## 1) Criar o banco (se ainda não existe)

No painel da hospedagem:

1. Abra **MySQL** / **Bancos de dados**
2. Crie um banco novo (anote o nome)
3. Crie um usuário e senha (anote tudo)
4. Dê permissão desse usuário no banco

## 2) Abrir o phpMyAdmin

1. Clique em **phpMyAdmin**
2. No menu da esquerda, selecione o banco que você criou

## 3) Importar

1. Aba **Importar**
2. Escolha o arquivo `database.sql`
3. Confirme / Execute
4. Espere a mensagem de sucesso

Se o arquivo for grande e der erro de tamanho:

- Peça para o suporte aumentar o limite de upload, **ou**
- Importe via linha de comando `mysql` (peça ajuda a alguém técnico)

## Como saber que deu certo?

Na esquerda do phpMyAdmin devem aparecer várias tabelas (`users`, `orders`, `settings`…). Se estiver vazio, a importação não concluiu.

> **Atenção:** Só depois disso abra o `/install`. Sem as tabelas, o assistente vai reclamar do schema.
MD
        ],
        [
            'title' => 'Passo 3 — Assistente /install',
            'slug' => 'wizard-install',
            'body' => <<<'MD'
# Passo 3 — Assistente /install

Agora vamos configurar o sistema pelo navegador.

## 1) Abrir o instalador

No navegador, abra:

`https://SEU-DOMINIO/install`

Troque `SEU-DOMINIO` pelo domínio real (ex.: `pay.minhaloja.com.br`).

## 2) Preencher com calma

O assistente vai pedir, em geral:

1. **Chave de licença** — copie do portal, sem espaços extras
2. **Dados do MySQL** — host (muitas vezes `localhost`), nome do banco, usuário, senha
3. **URL do site** — use com `https://` se já tiver SSL

## 3) O que o assistente faz

- Valida a licença
- Confere se as tabelas do banco existem
- Gera o arquivo de configuração (`.env`)
- Mostra o endereço do **cron** (guarde isso!)

## 4) Criar o primeiro administrador

Depois do install, abra:

`https://SEU-DOMINIO/criar-admin`

Crie e-mail e senha fortes. Guarde em local seguro.

## 5) Entrar na plataforma

Abra:

`https://SEU-DOMINIO/plataforma`

Faça login com o admin que você criou.

> **Importante:** Ainda falta configurar o **cron**. Sem ele, pagamentos podem ficar travados. Vá para a próxima página.
MD
        ],
        [
            'title' => 'Passo 4 — Cron (o despertador)',
            'slug' => 'cron-shared',
            'body' => <<<'MD'
# Passo 4 — Cron (o despertador)

O cron é um “alarme” da hospedagem que chama o Honey Pay **a cada 1 minuto**.

Sem isso, o sistema não consegue:

- Confirmar pagamentos pendentes
- Rodar filas (tarefas em segundo plano)
- Renovar a checagem da licença

## O que você precisa

No final do `/install` aparece um link parecido com:

```text
https://SEU-DOMINIO/cron?token=COLE_AQUI_O_TOKEN
```

Copie esse link inteiro.

## Como cadastrar na hospedagem

Os nomes mudam, mas a ideia é a mesma:

1. Abra **Cron Jobs** / **Tarefas agendadas**
2. Frequência: **a cada minuto** (`* * * * *` se pedir formato crontab)
3. Comando: chamar a URL (muitas hospedagens têm opção “wget/curl URL”)
4. Salve

Exemplo de comando (se a hospedagem pedir):

```bash
curl -fsS "https://SEU-DOMINIO/cron?token=SEU_TOKEN" >/dev/null 2>&1
```

## Como testar

1. Abra a URL do cron no navegador (uma vez)
2. Deve responder algo de sucesso (não erro 404/403)
3. Faça um pagamento teste e veja se sai de “pendente”

> **Atenção:** Se o token estiver errado, o cron não roda. Se a URL usar `http` sem SSL e o site redirecionar, ajuste para `https`.
MD
        ],
        [
            'title' => 'Problemas comuns na hospedagem compartilhada',
            'slug' => 'problemas-shared',
            'body' => <<<'MD'
# Problemas comuns na hospedagem compartilhada

## Página em branco / erro 500

- PHP abaixo de 8.3
- Pasta `storage` sem permissão de escrita
- Document root errado (não está em `public`)

## “Schema inválido” / tabelas faltando

- Você não importou o `database.sql`
- Importou no banco errado
- Importação incompleta

## Licença recusada

- Chave copiada errada
- Domínio digitado diferente do que será usado de verdade
- Tentando ativar segundo domínio na mesma chave

## PIX fica pendente

- Cron não está a cada minuto
- Token do cron errado
- Gateway/webhook ainda não configurados (veja capítulos de gateways)

## Não recebo e-mail

- SMTP ainda não configurado (capítulo Configurações)

> **Dica:** Anote o erro exato (print) antes de pedir suporte. Isso acelera a solução.
MD
        ],
    ],
];
