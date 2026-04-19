<script setup>
import { useForm } from '@inertiajs/vue3';
import { Layout, Check, ShieldCheck } from 'lucide-vue-next';

const props = defineProps({
    initialPages: Array,
    availablePages: Array
});

const form = useForm({
    settings: {
        default_allowed_pages: props.initialPages || [],
    },
});

const togglePage = (slug) => {
    const index = form.settings.default_allowed_pages.indexOf(slug);
    if (index > -1) {
        form.settings.default_allowed_pages.splice(index, 1);
    } else {
        form.settings.default_allowed_pages.push(slug);
    }
};

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden group hover:border-blue-400/30 transition-all duration-500">
        <div class="p-8 md:p-10">
            <div class="flex items-start justify-between mb-8">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform duration-500">
                        <Layout class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-tight">Acesso Padrão (Novos Usuários)</h3>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Define quais relatórios estarão visíveis por padrão para usuários não-master.</p>
                    </div>
                </div>
            </div>

            <!-- Page Selection Grid -->
            <div v-if="availablePages.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                <div
                    v-for="page in availablePages"
                    :key="page.id"
                    @click="togglePage(page.slug)"
                    class="relative p-5 rounded-2xl border-2 transition-all cursor-pointer flex items-center gap-4"
                    :class="form.settings.default_allowed_pages.includes(page.slug)
                        ? 'border-blue-500 bg-blue-50/30 shadow-sm'
                        : 'border-slate-100 bg-slate-50/30 hover:border-slate-200'"
                >
                    <div
                        class="w-8 h-8 rounded-xl flex items-center justify-center transition-all"
                        :class="form.settings.default_allowed_pages.includes(page.slug)
                            ? 'bg-blue-500 text-white'
                            : 'bg-white border border-slate-200 text-slate-300'"
                    >
                        <Check v-if="form.settings.default_allowed_pages.includes(page.slug)" class="w-4 h-4" />
                        <div v-else class="w-2 h-2 rounded-full bg-slate-200"></div>
                    </div>
                    
                    <div>
                        <h4 class="text-xs font-bold text-slate-800 uppercase tracking-tight leading-none mb-1">{{ page.title }}</h4>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">{{ page.slug }}</p>
                    </div>

                    <div v-if="form.settings.default_allowed_pages.includes(page.slug)" class="absolute top-2 right-2">
                         <div class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-ping"></div>
                    </div>
                </div>
            </div>

            <div v-else class="bg-slate-50 border-2 border-dashed border-slate-200 rounded-3xl p-12 text-center">
                <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                    <Layout class="w-6 h-6 text-slate-300" />
                </div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-[0.2em]">Nenhum relatório disponível</p>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2 px-6">Certifique-se de que as páginas foram criadas na gestão de dados.</p>
            </div>

            <div class="flex items-center justify-between pt-8 border-t border-slate-100">
                <div class="flex items-center space-x-2">
                    <ShieldCheck class="w-4 h-4 text-emerald-500" />
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Segurança de Acesso Ativa</span>
                </div>
                <button
                    @click="submit"
                    :disabled="form.processing"
                    class="bg-[#0f172a] hover:bg-slate-800 text-white px-8 py-3.5 rounded-2xl text-[10px] font-bold uppercase tracking-[0.2em] transition-all active:scale-95 disabled:opacity-50 flex items-center gap-2"
                >
                    Salvar Alterações
                    <div v-if="form.processing" class="w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                </button>
            </div>
        </div>
    </div>
</template>
