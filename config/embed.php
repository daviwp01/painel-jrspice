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

    // Origens de onde o formulário pode ser enviado (host do POST).
    // O form vive em xvision.xpice.pt e é embebido nas landings de xpice.pt,
    // então o Origin/Referer do envio é sempre o domínio do formulário.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'EMBED_ALLOWED_ORIGINS',
            'https://xvision.xpice.pt,https://xpice.pt,https://www.xpice.pt'
        ))
    ))),

    // Landings autorizadas a exibir o form dentro de um <iframe>.
    // CSP frame-ancestors é lido no documento do form (xvision.xpice.pt),
    // mas precisa liberar o host da página hospedeira (xpice.pt/...).
    'frame_ancestors' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'EMBED_FRAME_ANCESTORS',
            'https://xpice.pt,https://www.xpice.pt,https://xvision.xpice.pt,https://*.xpice.pt'
        ))
    ))),

    // Caminhos autorizados por form_source (Referer do documento do form).
    // system → /system · events → /events (em xvision.xpice.pt ou domínio próprio).
    'allowed_paths' => [
        'system' => ['/system', '/external-form-snippet.html'],
        'events' => ['/events'],
    ],

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
