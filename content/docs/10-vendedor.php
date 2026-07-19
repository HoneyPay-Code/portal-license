<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Painel do vendedor',
    'slug' => 'painel-vendedor',
    'pages' => [
        [
            'title' => 'Dashboard do vendedor',
            'slug' => 'dashboard-aurora',
            'body' => <<<'MD'
# Dashboard do vendedor

O painel do vendedor (visual **Aurora**) mostra o resumo do dia:

- Quanto vendeu
- Atalhos para produtos, vendas e financeiro
- Menu lateral para navegar

## Primeiro acesso do vendedor

1. Cadastro / convite
2. Login
3. Completar dados
4. Enviar KYC (se exigido)
5. Criar o primeiro produto

Explique isso no onboarding do seu time de suporte.
MD
        ],
        [
            'title' => 'Vendas',
            'slug' => 'vendas-vendedor',
            'body' => <<<'MD'
# Vendas

Em **Vendas** o vendedor vê os pedidos:

- Pagos
- Pendentes
- Reembolsados / cancelados (conforme status)

Dá para filtrar por período. Isso resolve a maior parte do “cadê minha venda?”.
MD
        ],
        [
            'title' => 'Produtos e link de checkout',
            'slug' => 'produtos-checkout',
            'body' => <<<'MD'
# Produtos e link de checkout

## Criar um produto (visão geral)

1. Nome claro
2. Preço
3. Conteúdo / entrega
4. Publicar
5. Copiar o link de checkout
6. Testar no celular

## Checklist antes de divulgar

1. Link abre certo
2. PIX aparece
3. Depois de pagar, status vira pago
4. Cliente recebe o que foi prometido

> **Dica:** Sempre teste **você mesmo** com valor baixo antes de anunciar.
MD
        ],
        [
            'title' => 'Afiliados',
            'slug' => 'afiliados',
            'body' => <<<'MD'
# Afiliados

Afiliado = pessoa que divulga o produto e ganha comissão.

## Do lado do produto

1. Ative afiliação
2. Defina a comissão
3. Defina regras (ex.: ocultar dados do cliente)

## Do lado do afiliado

Ele usa o painel de afiliados para ver:

- Dashboard
- Vendas
- Relatórios

Explique a regra de comissão por escrito para evitar mal-entendido.
MD
        ],
    ],
];
