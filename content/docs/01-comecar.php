<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Começar',
    'slug' => 'comecar',
    'pages' => [
        [
            'title' => 'Visão geral (leia primeiro)',
            'slug' => 'visao-geral',
            'body' => <<<'MD'
# Visão geral (leia primeiro)

Bem-vindo ao guia do **Honey Pay**.

Este texto foi feito para quem **não é técnico**. Vamos usar palavras do dia a dia e explicar cada termo na primeira vez que aparecer.

## O que você comprou?

Você comprou o direito de usar o **gateway de pagamentos Honey Pay** no **seu próprio domínio** (seu site).

Pense assim:

| Nome simples | O que é | Exemplo |
| --- | --- | --- |
| **Portal** | O site onde você entra com login, vê a licença, baixa o sistema e lê este guia | [https://portal.honeypay.tech](https://portal.honeypay.tech) |
| **Gateway** | O sistema que você instala no seu domínio para receber PIX e gerenciar vendedores | `https://seusite.com.br` |
| **Licença** | A “chave” que prova que você comprou e libera a instalação | Um código longo tipo `XXXX-XXXX-...` |
| **Domínio** | O endereço do seu site na internet | `loja.seusite.com.br` |

> **Dica:** Guarde o endereço do portal: **https://portal.honeypay.tech**. É por lá que você baixa atualizações e vê sua chave.

## O que este guia cobre?

1. Como achar sua chave e baixar o sistema
2. Como instalar em **hospedagem comum** (Hostinger, Locaweb, etc.)
3. Como instalar em **servidor VPS** (DigitalOcean, Contabo, etc.)
4. Como configurar e-mail, aparência, taxas e gateways (Mercado Pago, CajuPay…)
5. Como o admin da plataforma e o vendedor usam o painel no dia a dia

## Ordem recomendada

1. Leia **Palavras que vamos usar** (próxima página)
2. Leia **Qual instalação escolher?**
3. Vá em [Baixar / instalar](https://portal.honeypay.tech/app/install) no portal
4. Siga o capítulo da instalação que você escolheu
5. Só depois configure financeiro e gateways

> **Atenção:** Não pule a parte da **licença + domínio**. Se o domínio estiver errado, o sistema pode bloquear depois.
MD
        ],
        [
            'title' => 'Palavras que vamos usar',
            'slug' => 'glossario',
            'body' => <<<'MD'
# Palavras que vamos usar

Aqui está um dicionário simples. Volte aqui sempre que esquecer um termo.

| Palavra | Significado em português simples |
| --- | --- |
| **Hospedagem** | Empresa que “aluga” espaço para o seu site ficar online |
| **Hospedagem compartilhada** | Plano comum (mais barato). Vários sites no mesmo servidor. Exemplos: Hostinger, HostGator |
| **VPS** | Um servidor só seu (ou quase). Mais controle, mas pede um pouco mais de cuidado |
| **Domínio** | O nome do site (ex.: `minhaloja.com.br`) |
| **DNS** | Configuração que aponta o domínio para o servidor certo |
| **FTP / Gerenciador de arquivos** | Forma de enviar pastas do computador para a hospedagem |
| **Banco de dados** | Onde o sistema guarda pedidos, usuários, etc. (muitas vezes MySQL) |
| **phpMyAdmin** | Telinha da hospedagem para importar o banco de dados |
| **ZIP** | Arquivo compactado (igual um “pacote”) que você baixa e descompacta |
| **Cron** | Um “despertador” que a hospedagem liga a cada minuto para o sistema trabalhar sozinho |
| **Webhook** | Aviso automático que o Mercado Pago / CajuPay manda para o seu site quando alguém paga |
| **SMTP** | Configuração do e-mail de envio (recuperar senha, avisos) |
| **KYC** | Envio de documentos do vendedor para liberar saque |
| **PIX** | Forma de pagamento instantâneo no Brasil |
| **Saque** | Quando o vendedor pede para receber o dinheiro na conta |
| **Admin / Plataforma** | Quem manda no sistema inteiro (`/plataforma`) |
| **Vendedor** | Quem cadastra produtos e vende |
| **HTTPS / cadeado** | Conexão segura do site (obrigatório em produção) |

> **Dica:** Se aparecer uma palavra estranha em outra página, busque neste glossário ou use a busca do menu lateral.
MD
        ],
        [
            'title' => 'Quem faz o quê?',
            'slug' => 'papeis',
            'body' => <<<'MD'
# Quem faz o quê?

No Honey Pay existem **dois tipos principais de login** depois que o sistema está instalado no **seu domínio**.

## 1) Admin da plataforma

É você (ou a pessoa que gerencia a operação).

- Entra em: `https://SEU-DOMINIO/plataforma`
- Configura e-mail, logo, taxas, gateways
- Aprova documentos (KYC), vê saques e transações
- Resolve problemas do dia a dia

## 2) Vendedor (infoprodutor)

É quem vende produtos dentro da sua plataforma.

- Entra no painel do vendedor (depois do cadastro)
- Cria produtos, vê vendas, pede saque PIX
- Pode ter afiliados

## E o portal?

O **portal** ([portal.honeypay.tech](https://portal.honeypay.tech)) é separado:

- É onde **você** (comprador da licença) faz login
- Lá ficam: chave, downloads, este guia e releases

> **Nota:** Login do portal **não** é o mesmo login do gateway. São contas diferentes em sites diferentes.
MD
        ],
        [
            'title' => 'Qual instalação escolher?',
            'slug' => 'qual-instalacao-escolher',
            'body' => <<<'MD'
# Qual instalação escolher?

Escolha **uma** opção. Não precisa das duas.

## Opção A — Hospedagem compartilhada (mais comum para iniciantes)

Escolha esta se:

- Você já tem Hostinger, HostGator, Locaweb, KingHost, etc.
- Prefere painel com botões (cPanel / hPanel)
- Não quer mexer com “Docker” ou linha de comando

Você vai:

1. Baixar um arquivo ZIP no portal
2. Enviar para a hospedagem
3. Importar um arquivo `database.sql` no phpMyAdmin
4. Abrir `/install` no navegador e seguir o assistente
5. Configurar o cron (despertador)

Leia o capítulo **Instalar (hospedagem compartilhada)**.

## Opção B — VPS (servidor)

Escolha esta se:

- Você tem um VPS (Ubuntu) com IP e acesso root
- Quer Caddy + Docker nas portas 80 e 443
- Consegue colar um comando no terminal (mesmo sem ser expert)

Você vai:

1. Copiar o comando pronto em [portal.honeypay.tech/app/install](https://portal.honeypay.tech/app/install)
2. Colar no servidor
3. Abrir `/docker-setup` e informar domínio + licença
4. Criar o primeiro admin

Leia o capítulo **Instalar (VPS / Caddy)**.

## Comparação rápida

| Pergunta | Hospedagem compartilhada | VPS |
| --- | --- | --- |
| Mais fácil para leigo? | Em geral **sim** | Médio |
| Precisa de terminal? | Quase não | Sim (um comando) |
| Precisa importar SQL? | **Sim** | Não (o instalador cuida) |
| HTTPS | Pela hospedagem / Cloudflare | Caddy no servidor |

> **Atenção:** Se estiver em dúvida e já paga hospedagem compartilhada com PHP 8.3 e MySQL, comece pela **Opção A**.
MD
        ],
        [
            'title' => 'Checklist antes de abrir vendas',
            'slug' => 'checklist-go-live',
            'body' => <<<'MD'
# Checklist antes de abrir vendas

Imprima mentalmente esta lista. Só marque “pronto” quando testar de verdade.

## Instalação

1. Site abre com **HTTPS** (cadeado no navegador)
2. Licença aceita no domínio correto
3. Cron (hospedagem compartilhada) ou workers (VPS) funcionando
4. Você consegue entrar em `/plataforma`

## Configuração básica

1. E-mail SMTP testado (recebeu o e-mail de teste)
2. Logo e nome da marca ok
3. Gateway de pagamento configurado (ex.: Mercado Pago ou CajuPay)
4. Webhook do gateway apontando para o **seu domínio** (não localhost)
5. Taxas e métodos de pagamento definidos

## Operação

1. Conta admin com senha forte (ideal: 2FA ligado)
2. Fez uma compra teste de valor baixo
3. Pedido ficou **pago** sozinho (não ficou eternamente pendente)
4. Fluxo de KYC e saque entendido

> **Importante:** Se o PIX fica “pendente” para sempre, quase sempre é **cron parado** ou **webhook errado**. Veja o FAQ no final do guia.
MD
        ],
    ],
];
