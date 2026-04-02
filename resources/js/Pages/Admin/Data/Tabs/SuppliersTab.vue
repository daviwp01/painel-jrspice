<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PencilIcon, TrashIcon, XIcon, SearchIcon } from 'lucide-vue-next';
import Pagination from '@/Components/Pagination.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const props = defineProps({
    suppliers: Object,
    filters: Object,
});

const supplierForm = useForm({ id: null, name: '' });
const editingSupplier = ref(false);
const isConfirmModalOpen = ref(false);
const supplierToDelete = ref(null);

const submitSupplier = () => {
    if (editingSupplier.value) {
        supplierForm.put(route('admin.data.suppliers.update', supplierForm.id), { onSuccess: cancelSupplierEdit });
    } else {
        supplierForm.post(route('admin.data.suppliers.store'), { onSuccess: () => supplierForm.reset() });
    }
};

const editSupplier = (s) => { 
    editingSupplier.value = true; 
    supplierForm.id = s.id; 
    supplierForm.name = s.name; 
};

const cancelSupplierEdit = () => { 
    editingSupplier.value = false; 
    supplierForm.reset(); 
};

const deleteSupplier = (s) => { 
    supplierToDelete.value = s;
    isConfirmModalOpen.value = true;
};

const confirmDeleteSupplier = () => {
    if (!supplierToDelete.value) return;
    router.delete(route('admin.data.suppliers.destroy', supplierToDelete.value.id), {
        onSuccess: () => {
            isConfirmModalOpen.value = false;
            supplierToDelete.value = null;
        }
    });
};


</script>

<template>
    <div class="animate-in fade-in zoom-in-95 duration-200">
        <div class="flex flex-col xl:flex-row gap-8">
            <!-- Form -->
            <div class="w-full xl:w-1/3 bg-slate-50 p-6 rounded-2xl border border-slate-100 xl:sticky xl:top-10 self-start shadow-sm shadow-slate-200/50">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-blue-600"></div>
                    {{ editingSupplier ? 'Editando Fornecedor' : 'Adicionar Fornecedor' }}
                </h3>
                <form @submit.prevent="submitSupplier" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nome do Fornecedor</label>
                        <input v-model="supplierForm.name" type="text" placeholder="Nome da Empresa..." required class="w-full bg-white border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                    <div class="pt-4 flex items-center gap-3">
                        <button type="submit" :disabled="supplierForm.processing || !supplierForm.isDirty" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl text-xs uppercase tracking-widest transition-all text-center shadow-sm shadow-blue-600/20 disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none">
                            {{ editingSupplier ? 'Atualizar' : 'Adicionar' }}
                        </button>
                        <button v-if="editingSupplier" type="button" @click="cancelSupplierEdit" class="p-3 bg-red-50 text-red-600 hover:bg-red-100 rounded-xl transition-colors">
                            <XIcon class="w-4 h-4" />
                        </button>
                    </div>
                </form>
            </div>
            <!-- Table -->
            <div class="w-full xl:w-2/3 space-y-4">
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <SearchIcon class="h-4 w-4 text-slate-400 group-focus-within:text-blue-600 transition-colors" />
                    </div>
                    <input 
                        :value="filters.suppliers_search" 
                        @input="$emit('updateSearch', 'suppliers_search', $event.target.value)"
                        type="text" 
                        placeholder="PESQUISAR FORNECEDORES..."
                        class="w-full pl-12 pr-4 py-3.5 bg-white border-slate-200 border rounded-2xl text-[10px] font-bold uppercase tracking-[0.15em] text-slate-600 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50/50 transition-all shadow-sm"
                    >
                </div>

                <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-sm flex flex-col bg-white">
                    <!-- Header Tool Bar -->
                    <div class="bg-white px-6 py-4 border-b border-slate-100 flex justify-between items-center sticky top-0 z-20">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.15em]">Lista de Fornecedores Cadastrados</p>
                        <span class="bg-blue-50 text-blue-600 border border-blue-100 py-1 px-3 rounded-full text-[10px] font-bold uppercase tracking-widest">
                            {{ suppliers.total }} registros encontrados
                        </span>
                    </div>
                    <div class="relative">
                        <table class="w-full text-left text-slate-600">
                            <thead class="text-[10px] text-slate-500 bg-slate-50/90 backdrop-blur-sm uppercase font-bold border-b border-slate-200 tracking-[0.2em] sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-3">Nome do Fornecedor</th>
                                    <th class="px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="s in suppliers.data" :key="s.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                 <td class="px-6 py-3 font-bold text-slate-800 text-sm uppercase tracking-tight">{{ s.name }}</td>
                                 <td class="px-6 py-3 text-right">
                                     <button @click="editSupplier(s)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors inline-block mr-1">
                                         <PencilIcon class="w-3.5 h-3.5"/>
                                     </button>
                                     <button @click="deleteSupplier(s)" class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors inline-block">
                                         <TrashIcon class="w-3.5 h-3.5"/>
                                     </button>
                                 </td>
                             </tr>
                            <tr v-if="!suppliers.data?.length">
                                <td colspan="2" class="px-6 py-8 text-center text-slate-400 font-medium">Nenhum fornecedor cadastrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination Balanced -->
            <div v-if="suppliers.links?.length > 3" class="mt-4 flex flex-col md:flex-row items-center justify-between gap-4 bg-slate-50/50 p-4 rounded-xl border border-slate-100 shadow-sm transition-all duration-300">
                <!-- Info Region Minimalist -->
                <div class="flex items-center gap-3">
                    <div class="w-1 h-6 bg-blue-600 rounded-full"></div>
                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest leading-relaxed">
                        <span class="text-blue-600">{{ suppliers.from }}-{{ suppliers.to }}</span> 
                        <span class="mx-2 text-slate-300">de</span>
                        <span class="text-slate-900">{{ suppliers.total }}</span>
                    </p>
                </div>

                <!-- Navigation Buttons (Global Component) -->
                <Pagination :links="suppliers.links" />
            </div>
        </div>
    </div>
    
    <ConfirmationModal
        :show="isConfirmModalOpen"
        title="Excluir Fornecedor"
        :message="`Tem certeza que deseja excluir '${supplierToDelete?.name}'? Esta ação removerá a associação deste fornecedor de todos os registros de preços.`"
        confirm-text="Excluir Fornecedor"
        @close="isConfirmModalOpen = false"
        @confirm="confirmDeleteSupplier"
    />
    </div>
</template>
