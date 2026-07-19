<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Integrações e API',
    'slug' => 'integracoes-api',
    'pages' => [
        [
            'title' => 'UTMify e webhooks de venda',
            'slug' => 'utmify-webhooks',
            'body' => <<<'MD'
# UTMify e webhooks de venda

Serve para avisar outra ferramenta quando uma venda é paga (tráfego, CRM, automações).

## Cuidados

1. URL de destino com HTTPS
2. Evento certo (pago / reembolsado)
3. Teste com uma venda real pequena
4. Não compartilhe secrets em público

> **Dica:** Se a ferramenta externa não receber o evento, primeiro confirme se o pedido ficou **pago** no Honey Pay.
MD
        ],
        [
            'title' => 'API PIX / aplicações',
            'slug' => 'api-pix',
            'body' => <<<'MD'
# API PIX / aplicações

Se a sua instalação libera **aplicações API**, dá para gerar cobranças PIX por software.

## Regras de ouro

1. Guarde o token como senha
2. Nunca suba token em GitHub público
3. Use HTTPS
4. Leia a documentação da API na própria instalação

Se você não é desenvolvedor, pode ignorar este capítulo até precisar.
MD
        ],
    ],
];
