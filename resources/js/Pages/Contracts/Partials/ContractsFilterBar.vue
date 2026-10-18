<script setup>
import { computed, reactive, ref, useSlots, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Search, SlidersHorizontal, X, CalendarRange, Building2, Package, Flag, Check, AlertTriangle } from 'lucide-vue-next';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const props = defineProps({
  filters: { type: Object, default: () => ({}) },
  routeName: { type: String, default: 'admin.clients.index' },
  /**
   * Which filter groups to render. Lets the client portal show a reduced set
   * without forking the component.
   */
  fields: {
    type: Array,
    default: () => ['identification', 'companies', 'goods', 'status', 'situation'],
  },
  /**
   * Which "Situação" toggles are rendered. Logistics delay and commission
   * status are internal, so the client portal passes a reduced list.
   */
  situationItems: {
    type: Array,
    default: () => ['delayed', 'active', 'pending_commission', 'incident'],
  },
  clients: { type: Array, default: () => [] },
  sellers: { type: Array, default: () => [] },
  contractProducts: { type: Array, default: () => [] },
  countries: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
});

const FILTER_KEYS = [
  'search', 'contract_number', 'register_number', 'country',
  'exporter_id', 'importer_id', 'seller_id', 'product_id',
  'status', 'date_from', 'date_to',
  'active_only', 'delayed', 'has_incident', 'pending_commission',
];

const slots = useSlots();

const hasField = (key) => props.fields.includes(key);
const isPanelOpen = ref(false);

const draft = reactive({
  search: '',
  contract_number: '',
  register_number: '',
  country: '',
  exporter_id: '',
  importer_id: '',
  seller_id: '',
  product_id: '',
  status: [],
  date_from: '',
  date_to: '',
  active_only: false,
  delayed: false,
  has_incident: false,
  pending_commission: false,
});

const normalize = (source) => ({
  search: source.search || '',
  contract_number: source.contract_number || '',
  register_number: source.register_number || '',
  country: source.country || '',
  exporter_id: source.exporter_id || '',
  importer_id: source.importer_id || '',
  seller_id: source.seller_id || '',
  product_id: source.product_id || '',
  status: Array.isArray(source.status)
    ? [...source.status]
    : (source.status ? [source.status] : []),
  date_from: source.date_from || '',
  date_to: source.date_to || '',
  active_only: Boolean(source.active_only),
  delayed: Boolean(source.delayed),
  has_incident: Boolean(source.has_incident),
  pending_commission: Boolean(source.pending_commission),
});

const pick = (source) => {
  const picked = {};
  FILTER_KEYS.forEach((key) => {
    const value = source[key];
    if (value === '' || value === null || value === false || (Array.isArray(value) && value.length === 0)) return;
    picked[key] = value;
  });
  return picked;
};

Object.assign(draft, normalize(props.filters));

// Echo of our own request must not clobber the field being typed in.
let appliedSignature = JSON.stringify(pick(props.filters));

watch(() => props.filters, (next) => {
  const signature = JSON.stringify(pick(next));
  if (signature === appliedSignature) return;
  appliedSignature = signature;
  Object.assign(draft, normalize(next));
});

const exporters = computed(() => props.clients.filter((client) => client.type === 'exporter'));
const importers = computed(() => props.clients.filter((client) => client.type === 'importer'));

const payload = () => {
  const cleaned = {};
  FILTER_KEYS.forEach((key) => {
    const value = draft[key];
    if (value === '' || value === null || value === false) return;
    if (Array.isArray(value)) {
      if (value.length === 0) return;
      cleaned[key] = value;
      return;
    }
    cleaned[key] = value;
  });
  return cleaned;
};

const apply = () => {
  const cleaned = payload();
  appliedSignature = JSON.stringify(cleaned);

  router.get(route(props.routeName), { ...sortParams(), ...cleaned }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

// Sorting is orthogonal to filtering and must survive filter changes.
const sortParams = () => {
  const params = {};
  if (props.filters?.sort_by) params.sort_by = props.filters.sort_by;
  if (props.filters?.sort_dir) params.sort_dir = props.filters.sort_dir;
  return params;
};

let applyTimeout = null;
const scheduleApply = () => {
  clearTimeout(applyTimeout);
  applyTimeout = setTimeout(apply, 300);
};

const toggleStatus = (status) => {
  const index = draft.status.indexOf(status);
  index > -1 ? draft.status.splice(index, 1) : draft.status.push(status);
  scheduleApply();
};

const onFieldChange = () => scheduleApply();

const clearAll = () => {
  Object.assign(draft, normalize({}));
  clearTimeout(applyTimeout);
  appliedSignature = JSON.stringify({});
  // clear_filters tells the server to forget the remembered filters too.
  router.get(route(props.routeName), { ...sortParams(), clear_filters: 1 }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const clientName = (id) => props.clients.find((c) => c.id === Number(id))?.name || `#${id}`;
const sellerName = (id) => props.sellers.find((s) => s.id === Number(id))?.name || `#${id}`;
const productName = (id) => props.contractProducts.find((p) => p.id === Number(id))?.name || `#${id}`;

const formatDate = (value) => {
  if (!value) return '';
  const [year, month, day] = String(value).slice(0, 10).split('-');
  return `${day}/${month}/${year}`;
};

const chips = computed(() => {
  const list = [];
  const f = props.filters || {};

  if (f.search) list.push({ key: 'search', label: 'Busca', value: f.search });
  if (f.contract_number) list.push({ key: 'contract_number', label: 'Contrato', value: f.contract_number });
  if (f.register_number) list.push({ key: 'register_number', label: 'Registro', value: f.register_number });
  if (f.country) list.push({ key: 'country', label: 'País', value: f.country });
  if (f.exporter_id) list.push({ key: 'exporter_id', label: 'Exportador', value: clientName(f.exporter_id) });
  if (f.importer_id) list.push({ key: 'importer_id', label: 'Importador', value: clientName(f.importer_id) });
  if (f.seller_id) list.push({ key: 'seller_id', label: 'Vendedor', value: sellerName(f.seller_id) });
  if (f.product_id) list.push({ key: 'product_id', label: 'Produto', value: productName(f.product_id) });

  const statuses = Array.isArray(f.status) ? f.status : (f.status ? [f.status] : []);
  statuses.forEach((status) => list.push({ key: 'status', label: 'STATUS', value: status, single: status }));

  if (f.date_from) list.push({ key: 'date_from', label: 'De', value: formatDate(f.date_from) });
  if (f.date_to) list.push({ key: 'date_to', label: 'Até', value: formatDate(f.date_to) });
  if (f.active_only) list.push({ key: 'active_only', label: '', value: 'Somente ativos' });
  if (f.delayed) list.push({ key: 'delayed', label: '', value: 'Em atraso' });
  if (f.has_incident) list.push({ key: 'has_incident', label: '', value: 'Com incidente' });
  if (f.pending_commission) list.push({ key: 'pending_commission', label: '', value: 'Comissão pendente' });

  return list;
});

const removeChip = (chip) => {
  if (chip.key === 'status') {
    draft.status = draft.status.filter((value) => value !== chip.single);
  } else if (typeof draft[chip.key] === 'boolean') {
    draft[chip.key] = false;
  } else {
    draft[chip.key] = '';
  }
  clearTimeout(applyTimeout);
  appliedSignature = JSON.stringify(payload());
  apply();
};

const activeCount = computed(() => chips.value.length);

const fieldClass = 'block w-full px-3.5 py-2.5 text-sm font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all placeholder:text-slate-300 placeholder:font-normal';
const labelClass = 'block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5';
const groupTitleClass = 'flex items-center gap-2 text-[11px] font-black text-slate-700 uppercase tracking-widest mb-3 pb-2 border-b border-slate-100';

const toggleClass = (active, tone = 'blue') => 'inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border text-xs font-bold uppercase tracking-wider transition-all cursor-pointer select-none'
  + (active
    ? (tone === 'rose'
      ? ' bg-rose-600 border-rose-600 text-white shadow-md shadow-rose-200/60'
      : ' bg-blue-600 border-blue-600 text-white shadow-md shadow-blue-200/60')
    : ' bg-white border-slate-200 text-slate-600 hover:border-blue-300 hover:text-blue-700');
</script>

<template>
  <!-- Toolbar row — lives inside the contracts table box -->
  <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">
      <div class="relative flex-1 min-w-0 md:max-w-md">
        <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
        <input
          v-model="draft.search"
          @input="scheduleApply"
          type="text"
          placeholder="Busca rápida: contrato, registro, exportador, produto..."
          class="w-full pl-10 pr-9 py-2.5 text-sm font-medium text-slate-700 bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all placeholder:text-slate-400"
        />
        <button
          v-if="draft.search"
          @click="draft.search = ''; apply()"
          class="absolute right-2.5 top-1/2 -translate-y-1/2 p-1 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-200 transition-colors"
          title="Limpar busca"
        >
          <X class="w-3.5 h-3.5" />
        </button>
      </div>

      <button
        type="button"
        @click="isPanelOpen = !isPanelOpen"
        class="flex items-center gap-2 px-4 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest border transition-all shrink-0"
        :class="activeCount > 0
          ? 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100'
          : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
      >
        <SlidersHorizontal class="w-4 h-4" />
        Filtros
        <span
          v-if="activeCount > 0"
          class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-blue-600 text-white text-[10px] font-black"
        >{{ activeCount }}</span>
      </button>

      <div class="flex items-center gap-2 ml-auto shrink-0">
        <button
          v-if="activeCount > 0"
          type="button"
          @click="clearAll"
          class="flex items-center gap-1.5 px-3.5 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest text-rose-600 border border-rose-200 bg-rose-50/50 hover:bg-rose-50 transition-all"
        >
          <X class="w-3.5 h-3.5" />
          Limpar
        </button>

        <span v-if="slots.default" class="w-px h-6 bg-slate-200 mx-1"></span>

        <!-- Colunas / Novo Contrato live in the table's own scope -->
        <slot />
      </div>
    </div>

    <!-- Applied chips -->
    <div v-if="chips.length" class="px-5 py-3 border-b border-slate-100 flex flex-wrap items-center gap-2">
      <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest mr-1">Ativos:</span>
      <button
        v-for="chip in chips"
        :key="`${chip.key}-${chip.single || chip.value}`"
        type="button"
        @click="removeChip(chip)"
        class="group inline-flex items-center gap-1.5 pl-3 pr-2 py-1.5 rounded-lg bg-slate-100 border border-slate-200 text-xs font-bold text-slate-700 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-700 transition-all"
        :title="`Remover filtro: ${chip.label ? chip.label + ': ' : ''}${chip.value}`"
      >
        <template v-if="chip.label">
          <span class="text-slate-400 group-hover:text-rose-400 font-black uppercase tracking-wider text-[10px]">{{ chip.label }}:</span>
        </template>
        <span class="max-w-[220px] truncate">{{ chip.value }}</span>
        <X class="w-3 h-3 opacity-40 group-hover:opacity-100 shrink-0" />
      </button>
    </div>

    <!-- Panel -->
    <Transition name="panel">
      <div v-if="isPanelOpen" class="border-b border-slate-100 bg-slate-50/60 px-5 py-5">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-x-5 gap-y-5">

          <!-- Identificação -->
          <div v-if="hasField('identification')" class="space-y-3.5">
            <h3 :class="groupTitleClass">
              <Flag class="w-3.5 h-3.5 text-blue-500" />
              Identificação
            </h3>
            <div>
              <label :class="labelClass">Contrato</label>
              <input
                v-model="draft.contract_number"
                @input="scheduleApply"
                type="text"
                :class="fieldClass"
                placeholder="Ex: 1370"
              />
            </div>
            <div>
              <label :class="labelClass">Registro</label>
              <input
                v-model="draft.register_number"
                @input="scheduleApply"
                type="text"
                :class="fieldClass"
                placeholder="Ex: 45885"
              />
            </div>
          </div>

          <!-- Empresas -->
          <div v-if="hasField('companies')" class="space-y-3.5">
            <h3 :class="groupTitleClass">
              <Building2 class="w-3.5 h-3.5 text-blue-500" />
              Empresas &amp; Vendedor
            </h3>
            <div>
              <label :class="labelClass">Exportador</label>
              <div class="relative">
                <SearchableSelect
                  v-model="draft.exporter_id"
                  :options="exporters"
                  placeholder="Todos"
                  show-all
                  clearable
                  @change="onFieldChange"
                />
              </div>
            </div>
            <div>
              <label :class="labelClass">Importador</label>
              <div class="relative">
                <SearchableSelect
                  v-model="draft.importer_id"
                  :options="importers"
                  placeholder="Todos"
                  show-all
                  clearable
                  @change="onFieldChange"
                />
              </div>
            </div>
            <div>
              <label :class="labelClass">Vendedor</label>
              <div class="relative">
                <SearchableSelect
                  v-model="draft.seller_id"
                  :options="sellers"
                  placeholder="Todos"
                  show-all
                  clearable
                  @change="onFieldChange"
                />
              </div>
            </div>
          </div>

          <!-- Mercadoria & País -->
          <div v-if="hasField('goods')" class="space-y-3.5">
            <h3 :class="groupTitleClass">
              <Package class="w-3.5 h-3.5 text-blue-500" />
              Mercadoria &amp; País
            </h3>
            <div>
              <label :class="labelClass">Produto</label>
              <div class="relative">
                <SearchableSelect
                  v-model="draft.product_id"
                  :options="contractProducts"
                  placeholder="Todos"
                  show-all
                  clearable
                  @change="onFieldChange"
                />
              </div>
            </div>
            <div>
              <label :class="labelClass">País</label>
              <div class="relative">
                <SearchableSelect
                  v-model="draft.country"
                  :options="countries"
                  placeholder="Todos"
                  show-all
                  clearable
                  @change="onFieldChange"
                />
              </div>
            </div>
          </div>

          <!-- STATUS & Período -->
          <div v-if="hasField('status')" class="space-y-3.5">
            <h3 :class="groupTitleClass">
              <CalendarRange class="w-3.5 h-3.5 text-blue-500" />
              STATUS &amp; Período
            </h3>
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="option in statusOptions"
                :key="option.id"
                type="button"
                @click="toggleStatus(option.id)"
                :class="toggleClass(draft.status.includes(option.id))"
              >
                <Check v-if="draft.status.includes(option.id)" class="w-3 h-3" />
                {{ option.name }}
              </button>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label :class="labelClass">De</label>
                <input v-model="draft.date_from" @change="onFieldChange" type="date" :class="fieldClass" />
              </div>
              <div>
                <label :class="labelClass">Até</label>
                <input v-model="draft.date_to" @change="onFieldChange" type="date" :class="fieldClass" />
              </div>
            </div>
          </div>

          <!-- Situação -->
          <div v-if="hasField('situation')" class="space-y-3.5 xl:col-span-4 border-t border-slate-200 pt-4">
            <div class="flex flex-wrap items-center gap-2.5">
              <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest mr-1">Situação:</span>
              <button
                v-if="situationItems.includes('delayed')"
                type="button"
                @click="draft.delayed = !draft.delayed; onFieldChange()"
                :class="toggleClass(draft.delayed, 'rose')"
                title="ETA prevista (ou vencida) sem nº DHL e processo não finalizado"
              >
                <AlertTriangle v-if="draft.delayed" class="w-3 h-3" />
                Em atraso
              </button>
              <button type="button" @click="draft.active_only = !draft.active_only; onFieldChange()" :class="toggleClass(draft.active_only)">
                Somente ativos
              </button>
              <button
                v-if="situationItems.includes('pending_commission')"
                type="button"
                @click="draft.pending_commission = !draft.pending_commission; onFieldChange()"
                :class="toggleClass(draft.pending_commission)"
              >Comissão pendente</button>
              <button type="button" @click="draft.has_incident = !draft.has_incident; onFieldChange()" :class="toggleClass(draft.has_incident)">
                Com incidente
              </button>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between gap-3 mt-5 pt-4 border-t border-slate-200">
          <p class="text-[11px] font-medium text-slate-400">
            Os filtros são aplicados automaticamente e ficam na URL — você pode compartilhar o link.
          </p>
          <div class="flex items-center gap-2">
            <button
              v-if="activeCount > 0"
              type="button"
              @click="clearAll"
              class="px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-widest text-slate-500 hover:bg-slate-200 transition-colors"
            >Limpar tudo</button>
            <button
              type="button"
              @click="isPanelOpen = false"
              class="px-5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold uppercase tracking-widest transition-colors"
            >Fechar</button>
          </div>
        </div>
      </div>
    </Transition>
</template>

<style scoped>
.panel-enter-active, .panel-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.panel-enter-from, .panel-leave-to { opacity: 0; transform: translateY(-6px); }
</style>
