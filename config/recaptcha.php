<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Google reCAPTCHA
    |--------------------------------------------------------------------------
    |
    | v3 = invisível, baseado em nota de 0.0 a 1.0. (padrão)
    | v2 = caixinha "Não sou um robô" (visível).
    |
    | O tipo precisa ser o mesmo da chave criada no console do Google. Enquanto
    | as duas chaves não estiverem configuradas a verificação fica desligada e
    | o login/cadastro seguem apenas com os limites de requisição.
    |
    */

    'type' => env('RECAPTCHA_TYPE', 'v3'),

    'site_key' => env('RECAPTCHA_SITE_KEY'),

    'secret_key' => env('RECAPTCHA_SECRET_KEY'),

    // Só vale para o tipo v3: 0.0 = robô, 1.0 = humano. O Google sugere 0.5.
    'min_score' => (float) env('RECAPTCHA_MIN_SCORE', 0.5),

    // Token expira em ~2 minutos no lado do Google.
    'timeout' => (int) env('RECAPTCHA_TIMEOUT', 5),

    // Permite desligar mesmo com as chaves preenchidas (homologação).
    'enabled' => env('RECAPTCHA_ENABLED', true),
];
