<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Menu, Search, MapPin, Box, Calendar, ClockIcon, ChevronDown, Check, Truck, ArrowUpDown, Loader2, ArrowUp, ArrowDown, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import CountryFlag from '@/Components/CountryFlag.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const MapPinIcon = MapPin;
const TruckIcon = Truck;
const BoxIcon = Box;
const CalendarIcon = Calendar;
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200);
const isMobile = computed(() => windowWidth.value < 768);

onMounted(() => {
    window.addEventListener('resize', () => {
        windowWidth.value = window.innerWidth;
    });
});

const props = defineProps({
  currentPage: Object,
  countries: Array,
  suppliers: Array,
  products: Array,
  filters: Object,
  availableDates: Object,
  historicalData: {
    type: Object,
    default: () => ({ data: [], links: [] })
  },
  settings: Object,
});

const selectedCountry = ref(props.filters.country_id || '');
const selectedSupplier = ref(props.filters.supplier_id || '');
const selectedProduct = ref(props.filters.product_id || '');
const filterDateRange = ref(props.filters.date_range || 'Todos');

const suppliersWithOptions = computed(() => {
    return [{ id: '', name: 'Todos' }, ...props.suppliers];
});
const isDatePickerOpen = ref(false);
const expandedYears = ref([]);
const toggleYearGroup = (year) => {
    const sYear = year.toString();
    if (expandedYears.value.includes(sYear)) {
        expandedYears.value = expandedYears.value.filter(y => y !== sYear);
    } else {
        expandedYears.value.push(sYear);
    }
};
const isLoading = ref(false);

watch(() => props.filters, (newFilters) => {
    selectedCountry.value = newFilters.country_id || '';
    selectedSupplier.value = newFilters.supplier_id || '';
    selectedProduct.value = newFilters.product_id || '';
    filterDateRange.value = newFilters.date_range || 'Todos';
}, { deep: true });

const STORAGE_KEY = 'jrspice_filters_historical';

const applyFilters = (track = true, isRehydrating = false) => {
    saveFilters();
    isLoading.value = true;
    
    const query = { 
        country_id: selectedCountry.value,
        supplier_id: selectedSupplier.value,
        product_id: selectedProduct.value,
        date_range: filterDateRange.value,
        sort_field: props.filters.sort_field,
        sort_direction: props.filters.sort_direction
    };

    if (isRehydrating) {
        query._rehydrating = 1;
    }

    if (track) {
        query._track = 1;
    }

    router.get(
        route('dashboard.page', { slug: props.currentPage.slug }),
        query,
        { 
            preserveState: true, 
            replace: true,
            onFinish: () => { isLoading.value = false; }
        }
    );
};

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const hasManualFilters = urlParams.has('country_id') || urlParams.has('product_id') || urlParams.has('supplier_id') || urlParams.has('date_range');

    if (!hasManualFilters) {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            const parsed = JSON.parse(saved);
            // Verifica se o que temos no localStorage é diferente do que o backend mandou como padrão
            const isDifferent = 
                (parsed.country_id && parsed.country_id != props.filters.country_id) ||
                (parsed.supplier_id && parsed.supplier_id != props.filters.supplier_id) ||
                (parsed.product_id && parsed.product_id != props.filters.product_id) ||
                (parsed.date_range && parsed.date_range !== props.filters.date_range);

            if (isDifferent) {
                if (parsed.country_id) selectedCountry.value = parsed.country_id;
                if (parsed.supplier_id) selectedSupplier.value = parsed.supplier_id;
                if (parsed.product_id) selectedProduct.value = parsed.product_id;
                if (parsed.date_range) filterDateRange.value = parsed.date_range;
                applyFilters(false, true); // isRehydrating = true
            }
        }
    }
});

const handleCountryChange = () => {
    selectedProduct.value = ''; 
    selectedSupplier.value = '';
    filterDateRange.value = 'Todos';
    saveFilters();
    applyFilters();
};

const saveFilters = () => {
    const data = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
    data.country_id = selectedCountry.value;
    data.supplier_id = selectedSupplier.value;
    data.product_id = selectedProduct.value;
    data.date_range = filterDateRange.value;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
};

const clearFilters = () => {
    localStorage.removeItem(STORAGE_KEY);
    
    // Identifica os padrões
    const defCountry = props.settings?.default_filter_country_id || (props.countries[0]?.id || '');
    
    let defProduct = '';
    try {
        const val = props.settings?.default_filter_product_ids;
        const pids = Array.isArray(val) ? val : JSON.parse(val || '[]');
        defProduct = pids.length > 0 ? pids[0] : (props.products[0]?.id || '');
    } catch(e) {
        defProduct = props.products[0]?.id || '';
    }

    // Seta as refs locais antes do visit
    selectedCountry.value = defCountry;
    selectedSupplier.value = '';
    selectedProduct.value = defProduct;
    filterDateRange.value = 'Todos';

    isLoading.value = true;
    router.get(route('dashboard.page', { slug: props.currentPage.slug }), {
        country_id: defCountry,
        supplier_id: '',
        product_id: defProduct,
        date_range: 'Todos',
        sort_field: props.filters.sort_field,
        sort_direction: props.filters.sort_direction
    }, { 
        preserveState: false, 
        onFinish: () => isLoading.value = false 
    });
};

const handleSort = (field) => {
    let direction = 'asc';
    if (props.filters.sort_field === field) {
        direction = props.filters.sort_direction === 'asc' ? 'desc' : 'asc';
    }
    
    isLoading.value = true;
    router.get(
        route('dashboard.page', { slug: props.currentPage.slug }),
        { 
            country_id: selectedCountry.value,
            supplier_id: selectedSupplier.value,
            product_id: selectedProduct.value,
            date_range: filterDateRange.value,
            sort_field: field,
            sort_direction: direction
        },
        { preserveState: true, replace: true, onFinish: () => { isLoading.value = false; } }
    );
};

const getWeekNumber = (d) => {
    d = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay()||7));
    var yearStart = new Date(Date.UTC(d.getUTCFullYear(),0,1));
    var weekNo = Math.ceil(( ( (d - yearStart) / 86400000) + 1)/7);
    return weekNo;
};

const currentCountryData = computed(() => {
    return props.countries.find(c => c.id == selectedCountry.value) || {};
});

const processedHistoricalData = computed(() => {
    if (!props.historicalData?.data) return [];
    
    return props.historicalData.data.map(price => {
        const d = new Date(price.date);
        const y = d.getUTCFullYear();
        const w = getWeekNumber(d);
        
        return {
            productName: price.product?.name || '---',
            countryName: price.product?.country?.name || '',
            supplier: price.supplier?.name || 'Não inf.', 
            displayDate: new Date(price.date).toLocaleDateString('pt-BR', { timeZone: 'UTC' }),
            yearMonth: `${y}/${(d.getUTCMonth()+1).toString().padStart(2, '0')}`,
            week: w,
            priceVal: price.price
        };
    });
});

const formatPaginationLabel = (label) => {
    if (!label) return '';
    const l = label.toLowerCase();
    if (l.includes('previous')) return 'prev';
    if (l.includes('next')) return 'next';
    return label;
};

const changePage = (url) => {
    if (!url) return;
    isLoading.value = true;
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => { isLoading.value = false; }
    });
};

</script>

<template>
  <Head :title="currentPage.title" />

  <DashboardLayout>
    <template #sidebar-filters>
      <!-- FILTERS -->
      <div class="border-t border-slate-800/50 pt-4">
         <div class="flex items-center justify-between mb-4 px-3">
             <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em] flex items-center gap-2">
                 <span class="w-1 h-1 rounded-full bg-blue-500"></span> Filtros de Busca
             </p>
             <div class="flex items-center gap-3">
                 <button @click="clearFilters" class="text-[10px] font-bold text-slate-400 hover:text-blue-500 uppercase tracking-widest transition-colors">Limpar</button>
                 <Loader2 v-if="isLoading" class="w-3 h-3 text-blue-500 animate-spin" />
             </div>
         </div>
         
         <div class="space-y-5">
            <SearchableSelect 
               v-model="selectedCountry"
               :options="countries"
               label="País"
               placeholder="Selecione o País"
               :icon="MapPinIcon"
               :with-flag="true"
               variant="dark"
               direction="down"
               @change="handleCountryChange"
            />

            <SearchableSelect 
               v-model="selectedSupplier"
               :options="suppliersWithOptions"
               label="Fornecedor"
               placeholder="Todos os Fornecedores"
               :icon="TruckIcon"
               variant="dark"
               direction="down"
               @change="applyFilters"
            />

            <SearchableSelect 
               v-model="selectedProduct"
               :options="products"
               label="Produto"
               placeholder="Todos os Produtos"
               :icon="BoxIcon"
               :disabled="!selectedCountry"
               variant="dark"
               direction="down"
               @change="applyFilters"
            />

            <div class="relative px-1">
              <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5 flex items-center gap-1.5 cursor-pointer">
                  <Calendar class="w-3.5 h-3.5"/> Data (Ano / Semana)
              </label>
              
              <!-- CUSTOM TREE SELECT -->
              <div class="relative group">
                <button @click="isDatePickerOpen = !isDatePickerOpen" class="w-full bg-[#1e293b]/40 border border-slate-800 rounded-xl text-sm text-slate-300 font-bold px-3 py-3 flex items-center justify-between hover:bg-[#1e293b]/60 transition-colors uppercase">
                  <span>{{ filterDateRange === 'Todos' ? 'Todos os Registros' : (filterDateRange.includes('-') ? filterDateRange.replace('-', ' - Sem. ') : filterDateRange) }}</span>
                  <ChevronDown :class="{ 'rotate-180 text-blue-500': isDatePickerOpen }" class="w-4 h-4 text-slate-500 transition-transform duration-300" />
                </button>

                <div v-if="isDatePickerOpen" class="absolute z-50 left-0 right-0 bottom-full mb-2 bg-[#0f172a] border border-slate-800 rounded-xl shadow-2xl max-h-[300px] overflow-y-auto p-2 scrollbar-thin scrollbar-thumb-slate-800 animate-in fade-in slide-in-from-bottom-2 duration-200">
                   <div @click="() => { filterDateRange = 'Todos'; applyFilters(); isDatePickerOpen = false; }" class="flex items-center gap-2 p-2 hover:bg-white/5 rounded-lg cursor-pointer text-xs font-bold uppercase tracking-wider mb-2 border-b border-slate-800/50 pb-3 text-slate-400" :class="{ 'text-blue-400 bg-blue-600/10': filterDateRange === 'Todos' }">
                      <div class="w-4 h-4 border-2 rounded flex items-center justify-center border-slate-700" :class="{ 'bg-blue-600 border-blue-600': filterDateRange === 'Todos' }">
                        <Check v-if="filterDateRange === 'Todos'" class="w-3 h-3 text-white stroke-[4]" />
                      </div>
                      TODOS OS REGISTROS
                   </div>

                   <div v-for="group in availableDates" :key="group.year" class="mb-2">
                      <div class="flex items-center gap-1 group/yr">
                        <button @click.stop="toggleYearGroup(group.year)" class="p-1.5 text-slate-600 hover:text-blue-500 transition-colors">
                           <ChevronDown :class="{ '-rotate-90': !expandedYears.includes(group.year.toString()) }" class="w-3.5 h-3.5 transition-transform duration-300" />
                        </button>
                        <div @click="() => { filterDateRange = group.year.toString(); applyFilters(); isDatePickerOpen = false; }" class="flex-1 flex items-center gap-3 p-1 px-2 text-[11px] font-bold text-slate-500 uppercase tracking-widest cursor-pointer hover:text-blue-500 transition-all" :class="{ 'text-blue-400': filterDateRange == group.year }">
                           <div class="w-4 h-4 border-2 rounded flex items-center justify-center border-slate-700 transition-colors" :class="{ 'bg-blue-600 border-blue-600': filterDateRange == group.year }">
                              <Check v-if="filterDateRange == group.year" class="w-3 h-3 text-white stroke-[4]" />
                           </div>
                           {{ group.year }}
                        </div>
                      </div>
                      
                      <div v-if="expandedYears.includes(group.year.toString())" class="space-y-1 mt-1 ml-6 border-l border-slate-800/50 animate-in slide-in-from-top-2 duration-300">
                        <div v-for="d in group.weeks" :key="d.year + '-' + d.week" @click="() => { filterDateRange = d.year + '-' + d.week; applyFilters(); isDatePickerOpen = false; }" class="flex items-center gap-3 p-2 hover:bg-white/5 rounded-lg cursor-pointer text-[10px] font-bold uppercase tracking-wider text-slate-400 group/wk" :class="{ 'text-blue-400 bg-blue-600/10': filterDateRange === (d.year + '-' + d.week) }">
                           <div class="w-3.5 h-3.5 border-2 rounded flex items-center justify-center border-slate-700 transition-colors group-hover/wk:border-slate-500" :class="{ 'bg-blue-600 border-blue-600': filterDateRange === (d.year + '-' + d.week) }">
                              <Check v-if="filterDateRange === (d.year + '-' + d.week)" class="w-2.5 h-2.5 text-white stroke-[4]" />
                           </div>
                           SEMANA {{ d.week }}
                        </div>
                      </div>
                   </div>
                </div>
              </div>

              <!-- Overlay to close -->
              <div v-if="isDatePickerOpen" @click="isDatePickerOpen = false" class="fixed inset-0 z-40"></div>
            </div>
         </div>
      </div>
    </template>

    <div class="px-4 py-6 md:p-8 space-y-6 w-full max-w-none">
        


        <!-- Page Title Region -->
        <div class="flex flex-col md:flex-row md:items-end justify-between pb-4 border-b border-slate-200 mb-4 mt-2 gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 tracking-tight uppercase">{{ currentPage.title }}</h2>
                <p class="text-[10px] font-bold text-slate-500 mt-2 uppercase tracking-widest rounded-full border border-slate-200 bg-white inline-block px-3 py-1 shadow-sm flex items-center gap-2">
                    <ClockIcon class="w-3 h-3 text-slate-400" /> Atualizado em: <span class="text-blue-600">{{ new Date().toLocaleString('pt-BR') }}</span>
                </p>
            </div>
        </div>

        <div class="transition-opacity duration-300" :class="{ 'opacity-50 pointer-events-none': isLoading }">

            <!-- TABELA HISTÓRICO -->
            <div class="overflow-x-auto bg-white rounded-3xl shadow-sm border border-slate-200">
                <table class="w-full text-left border-collapse">
                    <thead class="text-[10px] md:text-base text-slate-500 bg-slate-50/80 font-bold uppercase tracking-wider md:tracking-widest border-b border-slate-200">
                        <tr>
                            <th class="px-2 py-3 md:px-5 md:py-4 cursor-pointer hover:bg-slate-100/50 transition-colors group" @click="handleSort('name')">
                                <div class="flex items-center gap-1 md:gap-2">
                                    PRODUTO
                                    <ArrowUp v-if="filters.sort_field === 'name' && filters.sort_direction === 'asc'" class="w-3 h-3 md:w-4 md:h-4 text-blue-600" />
                                    <ArrowDown v-else-if="filters.sort_field === 'name' && filters.sort_direction === 'desc'" class="w-3 h-3 md:w-4 md:h-4 text-blue-600" />
                                    <ArrowUpDown v-else class="w-3 h-3 md:w-4 md:h-4 text-slate-300 opacity-0 group-hover:opacity-100 transition-all" />
                                </div>
                            </th>
                            <th v-if="!isMobile" class="px-5 py-4 cursor-pointer hover:bg-slate-100/50 transition-colors group" @click="handleSort('country')">
                                <div class="flex items-center gap-2">
                                    PAÍS
                                    <ArrowUp v-if="filters.sort_field === 'country' && filters.sort_direction === 'asc'" class="w-4 h-4 text-blue-600" />
                                    <ArrowDown v-else-if="filters.sort_field === 'country' && filters.sort_direction === 'desc'" class="w-4 h-4 text-blue-600" />
                                    <ArrowUpDown v-else class="w-4 h-4 text-slate-300 opacity-0 group-hover:opacity-100 transition-all" />
                                </div>
                            </th>
                            <th v-if="!isMobile" class="px-5 py-4 cursor-pointer hover:bg-slate-100/50 transition-colors group" @click="handleSort('supplier')">
                                <div class="flex items-center gap-2">
                                    FORNECEDOR
                                    <ArrowUp v-if="filters.sort_field === 'supplier' && filters.sort_direction === 'asc'" class="w-4 h-4 text-blue-600" />
                                    <ArrowDown v-else-if="filters.sort_field === 'supplier' && filters.sort_direction === 'desc'" class="w-4 h-4 text-blue-600" />
                                    <ArrowUpDown v-else class="w-4 h-4 text-slate-300 opacity-0 group-hover:opacity-100 transition-all" />
                                </div>
                            </th>
                            <th class="px-2 py-3 md:px-5 md:py-4 cursor-pointer hover:bg-slate-100/50 transition-colors group" @click="handleSort('date')">
                                <div class="flex items-center gap-1 md:gap-2">
                                    DATA REGISTRO
                                    <ArrowUp v-if="filters.sort_field === 'date' && filters.sort_direction === 'asc'" class="w-3 h-3 md:w-4 md:h-4 text-blue-600" />
                                    <ArrowDown v-else-if="filters.sort_field === 'date' && filters.sort_direction === 'desc'" class="w-3 h-3 md:w-4 md:h-4 text-blue-600" />
                                    <ArrowUpDown v-else class="w-3 h-3 md:w-4 md:h-4 text-slate-300 opacity-0 group-hover:opacity-100 transition-all" />
                                </div>
                            </th>
                            <th v-if="!isMobile" class="px-5 py-4">ANO / MES</th>
                            <th v-if="!isMobile" class="px-5 py-4 text-center">SEMANA</th>
                            <th class="px-2 py-3 md:px-5 md:py-4 text-right cursor-pointer hover:bg-slate-100/50 transition-colors group" @click="handleSort('price')">
                                <div class="flex items-center justify-end gap-1 md:gap-2">
                                    {{ isMobile ? 'PREÇO' : 'PREÇO' }}
                                    <ArrowUp v-if="filters.sort_field === 'price' && filters.sort_direction === 'asc'" class="w-3 h-3 md:w-4 md:h-4 text-blue-600" />
                                    <ArrowDown v-else-if="filters.sort_field === 'price' && filters.sort_direction === 'desc'" class="w-3 h-3 md:w-4 md:h-4 text-blue-600" />
                                    <ArrowUpDown v-else class="w-3 h-3 md:w-4 md:h-4 text-slate-300 opacity-0 group-hover:opacity-100 transition-all" />
                                </div>
                            </th>
                        </tr>
                    </thead>
                        <tbody class="divide-y divide-slate-100 font-medium bg-white">
                            <tr v-for="(row, idx) in processedHistoricalData" :key="idx" class="hover:bg-blue-50/30 transition-colors">
                                <td class="px-2 py-3 md:px-5 md:py-4 text-slate-800 font-bold uppercase tracking-tight md:tracking-wide text-[11px] md:text-lg whitespace-normal leading-tight md:leading-snug max-w-[150px] md:max-w-[500px]">
                                    {{ row.productName }}
                                    <div v-if="isMobile" class="flex flex-col gap-0.5 mt-1 opacity-60 font-medium text-[10px]">
                                        <div class="flex items-center gap-1">
                                            <CountryFlag v-if="row.countryName" :name="row.countryName" class-name="w-3 h-2 rounded-[1px]" />
                                            {{ row.countryName }}
                                        </div>
                                        <span>{{ row.supplier }}</span>
                                    </div>
                                </td>
                                <td v-if="!isMobile" class="px-5 py-4 text-slate-500 uppercase text-base whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <CountryFlag v-if="row.countryName" :name="row.countryName" class-name="w-5 h-4 rounded-[1px]" />
                                        {{ row.countryName }}
                                    </div>
                                </td>
                                <td v-if="!isMobile" class="px-5 py-4 text-slate-500 uppercase text-base whitespace-nowrap">{{ row.supplier }}</td>
                                <td class="px-2 py-3 md:px-5 md:py-4 text-slate-500 text-[10px] md:text-lg whitespace-normal md:whitespace-nowrap leading-tight">
                                    {{ row.displayDate }}
                                    <div v-if="isMobile" class="mt-0.5 text-slate-400 font-mono text-[9px]">
                                        Mês: {{ row.yearMonth }} | Sem: {{ row.week }}
                                    </div>
                                </td>
                                <td v-if="!isMobile" class="px-5 py-4 text-slate-500 font-mono text-lg whitespace-nowrap">{{ row.yearMonth }}</td>
                                <td v-if="!isMobile" class="px-5 py-4 text-slate-700 text-center font-bold text-lg whitespace-nowrap">{{ row.week }}</td>
                                <td class="px-2 py-3 md:px-5 md:py-4 text-right tabular-nums font-black text-slate-900 pr-2 md:pr-8 text-sm md:text-2xl whitespace-nowrap">
                                    {{ row.priceVal ? Number(row.priceVal).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '--' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="historicalData.links?.length > 3" class="mt-8 flex flex-col md:flex-row items-center justify-between gap-4 bg-white p-4 md:p-6 rounded-3xl border border-slate-200 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest text-center md:text-left">
                         Exibindo <span class="text-blue-600">{{ historicalData.from }}</span> até <span class="text-blue-600">{{ historicalData.to }}</span> de <span class="text-slate-900">{{ historicalData.total }}</span> registros
                    </p>
                    <div class="flex flex-wrap justify-center gap-1 md:gap-1.5">
                        <template v-for="(link, i) in historicalData.links" :key="i">
                            <button v-if="formatPaginationLabel(link.label) === 'prev'" @click="changePage(link.url)" :disabled="!link.url"
                                :class="['p-2.5 md:px-4 md:py-2.5 rounded-xl border transition-all', !link.url ? 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50']">
                                <ChevronLeft class="w-4 h-4" />
                            </button>
                            <button v-else-if="formatPaginationLabel(link.label) === 'next'" @click="changePage(link.url)" :disabled="!link.url"
                                :class="['p-2.5 md:px-4 md:py-2.5 rounded-xl border transition-all', !link.url ? 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50']">
                                <ChevronRight class="w-4 h-4" />
                            </button>
                            <button v-else-if="!isMobile || (link.active || (i > 0 && i < historicalData.links.length - 1 && Math.abs(historicalData.current_page - parseInt(link.label)) <= 1))" 
                                v-html="link.label" @click="changePage(link.url)" :disabled="!link.url || link.label === '...'"
                                :class="['min-w-[40px] px-2 py-2.5 md:px-4 md:py-2.5 text-[10px] font-black uppercase tracking-widest rounded-xl border transition-all', link.active ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-100' : link.url ? 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' : 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed']" />
                        </template>
                    </div>
                </div>

        </div>
    </div>
  </DashboardLayout>
</template>
