<script setup>
import { ref, watch, computed, onMounted } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import UpgradeModal from '@/Components/UpgradeModal.vue';
import { Truck, ChevronDown, ChevronUp, Check, MapPin, Box, Calendar, Search, X, Loader2, Menu, ChartLine, ZoomIn, ZoomOut, MessageSquare, Lock } from 'lucide-vue-next';
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
  settings: Object,
});

const monthsFull = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

const yearColors = {
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
    // In Demo Mode, only allow toggling if it's the current year (latest one)
    const latestYear = String(Math.max(...Object.keys(props.chartData || {}).map(Number)));
    
    if (sYear !== latestYear) {
        openUpgradeModal({ is_locked: true, lock_message: 'Para visualizar detalhes por produto completo, entre em contato.' });
        return;
    }

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
        
        const yearsAvail = Object.keys(props.chartData || {});
        datasets = yearsAvail.filter(y => visibleYears.value.includes(y)).map(y => {
            const yearWeeks = Array(52).fill(null);
            props.pricesData.filter(p => new Date(p.date).getFullYear() == y).forEach(p => {
                const dateVal = new Date(p.date);
                const start = new Date(dateVal.getFullYear(), 0, 1);
                const week = Math.floor((dateVal - start) / (7 * 24 * 60 * 60 * 1000));
                if (week >= 0 && week < 52) {
                    if (yearWeeks[week] === null || p.price < yearWeeks[week]) {
                        yearWeeks[week] = p.price;
                    }
                }
            });

            return {
                label: y,
                data: yearWeeks,
                borderColor: yearColors[y] || '#cbd5e1',
                borderWidth: 2,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 8,
                pointBackgroundColor: yearColors[y],
                pointBorderColor: '#fff',
                pointBorderWidth: 1.5,
                fill: false,
                spanGaps: true
            };
        });
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
        labels = props.pricesData.map(p => new Date(p.date).toLocaleDateString('pt-BR', { month: 'short', year:'2-digit' }));
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
                font: { size: 14, weight: 'bold' },
                color: '#94a3b8',
                callback: (value) => `$ ${Number(value).toLocaleString('pt-BR')}`
            }
        },
        x: {
            grid: { display: false },
            ticks: { 
                font: { size: 10, weight: 'black' },
                color: '#94a3b8',
                autoSkip: chartMode.value === 'CONTÍNUO',
                maxRotation: 0,
                minRotation: 0,
                padding: 10
            }
        }
    }
}));

const selectedCountry = ref(props.filters.country_id || '');
const selectedSupplier = ref(props.filters.supplier_id || '');
const selectedProduct = ref(props.filters.product_id || '');
const filterDateRange = ref(props.filters.date_range || 'Todos');
const isDatePickerOpen = ref(false);
const isLoading = ref(false);
const isMobileMenuOpen = ref(false);
const isUpgradeModalOpen = ref(false);
const upgradeModalContent = ref({ title: '', message: '' });

const openUpgradeModal = (option) => {
    upgradeModalContent.value = {
        title: option.is_locked ? 'Acesso Restrito' : 'Recurso Premium',
        message: option.lock_message || 'Para visualizar detalhes por produto completo, fale conosco.'
    };
    isUpgradeModalOpen.value = true;
};

const demoCountryId = computed(() => props.settings?.default_filter_country_id || null);
const demoProductIds = computed(() => {
    try {
        const val = props.settings?.default_filter_product_ids;
        return Array.isArray(val) ? val : JSON.parse(val || '[]');
    } catch (e) {
        return [];
    }
});

const countriesDemo = computed(() => {
    return props.countries.map(c => ({
        ...c,
        is_locked: demoCountryId.value && c.id != demoCountryId.value,
        lock_message: 'Para visualizar detalhes por produto completo, fale conosco.'
    }));
});

const productsDemo = computed(() => {
    return props.products.map(p => ({
        ...p,
        is_locked: demoCountryId.value && (p.country_id != demoCountryId.value || !demoProductIds.value.includes(p.id)),
        lock_message: 'Para visualizar detalhes por produto completo, fale conosco.'
    }));
});

// Sincroniza props que vieram do backend (já com os defaults da primeira página se não tinha ID na URL) com as refs
watch(() => props.filters, (newFilters) => {
    selectedCountry.value = newFilters.country_id || '';
    selectedSupplier.value = newFilters.supplier_id || '';
    selectedProduct.value = newFilters.product_id || '';
    filterDateRange.value = newFilters.date_range || 'Todos';
}, { deep: true });

const handleCountryChange = () => {
    if (demoCountryId.value && selectedCountry.value != demoCountryId.value) {
        openUpgradeModal({ is_locked: true, lock_message: 'Para visualizar detalhes por produto completo, fale conosco.' });
        selectedCountry.value = demoCountryId.value; // Reverte para o permitido
        return;
    }
    applyFilters();
};

const applyFilters = () => {
  // Se for um produto bloqueado, barramos aqui no front também
  const product = props.products.find(p => p.id == selectedProduct.value);
  const isLocked = product?.country_id != demoCountryId.value || (demoProductIds.value.length > 0 && !demoProductIds.value.includes(selectedProduct.value));
  
  if (selectedProduct.value && isLocked) {
     openUpgradeModal({ is_locked: true, lock_message: 'Para visualizar detalhes por produto completo, fale conosco.' });
     return;
  }

  isLoading.value = true;
  router.get(route('dashboard.page', { slug: props.currentPage.slug }), {
    country_id: selectedCountry.value,
    supplier_id: selectedSupplier.value,
    product_id: selectedProduct.value,
    date_range: filterDateRange.value
  }, { 
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
const chartHeight = ref(650);
const zoomIn = () => { chartHeight.value += 100; };
const zoomOut = () => { if (chartHeight.value > 450) chartHeight.value -= 100; };

onMounted(() => {
    // Se o usuário tentar acessar via URL algo que não é o padrão, o servidor já forçou os dados corretos.
    // Apenas mostramos o modal para avisar que ele não tem essa permissão.
    const urlParams = new URLSearchParams(window.location.search);
    const urlCountryId = urlParams.get('country_id');
    const urlProductId = urlParams.get('product_id');

    if ((urlCountryId && urlCountryId != demoCountryId.value) || 
        (urlProductId && !demoProductIds.value.includes(parseInt(urlProductId)))) {
        setTimeout(() => {
            openUpgradeModal({ is_locked: true, lock_message: 'Para visualizar detalhes por produto completo, fale conosco.' });
        }, 500);
    }
});
</script>

<template>
  <Head :title="currentPage.title" />

  <DashboardLayout>
    <template #sidebar-filters>
      <!-- FILTERS -->
      <div class="mt-8">
         <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.1em] mb-4 flex items-center justify-between">
             <span class="flex items-center gap-2"><SearchIcon class="w-3 h-3"/> Filtros de Busca</span>
             <div v-if="demoCountryId" class="bg-amber-100 text-amber-700 text-[9px] font-black px-2 py-0.5 rounded-full border border-amber-200">MODO DEMO</div>
             <Loader2 v-if="isLoading" class="w-3 h-3 text-blue-500 animate-spin" />
         </p>
         
          <div class="space-y-4">
            <SearchableSelect 
               v-model="selectedCountry"
               :options="countriesDemo"
               label="País"
               placeholder="Selecione o País"
               :icon="MapPinIcon"
               :with-flag="true"
               direction="up"
               @click.stop
               @locked-click="openUpgradeModal"
               @change="handleCountryChange"
            />


            <SearchableSelect 
               v-model="selectedProduct"
               :options="productsDemo"
               label="Produto"
               placeholder="Selecione um Produto"
               :icon="BoxIcon"
               :disabled="!selectedCountry"
               @locked-click="openUpgradeModal"
               direction="up"
               @change="applyFilters"
            />

            <div class="relative">
              <label class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-1.5 flex items-center gap-1.5"><CalendarIcon class="w-3 h-3"/> Data (Ano / Semana)</label>
              
              <!-- CUSTOM TREE SELECT -->
              <div class="relative group">
                <button @click="isDatePickerOpen = !isDatePickerOpen" class="w-full bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-800 font-bold px-3 py-2.5 flex items-center justify-between hover:bg-slate-100 transition-colors uppercase">
                  <span>{{ filterDateRange === 'Todos' ? 'Todos os Registros' : filterDateRange.replace('-', ' - Sem. ') }}</span>
                  <ChevronDownIcon :class="{ 'rotate-180': isDatePickerOpen }" class="w-4 h-4 text-slate-400 transition-transform" />
                </button>

                <div v-if="isDatePickerOpen" class="absolute z-50 left-0 right-0 bottom-full mb-2 bg-white border border-slate-200 rounded-xl shadow-xl max-h-[300px] overflow-y-auto p-2 scrollbar-thin scrollbar-thumb-slate-200 animate-in fade-in slide-in-from-bottom-2 duration-200">
                   <div @click="openUpgradeModal({ is_locked: true, lock_message: 'Para visualizar detalhes por produto completo, fale conosco.' })" class="flex items-center gap-2 p-2 hover:bg-blue-50 rounded-lg cursor-pointer text-xs font-bold uppercase tracking-wider mb-2 border-b border-slate-100 pb-3" :class="{ 'text-blue-600 bg-blue-50/50': filterDateRange === 'Todos' }">
                      <div class="w-4 h-4 border-2 rounded flex items-center justify-center border-slate-300" :class="{ 'bg-blue-600 border-blue-600': filterDateRange === 'Todos' }">
                        <CheckIcon v-if="filterDateRange === 'Todos'" class="w-3 h-3 text-white stroke-[4]" />
                      </div>
                      TODOS OS REGISTROS
                   </div>

                   <div v-for="group in availableDates" :key="group.year" class="mb-4">
                      <div class="flex items-center gap-2 p-1 px-2 text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-2 border-l-2 border-slate-100 ml-1">
                        {{ group.year }}
                      </div>
                      <div class="space-y-1">
                        <div v-for="d in group.weeks" :key="d.year + '-' + d.week" @click="openUpgradeModal({ is_locked: true, lock_message: 'Para visualizar detalhes por produto completo, fale conosco.' })" class="flex items-center gap-3 p-2 hover:bg-slate-50 rounded-lg cursor-pointer text-[10px] font-bold uppercase tracking-wider" :class="{ 'text-blue-600 bg-blue-50/50': filterDateRange === (d.year + '-' + d.week) }">
                           <div class="w-4 h-4 border-2 rounded flex items-center justify-center border-slate-300 ml-4" :class="{ 'bg-blue-600 border-blue-600': filterDateRange === (d.year + '-' + d.week) }">
                              <CheckIcon v-if="filterDateRange === (d.year + '-' + d.week)" class="w-3 h-3 text-white stroke-[4]" />
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
            <div v-if="selectedProduct && metrics?.all" class="transition-opacity duration-300" :class="{ 'opacity-50 pointer-events-none': isLoading }">
                
                <!-- GRID -> Info + Price Metrics -->
                <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 xl:gap-6 mb-8">
                    
                    <!-- BOX 1: PRODUTO -->
                    <div class="lg:col-span-1 bg-white p-5 rounded-3xl shadow-sm border border-slate-200 relative group overflow-hidden">
                        <div class="absolute right-0 top-0 w-32 h-32 bg-slate-50 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-blue-50/50 transition-colors"></div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1 flex items-center gap-2">
                           <BoxIcon class="w-3 h-3 text-slate-300" /> Produto
                        </p>
                        <h3 class="text-lg font-bold text-slate-900 mb-3 relative z-10 uppercase tracking-tight leading-none">{{ products.find(p => p.id == selectedProduct)?.name }}</h3>
                        
                        <div :class="[products.find(p => p.id == selectedProduct)?.harvest_month ? 'grid-cols-2' : 'grid-cols-1']" class="grid gap-4 border-t border-slate-100 pt-4 relative z-10">
                            <div>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest flex items-center gap-2 mb-2">
                                    País
                                </p>
                                <div class="flex items-center gap-3">
                                   <CountryFlag v-if="countries.find(c => c.id == selectedCountry)?.name" :name="countries.find(c => c.id == selectedCountry)?.name" class-name="w-11 h-8 rounded-[4px] border border-slate-100" />
                                   <p class="text-base font-bold text-slate-900 uppercase tracking-tight">{{ countries.find(c => c.id == selectedCountry)?.name }}</p>
                                </div>
                            </div>
                            <div v-if="products.find(p => p.id == selectedProduct)?.harvest_month">
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-2">Safra</p>
                                <p class="text-sm font-bold text-blue-600 uppercase tracking-tight">{{ products.find(p => p.id == selectedProduct)?.harvest_month }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- BOX 2: ÚLTIMA SEMANA -->
                    <div class="bg-blue-600 text-white p-4 lg:p-5 rounded-3xl shadow-xl relative overflow-hidden border border-blue-500 group">
                        <div class="absolute right-0 top-0 w-32 h-32 bg-blue-500/50 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-blue-400/50 transition-colors"></div>
                        <div class="absolute right-4 top-4 bg-blue-800 text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded-lg shadow-lg border border-blue-400/30 z-10 tabular-nums">
                            {{ formatSpread(metrics.latest.spread) }}%
                        </div>
                        <p class="text-[9px] font-bold text-blue-100 uppercase tracking-[0.12em] mb-0.5 truncate pr-20 z-10 relative">{{ metrics.latest.label }}</p>
                        <p class="text-[9px] font-bold text-blue-200/60 uppercase tracking-widest mb-3 z-10 relative">{{ metrics.latest.sub_label }}</p>
                        <div class="space-y-4 pt-1 z-10 relative">
                            <div class="pl-2 border-l-[3px] border-white/20">
                                <p class="text-[11px] font-bold text-blue-200 uppercase tracking-wider mb-0.5">MIN</p>
                                <p class="text-2xl font-bold tabular-nums tracking-tight">{{ formatNumber(metrics.latest.min) }}</p>
                            </div>
                            <div class="pl-2 border-l-[3px] border-white/60">
                                <p class="text-[11px] font-bold text-blue-200 uppercase tracking-wider mb-0.5">MAX</p>
                                <p class="text-2xl font-bold tabular-nums tracking-tight">{{ formatNumber(metrics.latest.max) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- BOX 3: ANO -->
                    <div class="bg-white p-4 lg:p-5 rounded-3xl shadow-sm border border-slate-200 relative group overflow-hidden">
                        <div class="absolute right-0 top-0 w-32 h-32 bg-slate-50 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-blue-50/50 transition-colors"></div>
                        <div class="absolute right-4 top-4 bg-slate-100 text-slate-600 text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded-lg shadow-sm z-10 tabular-nums border border-slate-200">
                            {{ formatSpread(metrics.year.spread) }}%
                        </div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-[0.12em] mb-0.5 truncate pr-20 z-10 relative">
                            <span class="text-slate-500">{{ metrics.year.label?.split(':')[0] }}:</span> 
                            <span class="text-blue-600 ml-1">{{ metrics.year.label?.split(':')[1] }}</span>
                        </p>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-3 z-10 relative">{{ metrics.year.sub_label }}</p>
                        
                        <div class="space-y-4 pt-1 z-10 relative">
                            <div class="pl-2 border-l-[3px] border-blue-500/20">
                                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">MIN</p>
                                <p class="text-2xl font-bold text-slate-800 tabular-nums tracking-tight">{{ formatNumber(metrics.year.min) }}</p>
                            </div>
                            <div class="pl-2 border-l-[3px] border-blue-600">
                                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">MAX</p>
                                <p class="text-2xl font-bold text-blue-600 tabular-nums tracking-tight">{{ formatNumber(metrics.year.max) }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- BOX 4: DESDE -->
                    <div class="bg-white p-4 lg:p-5 rounded-3xl shadow-sm border border-slate-200 relative group overflow-hidden">
                        <div class="absolute right-0 top-0 w-32 h-32 bg-slate-50 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-blue-50/50 transition-colors"></div>
                        <div class="absolute right-4 top-4 bg-slate-100 text-rose-600 text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded-lg shadow-sm z-10 tabular-nums border border-slate-200">
                            {{ formatSpread(metrics.all.spread) }}%
                        </div>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-[0.12em] mb-0.5 truncate pr-20 z-10 relative">
                            <span class="text-slate-500">{{ metrics.all.label?.split(':')[0] }}:</span> 
                            <span class="text-blue-600 ml-1">{{ metrics.all.label?.split(':')[1] }}</span>
                        </p>
                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-3 z-10 relative">{{ metrics.all.sub_label }}</p>
                        
                        <div class="space-y-4 pt-1 z-10 relative">
                            <div class="pl-2 border-l-[3px] border-blue-500/20">
                                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">MIN</p>
                                <p class="text-2xl font-bold text-slate-800 tabular-nums tracking-tight">{{ formatNumber(metrics.all.min) }}</p>
                            </div>
                            <div class="pl-2 border-l-[3px] border-blue-600">
                                <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">MAX</p>
                                <p class="text-2xl font-bold text-blue-600 tabular-nums tracking-tight">{{ formatNumber(metrics.all.max) }}</p>
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
                        <div class="absolute left-0 top-0 bottom-12 w-20 flex flex-col justify-between text-sm font-bold text-slate-400 tabular-nums pb-2">
                           <span>{{ formatNumber(metrics.all.max, 0) }}</span>
                           <span>{{ getAvgLabel(metrics.all.min, metrics.all.max) }}</span>
                           <span>{{ formatNumber(metrics.all.min, 0) }}</span>
                        </div>

                        <!-- CHART AREA -->
                        <div class="ml-20 flex-1 relative border-l border-b border-slate-100 mb-12 min-h-0">
                           <Line 
                              v-if="chartData && Object.keys(chartData).length"
                              :key="chartMode"
                              :data="chartDataComputed" 
                              :options="chartOptions"
                           />
                        </div>

                    </div>

                    <!-- LEGEND -->
                    <div v-if="chartMode !== 'CONTÍNUO'" class="mt-4 flex flex-wrap justify-center gap-6 pt-4 border-t border-slate-50 items-center">
                       <div v-for="(yearsValue, yearKey) in chartData" :key="yearKey" @click="toggleYear(yearKey)" class="flex items-center gap-2 group cursor-pointer transition-opacity duration-300" :class="{ 'opacity-30': !visibleYears.includes(yearKey), 'grayscale opacity-40': yearKey != Math.max(...Object.keys(props.chartData || {}).map(Number)) }">
                          <div class="w-3 h-3 rounded-full" :style="{ backgroundColor: yearColors[yearKey] }"></div>
                          <span class="text-sm font-bold text-slate-600 uppercase tracking-widest group-hover:text-blue-600 transition-colors flex items-center gap-2">
                             {{ yearKey }}
                             <Lock v-if="yearKey != Math.max(...Object.keys(props.chartData || {}).map(Number))" class="w-3 h-3 text-slate-400" />
                          </span>
                       </div>
                    </div>
                </div>
            </div>

            <!-- EMPTY STATE PARA FILTROS FALTANTES -->
            <div v-else class="bg-white border border-dashed border-slate-300 rounded-3xl p-16 text-center flex flex-col items-center justify-center min-h-[50vh]">
                <div class="w-20 h-20 bg-slate-50 text-slate-300 border border-slate-100 rounded-full flex items-center justify-center mb-6 shadow-sm">
                    <SearchIcon class="w-8 h-8"/>
                </div>
                <h3 class="text-xl font-bold text-slate-800 uppercase tracking-tight mb-2">Selecione um Produto</h3>
                <p class="text-xs font-semibold text-slate-400 max-w-sm leading-relaxed uppercase tracking-widest">Utilize os filtros à esquerda para escolher o país e o produto correspondente para analisar os dados.</p>
            </div>

        </div>

    <!-- Upgrade / Conversion Modal -->
    <UpgradeModal 
        :show="isUpgradeModalOpen"
        :title="upgradeModalContent.title"
        :message="upgradeModalContent.message"
        @close="isUpgradeModalOpen = false"
    />
  </DashboardLayout>
</template>
