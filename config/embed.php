<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Formulário público de cadastro (embed)
    |--------------------------------------------------------------------------
    |
    | O snippet é servido pela aplicação (não como arquivo estático) para que
    | ele sempre chegue ao navegador com os cabeçalhos de segurança corretos e
    | com um token assinado de uso único por emissão.
    |
    */

    // Origens de onde o formulário pode ser enviado. Qualquer outra recebe 403.
    // Separe por vírgia. Vazio = nenhuma origem externa é aceita.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('EMBED_ALLOWED_ORIGINS', 'https://xvision.xpice.pt'))
    ))),

    // Sites autorizados a exibir o formulário dentro de um <iframe>.
    // '*' libera qualquer site — use uma lista em produção.
    'frame_ancestors' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('EMBED_FRAME_ANCESTORS', 'https://xvision.xpice.pt,https://*.xpice.pt'))
    ))),

    // Tempo mínimo (em segundos) entre a exibição do formulário e o envio.
    // Captura bots que submetem sem preencher nada.
    'min_fill_seconds' => (int) env('EMBED_MIN_FILL_SECONDS', 3),

    // Validade do token assinado embutido na página.
    'token_ttl_seconds' => (int) env('EMBED_TOKEN_TTL', 86400),

    // Limites de submissão: por IP, por IP/dia e por e-mail.
    'per_minute_by_ip' => (int) env('EMBED_PER_MINUTE_BY_IP', 5),
    'per_day_by_ip' => (int) env('EMBED_PER_DAY_BY_IP', 30),
    'per_minute_by_email' => (int) env('EMBED_PER_MINUTE_BY_EMAIL', 3),

    // Versão dos assets minificados (?v=...), invalida o cache do navegador.
    'asset_version' => env('EMBED_ASSET_VERSION', '1'),
];
