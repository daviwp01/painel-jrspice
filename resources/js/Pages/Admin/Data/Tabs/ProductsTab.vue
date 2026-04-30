<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { PencilIcon, TrashIcon, XIcon, GlobeIcon, SearchIcon, Calendar, SlidersHorizontal, Check, Star, Info, Eraser } from 'lucide-vue-next';
import CountryFlag from '@/Components/CountryFlag.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
    products: Object,
    all_countries: Array,
    all_products: Array,
    filters: Object,
    default_filter_config: Object,
});

const CalendarIcon = Calendar;



// --- Product Form ---
const productForm = useForm({ id: null, name: '', country_id: '', harvest_month: '' });
const editingProduct = ref(false);
const isConfirmModalOpen = ref(false);
const productToDelete = ref(null);

const submitProduct = () => {
    if (editingProduct.value) {
        productForm.put(route('admin.data.products.update', productForm.id), { onSuccess: cancelProductEdit });
    } else {
        productForm.post(route('admin.data.products.store'), { onSuccess: () => productForm.reset() });
    }
};

const editProduct = (p) => { 
    editingProduct.value = true; 
    productForm.id = p.id; 
    productForm.name = p.name; 
    productForm.country_id = p.country_id; 
    productForm.harvest_month = p.harvest_month || ''; 
};

const cancelProductEdit = () => { 
    editingProduct.value = false; 
    productForm.reset(); 
};

const deleteProduct = (p) => { 
    productToDelete.value = p;
    isConfirmModalOpen.value = true;
};

const confirmDeleteProduct = () => {
    if (!productToDelete.value) return;
    router.delete(route('admin.data.products.destroy', productToDelete.value.id), {
        onSuccess: () => {
            isConfirmModalOpen.value = false;
            productToDelete.value = null;
        }
    });
};

const changePage = (url) => {
    if (!url) return;
    router.visit(url, { 
        preserveState: true, 
        preserveScroll: true,
        only: ['products']
    });
};

const formatLabel = (label) => {
    if (!label) return '';
    const l = label.toLowerCase();
    if (l.includes('previous')) return '&laquo; Anterior';
    if (l.includes('next')) return 'Próximo &raquo;';
    return label;
};

// --- Default Filter Config ---
const defaultFilterForm = useForm({
    country_id: props.default_filter_config?.country_id || '',
    product_ids: props.default_filter_config?.product_ids || [],
});

const defaultProductSearch = ref('');

const normalize = (str) =>
    str.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();

// Products filtered by the selected country in the default filter config
const defaultFilterProducts = computed(() => {
    if (!defaultFilterForm.country_id) return [];
    if (!props.all_products) return [];
    
    let list = props.all_products.filter(p => p.country_id == defaultFilterForm.country_id);
    
    if (defaultProductSearch.value) {
        const query = normalize(defaultProductSearch.value);
        list = list.filter(p => normalize(p.name).includes(query));
    }
    
    return list;
});

// Reset search when country changes
watch(() => defaultFilterForm.country_id, () => {
    defaultProductSearch.value = '';
    const validIds = defaultFilterProducts.value.map(p => p.id);
    defaultFilterForm.product_ids = defaultFilterForm.product_ids.filter(id => validIds.includes(id));
});

const toggleDefaultProduct = (productId) => {
    // Single select as requested
    if (defaultFilterForm.product_ids.includes(productId)) {
        defaultFilterForm.product_ids = [];
    } else {
        defaultFilterForm.product_ids = [productId];
    }
};

const isDefaultProductSelected = (productId) => defaultFilterForm.product_ids.includes(productId);

const saveDefaultFilters = () => {
    defaultFilterForm.post(route('admin.data.default-filters.save'));
};

const clearDefaultFilters = () => {
    defaultFilterForm.country_id = '';
    defaultFilterForm.product_ids = [];
};

const defaultCountryData = computed(() =>
    props.all_countries?.find(c => c.id == defaultFilterForm.country_id)
);
import { toast } from '@/Stores/ToastStore';

const showClearHarvestModal = ref(false);
const clearingHarvests = ref(false);

const clearAllHarvests = () => {
    clearingHarvests.value = true;
    router.post(route('admin.data.products.clear-harvests'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showClearHarvestModal.value = false;
            clearingHarvests.value = false;
            toast.add('Todas as safras foram limpas com sucesso!', 'success');
        },
        onError: () => {
            clearingHarvests.value = false;
            toast.add('Erro ao limpar safras.', 'error');
        }
    });
};
</script>

<template>
    <div class="animate-in fade-in zoom-in-95 duration-200">
        <div class="flex flex-col xl:flex-row gap-8">
            
            <!-- LEFT COLUMN: FORMS & CONFIGS (STICKY) -->
            <div class="w-full xl:w-1/3 space-y-8 xl:sticky xl:top-4 self-start">
                
                <!-- 1. Form de Adição -->
                <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 shadow-sm shadow-slate-200/50">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-blue-600"></div>
                        {{ editingProduct ? 'Editando Produto' : 'Adicionar Produto' }}
                    </h3>
                    <form @submit.prevent="submitProduct" class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nome do Produto</label>
                            <input v-model="productForm.name" type="text" placeholder="Ex: Alho em Pó A" required class="w-full bg-white border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div class="space-y-4">
                            <SearchableSelect 
                                v-model="productForm.country_id"
                                :options="all_countries"
                                label="Origem (País)"
                                placeholder="Selecione a Origem"
                                :icon="GlobeIcon"
                                with-flag
                            />
                            
                            <div class="space-y-1">
                                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Safra (Opcional)</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <CalendarIcon class="h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors" />
                                    </div>
                                    <input 
                                        v-model="productForm.harvest_month"
                                        @input="productForm.harvest_month = $event.target.value.toUpperCase()"
                                        type="text"
                                        placeholder="Ex: JULHO"
                                        class="w-full pl-11 bg-white border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm uppercase font-bold text-slate-700"
                                    >
                                </div>
                            </div>
                        </div>
                        <div class="pt-4 flex items-center gap-2">
                            <button type="submit" :disabled="productForm.processing || !productForm.isDirty" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 px-4 rounded-xl text-xs uppercase tracking-widest transition-all text-center shadow-md shadow-blue-600/20 disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none">
                                {{ editingProduct ? 'Atualizar' : 'Adicionar' }}
                            </button>
                            <button v-if="editingProduct" type="button" @click="cancelProductEdit" class="p-3 bg-red-50 text-red-600 hover:bg-red-100 rounded-xl transition-colors">
                                <XIcon class="w-4 h-4" />
                            </button>
                        </div>
                    </form>
                </div>

                <!-- 2. Filtro Padrão do Dashboard -->
                <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 shadow-sm shadow-slate-200/50">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="p-2 bg-blue-600 rounded-lg">
                            <SlidersHorizontal class="w-3.5 h-3.5 text-white" />
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-widest">Filtro Padrão</h3>
                            <p class="text-[9px] text-slate-500 font-medium mt-0.5">Configuração fixa inicial do painel</p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <!-- País Padrão -->
                        <SearchableSelect 
                            v-model="defaultFilterForm.country_id"
                            :options="all_countries"
                            label="País de Abertura"
                            placeholder="Selecione o País"
                            :icon="GlobeIcon"
                            with-flag
                        />

                        <!-- Produtos para o país selecionado (Lista compacta) -->
                        <div v-if="defaultFilterForm.country_id" class="space-y-3">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1 flex items-center justify-between">
                                Produto Principal
                                <span v-if="defaultFilterForm.product_ids.length" class="text-blue-600">(1 Selecionado)</span>
                            </label>

                            <!-- Mini Search -->
                            <div class="relative">
                                <SearchIcon class="absolute left-3 top-1/2 -translate-y-1/2 w-3 h-3 text-slate-400" />
                                <input 
                                    v-model="defaultProductSearch"
                                    type="text" 
                                    placeholder="Localizar produto..." 
                                    class="w-full pl-8 pr-3 py-2 bg-white border border-slate-200 rounded-lg text-[11px] font-bold focus:border-blue-500 focus:ring-0 shadow-xs"
                                >
                            </div>
                            
                            <div class="max-h-[300px] overflow-y-auto pr-1 space-y-1.5 custom-scrollbar">
                                <div
                                    v-for="product in defaultFilterProducts"
                                    :key="product.id"
                                    @click="toggleDefaultProduct(product.id)"
                                    class="flex items-center justify-between p-3 rounded-xl border cursor-pointer transition-all group select-none shadow-xs"
                                    :class="isDefaultProductSelected(product.id)
                                        ? 'bg-blue-600 border-blue-600 text-white'
                                        : 'bg-white border-slate-200 hover:border-blue-400 text-slate-600'"
                                >
                                    <span class="text-xs font-bold uppercase truncate">{{ product.name }}</span>
                                    <div 
                                        class="w-4.5 h-4.5 rounded-full border flex items-center justify-center shrink-0"
                                        :class="isDefaultProductSelected(product.id) ? 'bg-white border-white' : 'border-slate-300'"
                                    >
                                        <Check v-if="isDefaultProductSelected(product.id)" class="w-3 h-3 text-blue-600 stroke-[4]" />
                                    </div>
                                </div>
                                <div v-if="!defaultFilterProducts.length" class="text-[10px] text-slate-400 font-medium py-6 text-center italic">
                                    Sem produtos para este país.
                                </div>
                            </div>
                        </div>

                        <!-- Botão de Salvar -->
                        <div class="pt-5 border-t border-slate-200 flex flex-col gap-3">
                            <button
                                @click="saveDefaultFilters"
                                :disabled="defaultFilterForm.processing || !defaultFilterForm.isDirty"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs uppercase tracking-widest px-4 py-3.5 rounded-xl shadow-md shadow-blue-600/20 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none"
                            >
                                <Check v-if="!defaultFilterForm.processing" class="w-3.5 h-3.5 inline mr-1" />
                                <span v-else class="animate-spin mr-2">...</span>
                                Salvar Filtro Padrão
                            </button>
                            
                            <div class="flex items-center justify-between px-1">
                                <button @click="clearDefaultFilters" type="button" class="text-[10px] font-bold text-slate-400 hover:text-red-500 uppercase tracking-widest transition-colors">
                                    Limpar
                                </button>
                                <span v-if="defaultFilterForm.recentlySuccessful" class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest flex items-center gap-1">
                                    <Check class="w-3 h-3" /> Salvo!
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: TABLE -->
            <div class="flex-1 space-y-4">
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <SearchIcon class="h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors" />
                    </div>
                    <input 
                        :value="filters.products_search" 
                        @input="$emit('updateSearch', 'products_search', $event.target.value)"
                        type="text" 
                        placeholder="BUSCAR PRODUTO..." 
                        class="w-full pl-12 pr-32 py-3.5 bg-white border-slate-200 border rounded-2xl text-[10px] font-bold uppercase tracking-[0.15em] text-slate-600 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50/50 transition-all shadow-sm"
                    >
                    <button 
                        @click="showClearHarvestModal = true"
                        type="button"
                        class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 hover:bg-red-50 text-slate-500 hover:text-red-600 rounded-lg text-[10px] font-bold uppercase tracking-widest transition-all border border-transparent hover:border-red-100 shadow-xs"
                        title="Limpar todas as safras do sistema"
                    >
                        <Eraser class="w-3 h-3" />
                        Limpar Safras
                    </button>
                </div>

                <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-sm flex flex-col bg-white">
                    <!-- Header Tool Bar -->
                    <div class="bg-white px-6 py-5 border-b border-slate-100 flex justify-between items-center sticky top-0 z-20">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-1">Inventário Global</p>
                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-tight">Produtos Cadastrados</h2>
                        </div>
                        <span class="bg-blue-50 text-blue-600 border border-blue-100 py-1.5 px-4 rounded-full text-[10px] font-bold uppercase tracking-widest">
                            {{ products.total }} produtos
                        </span>
                    </div>

                    <div class="overflow-x-auto relative">
                        <table class="w-full text-left border-collapse">
                            <thead class="text-[10px] text-slate-400 bg-slate-50/50 uppercase font-bold border-b border-slate-100 tracking-[0.2em]">
                                <tr>
                                    <th class="px-6 py-3">Produto</th>
                                    <th class="px-6 py-3">Origem</th>
                                    <th class="px-6 py-3">Safra</th>
                                    <th class="px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="p in products.data" :key="p.id" class="hover:bg-slate-50/80 transition-colors group border-b border-slate-50 last:border-0">
                                    <td class="px-6 py-3">
                                        <span class="font-bold text-slate-800 uppercase text-sm tracking-tight">{{ p.name }}</span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <CountryFlag :name="p.country?.name || ''" class-name="w-6 h-4 object-cover rounded-[2px] shadow-sm border border-slate-100" />
                                            <span class="text-xs font-bold text-slate-700 uppercase tracking-widest">{{ p.country?.name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ p.harvest_month || '--' }}</span>
                                    </td>
                                    <td class="px-6 py-3 text-right space-x-1">
                                        <button @click="editProduct(p)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                            <PencilIcon class="w-3.5 h-3.5"/>
                                        </button>
                                        <button @click="deleteProduct(p)" class="p-1.5 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                                            <TrashIcon class="w-3.5 h-3.5"/>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!products.data?.length">
                                    <td colspan="4" class="px-6 py-12 text-center text-slate-400 font-medium uppercase text-[10px] tracking-widest italic">Nenhum produto cadastrado com este filtro.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Balanced -->
                    <div v-if="products.links?.length > 3" class="bg-slate-50/20 px-6 py-5 border-t border-slate-100 flex flex-col md:flex-row items-center justify-between gap-4 transition-all duration-300">
                        <!-- Info Region Minimalist -->
                        <div class="flex items-center gap-3">
                            <div class="w-1 h-6 bg-blue-600 rounded-full"></div>
                            <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest leading-relaxed">
                                <span class="text-blue-600">{{ products.from }}-{{ products.to }}</span> 
                                <span class="mx-2 text-slate-300">de</span>
                                <span class="text-slate-900">{{ products.total }}</span>
                            </p>
                        </div>

                        <!-- Navigation Buttons (Global Component) -->
                        <Pagination :links="products.links" />
                    </div>
                </div>
            </div>
        </div>

        <ConfirmationModal
            :show="isConfirmModalOpen"
            title="Remover Produto"
            :message="`Deseja realmente excluir '${productToDelete?.name}'? Esta ação removerá o histórico de preços e outras referências.`"
            confirm-text="Confirmar Exclusão"
            @close="isConfirmModalOpen = false"
            @confirm="confirmDeleteProduct"
        />

        <!-- Modal de Confirmação para Limpar Safras -->
        <ConfirmationModal
            :show="showClearHarvestModal"
            title="Limpar Todas as Safras?"
            message="ATENÇÃO: Isso irá zerar a SAFRA de TODOS os produtos cadastrados no sistema. Esta ação não pode ser desfeita. Deseja continuar?"
            confirm-text="Sim, Limpar Tudo"
            cancel-text="Cancelar"
            :loading="clearingHarvests"
            @close="showClearHarvestModal = false"
            @confirm="clearAllHarvests"
        />
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}
</style>
