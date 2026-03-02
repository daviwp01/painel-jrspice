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
        return Inertia::render('Auth/Register');
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
            'phone' => 'nullable|string|max:20',
            'company_name' => ['nullable', 'string', 'max:255', 'not_regex:/[0-9]/', 'not_regex:/@/'],
        ], [
            'company_name.not_regex' => __('The company name cannot contain numbers or email addresses.'),
        ]);

        $requiresActivation = Setting::get('registration_requires_activation', false);

        // Combine Desktop and Mobile pages for the new user's allowed_pages
        $desktopPages = Setting::get('desktop_user_pages', []);
        $mobilePages = Setting::get('mobile_user_pages', []);
        $mergedPages = array_unique(array_merge($desktopPages, $mobilePages));

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'company_name' => $request->company_name,
            'is_active' => !$requiresActivation,
            'allowed_pages' => $mergedPages,
        ]);

        event(new Registered($user));

        if ($user->is_active) {
            Auth::login($user);
            return redirect(route('dashboard', absolute: false));
        }

        return redirect()->route('login')->with('status', __('Registration successful! Your account is now pending administrator approval. You will receive access shortly.'));
    }
}
