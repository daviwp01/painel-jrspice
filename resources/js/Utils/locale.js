import { currentLocale } from 'laravel-vue-i18n';

const DEFAULT_LOCALE = 'pt_BR';

/**
 * Reactive application locale.
 *
 * Backed by laravel-vue-i18n's `currentLocale` (a computed over the shared
 * instance's language ref), so any consumer that reads it inside a Vue
 * computed/render re-evaluates the moment the user switches language.
 */
export function getAppLocale() {
    const fromLibrary = currentLocale.value;

    if (fromLibrary) {
        return String(fromLibrary).replace('-', '_');
    }

    const match = document.cookie.match(/(?:^|;\s*)app_locale=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : DEFAULT_LOCALE;
}

export function getBcp47Locale() {
    return getAppLocale().replace('_', '-');
}

export function formatNumber(value, options = {}) {
    return Number(value).toLocaleString(getBcp47Locale(), options);
}

export function formatDate(value, options = {}) {
    const date = value instanceof Date ? value : new Date(value);
    return date.toLocaleDateString(getBcp47Locale(), options);
}

export function formatDateTime(value, options = {}) {
    const date = value instanceof Date ? value : new Date(value);
    return date.toLocaleString(getBcp47Locale(), options);
}
