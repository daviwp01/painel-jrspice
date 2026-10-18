<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\ExternalRegisterRequest;
use App\Models\ExternalRegistrationLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates accounts coming from the public registration form.
 *
 * The endpoint is anonymous, so everything here assumes hostile input: the
 * e-mail is normalised, the response never reveals whether an account already
 * exists (anti-enumeration) and every outcome is written to the audit trail.
 */
final class ExternalRegistrationService
{
    /**
     * @return array{status: int, message: string}
     */
    public function register(ExternalRegisterRequest $request): array
    {
        $email = Str::lower(trim($request->string('email')->toString()));

        if (User::query()->where('email', $email)->exists()) {
            ExternalRegistrationLog::record($email, ExternalRegistrationLog::OUTCOME_DUPLICATE);

            return $this->answer();
        }

        try {
            $user = DB::transaction(fn (): User => $this->createUser($request, $email));
        } catch (QueryException) {
            // Two submissions racing on the same address: the unique index
            // decided for us. Same generic answer, nothing is disclosed.
            ExternalRegistrationLog::record($email, ExternalRegistrationLog::OUTCOME_DUPLICATE);

            return $this->answer();
        }

        event(new Registered($user));

        ExternalRegistrationLog::record($email, ExternalRegistrationLog::OUTCOME_CREATED, null, $user->id);

        return $this->answer();
    }

    private function createUser(ExternalRegisterRequest $request, string $email): User
    {
        $requiresActivation = (bool) Setting::get('registration_requires_activation', false);

        return User::create([
            'name' => Str::squish($request->string('name')->toString()),
            'email' => $email,
            'password' => $request->string('password')->toString(),
            'phone' => Str::squish($request->string('phone')->toString()),
            'company_name' => Str::squish($request->string('company_name')->toString()),
            'cargo' => Str::squish($request->string('cargo')->toString()),
            'import_experience' => $request->string('import_experience')->toString(),
            'import_volume' => $request->string('import_volume')->toString(),
            'decision_role' => $request->string('decision_role')->toString(),
            'is_active' => ! $requiresActivation,
            'allowed_pages' => array_values((array) Setting::get('default_allowed_pages', [])),
            'registered_via_external_form' => true,
            'terms_accepted_at' => now(),
            // Absolute guarantee: a public form can never mint a master.
            'is_master' => false,
        ]);
    }

    /**
     * Identical body for "created" and "already existed", so the endpoint
     * cannot be used to probe which addresses have an account.
     *
     * @return array{status: int, message: string}
     */
    private function answer(): array
    {
        return [
            'status' => 201,
            'message' => 'Cadastro recebido com sucesso! Faça login com o e-mail e a senha informados.',
        ];
    }
}
