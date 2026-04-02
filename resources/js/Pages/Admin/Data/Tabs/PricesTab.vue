<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PencilIcon, TrashIcon, XIcon, SearchIcon, TrendingUpIcon, PackageIcon, TruckIcon } from 'lucide-vue-next';
import CountryFlag from '@/Components/CountryFlag.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const props = defineProps({
    prices: Object,
    all_suppliers: Array,
    searchableProducts: Array,
    filters: Object,
});

const priceForm = useForm({ id: null, product_id: '', supplier_id: '', date: '', price: '' });
const editingPrice = ref(false);
const isConfirmModalOpen = ref(false);
const priceToDelete = ref(null);

const submitPrice = () => {
    if (editingPrice.value) {
        priceForm.put(route('admin.data.prices.update', priceForm.id), { onSuccess: cancelPriceEdit });
    } else {
        priceForm.post(route('admin.data.prices.store'), { onSuccess: () => priceForm.reset() });
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('pt-BR', { timeZone: 'UTC' });
};

const extractDateOnly = (dateString) => {
    if (!dateString) return '';
    if (typeof dateString === 'string') {
        return dateString.split('T')[0];
    }
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return '';
    return date.toISOString().split('T')[0];
};

const editPrice = (p) => { 
    editingPrice.value = true; 
    priceForm.id = p.id; 
    priceForm.product_id = p.product_id; 
    priceForm.supplier_id = p.supplier_id || ''; 
    priceForm.date = extractDateOnly(p.date); 
    priceForm.price = parseFloat(p.price); 
};

const cancelPriceEdit = () => { 
    editingPrice.value = false; 
    priceForm.reset(); 
};

const deletePrice = (p) => { 
    priceToDelete.value = p;
    isConfirmModalOpen.value = true;
};

const confirmDeletePrice = () => {
    if (!priceToDelete.value) return;
    router.delete(route('admin.data.prices.destroy', priceToDelete.value.id), {
        onSuccess: () => {
            isConfirmModalOpen.value = false;
            priceToDelete.value = null;
        }
    });
};

const changePage = (url) => {
    if (!url) return;
    router.visit(url, { 
        preserveState: true, 
        preserveScroll: true,
        only: ['prices']
    });
};

const formatLabel = (label) => {
    if (label.includes('pagination.previous') || label.includes('Previous')) return '&laquo; Anterior';
    if (label.includes('pagination.next') || label.includes('Next')) return 'Próximo &raquo;';
    return label;
};
</script>

<template>
    <div class="animate-in fade-in zoom-in-95 duration-200">
        <div class="flex flex-col xl:flex-row gap-8">
            <!-- Form -->
            <div class="w-full xl:w-1/3 bg-slate-50 p-6 rounded-2xl border border-slate-100 xl:sticky xl:top-10 self-start shadow-sm shadow-slate-200/50">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-blue-600"></div>
                    {{ editingPrice ? 'Editando Preço' : 'Adicionar Preço' }}
                </h3>
                <form @submit.prevent="submitPrice" class="space-y-4">
                    <SearchableSelect 
                        v-model="priceForm.product_id"
                        :options="searchableProducts"
                        label="Produto"
                        placeholder="Selecione o Produto"
                        :icon="PackageIcon"
                        :with-flag="true"
                        flag-property="country.name"
                    />

                    <SearchableSelect 
                        v-model="priceForm.supplier_id"
                        :options="all_suppliers"
                        label="Fornecedor (Opcional)"
                        placeholder="Selecione o Fornecedor"
                        :icon="TruckIcon"
                    />

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Data</label>
                            <input v-model="priceForm.date" type="date" required class="w-full bg-white border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Preço ($US)</label>
                            <input v-model="priceForm.price" type="number" step="0.01" placeholder="0.00" required class="w-full bg-white border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                    </div>

                    <div class="pt-4 flex items-center gap-3">
                        <button type="submit" :disabled="priceForm.processing" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-black py-3.5 px-4 rounded-xl text-xs uppercase tracking-[0.15em] transition-all text-center shadow-md shadow-blue-200 transform active:scale-[0.98]">
                            {{ editingPrice ? 'Atualizar' : 'Adicionar' }}
                        </button>
                        <button v-if="editingPrice" type="button" @click="cancelPriceEdit" class="p-3 bg-red-50 text-red-600 hover:bg-red-100 rounded-xl transition-colors">
                            <XIcon class="w-4 h-4" />
                        </button>
                    </div>
                </form>
            </div>
            <!-- Table -->
            <div class="w-full xl:w-2/3 space-y-4">
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <SearchIcon class="h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors" />
                    </div>
                    <input 
                        @input="$emit('updateSearch', 'prices_search', $event.target.value)"
                        :value="filters.prices_search"
                        type="text" 
                        placeholder="Buscar por produto, fornecedor ou data..." 
                        class="w-full pl-11 pr-4 py-3 bg-white border-slate-200 border rounded-2xl text-sm focus:border-blue-500 focus:ring-blue-500/20 shadow-sm transition-all"
                    >
                </div>

                <div class="border border-slate-200 rounded-2xl overflow-hidden relative shadow-sm">
                    <!-- Header Tool Bar -->
                    <div class="bg-slate-50 px-6 py-3 border-b border-slate-100 flex justify-between items-center bg-white sticky top-0 z-20">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">Lista de Histórico de Preços</p>
                        <span class="bg-blue-50 text-blue-600 border border-blue-100 py-1 px-3 rounded-full text-[10px] font-black uppercase tracking-widest">
                            {{ prices.total }} registros encontrados
                        </span>
                    </div>
                    <div class="max-h-[600px] overflow-y-auto">
                         <table class="w-full text-lg text-left text-slate-600">
                            <thead class="text-sm text-slate-500 bg-slate-50/90 backdrop-blur-sm uppercase font-black border-b border-slate-200 tracking-wider sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-3">Data</th>
                                    <th class="px-6 py-3">Produto</th>
                                    <th class="px-6 py-3 text-right">Preço</th>
                                    <th class="px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="pr in prices.data" :key="pr.id" class="hover:bg-slate-50/50 transition-colors">
                                    <td class="px-6 py-2 font-mono text-xs font-bold text-slate-700">{{ formatDate(pr.date) }}</td>
                                    <td class="px-6 py-2">
                                        <div class="font-bold text-slate-800">{{ pr.product?.name }}</div>
                                        <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider flex items-center gap-2">
                                            <CountryFlag :name="pr.product?.country?.name || ''" class-name="w-3 h-2 outline-slate-100 outline" />
                                            {{ pr.product?.country?.name }} • <span :class="pr.supplier ? 'text-blue-600' : 'text-slate-300 italic'">{{ pr.supplier?.name || 'S/ Fornecedor' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-2 text-right">
                                        <span class="text-blue-600 bg-blue-50 px-2 py-1 rounded-lg font-black text-xs"> ${{ pr.price }} </span>
                                    </td>
                                    <td class="px-6 py-2 text-right">
                                        <button @click="editPrice(pr)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors inline-block mr-1"><PencilIcon class="w-3.5 h-3.5"/></button>
                                        <button @click="deletePrice(pr)" class="p-1.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors inline-block"><TrashIcon class="w-3.5 h-3.5"/></button>
                                    </td>
                                </tr>
                                <tr v-if="!prices.data?.length">
                                    <td colspan="4" class="px-6 py-8 text-center text-slate-400 font-medium">Nenhum registro de preço lançado.</td>
                                </tr>
                            </tbody>
                         </table>
                    </div>
                </div>
                <!-- Pagination -->
                <div v-if="prices.links?.length > 3" class="flex items-center justify-between px-4 py-3 bg-white border-t border-slate-200 sm:px-6 mt-4 rounded-xl shadow-sm">
                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                        <p class="text-xs text-slate-700 font-bold uppercase tracking-widest">
                            Exibindo <span class="font-black text-blue-600">{{ prices.from }}</span> até <span class="font-black text-blue-600">{{ prices.to }}</span> de <span class="font-black text-slate-900">{{ prices.total }}</span> resultados
                        </p>
                        <div class="flex gap-1">
                            <button v-for="(link, i) in prices.links" :key="i" v-html="formatLabel(link.label)" @click="changePage(link.url)" :disabled="!link.url"
                                :class="['px-3 py-2 text-xs font-black rounded-lg border transition-all', link.active ? 'bg-blue-600 text-white border-blue-600 shadow-sm shadow-blue-200' : link.url ? 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' : 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed']" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmationModal
            :show="isConfirmModalOpen"
            title="Excluir Registro de Preço"
            :message="`Tem certeza que deseja excluir o registro de preço de '${priceToDelete?.product?.name}' no dia ${formatDate(priceToDelete?.date)}? Esta ação não pode ser desfeita.`"
            confirm-text="Excluir Registro"
            @close="isConfirmModalOpen = false"
            @confirm="confirmDeletePrice"
        />
    </div>
</template>
