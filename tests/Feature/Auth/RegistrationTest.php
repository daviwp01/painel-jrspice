<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'phone' => '11999999999',
            'phone_country_code' => '+55',
            'company_name' => 'Example Corp',
            'cargo' => 'Manager',
            'import_experience' => 'Estamos avaliando começar',
            'import_volume' => 'Ainda não sei',
            'decision_role' => 'Outro',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = \App\Models\User::query()->where('email', 'test@example.com')->sole();
        $this->assertNotNull($user->terms_accepted_at);
    }

    public function test_registration_requires_privacy_and_contact_consent(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'phone' => '11999999999',
            'phone_country_code' => '+55',
            'company_name' => 'Example Corp',
            'cargo' => 'Manager',
            'import_experience' => 'Estamos avaliando começar',
            'import_volume' => 'Ainda não sei',
            'decision_role' => 'Outro',
        ]);

        $response->assertSessionHasErrors('terms');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}
