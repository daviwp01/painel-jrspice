<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RecaptchaProtectionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['password' => 'password']);
    }

    public function test_login_works_without_reCAPTCHA_when_no_keys_are_configured(): void
    {
        $this->post(route('login'), $this->credentials())->assertRedirect();

        $this->assertAuthenticatedAs($this->user);
        Http::assertNothingSent();
    }

    public function test_the_site_key_is_handed_to_the_login_page(): void
    {
        Config::set('recaptcha.site_key', 'SITE-CHAVE-PUBLICA');

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('recaptchaSiteKey', 'SITE-CHAVE-PUBLICA'));
    }

    public function test_login_is_allowed_with_a_high_score(): void
    {
        $this->enableRecaptcha();
        $this->fakeRecaptcha(success: true, score: 0.9, action: 'login');

        $this->post(route('login'), $this->credentials())->assertRedirect();

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_login_is_refused_with_a_low_score(): void
    {
        $this->enableRecaptcha();
        $this->fakeRecaptcha(success: true, score: 0.1, action: 'login');

        Log::spy();

        $this->post(route('login'), $this->credentials())
            ->assertSessionHasErrors('g_recaptcha');

        $this->assertGuest();

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message): bool => $message === 'recaptcha.rejected',
        )->atLeast()->once();
    }

    public function test_a_token_minted_for_register_cannot_be_spent_on_login(): void
    {
        $this->enableRecaptcha();
        $this->fakeRecaptcha(success: true, score: 0.9, action: 'register');

        $this->post(route('login'), $this->credentials())
            ->assertSessionHasErrors('g_recaptcha');

        $this->assertGuest();
    }

    public function test_an_invalid_token_is_refused(): void
    {
        $this->enableRecaptcha();
        $this->fakeRecaptcha(success: false, score: null, action: 'login', errors: ['timeout-or-duplicate']);

        $this->post(route('login'), $this->credentials())
            ->assertSessionHasErrors('g_recaptcha');

        $this->assertGuest();
    }

    public function test_a_missing_token_is_refused_without_calling_google(): void
    {
        $this->enableRecaptcha();
        Http::fake();

        $payload = $this->credentials();
        unset($payload['g_recaptcha']);

        $this->post(route('login'), $payload)
            ->assertSessionHasErrors('g_recaptcha');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_a_google_outage_does_not_lock_legitimate_users_out(): void
    {
        $this->enableRecaptcha();

        Http::fake(fn () => throw new ConnectionException('timeout'));
        Log::spy();

        $this->post(route('login'), $this->credentials())->assertRedirect();

        $this->assertAuthenticatedAs($this->user);

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message): bool => $message === 'recaptcha.unreachable',
        )->atLeast()->once();
    }

    public function test_a_v2_checkbox_token_is_accepted_without_a_score(): void
    {
        $this->enableRecaptcha();
        // Resposta do v2 (caixinha): só `success`, sem `score` nem `action`.
        Http::fake(['www.google.com/recaptcha/*' => Http::response(['success' => true])]);

        $this->post(route('login'), $this->credentials())->assertRedirect();

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_a_v2_checkbox_failure_is_refused(): void
    {
        $this->enableRecaptcha();
        Http::fake(['www.google.com/recaptcha/*' => Http::response([
            'success' => false,
            'error-codes' => ['invalid-input-response'],
        ])]);

        $this->post(route('login'), $this->credentials())
            ->assertSessionHasErrors('g_recaptcha');

        $this->assertGuest();
    }

    public function test_the_configured_type_is_handed_to_the_login_page(): void
    {
        Config::set('recaptcha.site_key', 'SITE-CHAVE');
        Config::set('recaptcha.type', 'v2');

        $this->get(route('login'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('recaptchaType', 'v2')
                ->where('recaptchaSiteKey', 'SITE-CHAVE')
            );
    }

    public function test_registration_is_protected_by_its_own_action(): void
    {
        $this->enableRecaptcha();
        $this->fakeRecaptcha(success: true, score: 0.2, action: 'register');

        $this->post(route('register'), $this->registration())
            ->assertSessionHasErrors('g_recaptcha');

        $this->assertSame(0, User::where('email', 'novo@empresa.com')->count());
    }

    public function test_a_registration_with_a_good_score_is_accepted(): void
    {
        $this->enableRecaptcha();
        $this->fakeRecaptcha(success: true, score: 0.9, action: 'register');

        $this->post(route('register'), $this->registration())->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'novo@empresa.com']);
    }

    public function test_the_token_is_sent_to_google_with_the_secret_and_the_ip(): void
    {
        $this->enableRecaptcha();
        $this->fakeRecaptcha(success: true, score: 0.9, action: 'login');

        $this->post(route('login'), $this->credentials())->assertRedirect();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'google.com/recaptcha/api/siteverify')
                && $request['secret'] === 'SEGREDO-DE-TESTE'
                && $request['response'] === 'token-do-usuario'
                && filled($request['remoteip']);
        });
    }

    private function fakeRecaptcha(bool $success, ?float $score, string $action, array $errors = []): void
    {
        $payload = ['success' => $success];

        if ($score !== null) {
            $payload['score'] = $score;
        }

        if ($action !== '') {
            $payload['action'] = $action;
        }

        if ($errors !== []) {
            $payload['error-codes'] = $errors;
        }

        Http::fake([
            'www.google.com/recaptcha/*' => Http::response($payload),
        ]);
    }

    private function enableRecaptcha(): void
    {
        Config::set('recaptcha.enabled', true);
        Config::set('recaptcha.site_key', 'SITE-CHAVE-PUBLICA');
        Config::set('recaptcha.secret_key', 'SEGREDO-DE-TESTE');
        Config::set('recaptcha.min_score', 0.5);
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(): array
    {
        return [
            'email' => $this->user->email,
            'password' => 'password',
            'g_recaptcha' => 'token-do-usuario',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function registration(): array
    {
        return [
            'name' => 'Pessoa Nova',
            'email' => 'novo@empresa.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'phone' => '(11) 98888-7777',
            'company_name' => 'Alimentos Fortes',
            'cargo' => 'Gerente',
            'import_experience' => 'Estamos avaliando começar',
            'import_volume' => 'Ainda não sei',
            'decision_role' => 'Decido a compra',
            'g_recaptcha' => 'token-do-usuario',
        ];
    }
}
