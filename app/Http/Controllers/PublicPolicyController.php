<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\View\View;

/**
 * Public copy of the legal texts, needed so the consent checkbox of the
 * registration form can point somewhere readable before the user has an
 * account.
 */
class PublicPolicyController extends Controller
{
    public function privacy(): View
    {
        return view('embed.policy', [
            'title' => 'Política de Privacidade',
            'body' => (string) Setting::get('legal_privacy_policy', ''),
        ]);
    }

    public function terms(): View
    {
        return view('embed.policy', [
            'title' => 'Termos de Uso',
            'body' => (string) Setting::get('legal_terms_of_use', ''),
        ]);
    }
}
