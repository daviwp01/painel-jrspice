<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Registers (or re-registers) the TimeToCargo webhook that pushes arrival
 * updates into POST /api/webhooks/timetocargo.
 *
 * Prints the signing secret once — store it in TIMETOCARGO_WEBHOOK_SECRET.
 */
class RegisterTimeToCargoWebhook extends Command
{
    protected $signature = 'tracking:register-timetocargo-webhook
        {--url= : Public callback URL (defaults to APP_URL/api/webhooks/timetocargo)}
        {--regenerate : Force a new secret on an existing webhook}';

    protected $description = 'Cria/atualiza o webhook TimeToCargo para sincronizar eta_date automaticamente';

    public function handle(): int
    {
        $config = (array) config('tracking.aggregator', []);
        $base = rtrim((string) ($config['url'] ?? ''), '/');
        $apiKey = (string) ($config['api_key'] ?? '');

        if ($base === '' || $apiKey === '') {
            $this->error('TRACKING_AGGREGATOR_URL / TRACKING_AGGREGATOR_KEY não configurados.');

            return self::FAILURE;
        }

        $callback = (string) ($this->option('url') ?: rtrim((string) config('app.url'), '/').'/api/webhooks/timetocargo');
        $events = (array) config('tracking.timetocargo.webhook_events', []);

        $existingId = (string) Setting::get('timetocargo_webhook_id', '');
        $regenerate = (bool) $this->option('regenerate');

        if ($existingId !== '' && ! $regenerate) {
            $listed = $this->getJson($base.'/webhooks', $apiKey);

            foreach ((array) data_get($listed, 'data', []) as $hook) {
                if (is_array($hook) && (string) ($hook['id'] ?? '') === $existingId) {
                    $this->info('Webhook já registrado: '.$existingId);
                    $this->line('Callback: '.(string) ($hook['callback_url'] ?? $callback));
                    $this->line('Se perdeu o secret: tracking:register-timetocargo-webhook --regenerate');

                    return self::SUCCESS;
                }
            }
        }

        if ($existingId !== '' && $regenerate) {
            $this->patchSecret($base, $apiKey, $existingId);

            return self::SUCCESS;
        }

        $response = Http::withHeaders(['Accept' => 'application/json'])
            ->timeout(30)
            ->post($base.'/webhooks?api_key='.urlencode($apiKey), [
                'callback_url' => $callback,
                'event_types' => array_values($events),
            ]);

        if ($response->failed()) {
            $this->error('Falha ao criar webhook: HTTP '.$response->status().' — '.$response->body());

            return self::FAILURE;
        }

        $id = (string) data_get($response->json(), 'data.id', '');
        $secret = (string) data_get($response->json(), 'data.secret', '');

        if ($id === '') {
            $this->error('Resposta sem data.id: '.$response->body());

            return self::FAILURE;
        }

        Setting::set('timetocargo_webhook_id', $id);

        $this->info('Webhook criado: '.$id);
        $this->line('Callback: '.$callback);
        $this->line('Eventos: '.implode(', ', $events));

        if ($secret !== '') {
            Setting::set('timetocargo_webhook_secret', $secret);
            $this->newLine();
            $this->warn('SECRET (guarde agora — não é exibido de novo):');
            $this->line($secret);
            $this->newLine();
            $this->line('Opcional: adicione ao .env → TIMETOCARGO_WEBHOOK_SECRET='.$secret);
            $this->line('Também fica em settings.timetocargo_webhook_secret (usado se o .env estiver vazio).');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(string $url, string $apiKey): array
    {
        $response = Http::withHeaders(['Accept' => 'application/json'])
            ->timeout(30)
            ->get($url.'?api_key='.urlencode($apiKey));

        return $response->successful() ? (array) $response->json() : [];
    }

    private function patchSecret(string $base, string $apiKey, string $webhookId): int
    {
        $response = Http::withHeaders(['Accept' => 'application/json', 'Content-Type' => 'application/json'])
            ->timeout(30)
            ->patch($base.'/webhooks/'.$webhookId.'?api_key='.urlencode($apiKey), [
                'regenerate_secret' => true,
            ]);

        if ($response->failed()) {
            $this->error('Falha ao regenerar secret: HTTP '.$response->status().' — '.$response->body());

            return self::FAILURE;
        }

        $secret = (string) data_get($response->json(), 'data.secret', '');

        if ($secret === '') {
            $this->error('Resposta sem data.secret.');

            return self::FAILURE;
        }

        Setting::set('timetocargo_webhook_secret', $secret);
        $this->info('Secret regenerado (guarde agora):');
        $this->line($secret);

        return self::SUCCESS;
    }
}
