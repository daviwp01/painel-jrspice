<script setup>
import { computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import { ShieldCheck, ScrollText, X } from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    type: {
        type: String, // 'privacy' or 'terms'
        required: true
    },
    content: {
        type: String,
        default: ''
    }
});

const emit = defineEmits(['close']);

const settings = computed(() => {
    if (props.type === 'privacy') {
        return {
            title: 'Privacy Policy',
            subtitle: 'Last updated: 2026',
            icon: ShieldCheck,
            iconClass: 'bg-blue-100 text-blue-600',
        };
    }
    return {
        title: 'Terms of Use',
        subtitle: 'Our commitment to you',
        icon: ScrollText,
        iconClass: 'bg-amber-100 text-amber-600',
    };
});
</script>

<template>
    <Modal :show="show" @close="emit('close')" maxWidth="2xl">
        <div class="flex flex-col h-[75vh] bg-white rounded-3xl overflow-hidden relative">
            <!-- Header -->
            <div class="px-8 py-8 border-b border-slate-100 flex items-center justify-between bg-slate-50/50 backdrop-blur-sm sticky top-0 z-10">
                <div class="flex items-center space-x-5">
                    <div :class="['p-4 rounded-2xl shadow-sm', settings.iconClass]">
                        <component :is="settings.icon" class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="text-2xl font-black text-slate-800 tracking-tight">{{ $t(settings.title) }}</h3>
                        <p class="text-xs font-black text-slate-400 uppercase tracking-[0.2em] mt-1">{{ $t(settings.subtitle) }}</p>
                    </div>
                </div>
                <button
                    @click="emit('close')"
                    class="p-3 rounded-xl hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-all active:scale-95"
                >
                    <X class="w-6 h-6" />
                </button>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto p-10 scroll-smooth">
                <div
                    v-if="content"
                    class="prose prose-slate max-w-none
                           prose-headings:font-black prose-headings:tracking-tight prose-headings:uppercase prose-headings:text-slate-800
                           prose-p:text-slate-600 prose-p:leading-relaxed prose-p:text-base
                           prose-li:text-slate-600 prose-li:text-base
                           prose-strong:text-slate-800 prose-strong:font-black
                           prose-a:text-indigo-600 prose-a:no-underline hover:prose-a:underline"
                    v-html="content"
                ></div>

                <div v-else class="h-full flex flex-col items-center justify-center text-center space-y-4 opacity-40 py-20">
                    <div class="p-6 bg-slate-100 rounded-full">
                        <component :is="settings.icon" class="w-12 h-12 text-slate-400" />
                    </div>
                    <div>
                        <p class="text-xl font-black text-slate-400 uppercase tracking-widest">{{ $t('Content not available') }}</p>
                        <p class="text-sm text-slate-400 font-medium">{{ $t('The administration team is currently updating this document.') }}</p>
                    </div>
                </div>
            </div>

        </div>
    </Modal>
</template>

<style scoped>
/* Custom scrollbar for a premium look */
div::-webkit-scrollbar {
    width: 6px;
}
div::-webkit-scrollbar-track {
    background: transparent;
}
div::-webkit-scrollbar-thumb {
    background: #e2e8f0;
    border-radius: 10px;
}
div::-webkit-scrollbar-thumb:hover {
    background: #cbd5e1;
}
</style>
