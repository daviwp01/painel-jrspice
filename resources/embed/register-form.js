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
        company_name: { step: 2, message: 'Nome da empresa é obrigatório' },
        cargo: { step: 2, message: 'Cargo é obrigatório' },
        import_experience: { step: 3, message: 'Selecione uma opção' },
        import_volume: { step: 3, message: 'Selecione uma opção' },
        decision_role: { step: 3, message: 'Selecione uma opção' },
        terms: { step: 3, message: 'É necessário aceitar a Política de Privacidade' }
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

    function updateUI() {
        progressLine.style.width = ((currentStep - 1) / (TOTAL_STEPS - 1)) * 100 + '%';

        for (var i = 1; i <= TOTAL_STEPS; i++) {
            document.getElementById('jr-circle-' + i).classList.toggle('active', i <= currentStep);
            document.getElementById('jr-label-' + i).classList.toggle('active', i <= currentStep);
            document.getElementById('jr-step-' + i).classList.toggle('active', i === currentStep);
        }

        btnPrev.style.display = currentStep > 1 ? 'block' : 'none';
        btnNext.style.display = currentStep === TOTAL_STEPS ? 'none' : 'block';
        btnSubmit.style.display = currentStep === TOTAL_STEPS ? 'block' : 'none';
    }

    function setError(field, message) {
        var el = document.getElementById('error-' + field);
        if (!el) return;
        el.textContent = message;
        el.style.display = 'block';

        var input = document.getElementById(field);
        if (input) input.setAttribute('aria-invalid', 'true');
    }

    function clearErrors() {
        var nodes = document.querySelectorAll('.jr-error-message');
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].style.display = 'none';
            nodes[i].textContent = '';
        }
        var invalid = document.querySelectorAll('[aria-invalid]');
        for (var j = 0; j < invalid.length; j++) invalid[j].removeAttribute('aria-invalid');

        alertBox.style.display = 'none';
        alertBox.className = 'jr-alert';
        alertBox.textContent = '';
    }

    function showAlert(kind, message) {
        alertBox.className = 'jr-alert jr-alert-' + kind;
        alertBox.textContent = message;
        alertBox.style.display = 'block';
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
            company_name: value('company_name'),
            cargo: value('cargo'),
            import_experience: value('import_experience'),
            import_volume: value('import_volume'),
            decision_role: value('decision_role'),
            g_recaptcha: '',
            terms: document.getElementById('terms').checked ? '1' : '0',
            form_token: TOKEN,
            honeypot: value('jr-company-site')
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
                    form.reset();
                    currentStep = 1;
                    updateUI();
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
})();
