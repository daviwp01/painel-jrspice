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
    <div v-if="links?.length > 3" class="flex flex-wrap items-center gap-1.5 justify-center md:justify-end">
        <template v-for="(link, key) in links" :key="key">
            <!-- Caso seja URL nula (primeiro ou último inativo) -->
            <div 
                v-if="link.url === null" 
                class="min-w-[32px] h-8 px-2.5 flex items-center justify-center text-slate-300 pointer-events-none gap-1 bg-slate-50 border border-slate-100 rounded-lg"
            >
                <ChevronLeft v-if="isPrevious(link.label)" class="w-3 h-3" />
                <span v-if="isPrevious(link.label) || isNext(link.label)" class="text-[9px] font-black uppercase tracking-widest whitespace-nowrap">
                    {{ cleanLabel(link.label) }}
                </span>
                <span v-else class="text-[10px] font-bold">{{ link.label }}</span>
                <ChevronRight v-if="isNext(link.label)" class="w-3 h-3" />
            </div>

            <!-- Caso seja um Link Ativo ou Normal -->
            <Link 
                v-else 
                :href="link.url"
                class="min-w-[32px] h-8 px-2.5 flex items-center justify-center rounded-lg text-[9px] font-black uppercase tracking-widest transition-all duration-200 border group select-none"
                :class="[
                    link.active 
                        ? 'bg-blue-600 text-white border-blue-600 shadow-sm shadow-blue-200 z-10' 
                        : 'bg-white text-slate-600 border-slate-200 hover:border-blue-300 hover:text-blue-600 hover:bg-blue-50'
                ]"
                preserve-scroll
                preserve-state
            >
                <ChevronLeft v-if="isPrevious(link.label)" class="w-3 h-3 group-hover:-translate-x-0.5 transition-transform" />
                
                <span v-if="isPrevious(link.label) || isNext(link.label)" class="whitespace-nowrap">
                    {{ cleanLabel(link.label) }}
                </span>
                <span v-else class="text-[10px] font-bold">{{ link.label }}</span>
                
                <ChevronRight v-if="isNext(link.label)" class="w-3 h-3 group-hover:translate-x-0.5 transition-transform" />
            </Link>
        </template>
    </div>
</template>

<style scoped>
span {
    line-height: 1;
}
</style>
