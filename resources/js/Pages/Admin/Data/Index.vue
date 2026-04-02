<script setup>
import { ref, computed, watch } from 'vue';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { 
    LayoutDashboard as LayoutIcon, 
    Globe as GlobeIcon, 
    Package as PackageIcon, 
    Truck as TruckIcon, 
    TrendingUp as ActivityIcon, 
    FileUp as FileUpIcon,
    Search as SearchIcon,
    Plus as PlusIcon
} from 'lucide-vue-next';

// Tab Components
import PagesTab from './Tabs/PagesTab.vue';
import CountriesTab from './Tabs/CountriesTab.vue';
import SuppliersTab from './Tabs/SuppliersTab.vue';
import ProductsTab from './Tabs/ProductsTab.vue';
import PricesTab from './Tabs/PricesTab.vue';
import ImportTab from './Tabs/ImportTab.vue';

const tabs = [
    { id: 'pages', name: 'Páginas / Menu', icon: LayoutIcon },
    { id: 'countries', name: 'Países', icon: GlobeIcon },
    { id: 'products', name: 'Produtos', icon: PackageIcon },
    { id: 'suppliers', name: 'Fornecedores', icon: TruckIcon },
    { id: 'prices', name: 'Histórico Preços', icon: ActivityIcon },
    { id: 'import', name: 'Importar Planilha', icon: FileUpIcon },
];

const props = defineProps({
    pages: Object,
    countries: Object,
    products: Object,
    suppliers: Object,
    prices: Object,
    filters: Object,
    all_countries: Array,
    all_products: Array,
    all_suppliers: Array,
    default_filter_config: Object,
});

const activeTab = ref(props.filters?.tab || 'pages');

// Search Filters
const filters = ref({
    countries_search: props.filters?.countries_search || '',
    products_search: props.filters?.products_search || '',
    suppliers_search: props.filters?.suppliers_search || '',
    prices_search: props.filters?.prices_search || '',
});

let timeout;
const performSearch = () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            route('admin.data.index'),
            {
                products_search: filters.value.products_search,
                countries_search: filters.value.countries_search,
                suppliers_search: filters.value.suppliers_search,
                prices_search: filters.value.prices_search,
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                only: [activeTab.value, 'filters'],
                data: { tab: activeTab.value }
            }
        );
    }, 400);
};

// Observa mudanças de aba
watch(activeTab, (newTab) => {
    router.visit(route('admin.data.index', { tab: newTab }), {
        preserveState: true,
        replace: true,
        preserveScroll: true
    });
});

// Observa mudanças nos filtros e dispara a busca
watch(
    () => [filters.value.products_search, filters.value.countries_search, filters.value.suppliers_search, filters.value.prices_search],
    () => {
        performSearch();
    }
);

const updateSearch = (key, value) => {
    filters.value[key] = value;
};

const searchableProducts = computed(() => {
    return props.all_products.map(p => ({
        ...p,
        name: `${p.name} (${p.country?.name || 'N/A'})`
    }));
});
</script>

<template>
    <Head title="Gerenciamento de Dados Analíticos" />

    <DashboardLayout>
        <template #header>
            <h2 class="hidden md:block text-xs font-bold text-slate-400 uppercase tracking-widest">Base de Dados e Configurações</h2>
        </template>

        <div class="h-full bg-slate-50/50 pt-8 pb-16 px-4 sm:px-6 lg:px-8">
            <div class="w-full max-w-none">


                <!-- Tabs Navigation -->
                <div class="bg-white rounded-t-2xl border-b border-slate-200 shadow-sm overflow-hidden flex flex-nowrap overflow-x-auto">
                    <button 
                        v-for="tab in tabs" 
                        :key="tab.id"
                        @click="activeTab = tab.id" 
                        :class="activeTab === tab.id ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50/10' : 'border-transparent text-slate-500 hover:text-slate-800 hover:bg-slate-50'" 
                        class="px-6 py-5 border-b-[4px] font-bold text-sm uppercase tracking-widest transition-all flex items-center gap-3 whitespace-nowrap"
                    >
                        <component :is="tab.icon" class="w-5 h-5" /> {{ tab.name }}
                    </button>
                </div>

                <!-- Tab Content -->
                <div class="bg-white p-6 sm:p-8 shadow-sm rounded-b-2xl border-x border-b border-slate-200">
                    
                    <PagesTab 
                        v-show="activeTab === 'pages'" 
                        :pages="pages" 
                    />

                    <CountriesTab 
                        v-show="activeTab === 'countries'" 
                        :countries="countries" 
                        :filters="filters"
                        @updateSearch="updateSearch"
                    />

                    <SuppliersTab 
                        v-show="activeTab === 'suppliers'" 
                        :suppliers="suppliers" 
                        :filters="filters"
                        @updateSearch="updateSearch"
                    />

                    <ProductsTab 
                        v-show="activeTab === 'products'" 
                        :products="products" 
                        :all_countries="all_countries"
                        :all_products="all_products"
                        :default_filter_config="default_filter_config"
                        :filters="filters"
                        @updateSearch="updateSearch"
                    />

                    <PricesTab 
                        v-show="activeTab === 'prices'" 
                        :prices="prices" 
                        :all_suppliers="all_suppliers"
                        :searchableProducts="searchableProducts"
                        :filters="filters"
                        @updateSearch="updateSearch"
                    />

                    <ImportTab 
                        v-show="activeTab === 'import'" 
                    />

                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
