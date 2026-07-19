<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Gateways',
    'slug' => 'gateways',
    'pages' => [
        [
            'title' => 'O que é gateway (bem simples)',
            'slug' => 'gateways-visao',
            'body' => <<<'MD'
# O que é gateway (bem simples)

Aqui “gateway” significa a **conexão** com quem processa o dinheiro (Mercado Pago, CajuPay…).

## Checklist de qualquer gateway

1. Criar conta no adquirente
2. Copiar as chaves / token
3. Colar no Honey Pay (menu Gateways)
4. Configurar **webhook** (aviso automático de pagamento)
5. Testar uma cobrança de verdade (valor baixo)

## O que é webhook?

É o “telefone” que o Mercado Pago/CajuPay liga para o **seu site** dizendo: “fulano pagou”.

A URL do webhook precisa ser do **seu domínio com HTTPS**, por exemplo:

`https://SEU-DOMINIO/...` (o caminho exato aparece na tela do gateway)

> **Atenção:** Webhook para `localhost` **não funciona** na internet. Só em domínio público.
MD
        ],
        [
            'title' => 'Mercado Pago',
            'slug' => 'mercado-pago',
            'body' => <<<'MD'
# Mercado Pago

## Passos amigáveis

1. Entre na sua conta Mercado Pago / Dev
2. Crie ou abra a aplicação
3. Copie o **Access Token** (produção ou teste — não misture)
4. No Honey Pay, abra **Gateways → Mercado Pago**
5. Cole o token e salve
6. Configure a URL de notificação/webhook conforme a tela indicar
7. Faça um pagamento teste

## Teste vs produção

- Token de **teste** = dinheiro de mentira / sandbox
- Token de **produção** = dinheiro real

> **Importante:** No dia do lançamento, confirme que não ficou token de teste por acidente.
MD
        ],
        [
            'title' => 'CajuPay',
            'slug' => 'cajupay',
            'body' => <<<'MD'
# CajuPay

A CajuPay pode cuidar de:

- **Checkout** (receber pagamento)
- **Payout / saque** (enviar dinheiro ao vendedor), se você usar esse fluxo

## O que configurar

1. Credenciais de checkout
2. Credenciais de payout (se for o caso)
3. Webhook de pagamento
4. Webhook de saque

## Se o saque ficou “pendente”

1. Veja no painel CajuPay se o payout foi pago
2. Veja se o webhook de payout chegou
3. Use reconciliação / saúde de pagamentos no Honey Pay

> **Atenção:** Checkout ok + payout mal configurado = vende, mas não saca direito.
MD
        ],
    ],
];
