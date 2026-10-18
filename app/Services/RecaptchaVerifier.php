<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\RecaptchaOutcome;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-side verification of Google reCAPTCHA v3 tokens.
 *
 * The token is produced in the browser right before the submit and is single
 * use, so it cannot be replayed. Verification also checks the action name,
 * which stops a token minted for one form from being spent on another.
 */
final class RecaptchaVerifier
{
    private const ENDPOINT = 'https://www.google.com/recaptcha/api/siteverify';

    public function verify(mixed $token, string $expectedAction): RecaptchaOutcome
    {
        if (! $this->enabled()) {
            return RecaptchaOutcome::disabled();
        }

        if (! is_string($token) || trim($token) === '') {
            return RecaptchaOutcome::failed('missing-input-response');
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('recaptcha.timeout', 5))
                ->post(self::ENDPOINT, [
                    'secret' => (string) config('recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => request()->ip(),
                ]);
        } catch (Throwable $exception) {
            Log::warning('recaptcha.unreachable', [
                'action' => $expectedAction,
                'error' => $exception->getMessage(),
            ]);

            return RecaptchaOutcome::unreachable();
        }

        if (! $response->successful()) {
            Log::warning('recaptcha.unreachable', [
                'action' => $expectedAction,
                'status' => $response->status(),
            ]);

            return RecaptchaOutcome::unreachable();
        }

        $payload = $response->json() ?? [];

        if (($payload['success'] ?? false) !== true) {
            return RecaptchaOutcome::failed($this->firstErrorCode($payload));
        }

        // O v3 (score) devolve também `score` e `action`; o v2 (caixinha) só
        // devolve `success`. Detectar pelo payload permite trocar o tipo de
        // chave mudando apenas o .env, sem tocar neste código.
        if (! array_key_exists('score', $payload)) {
            return RecaptchaOutcome::passed(null);
        }

        if ((string) ($payload['action'] ?? '') !== $expectedAction) {
            return RecaptchaOutcome::failed('action-mismatch');
        }

        $score = (float) ($payload['score'] ?? 0);
        $minimum = (float) config('recaptcha.min_score', 0.5);

        if ($score < $minimum) {
            return RecaptchaOutcome::failed('low-score', $score);
        }

        return RecaptchaOutcome::passed($score);
    }

    public function enabled(): bool
    {
        return (bool) config('recaptcha.enabled', true)
            && filled(config('recaptcha.site_key'))
            && filled(config('recaptcha.secret_key'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function firstErrorCode(array $payload): string
    {
        $codes = $payload['error-codes'] ?? [];

        if (is_array($codes) && $codes !== []) {
            return (string) reset($codes);
        }

        return 'not-valid';
    }
}
