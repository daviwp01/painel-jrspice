<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Support\EmbedToken;
use App\Models\ExternalRegistrationLog;
use App\Services\RecaptchaVerifier;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validates a submission of the public registration form.
 *
 * `authorize()` is the perimeter: an unknown origin, a missing/expired form
 * token or a filled honeypot never reaches the validation layer.
 */
class ExternalRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->originIsAllowed()) {
            return false;
        }

        if ($this->filled('honeypot')) {
            return false;
        }

        if (! EmbedToken::isValid($this->string('form_token')->toString())) {
            return false;
        }

        return app(RecaptchaVerifier::class)
            ->verify($this->input('g_recaptcha'), 'embed')
            ->passed;
    }

    /**
     * Every refusal leaves a trace: the audit table is how abuse is found
     * later ("this address was rejected 400 times from one IP").
     */
    protected function failedAuthorization(): void
    {
        ExternalRegistrationLog::record(
            (string) $this->input('email', ''),
            ExternalRegistrationLog::OUTCOME_REJECTED,
            $this->rejectionReason(),
        );

        throw new AuthorizationException('Não foi possível processar o cadastro a partir deste endereço.');
    }

    private function rejectionReason(): string
    {
        if (! $this->originIsAllowed()) {
            return 'origin';
        }

        if ($this->filled('honeypot')) {
            return 'honeypot';
        }

        if (! EmbedToken::isValid($this->string('form_token')->toString())) {
            return 'form_token';
        }

        return 'recaptcha';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc,dns', 'max:255', 'not_regex:/\s/'],
            'password' => ['required', 'confirmed', 'max:64', Password::min(8)],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[\d\s()+\-]{8,30}$/'],
            'company_name' => [
                'required', 'string', 'min:2', 'max:120', 'not_regex:/[0-9]/', 'not_regex:/@/',
            ],
            'cargo' => ['required', 'string', 'min:2', 'max:120'],
            'import_experience' => ['required', 'string', Rule::in(User::getImportExperienceOptions())],
            'import_volume' => ['required', 'string', Rule::in(User::getImportVolumeOptions())],
            'decision_role' => ['required', 'string', Rule::in(User::getDecisionRoleOptions())],
            'terms' => ['required', 'accepted'],
            'form_token' => ['required', 'string', 'max:200'],
            'honeypot' => ['nullable', 'prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.not_regex' => 'O nome da empresa não pode conter números ou e-mails.',
            'email.email' => 'Informe um e-mail corporativo válido.',
            'phone.regex' => 'Informe um telefone válido.',
            'terms.accepted' => 'É necessário aceitar a Política de Privacidade.',
        ];
    }

    /**
     * The browser sends `Origin` on every POST (same-origin included). When a
     * client strips it we fall back to `Referer`; when both are gone the
     * request is not coming from the form page and is refused.
     *
     * Configured origins accept wildcards on the host ("https://*.xpice.pt"
     * covers every subdomain, never the apex itself), so one entry can protect
     * a whole family of landing pages.
     */
    private function originIsAllowed(): bool
    {
        $host = $this->originHost();

        if ($host === null) {
            return false;
        }

        foreach ((array) config('embed.allowed_origins', []) as $pattern) {
            if ($this->hostMatches($host, (string) $pattern)) {
                return true;
            }
        }

        // Same-origin is always legitimate: the form runs on our own domain.
        return strtolower($host) === strtolower($this->host());
    }

    private function originHost(): ?string
    {
        foreach (['Origin', 'Referer'] as $header) {
            $value = $this->headers->get($header);

            if (! is_string($value) || $value === '' || $value === 'null') {
                continue;
            }

            $host = parse_url($value, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                return strtolower($host);
            }
        }

        return null;
    }

    private function hostMatches(string $host, string $pattern): bool
    {
        $expected = strtolower((string) (parse_url($pattern, PHP_URL_HOST) ?: trim($pattern)));

        if ($expected === '') {
            return false;
        }

        if ($host === $expected) {
            return true;
        }

        if (str_starts_with($expected, '*.')) {
            $suffix = substr($expected, 1);

            return str_ends_with($host, $suffix) && strlen($host) > strlen($suffix);
        }

        return false;
    }
}
