<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\RecaptchaVerifier;
use App\Support\ThrottleKeys;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'recaptchaSiteKey' => (string) config('recaptcha.site_key', ''),
            'recaptchaType' => (string) config('recaptcha.type', 'v2'),
            'importExperienceOptions' => User::getImportExperienceOptions(),
            'importVolumeOptions' => User::getImportVolumeOptions(),
            'decisionRoleOptions' => User::getDecisionRoleOptions(),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureEmailIsNotRateLimited($request);

        $this->ensureRecaptchaPasses($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'phone' => 'required|string|max:30',
            'company_name' => ['required', 'string', 'max:255', 'not_regex:/[0-9]/', 'not_regex:/@/'],
            'cargo' => ['required', 'string', 'max:255'],
            'import_experience' => ['required', 'string', \Illuminate\Validation\Rule::in(User::getImportExperienceOptions())],
            'import_volume' => ['required', 'string', \Illuminate\Validation\Rule::in(User::getImportVolumeOptions())],
            'decision_role' => ['required', 'string', \Illuminate\Validation\Rule::in(User::getDecisionRoleOptions())],
        ], [
            'company_name.not_regex' => __('The company name cannot contain numbers or email addresses.'),
        ]);

        $requiresActivation = Setting::get('registration_requires_activation', false);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'company_name' => $request->company_name,
            'cargo' => $request->cargo,
            'import_experience' => $request->import_experience,
            'import_volume' => $request->import_volume,
            'decision_role' => $request->decision_role,
            'is_active' => !$requiresActivation,
            'allowed_pages' => array_values(Setting::get('default_allowed_pages', [])),
        ]);

        RateLimiter::clear(ThrottleKeys::registerByEmail($request->string('email')->toString()));

        event(new Registered($user));

        if ($user->is_active) {
            Auth::login($user);
            return redirect(route('dashboard', absolute: false));
        }

        return redirect()->route('login')->with('status', __('Registration successful! Your account is now pending administrator approval. You will receive access shortly.'));
    }

    /**
     * Same assessment as the login form, with its own action name so a token
     * minted on one page cannot be spent on the other.
     *
     * @throws ValidationException
     */
    private function ensureRecaptchaPasses(Request $request): void
    {
        $outcome = app(RecaptchaVerifier::class)->verify($request->input('g_recaptcha'), 'register');

        if ($outcome->passed) {
            return;
        }

        Log::warning('recaptcha.rejected', [
            'action' => 'register',
            'reason' => $outcome->reason,
            'score' => $outcome->score,
            'ip' => $request->ip(),
        ]);

        throw ValidationException::withMessages([
            'g_recaptcha' => 'Não foi possível confirmar que você não é um robô. Atualize a página e tente novamente.',
        ]);
    }

    /**
     * A second, softer budget: the route limiter already caps the IP, this one
     * caps how many times one address can be submitted in a minute. It answers
     * with a validation error instead of a 429 so the Inertia page can show
     * the message inline.
     *
     * @throws ValidationException
     */
    private function ensureEmailIsNotRateLimited(Request $request): void
    {
        $email = $request->string('email')->toString();

        if ($email === '') {
            return;
        }

        $key = ThrottleKeys::registerByEmail($email);
        $max = (int) config('auth.throttle.register_by_email_per_minute', 3);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => (int) ceil($seconds / 60),
                ]),
            ]);
        }

        RateLimiter::hit($key, 60);
    }
}
