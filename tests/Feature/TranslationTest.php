<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return iterable<string, array{string}>
     */
    public static function frameworkKeys(): iterable
    {
        yield 'auth.failed' => ['auth.failed'];
        yield 'auth.password' => ['auth.password'];
        yield 'auth.throttle' => ['auth.throttle'];
        yield 'validation.required' => ['validation.required'];
        yield 'validation.email' => ['validation.email'];
        yield 'validation.confirmed' => ['validation.confirmed'];
        yield 'validation.unique' => ['validation.unique'];
        yield 'validation.min.string' => ['validation.min.string'];
        yield 'validation.mimes' => ['validation.mimes'];
        yield 'pagination.previous' => ['pagination.previous'];
        yield 'passwords.user' => ['passwords.user'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('frameworkKeys')]
    public function test_framework_messages_are_translated_instead_of_returning_the_key(string $key): void
    {
        $message = trans($key, [
            'attribute' => 'e-mail',
            'min' => 8,
            'max' => 64,
            'other' => 'senha',
            'seconds' => 30,
            'values' => 'pdf',
        ]);

        $this->assertNotSame($key, $message, "A chave {$key} não está traduzida.");
        $this->assertStringNotContainsString('validation.', $message);
    }

    public function test_field_names_are_humanised_in_portuguese(): void
    {
        // O nome legível do campo é aplicado pelo validador, não pelo trans().
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['company_name' => ''],
            ['company_name' => 'required'],
        );

        $this->assertSame(
            'O campo nome da empresa é obrigatório.',
            $validator->errors()->first('company_name'),
        );
    }

    public function test_a_failed_login_shows_a_readable_message(): void
    {
        $this->post(route('login'), [
            'email' => 'ninguem@empresa.com',
            'password' => 'senha-errada',
        ])->assertSessionHasErrors('email');

        $errors = session('errors')->first('email');

        $this->assertSame('E-mail ou senha incorretos.', $errors);
        $this->assertStringNotContainsString('auth.failed', $errors);
    }

    public function test_a_rate_limited_login_shows_a_readable_message(): void
    {
        config(['auth.throttle.login_by_ip_per_minute' => 1]);

        $payload = ['email' => 'ninguem@empresa.com', 'password' => 'senha-errada'];

        $this->post(route('login'), $payload)->assertSessionHasErrors('email');
        $this->post(route('login'), $payload)->assertStatus(429);

        $this->assertSame(
            'Muitas tentativas de acesso. Tente novamente em 60 segundos.',
            trans('auth.throttle', ['seconds' => 60, 'minutes' => 1]),
        );
    }

    public function test_a_missing_required_field_shows_a_readable_message(): void
    {
        $this->post(route('login'), ['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertSame('O campo e-mail é obrigatório.', session('errors')->first('email'));
    }
}
