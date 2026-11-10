import { getAppLocale } from '@/Utils/locale';

/**
 * Display-only product label. Never use the result for queries, sorting
 * keys or form submits — the canonical identifier stays `name`.
 */
export function productName(product) {
    if (!product) return '';
    const locale = getAppLocale();
    if (locale === 'en' && product.name_en) return product.name_en;
    if (locale === 'es' && product.name_es) return product.name_es;
    return product.name ?? '';
}

/**
 * Returns a shallow copy list with `name` swapped for the localized label,
 * for option lists / selects. Identity (`id`) is untouched.
 */
export function withTranslatedProductNames(products) {
    if (!Array.isArray(products)) return [];
    return products.map((p) => ({ ...p, name: productName(p) }));
}
