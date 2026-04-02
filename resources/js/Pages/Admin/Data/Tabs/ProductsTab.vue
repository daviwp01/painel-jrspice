<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { PencilIcon, TrashIcon, XIcon, GlobeIcon, SearchIcon, Calendar, SlidersHorizontal, Check, Star, Info } from 'lucide-vue-next';
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

const months = [
    { id: 'Janeiro', name: 'Janeiro' },
    { id: 'Fevereiro', name: 'Fevereiro' },
    { id: 'Março', name: 'Março' },
    { id: 'Abril', name: 'Abril' },
    { id: 'Maio', name: 'Maio' },
    { id: 'Junho', name: 'Junho' },
    { id: 'Julho', name: 'Julho' },
    { id: 'Agosto', name: 'Agosto' },
    { id: 'Setembro', name: 'Setembro' },
    { id: 'Outubro', name: 'Outubro' },
    { id: 'Novembro', name: 'Novembro' },
    { id: 'Dezembro', name: 'Dezembro' },
];

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

// Products filtered by the selected country in the default filter config
const defaultFilterProducts = computed(() => {
    if (!defaultFilterForm.country_id) return [];
    if (!props.all_products) return [];
    return props.all_products.filter(p => p.country_id == defaultFilterForm.country_id);
});

// When country changes in default filter, clear invalid product selections
watch(() => defaultFilterForm.country_id, () => {
    const validIds = defaultFilterProducts.value.map(p => p.id);
    defaultFilterForm.product_ids = defaultFilterForm.product_ids.filter(id => validIds.includes(id));
});

const toggleDefaultProduct = (productId) => {
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
</script>

<template>
    <div class="animate-in fade-in zoom-in-95 duration-200 space-y-8">

        <!-- ===== FILTRO PADRÃO DO DASHBOARD ===== -->
        <div class="bg-gradient-to-br from-blue-50 to-slate-50 border border-blue-100 rounded-2xl p-6 shadow-sm">
            <div class="flex items-start justify-between mb-5">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-blue-600 rounded-xl shadow-md shadow-blue-200">
                        <SlidersHorizontal class="w-4 h-4 text-white" />
                    </div>
                    <div>
                        <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest">Filtro Padrão do Dashboard</h3>
                        <p class="text-[10px] text-slate-500 font-medium mt-0.5">Pré-seleção automática ao abrir o painel pela primeira vez</p>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 bg-blue-100/70 text-blue-700 text-[10px] font-bold px-3 py-1.5 rounded-full uppercase tracking-widest">
                    <Star class="w-3 h-3 fill-blue-500" />
                    Config Global
                </div>
            </div>

            <div class="bg-white/80 rounded-xl border border-blue-100 p-4 mb-5 flex items-start gap-3">
                <Info class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" />
                <p class="text-[11px] text-slate-600 font-medium leading-relaxed">
                    Configure aqui <strong>qual país e produto(s)</strong> serão pré-selecionados nos filtros quando nenhum filtro manual estiver ativo. 
                    Ao clicar nos filtros manualmente, essa config é ignorada para aquela sessão.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- País Padrão -->
                <div>
                    <SearchableSelect 
                        v-model="defaultFilterForm.country_id"
                        :options="all_countries"
                        label="País Padrão"
                        placeholder="Selecione o País"
                        :icon="GlobeIcon"
                        with-flag
                    />
                </div>

                <!-- Info do país selecionado -->
                <div v-if="defaultCountryData" class="flex items-center gap-3 p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <CountryFlag :name="defaultCountryData.name" class-name="w-10 h-7 object-cover rounded shadow-sm" />
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">País selecionado</p>
                        <p class="text-sm font-black text-slate-800 uppercase mt-0.5">{{ defaultCountryData.name }}</p>
                    </div>
                </div>
                <div v-else class="flex items-center gap-3 p-4 bg-slate-50 rounded-xl border border-dashed border-slate-200">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Nenhum país selecionado</p>
                </div>
            </div>

            <!-- Produtos padrão para o país selecionado -->
            <div v-if="defaultFilterForm.country_id" class="mt-5">
                <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <Check class="w-3 h-3" /> Produtos padrão para {{ defaultCountryData?.name }}
                    <span class="text-blue-600">({{ defaultFilterForm.product_ids.length }} selecionados)</span>
                </p>

                <div v-if="defaultFilterProducts.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    <div
                        v-for="product in defaultFilterProducts"
                        :key="product.id"
                        @click="toggleDefaultProduct(product.id)"
                        class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all group select-none"
                        :class="isDefaultProductSelected(product.id)
                            ? 'bg-blue-50 border-blue-200 ring-1 ring-blue-200'
                            : 'bg-white border-slate-100 hover:border-blue-100 hover:bg-slate-50'"
                    >
                        <div
                            class="w-4.5 h-4.5 rounded border-2 flex items-center justify-center shrink-0 transition-all w-5 h-5"
                            :class="isDefaultProductSelected(product.id)
                                ? 'bg-blue-600 border-blue-600'
                                : 'border-slate-300 group-hover:border-blue-400'"
                        >
                            <Check v-if="isDefaultProductSelected(product.id)" class="w-3 h-3 text-white stroke-[3]" />
                        </div>
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-tight group-hover:text-blue-700 transition-colors">{{ product.name }}</span>
                    </div>
                </div>
                <div v-else class="text-[10px] text-slate-400 font-medium p-4 bg-slate-50 rounded-xl text-center">
                    Nenhum produto cadastrado para o país selecionado.
                </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-3 mt-5 pt-5 border-t border-blue-100">
                <button
                    @click="saveDefaultFilters"
                    :disabled="defaultFilterForm.processing"
                    class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-black text-[10px] uppercase tracking-widest px-5 py-3 rounded-xl shadow-md shadow-blue-200 transition-all active:scale-95 disabled:opacity-50"
                >
                    <Check class="w-3.5 h-3.5" />
                    Salvar Filtro Padrão
                </button>
                <button
                    @click="clearDefaultFilters"
                    type="button"
                    class="text-[10px] font-bold text-slate-500 hover:text-slate-700 uppercase tracking-widest px-4 py-3 rounded-xl hover:bg-slate-100 transition-colors"
                >
                    Limpar
                </button>
                <span v-if="defaultFilterForm.recentlySuccessful" class="text-[10px] font-black text-emerald-600 uppercase tracking-widest flex items-center gap-1">
                    <Check class="w-3 h-3" /> Salvo!
                </span>
            </div>
        </div>

        <!-- ===== FORM + TABLE ===== -->
        <div class="flex flex-col xl:flex-row gap-8">
            <!-- Form -->
            <div class="w-full xl:w-1/3 bg-slate-50 p-6 rounded-2xl border border-slate-100 xl:sticky xl:top-10 self-start shadow-sm shadow-slate-200/50">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
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
                        
                        <SearchableSelect 
                           v-model="productForm.harvest_month"
                           :options="months"
                           label="Mês de Safra (Opcional)"
                           placeholder="Selecione o Mês"
                           :icon="CalendarIcon"
                           :searchable="false"
                        />
                    </div>
                    <div class="pt-4 flex items-center gap-3">
                        <button type="submit" :disabled="productForm.processing" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl text-xs uppercase tracking-widest transition-all text-center shadow-sm shadow-blue-600/20">
                            {{ editingProduct ? 'Atualizar' : 'Adicionar' }}
                        </button>
                        <button v-if="editingProduct" type="button" @click="cancelProductEdit" class="p-3 bg-red-50 text-red-600 hover:bg-red-100 rounded-xl transition-colors">
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
                        :value="filters.products_search" 
                        @input="$emit('updateSearch', 'products_search', $event.target.value)"
                        type="text" 
                        placeholder="Buscar produto, país ou safra..." 
                        class="w-full pl-11 pr-4 py-3 bg-white border-slate-200 rounded-2xl text-sm focus:border-blue-500 focus:ring-blue-500/20 shadow-sm transition-all"
                    >
                </div>

                <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-sm flex flex-col max-h-[700px]">
                    <!-- Header Tool Bar -->
                    <div class="bg-white px-6 py-4 border-b border-slate-100 flex justify-between items-center sticky top-0 z-20">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em]">Lista de Produtos Cadastrados</p>
                        <span class="bg-blue-50 text-blue-600 border border-blue-100 py-1 px-3 rounded-full text-[10px] font-black uppercase tracking-widest">
                            {{ products.total }} produtos registrados
                        </span>
                    </div>
                    <div class="overflow-y-auto flex-1 relative">
                        <table class="w-full text-lg text-left text-slate-600">
                            <thead class="text-sm text-slate-500 bg-slate-50/90 backdrop-blur-sm uppercase font-black border-b border-slate-200 tracking-wider sticky top-0 z-10">
                            <tr>
                                <th class="px-6 py-5">Produto</th>
                                <th class="px-6 py-5">País Origem</th>
                                <th class="px-6 py-5">Safra</th>
                                <th class="px-6 py-4 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                             <tr v-for="p in products.data" :key="p.id" class="hover:bg-slate-50/50 transition-colors py-4">
                                <td class="px-6 py-4 font-bold text-slate-800">{{ p.name }}</td>
                                <td class="px-6 py-4 font-medium">
                                    <div class="flex items-center gap-2">
                                        <CountryFlag :name="p.country?.name || ''" class-name="w-4 h-3 object-cover shadow-xs" />
                                        {{ p.country?.name }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-slate-500">{{ p.harvest_month || '--' }}</td>
                                <td class="px-6 py-4 text-right">
                                    <button @click="editProduct(p)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors inline-block mr-1"><PencilIcon class="w-4 h-4"/></button>
                                    <button @click="deleteProduct(p)" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors inline-block"><TrashIcon class="w-4 h-4"/></button>
                                </td>
                            </tr>
                            <tr v-if="!products.data?.length">
                                <td colspan="4" class="px-6 py-8 text-center text-slate-400 font-medium">Nenhum produto cadastrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
                <div class="mt-6 flex justify-between items-center bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        Exibindo <span class="text-blue-600">{{ products.from }}</span> até <span class="text-blue-600">{{ products.to }}</span> de <span class="text-blue-600">{{ products.total }}</span> resultados
                    </p>
                    <Pagination :links="products.links" />
                </div>
        </div>
        <ConfirmationModal
            :show="isConfirmModalOpen"
            title="Excluir Produto"
            :message="`Tem certeza que deseja excluir o produto '${productToDelete?.name}'? Isso apagará todo o histórico e referências vinculadas a ele.`"
            confirm-text="Excluir Permanentemente"
            @close="isConfirmModalOpen = false"
            @confirm="confirmDeleteProduct"
        />
    </div>
</div>
</template>
