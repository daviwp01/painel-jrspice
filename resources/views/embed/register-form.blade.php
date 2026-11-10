<!DOCTYPE html>
<html lang="pt-BR">
<head>
    @php $gtmId = app()->environment('production') ? config('app.gtm_container_id') : null; @endphp
    @if($gtmId)
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','{{ $gtmId }}');</script>
    @endif
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>{{ __('Registration') }} — Xvision</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="{{ asset('embed/register-form.min.css') }}?v={{ $assetVersion }}">
</head>
<body>
@if(($gtmId ?? null))
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtmId }}"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
@endif

<div class="jr-register-container">
    <div id="jr-alert-box" class="jr-alert" role="status" aria-live="polite"></div>

    <noscript>
        <div class="jr-alert jr-alert-danger">{{ __('Enable JavaScript to complete the registration.') }}</div>
    </noscript>

    <div class="jr-steps-indicator" aria-hidden="true">
        <div class="jr-steps-line-bg"></div>
        <div class="jr-steps-line-active" id="jr-progress-line" style="width: 0%;"></div>

        <div class="jr-step-item">
            <div class="jr-step-circle active" id="jr-circle-1">1</div>
            <span class="jr-step-label active" id="jr-label-1">{{ __('Access') }}</span>
        </div>
        <div class="jr-step-item">
            <div class="jr-step-circle" id="jr-circle-2">2</div>
            <span class="jr-step-label" id="jr-label-2">{{ __('Company') }}</span>
        </div>
        <div class="jr-step-item">
            <div class="jr-step-circle" id="jr-circle-3">3</div>
            <span class="jr-step-label" id="jr-label-3">{{ __('Profile') }}</span>
        </div>
    </div>

    <form
        id="jr-register-form"
        data-endpoint="{{ route('api.external.register') }}"
        data-token="{{ $token }}"
        data-recaptcha-site-key="{{ $recaptchaSiteKey ?? '' }}"
        data-recaptcha-type="{{ $recaptchaType ?? 'v2' }}"
        data-min-fill-ms="{{ (int) config('embed.min_fill_seconds') * 1000 }}"
        data-form-source="{{ $formSource ?? 'system' }}"
        novalidate
    >
        <input type="text" name="website" id="jr-company-site" class="jr-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <input type="hidden" name="form_token" value="{{ $token }}">

        <div class="jr-step-content active" id="jr-step-1">
            <div class="jr-form-group">
                <label for="name">{{ __('Full Name') }}</label>
                <input type="text" id="name" name="name" maxlength="100" autocomplete="name" placeholder="{{ __('Ex: John Doe') }}" required>
                <div class="jr-error-message" id="error-name" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="email">{{ __('Corporate Email') }}</label>
                <input type="email" id="email" name="email" maxlength="255" autocomplete="email" inputmode="email" placeholder="{{ __('name@company.com') }}" required>
                <div class="jr-error-message" id="error-email" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="password">{{ __('Password') }}</label>
                <input type="password" id="password" name="password" maxlength="64" autocomplete="new-password" placeholder="••••••••" required>
                <div class="jr-error-message" id="error-password" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="password_confirmation">{{ __('Confirm Password') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" maxlength="64" autocomplete="new-password" placeholder="••••••••" required>
                <div class="jr-error-message" id="error-password_confirmation" role="alert"></div>
            </div>
        </div>

        <div class="jr-step-content" id="jr-step-2">
            <div class="jr-form-group">
                <label for="phone">{{ __('Phone / WhatsApp') }}</label>
                <input type="tel" id="phone" name="phone" maxlength="30" autocomplete="tel" inputmode="tel" placeholder="(11) 99999-9999" required>
                <div class="jr-error-message" id="error-phone" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="company_name">{{ __('Company Name') }}</label>
                <input type="text" id="company_name" name="company_name" maxlength="120" autocomplete="organization" placeholder="{{ __('Your Company') }}" required>
                <div class="jr-error-message" id="error-company_name" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="cargo">{{ __('Job Title') }}</label>
                <input type="text" id="cargo" name="cargo" maxlength="120" autocomplete="organization-title" placeholder="{{ __('e.g. Purchasing Manager') }}" required>
                <div class="jr-error-message" id="error-cargo" role="alert"></div>
            </div>
        </div>

        <div class="jr-step-content" id="jr-step-3">
            <div class="jr-form-group">
                <label for="import_experience">{{ __('Does your company already import food ingredients?') }}</label>
                <select id="import_experience" name="import_experience" required>
                    <option value="" disabled selected>{{ __('Select an option...') }}</option>
                    <option value="Sim, importamos diretamente com o fornecedor">{{ __('Yes, we import directly from the supplier') }}</option>
                    <option value="Sim, através de trading ou broker">{{ __('Yes, through a trading company or broker') }}</option>
                    <option value="Não, compramos no mercado interno">{{ __('No, we buy on the domestic market') }}</option>
                    <option value="Estamos avaliando começar">{{ __('We are considering starting') }}</option>
                </select>
                <div class="jr-error-message" id="error-import_experience" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="import_volume">{{ __('Approximate volume per shipment') }}</label>
                <select id="import_volume" name="import_volume" required>
                    <option value="" disabled selected>{{ __('Select an option...') }}</option>
                    <option value="Contêiner cheio, com frequência">{{ __('Full container, frequently') }}</option>
                    <option value="Contêiner cheio, esporadicamente">{{ __('Full container, sporadically') }}</option>
                    <option value="Menos de um contêiner">{{ __('Less than one container') }}</option>
                    <option value="Ainda não sei">{{ __('Not sure yet') }}</option>
                </select>
                <div class="jr-error-message" id="error-import_volume" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="decision_role">{{ __('Your role in the purchase decision') }}</label>
                <select id="decision_role" name="decision_role" required>
                    <option value="" disabled selected>{{ __('Select an option...') }}</option>
                    <option value="Decido a compra">{{ __('I make the purchase decision') }}</option>
                    <option value="Participo da decisão">{{ __('I take part in the decision') }}</option>
                    <option value="Pesquiso e levo para aprovação">{{ __('I research and take it for approval') }}</option>
                    <option value="Outro">{{ __('Other') }}</option>
                </select>
                <div class="jr-error-message" id="error-decision_role" role="alert"></div>
            </div>

            <div class="jr-form-group">
                <div class="jr-terms">
                    <input type="checkbox" id="terms" name="terms" value="1" required>
                    <label for="terms">
                        {{ __('I have read and agree to the') }}
                        <a href="{{ url('/privacidade') }}" target="_blank" rel="noopener noreferrer">{{ __('Privacy Policy') }}</a>
                        {{ __('and to the') }}
                        <a href="{{ url('/termos') }}" target="_blank" rel="noopener noreferrer">{{ __('Terms of Use') }}</a>.
                    </label>
                </div>
                <div class="jr-error-message" id="error-terms" role="alert"></div>
            </div>
        </div>

        @if(($recaptchaSiteKey ?? null) !== null && ($recaptchaType ?? 'v3') === 'v2')
        <div class="jr-recaptcha">
            <div id="jr-recaptcha"></div>
        </div>
        @endif

        @if(($recaptchaSiteKey ?? null) !== null && ($recaptchaType ?? 'v3') === 'v3')
        <p class="jr-recaptcha-note">{{ __('This site is protected by reCAPTCHA.') }}</p>
        @endif

        <div class="jr-actions">
            <button type="button" class="jr-btn jr-btn-prev" id="jr-btn-prev" style="display: none;">{{ __('Back') }}</button>
            <button type="button" class="jr-btn jr-btn-next" id="jr-btn-next">{{ __('Continue') }}</button>
            <button type="submit" class="jr-btn jr-btn-submit" id="jr-btn-submit" style="display: none;">{{ __('Create account') }}</button>
        </div>
    </form>
</div>

<script src="{{ asset('embed/register-form.min.js') }}?v={{ $assetVersion }}" defer></script>
</body>
</html>
