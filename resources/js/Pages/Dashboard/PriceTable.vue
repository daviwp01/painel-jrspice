<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Menu, Search, MapPin, ArrowDownIcon, ArrowUpIcon, MinusIcon, StarIcon, ClockIcon, Truck, FileDown, Check, X, Loader2, ArrowUpDown } from 'lucide-vue-next';
import CountryFlag from '@/Components/CountryFlag.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import axios from 'axios';

const MapPinIcon = MapPin;
const TruckIcon = Truck;

const props = defineProps({
  currentPage: Object,
  countries: Array,
  suppliers: Array,
  products: Object,     // Now paginated
  filters: Object,
});

const selectedCountry = ref(props.filters.country_id || '');
const isLoading = ref(false);

// Export PDF Logic
const isExportModalOpen = ref(false);
const isDownloading = ref(false);
const selectedExportCountries = ref([]);
const selectAll = ref(false);

const toggleAllCountries = () => {
    if (selectAll.value) {
        selectedExportCountries.value = props.countries.map(c => c.id);
    } else {
        selectedExportCountries.value = [];
    }
};

const handleExportPdf = async () => {
    if (selectedExportCountries.value.length === 0) {
        alert('Por favor, selecione ao menos um país.');
        return;
    }

    isDownloading.value = true;
    try {
        const response = await axios.get(route('dashboard.export.prices'), {
            params: { country_ids: selectedExportCountries.value },
            responseType: 'blob'
        });
        
        // Se a resposta for JSON (erro), ler como texto
        if (response.data.type === 'application/json') {
            const text = await response.data.text();
            const errorData = JSON.parse(text);
            if (errorData.is_error) {
                alert(`ERRO TÉCNICO: ${errorData.message}\nNo arquivo: ${errorData.file}\nLinha: ${errorData.line}`);
                isDownloading.value = false;
                return;
            }
        }

        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        const day = String(new Date().getDate()).padStart(2, '0');
        const month = String(new Date().getMonth() + 1).padStart(2, '0');
        const year = new Date().getFullYear();
        link.setAttribute('download', `tabela-de-preco-jrspice-${day}-${month}-${year}.pdf`);
        document.body.appendChild(link);
        link.click();
        
        isExportModalOpen.value = false;
    } catch (e) {
        console.error(e);
        alert('Ocorreu um erro ao gerar o PDF. Verifique os logs do servidor.');
    } finally {
        isDownloading.value = false;
    }
};

watch(() => props.filters, (newFilters) => {
    selectedCountry.value = newFilters.country_id || '';
}, { deep: true });

const handleCountryChange = () => {
    applyFilters();
};

const applyFilters = () => {
    isLoading.value = true;
    router.get(
        route('dashboard.page', { slug: props.currentPage.slug }),
        { 
            country_id: selectedCountry.value,
            sort_field: props.filters.sort_field,
            sort_direction: props.filters.sort_direction
        },
        { 
            preserveState: true, 
            replace: true,
            onFinish: () => { isLoading.value = false; }
        }
    );
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
            sort_field: field,
            sort_direction: direction
        },
        { preserveState: true, replace: true, onFinish: () => { isLoading.value = false; } }
    );
};

const currentCountryData = computed(() => {
    return props.countries.find(c => c.id == selectedCountry.value) || {};
});

const processedProducts = computed(() => {
    const list = props.products.data || [];
    return list.map(p => {
        const prices = p.prices || [];
        const latestInfo = prices[0];
        
        // Extrai apenas a porção da DATA (YYYY-MM-DD) para ignorar variações de hora do mesmo dia
        const latestDateStr = latestInfo && latestInfo.date ? latestInfo.date.substring(0, 10) : null;
        
        // Busca o primeiro preço que pertença a um DIA diferente do último
        const previousInfo = prices.find(pr => {
            const prDateStr = pr.date ? pr.date.substring(0, 10) : null;
            return prDateStr !== latestDateStr;
        });

        const latestPrice = latestInfo ? parseFloat(latestInfo.price) : null;
        const previousPrice = previousInfo ? parseFloat(previousInfo.price) : latestPrice;

        let variation = 0;
        if (latestPrice && previousPrice && previousPrice > 0) {
            variation = ((latestPrice - previousPrice) / previousPrice) * 100;
        }

        let status = 'none';
        if (variation > 0) status = 'up';
        else if (variation < 0) status = 'down';
        
        if (!previousInfo && latestInfo) status = 'new';

        return {
            ...p,
            latestPrice,
            previousPrice,
            variation,
            status
        };
    });
});

const formatPaginationLabel = (label) => {
    if (!label) return '';
    const l = label.toLowerCase();
    if (l.includes('previous')) return '&laquo; Anterior';
    if (l.includes('next')) return 'Próximo &raquo;';
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
         <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em] mb-4 px-3 flex items-center justify-between">
             <span class="flex items-center gap-2"><span class="w-1 h-1 rounded-full bg-blue-500"></span> Filtros de Busca</span>
             <Loader2 v-if="isLoading" class="w-3 h-3 text-blue-500 animate-spin" />
         </p>
         
         <div class="space-y-5">
            <SearchableSelect 
               v-model="selectedCountry"
               :options="countries"
               label="País"
               placeholder="Selecione o País"
               :icon="MapPinIcon"
               :with-flag="true"
               variant="dark"
               direction="up"
               @change="handleCountryChange"
            />
          </div>
      </div>
    </template>

    <div class="px-4 py-6 md:p-8 space-y-6 w-full max-w-none">
        


        <!-- Page Title Region -->
        <div class="hidden md:flex justify-between items-end pb-4 border-b border-slate-200 mb-8">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 tracking-tight uppercase">{{ currentPage.title }}</h2>
                <p class="text-[10px] font-bold text-slate-500 mt-2 uppercase tracking-widest rounded-full border border-slate-200 bg-white inline-block px-3 py-1 shadow-sm flex items-center gap-2">
                    <ClockIcon class="w-3 h-3 text-slate-400" /> Atualizado em: <span class="text-blue-600">{{ new Date().toLocaleString('pt-BR') }}</span>
                </p>
            </div>
            <div class="flex items-center gap-4">
                <button 
                    @click="isExportModalOpen = true"
                    class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-bold text-[10px] uppercase tracking-widest shadow-lg shadow-blue-200 transition-all active:scale-95"
                >
                    <FileDown class="w-4 h-4" /> Exportar PDF
                </button>

            </div>
        </div>



        <div class="transition-opacity duration-300" :class="{ 'opacity-50 pointer-events-none': isLoading }">
            
            <div class="flex flex-col lg:flex-row gap-4 mb-4 mt-2 w-full">
                <!-- PAÍS DE ORIGEM -->
                <div class="w-full lg:w-[40%] bg-white p-4 md:p-5 rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden flex flex-col justify-center min-h-[110px] group">
                    <div class="absolute right-0 top-0 w-64 h-64 bg-blue-50/50 rounded-full blur-3xl -mr-20 -mt-20 group-hover:bg-blue-100/50 transition-colors"></div>
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3 relative z-10 w-full flex items-center gap-2">
                        PAÍS DE ORIGEM
                    </p>
                    <div class="flex items-center gap-5 relative z-10">
                        <CountryFlag v-if="currentCountryData.name" :name="currentCountryData.name" class-name="w-14 h-10 object-cover" />
                        <h2 class="text-3xl md:text-5xl font-bold text-slate-800 tracking-tight uppercase break-all w-full leading-tight">
                            {{ currentCountryData.name || 'Selecione O País' }}
                        </h2>
                    </div>
                </div>

                <!-- LEGENDA -->
                <div class="w-full lg:w-[60%] bg-white p-4 md:p-5 rounded-3xl shadow-sm border border-slate-200 relative min-h-[110px] flex flex-col justify-center">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">LEGENDA DE VARIAÇÃO</p>
                    <div class="flex flex-col lg:flex-row gap-6 lg:items-center justify-between w-full">
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 lg:gap-x-16 gap-y-8 whitespace-nowrap mt-2 overflow-visible">
                            <div class="flex items-center gap-4 text-sm font-bold text-emerald-600 uppercase tracking-wider"><ArrowDownIcon class="text-emerald-500 w-5 h-5 stroke-[3] shrink-0" /> PREÇO CAIU</div>
                            <div class="flex items-center gap-4 text-sm font-bold text-rose-600 uppercase tracking-wider pl-4 sm:pl-0"><ArrowUpIcon class="text-rose-500 w-5 h-5 stroke-[3] shrink-0" /> PREÇO SUBIU</div>
                            <div class="flex items-center gap-4 text-sm font-bold text-slate-500 uppercase tracking-wider"><MinusIcon class="bg-slate-200 text-slate-400 rounded-full w-5 h-5 p-0.5 shrink-0" /> SEM ALTERAÇÕES</div>
                            <div class="flex items-center gap-4 text-sm font-bold text-amber-500 uppercase tracking-wider pl-4 sm:pl-0"><StarIcon class="text-amber-400 fill-amber-400 w-5 h-5 shrink-0" /> PRODUTO NOVO</div>
                        </div>

                        <div class="text-[10px] sm:text-[11px] font-bold text-slate-500 uppercase tracking-widest space-y-4 lg:border-l-2 lg:border-slate-100 lg:pl-8 leading-relaxed hidden sm:flex flex-col justify-center shrink-0">
                            <p class="flex items-center gap-3"><span class="w-1.5 h-1.5 bg-slate-300 rounded-full shrink-0"></span> Em relação ao preço anterior</p>
                            <p class="flex items-center gap-3"><span class="w-1.5 h-1.5 bg-slate-300 rounded-full shrink-0"></span> Preços em Dólar p/ ton 1XFCL 40'</p>
                            <p class="flex items-center gap-3"><span class="w-1.5 h-1.5 bg-slate-300 rounded-full shrink-0"></span> Preços à confirmação final</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABELA -->
            <div class="overflow-x-auto bg-white rounded-3xl shadow-sm border border-slate-200">
                <table class="w-full text-lg text-left whitespace-nowrap">
                    <thead class="text-sm text-slate-500 bg-white/95 backdrop-blur-sm font-bold uppercase tracking-widest border-b border-slate-200 sticky top-0 z-20">
                        <tr>
                            <th class="px-5 py-4 cursor-pointer hover:bg-slate-50 transition-colors group" @click="handleSort('name')">
                                <div class="flex items-center gap-2">
                                    PRODUTO
                                    <ArrowUpDown :class="filters.sort_field === 'name' ? 'text-blue-600' : 'text-slate-300 opacity-0 group-hover:opacity-100'" class="w-3.5 h-3.5 transition-all" />
                                </div>
                            </th>
                            <th class="px-5 py-4 text-right cursor-pointer hover:bg-slate-50 transition-colors group" @click="handleSort('latest_price')">
                                <div class="flex items-center justify-end gap-2">
                                    ÚLTIMO MELHOR PREÇO
                                    <ArrowUpDown :class="filters.sort_field === 'latest_price' ? 'text-blue-600' : 'text-slate-300 opacity-0 group-hover:opacity-100'" class="w-3.5 h-3.5 transition-all" />
                                </div>
                            </th>
                            <th class="px-5 py-4 text-right cursor-not-allowed text-slate-300">
                                MELHOR PREÇO ANTERIOR
                            </th>
                            <th class="px-5 py-4 text-right cursor-pointer hover:bg-slate-50 transition-colors group" @click="handleSort('variation')">
                                <div class="flex items-center justify-end gap-2">
                                    VARIAÇÃO
                                    <ArrowUpDown :class="filters.sort_field === 'variation' ? 'text-blue-600' : 'text-slate-300 opacity-0 group-hover:opacity-100'" class="w-3.5 h-3.5 transition-all" />
                                </div>
                            </th>
                        </tr>
                    </thead>
                        <tbody class="divide-y divide-slate-100 font-bold bg-white">
                            <tr v-for="prod in processedProducts" :key="prod.id" class="hover:bg-slate-50/50 transition-colors group">
                                <td class="px-5 py-3.5 text-slate-900 group-hover:text-blue-600 transition-colors">{{ prod.name }}</td>
                                <td class="px-5 py-3.5 text-right tabular-nums text-slate-900 pr-6">{{ prod.latestPrice ? Number(prod.latestPrice).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '--' }}</td>
                                <td class="px-5 py-3.5 text-right tabular-nums text-slate-400 pr-6">{{ prod.previousPrice ? Number(prod.previousPrice).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '--' }}</td>
                                <td class="px-5 py-3.5 text-right tabular-nums">
                                    <div class="flex items-center justify-end gap-2 pr-2">
                                    <span class="font-bold tracking-tight" :class="prod.status === 'down' ? 'text-emerald-600' : (prod.status === 'up' ? 'text-rose-600' : 'text-slate-500')">
                                        {{ (prod.variation > 0 ? '+' : '') }}{{ prod.variation.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}%
                                    </span>
                                        <ArrowDownIcon v-if="prod.status === 'down'" class="text-emerald-500 w-5 h-5 stroke-[3]" />
                                        <ArrowUpIcon v-else-if="prod.status === 'up'" class="text-rose-500 w-5 h-5 stroke-[3]" />
                                        <StarIcon v-else-if="prod.status === 'new'" class="text-amber-400 fill-amber-400 w-4 h-4 ml-1" />
                                        <div v-else class="w-3.5 h-3.5 rounded-full bg-slate-300 ml-1"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!processedProducts.length">
                                <td colspan="4" class="px-6 py-12 text-center text-slate-400 font-medium uppercase tracking-widest text-[10px]">Sem dados inseridos para a semana atual.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="products.links?.length > 3" class="mt-8 flex flex-col md:flex-row items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                        Exibindo <span class="text-blue-600">{{ products.from }}</span> até <span class="text-blue-600">{{ products.to }}</span> de <span class="text-slate-900">{{ products.total }}</span> produtos
                    </p>
                    <div class="flex gap-1">
                        <button v-for="(link, i) in products.links" :key="i" v-html="formatPaginationLabel(link.label)" @click="changePage(link.url)" :disabled="!link.url"
                            :class="['px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest rounded-xl border transition-all', link.active ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-100' : link.url ? 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' : 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed']" />
                    </div>
                </div>

        </div>
    </div>
  </DashboardLayout>

  <!-- LOADING OVERLAY MINIMALISTA -->
  <div v-if="isDownloading" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/20">
      <div class="bg-white/90 border border-slate-200 backdrop-blur-sm p-6 rounded-[2rem] shadow-2xl flex items-center gap-4 animate-in zoom-in-95 duration-300">
          <div class="relative w-10 h-10 flex items-center justify-center">
              <div class="absolute inset-0 border-2 border-slate-100 rounded-full"></div>
              <Loader2 class="w-6 h-6 text-blue-600 animate-spin" />
          </div>
          <div class="pr-2">
              <h3 class="text-sm font-black text-slate-800 uppercase tracking-widest whitespace-nowrap">Gerando PDF</h3>
              <p class="text-[9px] font-bold text-slate-500 uppercase tracking-[0.2em] leading-none mt-0.5">Aguarde um instante</p>
          </div>
      </div>
  </div>

  <!-- MODAL DE EXPORTAÇÃO -->
  <div v-if="isExportModalOpen" class="fixed inset-0 z-[90] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/70" @click="isExportModalOpen = false"></div>
      
      <div class="bg-white w-full max-w-3xl rounded-[32px] shadow-2xl relative overflow-hidden flex flex-col max-h-[90vh] animate-in zoom-in-95 duration-200">
          <div class="p-8 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-10">
              <div>
                  <h3 class="text-xl font-bold text-slate-900 uppercase tracking-tighter">Exportar Tabela de Preços</h3>
                  <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-widest">Selecione os países para o relatório PDF</p>
              </div>
              <button @click="isExportModalOpen = false" class="p-2 hover:bg-slate-100 rounded-full transition-colors">
                  <X class="w-6 h-6 text-slate-400" />
              </button>
          </div>

          <div class="p-8 overflow-y-auto">
              <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-50">
                  <span class="text-xs font-bold text-slate-700 uppercase tracking-widest">Lista de Países Disponíveis</span>
                  <label class="flex items-center gap-2 cursor-pointer group">
                      <input type="checkbox" v-model="selectAll" @change="toggleAllCountries" class="hidden">
                      <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-all" 
                           :class="selectAll ? 'bg-blue-600 border-blue-600' : 'border-slate-200 group-hover:border-blue-400'">
                          <Check v-if="selectAll" class="w-3.5 h-3.5 text-white stroke-[4]" />
                      </div>
                      <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Selecionar Todos</span>
                  </label>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div v-for="country in countries" :key="country.id" 
                       @click="!selectedExportCountries.includes(country.id) ? selectedExportCountries.push(country.id) : selectedExportCountries = selectedExportCountries.filter(id => id !== country.id)"
                       class="flex items-center gap-4 p-4 rounded-2xl border transition-all cursor-pointer group"
                       :class="selectedExportCountries.includes(country.id) ? 'bg-blue-50 border-blue-200 ring-1 ring-blue-200' : 'border-slate-100 hover:border-blue-100 hover:bg-slate-50'">
                      
                      <div class="w-5 h-5 rounded border-2 flex items-center justify-center transition-all shrink-0" 
                           :class="selectedExportCountries.includes(country.id) ? 'bg-blue-600 border-blue-600' : 'border-slate-200'">
                          <Check v-if="selectedExportCountries.includes(country.id)" class="w-3.5 h-3.5 text-white stroke-[4]" />
                      </div>

                      <div class="flex items-center gap-3">
                        <CountryFlag :name="country.name" class-name="w-6 h-4 object-cover rounded-[2px]" />
                        <span class="text-xs font-bold text-slate-700 uppercase tracking-tight group-hover:text-blue-600">{{ country.name }}</span>
                      </div>
                  </div>
              </div>
          </div>

          <div class="p-8 bg-slate-50 border-t border-slate-100 flex gap-4">
              <button @click="isExportModalOpen = false" class="flex-1 px-6 py-4 rounded-2xl font-bold text-[10px] uppercase tracking-widest text-slate-500 hover:bg-slate-200 transition-colors">
                  Cancelar
              </button>
              <button 
                  @click="handleExportPdf"
                  :disabled="selectedExportCountries.length === 0"
                  class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-6 py-4 rounded-2xl font-bold text-[10px] uppercase tracking-widest shadow-xl shadow-blue-200 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
              >
                  Gerar Relatório ({{ selectedExportCountries.length }})
              </button>
          </div>
      </div>
  </div>
</template>
