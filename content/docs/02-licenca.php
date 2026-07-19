<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Licença e portal',
    'slug' => 'licenca-portal',
    'pages' => [
        [
            'title' => 'Entrar no portal',
            'slug' => 'entrar-portal',
            'body' => <<<'MD'
# Entrar no portal

O portal oficial é:

**[https://portal.honeypay.tech](https://portal.honeypay.tech)**

## Passo a passo

1. Abra o navegador (Chrome, Edge, Safari…)
2. Digite o endereço acima e pressione Enter
3. Faça login com o e-mail e senha da sua compra
4. Se for o primeiro acesso, use o link de convite/recuperação que recebeu por e-mail

Depois de logado, você verá o menu:

- **Início**
- **Licença** — sua chave
- **Instalação** — baixar ZIP ou comando VPS
- **Produtos** — o que você comprou
- **Documentação** — este guia

> **Dica:** Salve o portal nos favoritos. Você vai voltar nele para baixar atualizações.
MD
        ],
        [
            'title' => 'Chave e domínio (regra de 1 site)',
            'slug' => 'chave-dominio',
            'body' => <<<'MD'
# Chave e domínio (regra de 1 site)

## Onde achar a chave

1. Entre em [https://portal.honeypay.tech](https://portal.honeypay.tech)
2. Clique em **Licença**
3. Copie a chave completa (é um código longo)

Guarde em um lugar seguro (gerenciador de senhas ou bloco de notas privado). **Não poste em grupo de WhatsApp.**

## O que a chave permite?

- **1 instalação de produção** = 1 domínio real (ex.: `pay.suaempresa.com.br`)
- Em testes locais, costuma funcionar em `localhost` / `127.0.0.1` / endereços `.local`

## O que NÃO fazer

- Usar a mesma chave em dois sites de produção diferentes
- Trocar o domínio no meio do caminho sem avisar o suporte
- Digitar a chave com espaço a mais no começo/fim

> **Atenção:** O domínio que o gateway usa precisa bater com o domínio vinculado na licença. Se você instalou em `a.com` e depois aponta `b.com`, pode dar “licença inválida”.
MD
        ],
        [
            'title' => 'Baixar o sistema (ZIP e VPS)',
            'slug' => 'downloads-releases',
            'body' => <<<'MD'
# Baixar o sistema (ZIP e VPS)

Tudo fica em:

**[https://portal.honeypay.tech/app/install](https://portal.honeypay.tech/app/install)**

## Se você usa hospedagem compartilhada

1. Abra a página **Instalação**
2. Baixe o **ZIP** da versão atual
3. Guarde o arquivo no computador (ex.: pasta Downloads)
4. Depois siga o capítulo de instalação em hospedagem compartilhada

O ZIP é o “pacote pronto” do Honey Pay.

## Se você usa VPS

1. Abra a mesma página **Instalação**
2. Copie o **comando** que aparece (é um `curl ... | bash`)
3. Cole no terminal do servidor (com permissão de administrador)
4. Espere terminar e siga as telas `/docker-setup`

> **Dica:** O comando do portal já autoriza o download com segurança. Prefira **sempre** o comando gerado na sua conta, não um comando antigo de print de tela.

## Atualizações futuras

Quando sair versão nova, volte ao portal, baixe de novo (ou rode o update do VPS) e siga o capítulo **Atualizações**.
MD
        ],
        [
            'title' => 'Portal × gateway (não misture)',
            'slug' => 'portal-vs-gateway',
            'body' => <<<'MD'
# Portal × gateway (não misture)

Muita gente confunde os dois. Veja a diferença:

| | Portal Honey Pay | Seu gateway (instalação) |
| --- | --- | --- |
| Endereço | [portal.honeypay.tech](https://portal.honeypay.tech) | O domínio **seu** |
| Para quê | Licença, download, este guia | Receber PIX, vender, sacar |
| Quem acessa | Você (comprador) | Você (admin) + vendedores + clientes no checkout |

## Exemplo do mundo real

1. Você entra no **portal** e copia a chave
2. Instala o gateway em `https://checkout.suaempresa.com.br`
3. Seus clientes pagam **no seu domínio**
4. De tempos em tempos o gateway “confirma” a licença com o portal

> **Nota:** Se o portal estiver fora do ar, o download/atualização pode falhar. O gateway instalado continua, mas a validação periódica precisa do portal acessível.
MD
        ],
    ],
];
