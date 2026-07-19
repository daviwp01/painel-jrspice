<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Search, Plus, Pencil, ChevronLeft, ChevronRight, ChevronDown, Package, Columns3, Check, RotateCcw } from 'lucide-vue-next';

const props = defineProps({
  exportProcesses: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) }
});

const emit = defineEmits(['create', 'edit']);

// ─── Column Definitions ────────────────────────────────────────────────────
const ALL_COLUMNS = [
  { key: 'date',               label: 'Data',           recommended: true  },
  { key: 'contract_number',    label: 'Contrato',       recommended: true  },
  { key: 'register_number',    label: 'Registro',       recommended: false },
  { key: 'exporter',           label: 'Exportador',     recommended: true  },
  { key: 'importer',           label: 'Importador',     recommended: true  },
  { key: 'product',            label: 'Produto',        recommended: true  },
  { key: 'quantity_tons',      label: 'Qtd (Ton)',      recommended: true  },
  { key: 'price_per_ton_usd',  label: 'Preço/Ton',      recommended: false },
  { key: 'sales_usd',          label: 'Venda $',        recommended: true  },
  { key: 'seller',             label: 'Vendedor',       recommended: false },
  { key: 'commission_usd',     label: 'Comissão',       recommended: true  },
  { key: 'status',             label: 'Status',         recommended: true  },
  { key: 'dhl_number',         label: 'DHL Nº',         recommended: false },
  { key: 'etd_date',           label: 'ETD',            recommended: false },
  { key: 'eta_date',           label: 'ETA',            recommended: true  },
  { key: 'video_sent',         label: 'Vídeo',          recommended: false },
];

// ─── Column Visibility (localStorage) ─────────────────────────────────────
const page = usePage();
const storageKey = computed(() => `jrspice_columns_contracts_${page.props.auth?.user?.id || 'guest'}`);

const visibleKeys = ref(new Set(ALL_COLUMNS.map(c => c.key)));

const loadFromStorage = () => {
  try {
    const saved = localStorage.getItem(storageKey.value);
    if (saved) {
      const parsed = JSON.parse(saved);
      visibleKeys.value = new Set(parsed);
    }
  } catch {}
};

const saveToStorage = () => {
  try {
    localStorage.setItem(storageKey.value, JSON.stringify([...visibleKeys.value]));
  } catch {}
};

onMounted(loadFromStorage);

const isVisible = (key) => visibleKeys.value.has(key);

const toggleColumn = (key) => {
  // Prevent toggling off required columns
  if (key === 'date' || key === 'contract_number') return;

  const next = new Set(visibleKeys.value);
  if (next.has(key)) { next.delete(key); }
  else { next.add(key); }
  visibleKeys.value = next;
  saveToStorage();
};

const resetToDefault = () => {
  visibleKeys.value = new Set(ALL_COLUMNS.map(c => c.key));
  saveToStorage();
};

const visibleColumns = computed(() => ALL_COLUMNS.filter(c => visibleKeys.value.has(c.key)));
const hiddenCount = computed(() => ALL_COLUMNS.length - visibleKeys.value.size);

const TOGGLEABLE_COLUMNS = ALL_COLUMNS.filter(c => c.key !== 'date' && c.key !== 'contract_number');
const toggleableVisibleCount = computed(() => TOGGLEABLE_COLUMNS.filter(c => visibleKeys.value.has(c.key)).length);

// ─── Column Config Dropdown ────────────────────────────────────────────────
const showColumnConfig = ref(false);
const configBtn = ref(null);

// ─── Dropdown Status Selector ────────────────────────────────────────────────
const scrollContainerRef = ref(null);
const activeStatusSelectorProcess = ref(null);
const statusDropdownStyle = ref({});

const statusOptions = [
  'A embarcar dia',
  'Transbordo até',
  'Chegou porto dia',
  'Só faltando comissão',
  'Invoice ENVIADA',
  'Processo FINALIZADO',
];

const openStatusSelector = (process, event) => {
  if (activeStatusSelectorProcess.value?.id === process.id) {
    closeStatusSelector();
    return;
  }
  activeStatusSelectorProcess.value = process;
  const rect = event.currentTarget.getBoundingClientRect();
  if (rect) {
    const dropdownHeight = 240;
    const fitsBelow = (window.innerHeight - rect.bottom) > dropdownHeight;
    
    statusDropdownStyle.value = {
      position: 'fixed',
      top: fitsBelow 
        ? `${rect.bottom + 1}px` 
        : `${rect.top - dropdownHeight - 1}px`,
      left: `${Math.max(16, Math.min(rect.left, window.innerWidth - 270))}px`,
      zIndex: 99999,
    };
    
    // Register scroll event capture listener dynamically after opening
    setTimeout(() => {
      window.addEventListener('scroll', handleScrollClose, true);
    }, 50);
  }
};

const closeStatusSelector = () => {
  activeStatusSelectorProcess.value = null;
  window.removeEventListener('scroll', handleScrollClose, true);
};

const selectStatus = (processId, status) => {
  router.put(route('export-processes.update', processId), {
    status: status
  }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      closeStatusSelector();
    }
  });
};

const handleScrollClose = () => {
  if (activeStatusSelectorProcess.value) {
    closeStatusSelector();
  }
};

const closeOnOutside = (e) => {
  if (configBtn.value && !configBtn.value.contains(e.target)) {
    showColumnConfig.value = false;
  }
  if (activeStatusSelectorProcess.value) {
    const statusDropdownEl = document.querySelector('.status-dropdown-content');
    const isClickInside = e.target.closest('.status-badge-trigger') || (statusDropdownEl && statusDropdownEl.contains(e.target));
    if (!isClickInside) {
      closeStatusSelector();
    }
  }
};

onMounted(() => {
  document.addEventListener('click', closeOnOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', closeOnOutside);
  window.removeEventListener('scroll', handleScrollClose, true);
});

// ─── Search ───────────────────────────────────────────────────────────────
const search = ref(props.filters?.search || '');
let searchTimeout = null;

const handleSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    router.get(route('admin.clients.index'), { search: search.value }, {
      preserveState: true, replace: true,
    });
  }, 350);
};

// ─── Pagination ───────────────────────────────────────────────────────────
const changePage = (url) => {
  if (!url) return;
  router.visit(url, { preserveState: true, preserveScroll: true });
};

// ─── Helpers ──────────────────────────────────────────────────────────────
const getStatusConfig = (status) => {
  if (!status) return { class: 'bg-slate-100 text-slate-500 border-slate-200', dot: 'bg-slate-400' };
  const s = status.toLowerCase();
  if (s.includes('finalizado'))               return { class: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' };
  if (s.includes('invoice'))                  return { class: 'bg-blue-50 text-blue-700 border-blue-200',         dot: 'bg-blue-500'    };
  if (s.includes('atraso') || s.includes('falta')) return { class: 'bg-rose-50 text-rose-700 border-rose-200',   dot: 'bg-rose-500'    };
  if (s.includes('transbordo') || s.includes('chegou')) return { class: 'bg-violet-50 text-violet-700 border-violet-200', dot: 'bg-violet-500' };
  if (s.includes('embarcar'))                 return { class: 'bg-amber-50 text-amber-700 border-amber-200',      dot: 'bg-amber-500'   };
  return { class: 'bg-slate-100 text-slate-500 border-slate-200', dot: 'bg-slate-400' };
};

const formatDate = (d) => {
  if (!d) return '—';
  const date = new Date(d);
  return isNaN(date.getTime()) ? d : date.toLocaleDateString('pt-BR');
};

const formatCurrency = (val) => {
  if (!val && val !== 0) return '—';
  return `$${Number(val).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
};
</script>

<template>
  <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-visible flex flex-col">

    <!-- Toolbar -->
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-4">
      <!-- Search -->
      <div class="relative flex-1 max-w-sm">
        <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
        <input
          v-model="search"
          @input="handleSearch"
          type="text"
          placeholder="Buscar contrato, exportador..."
          class="w-full pl-10 pr-4 py-2.5 text-sm font-medium text-slate-700 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all placeholder:text-slate-400"
        />
      </div>

      <div class="flex items-center gap-2">
        <!-- Column Configurator Button -->
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

          <!-- Dropdown Panel -->
          <Transition name="dropdown">
            <div
              v-if="showColumnConfig"
              class="absolute right-0 top-full mt-2 w-72 bg-white rounded-2xl border border-slate-200 shadow-xl z-50 overflow-hidden"
            >
              <!-- Header -->
              <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                <div>
                  <p class="text-xs font-black text-slate-700 uppercase tracking-wider">Colunas Opcionais</p>
                  <p class="text-[10px] font-medium text-slate-400 mt-0.5">{{ toggleableVisibleCount }} de {{ TOGGLEABLE_COLUMNS.length }} selecionadas</p>
                </div>
                <button
                  @click="resetToDefault"
                  class="flex items-center gap-1.5 text-[10px] font-bold text-slate-400 hover:text-blue-600 uppercase tracking-wider transition-colors"
                  title="Restaurar padrão"
                >
                  <RotateCcw class="w-3 h-3" />
                  Resetar
                </button>
              </div>

              <!-- Column List -->
              <div class="py-2 max-h-80 overflow-y-auto">
                <div
                  v-for="col in TOGGLEABLE_COLUMNS"
                  :key="col.key"
                  @click="toggleColumn(col.key)"
                  class="flex items-center justify-between px-4 py-2.5 hover:bg-slate-50 cursor-pointer group transition-colors"
                >
                  <div class="flex items-center gap-3">
                    <!-- Custom Checkbox -->
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

              <!-- Footer hint -->
              <div class="px-4 py-2.5 border-t border-slate-100 bg-slate-50/60">
                <p class="text-[10px] font-medium text-slate-400">Configuração salva automaticamente por usuário</p>
              </div>
            </div>
          </Transition>
        </div>

        <!-- New Contract Button -->
        <button
          @click="$emit('create')"
          class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest shadow-lg shadow-blue-200/60 transition-all active:scale-95 whitespace-nowrap"
        >
          <Plus class="w-4 h-4" />
          Novo Contrato
        </button>
      </div>
    </div>

    <!-- Table Wrapper -->
    <div ref="scrollContainerRef" class="overflow-x-auto flex-1 relative">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="border-b border-slate-100 bg-slate-50">
            <th v-if="isVisible('date')"              class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Data</th>
            <th v-if="isVisible('contract_number')"   class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Contrato</th>
            <th v-if="isVisible('register_number')"   class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Registro</th>
            <th v-if="isVisible('exporter')"          class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Exportador</th>
            <th v-if="isVisible('importer')"          class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Importador</th>
            <th v-if="isVisible('product')"           class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Produto</th>
            <th v-if="isVisible('quantity_tons')"     class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider text-right whitespace-nowrap">Qtd (Ton)</th>
            <th v-if="isVisible('price_per_ton_usd')" class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider text-right whitespace-nowrap">Preço/Ton</th>
            <th v-if="isVisible('sales_usd')"         class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider text-right whitespace-nowrap">Venda $</th>
            <th v-if="isVisible('seller')"            class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Vendedor</th>
            <th v-if="isVisible('commission_usd')"    class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider text-right whitespace-nowrap">Comissão</th>
            <th v-if="isVisible('status')"            class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Status</th>
            <th v-if="isVisible('dhl_number')"        class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">DHL Nº</th>
            <th v-if="isVisible('etd_date')"          class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">ETD</th>
            <th v-if="isVisible('eta_date')"          class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">ETA</th>
            <th v-if="isVisible('video_sent')"        class="px-5 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wider text-center whitespace-nowrap">Vídeo</th>
            <th class="w-14"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
          <tr
            v-for="process in exportProcesses.data"
            :key="process.id"
            @click="$emit('edit', process)"
            class="hover:bg-blue-50/50 cursor-pointer transition-colors duration-100 group"
          >
            <td v-if="isVisible('date')"
                class="px-5 py-4 text-sm font-semibold text-slate-600 whitespace-nowrap tabular-nums">
              {{ formatDate(process.date) }}
            </td>
            <td v-if="isVisible('contract_number')" class="px-5 py-4 whitespace-nowrap">
              <span class="text-sm font-bold text-blue-600 group-hover:text-blue-700 transition-colors">
                {{ process.contract_number || '—' }}
              </span>
            </td>
            <td v-if="isVisible('register_number')"
                class="px-5 py-4 text-sm font-medium text-slate-500 whitespace-nowrap">
              {{ process.register_number || '—' }}
            </td>
            <td v-if="isVisible('exporter')"
                class="px-5 py-4 text-sm font-semibold text-slate-800 whitespace-nowrap">
              {{ process.exporter?.name || '—' }}
            </td>
            <td v-if="isVisible('importer')"
                class="px-5 py-4 text-sm font-semibold text-slate-800 whitespace-nowrap">
              {{ process.importer?.name || '—' }}
            </td>
            <td v-if="isVisible('product')"
                class="px-5 py-4 text-sm font-medium text-slate-600 max-w-[200px]">
              <span class="block truncate" :title="process.product?.name">{{ process.product?.name || '—' }}</span>
            </td>
            <td v-if="isVisible('quantity_tons')"
                class="px-5 py-4 text-sm font-bold text-slate-800 text-right tabular-nums whitespace-nowrap">
              {{ process.quantity_tons ? Number(process.quantity_tons).toLocaleString('pt-BR') : '—' }}
            </td>
            <td v-if="isVisible('price_per_ton_usd')"
                class="px-5 py-4 text-sm font-bold text-slate-800 text-right tabular-nums whitespace-nowrap">
              {{ process.price_per_ton_usd ? `$${Number(process.price_per_ton_usd).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` : '—' }}
            </td>
            <td v-if="isVisible('sales_usd')"
                class="px-5 py-4 text-sm font-black text-slate-900 text-right tabular-nums whitespace-nowrap">
              {{ formatCurrency(process.sales_usd) }}
            </td>
            <td v-if="isVisible('seller')"
                class="px-5 py-4 text-sm font-medium text-slate-500 whitespace-nowrap">
              {{ process.seller?.name || '—' }}
            </td>
            <td v-if="isVisible('commission_usd')"
                class="px-5 py-4 text-sm font-bold text-emerald-700 text-right tabular-nums whitespace-nowrap">
              {{ formatCurrency(process.commission_usd) }}
            </td>
            <td v-if="isVisible('status')" class="px-5 py-4 whitespace-nowrap">
              <div
                v-if="process.status"
                class="status-badge-trigger inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider border cursor-pointer transition-all select-none shadow-sm hover:border-slate-350"
                :class="getStatusConfig(process.status).class"
                @click.stop="openStatusSelector(process, $event)"
              >
                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="getStatusConfig(process.status).dot"></span>
                {{ process.status }}
                <ChevronDown class="w-3.5 h-3.5 opacity-60 shrink-0 ml-0.5" />
              </div>
              <div 
                v-else 
                class="status-badge-trigger inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider border border-slate-200 bg-slate-50 hover:bg-slate-100/70 text-slate-400 hover:text-slate-600 cursor-pointer transition-all select-none hover:border-slate-300 shadow-sm"
                @click.stop="openStatusSelector(process, $event)"
              >
                Sem status
                <ChevronDown class="w-3.5 h-3.5 opacity-60 shrink-0 ml-0.5" />
              </div>
            </td>
            <td v-if="isVisible('dhl_number')"
                class="px-5 py-4 text-sm font-medium text-slate-500 whitespace-nowrap">
              {{ process.dhl_number || '—' }}
            </td>
            <td v-if="isVisible('etd_date')"
                class="px-5 py-4 text-sm font-medium text-slate-500 whitespace-nowrap tabular-nums">
              {{ formatDate(process.etd_date) }}
            </td>
            <td v-if="isVisible('eta_date')"
                class="px-5 py-4 text-sm font-medium whitespace-nowrap tabular-nums"
                :class="process.eta_date && !process.dhl_number ? 'text-rose-500 font-bold' : 'text-slate-500'">
              {{ formatDate(process.eta_date) }}
            </td>
            <td v-if="isVisible('video_sent')" class="px-5 py-4 text-center whitespace-nowrap">
              <span v-if="process.video_sent" class="inline-flex items-center justify-center w-6 h-6 bg-emerald-100 rounded-full">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </span>
              <span v-else class="text-slate-300 text-base font-bold">—</span>
            </td>
            <td class="px-4 py-4 text-right whitespace-nowrap">
              <button
                @click.stop="$emit('edit', process)"
                class="opacity-0 group-hover:opacity-100 transition-opacity p-2 rounded-xl hover:bg-blue-100 text-blue-500"
                title="Editar"
              >
                <Pencil class="w-4 h-4" />
              </button>
            </td>
          </tr>

          <!-- Empty State -->
          <tr v-if="!exportProcesses.data?.length">
            <td :colspan="visibleColumns.length + 1" class="px-6 py-20 text-center">
              <div class="inline-flex flex-col items-center gap-4">
                <div class="w-16 h-16 rounded-3xl bg-slate-100 flex items-center justify-center">
                  <Package class="w-8 h-8 text-slate-400" />
                </div>
                <div>
                  <p class="text-sm font-bold text-slate-500">Nenhum contrato encontrado</p>
                  <p class="text-xs text-slate-400 mt-1">Crie um novo contrato para começar</p>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

    </div>

    <!-- Footer -->
    <div class="px-6 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between gap-4 rounded-b-3xl">
      <p class="text-xs font-bold text-slate-500">
        Exibindo
        <span class="text-blue-600 font-black">{{ exportProcesses.from || 0 }}</span>–<span class="text-blue-600 font-black">{{ exportProcesses.to || 0 }}</span>
        de <span class="text-slate-700 font-black">{{ exportProcesses.total || 0 }}</span> registros
      </p>
      <div v-if="exportProcesses.links?.length > 3" class="flex items-center gap-1">
        <template v-for="(link, i) in exportProcesses.links" :key="i">
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

  <!-- Custom Status Selector Dropdown (Fixed Viewport Portal) -->
  <Teleport to="body">
    <div 
      v-if="activeStatusSelectorProcess" 
      class="status-dropdown-content fixed bg-white border border-slate-200 rounded-2xl shadow-xl py-2 w-64 z-[99999] animate-in fade-in slide-in-from-top-1 duration-150"
      :style="statusDropdownStyle"
    >
      <div class="px-4 py-2 border-b border-slate-100 mb-1">
        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest">Alterar Status</p>
        <p class="text-xs font-black text-slate-700 truncate mt-0.5">{{ activeStatusSelectorProcess.contract_number || 'Sem número' }}</p>
      </div>
      <div class="max-h-60 overflow-y-auto">
        <button
          v-for="statusOpt in statusOptions"
          :key="statusOpt"
          @click.stop="selectStatus(activeStatusSelectorProcess.id, statusOpt)"
          type="button"
          class="w-full text-left px-4 py-2.5 text-xs font-bold uppercase tracking-wider hover:bg-slate-50 flex items-center gap-2.5 transition-colors"
          :class="activeStatusSelectorProcess.status === statusOpt ? 'text-blue-600 bg-blue-50/50' : 'text-slate-650'"
        >
          <span class="w-2 h-2 rounded-full shrink-0" :class="getStatusConfig(statusOpt).dot"></span>
          {{ statusOpt }}
          <Check v-if="activeStatusSelectorProcess.status === statusOpt" class="w-3.5 h-3.5 text-blue-600 ml-auto shrink-0" />
        </button>
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
