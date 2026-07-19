<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PencilIcon, TrashIcon, XIcon, LayoutDashboardIcon } from 'lucide-vue-next';
import Pagination from '@/Components/Pagination.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const props = defineProps({
    pages: Object,
});

const pageForm = useForm({ id: null, title: '', component: 'Dashboard/Show', order: 0 });
const editingPage = ref(false);
const isConfirmModalOpen = ref(false);
const pageToDelete = ref(null);

const submitPage = () => {
    if (editingPage.value) {
        pageForm.put(route('admin.data.pages.update', pageForm.id), { onSuccess: cancelPageEdit });
    } else {
        pageForm.post(route('admin.data.pages.store'), { onSuccess: () => pageForm.reset() });
    }
};

const editPage = (p) => { 
    editingPage.value = true; 
    pageForm.id = p.id; 
    pageForm.title = p.title; 
    pageForm.component = p.component; 
    pageForm.order = p.order; 
};

const cancelPageEdit = () => { 
    editingPage.value = false; 
    pageForm.reset(); 
};

const deletePage = (p) => {
    pageToDelete.value = p;
    isConfirmModalOpen.value = true;
};

const confirmDeletePage = () => {
    if (!pageToDelete.value) return;
    router.delete(route('admin.data.pages.destroy', pageToDelete.value.id), {
        onSuccess: () => {
            isConfirmModalOpen.value = false;
            pageToDelete.value = null;
        }
    });
};

const changePage = (url) => {
    if (!url) return;
    router.visit(url, { 
        preserveState: true, 
        preserveScroll: true,
        only: ['pages']
    });
};

const formatLabel = (label) => {
    if (label.toLowerCase().includes('previous') || label.toLowerCase().includes('anterior')) return 'Anterior';
    if (label.toLowerCase().includes('next') || label.toLowerCase().includes('proximo')) return 'Próximo';
    return label;
};
</script>

<template>
    <div class="animate-in fade-in zoom-in-95 duration-200">
        <div class="flex flex-col xl:flex-row gap-8">
            <!-- Form -->
            <div class="w-full xl:w-1/3 bg-slate-50 p-6 rounded-2xl border border-slate-100 xl:sticky xl:top-6 self-start shadow-sm shadow-slate-200/50">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-blue-600"></div>
                    {{ editingPage ? 'Editando Página' : 'Adicionar Página' }}
                </h3>
                <form @submit.prevent="submitPage" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Título da Página</label>
                        <input v-model="pageForm.title" type="text" placeholder="Ex: Relatório de Vendas" required class="w-full bg-white border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Modelo de Layout</label>
                        <select v-model="pageForm.component" required class="w-full bg-white border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="Dashboard/Show">Modelo: Dashboard com Gráficos</option>
                            <option value="Dashboard/Demo">Modelo: Dashboard com Gráfico Demo</option>
                            <option value="Dashboard/PriceTable">Modelo: Listagem de Preços Semanais</option>
                            <option value="Dashboard/Contact">Modelo: Página de Contatos</option>
                            <option value="Dashboard/HistoricalData">Modelo: Consulta de Histórico</option>
                            <option value="MyProducts/Index">Modelo: Meus Contratos / Acompanhamento</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Ordem (Posição)</label>
                        <input v-model="pageForm.order" type="number" required class="w-full bg-white border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                    <div class="pt-4 flex items-center gap-3">
                        <button type="submit" :disabled="pageForm.processing" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl text-xs uppercase tracking-widest transition-all text-center shadow-sm shadow-blue-600/20">
                            {{ editingPage ? 'Atualizar' : 'Adicionar' }}
                        </button>
                        <button v-if="editingPage" type="button" @click="cancelPageEdit" class="p-3 bg-red-50 text-red-600 hover:bg-red-100 rounded-xl transition-colors">
                            <XIcon class="w-4 h-4" />
                        </button>
                    </div>
                </form>
            </div>
            <!-- Table -->
            <div class="w-full xl:w-2/3 border border-slate-200 rounded-2xl overflow-hidden shadow-sm flex flex-col max-h-[700px]">
                <!-- Header Tool Bar -->
                <div class="bg-white px-6 py-4 border-b border-slate-100 flex justify-between items-center sticky top-0 z-20">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.15em]">Estrutura de Páginas e Menus</p>
                    <span class="bg-blue-50 text-blue-600 border border-blue-100 py-1 px-3 rounded-full text-[10px] font-bold uppercase tracking-widest">
                        {{ pages.total }} registros encontrados
                    </span>
                </div>
                <div class="overflow-y-auto flex-1 relative">
                    <table class="w-full text-sm text-left text-slate-600">
                        <thead class="text-xs text-slate-500 bg-slate-50/90 backdrop-blur-sm uppercase font-bold border-b border-slate-200 tracking-wider sticky top-0 z-10">
                        <tr>
                            <th class="px-6 py-4">Título</th>
                            <th class="px-6 py-4">Slug</th>
                            <th class="px-6 py-4 text-center">Ordem</th>
                            <th class="px-6 py-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="p in pages.data" :key="p.id" class="hover:bg-slate-50/50 transition-colors">
                            <td class="px-6 py-4 font-bold text-slate-800">{{ p.title }}</td>
                            <td class="px-6 py-4 text-slate-500 font-mono text-xs">{{ p.slug }}</td>
                            <td class="px-6 py-4 text-center font-bold">{{ p.order }}</td>
                            <td class="px-6 py-4 text-right">
                                <button @click="editPage(p)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors inline-block mr-1"><PencilIcon class="w-4 h-4"/></button>
                                <button @click="deletePage(p)" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors inline-block"><TrashIcon class="w-4 h-4"/></button>
                            </td>
                        </tr>
                        <tr v-if="!pages.data?.length">
                            <td colspan="4" class="px-6 py-8 text-center text-slate-400 font-medium">Nenhuma página cadastrada.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

            <!-- Pagination for Pages -->
            <div v-if="pages.links?.length > 3" class="mt-8 flex flex-col sm:flex-row items-center justify-between gap-4 py-4 border-t border-slate-100">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                    Exibindo <span class="font-bold text-blue-600">{{ pages.from }}</span> até <span class="font-bold text-blue-600">{{ pages.to }}</span> de <span class="font-bold text-slate-900">{{ pages.total }}</span> páginas
                </p>
                <div class="flex gap-1">
                    <button v-for="(link, i) in pages.links" :key="i" v-html="formatLabel(link.label)" @click="changePage(link.url)" :disabled="!link.url"
                        :class="['px-3 py-2 text-xs font-bold rounded-lg border transition-all', link.active ? 'bg-blue-600 text-white border-blue-600 shadow-sm shadow-blue-200' : link.url ? 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' : 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed']" />
                </div>
            </div>
        </div>

        <ConfirmationModal
            :show="isConfirmModalOpen"
            title="Excluir Página"
            :message="`Tem certeza que deseja excluir '${pageToDelete?.title}'? Esta ação removerá o acesso a este dashboard para todos os usuários.`"
            confirm-text="Excluir Página"
            @close="isConfirmModalOpen = false"
            @confirm="confirmDeletePage"
        />
    </div>
</template>
