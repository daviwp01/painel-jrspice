<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
  isOpen: {
    type: Boolean,
    required: true
  },
  process: {
    type: Object,
    default: () => null
  },
  exporters: Array,
  importers: Array,
  products: Array,
  sellers: Array
});

const emit = defineEmits(['close']);

const currentTab = ref('general');

const form = useForm({
  date: '',
  contract_number: '',
  register_number: '',
  exporter_id: '',
  importer_id: '',
  product_id: '',
  quantity_tons: '',
  price_per_ton_usd: '',
  sales_usd: '',
  annual_sales_usd: '',
  commission_usd: '',
  total_commission_usd: '',
  exchange_rate: '',
  estimated_euro: '',
  estimated_receipt_date: '',
  seller_id: '',
  to_pay_usd: '',
  receipt_date: '',
  paid_in_date: '',
  paid_in_brl: '',
  incident: '',
  video_sent: false,
  video_date: '',
  status: '',
  status_date: '',
  dhl_date: '',
  dhl_number: '',
  etd_date: '',
  eta_date: '',
  observations: '',
});

watch(() => props.process, (newProcess) => {
  if (newProcess) {
    Object.keys(form.data()).forEach(key => {
      form[key] = newProcess[key] !== null && newProcess[key] !== undefined ? newProcess[key] : (typeof form[key] === 'boolean' ? false : '');
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

const submit = () => {
  if (props.process && props.process.id) {
    form.put(route('admin.export-processes.update', props.process.id), {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        emit('close');
      },
    });
  } else {
    form.post(route('admin.export-processes.store'), {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        emit('close');
      },
    });
  }
};
</script>

<template>
  <div v-if="isOpen" class="relative z-50" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="$emit('close')"></div>

    <div class="fixed inset-0 overflow-hidden">
      <div class="absolute inset-0 overflow-hidden">
        <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10 sm:pl-16">
          <div class="pointer-events-auto w-screen max-w-3xl transform transition ease-in-out duration-500 sm:duration-700">
            <form @submit.prevent="submit" class="flex h-full flex-col bg-white shadow-xl">
              
              <!-- Simple Header -->
              <div class="px-6 py-5 border-b border-gray-200 bg-white">
                <div class="flex items-center justify-between">
                  <h2 class="text-lg font-medium text-gray-900" id="slide-over-title">
                    {{ process ? 'Editar Contrato' : 'Novo Contrato' }}
                  </h2>
                  <div class="ml-3 flex h-7 items-center">
                    <button type="button" class="rounded-md text-gray-400 hover:text-gray-500 focus:outline-none" @click="$emit('close')">
                      <span class="sr-only">Fechar painel</span>
                      <X class="h-6 w-6" aria-hidden="true" />
                    </button>
                  </div>
                </div>
              </div>
              
              <!-- Standard Underline Tabs -->
              <div class="border-b border-gray-200 bg-white px-6">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                  <button type="button" @click="currentTab = 'general'" :class="[currentTab === 'general' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700', 'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium']">
                    Geral & Produto
                  </button>
                  <button type="button" @click="currentTab = 'financial'" :class="[currentTab === 'financial' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700', 'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium']">
                    Financeiro
                  </button>
                  <button type="button" @click="currentTab = 'logistics'" :class="[currentTab === 'logistics' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700', 'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium']">
                    Logística & Status
                  </button>
                </nav>
              </div>

              <!-- Scrollable Content -->
              <div class="flex-1 overflow-y-auto px-6 py-6 bg-white">
                
                <!-- Tab: General -->
                <div v-show="currentTab === 'general'" class="space-y-8">
                  
                  <section>
                    <h3 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">Identificação</h3>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-4">
                      <div>
                        <InputLabel for="date" value="Data" />
                        <TextInput id="date" type="date" class="mt-1 block w-full text-sm" v-model="form.date" />
                      </div>
                      <div>
                        <InputLabel for="contract_number" value="Nº Contrato" />
                        <TextInput id="contract_number" type="text" class="mt-1 block w-full text-sm" v-model="form.contract_number" />
                      </div>
                    </div>
                  </section>

                  <section>
                    <h3 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">Empresas</h3>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-4">
                      <div>
                        <InputLabel for="exporter_id" value="Exportador" />
                        <select id="exporter_id" v-model="form.exporter_id" class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm">
                          <option value="">Selecione...</option>
                          <option v-for="exp in exporters" :key="exp.id" :value="exp.id">{{ exp.name }}</option>
                        </select>
                      </div>
                      <div>
                        <InputLabel for="importer_id" value="Importador" />
                        <select id="importer_id" v-model="form.importer_id" class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm">
                          <option value="">Selecione...</option>
                          <option v-for="imp in importers" :key="imp.id" :value="imp.id">{{ imp.name }}</option>
                        </select>
                      </div>
                    </div>
                  </section>

                  <section>
                    <h3 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">Mercadoria</h3>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-4">
                      <div>
                        <InputLabel for="product_id" value="Produto" />
                        <select id="product_id" v-model="form.product_id" class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm">
                          <option value="">Selecione...</option>
                          <option v-for="prod in products" :key="prod.id" :value="prod.id">{{ prod.name }}</option>
                        </select>
                      </div>
                      <div>
                        <InputLabel for="register_number" value="Registro" />
                        <TextInput id="register_number" type="text" class="mt-1 block w-full text-sm" v-model="form.register_number" />
                      </div>
                    </div>
                  </section>
                </div>

                <!-- Tab: Financial -->
                <div v-show="currentTab === 'financial'" class="space-y-8">
                  
                  <section>
                    <h3 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">Valores da Venda</h3>
                    <div class="grid grid-cols-3 gap-x-4 gap-y-4">
                      <div>
                        <InputLabel for="quantity_tons" value="Qtd (Ton)" />
                        <TextInput id="quantity_tons" type="number" step="0.01" class="mt-1 block w-full text-sm" v-model="form.quantity_tons" @input="calculateSales" />
                      </div>
                      <div>
                        <InputLabel for="price_per_ton_usd" value="Preço Ton (USD)" />
                        <TextInput id="price_per_ton_usd" type="number" step="0.01" class="mt-1 block w-full text-sm" v-model="form.price_per_ton_usd" @input="calculateSales" />
                      </div>
                      <div>
                        <InputLabel for="sales_usd" value="Venda (USD)" />
                        <TextInput id="sales_usd" type="number" step="0.01" class="mt-1 block w-full bg-gray-100 text-sm" v-model="form.sales_usd" readonly />
                      </div>
                    </div>
                    <div class="grid grid-cols-3 gap-x-4 gap-y-4 mt-4">
                      <div>
                        <InputLabel for="annual_sales_usd" value="Venda Anual (USD)" />
                        <TextInput id="annual_sales_usd" type="number" step="0.01" class="mt-1 block w-full text-sm" v-model="form.annual_sales_usd" />
                      </div>
                      <div>
                        <InputLabel for="exchange_rate" value="Taxa Câmbio" />
                        <TextInput id="exchange_rate" type="number" step="0.0001" class="mt-1 block w-full text-sm" v-model="form.exchange_rate" />
                      </div>
                      <div>
                        <InputLabel for="estimated_euro" value="Estimado Euro" />
                        <TextInput id="estimated_euro" type="number" step="0.01" class="mt-1 block w-full text-sm" v-model="form.estimated_euro" />
                      </div>
                    </div>
                  </section>

                  <section>
                    <h3 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">Comissões</h3>
                    <div class="grid grid-cols-3 gap-x-4 gap-y-4">
                      <div>
                        <InputLabel for="seller_id" value="Vendedor" />
                        <select id="seller_id" v-model="form.seller_id" class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm">
                          <option value="">Selecione...</option>
                          <option v-for="seller in sellers" :key="seller.id" :value="seller.id">{{ seller.name }}</option>
                        </select>
                      </div>
                      <div>
                        <InputLabel for="commission_usd" value="Comissão ($)" />
                        <TextInput id="commission_usd" type="number" step="0.01" class="mt-1 block w-full text-sm" v-model="form.commission_usd" />
                      </div>
                      <div>
                        <InputLabel for="total_commission_usd" value="TT Comissão ($)" />
                        <TextInput id="total_commission_usd" type="number" step="0.01" class="mt-1 block w-full text-sm" v-model="form.total_commission_usd" />
                      </div>
                    </div>
                  </section>

                  <section>
                    <h3 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">Pagamentos</h3>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-4 mb-4">
                      <div>
                        <InputLabel for="estimated_receipt_date" value="Data Est. Recebimento" />
                        <TextInput id="estimated_receipt_date" type="date" class="mt-1 block w-full text-sm" v-model="form.estimated_receipt_date" />
                      </div>
                      <div>
                        <InputLabel for="receipt_date" value="Recebimento" />
                        <TextInput id="receipt_date" type="date" class="mt-1 block w-full text-sm" v-model="form.receipt_date" />
                      </div>
                    </div>
                    <div class="grid grid-cols-3 gap-x-4 gap-y-4">
                      <div>
                        <InputLabel for="to_pay_usd" value="A Pagar (USD)" />
                        <TextInput id="to_pay_usd" type="number" step="0.01" class="mt-1 block w-full text-sm" v-model="form.to_pay_usd" />
                      </div>
                      <div>
                        <InputLabel for="paid_in_date" value="Pago em" />
                        <TextInput id="paid_in_date" type="date" class="mt-1 block w-full text-sm" v-model="form.paid_in_date" />
                      </div>
                      <div>
                        <InputLabel for="paid_in_brl" value="Pago em (BRL)" />
                        <TextInput id="paid_in_brl" type="number" step="0.01" class="mt-1 block w-full text-sm" v-model="form.paid_in_brl" />
                      </div>
                    </div>
                  </section>
                </div>

                <!-- Tab: Logistics -->
                <div v-show="currentTab === 'logistics'" class="space-y-8">
                  
                  <section>
                    <h3 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">Incidentes e Vídeos</h3>
                    <div class="grid grid-cols-1 gap-x-4 gap-y-4 mb-4">
                      <div>
                        <InputLabel for="incident" value="Incidente" />
                        <TextInput id="incident" type="text" class="mt-1 block w-full text-sm" v-model="form.incident" />
                      </div>
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-4 items-center">
                      <div class="flex items-center mt-6">
                        <input id="video_sent" type="checkbox" v-model="form.video_sent" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-600">
                        <label for="video_sent" class="ml-2 block text-sm text-gray-900">Vídeo Enviado?</label>
                      </div>
                      <div>
                        <InputLabel for="video_date" value="Data do Vídeo" />
                        <TextInput id="video_date" type="date" class="mt-1 block w-full text-sm" v-model="form.video_date" :disabled="!form.video_sent" />
                      </div>
                    </div>
                  </section>

                  <section>
                    <h3 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">Status Atual</h3>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-4">
                      <div>
                        <InputLabel for="status" value="Status" />
                        <select id="status" v-model="form.status" class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm">
                          <option value="">Selecione...</option>
                          <option value="A embarcar dia">A embarcar dia</option>
                          <option value="Transbordo até">Transbordo até</option>
                          <option value="Chegou porto dia">Chegou porto dia</option>
                          <option value="Só faltando comissão">Só faltando comissão</option>
                          <option value="Invoice ENVIADA">Invoice ENVIADA</option>
                          <option value="Processo FINALIZADO">Processo FINALIZADO</option>
                        </select>
                      </div>
                      <div>
                        <InputLabel for="status_date" value="Data Status" />
                        <TextInput id="status_date" type="date" class="mt-1 block w-full text-sm" v-model="form.status_date" />
                      </div>
                    </div>
                  </section>

                  <section>
                    <h3 class="text-sm font-medium text-gray-900 border-b border-gray-200 pb-2 mb-4">Acompanhamento DHL e Embarque</h3>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-4 mb-4">
                      <div>
                        <InputLabel for="etd_date" value="ETD (Data Embarque)" />
                        <TextInput id="etd_date" type="date" class="mt-1 block w-full text-sm" v-model="form.etd_date" />
                      </div>
                      <div>
                        <InputLabel for="eta_date" value="ETA (Data Chegada)" />
                        <TextInput id="eta_date" type="date" class="mt-1 block w-full text-sm" v-model="form.eta_date" />
                      </div>
                    </div>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-4">
                      <div>
                        <InputLabel for="dhl_number" value="DHL Nº" />
                        <TextInput id="dhl_number" type="text" class="mt-1 block w-full text-sm" v-model="form.dhl_number" />
                      </div>
                      <div>
                        <InputLabel for="dhl_date" value="Data DHL" />
                        <TextInput id="dhl_date" type="date" class="mt-1 block w-full text-sm" v-model="form.dhl_date" />
                      </div>
                    </div>
                  </section>

                  <section>
                    <InputLabel for="observations" value="Observação Mazinho" />
                    <textarea id="observations" v-model="form.observations" rows="3" class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm text-sm"></textarea>
                  </section>
                </div>

              </div>
              
              <!-- Footer Actions -->
              <div class="flex flex-shrink-0 justify-end px-6 py-4 border-t border-gray-200 bg-gray-50">
                <SecondaryButton type="button" @click="$emit('close')" class="mr-3">
                  Cancelar
                </SecondaryButton>
                <PrimaryButton type="submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                  Salvar
                </PrimaryButton>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
