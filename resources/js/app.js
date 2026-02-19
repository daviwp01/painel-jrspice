import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Jrspice';

import { i18nVue } from 'laravel-vue-i18n';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18nVue, {
                lang: (() => {
                    const value = `; ${document.cookie}`;
                    const parts = value.split(`; app_locale=`);
                    if (parts.length === 2) return parts.pop().split(';').shift();
                    return 'en';
                })(),
                resolve: lang => {
                    const langs = import.meta.glob('../../lang/*.json', { eager: true });
                    return langs[`../../lang/${lang}.json`].default;
                }
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
