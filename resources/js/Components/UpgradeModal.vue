<script setup>
import Modal from './Modal.vue';
import { Lock, X, MessageSquare } from 'lucide-vue-next';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Recurso Premium',
    },
    message: {
        type: String,
        default: 'Para visualizar os detalhes completos por produto, fale com nosso consultor.',
    },
});

const emit = defineEmits(['close']);
</script>

<template>
    <Modal :show="show" @close="emit('close')" maxWidth="33rem">
        <div class="relative bg-white rounded-3xl overflow-hidden shadow-2xl">
            <!-- Background Decoration -->
            <div class="absolute top-0 right-0 -mr-16 -mt-16 w-48 h-48 bg-blue-50 rounded-full blur-3xl opacity-50"></div>
            <div class="absolute bottom-0 left-0 -ml-16 -mb-16 w-48 h-48 bg-blue-50 rounded-full blur-3xl opacity-50"></div>

            <div class="p-8 relative z-10">
                <!-- Close Button -->
                <button @click="emit('close')" class="absolute top-4 right-4 p-2 text-slate-300 hover:text-slate-500 hover:bg-slate-50 rounded-xl transition-all">
                    <X class="w-5 h-5" />
                </button>

                <div class="flex flex-col items-center text-center space-y-4 pt-4">
                    <!-- Lock Icon -->
                    <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-2 shadow-sm border border-blue-100">
                        <Lock class="w-8 h-8" />
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-2xl font-black text-slate-900 leading-tight uppercase tracking-tight">
                            {{ title }}
                        </h3>
                        <p class="text-sm text-slate-500 font-medium px-4">
                            {{ message }}
                        </p>
                    </div>

                    <div class="w-full pt-4 flex flex-col sm:flex-row items-center gap-3">
                        <button 
                            @click="emit('close')" 
                            class="w-full sm:flex-1 px-6 py-4 rounded-2xl text-xs font-bold text-slate-500 uppercase tracking-widest hover:bg-slate-50 transition-all"
                        >
                            Fechar
                        </button>
                        
                        <Link 
                            :href="route('dashboard.page', { slug: 'contato' })"
                            class="w-full sm:flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 px-6 rounded-2xl text-xs uppercase tracking-widest transition-all text-center flex items-center justify-center gap-2 shadow-lg shadow-blue-200 group transform hover:-translate-y-1 whitespace-nowrap"
                        >
                            <MessageSquare class="w-4 h-4 group-hover:scale-110 transition-transform" />
                            Entrar em contato
                        </Link>
                    </div>
                </div>
            </div>
            
            <div class="bg-slate-50 p-4 border-t border-slate-100/50 flex justify-center">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                    JRSPICE PLATAFORMA DE PREÇOS
                </p>
            </div>
        </div>
    </Modal>
</template>
