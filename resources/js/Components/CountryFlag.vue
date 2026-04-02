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
};

const removeAccents = (str) => {
    return str.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

const flagUrl = computed(() => {
    if (!props.name) return null;
    
    let nameToSearch = props.name.toLowerCase().trim();
    if (nameToSearch.includes('/')) {
        nameToSearch = nameToSearch.split('/')[0].trim();
    }
    
    nameToSearch = removeAccents(nameToSearch);
    const code = countryMap[nameToSearch];
    
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
