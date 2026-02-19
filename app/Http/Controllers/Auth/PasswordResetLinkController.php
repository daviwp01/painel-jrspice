<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Check if user exists and is active for "absolute security"
        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => [trans(Password::INVALID_USER)],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('This account is inactive. Please contact an administrator.')],
            ]);
        }

        try {
            // We will send the password reset link to this user. Once we have attempted
            // to send the link, we will examine the response then see the message we
            // need to show to the user. Finally, we'll send out a proper response.
            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status == Password::RESET_LINK_SENT) {
                return back()->with('status', __($status));
            }

            throw ValidationException::withMessages([
                'email' => [trans($status)],
            ]);
        } catch (\Exception $e) {
            // Log the error for the admin but show a friendly message to the user
            \Illuminate\Support\Facades\Log::error('Password reset mail failure: ' . $e->getMessage());

            throw ValidationException::withMessages([
                'email' => [__('We encountered a problem sending the reset link. Please try again later or contact support.')],
            ]);
        }
    }
}
