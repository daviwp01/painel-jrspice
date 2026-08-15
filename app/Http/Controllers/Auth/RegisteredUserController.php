<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
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

        event(new Registered($user));

        if ($user->is_active) {
            Auth::login($user);
            return redirect(route('dashboard', absolute: false));
        }

        return redirect()->route('login')->with('status', __('Registration successful! Your account is now pending administrator approval. You will receive access shortly.'));
    }
}
