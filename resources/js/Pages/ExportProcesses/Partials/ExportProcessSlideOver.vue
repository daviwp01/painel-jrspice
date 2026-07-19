<script setup>
import { ref, watch, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { X, Loader2, FileText, DollarSign, Truck, Trash2, AlertTriangle, CheckCircle } from 'lucide-vue-next';
import SearchableSelect from '@/Components/SearchableSelect.vue';

const props = defineProps({
  isOpen: { type: Boolean, required: true },
  process: { type: Object, default: () => null },
  exporters: Array,
  importers: Array,
  products: Array,
  sellers: Array
});

const emit = defineEmits(['close']);
const currentTab = ref('general');

const form = useForm({
  date: '', contract_number: '', register_number: '',
  exporter_id: '', importer_id: '', product_id: '',
  quantity_tons: '', price_per_ton_usd: '', sales_usd: '',
  annual_sales_usd: '', commission_usd: '', total_commission_usd: '',
  exchange_rate: '', estimated_euro: '', estimated_receipt_date: '',
  seller_id: '', to_pay_usd: '', receipt_date: '', paid_in_date: '',
  paid_in_brl: '', incident: '', video_sent: false, video_date: '',
  status: '', status_date: '', dhl_date: '', dhl_number: '',
  etd_date: '', eta_date: '', observations: '',
  shipping_company: '', container_number: '',
});

watch(() => props.process, (newProcess) => {
  if (newProcess) {
    Object.keys(form.data()).forEach(key => {
      let val = newProcess[key];
      // HTML5 date inputs expect YYYY-MM-DD format. We strip any time portion if present.
      if ((key.endsWith('_date') || key === 'date') && typeof val === 'string' && val) {
        val = val.substring(0, 10);
      }
      form[key] = val !== null && val !== undefined
        ? val
        : (typeof form[key] === 'boolean' ? false : '');
    });
  } else {
    form.reset();
  }
  currentTab.value = 'general';
}, { immediate: true });

const calculateSales = () => {
  const qty = parseFloat(form.quantity_tons) || 0;
  const price = parseFloat(form.price_per_ton_usd) || 0;
  form.sales_usd = (qty * price).toFixed(2);
};

const confirmingDelete = ref(false);
const isDeleting = ref(false);

const submit = () => {
  if (props.process?.id) {
    form.put(route('export-processes.update', props.process.id), {
      preserveScroll: true,
      onSuccess: () => { form.reset(); emit('close'); },
    });
  } else {
    form.post(route('export-processes.store'), {
      preserveScroll: true,
      onSuccess: () => { form.reset(); emit('close'); },
    });
  }
};

const deleteProcess = () => {
  if (!props.process?.id) return;
  isDeleting.value = true;
  router.delete(route('export-processes.destroy', props.process.id), {
    preserveScroll: true,
    onSuccess: () => {
      confirmingDelete.value = false;
      isDeleting.value = false;
      emit('close');
    },
    onError: () => { isDeleting.value = false; },
  });
};

const autoPayCommission = () => {
  const today = new Date().toISOString().split('T')[0];
  form.paid_in_date = today;

  const comm = parseFloat(form.commission_usd) || 0;
  const rate = parseFloat(form.exchange_rate) || 5.15;
  
  if (!form.exchange_rate) {
    form.exchange_rate = 5.15;
  }
  
  form.paid_in_brl = (comm * rate).toFixed(2);
  
  if (!form.receipt_date) {
    form.receipt_date = today;
  }
  
  alert(`Baixa efetuada com sucesso no formulário!\n\n• Data do Pagamento: ${today}\n• Taxa de Câmbio: R$ ${rate.toFixed(4)}\n• Total Pago (BRL): R$ ${(comm * rate).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}\n\n*Clique em "Salvar Contrato" para confirmar as mudanças.`);
};

const tabs = [
  { key: 'general',   label: 'Geral & Produto',   icon: FileText },
  { key: 'financial', label: 'Financeiro',         icon: DollarSign },
  { key: 'logistics', label: 'Logística & Status', icon: Truck },
];

const statusOptions = [
  'A embarcar dia', 'Transbordo até', 'Chegou porto dia',
  'Só faltando comissão', 'Invoice ENVIADA', 'Processo FINALIZADO',
];

const statusSelectOptions = computed(() => {
  return statusOptions.map(s => ({ id: s, name: s }));
});

const shippingCompanyOptions = [
  { id: 'HMM', name: 'HMM' },
  { id: 'Evergreen', name: 'Evergreen' },
  { id: 'Maersk', name: 'Maersk' },
  { id: 'CMA CGM', name: 'CMA CGM' },
  { id: 'Hapag lloyd', name: 'Hapag lloyd' },
  { id: 'MSC', name: 'MSC' },
  { id: 'OOCL', name: 'OOCL' },
  { id: 'Cosco', name: 'Cosco' },
  { id: 'Zim', name: 'Zim' },
];

const fieldClass = "mt-1.5 block w-full px-4 py-2.5 text-sm font-semibold text-slate-800 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all placeholder:text-slate-300 placeholder:font-normal";
const labelClass = "block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1";
</script>

<template>
  <!-- Backdrop -->
  <Transition name="fade">
    <div v-if="isOpen" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[70]" @click="$emit('close')" />
  </Transition>

  <!-- Panel -->
  <Transition name="slide">
    <div v-if="isOpen" class="fixed inset-y-0 right-0 z-[80] w-full max-w-3xl flex flex-col bg-[#f8fafc] shadow-2xl">

      <!-- Header -->
      <div class="bg-white border-b border-slate-100 px-7 py-5 flex items-center justify-between shrink-0">
        <div>
          <h2 class="text-lg font-black text-slate-800 uppercase tracking-tighter">
            {{ process ? 'Editar Contrato' : 'Novo Contrato' }}
          </h2>
          <p class="text-xs font-semibold text-slate-400 mt-1">
            {{ process ? `Contrato #${process.contract_number || process.id}` : 'Preencha as informações abaixo' }}
          </p>
        </div>
        <button @click="$emit('close')" class="p-2.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all">
          <X class="w-5 h-5" />
        </button>
      </div>

      <!-- Tab Bar -->
      <div class="bg-white border-b border-slate-100 px-7 flex gap-1 shrink-0">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          @click="currentTab = tab.key"
          type="button"
          class="flex items-center gap-2 px-4 py-4 text-xs font-bold uppercase tracking-widest border-b-2 transition-all -mb-px"
          :class="currentTab === tab.key
            ? 'border-blue-600 text-blue-600'
            : 'border-transparent text-slate-400 hover:text-slate-700'"
        >
          <component :is="tab.icon" class="w-4 h-4" />
          {{ tab.label }}
        </button>
      </div>

      <!-- Scrollable Body -->
      <form @submit.prevent="submit" class="flex-1 flex flex-col min-h-0">
        <div class="flex-1 overflow-y-auto px-7 py-6 space-y-5">

          <!-- ── TAB GERAL ─────────────────────────────── -->
          <template v-if="currentTab === 'general'">
            <!-- Identificação -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 pb-3 border-b border-slate-100">Identificação</h3>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label :class="labelClass">Data</label>
                  <input type="date" v-model="form.date" :class="fieldClass" />
                </div>
                <div>
                  <label :class="labelClass">Nº Contrato</label>
                  <input type="text" v-model="form.contract_number" :class="fieldClass" placeholder="Ex: 12345678" />
                </div>
                <div class="col-span-2">
                  <label :class="labelClass">Registro</label>
                  <input type="text" v-model="form.register_number" :class="fieldClass" placeholder="Nº de registro" />
                </div>
              </div>
            </div>

            <!-- Empresas -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 pb-3 border-b border-slate-100">Empresas</h3>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label :class="labelClass">Exportador</label>
                  <SearchableSelect
                    v-model="form.exporter_id"
                    :options="exporters"
                    placeholder="Selecione..."
                    class="mt-1.5"
                  />
                </div>
                <div>
                  <label :class="labelClass">Importador</label>
                  <SearchableSelect
                    v-model="form.importer_id"
                    :options="importers"
                    placeholder="Selecione..."
                    class="mt-1.5"
                  />
                </div>
              </div>
            </div>

            <!-- Mercadoria -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 pb-3 border-b border-slate-100">Mercadoria</h3>
              <div>
                <label :class="labelClass">Produto</label>
                <SearchableSelect
                  v-model="form.product_id"
                  :options="products"
                  placeholder="Selecione..."
                  class="mt-1.5"
                />
              </div>
            </div>
          </template>

          <!-- ── TAB FINANCEIRO ──────────────────────────── -->
          <template v-if="currentTab === 'financial'">
            <!-- Venda -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 pb-3 border-b border-slate-100">Valores da Venda</h3>
              <div class="grid grid-cols-3 gap-4">
                <div>
                  <label :class="labelClass">Qtd (Ton)</label>
                  <input type="number" step="0.01" v-model="form.quantity_tons" @input="calculateSales" :class="fieldClass" placeholder="0.00" />
                </div>
                <div>
                  <label :class="labelClass">Preço/Ton (USD)</label>
                  <input type="number" step="0.01" v-model="form.price_per_ton_usd" @input="calculateSales" :class="fieldClass" placeholder="0.00" />
                </div>
                <div>
                  <label :class="labelClass">Venda (USD) <span class="text-blue-500 ml-1 normal-case">auto</span></label>
                  <input type="number" step="0.01" v-model="form.sales_usd" readonly :class="fieldClass + ' bg-slate-50 cursor-not-allowed text-slate-500'" />
                </div>
                <div>
                  <label :class="labelClass">Venda Anual (USD)</label>
                  <input type="number" step="0.01" v-model="form.annual_sales_usd" :class="fieldClass" placeholder="0.00" />
                </div>
                <div>
                  <label :class="labelClass">Taxa Câmbio</label>
                  <input type="number" step="0.0001" v-model="form.exchange_rate" :class="fieldClass" placeholder="0.0000" />
                </div>
                <div>
                  <label :class="labelClass">Estimado Euro</label>
                  <input type="number" step="0.01" v-model="form.estimated_euro" :class="fieldClass" placeholder="0.00" />
                </div>
              </div>
            </div>

            <!-- Comissões -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
              <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100 gap-4">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Comissões</h3>
                <button
                  type="button"
                  @click="autoPayCommission"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-[10px] uppercase tracking-wider border border-emerald-200/50 transition-all select-none hover:scale-105 active:scale-95"
                >
                  <CheckCircle class="w-3.5 h-3.5 text-emerald-600 animate-pulse" />
                  Dar Baixa Automática
                </button>
              </div>
              <div class="grid grid-cols-3 gap-4">
                <div>
                  <label :class="labelClass">Vendedor</label>
                  <SearchableSelect
                    v-model="form.seller_id"
                    :options="sellers"
                    placeholder="Selecione..."
                    class="mt-1.5"
                  />
                </div>
                <div>
                  <label :class="labelClass">Comissão ($)</label>
                  <input type="number" step="0.01" v-model="form.commission_usd" :class="fieldClass" placeholder="0.00" />
                </div>
                <div>
                  <label :class="labelClass">TT Comissão ($)</label>
                  <input type="number" step="0.01" v-model="form.total_commission_usd" :class="fieldClass" placeholder="0.00" />
                </div>
              </div>
            </div>

            <!-- Pagamentos -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 pb-3 border-b border-slate-100">Pagamentos</h3>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label :class="labelClass">Data Est. Recebimento</label>
                  <input type="date" v-model="form.estimated_receipt_date" :class="fieldClass" />
                </div>
                <div>
                  <label :class="labelClass">Data Recebimento</label>
                  <input type="date" v-model="form.receipt_date" :class="fieldClass" />
                </div>
                <div>
                  <label :class="labelClass">A Pagar (USD)</label>
                  <input type="number" step="0.01" v-model="form.to_pay_usd" :class="fieldClass" placeholder="0.00" />
                </div>
                <div>
                  <label :class="labelClass">Pago em</label>
                  <input type="date" v-model="form.paid_in_date" :class="fieldClass" />
                </div>
                <div class="col-span-2">
                  <label :class="labelClass">Pago em (BRL)</label>
                  <input type="number" step="0.01" v-model="form.paid_in_brl" :class="fieldClass" placeholder="R$ 0,00" />
                </div>
              </div>
            </div>
          </template>

          <!-- ── TAB LOGÍSTICA ───────────────────────────── -->
          <template v-if="currentTab === 'logistics'">
            <!-- Status -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 pb-3 border-b border-slate-100">Status Atual</h3>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label :class="labelClass">Status</label>
                  <SearchableSelect
                    v-model="form.status"
                    :options="statusSelectOptions"
                    placeholder="Selecione..."
                    class="mt-1.5"
                  />
                </div>
                <div>
                  <label :class="labelClass">Data Status</label>
                  <input type="date" v-model="form.status_date" :class="fieldClass" />
                </div>
              </div>
            </div>

            <!-- Embarque DHL -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 pb-3 border-b border-slate-100">Embarque & DHL</h3>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label :class="labelClass">ETD – Data Embarque</label>
                  <input type="date" v-model="form.etd_date" :class="fieldClass" />
                </div>
                <div>
                  <label :class="labelClass">ETA – Data Chegada</label>
                  <input type="date" v-model="form.eta_date" :class="fieldClass" />
                </div>
                <div>
                  <label :class="labelClass">DHL Nº</label>
                  <input type="text" v-model="form.dhl_number" :class="fieldClass" placeholder="Ex: 1234567890" />
                </div>
                <div>
                  <label :class="labelClass">Data DHL</label>
                  <input type="date" v-model="form.dhl_date" :class="fieldClass" />
                </div>
                <div>
                  <label :class="labelClass">Transportadora (Container)</label>
                  <SearchableSelect
                    v-model="form.shipping_company"
                    :options="shippingCompanyOptions"
                    placeholder="Selecione..."
                    class="mt-1.5"
                  />
                </div>
                <div>
                  <label :class="labelClass">Container / Booking Nº</label>
                  <input type="text" v-model="form.container_number" :class="fieldClass" placeholder="Ex: MSMU8204910" />
                </div>
              </div>
            </div>

            <!-- Vídeo & Incidentes -->
            <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5">
              <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4 pb-3 border-b border-slate-100">Vídeo & Incidentes</h3>
              <div class="space-y-4">
                <!-- Toggle Vídeo -->
                <div class="flex items-center gap-4 p-3 bg-slate-50 rounded-xl border border-slate-100">
                  <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="checkbox" v-model="form.video_sent" class="sr-only peer" />
                    <div class="w-11 h-6 bg-slate-200 rounded-full peer peer-checked:bg-blue-600 transition-colors after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:shadow after:transition-all peer-checked:after:translate-x-5"></div>
                  </label>
                  <span class="text-sm font-bold text-slate-600">Vídeo Enviado</span>
                  <div class="flex-1">
                    <input type="date" v-model="form.video_date" :disabled="!form.video_sent" :class="fieldClass + ' mt-0' + (!form.video_sent ? ' opacity-40 cursor-not-allowed' : '')" />
                  </div>
                </div>

                <div>
                  <label :class="labelClass">Incidente</label>
                  <input type="text" v-model="form.incident" :class="fieldClass" placeholder="Descreva o incidente, se houver" />
                </div>

                <div>
                  <label :class="labelClass">Observações</label>
                  <textarea v-model="form.observations" rows="4" :class="fieldClass + ' resize-none'" placeholder="Observações gerais..."></textarea>
                </div>
              </div>
            </div>
          </template>

        </div>

        <!-- Footer -->
        <div class="bg-white border-t border-slate-100 px-7 py-5 flex items-center justify-between gap-3 shrink-0">
          <div class="flex items-center gap-3">
            <button
              type="button"
              @click="$emit('close')"
              class="px-6 py-3 rounded-xl text-sm font-bold uppercase tracking-widest text-slate-500 hover:bg-slate-100 transition-colors"
            >
              Cancelar
            </button>
            <!-- Delete Button (only when editing) -->
            <button
              v-if="process?.id"
              type="button"
              @click="confirmingDelete = true"
              class="flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-bold uppercase tracking-widest text-rose-500 hover:bg-rose-50 hover:text-rose-700 border border-rose-200 transition-all"
            >
              <Trash2 class="w-4 h-4" />
              Excluir
            </button>
          </div>
          <button
            type="submit"
            :disabled="form.processing"
            class="flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-7 py-3 rounded-xl font-bold text-sm uppercase tracking-widest shadow-lg shadow-blue-200/60 transition-all active:scale-95 disabled:opacity-60 disabled:cursor-not-allowed"
          >
            <Loader2 v-if="form.processing" class="w-4 h-4 animate-spin" />
            {{ form.processing ? 'Salvando...' : 'Salvar Contrato' }}
          </button>
        </div>
      </form>

    </div>
  </Transition>

  <!-- Delete Confirmation Modal -->
  <Transition name="fade">
    <div v-if="confirmingDelete" class="fixed inset-0 z-[90] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="confirmingDelete = false" />
      <div class="relative bg-white rounded-3xl shadow-2xl p-8 w-full max-w-md animate-in zoom-in-95 duration-200">
        <!-- Icon -->
        <div class="w-14 h-14 rounded-2xl bg-rose-100 flex items-center justify-center mx-auto mb-5">
          <AlertTriangle class="w-7 h-7 text-rose-600" />
        </div>
        <!-- Title -->
        <h3 class="text-lg font-black text-slate-800 text-center uppercase tracking-tighter">Excluir Contrato</h3>
        <p class="text-sm font-medium text-slate-500 text-center mt-2 leading-relaxed">
          Tem certeza que deseja excluir o contrato
          <span class="font-bold text-slate-800">#{{ process?.contract_number || process?.id }}</span>?
          <br />Esta ação <span class="text-rose-600 font-bold">não pode ser desfeita.</span>
        </p>
        <!-- Actions -->
        <div class="flex gap-3 mt-7">
          <button
            type="button"
            @click="confirmingDelete = false"
            :disabled="isDeleting"
            class="flex-1 px-5 py-3 rounded-xl font-bold text-sm uppercase tracking-widest text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors disabled:opacity-50"
          >
            Cancelar
          </button>
          <button
            type="button"
            @click="deleteProcess"
            :disabled="isDeleting"
            class="flex-1 flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm uppercase tracking-widest text-white bg-rose-600 hover:bg-rose-700 shadow-lg shadow-rose-200 transition-all active:scale-95 disabled:opacity-60"
          >
            <Loader2 v-if="isDeleting" class="w-4 h-4 animate-spin" />
            <Trash2 v-else class="w-4 h-4" />
            {{ isDeleting ? 'Excluindo...' : 'Sim, excluir' }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-enter-active, .slide-leave-active { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.slide-enter-from, .slide-leave-to { transform: translateX(100%); }
</style>
