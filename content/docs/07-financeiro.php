<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Financeiro e taxas',
    'slug' => 'financeiro-taxas',
    'pages' => [
        [
            'title' => 'Como o dinheiro flui',
            'slug' => 'como-dinheiro-flui',
            'body' => <<<'MD'
# Como o dinheiro flui

Em português bem direto:

1. O cliente paga no checkout (ex.: PIX)
2. O **adquirente** (Mercado Pago, CajuPay…) recebe o pagamento
3. O Honey Pay marca o pedido como pago
4. O sistema calcula taxas
5. O valor fica disponível para o vendedor (conforme regras)
6. O vendedor pede **saque** para a chave PIX dele

Você (plataforma) define as regras de taxa e limites.

> **Dica:** Configure gateway **antes** de abrir vendas reais. Sem gateway, não tem pagamento.
MD
        ],
        [
            'title' => 'Adquirentes e métodos',
            'slug' => 'adquirentes-metodos',
            'body' => <<<'MD'
# Adquirentes e métodos

**Adquirente** = empresa que processa o pagamento.

**Método** = PIX, cartão, etc.

## Ordem certa

1. Conecte o gateway (capítulo Gateways)
2. Ative os métodos no Financeiro
3. Defina taxas
4. Faça uma compra teste

> **Nota:** Dá para começar só com PIX. É mais simples para validar a operação.
MD
        ],
        [
            'title' => 'Taxas e limites (explicado)',
            'slug' => 'taxas-limites',
            'body' => <<<'MD'
# Taxas e limites (explicado)

## Taxa percentual

Exemplo: **1,99%** — em uma venda de R$ 100,00, a taxa percentual é cerca de R$ 1,99.

## Taxa fixa

Exemplo: **R$ 0,50** por venda — soma junto com a percentual.

## Limites de saque

- **Mínimo:** abaixo disso o vendedor não consegue sacar
- **Máximo:** proteção operacional (se existir na sua config)

| Campo | Exemplo |
| --- | --- |
| Taxa % | 1,99% |
| Taxa fixa | R$ 0,50 |
| Saque mínimo | R$ 50,00 |

> **Atenção:** Explique as taxas com clareza para seus vendedores. Evita briga depois.
MD
        ],
        [
            'title' => 'Saques e liquidação',
            'slug' => 'saques-liquidacao',
            'body' => <<<'MD'
# Saques e liquidação

O vendedor pede saque no painel dele. Você acompanha na área de saques da plataforma.

## Status comuns (ideia geral)

- **Pendente** — aguardando processamento
- **Pago / concluído** — dinheiro enviado
- **Falhou** — precisa olhar o motivo (chave PIX errada, KYC, saldo, etc.)

## Antes de liberar saque

1. KYC aprovado?
2. Saldo faz sentido com as vendas?
3. Chave PIX confere com o documento?

> **Importante:** Se o adquirente já pagou mas a tela ficou pendente, use as ferramentas de reconciliação / saúde de pagamentos.
MD
        ],
        [
            'title' => 'PixGO (ferramenta operacional)',
            'slug' => 'pixgo',
            'body' => <<<'MD'
# PixGO (ferramenta operacional)

O **PixGO** (quando habilitado) serve para gerar/consultar cobranças PIX em fluxos operacionais.

Não substitui o checkout do produto do vendedor.

Use para testes controlados ou operações manuais alinhadas ao gateway ativo.
MD
        ],
    ],
];
