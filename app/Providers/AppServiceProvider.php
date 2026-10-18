<?php

namespace App\Providers;

use App\Support\ThrottleKeys;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->registerExternalRegistrationRateLimiter();
        $this->registerAuthenticationRateLimiters();
        $this->registerAuthenticationAuditTrail();

        // Load dynamic SMTP settings on boot and before each queue job
        $this->loadDynamicSmtpSettings();

        \Illuminate\Support\Facades\Queue::before(function (\Illuminate\Queue\Events\JobProcessing $event) {
            $this->loadDynamicSmtpSettings();
        });

        // Track Last Login + Start Session
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Login::class,
            function ($event) {
                $event->user->update([
                    'last_login_at' => now(),
                ]);

                // Start a tracked session
                app(\App\Services\UserActivityService::class)
                    ->startSession($event->user, request());
            }
        );
        // Track Logout (Mark as offline + close session)
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Auth\Events\Logout::class,
            function ($event) {
                if ($event->user) {
                    // End the tracked session before clearing activity
                    app(\App\Services\UserActivityService::class)
                        ->endSession($event->user);

                    $event->user->update([
                        'last_activity_at' => null,
                    ]);
                }
            }
        );

        // --------------------------------------------------------
        // FORÇAR HTTPS EM PRODUÇÃO
        // --------------------------------------------------------
        if (app()->environment('production') || app()->isProduction() || !app()->environment('local')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }
    }

    /**
     * Hard limits on the authentication routes, applied before any controller
     * work. They complement (never replace) the per-account lockout enforced
     * inside LoginRequest: that one stops guessing one address, these stop one
     * address being guessed from many accounts or many networks of requests.
     */
    protected function registerAuthenticationRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute((int) config('auth.throttle.login_by_ip_per_minute'))
                ->by(ThrottleKeys::loginByIp((string) $request->ip()));
        });

        RateLimiter::for('register', function (Request $request) {
            $ip = (string) $request->ip();

            return [
                Limit::perMinute((int) config('auth.throttle.register_by_ip_per_minute'))
                    ->by(ThrottleKeys::registerByIp($ip)),
                Limit::perDay((int) config('auth.throttle.register_by_ip_per_day'))
                    ->by(ThrottleKeys::registerDayByIp($ip)),
            ];
        });

        RateLimiter::for('auth-confirm', function (Request $request) {
            return Limit::perMinute((int) config('auth.throttle.confirm_by_ip_per_minute'))
                ->by(ThrottleKeys::confirmByIp((string) $request->ip()));
        });
    }

    /**
     * Failed logins and lockouts are the earliest signal of credential
     * stuffing. They are written with a hashed and a masked address so the
     * trail stays useful for operations without storing plain PII.
     */
    protected function registerAuthenticationAuditTrail(): void
    {
        Event::listen(Failed::class, function (Failed $event): void {
            $email = (string) ($event->credentials['email'] ?? '');

            Log::warning('auth.login.failed', [
                'email_hash' => ThrottleKeys::digest($email),
                'email_masked' => self::maskEmail($email),
                'user_id' => $event->user?->getAuthIdentifier(),
                'ip' => request()->ip(),
                'user_agent' => Str::limit((string) request()->userAgent(), 120),
            ]);
        });

        Event::listen(Lockout::class, function (Lockout $event): void {
            $email = (string) $event->request->input('email', '');

            Log::warning('auth.login.lockout', [
                'email_hash' => ThrottleKeys::digest($email),
                'email_masked' => self::maskEmail($email),
                'ip' => $event->request->ip(),
            ]);
        });
    }

    /**
     * Keeps the local part recognizable ("ma***@empresa.com") without logging
     * the full address.
     */
    protected static function maskEmail(string $email): string
    {
        $parts = explode('@', trim($email));

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return '—';
        }

        return Str::limit($parts[0], 2, '').'***@'.$parts[1];
    }

    /**
     * Three independent budgets for the public registration form: per IP per
     * minute, per IP per day and per e-mail. Each dimension blocks a different
     * abuse pattern (spray, slow drip and address hammering).
     */
    protected function registerExternalRegistrationRateLimiter(): void
    {
        RateLimiter::for('external-register', function (Request $request) {
            $ip = (string) $request->ip();
            $email = sha1(Str::lower(trim((string) $request->input('email'))));

            return [
                Limit::perMinute((int) config('embed.per_minute_by_ip', 5))->by("ext-register|min|{$ip}"),
                Limit::perDay((int) config('embed.per_day_by_ip', 30))->by("ext-register|day|{$ip}"),
                Limit::perMinute((int) config('embed.per_minute_by_email', 3))->by("ext-register|email|{$email}"),
            ];
        });
    }

    /**
     * Load SMTP settings from the database.
     */
    protected function loadDynamicSmtpSettings(): void
    {
        try {
            if (config('app.env') !== 'testing' && \Schema::hasTable('settings')) {
                $settings = \App\Models\Setting::whereIn('key', [
                    'mail_host', 'mail_port', 'mail_username', 'mail_password',
                    'mail_encryption', 'mail_from_address', 'mail_from_name'
                ])->get()->pluck('value', 'key');

                if (isset($settings['mail_host']) && $settings['mail_host']) {
                    config([
                        'mail.default' => 'smtp',
                        'mail.mailers.smtp.host' => $settings['mail_host'],
                        'mail.mailers.smtp.port' => $settings['mail_port'] ?? 587,
                        'mail.mailers.smtp.username' => $settings['mail_username'] ?? null,
                        'mail.mailers.smtp.password' => $settings['mail_password'] ?? null,
                        'mail.mailers.smtp.encryption' => $settings['mail_encryption'] ?? 'tls',
                        'mail.from.address' => $settings['mail_from_address'] ?? config('mail.from.address'),
                        'mail.from.name' => $settings['mail_from_name'] ?? config('mail.from.name'),
                    ]);

                    // Reset Mailer instances so they pick up new config
                    $this->app->forgetInstance('mail.manager');
                    $this->app->forgetInstance('mailer');
                }
            }
        } catch (\Exception $e) {
            // Silently fail if DB is not ready
        }
    }
}
