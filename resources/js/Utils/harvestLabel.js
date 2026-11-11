import { getAppLocale } from '@/Utils/locale';

/**
 * Display-only translation for free-form PT harvest strings stored in
 * products.harvest_month ("JULHO", "JANEIRO A ABRIL", "ANO TODO"…).
 * Never used for filtering or persistence — `name`/`harvest_month` stay canonical.
 */
const MONTHS = {
    JANEIRO: { en: 'January', es: 'Enero' },
    FEVEREIRO: { en: 'February', es: 'Febrero' },
    MARÇO: { en: 'March', es: 'Marzo' },
    MARCO: { en: 'March', es: 'Marzo' },
    ABRIL: { en: 'April', es: 'Abril' },
    MAIO: { en: 'May', es: 'Mayo' },
    JUNHO: { en: 'June', es: 'Junio' },
    JULHO: { en: 'July', es: 'Julio' },
    AGOSTO: { en: 'August', es: 'Agosto' },
    SETEMBRO: { en: 'September', es: 'Septiembre' },
    OUTUBRO: { en: 'October', es: 'Octubre' },
    NOVEMBRO: { en: 'November', es: 'Noviembre' },
    DEZEMBRO: { en: 'December', es: 'Diciembre' },
};

const PHRASES = {
    'ANO TODO': { en: 'ALL YEAR', es: 'TODO EL AÑO' },
    '1º TRIMESTRE': { en: '1ST QUARTER', es: '1ER TRIMESTRE' },
    '2º TRIMESTRE': { en: '2ND QUARTER', es: '2DO TRIMESTRE' },
    '3º TRIMESTRE': { en: '3RD QUARTER', es: '3ER TRIMESTRE' },
    '4º TRIMESTRE': { en: '4TH QUARTER', es: '4TO TRIMESTRE' },
};

/**
 * @param {string|null|undefined} value raw harvest_month from the database
 * @returns {string} label in the active locale (falls back to the raw value)
 */
export function harvestLabel(value) {
    if (!value) return value ?? '';

    // Only en/es get a translated label; pt_BR (and anything unknown) keeps
    // the canonical database value — same rule as productName().
    const appLocale = getAppLocale();
    const locale = appLocale === 'en' ? 'en' : appLocale === 'es' ? 'es' : null;

    if (locale === null) {
        return String(value);
    }

    const raw = String(value).trim().toUpperCase();

    if (PHRASES[raw]) return PHRASES[raw][locale];

    if (MONTHS[raw]) return MONTHS[raw][locale];

    // Ranges / lists: "JULHO A AGOSTO", "ABRIL E SETEMBRO"
    let out = String(value);

    for (const [pt, forms] of Object.entries(MONTHS)) {
        const re = new RegExp(`\\b${pt}\\b`, 'gi');
        if (re.test(out)) {
            out = out.replace(re, forms[locale]);
        }
    }

    if (locale === 'en') {
        out = out.replace(/\bA\b/gi, 'TO').replace(/\bE\b/gi, 'AND');
    } else if (locale === 'es') {
        out = out.replace(/\bE\b/gi, 'y');
    }

    // Preserve the ALL-CAPS style used by the dashboard badge
    if (raw === String(value).trim()) {
        return out.toUpperCase();
    }

    return out;
}
