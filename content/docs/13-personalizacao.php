<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Personalização',
    'slug' => 'personalizacao',
    'pages' => [
        [
            'title' => 'Marca e cores',
            'slug' => 'marca-cores',
            'body' => <<<'MD'
# Marca e cores

Na plataforma, a identidade visual (nome, logo, cores quando disponíveis) deixa o painel e os e-mails com a sua cara.

Faça mudanças, salve e **atualize a página** (Ctrl+F5) para ver o resultado.
MD
        ],
        [
            'title' => 'Tema claro e escuro',
            'slug' => 'temas-claro-escuro',
            'body' => <<<'MD'
# Tema claro e escuro

Se o painel permitir alternar tema:

1. Envie logo que funcione nos dois fundos
2. Evite logo preta em fundo preto
3. Peça para um colega testar no celular
MD
        ],
        [
            'title' => 'Suporte no painel do vendedor',
            'slug' => 'suporte-painel',
            'body' => <<<'MD'
# Suporte no painel do vendedor

Configure o canal oficial de ajuda (WhatsApp, central, e-mail).

Assim o vendedor sabe **para quem falar**, em vez de mandar mensagem aleatória para o admin errado.
MD
        ],
    ],
];
