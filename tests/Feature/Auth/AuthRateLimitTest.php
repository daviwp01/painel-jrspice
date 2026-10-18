<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\ThrottleKeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['password' => 'password']);
    }

    public function test_login_is_capped_per_ip(): void
    {
        Config::set('auth.throttle.login_by_ip_per_minute', 3);

        $payload = ['email' => 'inexistente@empresa.com', 'password' => 'senha-errada'];

        // Em HTML o erro de credenciais volta como redirect com erros na sessão.
        $this->post(route('login'), $payload)->assertSessionHasErrors('email');
        $this->post(route('login'), $payload)->assertSessionHasErrors('email');
        $this->post(route('login'), $payload)->assertSessionHasErrors('email');

        $this->post(route('login'), $payload)->assertStatus(429);
    }

    public function test_login_locks_one_account_after_five_failed_attempts(): void
    {
        $payload = ['email' => $this->user->email, 'password' => 'senha-errada'];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), $payload)->assertSessionHasErrors('email');
        }

        $this->post(route('login'), $payload)->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_correct_password_logs_in_below_the_lockout_threshold(): void
    {
        Config::set('auth.throttle.login_by_ip_per_minute', 50);

        $payload = ['email' => $this->user->email, 'password' => 'senha-errada'];

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('login'), $payload)->assertSessionHasErrors('email');
        }

        $this->post(route('login'), [
            'email' => $this->user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($this->user);
    }

    public function test_even_the_correct_password_is_refused_after_the_lockout_threshold(): void
    {
        Config::set('auth.throttle.login_by_ip_per_minute', 50);

        $payload = ['email' => $this->user->email, 'password' => 'senha-errada'];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), $payload)->assertSessionHasErrors('email');
        }

        $this->post(route('login'), [
            'email' => $this->user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_register_is_capped_per_ip_per_minute(): void
    {
        Config::set('auth.throttle.register_by_ip_per_minute', 2);
        Config::set('auth.throttle.register_by_email_per_minute', 50);

        $this->post(route('register'), $this->registration('a@empresa.com'))->assertStatus(302);
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post(route('register'), $this->registration('b@empresa.com'))->assertStatus(302);
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->post(route('register'), $this->registration('c@empresa.com'))->assertStatus(429);
    }

    public function test_register_is_capped_per_ip_per_day(): void
    {
        Config::set('auth.throttle.register_by_ip_per_minute', 50);
        Config::set('auth.throttle.register_by_ip_per_day', 2);
        Config::set('auth.throttle.register_by_email_per_minute', 50);

        $this->post(route('register'), $this->registration('a@empresa.com'))->assertStatus(302);
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->post(route('register'), $this->registration('b@empresa.com'))->assertStatus(302);
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->post(route('register'), $this->registration('c@empresa.com'))->assertStatus(429);
    }

    public function test_register_is_capped_per_email_and_answers_inline(): void
    {
        Config::set('auth.throttle.register_by_ip_per_minute', 50);
        Config::set('auth.throttle.register_by_email_per_minute', 2);

        $email = 'duvida@empresa.com';

        // O primeiro cria a conta e zera o orçamento; os dois seguintes caem na
        // unicidade; no quarto o orçamento do endereço está gasto. Responde com
        // redirect + erro no campo, que é como a página Inertia exibe inline.
        $last = null;

        foreach (range(1, 4) as $attempt) {
            $last = $this->post(route('register'), $this->registration($email))->assertStatus(302);

            // A sessão é limpa só entre as tentativas: o middleware `guest`
            // redirecionaria as seguintes sem chegar ao controller.
            if ($attempt < 4) {
                $this->flushSession();
                $this->app['auth']->forgetGuards();
            }
        }

        $last->assertSessionHasErrors('email');
        $this->assertTrue(
            RateLimiter::tooManyAttempts(ThrottleKeys::registerByEmail($email), 2),
            'o orçamento do endereço deve estar gasto após as tentativas',
        );
        $this->assertSame(1, User::where('email', $email)->count(), 'nenhuma conta extra pode nascer');
    }

    public function test_a_successful_registration_clears_that_email_budget(): void
    {
        Config::set('auth.throttle.register_by_ip_per_minute', 50);
        Config::set('auth.throttle.register_by_email_per_minute', 2);

        $email = 'limpo@empresa.com';
        $key = ThrottleKeys::registerByEmail($email);

        // Deixa o orçamento gasto para provar que o cadastro o zera.
        \Illuminate\Support\Facades\RateLimiter::hit($key, 60);
        $this->assertSame(1, \Illuminate\Support\Facades\RateLimiter::attempts($key));

        $this->post(route('register'), $this->registration($email))->assertStatus(302);
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        $this->assertDatabaseHas('users', ['email' => $email]);
        $this->assertSame(0, \Illuminate\Support\Facades\RateLimiter::attempts($key));
    }

    public function test_a_different_address_is_not_blocked_by_another_one_budget(): void
    {
        Config::set('auth.throttle.register_by_ip_per_minute', 50);
        Config::set('auth.throttle.register_by_email_per_minute', 1);

        $this->post(route('register'), $this->registration('um@empresa.com'))->assertStatus(302);
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        // O orçamento é por endereço: outro endereço continua liberado.
        $this->post(route('register'), $this->registration('dois@empresa.com'))->assertStatus(302);
    }

    public function test_password_confirmation_is_capped_per_ip(): void
    {
        Config::set('auth.throttle.confirm_by_ip_per_minute', 2);

        $this->actingAs($this->user);

        $this->post(route('password.confirm'), ['password' => 'errada'])->assertSessionHasErrors('password');
        $this->post(route('password.confirm'), ['password' => 'errada'])->assertSessionHasErrors('password');

        $this->post(route('password.confirm'), ['password' => 'errada'])->assertStatus(429);
    }

    public function test_failed_logins_are_audited_without_the_plain_address(): void
    {
        Config::set('auth.throttle.login_by_ip_per_minute', 50);

        Log::spy();

        $email = 'alvo@empresa.com';

        $this->post(route('login'), ['email' => $email, 'password' => 'errada'])->assertSessionHasErrors('email');

        Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context) use ($email): bool {
            if ($message !== 'auth.login.failed') {
                return false;
            }

            return $context['email_hash'] === ThrottleKeys::digest($email)
                && $context['email_masked'] === 'al***@empresa.com'
                && ! str_contains(json_encode($context), $email);
        })->atLeast()->once();
    }

    public function test_a_lockout_is_audited(): void
    {
        Config::set('auth.throttle.login_by_ip_per_minute', 50);

        Log::spy();

        $payload = ['email' => 'alvo@empresa.com', 'password' => 'errada'];

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login'), $payload)->assertSessionHasErrors('email');
        }

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => $message === 'auth.login.lockout')
            ->atLeast()->once();
    }

    /**
     * @return array<string, mixed>
     */
    private function registration(string $email): array
    {
        return [
            'name' => 'Pessoa Teste',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'phone' => '(11) 98888-7777',
            'company_name' => 'Alimentos Fortes',
            'cargo' => 'Gerente',
            'import_experience' => 'Estamos avaliando começar',
            'import_volume' => 'Ainda não sei',
            'decision_role' => 'Decido a compra',
        ];
    }
}
