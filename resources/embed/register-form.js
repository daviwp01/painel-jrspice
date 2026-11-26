/*
 * Registro público (embed) — fonte legível.
 * Build: node scripts/build-embed.mjs → public/embed/register-form.min.js
 *
 * O arquivo pronto não contém comentários nem identificadores longos.
 * Toda a defesa real (origem, token, honeypot, limite de requisições,
 * enumeração de contas) vive no back-end; aqui só espelhamos as regras para
 * dar retorno imediato ao usuário.
 */
(function () {
    'use strict';

    var form = document.getElementById('jr-register-form');
    if (!form) return;

    var ENDPOINT = form.getAttribute('data-endpoint');
    var TOKEN = form.getAttribute('data-token');
    var FORM_SOURCE = form.getAttribute('data-form-source') || 'system';

    var UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'gclid', 'fbclid', 'msclkid'];

    function parseQuery(query) {
        var out = {};
        if (!query) return out;
        var pairs = query.replace(/^\?/, '').split('&');
        for (var i = 0; i < pairs.length; i++) {
            if (!pairs[i]) continue;
            var idx = pairs[i].indexOf('=');
            var key = idx === -1 ? pairs[i] : pairs[i].slice(0, idx);
            var val = idx === -1 ? '' : pairs[i].slice(idx + 1);
            try { key = decodeURIComponent(key.replace(/\+/g, ' ')); } catch (e) { continue; }
            try { val = decodeURIComponent(val.replace(/\+/g, ' ')); } catch (e) { val = ''; }
            out[key] = val;
        }
        return out;
    }

    function captureUtm() {
        var utm = {};
        var i;
        var fromLocation = parseQuery(window.location.search);
        for (i = 0; i < UTM_KEYS.length; i++) {
            if (fromLocation[UTM_KEYS[i]]) utm[UTM_KEYS[i]] = fromLocation[UTM_KEYS[i]];
        }
        // Fallback: a landing page em iframe pode não repassar a query.
        if (!utm.utm_source && document.referrer) {
            try {
                var fromRef = parseQuery(new URL(document.referrer).search);
                for (i = 0; i < UTM_KEYS.length; i++) {
                    if (fromRef[UTM_KEYS[i]]) utm[UTM_KEYS[i]] = fromRef[UTM_KEYS[i]];
                }
            } catch (e) { /* referrer inválido */ }
        }
        return utm;
    }

    var UTM = captureUtm();

    function parentTargetOrigin() {
        try {
            if (document.referrer) {
                return new URL(document.referrer).origin;
            }
        } catch (e) { /* ignore */ }
        return '*';
    }

    function notifyParentResize() {
        if (!window.parent || window.parent === window) return;
        var height = Math.max(
            document.documentElement.scrollHeight,
            document.body ? document.body.scrollHeight : 0
        );
        try {
            window.parent.postMessage({ type: 'xpice:resize', height: height }, parentTargetOrigin());
        } catch (e) { /* ignore */ }
    }

    var RECAPTCHA_SITE_KEY = form.getAttribute('data-recaptcha-site-key') || '';
    var RECAPTCHA_TYPE = form.getAttribute('data-recaptcha-type') || 'v2';
    var recaptchaWidgetId = null;
    var MIN_FILL_MS = Number(form.getAttribute('data-min-fill-ms') || 3000);
    var loadedAt = Date.now();

    var recaptchaScript = null;

    function loadRecaptcha() {
        if (!RECAPTCHA_SITE_KEY) return Promise.resolve(null);
        if (window.grecaptcha) return Promise.resolve(window.grecaptcha);
        if (recaptchaScript) return recaptchaScript;

        var source = RECAPTCHA_TYPE === 'v3'
            ? 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(RECAPTCHA_SITE_KEY)
            : 'https://www.google.com/recaptcha/api.js';

        recaptchaScript = new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = source;
            script.async = true;
            script.defer = true;
            script.onload = function () { resolve(window.grecaptcha); };
            script.onerror = function () {
                recaptchaScript = null;
                reject(new Error('recaptcha'));
            };
            document.head.appendChild(script);
        });

        return recaptchaScript;
    }

    function renderRecaptcha() {
        if (!RECAPTCHA_SITE_KEY || RECAPTCHA_TYPE !== 'v2') return Promise.resolve(null);

        var container = document.getElementById('jr-recaptcha');
        if (!container || recaptchaWidgetId !== null) return Promise.resolve(recaptchaWidgetId);

        return loadRecaptcha().then(function (grecaptcha) {
            return new Promise(function (resolve) { grecaptcha.ready(resolve); });
        }).then(function () {
            recaptchaWidgetId = window.grecaptcha.render(container, { sitekey: RECAPTCHA_SITE_KEY });
            return recaptchaWidgetId;
        });
    }

    function recaptchaToken() {
        if (!RECAPTCHA_SITE_KEY) return Promise.resolve('');

        return loadRecaptcha().then(function (grecaptcha) {
            return new Promise(function (resolve) { grecaptcha.ready(resolve); });
        }).then(function () {
            if (RECAPTCHA_TYPE !== 'v3') {
                return recaptchaWidgetId === null ? '' : (window.grecaptcha.getResponse(recaptchaWidgetId) || '');
            }
            return window.grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'embed' });
        });
    }

    function resetRecaptcha() {
        if (window.grecaptcha && recaptchaWidgetId !== null) {
            window.grecaptcha.reset(recaptchaWidgetId);
        }
    }

    var TOTAL_STEPS = 3;
    var currentStep = 1;

    var btnPrev = document.getElementById('jr-btn-prev');
    var btnNext = document.getElementById('jr-btn-next');
    var btnSubmit = document.getElementById('jr-btn-submit');
    var progressLine = document.getElementById('jr-progress-line');
    var alertBox = document.getElementById('jr-alert-box');

    // Whitelist: um campo vindo do servidor só escreve no DOM se existir aqui.
    var FIELDS = {
        name: { step: 1, message: 'Nome é obrigatório' },
        email: { step: 1, message: 'E-mail é obrigatório' },
        password: { step: 1, message: 'Senha é obrigatória' },
        password_confirmation: { step: 1, message: 'As senhas não conferem' },
        phone: { step: 2, message: 'Telefone é obrigatório' },
        phone_country_code: { step: 2, message: 'Selecione o código do país' },
        company_name: { step: 2, message: 'Nome da empresa é obrigatório' },
        cargo: { step: 2, message: 'Cargo é obrigatório' },
        import_experience: { step: 3, message: 'Selecione uma opção' },
        import_volume: { step: 3, message: 'Selecione uma opção' },
        decision_role: { step: 3, message: 'Selecione uma opção' },
        terms: { step: 3, message: 'Você precisa aceitar a Política de Privacidade e autorizar o contato por e-mail e WhatsApp.' }
    };

    var LIMITS = {
        name: 100,
        email: 255,
        password: 64,
        phone: 30,
        company_name: 120,
        cargo: 120
    };

    var PHONE_PATTERN = /^[\d\s()+\-]{8,30}$/;
    var COUNTRY_CODE_PATTERN = /^\+\d{1,4}$/;
    var phoneCountrySelect = document.getElementById('phone_country_code');
    var phoneInput = document.getElementById('phone');
    var successPanel = document.getElementById('jr-success-panel');
    var successMessage = document.getElementById('jr-success-message');

    function phonePlaceholder(code) {
        if (code === '+55') return '(11) 99999-9999';
        if (code === '+1') return '(555) 555-5555';
        if (code === '+351') return '912 345 678';
        return '(11) 99999-9999';
    }

    function phoneMaxLength(code) {
        if (code === '+55') return 15;
        if (code === '+1') return 14;
        if (code === '+351') return 11;
        return 15;
    }

    function formatNationalPhone(raw, code) {
        var digits = String(raw || '').replace(/\D/g, '');

        if (code === '+55') {
            digits = digits.substring(0, 11);
            if (digits.length > 6) {
                return '(' + digits.substring(0, 2) + ') ' + digits.substring(2, 7) + '-' + digits.substring(7);
            }
            if (digits.length > 2) {
                return '(' + digits.substring(0, 2) + ') ' + digits.substring(2);
            }
            if (digits.length > 0) {
                return '(' + digits;
            }
            return '';
        }

        if (code === '+1') {
            digits = digits.substring(0, 10);
            if (digits.length > 6) {
                return '(' + digits.substring(0, 3) + ') ' + digits.substring(3, 6) + '-' + digits.substring(6);
            }
            if (digits.length > 3) {
                return '(' + digits.substring(0, 3) + ') ' + digits.substring(3);
            }
            if (digits.length > 0) {
                return '(' + digits;
            }
            return '';
        }

        if (code === '+351') {
            digits = digits.substring(0, 9);
            if (digits.length > 6) {
                return digits.substring(0, 3) + ' ' + digits.substring(3, 6) + ' ' + digits.substring(6);
            }
            if (digits.length > 3) {
                return digits.substring(0, 3) + ' ' + digits.substring(3);
            }
            return digits;
        }

        return String(raw || '').trim();
    }

    function syncPhoneMeta() {
        if (!phoneCountrySelect || !phoneInput) return;
        var code = phoneCountrySelect.value || '+55';
        phoneInput.placeholder = phonePlaceholder(code);
        phoneInput.maxLength = phoneMaxLength(code);
    }

    if (phoneCountrySelect && phoneInput) {
        syncPhoneMeta();
        phoneCountrySelect.addEventListener('change', function () {
            phoneInput.value = '';
            phoneInput.removeAttribute('aria-invalid');
            syncPhoneMeta();
        });
        phoneInput.addEventListener('input', function () {
            var code = phoneCountrySelect.value || '+55';
            phoneInput.value = formatNationalPhone(phoneInput.value, code);
        });
    }

    function updateUI() {
        var progressClass = currentStep === 1 ? 'jr-progress-0' : (currentStep === 2 ? 'jr-progress-50' : 'jr-progress-100');
        progressLine.classList.remove('jr-progress-0', 'jr-progress-50', 'jr-progress-100');
        progressLine.classList.add(progressClass);

        for (var i = 1; i <= TOTAL_STEPS; i++) {
            document.getElementById('jr-circle-' + i).classList.toggle('active', i <= currentStep);
            document.getElementById('jr-label-' + i).classList.toggle('active', i <= currentStep);
            document.getElementById('jr-step-' + i).classList.toggle('active', i === currentStep);
        }

        btnPrev.classList.toggle('jr-hidden', currentStep <= 1);
        btnNext.classList.toggle('jr-hidden', currentStep === TOTAL_STEPS);
        btnSubmit.classList.toggle('jr-hidden', currentStep !== TOTAL_STEPS);
        notifyParentResize();
    }

    function setError(field, message) {
        var el = document.getElementById('error-' + field);
        if (!el) return;
        el.textContent = message;
        el.classList.add('jr-show');

        var input = document.getElementById(field);
        if (input) input.setAttribute('aria-invalid', 'true');
    }

    function clearErrors() {
        var nodes = document.querySelectorAll('.jr-error-message');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].classList.remove('jr-show');
            nodes[i].textContent = '';
        }
        var invalid = document.querySelectorAll('[aria-invalid]');
        for (var j = 0; j < invalid.length; j++) invalid[j].removeAttribute('aria-invalid');

        alertBox.className = 'jr-alert';
        alertBox.textContent = '';
        hideSuccessPanel();
    }

    function hideSuccessPanel() {
        if (successPanel) {
            successPanel.classList.remove('jr-show');
            successPanel.setAttribute('hidden', '');
            if (successMessage) successMessage.textContent = '';
        }
        form.classList.remove('jr-hidden');
        var steps = document.getElementById('jr-steps');
        if (steps) steps.classList.remove('jr-hidden');
        document.body.classList.remove('jr-embed-success');
    }

    function showSuccessPanel(message) {
        if (successMessage) successMessage.textContent = message;
        if (successPanel) {
            successPanel.classList.add('jr-show');
            successPanel.removeAttribute('hidden');
        }
        form.classList.add('jr-hidden');
        var steps = document.getElementById('jr-steps');
        if (steps) steps.classList.add('jr-hidden');
        alertBox.className = 'jr-alert';
        alertBox.classList.remove('jr-show');
        alertBox.textContent = '';
        document.body.classList.add('jr-embed-success');
        if (successPanel) {
            successPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        notifyParentResize();
    }

    function showAlert(kind, message) {
        if (kind === 'success') {
            showSuccessPanel(message);
            return;
        }

        hideSuccessPanel();
        alertBox.className = 'jr-alert jr-alert-' + kind + ' jr-show';
        alertBox.textContent = message;
    }

    function value(id) {
        var el = document.getElementById(id);
        return el ? String(el.value || '').trim() : '';
    }

    function validateStep1() {
        clearErrors();
        var valid = true;
        var name = value('name');
        var email = value('email');
        var password = value('password');
        var confirmation = value('password_confirmation');

        if (name.length < 2) { setError('name', FIELDS.name.message); valid = false; }
        if (!email) { setError('email', FIELDS.email.message); valid = false; }
        else if (email.length > LIMITS.email || email.indexOf(' ') !== -1) { setError('email', 'Informe um e-mail corporativo válido'); valid = false; }
        if (password.length < 8) { setError('password', 'A senha deve ter pelo menos 8 caracteres'); valid = false; }
        else if (password.length > LIMITS.password) { setError('password', 'A senha deve ter no máximo 64 caracteres'); valid = false; }
        if (password !== confirmation) { setError('password_confirmation', FIELDS.password_confirmation.message); valid = false; }

        return valid;
    }

    function validateStep2() {
        clearErrors();
        var valid = true;
        var phone = value('phone');
        var company = value('company_name');
        var cargo = value('cargo');
        var countryCode = value('phone_country_code');

        if (!COUNTRY_CODE_PATTERN.test(countryCode)) {
            setError('phone_country_code', FIELDS.phone_country_code.message);
            valid = false;
        }
        if (!PHONE_PATTERN.test(phone)) { setError('phone', 'Informe um telefone válido'); valid = false; }
        if (company.length < 2 || /[0-9@]/.test(company)) { setError('company_name', 'Informe o nome da empresa sem números ou e-mails'); valid = false; }
        if (cargo.length < 2) { setError('cargo', FIELDS.cargo.message); valid = false; }

        return valid;
    }

    function validateStep3() {
        clearErrors();
        var valid = true;

        ['import_experience', 'import_volume', 'decision_role'].forEach(function (field) {
            if (!value(field)) { setError(field, FIELDS[field].message); valid = false; }
        });

        var terms = document.getElementById('terms');
        if (!terms || !terms.checked) { setError('terms', FIELDS.terms.message); valid = false; }

        return valid;
    }

    function focusFirstInvalid(step) {
        for (var field in FIELDS) {
            if (FIELDS[field].step !== step) continue;
            var el = document.getElementById(field);
            if (el && el.getAttribute('aria-invalid') === 'true') { el.focus(); return; }
        }
    }

    function collectPayload() {
        return {
            name: value('name'),
            email: value('email'),
            password: value('password'),
            password_confirmation: value('password_confirmation'),
            phone: value('phone'),
            phone_country_code: value('phone_country_code'),
            company_name: value('company_name'),
            cargo: value('cargo'),
            import_experience: value('import_experience'),
            import_volume: value('import_volume'),
            decision_role: value('decision_role'),
            g_recaptcha: '',
            terms: document.getElementById('terms').checked ? '1' : '0',
            form_token: TOKEN,
            honeypot: value('jr-company-site'),
            form_source: FORM_SOURCE,
            utm_source: UTM.utm_source || '',
            utm_medium: UTM.utm_medium || '',
            utm_campaign: UTM.utm_campaign || '',
            utm_content: UTM.utm_content || '',
            utm_term: UTM.utm_term || '',
            gclid: UTM.gclid || '',
            fbclid: UTM.fbclid || '',
            msclkid: UTM.msclkid || ''
        };
    }

    function showServerErrors(errors) {
        var highest = 1;

        for (var field in errors) {
            if (!Object.prototype.hasOwnProperty.call(FIELDS, field)) continue;
            var messages = errors[field];
            var text = Array.isArray(messages) ? messages[0] : String(messages);
            setError(field, text);
            if (FIELDS[field].step > highest) highest = FIELDS[field].step;
        }

        if (currentStep !== highest) {
            currentStep = highest;
            updateUI();
        }

        focusFirstInvalid(highest);
        showAlert('danger', 'Por favor, verifique os erros nos campos.');
    }

    btnNext.addEventListener('click', function () {
        var ok = currentStep === 1 ? validateStep1() : validateStep2();
        if (!ok) return;
        if (currentStep < TOTAL_STEPS) {
            currentStep++;
            updateUI();
            window.scrollTo(0, 0);
        }
    });

    btnPrev.addEventListener('click', function () {
        if (currentStep > 1) {
            currentStep--;
            updateUI();
            clearErrors();
        }
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!validateStep3()) {
            focusFirstInvalid(3);
            return;
        }

        // Envio mais rápido que o mínimo humano configurado: bot.
        if (Date.now() - loadedAt < MIN_FILL_MS) {
            showAlert('danger', 'Aguarde um instante e envie novamente.');
            return;
        }

        var busy = true;
        btnSubmit.disabled = true;
        btnSubmit.textContent = 'Processando...';
        btnPrev.disabled = true;

        var restore = function () {
            if (!busy) return;
            busy = false;
            btnSubmit.disabled = false;
            btnSubmit.textContent = 'Criar Conta';
            btnPrev.disabled = false;
        };

        recaptchaToken()
            .catch(function () {
                restore();
                showAlert('danger', 'Não foi possível verificar que você não é um robô. Recarregue a página e tente novamente.');
                return null;
            })
            .then(function (token) {
                if (token === null) return null;

                if (RECAPTCHA_SITE_KEY && RECAPTCHA_TYPE !== 'v3' && !token) {
                    restore();
                    showAlert('danger', 'Confirme que você não é um robô.');
                    return null;
                }

                var payload = collectPayload();
                payload.g_recaptcha = token;

                return fetch(ENDPOINT, {
                    method: 'POST',
                    mode: 'cors',
                    credentials: 'omit',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
            })
            .then(function (response) {
                if (response === null) return { response: { status: 0 }, body: {} };
                return response.json().catch(function () { return {}; }).then(function (body) {
                    return { response: response, body: body };
                });
            })
            .then(function (result) {
                var status = result.response.status;
                var body = result.body;

                if (status === 422 && body.errors) {
                    showServerErrors(body.errors);
                    return;
                }

                if (status === 201 || status === 200) {
                    showAlert('success', body.message || 'Cadastro recebido com sucesso!');
                    window.dataLayer = window.dataLayer || [];
                    window.dataLayer.push({
                        event: 'form_registration_success',
                        form_source: FORM_SOURCE,
                        utm_source: UTM.utm_source || '',
                        utm_medium: UTM.utm_medium || '',
                        utm_campaign: UTM.utm_campaign || '',
                        utm_content: UTM.utm_content || '',
                        utm_term: UTM.utm_term || '',
                        gclid: UTM.gclid || '',
                        fbclid: UTM.fbclid || '',
                        msclkid: UTM.msclkid || ''
                    });
                    form.reset();
                    currentStep = 1;
                    updateUI();
                    notifyParentResize();
                    return;
                }

                if (status === 429) {
                    showAlert('danger', 'Muitas tentativas. Aguarde um minuto e tente novamente.');
                    return;
                }

                if (status === 403) {
                    showAlert('danger', 'Não foi possível concluir o cadastro a partir deste endereço.');
                    return;
                }

                showAlert('danger', 'Erro ao processar. Tente novamente.');
            })
            .catch(function () {
                showAlert('danger', 'Erro de conexão com o servidor.');
            })
            .finally(function () {
                resetRecaptcha();
                if (!busy) return;
                busy = false;
                btnSubmit.disabled = false;
                btnSubmit.textContent = 'Criar Conta';
                btnPrev.disabled = false;
            });
    });

    updateUI();
    renderRecaptcha();
    window.addEventListener('load', notifyParentResize);
    window.addEventListener('resize', notifyParentResize);
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(notifyParentResize);
    }
})();
