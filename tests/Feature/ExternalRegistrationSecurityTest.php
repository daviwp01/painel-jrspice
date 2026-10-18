<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ExternalRegistrationLog;
use App\Models\User;
use App\Support\EmbedToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalRegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = 'https://xvision.xpice.pt';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('embed.allowed_origins', [self::ORIGIN]);
        Config::set('embed.min_fill_seconds', 0);
        Config::set('embed.per_minute_by_ip', 50);
        Config::set('embed.per_day_by_ip', 500);
        Config::set('embed.per_minute_by_email', 50);
        Config::set('embed.frame_ancestors', [self::ORIGIN]);
    }

    public function test_valid_submission_creates_a_non_master_account_and_audits_it(): void
    {
        $response = $this->postExternal($this->payload());

        $response->assertCreated()->assertJson(['success' => true]);

        $user = User::query()->where('email', 'maria@empresa.com.br')->sole();

        $this->assertFalse($user->is_master);
        $this->assertTrue($user->registered_via_external_form);
        $this->assertNotNull($user->terms_accepted_at);
        $this->assertTrue($user->is_active);

        $this->assertDatabaseHas('external_registration_logs', [
            'user_id' => $user->id,
            'outcome' => ExternalRegistrationLog::OUTCOME_CREATED,
        ]);
    }

    public function test_submission_without_origin_is_refused(): void
    {
        $this->postJson(route('api.external.register'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, User::count());
    }

    public function test_submission_from_another_origin_is_refused(): void
    {
        $this->withHeader('Origin', 'https://evil.example')
            ->postJson(route('api.external.register'), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, User::count());
    }

    public function test_referer_falls_back_when_origin_is_stripped(): void
    {
        $this->withHeader('Referer', self::ORIGIN.'/landing/curso')
            ->postJson(route('api.external.register'), $this->payload())
            ->assertCreated();

        $this->assertSame(1, User::count());
    }

    public function test_filled_honeypot_is_refused(): void
    {
        $payload = $this->payload();
        $payload['honeypot'] = 'http://spam.example';

        $this->postExternal($payload)->assertForbidden();

        $this->assertSame(0, User::count());
        $this->assertDatabaseHas('external_registration_logs', [
            'outcome' => ExternalRegistrationLog::OUTCOME_REJECTED,
        ]);
    }

    public function test_missing_form_token_is_refused(): void
    {
        $payload = $this->payload();
        unset($payload['form_token']);

        $this->postExternal($payload)->assertForbidden();

        $this->assertSame(0, User::count());
    }

    public function test_forged_form_token_is_refused(): void
    {
        $payload = $this->payload();
        $payload['form_token'] = '|'.time().'|'.str_repeat('a', 64);

        $this->postExternal($payload)->assertForbidden();

        $this->assertSame(0, User::count());
    }

    public function test_expired_form_token_is_refused(): void
    {
        Config::set('embed.token_ttl_seconds', -1);

        $this->postExternal($this->payload())->assertForbidden();

        $this->assertSame(0, User::count());
    }

    public function test_token_younger_than_the_minimum_fill_time_is_refused(): void
    {
        Config::set('embed.min_fill_seconds', 300);

        $this->postExternal($this->payload())->assertForbidden();

        $this->assertSame(0, User::count());
    }

    public function test_a_fresh_token_is_accepted(): void
    {
        $this->postExternal($this->payload())->assertCreated();
    }

    public function test_invalid_payload_is_rejected_with_field_errors(): void
    {
        $payload = $this->payload();
        $payload['email'] = 'nao-e-email';
        $payload['password'] = 'curta';
        $payload['company_name'] = 'Empresa 42';
        $payload['import_volume'] = 'volume inexistente';
        $payload['terms'] = '0';

        $this->postExternal($payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'email', 'password', 'company_name', 'import_volume', 'terms',
            ]);

        $this->assertSame(0, User::count());
    }

    public function test_a_payload_cannot_elevate_privileges(): void
    {
        $payload = $this->payload();
        $payload['is_master'] = true;
        $payload['is_active'] = true;
        $payload['allowed_pages'] = ['admin.data.index'];

        $this->postExternal($payload)->assertCreated();

        $user = User::query()->where('email', 'maria@empresa.com.br')->sole();

        $this->assertFalse($user->is_master);
    }

    public function test_a_repeated_email_answers_exactly_like_a_new_one(): void
    {
        $first = $this->postExternal($this->payload());
        $first->assertCreated();
        $firstMessage = $first->json('message');

        $payload = $this->payload();
        $payload['name'] = 'Outra Pessoa';

        $second = $this->postExternal($payload);
        $second->assertCreated();

        $this->assertSame($firstMessage, $second->json('message'), 'a resposta não pode revelar que a conta existe');
        $this->assertSame(1, User::count());

        $this->assertDatabaseHas('external_registration_logs', [
            'outcome' => ExternalRegistrationLog::OUTCOME_DUPLICATE,
        ]);
    }

    public function test_the_rate_limiter_blocks_a_burst_from_one_ip(): void
    {
        Config::set('embed.per_minute_by_ip', 2);

        $this->postExternal($this->payload())->assertCreated();
        $this->postExternal($this->payload())->assertCreated();
        $this->postExternal($this->payload())->assertStatus(429);
    }

    public function test_the_rate_limiter_blocks_hammering_one_address(): void
    {
        Config::set('embed.per_minute_by_email', 2);
        Config::set('embed.per_minute_by_ip', 50);

        $this->postExternal($this->payload())->assertCreated();

        $payload = $this->payload();
        $payload['name'] = 'Tentativa Dois';

        $this->postExternal($payload)->assertCreated();

        $payload['name'] = 'Tentativa Três';

        $this->postExternal($payload)->assertStatus(429);
    }

    public function test_a_submission_without_a_reCAPTCHA_token_is_refused_when_it_is_enabled(): void
    {
        $this->enableRecaptcha();

        $payload = $this->payload();
        unset($payload['g_recaptcha']);

        $this->postExternal($payload)->assertForbidden();

        $this->assertSame(0, User::count());
        $this->assertDatabaseHas('external_registration_logs', [
            'outcome' => ExternalRegistrationLog::OUTCOME_REJECTED,
            'reason' => 'recaptcha',
        ]);
    }

    public function test_a_submission_with_a_valid_embed_token_is_accepted(): void
    {
        $this->enableRecaptcha();
        $this->fakeRecaptcha(success: true, score: 0.9, action: 'embed');

        $payload = $this->payload();
        $payload['g_recaptcha'] = 'token-do-usuario';

        $this->postExternal($payload)->assertCreated();

        $this->assertSame(1, User::count());
    }

    public function test_a_token_minted_for_another_form_cannot_be_spent_on_the_embed(): void
    {
        $this->enableRecaptcha();
        $this->fakeRecaptcha(success: true, score: 0.9, action: 'login');

        $payload = $this->payload();
        $payload['g_recaptcha'] = 'token-do-login';

        $this->postExternal($payload)->assertForbidden();

        $this->assertSame(0, User::count());
    }

    public function test_a_low_score_is_refused(): void
    {
        $this->enableRecaptcha();
        $this->fakeRecaptcha(success: true, score: 0.1, action: 'embed');

        $payload = $this->payload();
        $payload['g_recaptcha'] = 'token-de-bot';

        $this->postExternal($payload)->assertForbidden();

        $this->assertSame(0, User::count());
    }

    public function test_the_form_page_only_allows_google_when_reCAPTCHA_is_active(): void
    {
        $without = $this->get('/external-form-snippet.html');
        $without->assertOk();
        $this->assertStringNotContainsString('google.com', $without->headers->get('Content-Security-Policy'));

        $this->enableRecaptcha();

        $with = $this->get('/external-form-snippet.html');
        $with->assertOk();

        $csp = $with->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('script-src \'self\' https://www.google.com', $csp);
        $this->assertStringContainsString('connect-src \'self\' https://www.google.com', $csp);
        $this->assertStringContainsString('frame-src \'self\' https://www.google.com', $csp);
        $this->assertStringContainsString('data-recaptcha-site-key="SITE-CHAVE"', $with->getContent());
        $this->assertStringContainsString('protegido pelo reCAPTCHA', $with->getContent());
    }

    public function test_without_keys_the_embed_form_never_calls_google(): void
    {
        Http::fake();

        $this->postExternal($this->payload())->assertCreated();

        Http::assertNothingSent();
    }

    private function enableRecaptcha(): void
    {
        Config::set('recaptcha.enabled', true);
        Config::set('recaptcha.site_key', 'SITE-CHAVE');
        Config::set('recaptcha.secret_key', 'SEGREDO-CHAVE');
        Config::set('recaptcha.min_score', 0.5);
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

        Http::fake(['www.google.com/recaptcha/*' => Http::response($payload)]);
    }

    public function test_the_form_page_ships_with_security_headers_and_a_valid_token(): void
    {
        $response = $this->get('/external-form-snippet.html');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('frame-ancestors '.self::ORIGIN, $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));

        $html = $response->getContent();

        $this->assertStringNotContainsString('<!--', $html, 'a página entregue não pode ter comentários');
        $this->assertStringContainsString('id="jr-company-site"', $html);
        $this->assertStringContainsString('id="terms"', $html);
        $this->assertStringContainsString('register-form.min.js', $html);

        $this->assertSame(1, preg_match('/data-token="([^"]+)"/', $html, $matches));
        $this->assertTrue(EmbedToken::isValid($matches[1]), 'o token embutido na página precisa ser aceito');
    }

    public function test_the_legal_pages_render_without_storing_the_text_as_html(): void
    {
        Config::set('embed.allowed_origins', [self::ORIGIN]);

        $this->get('/privacidade')->assertOk()->assertSee('Texto ainda não publicado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'name' => 'Maria Teste',
            'email' => 'maria@empresa.com.br',
            'password' => 'SenhaForte123',
            'password_confirmation' => 'SenhaForte123',
            'phone' => '(11) 98888-7777',
            'company_name' => 'Alimentos Fortes',
            'cargo' => 'Gerente de Compras',
            'import_experience' => 'Estamos avaliando começar',
            'import_volume' => 'Ainda não sei',
            'decision_role' => 'Decido a compra',
            'terms' => '1',
            'form_token' => EmbedToken::issue(),
            'honeypot' => '',
        ];
    }

    private function postExternal(array $payload)
    {
        return $this->withHeader('Origin', self::ORIGIN)
            ->postJson(route('api.external.register'), $payload);
    }
}
