<?php

declare(strict_types=1);

/** @return array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} */
return [
    'title' => 'Admin da plataforma',
    'slug' => 'admin-plataforma',
    'pages' => [
        [
            'title' => 'Entrar em /plataforma',
            'slug' => 'login-plataforma',
            'body' => <<<'MD'
# Entrar em /plataforma

Depois que o Honey Pay está instalado no **seu domínio**:

1. Abra `https://SEU-DOMINIO/plataforma`
2. Digite o e-mail e a senha do administrador
3. Se pedir verificação em duas etapas (2FA), abra o app autenticador no celular e digite o código
4. Se aparecer um captcha (Turnstile), marque a caixinha

## Esqueceu a senha?

Use a recuperação de senha **somente se o e-mail SMTP já estiver configurado**. Caso contrário, o e-mail não chega.

> **Atenção:** Não compartilhe a conta admin. Cada pessoa da equipe deve ter seu próprio usuário, se possível.
MD
        ],
        [
            'title' => 'Dashboard e perfil',
            'slug' => 'dashboard-perfil',
            'body' => <<<'MD'
# Dashboard e perfil

Ao entrar, você vê o **painel inicial** (dashboard): resumo do que está acontecendo na operação.

## O que fazer no primeiro dia

1. Abra seu **perfil**
2. Troque a senha se ainda for fraca
3. Ative **2FA** (autenticação em dois fatores) — é como um segundo cadeado
4. Confira se o nome/logo da plataforma já estão ok (ou vá em Configurações)

> **Dica:** Ative o 2FA **antes** de colocar dinheiro real e credenciais de gateway.
MD
        ],
    ],
];
