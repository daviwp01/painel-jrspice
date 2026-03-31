<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Menu, Search, MapPin, ArrowDownIcon, ArrowUpIcon, MinusIcon, StarIcon, ClockIcon, Truck } from 'lucide-vue-next';
import CountryFlag from '@/Components/CountryFlag.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';

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
const selectedSupplier = ref(props.filters.supplier_id || '');
const isLoading = ref(false);

watch(() => props.filters, (newFilters) => {
    selectedCountry.value = newFilters.country_id || '';
    selectedSupplier.value = newFilters.supplier_id || '';
}, { deep: true });

const applyFilters = () => {
    isLoading.value = true;
    router.get(
        route('dashboard.page', { slug: props.currentPage.slug }),
        { 
            country_id: selectedCountry.value,
            supplier_id: selectedSupplier.value
        },
        { 
            preserveState: true, 
            replace: true,
            onFinish: () => { isLoading.value = false; }
        }
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
        const previousInfo = prices[1];

        const latestPrice = latestInfo ? parseFloat(latestInfo.min_price) : null;
        const previousPrice = previousInfo ? parseFloat(previousInfo.min_price) : latestPrice;

        let variation = 0;
        if (latestPrice && previousPrice) {
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
                  <div class="space-y-4">
            <SearchableSelect 
               v-model="selectedCountry"
               :options="countries"
               label="País"
               placeholder="Selecione o País"
               :icon="MapPinIcon"
               @change="applyFilters"
            />

            <SearchableSelect 
               v-model="selectedSupplier"
               :options="suppliers"
               label="Fornecedor"
               placeholder="Todos os Fornecedores"
               :icon="TruckIcon"
               @change="applyFilters"
            />
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
        <div class="hidden md:flex justify-between items-end pb-4 border-b border-slate-200 mb-8">
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
            
            <div class="flex flex-col lg:flex-row gap-4 lg:gap-6 mb-8 mt-2 w-full">
                <!-- PAÍS DE ORIGEM -->
                <div class="w-full lg:w-[40%] bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden flex flex-col justify-center min-h-[140px] group">
                    <div class="absolute right-0 top-0 w-64 h-64 bg-blue-50/50 rounded-full blur-3xl -mr-20 -mt-20 group-hover:bg-blue-100/50 transition-colors"></div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 relative z-10 w-full flex items-center gap-2">
                        PAÍS DE ORIGEM
                    </p>
                    <div class="flex items-center gap-5 relative z-10">
                        <CountryFlag v-if="currentCountryData.name" :name="currentCountryData.name" class-name="w-14 h-10 object-cover" />
                        <h2 class="text-3xl md:text-5xl font-black text-slate-800 tracking-tight uppercase break-all w-full leading-tight">
                            {{ currentCountryData.name || 'Selecione O País' }}
                        </h2>
                    </div>
                </div>

                <!-- LEGENDA -->
                <div class="w-full lg:w-[60%] bg-white p-6 md:p-8 rounded-3xl shadow-sm border border-slate-200 relative min-h-[140px] flex flex-col justify-center">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-5">LEGENDA DE VARIAÇÃO</p>
                    <div class="flex flex-col lg:flex-row gap-8 lg:items-center justify-between w-full">
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-12 lg:gap-x-16 gap-y-8 whitespace-nowrap mt-2 overflow-visible">
                            <div class="flex items-center gap-4 text-sm font-black text-emerald-600 uppercase tracking-wider"><ArrowDownIcon class="text-emerald-500 w-5 h-5 stroke-[3] shrink-0" /> PREÇO CAIU</div>
                            <div class="flex items-center gap-4 text-sm font-black text-rose-600 uppercase tracking-wider pl-4 sm:pl-0"><ArrowUpIcon class="text-rose-500 w-5 h-5 stroke-[3] shrink-0" /> PREÇO SUBIU</div>
                            <div class="flex items-center gap-4 text-sm font-black text-slate-500 uppercase tracking-wider"><MinusIcon class="bg-slate-200 text-slate-400 rounded-full w-5 h-5 p-0.5 shrink-0" /> SEM ALTERAÇÕES</div>
                            <div class="flex items-center gap-4 text-sm font-black text-amber-500 uppercase tracking-wider pl-4 sm:pl-0"><StarIcon class="text-amber-400 fill-amber-400 w-5 h-5 shrink-0" /> PRODUTO NOVO</div>
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
                <table class="w-full text-sm text-left whitespace-nowrap">
                    <thead class="text-[10px] text-slate-500 bg-slate-50/80 font-black uppercase tracking-widest border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-5">PRODUTO</th>
                            <th class="px-6 py-5 text-right">ÚLTIMO MELHOR PREÇO</th>
                            <th class="px-6 py-5 text-right">MELHOR PREÇO ANTERIOR</th>
                            <th class="px-6 py-5 text-right">VARIAÇÃO</th>
                        </tr>
                    </thead>
                        <tbody class="divide-y divide-slate-100 font-bold bg-white">
                            <tr v-for="prod in processedProducts" :key="prod.id" class="hover:bg-slate-50/50 transition-colors group">
                                <td class="px-5 py-4 text-slate-900 group-hover:text-blue-600 transition-colors">{{ prod.name }}</td>
                                <td class="px-5 py-4 text-right tabular-nums text-slate-900 pr-6">{{ prod.latestPrice ? Number(prod.latestPrice).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '--' }}</td>
                                <td class="px-5 py-4 text-right tabular-nums text-slate-400 pr-6">{{ prod.previousPrice ? Number(prod.previousPrice).toLocaleString('pt-BR', {minimumFractionDigits: 2}) : '--' }}</td>
                                <td class="px-5 py-4 text-right tabular-nums">
                                    <div class="flex items-center justify-end gap-2 pr-2">
                                    <span class="font-black tracking-tight" :class="prod.status === 'down' ? 'text-emerald-600' : (prod.status === 'up' ? 'text-rose-600' : 'text-slate-500')">
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
                                <td colspan="4" class="px-6 py-12 text-center text-slate-400 font-medium uppercase tracking-widest text-[10px]">Nenhum dado registrado para este país.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="products.links?.length > 3" class="mt-8 flex flex-col md:flex-row items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-slate-200 shadow-sm">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        Exibindo <span class="text-blue-600">{{ products.from }}</span> até <span class="text-blue-600">{{ products.to }}</span> de <span class="text-slate-900">{{ products.total }}</span> produtos
                    </p>
                    <div class="flex gap-1">
                        <button v-for="(link, i) in products.links" :key="i" v-html="formatPaginationLabel(link.label)" @click="changePage(link.url)" :disabled="!link.url"
                            :class="['px-4 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-xl border transition-all', link.active ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-100' : link.url ? 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' : 'bg-slate-50 text-slate-300 border-slate-100 cursor-not-allowed']" />
                    </div>
                </div>

        </div>
    </div>
  </DashboardLayout>
</template>
