<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class ExternalRegistrationController extends Controller
{
    /**
     * Handle an incoming external registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): JsonResponse
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
            'registered_via_external_form' => true,
            'is_master' => false, // Garantia extra e absoluta de que nunca será master
        ]);

        event(new Registered($user));

        if ($user->is_active) {
            return response()->json([
                'success' => true,
                'message' => 'Cadastro realizado com sucesso! Você já pode acessar a plataforma.'
            ], 201);
        }

        return response()->json([
            'success' => true,
            'message' => 'Cadastro realizado com sucesso! Sua conta está aguardando aprovação do administrador. Você receberá acesso em breve.'
        ], 201);
    }
}
