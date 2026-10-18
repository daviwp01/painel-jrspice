import { ref } from 'vue';

/**
 * Loads Google reCAPTCHA and produces the token for the configured type.
 *
 * - v2 ("Não sou um robô"): the widget is rendered into a container the caller
 *   provides and the token comes from `getResponse()`. It is single use, so the
 *   widget must be reset after every attempt.
 * - v3 (invisible, score): a token is minted on demand with `execute(action)`.
 *
 * The script is only fetched when a site key is configured, so environments
 * without reCAPTCHA never talk to Google.
 */
let scriptPromise = null;

function loadScript(renderSiteKey) {
    if (window.grecaptcha) return Promise.resolve(window.grecaptcha);
    if (scriptPromise) return scriptPromise;

    scriptPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        // v3 exige ?render=<chave>; v2 precisa do script "limpo" para que o
        // widget seja montado manualmente com grecaptcha.render().
        script.src = renderSiteKey
            ? `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(renderSiteKey)}`
            : 'https://www.google.com/recaptcha/api.js';
        script.async = true;
        script.defer = true;
        script.onload = () => resolve(window.grecaptcha);
        script.onerror = () => {
            scriptPromise = null;
            reject(new Error('Não foi possível carregar o reCAPTCHA.'));
        };
        document.head.appendChild(script);
    });

    return scriptPromise;
}

export function useRecaptcha(siteKey, type = 'v2') {
    const ready = ref(false);
    const widgetId = ref(null);

    const ensureLoaded = () => (type === 'v3' ? loadScript(siteKey) : loadScript(''));

    const render = async (container, options = {}) => {
        if (!siteKey || !container) return null;

        const grecaptcha = await ensureLoaded();
        await new Promise((resolve) => grecaptcha.ready(resolve));

        if (widgetId.value !== null) return widgetId.value;

        widgetId.value = grecaptcha.render(container, {
            sitekey: siteKey,
            ...options,
        });
        ready.value = true;

        return widgetId.value;
    };

    const token = async (action) => {
        if (!siteKey) return '';

        const grecaptcha = await ensureLoaded();
        await new Promise((resolve) => grecaptcha.ready(resolve));
        ready.value = true;

        if (type === 'v3') {
            return grecaptcha.execute(siteKey, { action });
        }

        const response = widgetId.value === null ? '' : grecaptcha.getResponse(widgetId.value);
        return response || '';
    };

    const reset = () => {
        if (!window.grecaptcha || widgetId.value === null) return;
        window.grecaptcha.reset(widgetId.value);
    };

    return { render, token, reset, ready, widgetId };
}
