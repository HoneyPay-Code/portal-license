<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Operação diária',
    'slug' => 'operacao-diaria',
    'pages' => [
        [
            'title' => 'Usuários e documentos (KYC)',
            'slug' => 'usuarios-kyc',
            'body' => <<<'MD'
# Usuários e documentos (KYC)

## Usuários

Na área de usuários você vê quem se cadastrou (vendedores) e o status da conta.

## KYC — o que é?

É a pasta de documentos do vendedor (RG/CNH, selfie, dados…). Serve para reduzir fraude e liberar saque.

## Fluxo sugerido

1. Vendedor envia documentos
2. Você revisa com calma
3. Aprova ou pede reenvio
4. Só então o saque fica liberado (conforme sua regra)

> **Dica:** Crie um checklist interno fixo (quais docs aceita). Isso evita decisões diferentes a cada dia.
MD
        ],
        [
            'title' => 'Transações e saques',
            'slug' => 'transacoes-saques',
            'body' => <<<'MD'
# Transações e saques

## Quando o cliente diz “paguei e não liberou”

1. Abra **Transações**
2. Busque pelo e-mail, ID ou valor/horário
3. Veja o status no Honey Pay
4. Compare com o status no Mercado Pago/CajuPay
5. Se o adquirente mostra pago e o Honey Pay não, olhe webhook/cron/reconciliação

## Saques

Acompanhe pendentes todos os dias no começo da operação.

> **Nota:** Antes de reembolsar manualmente, confirme o ID externo no adquirente para não pagar duas vezes.
MD
        ],
        [
            'title' => 'Disputas MED',
            'slug' => 'disputas-med',
            'body' => <<<'MD'
# Disputas MED

**MED** é um tipo de contestação/disputa ligada a pagamentos (quando disponível na sua operação).

## O que fazer

1. Abra a disputa no painel
2. Reúna provas (comprovante de entrega/acesso, conversas, dados do pedido)
3. Envie a defesa no prazo
4. Acompanhe o resultado e o impacto no saldo

> **Atenção:** Prazo curto. SMTP funcionando ajuda a não perder aviso.
MD
        ],
        [
            'title' => 'Saúde de pagamentos',
            'slug' => 'saude-pagamentos',
            'body' => <<<'MD'
# Saúde de pagamentos

Esta tela é o “painel do médico” da operação:

- Filas atrasadas?
- Webhooks chegando?
- Muitos PIX pendentes?
- Credencial com cara de inválida?

## Rotina sugerida

- Nos primeiros 30 dias: olhe **todo dia**
- Depois: pelo menos algumas vezes por semana

> **Dica:** Pico de pendentes + nada de webhook = cron/worker ou URL de webhook quebrada.
MD
        ],
    ],
];
