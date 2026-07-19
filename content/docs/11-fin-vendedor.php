<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Financeiro do vendedor',
    'slug' => 'financeiro-vendedor',
    'pages' => [
        [
            'title' => 'Extrato e saldo',
            'slug' => 'extrato-saldo',
            'body' => <<<'MD'
# Extrato e saldo

O vendedor precisa entender três ideias:

1. **Valor da venda** — o que o cliente pagou
2. **Taxas** — o que a plataforma/adquirente descontou
3. **Saldo disponível** — o que pode sacar agora

Se a conta não fecha, olhe taxas + saques já feitos + vendas ainda não liberadas.
MD
        ],
        [
            'title' => 'KYC do vendedor',
            'slug' => 'kyc-vendedor',
            'body' => <<<'MD'
# KYC do vendedor

## Passos para o vendedor

1. Abrir a área de documentos / KYC
2. Enviar fotos nítidas (sem corte, sem reflexo)
3. Esperar análise
4. Se rejeitado, ler o motivo e reenviar

Sem KYC aprovado, o saque normalmente fica bloqueado — isso é proteção, não “bug”.
MD
        ],
        [
            'title' => 'Pedir saque PIX',
            'slug' => 'saque-pix',
            'body' => <<<'MD'
# Pedir saque PIX

1. Cadastre a chave PIX correta (CPF/CNPJ/e-mail/aleatória — a que a plataforma pedir)
2. Confira se o nome bate com o documento
3. Digite o valor (respeitando mínimo)
4. Confirme o pedido
5. Acompanhe o status
6. Baixe o comprovante quando existir

> **Atenção:** Chave PIX de outra pessoa / CPF diferente = falha ou dor de cabeça. Oriente o vendedor a conferir duas vezes.
MD
        ],
    ],
];
