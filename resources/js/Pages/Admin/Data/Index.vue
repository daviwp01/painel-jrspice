<script setup>
import { ref, computed } from 'vue';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { PlusIcon, SaveIcon, LayoutDashboardIcon, GlobeIcon, PackageIcon, TrendingUpIcon, PencilIcon, TrashIcon, XIcon, TruckIcon, SearchIcon, FilterIcon, FileUpIcon, UploadCloudIcon, CheckCircleIcon, AlertCircleIcon, Loader2Icon } from 'lucide-vue-next';
import CountryFlag from '@/Components/CountryFlag.vue';

const props = defineProps({
    pages: Object,
    countries: Object,
    products: Object,
    suppliers: Object,
    prices: Object,
});

const activeTab = ref('pages');

// Forms
const pageForm = useForm({ id: null, title: '', component: 'Dashboard/Show', order: 0 });
const countryForm = useForm({ id: null, name: '' });
const supplierForm = useForm({ id: null, name: '' });
const productForm = useForm({ id: null, name: '', country_id: '', harvest_month: '' });
const priceForm = useForm({ id: null, product_id: '', supplier_id: '', date: '', min_price: '', max_price: '', average_price: '' });

// Search Filters
const filters = ref({
    countries: '',
    products: '',
    suppliers: '',
    prices: '',
});

const filteredCountries = computed(() => props.countries.data || []);
const filteredProducts = computed(() => props.products.data || []);
const filteredSuppliers = computed(() => props.suppliers.data || []);
const filteredPrices = computed(() => props.prices.data || []);

const changePage = (url) => {
    if (!url) return;
    router.visit(url, { 
        preserveState: true, 
        preserveScroll: true,
        only: [activeTab.value]
    });
};

const formatLabel = (label) => {
    if (label.includes('pagination.previous') || label.includes('Previous')) return '&laquo; Anterior';
    if (label.includes('pagination.next') || label.includes('Next')) return 'Próximo &raquo;';
    return label;
};

// States
const editingPage = ref(false);
const editingCountry = ref(false);
const editingSupplier = ref(false);
const editingProduct = ref(false);
const editingPrice = ref(false);

// --- PAGES ---
const submitPage = () => {
    if (editingPage.value) {
        pageForm.put(route('admin.data.pages.update', pageForm.id), { onSuccess: cancelPageEdit });
    } else {
        pageForm.post(route('admin.data.pages.store'), { onSuccess: () => pageForm.reset() });
    }
};
const editPage = (p) => { editingPage.value = true; pageForm.id = p.id; pageForm.title = p.title; pageForm.component = p.component; pageForm.order = p.order; };
const cancelPageEdit = () => { editingPage.value = false; pageForm.reset(); };
const deletePage = (p) => { if(confirm('Tem certeza que deseja deletar esta página?')) router.delete(route('admin.data.pages.destroy', p.id)); };

// --- COUNTRIES ---
const submitCountry = () => {
    if (editingCountry.value) {
        countryForm.put(route('admin.data.countries.update', countryForm.id), { onSuccess: cancelCountryEdit });
    } else {
        countryForm.post(route('admin.data.countries.store'), { onSuccess: () => countryForm.reset() });
    }
};
const editCountry = (c) => { editingCountry.value = true; countryForm.id = c.id; countryForm.name = c.name; };
const cancelCountryEdit = () => { editingCountry.value = false; countryForm.reset(); };
const deleteCountry = (c) => { if(confirm('Tem certeza em deletar este país? Ele pode estar associado a produtos.')) router.delete(route('admin.data.countries.destroy', c.id)); };

// --- SUPPLIERS ---
const submitSupplier = () => {
    if (editingSupplier.value) {
        supplierForm.put(route('admin.data.suppliers.update', supplierForm.id), { onSuccess: cancelSupplierEdit });
    } else {
        supplierForm.post(route('admin.data.suppliers.store'), { onSuccess: () => supplierForm.reset() });
    }
};
const editSupplier = (s) => { editingSupplier.value = true; supplierForm.id = s.id; supplierForm.name = s.name; };
const cancelSupplierEdit = () => { editingSupplier.value = false; supplierForm.reset(); };
const deleteSupplier = (s) => { if(confirm('Remover fornecedor? Isso pode afetar históricos vinculados.')) router.delete(route('admin.data.suppliers.destroy', s.id)); };

// --- PRODUCTS ---
const submitProduct = () => {
    if (editingProduct.value) {
        productForm.put(route('admin.data.products.update', productForm.id), { onSuccess: cancelProductEdit });
    } else {
        productForm.post(route('admin.data.products.store'), { onSuccess: () => productForm.reset() });
    }
};
const editProduct = (p) => { editingProduct.value = true; productForm.id = p.id; productForm.name = p.name; productForm.country_id = p.country_id; productForm.harvest_month = p.harvest_month || ''; };
const cancelProductEdit = () => { editingProduct.value = false; productForm.reset(); };
const deleteProduct = (p) => { if(confirm('Deletar produto e apagar todo o seu histórico e referências?')) router.delete(route('admin.data.products.destroy', p.id)); };

// --- PRICES ---
const submitPrice = () => {
    if (editingPrice.value) {
        priceForm.put(route('admin.data.prices.update', priceForm.id), { onSuccess: cancelPriceEdit });
    } else {
        priceForm.post(route('admin.data.prices.store'), { onSuccess: () => priceForm.reset() });
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    // Formata o UTC timestamp '2026-03-30T00:00:00.000000Z' para '30/03/2026'
    return new Date(dateString).toLocaleDateString('pt-BR', { timeZone: 'UTC' });
};

const extractDateOnly = (dateString) => {
    if (!dateString) return '';
    // Extrai apenas YYYY-MM-DD para o input=date
    return dateString.split('T')[0];
};

const editPrice = (p) => { 
    editingPrice.value = true; 
    priceForm.id = p.id; 
    priceForm.product_id = p.product_id; 
    priceForm.supplier_id = p.supplier_id || ''; 
    priceForm.date = extractDateOnly(p.date); 
    priceForm.min_price = p.min_price; 
    priceForm.max_price = p.max_price; 
    priceForm.average_price = p.average_price || ''; 
};
const cancelPriceEdit = () => { editingPrice.value = false; priceForm.reset(); };
const deletePrice = (p) => { if(confirm('Remover este preço do histórico?')) router.delete(route('admin.data.prices.destroy', p.id)); };

// --- IMPORTATION ---
const importFile = ref(null);
const isImporting = ref(false);
const importProgress = ref(null);
const importError = ref(null);
const importSuccess = ref(false);
let progressInterval = null;

const handleFileChange = (e) => {
    importFile.value = e.target.files[0];
};

const startImport = async () => {
    if (!importFile.value) return;

    isImporting.value = true;
    importError.value = null;
    importSuccess.value = false;
    importProgress.value = { percentage: 0, status: 'queued' };

    const formData = new FormData();
    formData.append('file', importFile.value);

    try {
        const response = await axios.post(route('admin.data.import'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const jobId = response.data.jobId;
        pollProgress(jobId);
    } catch (err) {
        isImporting.value = false;
        importError.value = err.response?.data?.message || 'Erro ao iniciar importação.';
    }
};

const pollProgress = (jobId) => {
    progressInterval = setInterval(async () => {
        try {
            const response = await axios.get(route('admin.data.import-status', jobId));
            importProgress.value = response.data;

            if (response.data.status === 'completed') {
                clearInterval(progressInterval);
                isImporting.value = false;
                importSuccess.value = true;
                // Recarregar dados após 2 segundos
                setTimeout(() => {
                    router.reload();
                    importSuccess.value = false;
                    importProgress.value = null;
                }, 3000);
            } else if (response.data.status === 'failed') {
                clearInterval(progressInterval);
                isImporting.value = false;
                importError.value = response.data.error || 'Erro no processamento.';
            }
        } catch (err) {
            console.error('Error polling:', err);
        }
    }, 1500);
};
</script>

<template>
    <Head title="Gerenciamento de Dados Analíticos" />

    <DashboardLayout>
        <template #header>
            <h2 class="hidden md:block text-xs font-bold text-slate-400 uppercase tracking-widest">Base de Dados e Configurações</h2>
        </template>

        <div class="h-full bg-slate-50/50 pt-8 pb-16 px-4 sm:px-6 lg:px-8">
            <div class="w-full max-w-none">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Gerenciamento Analítico</h1>
                        <p class="text-sm text-slate-500 mt-1">Configure as origens de dados, cadastre países, produtos e tabelas de histórico.</p>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="bg-white rounded-t-2xl border-b border-slate-200 px-2 sm:px-6 shadow-sm overflow-hidden flex flex-nowrap space-x-1 overflow-x-auto">
                    <button @click="activeTab = 'pages'" :class="activeTab === 'pages' ? 'border-indigo-500 text-indigo-600 bg-indigo-50/30' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'" class="px-4 py-4 border-b-[3px] font-bold text-[11px] uppercase tracking-widest transition-all flex items-center gap-2 whitespace-nowrap">
                        <LayoutDashboardIcon class="w-4 h-4" /> Páginas / Menu
                    </button>
                    <button @click="activeTab = 'countries'" :class="activeTab === 'countries' ? 'border-emerald-500 text-emerald-600 bg-emerald-50/30' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'" class="px-4 py-4 border-b-[3px] font-bold text-[11px] uppercase tracking-widest transition-all flex items-center gap-2 whitespace-nowrap">
                        <GlobeIcon class="w-4 h-4" /> Países
                    </button>
                    <button @click="activeTab = 'products'" :class="activeTab === 'products' ? 'border-orange-500 text-orange-600 bg-orange-50/30' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'" class="px-4 py-4 border-b-[3px] font-bold text-[11px] uppercase tracking-widest transition-all flex items-center gap-2 whitespace-nowrap">
                        <PackageIcon class="w-4 h-4" /> Produtos
                    </button>
                    <button @click="activeTab = 'suppliers'" :class="activeTab === 'suppliers' ? 'border-amber-600 text-amber-700 bg-amber-50/30' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'" class="px-4 py-4 border-b-[3px] font-bold text-[11px] uppercase tracking-widest transition-all flex items-center gap-2 whitespace-nowrap">
                        <TruckIcon class="w-4 h-4" /> Fornecedores
                    </button>
                    <button @click="activeTab = 'prices'" :class="activeTab === 'prices' ? 'border-blue-500 text-blue-600 bg-blue-50/30' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'" class="px-4 py-4 border-b-[3px] font-bold text-[11px] uppercase tracking-widest transition-all flex items-center gap-2 whitespace-nowrap">
                        <TrendingUpIcon class="w-4 h-4" /> Histórico Preços
                    </button>
                    <button @click="activeTab = 'import'" :class="activeTab === 'import' ? 'border-indigo-600 text-indigo-700 bg-indigo-50/30' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'" class="px-4 py-4 border-b-[3px] font-bold text-[11px] uppercase tracking-widest transition-all flex items-center gap-2 whitespace-nowrap">
                        <FileUpIcon class="w-4 h-4" /> Importar Planilha
                    </button>
                </div>

                <!-- Tab Content -->
                <div class="bg-white p-6 sm:p-8 shadow-sm rounded-b-2xl border-x border-b border-slate-200">
                    
                    <!-- TAB: PAGES -->
                    <div v-show="activeTab === 'pages'" class="animate-in fade-in zoom-in-95 duration-200">
                        <div class="flex flex-col xl:flex-row gap-8">
                            <!-- Form -->
                            <div class="w-full xl:w-1/3 bg-slate-50 p-6 rounded-2xl border border-slate-100 self-start">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full" :class="editingPage ? 'bg-amber-500' : 'bg-indigo-500'"></div>
                                    {{ editingPage ? 'Editando Página' : 'Adicionar Página' }}
                                </h3>
                                <form @submit.prevent="submitPage" class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Título</label>
                                        <input v-model="pageForm.title" type="text" placeholder="Nome do menu..." required class="w-full border-slate-200 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Modelo da Página (Componente)</label>
                                        <select v-model="pageForm.component" required class="w-full border-slate-200 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="Dashboard/Show">Painel Gráfico Completo (Gráficos + Filtros)</option>
                                            <option value="Dashboard/PriceTable">Tabela de Preços (Lista Geral P/País)</option>
                                            <option value="Dashboard/HistoricalData">Dados Históricos (Registro Completo)</option>
                                            <option value="Dashboard/Contact">Página de Contato (Telefones, E-mail etc)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Ordem (Posição)</label>
                                        <input v-model="pageForm.order" type="number" required class="w-full border-slate-200 rounded-xl shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                    <div class="pt-4 flex items-center gap-3">
                                        <button type="submit" :disabled="pageForm.processing" class="flex-1 bg-[#0f172a] hover:bg-slate-800 text-white font-bold py-3 px-4 rounded-xl text-xs uppercase tracking-widest transition-all text-center">
                                            {{ editingPage ? 'Atualizar' : 'Adicionar' }}
                                        </button>
                                        <button v-if="editingPage" type="button" @click="cancelPageEdit" class="p-3 bg-red-50 text-red-600 hover:bg-red-100 rounded-xl transition-colors">
                                            <XIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <!-- Table -->
                            <div class="w-full xl:w-2/3 border border-slate-200 rounded-2xl overflow-hidden">
                                <table class="w-full text-sm text-left text-slate-600">
                                    <thead class="text-xs text-slate-500 bg-slate-50 uppercase font-black border-b border-slate-200 tracking-wider">
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
                                                <button @click="editPage(p)" class="p-2 text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors inline-block mr-1"><PencilIcon class="w-4 h-4"/></button>
                                                <button @click="deletePage(p)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors inline-block"><TrashIcon class="w-4 h-4"/></button>
                                            </td>
                                        </tr>
                                        <tr v-if="!pages.data?.length">
                                            <td colspan="4" class="px-6 py-8 text-center text-slate-400 font-medium">Nenhuma página cadastrada.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination for Pages -->
                            <div v-if="pages.links?.length > 3" class="mt-8 flex flex-col sm:flex-row items-center justify-between gap-4 py-4 border-t border-slate-100">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                                    Exibindo <span class="font-black text-indigo-600">{{ pages.from }}</span> até <span class="font-black text-indigo-600">{{ pages.to }}</span> de <span class="font-black text-slate-900">{{ pages.total }}</span> páginas
                                </p>
                                <div class="flex gap-1">
                                    <button v-for="(link, i) in pages.links" :key="i" v-html="formatLabel(link.label)" @click="changePage(link.url)" :disabled="!link.url"
                                        :class="['px-3 py-2 text-xs font-black rounded-lg border transition-all', link.active ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm shadow-indigo-200' : link.url ? 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' : 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed']" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: COUNTRIES -->
                    <div v-show="activeTab === 'countries'" class="animate-in fade-in zoom-in-95 duration-200">
                        <div class="flex flex-col xl:flex-row gap-8">
                            <!-- Form -->
                            <div class="w-full xl:w-1/3 bg-slate-50 p-6 rounded-2xl border border-slate-100 self-start">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full" :class="editingCountry ? 'bg-amber-500' : 'bg-emerald-500'"></div>
                                    <div class="flex items-center gap-2">
                                        {{ editingCountry ? 'Editando' : 'Adicionar' }}
                                        <div v-if="countryForm.name" class="inline-flex items-center gap-2 text-slate-900 lowercase first-letter:uppercase">
                                            <CountryFlag :name="countryForm.name" class-name="w-4 h-3 shadow-sm" />
                                            {{ countryForm.name }}
                                        </div>
                                        <span v-else>País</span>
                                    </div>
                                </h3>
                                <form @submit.prevent="submitCountry" class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nome do País</label>
                                        <input v-model="countryForm.name" type="text" placeholder="Ex: China, Índia..." required class="w-full border-slate-200 rounded-xl shadow-sm focus:border-emerald-500 focus:ring-emerald-500 sm:text-sm">
                                    </div>
                                    <div class="pt-4 flex items-center gap-3">
                                        <button type="submit" :disabled="countryForm.processing" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-xl text-xs uppercase tracking-widest transition-all text-center shadow-sm shadow-emerald-600/20">
                                            {{ editingCountry ? 'Atualizar' : 'Adicionar' }}
                                        </button>
                                        <button v-if="editingCountry" type="button" @click="cancelCountryEdit" class="p-3 bg-red-50 text-red-600 hover:bg-red-100 rounded-xl transition-colors">
                                            <XIcon class="w-4 h-4" />
                                        </button>
                                    </div>
                                </form>
                            </div>
                            <!-- Table -->
                            <div class="w-full xl:w-2/3 space-y-4">
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                        <SearchIcon class="h-4 w-4 text-slate-400 group-focus-within:text-emerald-500 transition-colors" />
                                    </div>
                                    <input 
                                        v-model="filters.countries" 
                                        type="text" 
                                        placeholder="Buscar país por nome..." 
                                        class="w-full pl-11 pr-4 py-3 bg-white border-slate-200 rounded-2xl text-sm focus:border-emerald-500 focus:ring-emerald-500/20 shadow-sm transition-all"
                                    >
                                </div>

                                <div class="border border-slate-200 rounded-2xl overflow-hidden">
                                    <table class="w-full text-sm text-left text-slate-600">
                                        <thead class="text-xs text-slate-500 bg-slate-50 uppercase font-black border-b border-slate-200 tracking-wider">
                                            <tr>
                                                <th class="px-6 py-4">Nome do País</th>
                                                <th class="px-6 py-4 text-center">Produtos Cadastrados</th>
                                                <th class="px-6 py-4 text-right">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <tr v-for="c in filteredCountries" :key="c.id" class="hover:bg-slate-50/50 transition-colors">
                                                <td class="px-6 py-4 font-bold text-slate-800">
                                                    <div class="flex items-center gap-3">
                                                        <CountryFlag :name="c.name" class-name="w-6 h-4 object-cover border border-slate-100" />
                                                        {{ c.name }}
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <span class="bg-slate-100 text-slate-600 py-1 px-3 rounded-full text-xs font-bold">{{ c.products?.length || 0 }}</span>
                                                </td>
                                                <td class="px-6 py-4 text-right">
                                                    <button @click="editCountry(c)" class="p-2 text-emerald-600 hover:bg-emerald-50 rounded-lg transition-colors inline-block mr-1"><PencilIcon class="w-4 h-4"/></button>
                                                    <button @click="deleteCountry(c)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors inline-block"><TrashIcon class="w-4 h-4"/></button>
                                                </td>
                                            </tr>
                                            <tr v-if="!countries.data?.length">
                                                <td colspan="3" class="px-6 py-8 text-center text-slate-400 font-medium">Nenhum país cadastrado.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Pagination -->
                                <div v-if="countries.links?.length > 3" class="flex items-center justify-between px-4 py-3 bg-white border-t border-slate-200 sm:px-6 mt-4 rounded-xl shadow-sm">
                                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                        <p class="text-xs text-slate-700 font-bold uppercase tracking-widest">
                                            Exibindo <span class="font-black text-emerald-600">{{ countries.from }}</span> até <span class="font-black text-emerald-600">{{ countries.to }}</span> de <span class="font-black text-slate-900">{{ countries.total }}</span> resultados
                                        </p>
                                        <div class="flex gap-1">
                                            <button v-for="(link, i) in countries.links" :key="i" v-html="formatLabel(link.label)" @click="changePage(link.url)" :disabled="!link.url"
                                                :class="['px-3 py-2 text-xs font-black rounded-lg border transition-all', link.active ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-200' : link.url ? 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' : 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed']" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB: SUPPLIERS -->
                    <div v-show="activeTab === 'suppliers'" class="animate-in fade-in zoom-in-95 duration-200">
                        <div class="flex flex-col xl:flex-row gap-8">
                            <!-- Form -->
                            <div class="w-full xl:w-1/3 bg-slate-50 p-6 rounded-2xl border border-slate-100 self-start">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-amber-500"></div>
                                    {{ editingSupplier ? 'Editando Fornecedor' : 'Adicionar Fornecedor' }}
                                </h3>
                                <form @submit.prevent="submitSupplier" class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nome do Fornecedor</label>
                                        <input v-model="supplierForm.name" type="text" placeholder="Nome da Empresa..." required class="w-full border-slate-200 rounded-xl shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm">
                                    </div>
                                    <div class="pt-4 flex items-center gap-3">
                                        <button type="submit" :disabled="supplierForm.processing" class="flex-1 bg-amber-600 hover:bg-amber-700 text-white font-bold py-3 px-4 rounded-xl text-xs uppercase tracking-widest transition-all text-center shadow-sm shadow-amber-600/20">
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
                                        <SearchIcon class="h-4 w-4 text-slate-400 group-focus-within:text-amber-500 transition-colors" />
                                    </div>
                                    <input 
                                        v-model="filters.suppliers" 
                                        type="text" 
                                        placeholder="Buscar fornecedor por nome..." 
                                        class="w-full pl-11 pr-4 py-3 bg-white border-slate-200 rounded-2xl text-sm focus:border-amber-500 focus:ring-amber-500/20 shadow-sm transition-all"
                                    >
                                </div>

                                <div class="border border-slate-200 rounded-2xl overflow-hidden">
                                    <table class="w-full text-sm text-left text-slate-600">
                                        <thead class="text-xs text-slate-500 bg-slate-50 uppercase font-black border-b border-slate-200 tracking-wider">
                                            <tr>
                                                <th class="px-6 py-4">Nome do Fornecedor</th>
                                                <th class="px-6 py-4 text-right">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <tr v-for="s in filteredSuppliers" :key="s.id" class="hover:bg-slate-50/50 transition-colors">
                                                <td class="px-6 py-4 font-bold text-slate-800">{{ s.name }}</td>
                                                <td class="px-6 py-4 text-right">
                                                    <button @click="editSupplier(s)" class="p-2 text-amber-600 hover:bg-amber-50 rounded-lg transition-colors inline-block mr-1"><PencilIcon class="w-4 h-4"/></button>
                                                    <button @click="deleteSupplier(s)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors inline-block"><TrashIcon class="w-4 h-4"/></button>
                                                </td>
                                            </tr>
                                            <tr v-if="!suppliers.data?.length">
                                                <td colspan="2" class="px-6 py-8 text-center text-slate-400 font-medium">Nenhum fornecedor cadastrado.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Pagination -->
                                <div v-if="suppliers.links?.length > 3" class="flex items-center justify-between px-4 py-3 bg-white border-t border-slate-200 sm:px-6 mt-4 rounded-xl shadow-sm">
                                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                        <p class="text-xs text-slate-700 font-bold uppercase tracking-widest">
                                            Exibindo <span class="font-black text-amber-600">{{ suppliers.from }}</span> até <span class="font-black text-amber-600">{{ suppliers.to }}</span> de <span class="font-black text-slate-900">{{ suppliers.total }}</span> resultados
                                        </p>
                                        <div class="flex gap-1">
                                            <button v-for="(link, i) in suppliers.links" :key="i" v-html="formatLabel(link.label)" @click="changePage(link.url)" :disabled="!link.url"
                                                :class="['px-3 py-2 text-xs font-black rounded-lg border transition-all', link.active ? 'bg-amber-600 text-white border-amber-600 shadow-sm shadow-amber-200' : link.url ? 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' : 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed']" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- TAB: PRODUCTS -->
                    <div v-show="activeTab === 'products'" class="animate-in fade-in zoom-in-95 duration-200">
                        <div class="flex flex-col xl:flex-row gap-8">
                            <!-- Form -->
                            <div class="w-full xl:w-1/3 bg-slate-50 p-6 rounded-2xl border border-slate-100 self-start">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full" :class="editingProduct ? 'bg-amber-500' : 'bg-orange-500'"></div>
                                    {{ editingProduct ? 'Editando Produto' : 'Adicionar Produto' }}
                                </h3>
                                <form @submit.prevent="submitProduct" class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Nome do Produto</label>
                                        <input v-model="productForm.name" type="text" placeholder="Ex: Alho em Pó A" required class="w-full border-slate-200 rounded-xl shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Origem (País)</label>
                                        <select v-model="productForm.country_id" required class="w-full border-slate-200 rounded-xl shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm">
                                            <option value="" disabled>Selecione a Origem</option>
                                            <option v-for="c in countries.data" :key="c.id" :value="c.id">
                                                {{ c.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Mês de Safra (Opcional)</label>
                                        <input v-model="productForm.harvest_month" type="text" placeholder="Ex: Julho" class="w-full border-slate-200 rounded-xl shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm">
                                    </div>
                                    <div class="pt-4 flex items-center gap-3">
                                        <button type="submit" :disabled="productForm.processing" class="flex-1 bg-orange-600 hover:bg-orange-700 text-white font-bold py-3 px-4 rounded-xl text-xs uppercase tracking-widest transition-all text-center shadow-sm shadow-orange-600/20">
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
                                        <SearchIcon class="h-4 w-4 text-slate-400 group-focus-within:text-orange-500 transition-colors" />
                                    </div>
                                    <input 
                                        v-model="filters.products" 
                                        type="text" 
                                        placeholder="Buscar produto, país ou safra..." 
                                        class="w-full pl-11 pr-4 py-3 bg-white border-slate-200 rounded-2xl text-sm focus:border-orange-500 focus:ring-orange-500/20 shadow-sm transition-all"
                                    >
                                </div>

                                <div class="border border-slate-200 rounded-2xl overflow-hidden">
                                    <table class="w-full text-sm text-left text-slate-600">
                                        <thead class="text-xs text-slate-500 bg-slate-50 uppercase font-black border-b border-slate-200 tracking-wider">
                                            <tr>
                                                <th class="px-6 py-4">Produto</th>
                                                <th class="px-6 py-4">País Origem</th>
                                                <th class="px-6 py-4">Safra</th>
                                                <th class="px-6 py-4 text-right">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <tr v-for="p in filteredProducts" :key="p.id" class="hover:bg-slate-50/50 transition-colors">
                                                <td class="px-6 py-4 font-bold text-slate-800">{{ p.name }}</td>
                                                <td class="px-6 py-4 font-medium">
                                                    <div class="flex items-center gap-2">
                                                        <CountryFlag :name="p.country?.name || ''" class-name="w-4 h-3 object-cover shadow-xs" />
                                                        {{ p.country?.name }}
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 text-slate-500">{{ p.harvest_month || '--' }}</td>
                                                <td class="px-6 py-4 text-right">
                                                    <button @click="editProduct(p)" class="p-2 text-orange-600 hover:bg-orange-50 rounded-lg transition-colors inline-block mr-1"><PencilIcon class="w-4 h-4"/></button>
                                                    <button @click="deleteProduct(p)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors inline-block"><TrashIcon class="w-4 h-4"/></button>
                                                </td>
                                            </tr>
                                            <tr v-if="!products.data?.length">
                                                <td colspan="4" class="px-6 py-8 text-center text-slate-400 font-medium">Nenhum produto cadastrado.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Pagination -->
                                <div v-if="products.links?.length > 3" class="flex items-center justify-between px-4 py-3 bg-white border-t border-slate-200 sm:px-6 mt-4 rounded-xl shadow-sm">
                                    <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                        <p class="text-xs text-slate-700 font-bold uppercase tracking-widest">
                                            Exibindo <span class="font-black text-orange-600">{{ products.from }}</span> até <span class="font-black text-orange-600">{{ products.to }}</span> de <span class="font-black text-slate-900">{{ products.total }}</span> resultados
                                        </p>
                                        <div class="flex gap-1">
                                            <button v-for="(link, i) in products.links" :key="i" v-html="formatLabel(link.label)" @click="changePage(link.url)" :disabled="!link.url"
                                                :class="['px-3 py-2 text-xs font-black rounded-lg border transition-all', link.active ? 'bg-orange-600 text-white border-orange-600 shadow-sm shadow-orange-200' : link.url ? 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' : 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed']" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                <!-- TAB: PRICES -->
                    <div v-show="activeTab === 'prices'" class="animate-in fade-in zoom-in-95 duration-200">
                        <div class="flex flex-col xl:flex-row gap-8">
                            <!-- Form -->
                            <div class="w-full xl:w-1/3 bg-slate-50 p-6 rounded-2xl border border-slate-100 self-start">
                                <h3 class="text-xs font-black text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full" :class="editingPrice ? 'bg-amber-500' : 'bg-blue-500'"></div>
                                    {{ editingPrice ? 'Editando Preço' : 'Registrar Preço Histórico' }}
                                </h3>
                                <form @submit.prevent="submitPrice" class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Produto</label>
                                        <select v-model="priceForm.product_id" required class="w-full border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                            <option value="" disabled>Selecione o Produto</option>
                                            <option v-for="p in products.data" :key="p.id" :value="p.id">{{ p.name }} ({{ p.country?.name }})</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Data Correspon.</label>
                                        <input v-model="priceForm.date" type="date" required class="w-full border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">Fornecedor (Opcional)</label>
                                        <select v-model="priceForm.supplier_id" class="w-full border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                            <option value="">Não especificado</option>
                                            <option v-for="s in suppliers.data" :key="s.id" :value="s.id">{{ s.name }}</option>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">P. Mín.</label>
                                            <input v-model="priceForm.min_price" type="number" step="0.01" required placeholder="0.00" class="w-full border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">P. Máx.</label>
                                            <input v-model="priceForm.max_price" type="number" step="0.01" required placeholder="0.00" class="w-full border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">P. Médio (Opcional)</label>
                                        <input v-model="priceForm.average_price" type="number" step="0.01" placeholder="0.00" class="w-full border-slate-200 rounded-xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    </div>
                                    <div class="pt-4 flex items-center gap-3">
                                        <button type="submit" :disabled="priceForm.processing" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl text-xs uppercase tracking-widest transition-all text-center shadow-sm shadow-blue-600/20">
                                            {{ editingPrice ? 'Atualizar' : 'Registrar Preço' }}
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
                                        v-model="filters.prices" 
                                        type="text" 
                                        placeholder="Buscar por data, produto, país, fornecedor ou preço..." 
                                        class="w-full pl-11 pr-4 py-3 bg-white border-slate-200 rounded-2xl text-sm focus:border-blue-500 focus:ring-blue-500/20 shadow-sm transition-all"
                                    >
                                </div>

                                <div class="border border-slate-200 rounded-2xl overflow-hidden relative">
                                    <div class="absolute top-0 right-0 p-3 z-20">
                                        <span class="bg-blue-50 text-blue-600 border border-blue-100 py-1 px-3 rounded-full text-[10px] font-black uppercase tracking-widest">
                                            {{ prices.total }} registros encontrados
                                        </span>
                                    </div>
                                    <div class="max-h-[600px] overflow-y-auto">
                                        <table class="w-full text-sm text-left text-slate-600">
                                            <thead class="text-xs text-slate-500 bg-slate-50 uppercase font-black border-b border-slate-200 tracking-wider sticky top-0 z-10">
                                                <tr>
                                                    <th class="px-6 py-4">Data</th>
                                                    <th class="px-6 py-4">Produto</th>
                                                    <th class="px-6 py-4 text-right">Min/Max</th>
                                                    <th class="px-6 py-4 text-right">Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                <tr v-for="pr in filteredPrices" :key="pr.id" class="hover:bg-slate-50/50 transition-colors">
                                                    <td class="px-6 py-4 font-mono text-xs font-bold text-slate-700">{{ formatDate(pr.date) }}</td>
                                                    <td class="px-6 py-4">
                                                        <div class="font-bold text-slate-800">{{ pr.product?.name }}</div>
                                                        <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider flex items-center gap-2">
                                                            <CountryFlag :name="pr.product?.country?.name || ''" class-name="w-3 h-2 outline-slate-100 outline" />
                                                            {{ pr.product?.country?.name }} • <span :class="pr.supplier ? 'text-blue-600' : 'text-slate-300 italic'">{{ pr.supplier?.name || 'S/ Fornecedor' }}</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 text-right font-mono text-xs">
                                                        <div class="flex flex-col gap-1 items-end">
                                                            <span class="text-emerald-600 bg-emerald-50 px-2 rounded"> Min: ${{ pr.min_price }} </span>
                                                            <span class="text-rose-600 bg-rose-50 px-2 rounded"> Max: ${{ pr.max_price }} </span>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 text-right">
                                                        <button @click="editPrice(pr)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors inline-block mr-1"><PencilIcon class="w-4 h-4"/></button>
                                                        <button @click="deletePrice(pr)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors inline-block"><TrashIcon class="w-4 h-4"/></button>
                                                    </td>
                                                </tr>
                                                <tr v-if="!prices.data?.length">
                                                    <td colspan="4" class="px-6 py-8 text-center text-slate-400 font-medium">Nenhum registro de preço lançado.</td>
                                                </tr>
                                            </tbody>
                                        </table>
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
                    </div>
                </div>

                <!-- TAB: IMPORT -->
                    <div v-show="activeTab === 'import'" class="animate-in fade-in zoom-in-95 duration-200">
                        <div class="max-w-4xl mx-auto space-y-8">
                            <div class="bg-indigo-50/50 border border-indigo-100 rounded-3xl p-8 sm:p-12 text-center space-y-6">
                                <div class="w-20 h-20 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto shadow-sm">
                                    <UploadCloudIcon class="w-10 h-10" />
                                </div>
                                <div class="space-y-2">
                                    <h2 class="text-2xl font-black text-slate-900 tracking-tight">Importação de Dados Automatizada</h2>
                                    <p class="text-slate-500 max-w-md mx-auto">Selecione sua planilha de preços (XLSX, XLS ou CSV) para atualizar rapidamente nossa base de dados analítica.</p>
                                </div>
                                
                                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm inline-block w-full max-w-md">
                                    <input 
                                        type="file" 
                                        @change="handleFileChange"
                                        accept=".xlsx,.xls,.csv"
                                        class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-6 file:rounded-xl file:border-0 file:text-xs file:font-black file:uppercase file:tracking-widest file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 cursor-pointer"
                                    />
                                    <p class="mt-4 text-[10px] text-slate-400 font-bold uppercase tracking-widest">Formatos aceitos: Excel (.xlsx, .xls) e CSV</p>
                                </div>

                                <div v-if="importFile && !isImporting && !importSuccess" class="pt-4">
                                    <button 
                                        @click="startImport"
                                        class="bg-[#0f172a] hover:bg-slate-800 text-white font-black py-4 px-12 rounded-2xl text-sm uppercase tracking-[0.2em] transition-all shadow-xl shadow-slate-200 active:scale-95"
                                    >
                                        Iniciar Importação
                                    </button>
                                </div>

                                <!-- Progress Bar -->
                                <div v-if="isImporting" class="max-w-md mx-auto space-y-4 pt-6 animate-in fade-in duration-500">
                                    <div class="flex items-center justify-between text-xs font-black uppercase tracking-widest text-slate-500">
                                        <div class="flex items-center gap-2">
                                            <Loader2Icon class="w-4 h-4 animate-spin text-indigo-600" />
                                            {{ importProgress?.status === 'queued' ? 'Aguardando na fila...' : 'Processando registros...' }}
                                        </div>
                                        <span>{{ importProgress?.percentage }}%</span>
                                    </div>
                                    <div class="w-full h-4 bg-slate-100 rounded-full overflow-hidden border border-slate-200 p-0.5">
                                        <div 
                                            class="h-full bg-indigo-600 rounded-full transition-all duration-500 shadow-sm shadow-indigo-200"
                                            :style="{ width: `${importProgress?.percentage}%` }"
                                        ></div>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest italic">
                                        {{ importProgress?.current }} de {{ importProgress?.total }} registros processados
                                    </p>
                                </div>

                                <!-- Success State -->
                                <div v-if="importSuccess" class="max-w-md mx-auto p-6 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-center gap-4 text-emerald-800 animate-in bounce-in duration-500">
                                    <CheckCircleIcon class="w-8 h-8 text-emerald-500 shrink-0" />
                                    <div class="text-left">
                                        <p class="font-black text-sm uppercase tracking-tight">Sucesso!</p>
                                        <p class="text-xs font-medium opacity-80">Todos os registros foram importados e as páginas atualizadas.</p>
                                    </div>
                                </div>

                                <!-- Error State -->
                                <div v-if="importError" class="max-w-md mx-auto p-6 bg-rose-50 border border-rose-100 rounded-2xl flex items-center gap-4 text-rose-800 animate-in shake duration-500">
                                    <AlertCircleIcon class="w-8 h-8 text-rose-500 shrink-0" />
                                    <div class="text-left">
                                        <p class="font-black text-sm uppercase tracking-tight">Falha na Importação</p>
                                        <p class="text-xs font-medium opacity-80">{{ importError }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                                <div class="bg-white p-6 rounded-2xl border border-slate-100 space-y-3">
                                    <h4 class="font-black text-slate-800 uppercase tracking-widest text-[10px]">Instruções do Formato</h4>
                                    <ul class="text-slate-500 space-y-2 text-xs font-medium">
                                        <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mt-1.5 shrink-0"></span> A primeira linha deve conter os cabeçalhos.</li>
                                        <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mt-1.5 shrink-0"></span> Colunas recomendadas: <b>Produto, País, Fornecedor, Data Registro, Preço</b>.</li>
                                        <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mt-1.5 shrink-0"></span> O sistema identificará automaticamente países e fornecedores novos.</li>
                                    </ul>
                                </div>
                                <div class="bg-white p-6 rounded-2xl border border-slate-100 space-y-3">
                                    <h4 class="font-black text-slate-800 uppercase tracking-widest text-[10px]">Dica Importante</h4>
                                    <p class="text-xs text-slate-500 font-medium leading-relaxed">
                                        Ao importar, o sistema usa as colunas <b>Produto, Fornecedor e Data</b> para evitar duplicatas. Se um registro com esse conjunto já existir, ele será atualizado com o novo preço.
                                    </p>
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    </template>
