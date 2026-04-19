<script setup>
import { loadLanguageAsync } from 'laravel-vue-i18n';
import { computed, ref, onMounted } from 'vue';
import { getActiveLanguage } from 'laravel-vue-i18n';
import Dropdown from '@/Components/Dropdown.vue';
import { ChevronDown, Check } from 'lucide-vue-next';

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
                    <ChevronDown class="-me-0.5 h-4 w-4 text-gray-400" />
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
                        <Check class="h-4 w-4" stroke-width="3" />
                    </span>
                </button>
            </template>
        </Dropdown>
    </div>
</template>
