<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Package, Search, ChevronRight, TrendingUp, ShieldAlert, ArrowUpRight, Ship } from 'lucide-vue-next';

const props = defineProps({
  exportProcesses: Object,
  summary: Object,
  filters: Object,
  warning: String,
});

const search = ref(props.filters?.search || '');
let searchTimeout = null;

const handleSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get(window.location.pathname, { search: search.value }, {
      preserveState: true,
      replace: true,
    });
  }, 350);
};

const getStatusConfig = (status) => {
  if (!status) return { class: 'bg-slate-100 text-slate-600 border-slate-200', dot: 'bg-slate-400' };
  const s = status.toLowerCase();
  if (s.includes('finalizado')) return { class: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' };
  if (s.includes('invoice')) return { class: 'bg-blue-50 text-blue-700 border-blue-200', dot: 'bg-blue-500' };
  if (s.includes('atraso') || s.includes('falta')) return { class: 'bg-rose-50 text-rose-700 border-rose-200', dot: 'bg-rose-500' };
  if (s.includes('transbordo') || s.includes('chegou')) return { class: 'bg-violet-50 text-violet-700 border-violet-200', dot: 'bg-violet-500' };
  if (s.includes('embarcar')) return { class: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' };
  return { class: 'bg-slate-100 text-slate-600 border-slate-200', dot: 'bg-slate-400' };
};

const formatDate = (d) => {
  if (!d) return '—';
  const date = new Date(d);
  return isNaN(date.getTime()) ? d : date.toLocaleDateString('pt-BR');
};
</script>

<template>
  <Head title="Meus Contratos" />

  <DashboardLayout>
    <div class="px-6 py-7 md:px-8 w-full max-w-none space-y-6">

      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 pb-5 border-b border-slate-200">
        <div>
          <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tighter">Meus Contratos</h1>
          <p class="text-sm font-medium text-slate-500 mt-1">Acompanhe a fabricação, envio, status de embarque e documentos das suas encomendas.</p>
        </div>
      </div>

      <!-- Warning if user not linked to any client -->
      <div v-if="warning" class="bg-amber-50 border border-amber-200 rounded-3xl p-5 flex items-start gap-4">
        <ShieldAlert class="w-6 h-6 text-amber-600 shrink-0 mt-0.5" />
        <div>
          <h3 class="text-sm font-bold text-amber-800 uppercase tracking-wider">Atenção</h3>
          <p class="text-sm font-semibold text-amber-700 mt-1">{{ warning }}</p>
        </div>
      </div>

      <template v-else>
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <!-- Total Contracts -->
          <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex items-center gap-5 relative overflow-hidden group">
            <div class="absolute right-0 top-0 w-56 h-56 bg-blue-50/60 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-blue-100/60 transition-colors pointer-events-none"></div>
            <div class="w-14 h-14 rounded-2xl bg-blue-600/10 border border-blue-500/20 flex items-center justify-center shrink-0">
              <Package class="w-7 h-7 text-blue-600" />
            </div>
            <div class="relative z-10 min-w-0">
              <p class="text-xs font-bold text-slate-400 uppercase tracking-widest leading-none mb-2">Total de Contratos</p>
              <span class="text-3xl font-black text-slate-800">{{ summary.total_contracts || 0 }}</span>
            </div>
          </div>

          <!-- Total Tons -->
          <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex items-center gap-5 relative overflow-hidden group">
            <div class="absolute right-0 top-0 w-56 h-56 bg-emerald-50/60 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-emerald-100/60 transition-colors pointer-events-none"></div>
            <div class="w-14 h-14 rounded-2xl bg-emerald-600/10 border border-emerald-500/20 flex items-center justify-center shrink-0">
              <TrendingUp class="w-7 h-7 text-emerald-600" />
            </div>
            <div class="relative z-10 min-w-0">
              <p class="text-xs font-bold text-slate-400 uppercase tracking-widest leading-none mb-2">Volume Total Contratado</p>
              <div class="flex items-baseline gap-2">
                <span class="text-3xl font-black text-slate-800">{{ Number(summary.total_tons || 0).toLocaleString('pt-BR') }}</span>
                <span class="text-sm font-bold text-slate-400 uppercase">ton</span>
              </div>
            </div>
          </div>

          <!-- Active Shipments -->
          <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex items-center gap-5 relative overflow-hidden group">
            <div class="absolute right-0 top-0 w-56 h-56 bg-violet-50/60 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-violet-100/60 transition-colors pointer-events-none"></div>
            <div class="w-14 h-14 rounded-2xl bg-violet-600/10 border border-violet-500/20 flex items-center justify-center shrink-0">
              <Ship class="w-7 h-7 text-violet-600" />
            </div>
            <div class="relative z-10 min-w-0">
              <p class="text-xs font-bold text-slate-400 uppercase tracking-widest leading-none mb-2">Containers em Trânsito</p>
              <span class="text-3xl font-black text-slate-800">{{ summary.active_shipments || 0 }}</span>
            </div>
          </div>
        </div>

        <!-- Table Card -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
          <!-- Toolbar -->
          <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
            <div class="relative flex-1 max-w-sm">
              <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
              <input
                v-model="search"
                @input="handleSearch"
                type="text"
                placeholder="Buscar por contrato ou produto..."
                class="w-full pl-10 pr-4 py-2.5 text-sm font-medium text-slate-700 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all placeholder:text-slate-400"
              />
            </div>
          </div>

          <!-- Table -->
          <div class="overflow-x-auto flex-1">
            <table class="w-full text-left border-collapse min-w-[800px]">
              <thead>
                <tr class="border-b border-slate-100 bg-slate-50">
                  <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Data</th>
                  <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Nº Contrato</th>
                  <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Produto</th>
                  <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Qtd (Ton)</th>
                  <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider">Previsão Chegada (ETA)</th>
                  <th class="w-14"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-50">
                <tr
                  v-for="process in exportProcesses.data"
                  :key="process.id"
                  @click="router.visit(route('my-products.show', process.id))"
                  class="hover:bg-blue-50/40 cursor-pointer transition-colors duration-100 group"
                >
                  <td class="px-6 py-4 text-sm font-semibold text-slate-600 whitespace-nowrap tabular-nums">
                    {{ formatDate(process.date) }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="text-sm font-bold text-blue-600 group-hover:text-blue-700 transition-colors">
                      {{ process.contract_number || '—' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-sm font-semibold text-slate-800 max-w-[240px] truncate">
                    {{ process.product?.name || '—' }}
                  </td>
                  <td class="px-6 py-4 text-sm font-bold text-slate-800 text-right tabular-nums whitespace-nowrap">
                    {{ process.quantity_tons ? Number(process.quantity_tons).toLocaleString('pt-BR') : '—' }}
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span
                      v-if="process.status"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-wider border"
                      :class="getStatusConfig(process.status).class"
                    >
                      <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="getStatusConfig(process.status).dot"></span>
                      {{ process.status }}
                    </span>
                    <span v-else class="text-sm text-slate-300 font-medium">—</span>
                  </td>
                  <td class="px-6 py-4 text-sm font-semibold text-slate-600 whitespace-nowrap tabular-nums">
                    {{ formatDate(process.eta_date) }}
                  </td>
                  <td class="px-4 py-4 text-right whitespace-nowrap">
                    <button
                      class="opacity-0 group-hover:opacity-100 transition-opacity p-2 rounded-xl hover:bg-blue-100 text-blue-500"
                      title="Ver Detalhes"
                    >
                      <ArrowUpRight class="w-4 h-4" />
                    </button>
                  </td>
                </tr>

                <!-- Empty State -->
                <tr v-if="!exportProcesses.data?.length">
                  <td colspan="7" class="px-6 py-20 text-center">
                    <div class="inline-flex flex-col items-center gap-4">
                      <div class="w-16 h-16 rounded-3xl bg-slate-100 flex items-center justify-center">
                        <Package class="w-8 h-8 text-slate-400" />
                      </div>
                      <div>
                        <p class="text-sm font-bold text-slate-500">Nenhum contrato ativo encontrado</p>
                        <p class="text-xs text-slate-400 mt-1">Seus contratos aparecerão listados aqui assim que registrados no sistema.</p>
                      </div>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="exportProcesses.links?.length > 3" class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between gap-4">
            <p class="text-xs font-bold text-slate-500">
              Exibindo
              <span class="text-blue-600 font-black">{{ exportProcesses.from || 0 }}</span>–<span class="text-blue-600 font-black">{{ exportProcesses.to || 0 }}</span>
              de <span class="text-slate-700 font-black">{{ exportProcesses.total || 0 }}</span> registros
            </p>
            <div class="flex items-center gap-1">
              <template v-for="(link, i) in exportProcesses.links" :key="i">
                <button
                  v-if="i === 0"
                  @click="router.visit(link.url)"
                  :disabled="!link.url"
                  class="p-2 rounded-xl border text-slate-500 transition-all"
                  :class="!link.url ? 'opacity-30 cursor-not-allowed border-slate-100' : 'border-slate-200 bg-white hover:border-blue-400 hover:text-blue-600'"
                >
                  <ChevronRight class="w-4 h-4 rotate-180" />
                </button>
                <button
                  v-else-if="i === exportProcesses.links.length - 1"
                  @click="router.visit(link.url)"
                  :disabled="!link.url"
                  class="p-2 rounded-xl border text-slate-500 transition-all"
                  :class="!link.url ? 'opacity-30 cursor-not-allowed border-slate-100' : 'border-slate-200 bg-white hover:border-blue-400 hover:text-blue-600'"
                >
                  <ChevronRight class="w-4 h-4" />
                </button>
                <button
                  v-else
                  @click="router.visit(link.url)"
                  :disabled="!link.url"
                  v-html="link.label"
                  class="min-w-[34px] h-8 px-2.5 text-xs font-bold rounded-xl border transition-all"
                  :class="link.active
                    ? 'bg-blue-600 text-white border-blue-600 shadow-sm shadow-blue-200'
                    : link.url ? 'bg-white text-slate-600 border-slate-200 hover:border-blue-400 hover:text-blue-600'
                    : 'text-slate-300 border-slate-100 cursor-not-allowed'"
                />
              </template>
            </div>
          </div>
        </div>
      </template>

    </div>
  </DashboardLayout>
</template>
