<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\RecaptchaVerifier;
use App\Support\EmbedToken;
use Illuminate\Http\Response;

/**
 * Serves the public registration form.
 *
 * The page used to be a static file in public/, which meant nginx delivered it
 * without any security header. Going through Laravel keeps the URL stable for
 * the landing pages while giving us a strict Content-Security-Policy, a
 * per-request form token and a single place to change the embed rules.
 */
class EmbedFormController extends Controller
{
    public function show(): Response
    {
        $recaptcha = app(RecaptchaVerifier::class);

        $response = response()->view('embed.register-form', [
            'token' => EmbedToken::issue(),
            'assetVersion' => (string) config('embed.asset_version'),
            // A chave é pública; nula quando a verificação está desligada, o
            // que também mantém a CSP fechada e o script do Google fora da página.
            'recaptchaSiteKey' => $recaptcha->enabled() ? (string) config('recaptcha.site_key') : null,
            'recaptchaType' => (string) config('recaptcha.type', 'v2'),
        ]);

        $response->headers->add([
            'Content-Security-Policy' => $this->contentSecurityPolicy(),
            'X-Content-Type-Options' => 'nosniff',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), camera=(), microphone=(), payment=(), usb=(), interest-cohort=()',
            // The page carries a signed token: never let a shared cache
            // replay it to somebody else.
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        $ancestors = (array) config('embed.frame_ancestors', []);
        $frameAncestors = $ancestors === [] ? "'none'" : implode(' ', array_map('strval', $ancestors));

        // O reCAPTCHA distribui o carregamento em três domênios do Google:
        // www.google.com (api.js e as chamadas que geram o token),
        // www.gstatic.com (a implementação que o api.js puxa) e
        // www.recaptcha.net (espelho opcional). Só entram na política quando a
        // verificação está ativa, para a página continuar fechada no resto do
        // tempo.
        $recaptcha = app(RecaptchaVerifier::class)->enabled();
        $hosts = $recaptcha ? ' https://www.google.com https://www.gstatic.com https://www.recaptcha.net' : '';

        $directives = array_merge(
            [
                "default-src 'none'",
                "script-src 'self'{$hosts}",
                "style-src 'self'",
            ],
            // O badge do Google nasce com um atributo style dinâmico (largura e
            // posição). Liberar somente o atributo mantém os stylesheets
            // restritos ao nosso domínio; navegadores sem CSP3 ignoram esta
            // diretiva e continuam valendo style-src 'self'.
            $recaptcha ? ["style-src-attr 'unsafe-inline'"] : [],
            [
                "img-src 'self' data:{$hosts}",
                "font-src 'self'",
                "connect-src 'self'{$hosts}",
                "frame-src 'self'{$hosts}",
                "form-action 'self'",
                "base-uri 'none'",
                "object-src 'none'",
                "frame-ancestors {$frameAncestors}",
            ],
        );

        // Only in production: on http:// localhost this directive would rewrite
        // every asset URL to https:// and hit the live server.
        if (app()->isProduction()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
