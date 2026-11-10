<script setup>
import { getBcp47Locale } from '@/Utils/locale';
import { ref, watch, computed, onMounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import {
  X, Loader2, FileText, DollarSign, Truck, Trash2,
  CheckCircle, Plus, Package, ChevronDown,
} from 'lucide-vue-next';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { contractUploads, csrfToken, formatSize } from '@/Stores/ContractUploadStore';
import { ContractDocumentRules } from '@/Utils/contractDocuments';
import { trans } from 'laravel-vue-i18n';

const props = defineProps({
  isOpen: { type: Boolean, required: true },
  contractId: { type: [Number, String], default: null },
  clients: { type: Array, default: () => [] },
  sellers: { type: Array, default: () => [] },
  contractProducts: { type: Array, default: () => [] },
  statusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const currentTab = ref('general');
const expandedItem = ref(null);
const loading = ref(false);
const loadError = ref(null);

const blankItem = () => ({
  id: null,
  register_number: '',
  product_id: '',
  quantity_tons: '',
  price_per_ton_usd: '',
  sales_usd: '',
  commission_rate: '2',
  commission_usd: '',
  to_pay_rate: '',
  to_pay_usd: '',
  etd_date: '',
  eta_date: '',
  dhl_number: '',
  video_date: '',
  video_reference: '',
  observations: '',
});

const form = useForm({
  date: '',
  contract_number: '',
  exporter_id: '',
  importer_id: '',
  country: '',
  seller_id: '',
  status: '',
  dhl_date: '',
  dhl_number: '',
  etd_date: '',
  eta_date: '',
  video_sent: false,
  video_reference: '',
  video_date: '',
  incident: '',
  estimated_receipt_date: '',
  exchange_rate: '',
  receipt_date: '',
  paid_in_date: '',
  paid_in_brl: '',
  observations: '',
  shipping_company: '',
  container_number: '',
  items: [blankItem()],
});

const isEditing = computed(() => Boolean(props.contractId));

// ─── Documentos ──────────────────────────────────────────────────────────────
const documents = ref([]);
const fileInput = ref(null);
const documentAccept = ContractDocumentRules.ACCEPT;

const pendingUploads = computed(() =>
  props.contractId ? contractUploads.forContract(props.contractId) : []);

const loadDocuments = async () => {
  if (!props.contractId) {
    documents.value = [];
    return;
  }

  const response = await fetch(route('contracts.show', props.contractId), {
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  });

  if (!response.ok) return;

  documents.value = (await response.json()).documents || [];
};

const handleFiles = (event) => {
  if (!props.contractId) return;

  const contract = { id: props.contractId, contract_number: form.contract_number };
  [...event.target.files].forEach((file) => contractUploads.start(contract, file));
  event.target.value = '';
};

const deleteDocument = async (document) => {
  if (!confirm(trans('Delete ":name"?', { name: document.name }))) return;

  await fetch(route('contracts.documents.destroy', document.id), {
    method: 'DELETE',
    headers: {
      'X-XSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
      Accept: 'application/json',
    },
  });

  await loadDocuments();
};

// Um envio continua rodando mesmo que o usuário navegue; ao voltar, o card
// já reflete o progresso salvo no store global.
watch(() => contractUploads.revision, async () => {
  if (props.contractId && contractUploads.lastContractId === Number(props.contractId)) {
    await loadDocuments();
  }
});

watch(isEditing, (editing) => {
  if (!editing) documents.value = [];
});

onMounted(loadDocuments);

// ─── Rascunho local (contrato novo) ──────────────────────────────────────────
// Fechar o modal não pode perder o que o funcionário já digitou.
const page = usePage();
const draftKey = `jrspice_contract_draft_${page.props.auth?.user?.id ?? 'guest'}`;

const readDraft = () => {
  try {
    return JSON.parse(localStorage.getItem(draftKey) || 'null');
  } catch {
    return null;
  }
};

const writeDraft = () => {
  try {
    localStorage.setItem(draftKey, JSON.stringify(form.data()));
  } catch {
    /* quota */
  }
};

const clearDraft = () => {
  try {
    localStorage.removeItem(draftKey);
  } catch {
    /* quota */
  }
};

const restoreDraft = () => {
  const draft = readDraft();
  if (!draft) return false;

  Object.keys(form.data()).forEach((key) => {
    if (key === 'items') return;
    if (!(key in draft)) return;
    form[key] = draft[key];
  });

  if (Array.isArray(draft.items) && draft.items.length) {
    form.items = draft.items.map((item) => ({ ...blankItem(), ...item }));
  }


  return true;
};

// Persiste enquanto o formulário de um contrato NOVO estiver aberto.
watch(form, () => {
  if (props.contractId) return;
  writeDraft();
}, { deep: true });

const countryOptions = computed(() => {
  const names = new Set();
  props.clients.forEach((client) => {
    if (client.country) names.add(client.country);
  });
  ['China', 'India', 'Egito', 'Vietnã', 'Peru', 'Paquistão', 'Canadá', 'Turquia', 'Espanha', 'Indonesia', 'Malásia', 'Guatemala', 'Uruguai', 'Sudão', 'Nigeria', 'Costa do Marfim', 'México', 'França', 'Brasil', 'Portugal']
    .forEach((name) => names.add(name));
  return [...names].sort((a, b) => a.localeCompare(b, 'pt-BR'));
});

const exporters = computed(() => props.clients.filter((c) => c.type === 'exporter'));
const importers = computed(() => props.clients.filter((c) => c.type === 'importer'));

const shippingCompanyOptions = ['HMM', 'Evergreen', 'Maersk', 'CMA CGM', 'Hapag lloyd', 'MSC', 'OOCL', 'Cosco', 'Zim']
  .map((name) => ({ id: name, name }));

const toDateInput = (value) => (value ? String(value).slice(0, 10) : '');

const hydrate = (payload) => {
  Object.keys(form.data()).forEach((key) => {
    if (key === 'items') return;
    const value = payload[key];
    if (key === 'video_sent') {
      form.video_sent = Boolean(value);
      return;
    }
    form[key] = value === null || value === undefined ? '' : (key.endsWith('_date') || key === 'date' ? toDateInput(value) : value);
  });

  const items = Array.isArray(payload.items) && payload.items.length ? payload.items : [blankItem()];
  form.items = items.map((item) => ({
    id: item.id ?? null,
    register_number: item.register_number ?? '',
    product_id: item.product_id ?? '',
    quantity_tons: item.quantity_tons ?? '',
    price_per_ton_usd: item.price_per_ton_usd ?? '',
    sales_usd: item.sales_usd ?? '',
    commission_rate: item.commission_rate ?? '',
    commission_usd: item.commission_usd ?? '',
    to_pay_rate: item.to_pay_rate ?? '',
    to_pay_usd: item.to_pay_usd ?? '',
    etd_date: toDateInput(item.etd_date),
    eta_date: toDateInput(item.eta_date),
    dhl_number: item.dhl_number ?? '',
    video_date: toDateInput(item.video_date),
    video_reference: item.video_reference ?? '',
    observations: item.observations ?? '',
  }));
};

watch(() => props.isOpen, async (open) => {
  currentTab.value = 'general';
  expandedItem.value = null;
  loadError.value = null;

  if (!open) {
    // envios concluídos/errados não precisam sobreviver ao fechamento;
    // os que estão rodando continuam no store global.
    if (props.contractId) contractUploads.clearContract(props.contractId);
    return;
  }

  if (!props.contractId) {
    form.reset();
    form.clearErrors();
    form.items = [blankItem()];

    if (restoreDraft()) {
      currentTab.value = 'general';
    }

    return;
  }

  loading.value = true;
  try {
    const response = await fetch(route('contracts.show', props.contractId), {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!response.ok) throw new Error(trans('Could not load the contract.'));
    const payload = await response.json();
    documents.value = payload.documents || [];
    hydrate(payload);
  } catch (error) {
    loadError.value = error.message || trans('Error loading the contract.');
  } finally {
    loading.value = false;
  }
}, { immediate: true });

const recalcItem = (item) => {
  const quantity = parseFloat(item.quantity_tons) || 0;
  const price = parseFloat(item.price_per_ton_usd) || 0;
  item.sales_usd = quantity && price ? (quantity * price).toFixed(2) : '';

  const rate = parseFloat(item.commission_rate);
  if (!Number.isNaN(rate) && item.sales_usd) {
    item.commission_usd = ((parseFloat(item.sales_usd) * rate) / 100).toFixed(2);
  }

  const toPayRate = parseFloat(item.to_pay_rate);
  if (!Number.isNaN(toPayRate) && item.sales_usd) {
    item.to_pay_usd = ((parseFloat(item.sales_usd) * toPayRate) / 100).toFixed(2);
  }
};

const itemSales = (item) => {
  if (item.sales_usd !== '' && item.sales_usd !== null && item.sales_usd !== undefined) {
    const explicit = parseFloat(item.sales_usd);
    if (!Number.isNaN(explicit)) return explicit;
  }

  const quantity = parseFloat(item.quantity_tons);
  const price = parseFloat(item.price_per_ton_usd);

  return Number.isNaN(quantity) || Number.isNaN(price) ? 0 : quantity * price;
};

const estimatedEuro = (item) => {
  const commission = parseFloat(item.commission_usd) || 0;
  const rate = parseFloat(form.exchange_rate) || 0;
  return commission && rate ? (commission * rate).toFixed(2) : '';
};

const addItem = () => form.items.push(blankItem());

const removeItem = (index) => {
  if (form.items.length === 1) {
    form.items[0] = blankItem();
    return;
  }
  form.items.splice(index, 1);
  if (expandedItem.value === index) expandedItem.value = null;
};

const toggleItemOverrides = (index) => {
  expandedItem.value = expandedItem.value === index ? null : index;
};

const totals = computed(() => {
  const sales = form.items.reduce((sum, item) => sum + itemSales(item), 0);
  const commission = form.items.reduce((sum, item) => sum + (parseFloat(item.commission_usd) || 0), 0);
  const quantity = form.items.reduce((sum, item) => sum + (parseFloat(item.quantity_tons) || 0), 0);
  return { sales, commission, quantity };
});

const autoPayCommission = () => {
  const today = new Date().toISOString().slice(0, 10);
  const rate = parseFloat(form.exchange_rate) || 5.15;

  if (!form.exchange_rate) form.exchange_rate = rate;

  form.paid_in_date = today;
  form.paid_in_brl = (totals.value.commission * rate).toFixed(2);
  if (!form.receipt_date) form.receipt_date = today;
};

const submit = () => {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      if (!props.contractId) clearDraft();
      form.reset();
      emit('close');
    },
  };

  if (isEditing.value) {
    form.put(route('contracts.update', props.contractId), options);
  } else {
    form.post(route('contracts.store'), options);
  }
};

const tabs = [
  { key: 'general', label: trans('Contract & Items'), icon: FileText },
  { key: 'financial', label: trans('Financial'), icon: DollarSign },
  { key: 'logistics', label: trans('Logistics & Status'), icon: Truck },
];

const fieldClass = 'mt-1.5 block w-full px-4 py-2.5 text-sm font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all placeholder:text-slate-300 placeholder:font-normal';
const labelClass = 'block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1';
const cardClass = 'bg-white rounded-2xl border border-slate-100 shadow-sm p-5';
const sectionTitleClass = 'text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 pb-3 border-b border-slate-100';
const money = (value) => (value === '' || value === null || value === undefined
  ? '—'
  : `$ ${Number(value).toLocaleString(getBcp47Locale(), { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
</script>

<template>
  <Transition name="fade">
    <div v-if="isOpen" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[70]" @click="$emit('close')" />
  </Transition>

  <Transition name="slide">
    <div v-if="isOpen" class="fixed inset-y-0 right-0 z-[80] w-full max-w-4xl flex flex-col bg-[#f8fafc] shadow-2xl">
      <div class="bg-white border-b border-slate-100 px-7 py-5 flex items-center justify-between shrink-0">
        <div>
          <h2 class="text-lg font-black text-slate-800 uppercase tracking-tighter">
            {{ isEditing ? $t('Edit Contract') : $t('New Contract') }}
          </h2>
          <p class="text-xs font-semibold text-slate-400 mt-1">
            {{ isEditing ? $t('Contract #:number', { number: form.contract_number || props.contractId }) : $t('Fill in the contract and its items') }}
          </p>
        </div>
        <button @click="$emit('close')" class="p-2.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all">
          <X class="w-5 h-5" />
        </button>
      </div>

      <div class="bg-white border-b border-slate-100 px-7 flex gap-1 shrink-0">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="currentTab = tab.key"
          type="button"
          class="flex items-center gap-2 px-4 py-4 text-xs font-bold uppercase tracking-widest border-b-2 transition-all -mb-px"
          :class="currentTab === tab.key ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-400 hover:text-slate-700'"
        >
          <component :is="tab.icon" class="w-4 h-4" />
          {{ tab.label }}
        </button>
      </div>

      <form @submit.prevent="submit" class="flex-1 flex flex-col min-h-0">
        <div class="flex-1 overflow-y-auto px-7 py-6 space-y-5">
          <div v-if="loadError" class="bg-rose-50 border border-rose-200 rounded-2xl px-5 py-4 text-sm font-semibold text-rose-700">
            {{ loadError }}
          </div>

          <div v-if="loading" class="flex items-center justify-center py-16 text-slate-400">
            <Loader2 class="w-6 h-6 animate-spin mr-3" />
            <span class="text-sm font-bold uppercase tracking-widest">{{ $t('Loading contract...') }}</span>
          </div>

          <template v-else>
            <!-- ── CONTRATO & ITENS ───────────────────────── -->
            <template v-if="currentTab === 'general'">
              <div :class="cardClass">
                <h3 :class="sectionTitleClass">{{ $t('Identification') }}</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                  <div>
                    <label :class="labelClass">{{ $t('Date') }}</label>
                    <input type="date" v-model="form.date" :class="fieldClass" />
                  </div>
                  <div>
                    <label :class="labelClass">{{ $t('Contract') }}</label>
                    <input type="text" v-model="form.contract_number" :class="fieldClass" :placeholder="$t('E.g. 1370')" />
                  </div>
                  <div>
                    <label :class="labelClass">{{ $t('Country') }}</label>
                    <input type="text" v-model="form.country" :class="fieldClass" list="contract-countries" :placeholder="$t('E.g. China')" />
                    <datalist id="contract-countries">
                      <option v-for="name in countryOptions" :key="name" :value="name" />
                    </datalist>
                  </div>
                </div>
                <p v-if="form.errors.country" class="mt-2 text-xs font-semibold text-rose-600">{{ form.errors.country }}</p>
              </div>

              <div :class="cardClass">
                <h3 :class="sectionTitleClass">{{ $t('Companies & Seller') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label :class="labelClass">{{ $t('Exporter') }}</label>
                    <SearchableSelect v-model="form.exporter_id" :options="exporters" :placeholder="$t('Select...')" class="mt-1.5" />
                  </div>
                  <div>
                    <label :class="labelClass">{{ $t('Importer') }}</label>
                    <SearchableSelect v-model="form.importer_id" :options="importers" :placeholder="$t('Select...')" class="mt-1.5" />
                  </div>
                  <div>
                    <label :class="labelClass">{{ $t('Seller') }}</label>
                    <SearchableSelect v-model="form.seller_id" :options="sellers" :placeholder="$t('Select...')" class="mt-1.5" />
                  </div>
                </div>
                <p v-if="form.errors.exporter_id" class="mt-2 text-xs font-semibold text-rose-600">{{ form.errors.exporter_id }}</p>
              </div>

              <div :class="cardClass">
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100 gap-4">
                  <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $t('Contract Items') }}</h3>
                  <div class="flex items-center gap-3 text-[11px] font-bold text-slate-500">
                    <span>{{ $t(':count item(s)', { count: form.items.length }) }}</span>
                    <span class="text-slate-300">|</span>
                    <span>{{ Number(totals.quantity || 0).toLocaleString(getBcp47Locale(), { maximumFractionDigits: 3 }) }} {{ $t('ton') }}</span>
                    <span class="text-slate-300">|</span>
                    <span class="text-slate-800">{{ money(totals.sales) }}</span>
                  </div>
                </div>

                <div class="space-y-3">
                  <div
                    v-for="(item, index) in form.items"
                    :key="index"
                    class="border border-slate-100 rounded-2xl"
                  >
                    <div class="grid grid-cols-2 md:grid-cols-6 gap-3 p-4 bg-slate-50/60 rounded-t-2xl">
                      <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $t('Registration') }}</label>
                        <input type="text" v-model="item.register_number" class="mt-1 block w-full px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20" placeholder="45885" />
                      </div>
                      <div class="col-span-2">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $t('Product') }}</label>
                        <SearchableSelect
                          v-model="item.product_id"
                          :options="contractProducts"
                          :placeholder="$t('Select the product...')"
                          class="mt-1"
                        />
                        <p v-if="form.errors[`items.${index}.product_id`]" class="mt-1 text-[10px] font-semibold text-rose-600">
                          {{ form.errors[`items.${index}.product_id`] }}
                        </p>
                      </div>
                      <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $t('Quantity') }}</label>
                        <input type="number" step="0.001" min="0" v-model="item.quantity_tons" @input="recalcItem(item)" class="mt-1 block w-full px-3 py-2 text-sm font-medium text-slate-800 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 tabular-nums" placeholder="0.000" />
                      </div>
                      <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $t('Price Ton US$') }}</label>
                        <input type="number" step="0.01" min="0" v-model="item.price_per_ton_usd" @input="recalcItem(item)" class="mt-1 block w-full px-3 py-2 text-sm font-medium text-slate-800 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 tabular-nums" placeholder="0.00" />
                      </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 px-4 py-3 border-t border-slate-100 items-end">
                      <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $t('Sales US$') }} <span class="text-blue-400 normal-case">{{ $t('auto') }}</span></label>
                        <input type="text" :value="money(itemSales(item))" readonly class="mt-1 block w-full px-3 py-2 text-sm font-bold text-slate-600 bg-slate-50 border border-slate-200 rounded-lg cursor-not-allowed tabular-nums" />
                      </div>
                      <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $t('Commission %') }}</label>
                        <input type="number" step="0.01" min="0" max="100" v-model="item.commission_rate" @input="recalcItem(item)" class="mt-1 block w-full px-3 py-2 text-sm font-medium text-slate-800 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 tabular-nums" placeholder="2" />
                      </div>
                      <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $t('Commission US$') }}</label>
                        <input type="number" step="0.01" min="0" v-model="item.commission_usd" class="mt-1 block w-full px-3 py-2 text-sm font-bold text-emerald-700 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 tabular-nums" placeholder="0.00" />
                      </div>
                      <div>
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $t('To pay %') }}</label>
                        <input type="number" step="0.01" min="0" max="100" v-model="item.to_pay_rate" @input="recalcItem(item)" class="mt-1 block w-full px-3 py-2 text-sm font-medium text-slate-800 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 tabular-nums" placeholder="0.9" />
                      </div>
                      <div class="flex items-center gap-1.5">
                        <input type="number" step="0.01" min="0" v-model="item.to_pay_usd" class="flex-1 min-w-0 px-3 py-2 text-sm font-medium text-slate-800 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 tabular-nums" :placeholder="$t('To pay')" />
                        <button
                          type="button"
                          @click="removeItem(index)"
                          class="p-2 rounded-lg text-rose-400 hover:text-rose-600 hover:bg-rose-50 transition-colors shrink-0"
                          :title="$t('Remove item')"
                        >
                          <Trash2 class="w-4 h-4" />
                        </button>
                      </div>
                    </div>

                    <div class="px-4 pb-3">
                      <button
                        type="button"
                        @click="toggleItemOverrides(index)"
                        class="inline-flex items-center gap-1.5 text-[10px] font-bold text-slate-400 hover:text-blue-600 uppercase tracking-wider transition-colors"
                      >
                        <ChevronDown class="w-3 h-3 transition-transform" :class="expandedItem === index ? 'rotate-180' : ''" />
                        {{ $t('Specific logistics for this item') }}
                      </button>

                      <div v-if="expandedItem === index" class="grid grid-cols-2 md:grid-cols-3 gap-3 mt-3 pt-3 border-t border-slate-100">
                        <div>
                          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">ETD</label>
                          <input type="date" v-model="item.etd_date" :class="fieldClass + ' mt-0.5'" />
                        </div>
                        <div>
                          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">ETA</label>
                          <input type="date" v-model="item.eta_date" :class="fieldClass + ' mt-0.5'" />
                        </div>
                        <div>
                          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $t('DHL No.') }}</label>
                          <input type="text" v-model="item.dhl_number" :class="fieldClass + ' mt-0.5'" placeholder="DHL0000000000" />
                        </div>
                        <div>
  
                        </div>
                        <div>
                          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $t('VIDEO DATE') }}</label>
                          <input type="date" v-model="item.video_date" :class="fieldClass + ' mt-0.5'" />
                        </div>
                        <div>
                          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $t('Video (tracking)') }}</label>
                          <input type="text" v-model="item.video_reference" :class="fieldClass + ' mt-0.5'" placeholder="300100/1" />
                        </div>
                        <div class="col-span-2 md:col-span-3">
                          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">{{ $t('Item observations') }}</label>
                          <textarea v-model="item.observations" rows="2" :class="fieldClass + ' mt-0.5 resize-none'"></textarea>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <button
                  type="button"
                  @click="addItem"
                  class="mt-4 inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs uppercase tracking-wider border border-blue-200/60 transition-all"
                >
                  <Plus class="w-4 h-4" />
                  {{ $t('Add item') }}
                </button>
                <p v-if="form.errors.items" class="mt-2 text-xs font-semibold text-rose-600">{{ form.errors.items }}</p>
              </div>

            </template>

            <!-- ── FINANCEIRO ───────────────────────── -->
            <template v-if="currentTab === 'financial'">
              <div :class="cardClass">
                <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100 gap-4">
                  <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $t('Exchange & Estimate') }}</h3>
                  <button
                    type="button"
                    @click="autoPayCommission"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-[10px] uppercase tracking-wider border border-emerald-200/50 transition-all select-none hover:scale-105 active:scale-95"
                  >
                    <CheckCircle class="w-3.5 h-3.5 text-emerald-600" />
                    {{ $t('Automatic Write-off') }}
                  </button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label :class="labelClass">{{ $t('Exchange Rate') }}</label>
                    <input type="number" step="0.0001" min="0" v-model="form.exchange_rate" :class="fieldClass" placeholder="0.9100" />
                  </div>
                  <div>
                    <label :class="labelClass">{{ $t('Estimated in Euro') }} <span class="text-blue-400 normal-case">{{ $t('auto') }}</span></label>
                    <input type="text" readonly :value="money(totals.commission * (parseFloat(form.exchange_rate) || 0))" :class="fieldClass + ' bg-slate-50 cursor-not-allowed text-slate-500'" />
                  </div>
                  <div>
                    <label :class="labelClass">{{ $t('Total Commission US$') }} <span class="text-blue-400 normal-case">{{ $t('auto') }}</span></label>
                    <input type="text" readonly :value="money(totals.commission)" :class="fieldClass + ' bg-slate-50 cursor-not-allowed text-slate-500 font-bold text-emerald-700'" />
                  </div>
                </div>
              </div>

              <div :class="cardClass">
                <h3 :class="sectionTitleClass">{{ $t('Payments') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label :class="labelClass">{{ $t('Estimated Receipt Date') }}</label>
                    <input type="date" v-model="form.estimated_receipt_date" :class="fieldClass" />
                  </div>
                  <div>
                    <label :class="labelClass">{{ $t('Receipt') }}</label>
                    <input type="date" v-model="form.receipt_date" :class="fieldClass" />
                  </div>
                  <div>
                    <label :class="labelClass">{{ $t('Paid on') }}</label>
                    <input type="date" v-model="form.paid_in_date" :class="fieldClass" />
                  </div>
                  <div>
                    <label :class="labelClass">{{ $t('Paid in BRL') }}</label>
                    <input type="number" step="0.01" min="0" v-model="form.paid_in_brl" :class="fieldClass" placeholder="R$ 0,00" />
                  </div>
                </div>
              </div>
            </template>

            <!-- ── LOGÍSTICA & STATUS ───────────────────────── -->
            <template v-if="currentTab === 'logistics'">
              <div :class="cardClass">
                <h3 :class="sectionTitleClass">{{ $t('Status, Shipping & DHL') }}</h3>

                <div class="space-y-4">
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                      <label :class="labelClass">{{ $t('Status') }}</label>
                      <SearchableSelect v-model="form.status" :options="statusOptions" :placeholder="$t('Select...')" class="mt-1.5" />
                      <p v-if="form.errors.status" class="mt-2 text-xs font-semibold text-rose-600">{{ form.errors.status }}</p>
                    </div>
                    <div>
                      <label :class="labelClass">{{ $t('ETD shipping date') }}</label>
                      <input type="date" v-model="form.etd_date" :class="fieldClass" />
                    </div>
                    <div>
                      <label :class="labelClass">{{ $t('ETA arrival date') }}</label>
                      <input type="date" v-model="form.eta_date" :class="fieldClass" />
                    </div>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-slate-100">
                    <div>
                      <label :class="labelClass">{{ $t('DHL No.') }}</label>
                      <input type="text" v-model="form.dhl_number" :class="fieldClass" placeholder="DHL0000000000" />
                    </div>
                    <div>
                      <label :class="labelClass">{{ $t('DHL date') }}</label>
                      <input type="date" v-model="form.dhl_date" :class="fieldClass" />
                    </div>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-slate-100">
                    <div>
                      <label :class="labelClass">{{ $t('Carrier') }}</label>
                      <SearchableSelect v-model="form.shipping_company" :options="shippingCompanyOptions" :placeholder="$t('Select...')" class="mt-1.5" />
                    </div>
                    <div>
                      <label :class="labelClass">{{ $t('Container / Booking No.') }}</label>
                      <input type="text" v-model="form.container_number" :class="fieldClass" :placeholder="$t('E.g. MSMU8204910')" />
                    </div>
                  </div>
                </div>
              </div>

              <div :class="cardClass">
                <h3 :class="sectionTitleClass">{{ $t('Video, Incident & Observations') }}</h3>
                <div class="space-y-4">
                  <div class="flex items-center gap-4 p-3 bg-slate-50 rounded-xl border border-slate-100">
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                      <input type="checkbox" v-model="form.video_sent" class="sr-only peer" />
                      <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:shadow after:transition-all peer-checked:after:translate-x-5"></div>
                    </label>
                    <span class="text-sm font-bold text-slate-600">{{ $t('Video sent') }}</span>
                    <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-3">
                      <input type="date" v-model="form.video_date" :disabled="!form.video_sent" :class="fieldClass + ' mt-0' + (!form.video_sent ? ' opacity-40 cursor-not-allowed' : '')" />
                      <input type="text" v-model="form.video_reference" :disabled="!form.video_sent" :class="fieldClass + ' mt-0' + (!form.video_sent ? ' opacity-40 cursor-not-allowed' : '')" :placeholder="$t('VIDEO DATE / tracking')" />
                    </div>
                  </div>

                  <div>
                    <label :class="labelClass">{{ $t('Incident') }}</label>
                    <input type="text" v-model="form.incident" :class="fieldClass" :placeholder="$t('Describe the incident, if any')" />
                  </div>

                  <div>
                    <label :class="labelClass">{{ $t('Observations') }}</label>
                    <textarea v-model="form.observations" rows="4" :class="fieldClass + ' resize-none'" :placeholder="$t('Internal control: received, paid, cancelled...')"></textarea>
                  </div>
                </div>
              </div>

              <!-- ── DOCUMENTOS ─────────────────────────────────── -->
              <div v-if="isEditing" :class="cardClass">
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100 gap-4">
                  <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $t('Documents') }}</h3>
                  <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                    {{ $t('up to :size per file', { size: ContractDocumentRules.HUMAN_MAX_SIZE }) }}
                  </span>
                </div>

                <div class="space-y-4">
                  <label for="contract-document-input" class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-slate-200 hover:border-blue-400 hover:bg-blue-50/40 rounded-2xl px-4 py-6 cursor-pointer transition-all text-center">
                    <input
                      ref="fileInput"
                      id="contract-document-input"
                      type="file"
                      multiple
                      :accept="documentAccept"
                      class="hidden"
                      @change="handleFiles"
                    />
                    <Package class="w-7 h-7 text-slate-300" />
                    <span class="text-xs font-bold text-slate-600">{{ $t('Send files to the client') }}</span>
                    <span class="text-[10px] font-medium text-slate-400">
                      {{ $t('video, PDF, DOC, XLS, JPG or PNG — max :size', { size: ContractDocumentRules.HUMAN_MAX_SIZE }) }}
                    </span>
                  </label>

                  <div v-if="pendingUploads.length" class="space-y-2">
                    <div
                      v-for="item in pendingUploads"
                      :key="item.key"
                      class="rounded-xl border border-blue-100 bg-blue-50/50 px-4 py-3"
                    >
                      <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                          <p class="text-xs font-bold text-slate-700 truncate">{{ item.name }}</p>
                          <p class="text-[10px] font-semibold text-slate-400">
                            {{ formatSize(item.size) }}
                            <template v-if="item.state === 'uploading'">· {{ item.progress }}%</template>
                            <template v-else-if="item.state === 'done'">· {{ $t('done') }}</template>
                          </p>
                        </div>
                        <button
                          v-if="item.state === 'uploading'"
                          type="button"
                          @click="contractUploads.cancel(item.key)"
                          class="text-[10px] font-bold uppercase tracking-wider text-rose-500 hover:text-rose-700 shrink-0"
                        >{{ $t('Cancel') }}</button>
                      </div>

                      <div class="mt-2 h-1.5 rounded-full bg-blue-100 overflow-hidden">
                        <div
                          class="h-full bg-blue-600 transition-all duration-300"
                          :style="{ width: item.progress + '%' }"
                        ></div>
                      </div>

                      <p v-if="item.state === 'error'" class="mt-2 text-[11px] font-semibold text-rose-600">
                        {{ item.message }}
                      </p>
                    </div>
                  </div>

                  <div v-if="documents.length" class="divide-y divide-slate-100 rounded-xl border border-slate-100 overflow-hidden">
                    <div
                      v-for="doc in documents"
                      :key="doc.id"
                      class="flex items-center gap-3 px-4 py-3 bg-white hover:bg-slate-50 transition-colors"
                    >
                      <div class="w-9 h-9 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center shrink-0">
                        <FileText
                          class="w-4 h-4"
                          :class="doc.file_type === 'video' ? 'text-purple-500' : doc.file_type === 'image' ? 'text-emerald-500' : 'text-blue-500'"
                        />
                      </div>

                      <div class="min-w-0 flex-1">
                        <a
                          :href="route('my-products.preview-document', doc.id)"
                          target="_blank"
                          class="text-xs font-bold text-slate-800 hover:text-blue-600 transition-colors truncate block"
                          :title="doc.name"
                        >{{ doc.name }}</a>
                        <p class="text-[10px] font-semibold text-slate-400">
                          {{ formatSize(doc.size_bytes) }}
                          <template v-if="doc.uploader">· {{ $t('by :name', { name: doc.uploader }) }}</template>
                        </p>
                      </div>

                      <a
                        :href="route('my-products.download-document', doc.id)"
                        class="text-[10px] font-bold uppercase tracking-wider text-slate-400 hover:text-blue-600 transition-colors shrink-0"
                      >{{ $t('Download') }}</a>

                      <button
                        type="button"
                        @click="deleteDocument(doc)"
                        class="p-1.5 rounded-lg text-slate-300 hover:text-rose-600 hover:bg-rose-50 transition-colors shrink-0"
                        :title="$t('Delete')"
                      >
                        <Trash2 class="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>

                  <p v-else class="text-xs font-medium text-slate-400 text-center py-4">
                    {{ $t('No documents attached to this contract yet.') }}
                  </p>
                </div>
              </div>

            </template>
          </template>
        </div>

        <div class="bg-white border-t border-slate-100 px-7 py-5 flex items-center justify-between gap-3 shrink-0">
          <button
            type="button"
            @click="$emit('close')"
            class="px-6 py-3 rounded-xl text-sm font-bold uppercase tracking-widest text-slate-500 hover:bg-slate-100 transition-colors"
          >{{ $t('Cancel') }}</button>
          <button
            type="submit"
            :disabled="form.processing || loading"
            class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-7 py-3 rounded-xl font-bold text-sm uppercase tracking-widest shadow-lg shadow-blue-200/60 transition-all active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed"
          >
            <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
            {{ form.processing ? $t('Saving...') : $t('Save Contract') }}
          </button>
        </div>
      </form>
    </div>
  </Transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-enter-active, .slide-leave-active { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-enter-from, .slide-leave-to { transform: translateX(100%); }
</style>
