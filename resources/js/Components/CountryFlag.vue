<script setup>
import { computed } from 'vue';
import { globalCountries } from '@/Constants/globalCountries';

const props = defineProps({
    name: {
        type: String,
        required: true
    },
    code: {
        type: String,
        default: null
    },
    className: {
        type: String,
        default: 'w-5 h-4 object-cover shadow-sm'
    }
});

const countryMap = {
    'brasil': 'br', 'china': 'cn', 'india': 'in', 'indonesia': 'id', 'vietna': 'vn', 'vietnam': 'vn',
    'madagascar': 'mg', 'egito': 'eg', 'egypt': 'eg', 'espanha': 'es', 'spain': 'es', 'argentina': 'ar',
    'paraguai': 'py', 'paraguay': 'py', 'uruguai': 'uy', 'uruguay': 'uy', 'mexico': 'mx', 'canada': 'ca',
    'franca': 'fr', 'france': 'fr', 'alemanha': 'de', 'germany': 'de', 'italia': 'it', 'italy': 'it',
    'reino unido': 'gb', 'united kingdom': 'gb', 'uk': 'gb', 'japao': 'jp', 'japan': 'jp', 'turquia': 'tr',
    'turkey': 'tr', 'russia': 'ru', 'africa do sul': 'za', 'south africa': 'za', 'nigeria': 'ng', 'marrocos': 'ma',
    'morocco': 'ma', 'peru': 'pe', 'colombia': 'co', 'chile': 'cl', 'bulgaria': 'bg', 'guatemala': 'gt',
    'paquistao': 'pk', 'pakistan': 'pk', 'sri lanka': 'lk', 'malaysia': 'my', 'malasia': 'my',
    'tailandia': 'th', 'thailand': 'th', 'equador': 'ec', 'equator': 'ec', 'holanda': 'nl',
    'netherlands': 'nl', 'portugal': 'pt', 'grecia': 'gr', 'greece': 'gr', 'panama': 'pa', 'costa rica': 'cr',
    'honduras': 'hn', 'salvador': 'sv', 'nicaragua': 'ni', 'venezuela': 've', 'bolivia': 'bo', 
    'africa': 'za', 'emirados': 'ae', 'dubai': 'ae', 'arabia': 'sa', 'combodia': 'kh', 'catar': 'qa', 'israel': 'il',
    'suica': 'ch', 'switzerland': 'ch',
};

const removeAccents = (str) => {
    return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

const flagUrl = computed(() => {
    // 1. Prioridade: Código ISO passado via prop (validamos se tem 2 caracteres)
    if (props.code && props.code.length === 2) {
        return `https://flagcdn.com/w40/${props.code.toLowerCase()}.png`;
    }

    if (!props.name) return null;
    
    // 2. Normalização e Tratamento de Nomes Compostos (Ex: Malasia / Sri Lanka)
    // Pegamos apenas a primeira parte do nome se houver uma barra
    const rawBaseName = props.name.includes('/') ? props.name.split('/')[0] : props.name;
    const normalizedName = removeAccents(rawBaseName.toLowerCase().trim());

    // 3. Fallback: Busca no mapa manual (para nomes alternativos ou legados)
    let codeFromMap = countryMap[normalizedName];
    
    if (codeFromMap) {
        return `https://flagcdn.com/w40/${codeFromMap}.png`;
    }

    // 4. Fallback Final: Busca exata no catálogo Global (globalCountries)
    const foundInGlobal = globalCountries.find(c => 
        removeAccents(c.name.toLowerCase()) === normalizedName
    );

    if (foundInGlobal) {
        return `https://flagcdn.com/w40/${foundInGlobal.code.toLowerCase()}.png`;
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
