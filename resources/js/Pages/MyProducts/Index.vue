<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Package, ChevronRight, TrendingUp, ArrowUpRight, Ship } from 'lucide-vue-next';
import ContractsFilterBar from '@/Pages/Contracts/Partials/ContractsFilterBar.vue';
import { formatDate, getStatusConfig } from '@/Utils/contracts';

const props = defineProps({
  exportProcesses: Object,
  summary: Object,
  filters: Object,
  activeFilters: Object,
  statusOptions: { type: Array, default: () => [] },
  contractProducts: { type: Array, default: () => [] },
  countries: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

// Groups shown in the client portal. Exporter/importer/seller, logistics delay
// and commission filtering are internal concerns.
const filterFields = ['identification', 'goods', 'status', 'situation'];
const situationItems = ['active', 'incident'];

const getOperationSigla = (process) => {
  if (user.value?.client_id && process.importer_id === user.value.client_id) {
    return { sigla: 'IMP', label: 'Importação', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
  }
  if (user.value?.client_id && process.exporter_id === user.value.client_id) {
    return { sigla: 'EXP', label: 'Exportação', class: 'bg-blue-50 text-blue-700 border-blue-200' };
  }
  return { sigla: 'IMP', label: 'Importação', class: 'bg-emerald-50 text-emerald-700 border-emerald-200' };
};

const isEtaOverdue = (process) => {
  if (!process?.eta_date) return false;
  if (String(process.status || '').toLowerCase().includes('finalizado')) return false;

  const eta = new Date(`${String(process.eta_date).slice(0, 10)}T00:00:00`);
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  return eta < today;
};

const changePage = (url) => {
  if (!url) return;
  router.visit(url, { preserveState: true, preserveScroll: true });
};
</script>

<template>
  <Head title="Meus Contratos" />

  <DashboardLayout>
    <div class="px-6 py-7 md:px-8 w-full max-w-none space-y-6">

      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 pb-5 border-b border-slate-200">
        <div>
          <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tighter">Meus Contratos</h1>
          <p class="text-sm font-medium text-slate-500 mt-1">
            Acompanhe a fabricação, envio, status de embarque e documentos das suas encomendas.
          </p>
        </div>
        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-white border border-slate-200 rounded-xl px-4 py-2 shadow-sm">
          {{ summary?.total_contracts || 0 }} contratos · {{ exportProcesses?.total || 0 }} itens
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm px-4 py-3.5 flex items-center gap-3 relative overflow-hidden group">
          <div class="absolute right-0 top-0 w-48 h-48 bg-blue-50/60 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-blue-100/60 transition-colors pointer-events-none"></div>
          <div class="w-10 h-10 rounded-xl bg-blue-600/10 border border-blue-500/20 flex items-center justify-center shrink-0">
            <Package class="w-5 h-5 text-blue-600" />
          </div>
          <div class="relative z-10 min-w-0">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider leading-none mb-1.5">Total de Contratos</p>
            <span class="text-xl font-black text-slate-800 tabular-nums leading-none">{{ summary.total_contracts || 0 }}</span>
          </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm px-4 py-3.5 flex items-center gap-3 relative overflow-hidden group">
          <div class="absolute right-0 top-0 w-48 h-48 bg-emerald-50/60 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-emerald-100/60 transition-colors pointer-events-none"></div>
          <div class="w-10 h-10 rounded-xl bg-emerald-600/10 border border-emerald-500/20 flex items-center justify-center shrink-0">
            <TrendingUp class="w-5 h-5 text-emerald-600" />
          </div>
          <div class="relative z-10 min-w-0">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider leading-none mb-1.5">Volume Total Contratado</p>
            <div class="flex items-baseline gap-1.5 whitespace-nowrap">
              <span class="text-xl font-black text-slate-800 tabular-nums leading-none">
                {{ Number(summary.total_tons || 0).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }}
              </span>
              <span class="text-[11px] font-bold text-slate-400 uppercase">ton</span>
            </div>
          </div>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm px-4 py-3.5 flex items-center gap-3 relative overflow-hidden group">
          <div class="absolute right-0 top-0 w-48 h-48 bg-violet-50/60 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-violet-100/60 transition-colors pointer-events-none"></div>
          <div class="w-10 h-10 rounded-xl bg-violet-600/10 border border-violet-500/20 flex items-center justify-center shrink-0">
            <Ship class="w-5 h-5 text-violet-600" />
          </div>
          <div class="relative z-10 min-w-0">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider leading-none mb-1.5">Contratos em Andamento</p>
            <span class="text-xl font-black text-slate-800 tabular-nums leading-none">{{ summary.active_shipments || 0 }}</span>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-visible flex flex-col">
        <ContractsFilterBar
          :filters="filters"
          :fields="filterFields"
          route-name="my-products.index"
          :situation-items="situationItems"
          :status-options="statusOptions"
          :contract-products="contractProducts"
          :countries="countries"
        />

        <div class="overflow-x-auto flex-1">
          <table class="w-full text-left border-collapse min-w-[900px]">
            <thead>
              <tr class="border-b border-slate-100 bg-slate-50">
                <th class="px-4 py-2.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Data</th>
                <th class="px-4 py-2.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Nº Contrato</th>
                <th class="px-4 py-2.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-center">Tipo</th>
                <th class="px-4 py-2.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider">Produto</th>
                <th class="px-4 py-2.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">Quantidade</th>
                <th class="px-4 py-2.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">Preço Ton US$</th>
                <th class="px-4 py-2.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider">STATUS</th>
                <th class="px-4 py-2.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider">ETD</th>
                <th class="px-4 py-2.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider">ETA</th>
                <th class="w-14"></th>
              </tr>
            </thead>

            <tbody class="divide-y divide-slate-50">
              <tr
                v-for="contract in exportProcesses.data"
                :key="contract.id"
                @click="router.visit(route('my-products.show', contract.id))"
                class="hover:bg-blue-50/50 cursor-pointer transition-colors duration-100 group"
              >
                <td class="px-4 py-2.5 text-sm font-semibold text-slate-600 whitespace-nowrap tabular-nums">
                  {{ formatDate(contract.date) }}
                </td>

                <td class="px-4 py-2.5 whitespace-nowrap">
                  <span class="text-sm font-bold text-blue-600 group-hover:text-blue-700 transition-colors">
                    {{ contract.contract_number || '—' }}
                  </span>
                </td>

                <td class="px-4 py-2.5 whitespace-nowrap text-center">
                  <span
                    class="inline-flex items-center justify-center px-2 py-0.5 rounded text-[11px] font-black uppercase tracking-wider border"
                    :class="getOperationSigla(contract).class"
                    :title="getOperationSigla(contract).label"
                  >{{ getOperationSigla(contract).sigla }}</span>
                </td>

                <td class="px-4 py-2.5 text-sm font-semibold text-slate-800 max-w-[240px] truncate">
                  <span :title="contract.product?.name">{{ contract.product?.name || '—' }}</span>
                </td>

                <td class="px-4 py-2.5 text-sm font-bold text-slate-800 text-right tabular-nums whitespace-nowrap">
                  {{ contract.quantity_tons ? Number(contract.quantity_tons).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) : '—' }}
                </td>

                <td class="px-4 py-2.5 text-sm font-bold text-slate-800 text-right tabular-nums whitespace-nowrap">
                  {{ contract.price_per_ton_usd ? `$ ${Number(contract.price_per_ton_usd).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}` : '—' }}
                </td>

                <td class="px-4 py-2.5 whitespace-nowrap">
                  <span
                    v-if="contract.status"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-semibold normal-case border"
                    :class="getStatusConfig(contract.status).class"
                  >
                    <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="getStatusConfig(contract.status).dot"></span>
                    {{ contract.status }}
                  </span>
                  <span v-else class="text-sm text-slate-300 font-medium">—</span>
                </td>

                <td class="px-4 py-2.5 text-sm font-semibold text-slate-600 whitespace-nowrap tabular-nums">
                  {{ formatDate(contract.etd_date) }}
                </td>

                <td
                  class="px-4 py-2.5 text-sm whitespace-nowrap tabular-nums"
                  :class="isEtaOverdue(contract) ? 'font-bold text-rose-500 bg-rose-50/30' : 'font-semibold text-slate-600'"
                >
                  {{ formatDate(contract.eta_date) }}
                </td>

                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                  <button
                    class="opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity p-2 rounded-xl hover:bg-blue-100 text-blue-500"
                    title="Ver Detalhes"
                  >
                    <ArrowUpRight class="w-4 h-4" />
                  </button>
                </td>
              </tr>

              <tr v-if="!exportProcesses.data?.length">
                <td colspan="10" class="px-6 py-20 text-center">
                  <div class="inline-flex flex-col items-center gap-4">
                    <div class="w-16 h-16 rounded-3xl bg-slate-100 flex items-center justify-center">
                      <Package class="w-8 h-8 text-slate-400" />
                    </div>
                    <div>
                      <p class="text-sm font-bold text-slate-500">Nenhum contrato encontrado</p>
                      <p class="text-xs text-slate-400 mt-1">Ajuste os filtros ou aguarde o registro dos seus contratos.</p>
                    </div>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div
          v-if="exportProcesses.links?.length > 3"
          class="px-5 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between gap-4"
        >
          <p class="text-xs font-bold text-slate-500">
            Exibindo
            <span class="text-blue-600 font-black">{{ exportProcesses.from || 0 }}</span>–<span class="text-blue-600 font-black">{{ exportProcesses.to || 0 }}</span>
            de <span class="text-slate-700 font-black">{{ exportProcesses.total || 0 }}</span> itens
          </p>

          <div class="flex items-center gap-1">
            <template v-for="(link, i) in exportProcesses.links" :key="i">
              <button
                v-if="i === 0"
                @click="changePage(link.url)"
                :disabled="!link.url"
                class="p-2 rounded-xl border text-slate-500 transition-all"
                :class="!link.url ? 'opacity-30 cursor-not-allowed border-slate-100' : 'border-slate-200 bg-white hover:border-blue-400 hover:text-blue-600'"
              >
                <ChevronRight class="w-4 h-4 rotate-180" />
              </button>

              <button
                v-else-if="i === exportProcesses.links.length - 1"
                @click="changePage(link.url)"
                :disabled="!link.url"
                class="p-2 rounded-xl border text-slate-500 transition-all"
                :class="!link.url ? 'opacity-30 cursor-not-allowed border-slate-100' : 'border-slate-200 bg-white hover:border-blue-400 hover:text-blue-600'"
              >
                <ChevronRight class="w-4 h-4" />
              </button>

              <button
                v-else
                @click="changePage(link.url)"
                :disabled="!link.url"
                v-html="link.label"
                class="min-w-[34px] h-8 px-2.5 text-xs font-bold rounded-xl border transition-all"
                :class="link.active
                  ? 'bg-blue-600 text-white border-blue-600 shadow-sm shadow-blue-200'
                  : link.url
                    ? 'bg-white text-slate-600 border-slate-200 hover:border-blue-400 hover:text-blue-600'
                    : 'text-slate-300 border-slate-100 cursor-not-allowed'"
              />
            </template>
          </div>
        </div>
      </div>

    </div>
  </DashboardLayout>
</template>
