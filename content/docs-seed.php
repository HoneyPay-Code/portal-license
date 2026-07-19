<?php

declare(strict_types=1);

/**
 * Seed oficial da documentação (PT-BR), pensado para iniciantes.
 * Portal oficial: https://portal.honeypay.tech
 *
 * Upsert por slug — páginas custom com outros slugs não são apagadas.
 *
 * @return list<array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>}>
 */
$files = glob(__DIR__.'/docs/*.php') ?: [];
sort($files, SORT_STRING);

$seed = [];
foreach ($files as $file) {
    /** @var array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>} $chapter */
    $chapter = require $file;
    if (! isset($chapter['title'], $chapter['slug'], $chapter['pages']) || ! is_array($chapter['pages'])) {
        throw new RuntimeException('Capítulo inválido: '.$file);
    }
    $seed[] = $chapter;
}

if ($seed === []) {
    throw new RuntimeException('Nenhum capítulo encontrado em content/docs/');
}

return $seed;
