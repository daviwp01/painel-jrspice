<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: Array
});
</script>

<template>
    <div v-if="links.length > 3" class="flex flex-wrap items-center justify-center gap-2">
        <template v-for="(link, key) in links" :key="key">
            <div
                v-if="link.url === null"
                class="px-4 py-2 text-xs font-black text-slate-300 uppercase tracking-widest bg-slate-50 border border-slate-100 rounded-xl cursor-not-allowed"
                v-html="link.label"
            />
            <Link
                v-else
                :href="link.url"
                class="px-4 py-2 text-xs font-black uppercase tracking-widest rounded-xl border transition-all duration-200"
                :class="link.active
                    ? 'bg-indigo-600 border-indigo-600 text-white shadow-lg shadow-indigo-100'
                    : 'bg-white border-slate-200 text-slate-500 hover:border-indigo-400 hover:text-indigo-600 shadow-sm'"
                v-html="link.label"
                preserve-scroll
            />
        </template>
    </div>
</template>
