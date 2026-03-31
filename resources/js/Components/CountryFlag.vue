<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: {
        type: String,
        required: true
    },
    className: {
        type: String,
        default: 'w-5 h-4 object-cover shadow-sm'
    }
});

const countryMap = {
    'brasil': 'br',
    'china': 'cn',
    'índia': 'in',
    'india': 'in',
    'vietnã': 'vn',
    'vietnam': 'vn',
    'indonésia': 'id',
    'indonesia': 'id',
    'madagáscar': 'mg',
    'madagascar': 'mg',
    'egito': 'eg',
    'egypt': 'eg',
    'espanha': 'es',
    'spain': 'es',
    'estados unidos': 'us',
    'usa': 'us',
    'argentina': 'ar',
    'paraguai': 'py',
    'paraguay': 'py',
    'uruguai': 'uy',
    'uruguay': 'uy',
    'méxico': 'mx',
    'mexico': 'mx',
    'canadá': 'ca',
    'canada': 'ca',
    'frança': 'fr',
    'france': 'fr',
    'alemanha': 'de',
    'germany': 'de',
    'itália': 'it',
    'italy': 'it',
    'reino unido': 'gb',
    'united kingdom': 'gb',
    'uk': 'gb',
    'japão': 'jp',
    'japan': 'jp',
    'turquia': 'tr',
    'turkey': 'tr',
    'rússia': 'ru',
    'russia': 'ru',
    'áfrica do sul': 'za',
    'south africa': 'za',
    'nigeria': 'ng',
    'nigéria': 'ng',
    'marrocos': 'ma',
    'morocco': 'ma',
    'peru': 'pe',
    'colômbia': 'co',
    'colombia': 'co',
};

const flagUrl = computed(() => {
    if (!props.name) return null;
    const normalized = props.name.toLowerCase().trim();
    const code = countryMap[normalized];
    
    if (code) {
        return `https://flagcdn.com/w40/${code}.png`;
    }
    
    return null;
});
</script>

<template>
    <img 
        v-if="flagUrl" 
        :src="flagUrl" 
        :alt="name" 
        :class="className"
        loading="lazy"
    />
</template>
