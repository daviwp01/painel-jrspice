<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import {
  Plus, ChevronLeft, ChevronRight, ChevronDown, Package,
  Columns3, Check, RotateCcw, Users, Trash2, X,
  ArrowUp, ArrowDown, ArrowUpDown, Ship,
} from 'lucide-vue-next';

const props = defineProps({
  rows: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  clients: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
  sellers: { type: Array, default: () => [] },
  contractProducts: { type: Array, default: () => [] },
  countries: { type: Array, default: () => [] },
});

// Mirrors ContractQueryService::SORTABLE on the back-end.
const SORTABLE_COLUMNS = ['date'];

const sortBy = computed(() => props.filters?.sort_by || 'date');
const sortDir = computed(() => (props.filters?.sort_dir === 'asc' ? 'asc' : 'desc'));

const isSortable = (key) => SORTABLE_COLUMNS.includes(key);
const isActiveSort = (key) => isSortable(key) && sortBy.value === key;

const toggleSort = (key) => {
  if (!isSortable(key)) return;

  const direction = isActiveSort(key) && sortDir.value === 'desc' ? 'asc' : 'desc';

  router.get(
    route('admin.clients.index'),
    { ...props.filters, sort_by: key, sort_dir: direction },
    { preserveState: true, preserveScroll: true, replace: true },
  );
};

import ContractsFilterBar from './ContractsFilterBar.vue';

const emit = defineEmits(['create', 'edit', 'tracking']);

const ALL_COLUMNS = [
  { key: 'date', label: 'Data', fixed: true, recommended: true },
  { key: 'contract_number', label: 'Contrato', fixed: true, recommended: true },
  { key: 'register_number', label: 'Registro', recommended: false },
  { key: 'exporter', label: 'Exportador', recommended: true },
  { key: 'importer', label: 'Importador', recommended: true },
  { key: 'country', label: 'País', recommended: true },
  { key: 'product', label: 'Produto', recommended: true },
  { key: 'quantity_tons', label: 'Quantidade', recommended: true, numeric: true },
  { key: 'price_per_ton_usd', label: 'Preço Ton US$', recommended: true, numeric: true },
  { key: 'sales_usd', label: 'Venda US$', recommended: true, numeric: true },
  { key: 'cumulative_sales_usd', label: 'Venda Anual US$', recommended: false, numeric: true },
  { key: 'commission_usd', label: 'Comissão US$', recommended: true, numeric: true },
  { key: 'cumulative_commission_usd', label: 'TT Comissão US$', recommended: false, numeric: true },
  { key: 'exchange_rate', label: 'Taxa de Câmbio', recommended: false, numeric: true },
  { key: 'estimated_euro', label: 'Estimado em Euro', recommended: false, numeric: true },
  { key: 'estimated_receipt_date', label: 'Data Estimada Recebimento', recommended: false },
  { key: 'seller', label: 'Vendedor', recommended: true },
  { key: 'to_pay_usd', label: 'A pagar', recommended: false, numeric: true },
  { key: 'receipt_date', label: 'Recebimento', recommended: false },
  { key: 'paid_in_date', label: 'Pago em', recommended: false },
  { key: 'paid_in_brl', label: 'Pago em Real', recommended: false, numeric: true },
  { key: 'incident', label: 'Incidente', recommended: false },
  { key: 'video_sent', label: 'Video enviado', recommended: false },
  { key: 'video_date', label: 'DATA VÍDEO', recommended: false },
  { key: 'status', label: 'STATUS', recommended: true },
  { key: 'status_date', label: 'Data Status', recommended: false },
  { key: 'dhl_date', label: 'Data DHL', recommended: false },
  { key: 'etd_date', label: 'Data embarque ETD', recommended: false },
  { key: 'eta_date', label: 'Data da chegada ETA', recommended: true },
  { key: 'dhl_number', label: 'DHL Nº', recommended: false },
  { key: 'observations', label: 'Observações', recommended: false },
];

const DEFAULT_VISIBLE = ALL_COLUMNS.filter((c) => c.recommended).map((c) => c.key);

const page = usePage();
const storageKey = computed(() => `jrspice_contract_columns_${page.props.auth?.user?.id || 'guest'}`);
const visibleKeys = ref(new Set(DEFAULT_VISIBLE));

onMounted(() => {
  try {
    const saved = localStorage.getItem(storageKey.value);
    if (saved) visibleKeys.value = new Set(JSON.parse(saved));
  } catch { /* ignore corrupted preference */ }
});

const saveToStorage = () => {
  try { localStorage.setItem(storageKey.value, JSON.stringify([...visibleKeys.value])); } catch { /* quota */ }
};

const isVisible = (key) => visibleKeys.value.has(key);

const toggleColumn = (key) => {
  const column = ALL_COLUMNS.find((c) => c.key === key);
  if (column?.fixed) return;
  const next = new Set(visibleKeys.value);
  next.has(key) ? next.delete(key) : next.add(key);
  visibleKeys.value = next;
  saveToStorage();
};

const resetToDefault = () => {
  visibleKeys.value = new Set(DEFAULT_VISIBLE);
  saveToStorage();
};

const visibleColumns = computed(() => ALL_COLUMNS.filter((c) => visibleKeys.value.has(c.key)));
const toggleableColumns = computed(() => ALL_COLUMNS.filter((c) => !c.fixed));
const hiddenCount = computed(() => toggleableColumns.value.filter((c) => !visibleKeys.value.has(c.key)).length);
const toggleableVisibleCount = computed(() => toggleableColumns.value.filter((c) => visibleKeys.value.has(c.key)).length);

const showColumnConfig = ref(false);
const configBtn = ref(null);

const selectedContractIds = ref([]);

const pageContractIds = computed(() => [...new Set((props.rows.data || []).map((r) => r.contract_id))]);

const isAllSelected = computed(() =>
  pageContractIds.value.length > 0 && pageContractIds.value.every((id) => selectedContractIds.value.includes(id))
);

const toggleSelectAll = () => {
  selectedContractIds.value = isAllSelected.value ? [] : pageContractIds.value;
};

const isSelected = (contractId) => selectedContractIds.value.includes(contractId);

const toggleSelect = (contractId) => {
  const index = selectedContractIds.value.indexOf(contractId);
  index > -1
    ? selectedContractIds.value.splice(index, 1)
    : selectedContractIds.value.push(contractId);
};

const handleBulkDelete = () => {
  if (!selectedContractIds.value.length) return;
  if (!confirm(`Excluir definitivamente ${selectedContractIds.value.length} contrato(s) e todos os seus itens?`)) return;

  router.post(route('contracts.bulk-delete'), { ids: selectedContractIds.value }, {
    onSuccess: () => { selectedContractIds.value = []; },
  });
};

const isBulkModalOpen = ref(false);
const bulkUserSearch = ref('');
const bulkSelectedUserIds = ref([]);

const filteredBulkUsers = computed(() => {
  const term = bulkUserSearch.value.toLowerCase().trim();
  if (!term) return props.users;
  return props.users.filter((u) =>
    [u.name, u.email, u.phone, u.company_name].some((v) => (v || '').toLowerCase().includes(term))
  );
});

const openBulkLinkModal = () => {
  bulkSelectedUserIds.value = [];
  bulkUserSearch.value = '';
  isBulkModalOpen.value = true;
};

const handleBulkSyncUsers = () => {
  if (!selectedContractIds.value.length || !bulkSelectedUserIds.value.length) {
    alert('Selecione ao menos um usuário para atrelar aos contratos.');
    return;
  }

  router.post(route('contracts.bulk-sync-users'), {
    ids: selectedContractIds.value,
    user_ids: bulkSelectedUserIds.value,
  }, {
    onSuccess: () => {
      isBulkModalOpen.value = false;
      selectedContractIds.value = [];
      bulkSelectedUserIds.value = [];
    },
  });
};

const statusDropdownRef = ref(null);
const activeStatusRow = ref(null);
const statusDropdownStyle = ref({});

const openStatusSelector = (row, event) => {
  if (activeStatusRow.value?.contract_id === row.contract_id) {
    closeStatusSelector();
    return;
  }
  activeStatusRow.value = row;

  const rect = event.currentTarget.getBoundingClientRect();
  const height = 260;
  const fitsBelow = window.innerHeight - rect.bottom > height;

  statusDropdownStyle.value = {
    position: 'fixed',
    top: fitsBelow ? `${rect.bottom + 1}px` : `${rect.top - height - 1}px`,
    left: `${Math.max(16, Math.min(rect.left, window.innerWidth - 280))}px`,
    zIndex: 99999,
  };

  setTimeout(() => window.addEventListener('scroll', closeStatusSelector, true), 50);
};

const closeStatusSelector = () => {
  activeStatusRow.value = null;
  window.removeEventListener('scroll', closeStatusSelector, true);
};

const selectStatus = (contractId, status) => {
  router.patch(route('contracts.status', contractId), {
    status,
    status_date: new Date().toISOString().slice(0, 10),
  }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: closeStatusSelector,
  });
};

const handleDocumentClick = (event) => {
  if (configBtn.value && !configBtn.value.contains(event.target)) showColumnConfig.value = false;
  if (activeStatusRow.value) {
    const dropdown = statusDropdownRef.value;
    const inside = event.target.closest('.status-badge-trigger')
      || (dropdown && dropdown.contains(event.target));
    if (!inside) closeStatusSelector();
  }
};

onMounted(() => document.addEventListener('click', handleDocumentClick));
onUnmounted(() => {
  document.removeEventListener('click', handleDocumentClick);
  window.removeEventListener('scroll', closeStatusSelector, true);
});

const changePage = (url) => {
  if (!url) return;
  router.visit(url, { preserveState: true, preserveScroll: true });
};

const getStatusConfig = (status) => {
  if (!status) return { class: 'bg-slate-50/50 text-slate-600 border-slate-200/60', dot: 'bg-slate-400' };
  const s = status.toLowerCase();
  if (s.includes('finalizado')) return { class: 'bg-emerald-50/35 text-emerald-800 border-emerald-500/15', dot: 'bg-emerald-500' };
  if (s.includes('invoice')) return { class: 'bg-sky-50/35 text-sky-800 border-sky-500/15', dot: 'bg-sky-500' };
  if (s.includes('faltando')) return { class: 'bg-rose-50/35 text-rose-800 border-rose-500/15', dot: 'bg-rose-500' };
  if (s.includes('transbordo') || s.includes('chegou')) return { class: 'bg-purple-50/35 text-purple-800 border-purple-500/15', dot: 'bg-purple-500' };
  if (s.includes('embarcar')) return { class: 'bg-amber-50/35 text-amber-800 border-amber-500/15', dot: 'bg-amber-500' };
  return { class: 'bg-slate-50/50 text-slate-600 border-slate-200/60', dot: 'bg-slate-400' };
};

const formatDate = (value) => {
  if (!value) return '—';
  const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('pt-BR');
};

const formatNumber = (value, digits = 2) => {
  if (value === null || value === undefined || value === '') return '—';
  return Number(value).toLocaleString('pt-BR', { minimumFractionDigits: digits, maximumFractionDigits: digits });
};

const formatCurrency = (value, symbol = 'US$') => {
  if (value === null || value === undefined || value === '') return '—';
  return `${symbol} ${formatNumber(value)}`;
};

const cellValue = (row, key) => {
  const column = ALL_COLUMNS.find((c) => c.key === key);
  const value = row[key];

  if (key === 'video_sent') return value ? 'Sim' : '—';
  if (column?.numeric) return formatNumber(value, key === 'exchange_rate' ? 4 : 2);

  switch (key) {
    case 'date':
    case 'estimated_receipt_date':
    case 'receipt_date':
    case 'paid_in_date':
    case 'video_date':
    case 'status_date':
    case 'dhl_date':
    case 'etd_date':
    case 'eta_date':
      return formatDate(value);
    case 'exporter':
    case 'importer':
    case 'seller':
      return value?.name || '—';
    case 'product':
      return value?.name || '—';
    default:
      return value || '—';
  }
};

const numericClass = (key) =>
  ALL_COLUMNS.find((c) => c.key === key)?.numeric ? 'text-right tabular-nums' : '';
</script>

<template>
  <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-visible flex flex-col">
  <ContractsFilterBar
    :filters="filters"
    :clients="clients"
    :sellers="sellers"
    :contract-products="contractProducts"
    :countries="countries"
    :status-options="statusOptions"
  >
    <!-- Colunas + Novo Contrato live in the table's own scope -->
    <div class="relative" ref="configBtn">
      <button
        type="button"
        @click.stop="showColumnConfig = !showColumnConfig"
        class="flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest border transition-all"
        :class="hiddenCount > 0
          ? 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100'
          : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
      >
        <Columns3 class="w-4 h-4" />
        Colunas
        <span
          v-if="hiddenCount > 0"
          class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-600 text-white text-[9px] font-black"
        >{{ hiddenCount }}</span>
      </button>

      <Transition name="dropdown">
        <div
          v-if="showColumnConfig"
          class="absolute right-0 top-full mt-2 w-80 bg-white rounded-2xl border border-slate-200 shadow-xl z-50 overflow-hidden"
        >
          <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <div>
              <p class="text-xs font-black text-slate-700 uppercase tracking-wider">Colunas da Planilha</p>
              <p class="text-[10px] font-medium text-slate-400 mt-0.5">
                {{ toggleableVisibleCount }} de {{ toggleableColumns.length }} exibidas
              </p>
            </div>
            <button
              @click="resetToDefault"
              class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 hover:text-blue-600 uppercase tracking-wider transition-colors"
            >
              <RotateCcw class="w-3 h-3" />
              Resetar
            </button>
          </div>

          <div class="py-2 max-h-96 overflow-y-auto">
            <div
              v-for="col in toggleableColumns"
              :key="col.key"
              @click="toggleColumn(col.key)"
              class="flex items-center justify-between px-4 py-2.5 hover:bg-slate-50 cursor-pointer group transition-colors"
            >
              <div class="flex items-center gap-3">
                <div
                  class="w-5 h-5 rounded-md border-2 flex items-center justify-center transition-all shrink-0"
                  :class="isVisible(col.key)
                    ? 'bg-blue-600 border-blue-600'
                    : 'border-slate-300 group-hover:border-blue-400'"
                >
                  <Check v-if="isVisible(col.key)" class="w-3 h-3 text-white stroke-[3]" />
                </div>
                <span class="text-sm font-semibold text-slate-700">{{ col.label }}</span>
              </div>
            </div>
          </div>

          <div class="px-4 py-2.5 border-t border-slate-100 bg-slate-50/60">
            <p class="text-[10px] font-medium text-slate-400">Configuração salva por usuário</p>
          </div>
        </div>
      </Transition>
    </div>

    <button
      @click="$emit('create')"
      class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest shadow-lg shadow-blue-200/60 transition-all active:scale-95 whitespace-nowrap"
    >
      <Plus class="w-4 h-4" />
      Novo Contrato
    </button>
  </ContractsFilterBar>

    <div
      v-if="selectedContractIds.length > 0"
      class="mx-5 mb-4 bg-slate-900 text-white px-5 py-3 rounded-2xl flex items-center justify-between shadow-xl"
    >
      <span class="text-xs font-black bg-blue-600 text-white px-3 py-1 rounded-lg uppercase tracking-wider">
        {{ selectedContractIds.length }} {{ selectedContractIds.length === 1 ? 'contrato selecionado' : 'contratos selecionados' }}
      </span>

      <div class="flex items-center gap-2">
        <button
          @click="openBulkLinkModal"
          class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-2 shadow-md active:scale-95"
        >
          <Users class="w-4 h-4" />
          Vincular Usuários
        </button>
        <button
          @click="handleBulkDelete"
          class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold uppercase tracking-wider transition-all flex items-center gap-2 shadow-md active:scale-95"
        >
          <Trash2 class="w-4 h-4" />
          Excluir
        </button>
      </div>
    </div>

    <div class="overflow-x-auto flex-1 relative">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50">
            <th class="px-3.5 py-2.5 w-10 text-center">
              <input
                type="checkbox"
                :checked="isAllSelected"
                @change="toggleSelectAll"
                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer w-4 h-4"
              />
            </th>
            <th
              v-for="col in visibleColumns"
              :key="col.key"
              class="px-4 py-2.5 text-[11px] font-bold uppercase tracking-wider whitespace-nowrap transition-colors"
              :class="[
                numericClass(col.key),
                isActiveSort(col.key) ? 'bg-blue-50/70 text-blue-700' : 'text-slate-500',
              ]"
            >
              <button
                v-if="isSortable(col.key)"
                type="button"
                @click="toggleSort(col.key)"
                class="inline-flex items-center gap-1 uppercase tracking-wider transition-colors"
                :class="isActiveSort(col.key) ? 'text-blue-700' : 'text-slate-500 hover:text-slate-800'"
                :title="`Ordenar por ${col.label}`"
              >
                {{ col.label }}
                <ArrowUpDown v-if="!isActiveSort(col.key)" class="w-3 h-3 opacity-40" />
                <ArrowUp v-else-if="sortDir === 'asc'" class="w-3.5 h-3.5" />
                <ArrowDown v-else class="w-3.5 h-3.5" />
              </button>
              <template v-else>{{ col.label }}</template>
            </th>
            <th class="w-14"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr
            v-for="row in rows.data"
            :key="`${row.contract_id}-${row.id}`"
            @click="$emit('edit', row)"
            class="hover:bg-blue-50/50 cursor-pointer transition-colors duration-100 group"
          >
            <td class="px-3.5 py-2.5 text-center w-10" @click.stop>
              <input
                type="checkbox"
                :checked="isSelected(row.contract_id)"
                @change="toggleSelect(row.contract_id)"
                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer w-4 h-4"
              />
            </td>

            <td
              v-for="col in visibleColumns"
              :key="col.key"
              class="px-4 py-2.5 text-[13px] whitespace-nowrap"
              :class="[
                numericClass(col.key),
                col.key === 'contract_number' ? 'font-bold text-blue-600 group-hover:text-blue-700' : '',
                col.key === 'sales_usd' || col.key === 'quantity_tons' ? 'font-bold text-slate-800' : '',
                col.key === 'commission_usd' ? 'font-bold text-emerald-700' : '',
                col.key === 'cumulative_sales_usd' || col.key === 'cumulative_commission_usd' ? 'text-slate-400 tabular-nums' : '',
                ['date', 'register_number', 'estimated_receipt_date', 'receipt_date', 'paid_in_date', 'video_date', 'status_date', 'dhl_date', 'etd_date', 'eta_date'].includes(col.key) ? 'text-slate-500 tabular-nums' : '',
                ['exporter', 'importer', 'seller', 'product', 'country', 'dhl_number'].includes(col.key) ? 'font-semibold text-slate-800' : '',
                ['incident', 'observations'].includes(col.key) ? 'max-w-[220px] truncate text-slate-500' : '',
              ]"
            >
              <template v-if="col.key === 'status'">
                <div
                  v-if="row.status"
                  class="status-badge-trigger inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-semibold normal-case border cursor-pointer transition-all select-none shadow-sm hover:border-slate-300"
                  :class="getStatusConfig(row.status).class"
                  @click.stop="openStatusSelector(row, $event)"
                >
                  <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="getStatusConfig(row.status).dot"></span>
                  {{ row.status }}
                  <ChevronDown class="w-3.5 h-3.5 opacity-60 shrink-0 ml-0.5" />
                </div>
                <div
                  v-else
                  class="status-badge-trigger inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-semibold normal-case border border-slate-200 bg-slate-50/50 hover:bg-slate-100/50 text-slate-450 hover:text-slate-600 cursor-pointer transition-all select-none hover:border-slate-300 shadow-sm"
                  @click.stop="openStatusSelector(row, $event)"
                >
                  Sem status
                  <ChevronDown class="w-3.5 h-3.5 opacity-60 shrink-0 ml-0.5" />
                </div>
              </template>

              <template v-else-if="col.key === 'eta_date'">
                <span :class="row.eta_date && !row.dhl_number ? 'text-rose-500 font-bold' : 'text-slate-500'">
                  {{ cellValue(row, col.key) }}
                </span>
              </template>

              <template v-else-if="col.key === 'video_sent'">
                <span v-if="row.video_sent" class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 rounded-full">
                  <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </span>
                <span v-else class="text-slate-300 text-base font-bold">—</span>
              </template>

              <template v-else>
                <span
                  :class="col.key === 'product' ? 'block truncate max-w-[200px]' : ''"
                  :title="col.key === 'product' ? row.product?.name : undefined"
                >{{ cellValue(row, col.key) }}</span>
              </template>
            </td>

            <td class="px-3 py-2.5 text-right whitespace-nowrap">
              <div class="flex items-center justify-end gap-1">
                <button
                  v-if="row.container_number || row.dhl_number"
                  @click.stop="$emit('tracking', row)"
                  class="opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity p-2 rounded-xl hover:bg-emerald-100 text-emerald-600"
                  title="Rastreamento"
                >
                  <Ship class="w-4 h-4" />
                </button>

                <button
                  @click.stop="$emit('edit', row)"
                  class="opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity p-2 rounded-xl hover:bg-blue-100 text-blue-500"
                  title="Editar contrato"
                >
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 00-2 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
                </button>
              </div>
            </td>
          </tr>

          <tr v-if="!rows.data?.length">
            <td :colspan="visibleColumns.length + 2" class="px-6 py-20 text-center">
              <div class="inline-flex flex-col items-center gap-4">
                <div class="w-16 h-16 rounded-3xl bg-slate-100 flex items-center justify-center">
                  <Package class="w-8 h-8 text-slate-400" />
                </div>
                <div>
                  <p class="text-sm font-bold text-slate-500">Nenhum contrato encontrado</p>
                  <p class="text-xs text-slate-400 mt-1">Ajuste os filtros ou crie um novo contrato</p>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="px-5 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between gap-4 rounded-b-3xl">
      <p class="text-xs font-bold text-slate-500">
        Exibindo
        <span class="text-blue-600 font-black">{{ rows.from || 0 }}</span>–<span class="text-blue-600 font-black">{{ rows.to || 0 }}</span>
        de <span class="text-slate-700 font-black">{{ rows.total || 0 }}</span> itens
      </p>
      <div v-if="rows.links?.length > 3" class="flex items-center gap-1">
        <template v-for="(link, i) in rows.links" :key="i">
          <button
            v-if="i === 0"
            @click="changePage(link.url)"
            :disabled="!link.url"
            class="p-2 rounded-xl border text-slate-500 transition-all"
            :class="!link.url ? 'opacity-30 cursor-not-allowed border-slate-100' : 'border-slate-200 bg-white hover:border-blue-400 hover:text-blue-600'"
          >
            <ChevronLeft class="w-4 h-4" />
          </button>
          <button
            v-else-if="i === rows.links.length - 1"
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

  <Teleport to="body">
    <div
      v-if="activeStatusRow"
      ref="statusDropdownRef"
      class="status-dropdown-content fixed bg-white border border-slate-200 rounded-2xl shadow-xl py-2 w-64 z-[99999]"
      :style="statusDropdownStyle"
    >
      <div class="px-4 py-2 border-b border-slate-100 mb-1">
        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Alterar STATUS</p>
        <p class="text-xs font-black text-slate-700 truncate mt-0.5">
          Contrato {{ activeStatusRow.contract_number || 'sem número' }}
        </p>
      </div>
      <div class="max-h-60 overflow-y-auto">
        <button
          v-for="option in statusOptions"
          :key="option.id"
          @click.stop="selectStatus(activeStatusRow.contract_id, option.id)"
          type="button"
          class="w-full text-left px-4 py-2 text-xs font-semibold normal-case hover:bg-slate-50 flex items-center gap-2.5 transition-colors"
          :class="activeStatusRow.status === option.id ? 'text-blue-600 bg-blue-50/50' : 'text-slate-600'"
        >
          <span class="w-2 h-2 rounded-full shrink-0" :class="getStatusConfig(option.id).dot"></span>
          {{ option.name }}
          <Check v-if="activeStatusRow.status === option.id" class="w-3.5 h-3.5 text-blue-600 ml-auto shrink-0" />
        </button>
      </div>
    </div>
  </Teleport>

  <Teleport to="body">
    <div v-if="isBulkModalOpen" class="fixed inset-0 z-[99999] flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4">
      <div class="bg-white rounded-3xl border border-slate-200 shadow-2xl w-full max-w-md p-6 space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div>
            <h3 class="text-base font-black text-slate-900 uppercase tracking-tighter">Vincular Usuários</h3>
            <p class="text-xs font-medium text-slate-500 mt-0.5">
              Atrele usuários aos {{ selectedContractIds.length }} contratos selecionados
            </p>
          </div>
          <button @click="isBulkModalOpen = false" class="text-slate-400 hover:text-slate-600">
            <X class="w-5 h-5" />
          </button>
        </div>

        <div class="space-y-2 max-h-72 overflow-y-auto">
          <label
            v-for="user in filteredBulkUsers"
            :key="user.id"
            class="flex items-center gap-3 px-3 py-2 rounded-xl hover:bg-slate-50 cursor-pointer"
          >
            <input
              type="checkbox"
              :value="user.id"
              v-model="bulkSelectedUserIds"
              class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
            />
            <span class="text-sm font-semibold text-slate-700">{{ user.name }}</span>
            <span class="text-xs text-slate-400">{{ user.email }}</span>
          </label>
          <p v-if="!filteredBulkUsers.length" class="text-xs text-slate-400 text-center py-4">Nenhum usuário encontrado</p>
        </div>

        <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
          <button
            type="button"
            @click="isBulkModalOpen = false"
            class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl uppercase tracking-wider transition-colors"
          >Cancelar</button>
          <button
            type="button"
            @click="handleBulkSyncUsers"
            class="px-5 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-xl uppercase tracking-wider shadow-lg shadow-blue-200/50 transition-all active:scale-95"
          >Atrelar aos {{ selectedContractIds.length }} Contratos</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.dropdown-enter-active { transition: opacity 0.15s ease, transform 0.15s ease; }
.dropdown-leave-active { transition: opacity 0.1s ease, transform 0.1s ease; }
.dropdown-enter-from  { opacity: 0; transform: translateY(-6px) scale(0.97); }
.dropdown-leave-to    { opacity: 0; transform: translateY(-4px) scale(0.98); }
</style>
