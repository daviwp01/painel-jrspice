<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Truck, ChevronDown, ChevronUp, Check, MapPin, Box, Calendar, Search, X, Loader2, Menu, ChartLine, ZoomIn, ZoomOut } from 'lucide-vue-next';
import CountryFlag from '@/Components/CountryFlag.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Line } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, PointElement, Filler } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, PointElement, Filler);

const ChartLineIcon = ChartLine;
const CalendarIcon = Calendar;
const MapPinIcon = MapPin;
const TruckIcon = Truck;
const BoxIcon = Box;
const ChevronDownIcon = ChevronDown;
const ChevronUpIcon = ChevronUp;
const CheckIcon = Check;
const SearchIcon = Search;
const XIcon = X;
const Loader2Icon = Loader2;
const MenuIcon = Menu;
const ZoomInIcon = ZoomIn;
const ZoomOutIcon = ZoomOut;
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1200);
const isMobile = computed(() => windowWidth.value < 768);

onMounted(() => {
    window.addEventListener('resize', () => {
        windowWidth.value = window.innerWidth;
    });
});

const props = defineProps({
  pages: Array,
  currentPage: Object,
  countries: Array,
  suppliers: Array,
  products: Array,
  pricesData: Array,
  metrics: Object,
  filters: Object,
  availableDates: Object,
  chartData: Object,
  chartWeeklyData: Object,
  settings: Object,
});

const monthsFull = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

const yearColors = {
    '2022': '#64748b',
    '2023': '#0f172a',
    '2024': '#2563eb',
    '2025': '#06b6d4',
    '2026': '#f59e0b',
};

const visibleYears = ref(Object.keys(props.chartData || {}));

// Keep visibleYears in sync with backend data
watch(() => props.chartData, (newData) => {
    visibleYears.value = Object.keys(newData || {});
}, { immediate: true });

const chartMode = ref('SEMANAL');

const toggleYear = (year) => {
    const sYear = String(year);
    if (visibleYears.value.includes(sYear)) {
        visibleYears.value = visibleYears.value.filter(y => y !== sYear);
    } else {
        visibleYears.value.push(sYear);
    }
};

const chartDataComputed = computed(() => {
    let datasets = [];
    let labels = [];
    if (chartMode.value === 'SEMANAL') {
        const monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
        const monthLabels = Array(52).fill("");
        for (let i = 0; i < 52; i++) {
            const d = new Date(2024, 0, 1 + (i * 7));
            const month = monthNames[d.getMonth()];
            const prevD = i > 0 ? new Date(2024, 0, 1 + ((i - 1) * 7)) : null;
            const isMonthStart = !prevD || prevD.getMonth() !== d.getMonth();
            if (isMonthStart) monthLabels[i] = month;
        }
        labels = monthLabels;

        const yearsAvail = Object.keys(props.chartWeeklyData || {});
        datasets = yearsAvail.filter(y => visibleYears.value.includes(y)).map(y => ({
            label: y,
            data: props.chartWeeklyData[y],
            borderColor: yearColors[y] || '#cbd5e1',
            borderWidth: 2,
            tension: 0.4,
            pointRadius: 3,
            pointHoverRadius: 8,
            pointBackgroundColor: yearColors[y] || '#cbd5e1',
            pointBorderColor: '#fff',
            pointBorderWidth: 1.5,
            fill: false,
            spanGaps: true
        }));
    } else if (chartMode.value === 'MENSAL') {
        labels = monthsFull.map(m => m.slice(0, 3));
        datasets = Object.entries(props.chartData || {})
            .filter(([year]) => visibleYears.value.includes(year))
            .map(([year, data]) => ({
                label: year,
                data: data,
                borderColor: yearColors[year] || '#cbd5e1',
                backgroundColor: (yearColors[year] || '#cbd5e1') + '20',
                borderWidth: 4,
                tension: 0.4,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointBackgroundColor: yearColors[year] || '#cbd5e1',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                fill: false,
                spanGaps: true
            }));
    } else {
        // CONTÍNUO
        labels = props.pricesData.map(p => {
           const d = new Date(p.date);
           return `${(d.getUTCMonth() + 1).toString().padStart(2, '0')}/${d.getUTCFullYear().toString().slice(-2)}`;
        });
        datasets = [{
            label: 'Tendência Histórica',
            data: props.pricesData.map(p => p.price),
            borderColor: '#2563eb', // Standard Blue
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            borderWidth: 4,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#2563eb',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            fill: true
        }];
    }

    return { labels, datasets };
});

const currentHarvest = computed(() => {
    // 1. Tenta buscar no array de produtos
    const product = props.products.find(p => p.id == selectedProduct.value);
    if (product?.harvest_month) return product.harvest_month;

    // 2. Tenta buscar no último registro de preços (mais recente, já que agora pricesData vem ASC)
    const latestWithHarvest = [...props.pricesData].reverse().find(p => p.harvest_month);
    if (latestWithHarvest?.harvest_month) return latestWithHarvest.harvest_month;

    // 3. Verifica se as métricas trouxeram info extra
    if (props.metrics?.product_info?.harvest_month) return props.metrics.product_info.harvest_month;

    return null;
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            mode: 'index',
            intersect: false,
            backgroundColor: '#0f172a',
            padding: 12,
            boxPadding: 1,
            usePointStyle: true,
            titleFont: { size: 14, weight: 'bold' },
            bodyFont: { size: 15 },
            callbacks: {
                label: (context) => ` ${context.dataset.label}: $ ${context.parsed.y.toLocaleString('pt-BR', {minimumFractionDigits: 2})}`,
                labelColor: (context) => ({
                    borderColor: 'transparent',
                    backgroundColor: context.dataset.borderColor,
                    borderWidth: 0
                }),
                labelPointStyle: () => ({
                    pointStyle: 'circle',
                    rotation: 0
                })
            }
        }
    },
    scales: {
        y: {
            grid: { color: '#f1f5f9' },
            ticks: { 
                font: { size: isMobile.value ? 10 : 18, weight: 'bold' },
                color: '#94a3b8',
                callback: (value) => `$ ${Number(value).toLocaleString('pt-BR')}`
            }
        },
        x: {
            grid: { display: false },
            ticks: { 
                font: { size: isMobile.value ? (chartMode.value === 'CONTÍNUO' ? 7 : 8) : 10, weight: 'black' },
                color: '#94a3b8',
                autoSkip: chartMode.value === 'CONTÍNUO',
                maxTicksLimit: chartMode.value === 'CONTÍNUO' ? (isMobile.value ? 6 : 12) : undefined,
                maxRotation: chartMode.value === 'CONTÍNUO' ? 0 : (isMobile.value ? 45 : 0),
                minRotation: chartMode.value === 'CONTÍNUO' ? 0 : (isMobile.value ? 45 : 0),
                padding: 10
            }
        }
    }
}));

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

const isMobileMenuOpen = ref(false);

// Sincroniza props que vieram do backend (já com os defaults da primeira página se não tinha ID na URL) com as refs
watch(() => props.filters, (newFilters) => {
    selectedCountry.value = newFilters.country_id || '';
    selectedSupplier.value = newFilters.supplier_id || '';
    selectedProduct.value = newFilters.product_id || '';
    filterDateRange.value = newFilters.date_range || 'Todos';
}, { deep: true });

const STORAGE_KEY = 'jrspice_filters_show';

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
                (parsed.product_id && parsed.product_id != props.filters.product_id) ||
                (parsed.date_range && parsed.date_range !== props.filters.date_range);

            if (isDifferent) {
                if (parsed.country_id) selectedCountry.value = parsed.country_id;
                if (parsed.supplier_id) selectedSupplier.value = parsed.supplier_id;
                if (parsed.product_id) selectedProduct.value = parsed.product_id;
                if (parsed.date_range) filterDateRange.value = parsed.date_range;
                applyFilters(false, true);
            }
        }
    }
});

const handleCountryChange = () => {
    selectedProduct.value = ''; // Limpa para que o backend decida o primeiro produto do novo país
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
    selectedProduct.value = defProduct;
    filterDateRange.value = 'Todos';

    isLoading.value = true;
    router.get(route('dashboard.page', { slug: props.currentPage.slug }), {
        country_id: defCountry,
        product_id: defProduct,
        date_range: 'Todos'
    }, { 
        preserveState: false, 
        onFinish: () => isLoading.value = false 
    });
};

const applyFilters = (track = true, isRehydrating = false) => {
  saveFilters();
  isLoading.value = true;
  
  const query = {
    country_id: selectedCountry.value,
    supplier_id: selectedSupplier.value,
    product_id: selectedProduct.value,
    date_range: filterDateRange.value
  };

  if (track) {
    query._track = 1;
  }
  
  if (isRehydrating) {
    query._rehydrating = 1;
  }

  router.get(route('dashboard.page', { slug: props.currentPage.slug }), query, { 
    preserveState: true, 
    onFinish: () => isLoading.value = false 
  });
};

const calculateChange = (min, max) => {
  if (!min || !max) return 0;
  return (((max - min) / min) * 100).toFixed(2);
};

const formatNumber = (value, decimals = 2) => {
    const num = parseFloat(value);
    if (isNaN(num)) return decimals === 3 ? '0,000' : '0,00';
    return num.toLocaleString('pt-BR', { 
        minimumFractionDigits: decimals, 
        maximumFractionDigits: decimals 
    });
};

const formatSpread = (value) => {
    const num = parseFloat(value);
    if (isNaN(num)) return '0,00';
    return num.toLocaleString('pt-BR', { 
        minimumFractionDigits: 2, 
        maximumFractionDigits: 2 
    });
};

const getAvgLabel = (min, max) => {
    const nMin = parseFloat(min);
    const nMax = parseFloat(max);
    if (isNaN(nMin) || isNaN(nMax)) return '0,00';
    return ((nMin + nMax) / 2).toLocaleString('pt-BR', { 
        minimumFractionDigits: 0, 
        maximumFractionDigits: 0 
    });
};

// ZOOM CHART LOGIC
const chartHeight = ref(450);
const zoomIn = () => { chartHeight.value += 100; };
const zoomOut = () => { if (chartHeight.value > 450) chartHeight.value -= 100; };
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
               v-model="selectedProduct"
               :options="products"
               label="Produto"
               placeholder="Selecione um Produto"
               :icon="BoxIcon"
               :disabled="!selectedCountry"
               variant="dark"
               direction="down"
               @change="applyFilters"
            />

            <div class="relative px-1">
              <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5 flex items-center gap-1.5 cursor-pointer">
                  <CalendarIcon class="w-3.5 h-3.5"/> Data (Ano / Semana)
              </label>
              
              <!-- CUSTOM TREE SELECT -->
              <div class="relative group">
                <button @click="isDatePickerOpen = !isDatePickerOpen" class="w-full bg-[#1e293b]/40 border border-slate-800 rounded-xl text-sm text-slate-300 font-bold px-3 py-3 flex items-center justify-between hover:bg-[#1e293b]/60 transition-colors uppercase">
                  <span>{{ filterDateRange === 'Todos' ? 'Todos os Registros' : (filterDateRange.includes('-') ? filterDateRange.replace('-', ' - Sem. ') : filterDateRange) }}</span>
                  <ChevronDownIcon :class="{ 'rotate-180 text-blue-500': isDatePickerOpen }" class="w-4 h-4 text-slate-500 transition-transform duration-300" />
                </button>

                <div v-if="isDatePickerOpen" class="absolute z-50 left-0 right-0 bottom-full mb-2 bg-[#0f172a] border border-slate-800 rounded-xl shadow-2xl max-h-[300px] overflow-y-auto p-2 scrollbar-thin scrollbar-thumb-slate-800 animate-in fade-in slide-in-from-bottom-2 duration-200">
                   <div @click="() => { filterDateRange = 'Todos'; applyFilters(); isDatePickerOpen = false; }" class="flex items-center gap-2 p-2 hover:bg-white/5 rounded-lg cursor-pointer text-xs font-bold uppercase tracking-wider mb-2 border-b border-slate-800/50 pb-3 text-slate-400" :class="{ 'text-blue-400 bg-blue-600/10': filterDateRange === 'Todos' }">
                      <div class="w-4 h-4 border-2 rounded flex items-center justify-center border-slate-700" :class="{ 'bg-blue-600 border-blue-600': filterDateRange === 'Todos' }">
                        <CheckIcon v-if="filterDateRange === 'Todos'" class="w-3 h-3 text-white stroke-[4]" />
                      </div>
                      TODOS OS REGISTROS
                   </div>

                   <div v-for="group in availableDates" :key="group.year" class="mb-2">
                      <div class="flex items-center gap-1 group/yr">
                        <button @click.stop="toggleYearGroup(group.year)" class="p-1.5 text-slate-600 hover:text-blue-500 transition-colors">
                           <ChevronDownIcon :class="{ '-rotate-90': !expandedYears.includes(group.year.toString()) }" class="w-3.5 h-3.5 transition-transform duration-300" />
                        </button>
                        <div @click="() => { filterDateRange = group.year.toString(); applyFilters(); isDatePickerOpen = false; }" class="flex-1 flex items-center gap-3 p-1 px-2 text-[11px] font-bold text-slate-500 uppercase tracking-widest cursor-pointer hover:text-blue-500 transition-all" :class="{ 'text-blue-400': filterDateRange == group.year }">
                           <div class="w-4 h-4 border-2 rounded flex items-center justify-center border-slate-700 transition-colors" :class="{ 'bg-blue-600 border-blue-600': filterDateRange == group.year }">
                              <CheckIcon v-if="filterDateRange == group.year" class="w-3 h-3 text-white stroke-[4]" />
                           </div>
                           {{ group.year }}
                        </div>
                      </div>
                      
                      <div v-if="expandedYears.includes(group.year.toString())" class="space-y-1 mt-1 ml-6 border-l border-slate-800/50 animate-in slide-in-from-top-2 duration-300">
                        <div v-for="d in group.weeks" :key="d.year + '-' + d.week" @click="() => { filterDateRange = d.year + '-' + d.week; applyFilters(); isDatePickerOpen = false; }" class="flex items-center gap-3 p-2 hover:bg-white/5 rounded-lg cursor-pointer text-[10px] font-bold uppercase tracking-wider text-slate-400 group/wk" :class="{ 'text-blue-400 bg-blue-600/10': filterDateRange === (d.year + '-' + d.week) }">
                           <div class="w-3.5 h-3.5 border-2 rounded flex items-center justify-center border-slate-700 transition-colors group-hover/wk:border-slate-500" :class="{ 'bg-blue-600 border-blue-600': filterDateRange === (d.year + '-' + d.week) }">
                              <CheckIcon v-if="filterDateRange === (d.year + '-' + d.week)" class="w-2.5 h-2.5 text-white stroke-[4]" />
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

    <div class="p-6 md:p-8 space-y-8 w-full max-w-none">
        <!-- Mobile Navigation Help Text -->






            <!-- Dashboard Content -->
            <!-- Dashboard Content -->
            <div class="transition-opacity duration-300" :class="{ 'opacity-50 pointer-events-none': isLoading }">
                
                <!-- GRID -> Info + Price Metrics -->
                <div v-if="metrics?.all" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 xl:gap-6 mb-8">
                    
                    <!-- BOX 1: PRODUTO -->
                    <div class="lg:col-span-1 bg-white py-1.5 px-2.5 rounded-xl shadow-sm border border-slate-200 relative group overflow-hidden flex flex-col">
                        <div class="absolute right-0 top-0 w-24 h-24 bg-slate-50 rounded-full blur-3xl -mr-12 -mt-12 group-hover:bg-blue-50/50 transition-colors"></div>
                        
                        <div class="relative z-10">
                            <p class="text-[8px] xl:text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-0.5 flex items-center gap-2">
                                <BoxIcon class="w-3" /> PRODUTO
                            </p>
                            <h3 class="text-sm xl:text-base font-black text-slate-900 uppercase tracking-tighter xl:tracking-tight leading-tight mb-1 truncate">
                                {{ products.find(p => p.id == selectedProduct)?.name || 'Todos os Produtos' }}
                            </h3>
                            
                            <div class="flex items-center gap-2 pt-1.5 border-t border-slate-50 overflow-hidden">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <CountryFlag v-if="countries.find(c => c.id == selectedCountry)?.name" :name="countries.find(c => c.id == selectedCountry)?.name" class-name="w-5 h-3.5 xl:w-6 xl:h-4 rounded-sm border border-slate-100 shrink-0" />
                                    <p class="text-[10px] xl:text-[11px] font-bold text-slate-500 uppercase tracking-wide truncate">
                                        {{ countries.find(c => c.id == selectedCountry)?.name || 'GLOBAL' }}
                                    </p>
                                </div>
                                <div v-if="currentHarvest" class="ml-auto flex items-center gap-1 shrink-0">
                                    <span class="text-[8px] xl:text-[9px] font-black text-slate-300 uppercase">SAFRA</span>
                                    <span class="text-[9px] xl:text-[10px] font-bold text-blue-600 bg-blue-50 px-1.5 xl:px-2 py-0.5 rounded-full uppercase">
                                        {{ currentHarvest }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BOX 2: ÚLTIMA SEMANA -->
                    <div class="bg-blue-600 text-white py-1.5 px-2.5 rounded-xl shadow-xl relative overflow-hidden border border-blue-500 group flex flex-col">
                        <div class="absolute right-0 top-0 w-24 h-24 bg-blue-500/50 rounded-full blur-3xl -mr-12 -mt-12 group-hover:bg-blue-400/50 transition-colors"></div>
                        
                        <div class="flex items-start justify-between mb-1 z-10 relative">
                            <p class="text-[8px] xl:text-[9px] font-bold text-blue-100 uppercase tracking-tighter xl:tracking-widest leading-[1.1] mr-2">Menores e maiores preços na semana</p>
                            <div class="bg-blue-800 text-[9px] xl:text-[10px] font-bold uppercase px-1.5 py-0.5 rounded shadow-sm border border-blue-400/30 tabular-nums shrink-0 mt-0.5">
                                {{ formatSpread(metrics.latest.spread) }}%
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2 xl:gap-4 z-10 relative mt-0.5">
                            <div class="pl-2 border-l-2 border-white/20">
                                <p class="text-[8px] xl:text-[9px] font-bold text-blue-200 uppercase tracking-wider mb-0.5">MIN</p>
                                <p class="text-lg xl:text-xl font-black tabular-nums tracking-tighter xl:tracking-tight leading-none">{{ formatNumber(metrics.latest.min) }}</p>
                            </div>
                            <div class="pl-2 border-l-2 border-white/60">
                                <p class="text-[8px] xl:text-[9px] font-bold text-blue-200 uppercase tracking-wider mb-0.5">MAX</p>
                                <p class="text-lg xl:text-xl font-black tabular-nums tracking-tighter xl:tracking-tight leading-none">{{ formatNumber(metrics.latest.max) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- BOX 3: ANO -->
                    <div class="bg-white py-1.5 px-2.5 rounded-xl shadow-sm border border-slate-200 relative group overflow-hidden flex flex-col">
                        <div class="absolute right-0 top-0 w-24 h-24 bg-slate-50 rounded-full blur-3xl -mr-12 -mt-12 group-hover:bg-blue-50/50 transition-colors"></div>
                        
                        <div class="flex items-start justify-between mb-1 z-10 relative">
                            <p class="text-[8px] xl:text-[9px] font-bold text-slate-400 uppercase tracking-tighter xl:tracking-widest leading-[1.1] mr-2">
                                Variação total no ano de <span class="text-blue-600 font-black">{{ metrics.year.label?.split(':')?.[1]?.trim() || new Date().getFullYear() }}</span>
                            </p>
                            <div class="bg-slate-100 text-[9px] xl:text-[10px] font-bold text-slate-600 uppercase px-1.5 py-0.5 rounded shadow-sm border border-slate-200 tabular-nums shrink-0 mt-0.5">
                                {{ formatSpread(metrics.year.spread) }}%
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2 xl:gap-4 z-10 relative mt-0.5">
                            <div class="pl-2 border-l-2 border-blue-500/20">
                                <p class="text-[8px] xl:text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">MIN</p>
                                <p class="text-lg xl:text-xl font-black text-slate-800 tabular-nums tracking-tighter xl:tracking-tight leading-none">{{ formatNumber(metrics.year.min) }}</p>
                            </div>
                            <div class="pl-2 border-l-2 border-blue-600">
                                <p class="text-[8px] xl:text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">MAX</p>
                                <p class="text-lg xl:text-xl font-black text-blue-600 tabular-nums tracking-tighter xl:tracking-tight leading-none">{{ formatNumber(metrics.year.max) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- BOX 4: DESDE -->
                    <div class="bg-white py-1.5 px-2.5 rounded-xl shadow-sm border border-slate-200 relative group overflow-hidden flex flex-col">
                        <div class="absolute right-0 top-0 w-24 h-24 bg-slate-50 rounded-full blur-3xl -mr-12 -mt-12 group-hover:bg-blue-50/50 transition-colors"></div>
                        
                        <div class="flex items-start justify-between mb-1 z-10 relative">
                            <p class="text-[8px] xl:text-[9px] font-bold text-slate-400 uppercase tracking-tighter xl:tracking-widest leading-[1.1] mr-2">
                                Variação total desde <span class="text-blue-600 font-black">{{ metrics.all.label?.split(':')?.[1]?.trim() }}</span>
                            </p>
                            <div class="bg-slate-100 text-[9px] xl:text-[10px] font-bold text-rose-600 uppercase px-1.5 py-0.5 rounded shadow-sm border border-slate-200 tabular-nums shrink-0 mt-0.5">
                                {{ formatSpread(metrics.all.spread) }}%
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-2 xl:gap-4 z-10 relative mt-0.5">
                            <div class="pl-2 border-l-2 border-blue-500/20">
                                <p class="text-[8px] xl:text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">MIN</p>
                                <p class="text-lg xl:text-xl font-black text-slate-800 tabular-nums tracking-tighter xl:tracking-tight leading-none">{{ formatNumber(metrics.all.min) }}</p>
                            </div>
                            <div class="pl-2 border-l-2 border-blue-600">
                                <p class="text-[8px] xl:text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">MAX</p>
                                <p class="text-lg xl:text-xl font-black text-blue-600 tabular-nums tracking-tighter xl:tracking-tight leading-none">{{ formatNumber(metrics.all.max) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- COMPARATIVE YEAR CHART (Multi-line) -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 md:p-8">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 pb-4 border-b border-slate-100 gap-4">
                        <div class="flex items-center gap-4">
                           <h3 class="text-lg font-bold text-slate-900 uppercase tracking-widest">MELHOR PREÇO x TEMPO</h3>
                           <div class="flex items-center gap-1.5 ml-2 bg-slate-50 p-1 rounded-xl border border-slate-100">
                               <button @click="zoomOut" class="p-2 bg-white text-slate-500 hover:text-blue-600 rounded-lg shadow-sm border border-slate-200 hover:border-blue-200 transition-all group" title="Diminuir Visualização">
                                   <ZoomOutIcon class="w-4 h-4" />
                               </button>
                               <button @click="zoomIn" class="p-2 bg-white text-slate-500 hover:text-blue-600 rounded-lg shadow-sm border border-slate-200 hover:border-blue-200 transition-all group" title="Aumentar Visualização">
                                   <ZoomInIcon class="w-4 h-4" />
                               </button>
                           </div>
                        </div>
                        <div class="flex bg-slate-100 p-1 rounded-lg">
                            <button @click="chartMode = 'MENSAL'" :class="[chartMode === 'MENSAL' ? 'bg-white text-slate-800 shadow-sm border border-slate-200' : 'text-slate-500 hover:text-slate-700']" class="px-5 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all">MENSAL</button>
                            <button @click="chartMode = 'SEMANAL'" :class="[chartMode === 'SEMANAL' ? 'bg-white text-slate-800 shadow-sm border border-slate-200' : 'text-slate-500 hover:text-slate-700']" class="px-5 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all">SEMANAL</button>
                            <button @click="chartMode = 'CONTÍNUO'" :class="[chartMode === 'CONTÍNUO' ? 'bg-white text-slate-800 shadow-sm border border-slate-200' : 'text-slate-500 hover:text-slate-700']" class="px-5 py-2 text-xs font-bold uppercase tracking-wider rounded-md transition-all">CONTÍNUO</button>
                        </div>
                    </div>
                    
                    <div class="relative w-full mt-8 flex flex-col pt-4" :style="{ height: chartHeight + 'px' }">
                        <!-- Y-AXIS LABELS -->
                        <div v-if="metrics" :class="[isMobile ? 'text-[10px] w-12' : 'text-lg w-20']" class="absolute left-0 top-0 bottom-12 flex flex-col justify-between font-bold text-slate-400 tabular-nums pb-2">
                           <span>{{ formatNumber(metrics.all.max, 0) }}</span>
                           <span>{{ getAvgLabel(metrics.all.min, metrics.all.max) }}</span>
                           <span>{{ formatNumber(metrics.all.min, 0) }}</span>
                        </div>

                        <!-- CHART AREA -->
                        <div :class="[isMobile ? 'ml-12' : 'ml-20']" class="flex-1 relative border-l border-b border-slate-100 mb-12 min-h-0">
                           <Line 
                              v-if="chartData && Object.keys(chartData).length"
                              :key="chartMode"
                              :data="chartDataComputed" 
                              :options="chartOptions"
                           />
                           <div v-else-if="!isLoading" class="absolute inset-0 flex flex-col items-center justify-center text-slate-300">
                               <SearchIcon class="w-12 h-12 mb-4 opacity-20" />
                               <p class="text-[10px] font-bold uppercase tracking-widest">Aguardando seleção de produto</p>
                           </div>
                        </div>

                    </div>

                    <!-- LEGEND -->
                    <div v-if="chartMode !== 'CONTÍNUO'" class="mt-4 flex flex-wrap justify-center gap-6 pt-4 border-t border-slate-50 items-center">
                       <div v-for="(yearsValue, yearKey) in chartData" :key="yearKey" @click="toggleYear(yearKey)" class="flex items-center gap-2 group cursor-pointer transition-opacity duration-300" :class="{ 'opacity-30': !visibleYears.includes(yearKey) }">
                          <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: yearColors[yearKey] }"></div>
                          <span class="text-sm font-bold text-slate-600 uppercase tracking-widest group-hover:text-blue-600 transition-colors">{{ yearKey }}</span>
                       </div>
                    </div>
                </div>
            </div>

        </div>
  </DashboardLayout>
</template>
