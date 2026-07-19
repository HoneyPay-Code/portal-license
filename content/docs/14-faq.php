<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Atualizações e FAQ',
    'slug' => 'atualizacoes-faq',
    'pages' => [
        [
            'title' => 'Como atualizar (hospedagem compartilhada e VPS)',
            'slug' => 'atualizar-shared-vps',
            'body' => <<<'MD'
# Como atualizar (hospedagem compartilhada e VPS)

## Sempre antes

1. Backup do banco
2. Backup dos arquivos (principalmente `storage` e `.env`)
3. Avisar a equipe (“vamos pausar 10 minutos”)

## Em hospedagem compartilhada

1. Baixe o ZIP novo em [portal.honeypay.tech/app/install](https://portal.honeypay.tech/app/install)
2. Substitua os arquivos do código
3. **Não apague** `.env` nem `storage` sem querer
4. Se a versão pedir SQL/migration extra, siga o aviso da release
5. Teste login e um pagamento

## VPS

1. Siga o script/update oficial da release
2. Suba a stack de novo
3. Teste

> **Atenção:** Atualizar não é formatar o servidor. Backup primeiro. Sempre.
MD
        ],
        [
            'title' => 'FAQ — perguntas frequentes',
            'slug' => 'faq-operacional',
            'body' => <<<'MD'
# FAQ — perguntas frequentes

## O PIX fica pendente

Verifique nesta ordem:

1. Cron está rodando a cada minuto? (hospedagem compartilhada)
2. Workers/containers ok? (VPS)
3. Webhook do gateway aponta para o domínio certo?
4. Token/credencial do gateway ainda válidos?
5. O pagamento aparece como pago no painel do adquirente?

## Não chega e-mail

1. SMTP configurado e testado?
2. Caiu no spam?
3. Porta SMTP bloqueada pela hospedagem?

## Licença inválida

1. Domínio do site = domínio da licença?
2. Chave correta em [portal.honeypay.tech](https://portal.honeypay.tech)?
3. Servidor consegue acessar o portal?

## Esqueci a senha do admin e não tenho SMTP

Precisa de acesso ao banco/servidor (peça ajuda técnica) ou reconfigurar SMTP primeiro.

## Onde peço suporte da licença / download?

Pelo próprio portal: [https://portal.honeypay.tech](https://portal.honeypay.tech)

> **Dica:** Quanto mais detalhes você mandar (prints, horário, ID do pedido), mais rápido resolve.
MD
        ],
        [
            'title' => 'Preciso de ajuda',
            'slug' => 'preciso-de-ajuda',
            'body' => <<<'MD'
# Preciso de ajuda

## Antes de chamar alguém

1. Anote o endereço do site (URL)
2. Anote a hora do problema
3. Tire print da tela de erro
4. Diga se é instalação em **hospedagem compartilhada** ou **VPS**
5. Diga se o problema é: instalar, pagar, sacar, e-mail ou licença

## Links úteis

- Portal: [https://portal.honeypay.tech](https://portal.honeypay.tech)
- Licença: [https://portal.honeypay.tech/app/license](https://portal.honeypay.tech/app/license)
- Instalação / download: [https://portal.honeypay.tech/app/install](https://portal.honeypay.tech/app/install)
- Este guia: [https://portal.honeypay.tech/app/docs](https://portal.honeypay.tech/app/docs)

Você consegue. Vá página por página — não precisa fazer tudo no mesmo dia.
MD
        ],
    ],
];
