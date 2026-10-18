<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <title>Cadastro — Xvision</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <link rel="stylesheet" href="{{ asset('embed/register-form.min.css') }}?v={{ $assetVersion }}">
</head>
<body>

<div class="jr-register-container">
    <div id="jr-alert-box" class="jr-alert" role="status" aria-live="polite"></div>

    <noscript>
        <div class="jr-alert jr-alert-danger">Ative o JavaScript para concluir o cadastro.</div>
    </noscript>

    <div class="jr-steps-indicator" aria-hidden="true">
        <div class="jr-steps-line-bg"></div>
        <div class="jr-steps-line-active" id="jr-progress-line" style="width: 0%;"></div>

        <div class="jr-step-item">
            <div class="jr-step-circle active" id="jr-circle-1">1</div>
            <span class="jr-step-label active" id="jr-label-1">Acesso</span>
        </div>
        <div class="jr-step-item">
            <div class="jr-step-circle" id="jr-circle-2">2</div>
            <span class="jr-step-label" id="jr-label-2">Empresa</span>
        </div>
        <div class="jr-step-item">
            <div class="jr-step-circle" id="jr-circle-3">3</div>
            <span class="jr-step-label" id="jr-label-3">Perfil</span>
        </div>
    </div>

    <form
        id="jr-register-form"
        data-endpoint="{{ route('api.external.register') }}"
        data-token="{{ $token }}"
        data-recaptcha-site-key="{{ $recaptchaSiteKey ?? '' }}"
        data-recaptcha-type="{{ $recaptchaType ?? 'v2' }}"
        data-min-fill-ms="{{ (int) config('embed.min_fill_seconds') * 1000 }}"
        novalidate
    >
        <input type="text" name="website" id="jr-company-site" class="jr-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <input type="hidden" name="form_token" value="{{ $token }}">

        <div class="jr-step-content active" id="jr-step-1">
            <div class="jr-form-group">
                <label for="name">Nome Completo</label>
                <input type="text" id="name" name="name" maxlength="100" autocomplete="name" placeholder="Ex: João da Silva" required>
                <div class="jr-error-message" id="error-name" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="email">E-mail Corporativo</label>
                <input type="email" id="email" name="email" maxlength="255" autocomplete="email" inputmode="email" placeholder="nome@empresa.com" required>
                <div class="jr-error-message" id="error-email" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="password">Senha</label>
                <input type="password" id="password" name="password" maxlength="64" autocomplete="new-password" placeholder="••••••••" required>
                <div class="jr-error-message" id="error-password" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="password_confirmation">Confirmar Senha</label>
                <input type="password" id="password_confirmation" name="password_confirmation" maxlength="64" autocomplete="new-password" placeholder="••••••••" required>
                <div class="jr-error-message" id="error-password_confirmation" role="alert"></div>
            </div>
        </div>

        <div class="jr-step-content" id="jr-step-2">
            <div class="jr-form-group">
                <label for="phone">Telefone / WhatsApp</label>
                <input type="tel" id="phone" name="phone" maxlength="30" autocomplete="tel" inputmode="tel" placeholder="(11) 99999-9999" required>
                <div class="jr-error-message" id="error-phone" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="company_name">Nome da Empresa</label>
                <input type="text" id="company_name" name="company_name" maxlength="120" autocomplete="organization" placeholder="Sua Empresa" required>
                <div class="jr-error-message" id="error-company_name" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="cargo">Cargo</label>
                <input type="text" id="cargo" name="cargo" maxlength="120" autocomplete="organization-title" placeholder="Ex: Gerente de Compras" required>
                <div class="jr-error-message" id="error-cargo" role="alert"></div>
            </div>
        </div>

        <div class="jr-step-content" id="jr-step-3">
            <div class="jr-form-group">
                <label for="import_experience">Sua empresa já importa insumos alimentícios?</label>
                <select id="import_experience" name="import_experience" required>
                    <option value="" disabled selected>Selecione uma opção...</option>
                    <option value="Sim, importamos diretamente com o fornecedor">Sim, importamos diretamente com o fornecedor</option>
                    <option value="Sim, através de trading ou broker">Sim, através de trading ou broker</option>
                    <option value="Não, compramos no mercado interno">Não, compramos no mercado interno</option>
                    <option value="Estamos avaliando começar">Estamos avaliando começar</option>
                </select>
                <div class="jr-error-message" id="error-import_experience" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="import_volume">Volume aproximado por embarque</label>
                <select id="import_volume" name="import_volume" required>
                    <option value="" disabled selected>Selecione uma opção...</option>
                    <option value="Contêiner cheio, com frequência">Contêiner cheio, com frequência</option>
                    <option value="Contêiner cheio, esporadicamente">Contêiner cheio, esporadicamente</option>
                    <option value="Menos de um contêiner">Menos de um contêiner</option>
                    <option value="Ainda não sei">Ainda não sei</option>
                </select>
                <div class="jr-error-message" id="error-import_volume" role="alert"></div>
            </div>
            <div class="jr-form-group">
                <label for="decision_role">Seu papel na decisão de compra</label>
                <select id="decision_role" name="decision_role" required>
                    <option value="" disabled selected>Selecione uma opção...</option>
                    <option value="Decido a compra">Decido a compra</option>
                    <option value="Participo da decisão">Participo da decisão</option>
                    <option value="Pesquiso e levo para aprovação">Pesquiso e levo para aprovação</option>
                    <option value="Outro">Outro</option>
                </select>
                <div class="jr-error-message" id="error-decision_role" role="alert"></div>
            </div>

            <div class="jr-form-group">
                <div class="jr-terms">
                    <input type="checkbox" id="terms" name="terms" value="1" required>
                    <label for="terms">
                        Li e concordo com a
                        <a href="{{ url('/privacidade') }}" target="_blank" rel="noopener noreferrer">Política de Privacidade</a>
                        e com os
                        <a href="{{ url('/termos') }}" target="_blank" rel="noopener noreferrer">Termos de Uso</a>.
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
        <p class="jr-recaptcha-note">Este site é protegido pelo reCAPTCHA.</p>
        @endif

        <div class="jr-actions">
            <button type="button" class="jr-btn jr-btn-prev" id="jr-btn-prev" style="display: none;">Voltar</button>
            <button type="button" class="jr-btn jr-btn-next" id="jr-btn-next">Continuar</button>
            <button type="submit" class="jr-btn jr-btn-submit" id="jr-btn-submit" style="display: none;">Criar Conta</button>
        </div>
    </form>
</div>

<script src="{{ asset('embed/register-form.min.js') }}?v={{ $assetVersion }}" defer></script>
</body>
</html>
