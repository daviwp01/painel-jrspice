<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    links: Array,
});

const isPrevious = (label) => {
    if (!label) return false;
    const l = String(label).toLowerCase();
    return /previous|anterior|prev|«/.test(l);
};

const isNext = (label) => {
    if (!label) return false;
    const l = String(label).toLowerCase();
    return /next|proximo|próximo|prox|»/.test(l);
};

const cleanLabel = (label) => {
    if (isPrevious(label)) return 'Anterior';
    if (isNext(label)) return 'Próximo';
    return label;
};
</script>

<template>
    <div v-if="links?.length > 3" class="flex items-center gap-1.5 flex-nowrap overflow-x-auto pb-1 no-scrollbar">
        <template v-for="(link, key) in links" :key="key">
            <!-- Caso seja URL nula (primeiro ou último inativo) -->
            <div 
                v-if="link.url === null" 
                class="h-8 px-3 flex items-center justify-center text-slate-300 pointer-events-none gap-1 bg-slate-50 border border-slate-100 rounded-lg"
            >
                <ChevronLeft v-if="isPrevious(link.label)" class="w-3.5 h-3.5" />
                <span v-if="isPrevious(link.label) || isNext(link.label)" class="text-[10px] font-black uppercase tracking-widest whitespace-nowrap">
                    {{ cleanLabel(link.label) }}
                </span>
                <span v-else class="text-xs font-bold">{{ link.label }}</span>
                <ChevronRight v-if="isNext(link.label)" class="w-3.5 h-3.5" />
            </div>

            <!-- Caso seja um Link Ativo ou Normal -->
            <Link 
                v-else 
                :href="link.url"
                class="min-w-[32px] h-8 px-3 flex items-center justify-center rounded-lg text-xs font-bold transition-all gap-1.5 whitespace-nowrap"
                :class="[
                    link.active 
                        ? 'bg-blue-600 text-white shadow-md shadow-blue-200 pointer-events-none' 
                        : 'bg-white text-slate-500 hover:bg-blue-50 hover:text-blue-600 border border-slate-100 hover:border-blue-200'
                ]"
                preserve-scroll
            >
                <ChevronLeft v-if="isPrevious(link.label)" class="w-3.5 h-3.5" />
                
                <span :class="(isPrevious(link.label) || isNext(link.label)) ? 'text-[10px] uppercase tracking-widest font-black' : ''">
                    {{ cleanLabel(link.label) }}
                </span>
                
                <ChevronRight v-if="isNext(link.label)" class="w-3.5 h-3.5" />
            </Link>
        </template>
    </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
span {
    line-height: 1;
}
</style>
