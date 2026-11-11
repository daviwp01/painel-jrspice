<script setup>
import { computed, onMounted, ref } from 'vue';
import { getActiveLanguage, loadLanguageAsync } from 'laravel-vue-i18n';
import Dropdown from '@/Components/Dropdown.vue';
import { Check, ChevronDown, Languages } from 'lucide-vue-next';

const props = defineProps({
    variant: {
        type: String,
        default: 'light',
        validator: (value) => ['light', 'dark'].includes(value),
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const languages = [
    { value: 'en', label: 'English', flag: '🇺🇸' },
    { value: 'pt_BR', label: 'Português (BR)', flag: '🇧🇷' },
    { value: 'es', label: 'Español', flag: '🇪🇸' },
];

const readLocaleCookie = () => {
    const match = document.cookie.match(/(?:^|;\s*)app_locale=([^;]+)/);
    return match ? match[1] : null;
};

const currentLang = ref('pt_BR');

onMounted(() => {
    const active = getActiveLanguage() || readLocaleCookie() || 'pt_BR';
    const matched = languages.find((lang) => lang.value === active)
        || languages.find((lang) => active.startsWith(lang.value.split('_')[0]));
    currentLang.value = matched ? matched.value : 'pt_BR';
});

const current = computed(
    () => languages.find((lang) => lang.value === currentLang.value) || languages[0],
);

const changeLanguage = async (value) => {
    if (value === currentLang.value) return;

    currentLang.value = value;

    const expires = new Date();
    expires.setTime(expires.getTime() + 365 * 24 * 60 * 60 * 1000);
    document.cookie = `app_locale=${value};expires=${expires.toUTCString()};path=/`;

    // Applies messages + currentLanguage before the next paint so every
    // consumer of currentLocale / $t re-renders in the same switch.
    await loadLanguageAsync(value);
};
</script>

<template>
    <Dropdown
        align="right"
        width="56"
        content-classes="py-1.5 bg-white rounded-2xl border border-slate-200/80 shadow-2xl shadow-slate-900/10 overflow-hidden"
    >
        <template #trigger>
            <button
                type="button"
                class="group inline-flex items-center gap-2 rounded-xl border transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/40"
                :class="[
                    compact
                        ? 'px-2 py-1.5'
                        : 'px-3 py-2',
                    variant === 'dark'
                        ? 'border-white/10 bg-white/5 text-slate-300 hover:border-white/20 hover:bg-white/10 hover:text-white'
                        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900',
                ]"
                :aria-label="$t('Change language')"
                :title="$t('Change language')"
            >
                <span class="text-base leading-none">{{ current.flag }}</span>
                <span
                    v-if="!compact"
                    class="text-xs font-bold tracking-wide"
                >{{ current.label }}</span>
                <ChevronDown
                    class="h-3.5 w-3.5 opacity-50 transition-transform duration-200 group-hover:opacity-80"
                />
            </button>
        </template>

        <template #content>
            <p
                class="px-4 pb-2 pt-1 text-[10px] font-black uppercase tracking-[0.18em] text-slate-400"
            >
                <Languages class="mr-1.5 inline h-3 w-3 align-[-2px]" />
                {{ $t('Language') }}
            </p>

            <button
                v-for="lang in languages"
                :key="lang.value"
                type="button"
                class="group flex w-full items-center gap-3 px-4 py-2.5 text-sm font-semibold transition-colors"
                :class="lang.value === currentLang
                    ? 'bg-blue-50 text-blue-700'
                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                @click="changeLanguage(lang.value)"
            >
                <span
                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-sm leading-none shadow-sm transition-colors group-hover:border-slate-300"
                >{{ lang.flag }}</span>
                <span class="flex-1 text-left">{{ lang.label }}</span>
                <Check
                    v-if="lang.value === currentLang"
                    class="h-4 w-4 text-blue-600"
                    :stroke-width="3"
                />
            </button>
        </template>
    </Dropdown>
</template>
