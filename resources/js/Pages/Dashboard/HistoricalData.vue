<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Menu, Search, MapPin, Box, Calendar, ClockIcon, ChevronDown, Check, Truck } from 'lucide-vue-next';
import CountryFlag from '@/Components/CountryFlag.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const MapPinIcon = MapPin;
const TruckIcon = Truck;
const BoxIcon = Box;
const CalendarIcon = Calendar;

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
});

const selectedCountry = ref(props.filters.country_id || '');
const selectedSupplier = ref(props.filters.supplier_id || '');
const selectedProduct = ref(props.filters.product_id || '');
const filterDateRange = ref(props.filters.date_range || 'Todos');
const isDatePickerOpen = ref(false);
const isLoading = ref(false);

watch(() => props.filters, (newFilters) => {
    selectedCountry.value = newFilters.country_id || '';
    selectedSupplier.value = newFilters.supplier_id || '';
    selectedProduct.value = newFilters.product_id || '';
    filterDateRange.value = newFilters.date_range || 'Todos';
}, { deep: true });

const handleCountryChange = () => {
    selectedProduct.value = ''; // Reset product to show all
    applyFilters();
};

const applyFilters = () => {
    isLoading.value = true;
    router.get(
        route('dashboard.page', { slug: props.currentPage.slug }),
        { 
            country_id: selectedCountry.value,
            supplier_id: selectedSupplier.value,
            product_id: selectedProduct.value,
            date_range: filterDateRange.value
        },
        { 
            preserveState: true, 
            replace: true,
            onFinish: () => { isLoading.value = false; }
        }
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
            rawDate: price.date,
            displayDate: new Date(price.date).toLocaleDateString('pt-BR', { timeZone: 'UTC' }),
            yearMonth: `${y}/${(d.getUTCMonth()+1).toString().padStart(2, '0')}`,
            week: w,
            priceVal: price.min_price || price.max_price
        };
    });
});

const formatPaginationLabel = (label) => {
    if (label.includes('Previous')) return '&laquo; Anterior';
    if (label.includes('Next')) return 'Próximo &raquo;';
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
      <div class="mt-8 border-t border-slate-100 pt-6">
         <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.1em] mb-4 flex items-center gap-2"><Search class="w-3 h-3"/> Filtros de Busca</p>
         
         <div class="space-y-5">
            <SearchableSelect 
               v-model="selectedCountry"
               :options="countries"
               label="País"
               placeholder="Selecione o País"
               :icon="MapPinIcon"
               @change="handleCountryChange"
            />

            <SearchableSelect 
               v-model="selectedSupplier"
               :options="suppliers"
               label="Fornecedor"
               placeholder="Todos os Fornecedores"
               :icon="TruckIcon"
               @change="applyFilters"
            />

            <SearchableSelect 
               v-model="selectedProduct"
               :options="products"
               label="Produto"
               placeholder="Todos os Produtos"
               :icon="BoxIcon"
               :disabled="!selectedCountry"
               direction="up"
               @change="applyFilters"
            />

            <div class="relative">
              <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1.5 flex items-center gap-1.5"><Calendar class="w-3 h-3"/> Data (Ano / Semana)</label>
              
              <!-- CUSTOM TREE SELECT -->
              <div class="relative group">
                <button @click="isDatePickerOpen = !isDatePickerOpen" class="w-full bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-800 font-bold px-3 py-2.5 flex items-center justify-between hover:bg-slate-100 transition-colors uppercase">
                  <span>{{ filterDateRange === 'Todos' ? 'Todos os Registros' : filterDateRange.replace('-', ' - Sem. ') }}</span>
                  <ChevronDown :class="{ 'rotate-180': isDatePickerOpen }" class="w-4 h-4 text-slate-400 transition-transform" />
                </button>

                <div v-if="isDatePickerOpen" class="absolute z-50 left-0 right-0 bottom-full mb-2 bg-white border border-slate-200 rounded-xl shadow-xl max-h-[300px] overflow-y-auto p-2 scrollbar-thin scrollbar-thumb-slate-200 animate-in fade-in slide-in-from-bottom-2 duration-200">
                   <div @click="() => { filterDateRange = 'Todos'; applyFilters(); isDatePickerOpen = false; }" class="flex items-center gap-2 p-2 hover:bg-blue-50 rounded-lg cursor-pointer text-xs font-black uppercase tracking-wider mb-2 border-b border-slate-100 pb-3" :class="{ 'text-blue-600 bg-blue-50/50': filterDateRange === 'Todos' }">
                      <div class="w-4 h-4 border-2 rounded flex items-center justify-center border-slate-300" :class="{ 'bg-blue-600 border-blue-600': filterDateRange === 'Todos' }">
                        <Check v-if="filterDateRange === 'Todos'" class="w-3 h-3 text-white stroke-[4]" />
                      </div>
                      TODOS OS REGISTROS
                   </div>

                   <div v-for="group in availableDates" :key="group.year" class="mb-4">
                      <div class="flex items-center gap-2 p-1 px-2 text-[11px] font-black text-slate-400 uppercase tracking-widest mb-2 border-l-2 border-slate-100 ml-1">
                        {{ group.year }}
                      </div>
                      <div class="space-y-1">
                        <div v-for="d in group.weeks" :key="d.year + '-' + d.week" @click="() => { filterDateRange = d.year + '-' + d.week; applyFilters(); isDatePickerOpen = false; }" class="flex items-center gap-3 p-2 hover:bg-slate-50 rounded-lg cursor-pointer text-[10px] font-bold uppercase tracking-wider" :class="{ 'text-blue-600 bg-blue-50/50': filterDateRange === (d.year + '-' + d.week) }">
                           <div class="w-4 h-4 border-2 rounded flex items-center justify-center border-slate-300 ml-4" :class="{ 'bg-blue-600 border-blue-600': filterDateRange === (d.year + '-' + d.week) }">
                              <Check v-if="filterDateRange === (d.year + '-' + d.week)" class="w-3 h-3 text-white stroke-[4]" />
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
        
        <!-- Mobile Nav Guide -->
        <div class="md:hidden flex items-center justify-between bg-blue-50/50 text-blue-700 p-4 rounded-xl shadow-sm border border-blue-100 mb-6 cursor-pointer" @click="$emit('open-mobile-menu')">
            <span class="text-[10px] font-black uppercase tracking-widest flex items-center gap-2">
                <Menu class="w-4 h-4"/> Menu de Filtros Adicionais
            </span>
        </div>

        <!-- Page Title Region -->
        <div class="hidden md:flex justify-between items-end pb-4 border-b border-slate-200 mb-8 mt-2">
            <div>
                <h2 class="text-2xl font-black text-slate-900 tracking-tight uppercase">{{ currentPage.title }}</h2>
                <p class="text-[10px] font-bold text-slate-500 mt-2 uppercase tracking-widest rounded-full border border-slate-200 bg-white inline-block px-3 py-1 shadow-sm flex items-center gap-2">
                    <ClockIcon class="w-3 h-3 text-slate-400" /> Atualizado em: <span class="text-blue-600">{{ new Date().toLocaleString('pt-BR') }}</span>
                </p>
            </div>
            <div class="text-[9px] font-black text-slate-400 uppercase tracking-widest">
                Gerenciador Analítico JRSpice
            </div>
        </div>

        <div class="transition-opacity duration-300" :class="{ 'opacity-50 pointer-events-none': isLoading }">

            <!-- TABELA HISTÓRICO -->
            <div class="overflow-x-auto bg-white rounded-3xl shadow-sm border border-slate-200">
                <table class="w-full text-sm text-left whitespace-nowrap">
                    <thead class="text-[10px] text-slate-500 bg-slate-50/80 font-black uppercase tracking-widest border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-5">PRODUTO</th>
                            <th class="px-6 py-5">PAÍS</th>
                            <th class="px-6 py-5">FORNECEDOR</th>
                            <th class="px-6 py-5">DATA REGISTRO</th>
                            <th class="px-6 py-5">ANO / MES</th>
                            <th class="px-6 py-5 text-center">SEMANA</th>
                            <th class="px-6 py-5 text-right">PREÇO</th>
                        </tr>
                    </thead>
                        <tbody class="divide-y divide-slate-100 font-medium bg-white">
                            <tr v-for="(row, idx) in processedHistoricalData" :key="idx" class="hover:bg-blue-50/30 transition-colors">
                                <td class="px-5 py-3 text-slate-800 font-bold uppercase tracking-wide text-xs">{{ row.productName }}</td>
                                <td class="px-5 py-3 text-slate-500 uppercase text-xs">
                                    <div class="flex items-center gap-2">
                                        <CountryFlag v-if="row.countryName" :name="row.countryName" class-name="w-4 h-3 rounded-[1px]" />
                                        {{ row.countryName }}
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-slate-500 uppercase text-xs">{{ row.supplier }}</td>
                                <td class="px-5 py-3 text-slate-500 text-xs">{{ row.displayDate }}</td>
                                <td class="px-5 py-3 text-slate-500 font-mono text-xs">{{ row.yearMonth }}</td>
                                <td class="px-5 py-3 text-slate-700 text-center font-bold text-xs">{{ row.week }}</td>
                                <td class="px-5 py-3 text-right tabular-nums font-bold text-slate-900 pr-4 md:pr-8">
                                    {{ row.priceVal ? Number(row.priceVal).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '--' }}
                                </td>
                            </tr>
                            <tr v-if="!processedHistoricalData.length">
                                <td colspan="7" class="px-6 py-12 text-center text-slate-400 font-medium uppercase tracking-widest text-[10px]">Nenhuma tabela de preço histórico localizada.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="historicalData.links?.length > 3" class="mt-8 flex flex-col md:flex-row items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                         Exibindo <span class="text-blue-600">{{ historicalData.from }}</span> até <span class="text-blue-600">{{ historicalData.to }}</span> de <span class="text-slate-900">{{ historicalData.total }}</span> registros
                    </p>
                    <div class="flex gap-1">
                        <button v-for="(link, i) in historicalData.links" :key="i" v-html="formatPaginationLabel(link.label)" @click="changePage(link.url)" :disabled="!link.url"
                            :class="['px-4 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-xl border transition-all', link.active ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-100' : link.url ? 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' : 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed']" />
                    </div>
                </div>

        </div>
    </div>
  </DashboardLayout>
</template>
