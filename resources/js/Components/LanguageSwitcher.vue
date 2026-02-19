<script setup>
import { loadLanguageAsync } from 'laravel-vue-i18n';
import { computed, ref, onMounted } from 'vue';
import { getActiveLanguage } from 'laravel-vue-i18n';
import Dropdown from '@/Components/Dropdown.vue';

const getLocaleCookie = () => {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; app_locale=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
};

const currentLang = ref('en');

const items = [
    { value: 'en', label: 'United States', flag: '🇺🇸' },
    { value: 'pt_BR', label: 'Brasil', flag: '🇧🇷' },
    { value: 'pt_PT', label: 'Portugal', flag: '🇵🇹' },
    { value: 'es', label: 'España', flag: '🇪🇸' },
];

onMounted(() => {
    currentLang.value = getActiveLanguage() || getLocaleCookie() || 'en';
});

const currentLabel = computed(() => {
    const found = items.find(i => i.value === currentLang.value) ||
                  items.find(i => currentLang.value.startsWith(i.value.split('_')[0]));
    return found ? found.label : 'United States';
});

const currentFlag = computed(() => {
    const found = items.find(i => i.value === currentLang.value) ||
                  items.find(i => currentLang.value.startsWith(i.value.split('_')[0]));
    return found ? found.flag : '🇺🇸';
});

const changeLanguage = (lang) => {
    currentLang.value = lang;
    loadLanguageAsync(lang);
    const d = new Date();
    d.setTime(d.getTime() + (365*24*60*60*1000));
    document.cookie = "app_locale=" + lang + ";expires="+ d.toUTCString() + ";path=/";
};
</script>

<template>
    <div class="relative z-50">
        <!--
            Fixing the 'double' visual issue:
            We pass the styling classes to the Dropdown component via content-classes instead of nesting a styled div.
        -->
        <Dropdown
            align="right"
            width="48"
            content-classes="py-1 bg-white rounded-xl border border-gray-100 shadow-2xl ring-1 ring-black ring-opacity-5 overflow-hidden"
        >
            <template #trigger>
                <button
                    type="button"
                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-bold rounded-xl text-gray-500 hover:text-gray-900 hover:bg-gray-50 focus:outline-none transition-all duration-200 ease-in-out gap-x-2"
                >
                    <span class="text-lg leading-none shadow-sm rounded-full overflow-hidden">{{ currentFlag }}</span>
                    <span class="hidden sm:inline-block tracking-wide">{{ currentLabel }}</span>
                    <svg
                        class="-me-0.5 h-4 w-4 text-gray-400"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                    >
                        <path
                            fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                        />
                    </svg>
                </button>
            </template>

            <template #content>
                <!-- Direct button list, no extra wrapping div to avoid double borders -->
                <button
                    v-for="item in items"
                    :key="item.value"
                    @click="changeLanguage(item.value)"
                    class="group flex w-full items-center px-4 py-3 text-sm font-medium transition-all duration-200 hover:bg-gray-50 border-b border-gray-50 last:border-0"
                    :class="item.value === currentLang ? 'bg-indigo-50/50 text-indigo-700' : 'text-gray-600'"
                >
                    <span class="mr-3 text-lg leading-none shadow-sm rounded-full w-6 h-6 flex items-center justify-center bg-gray-50 border border-gray-100 group-hover:border-gray-200 transition-colors">{{ item.flag }}</span>
                    <span class="flex-1 text-left">{{ item.label }}</span>

                    <span v-if="item.value === currentLang" class="text-indigo-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                        </svg>
                    </span>
                </button>
            </template>
        </Dropdown>
    </div>
</template>
