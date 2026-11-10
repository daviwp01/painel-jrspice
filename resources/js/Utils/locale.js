const DEFAULT_LOCALE = 'pt_BR';

export function getAppLocale() {
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
