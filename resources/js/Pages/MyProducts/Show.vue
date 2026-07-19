<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { 
  ArrowLeft, ArrowRight, Package, FileText, Upload, Download, Trash2, 
  Calendar, Loader2, Anchor, Ship, MapPin, CheckCircle, Info, FileImage, FileVideo,
  ClipboardList, Navigation, RefreshCw, Compass
} from 'lucide-vue-next';

const props = defineProps({
  process: Object,
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const activeTab = ref('details'); // 'details' or 'tracking'

const animatedProgress = ref(0);

onMounted(() => {
  animatedProgress.value = 0;
  setTimeout(() => {
    animatedProgress.value = shipmentProgress.value;
  }, 400);
});

watch(activeTab, (tab) => {
  if (tab === 'tracking') {
    animatedProgress.value = 0;
    setTimeout(() => {
      animatedProgress.value = shipmentProgress.value;
    }, 200);
  }
});

const fileForm = useForm({
  file: null,
});

const isUploading = ref(false);
const fileInput = ref(null);

const handleFileUpload = (e) => {
  const file = e.target.files[0];
  if (!file) return;

  fileForm.file = file;
  isUploading.value = true;

  fileForm.post(route('my-products.upload-document', props.process.id), {
    preserveScroll: true,
    onSuccess: () => {
      fileForm.reset();
      isUploading.value = false;
      if (fileInput.value) fileInput.value.value = '';
    },
    onError: () => {
      isUploading.value = false;
    }
  });
};

const deleteDocument = (docId) => {
  if (confirm('Tem certeza que deseja excluir este documento?')) {
    useForm({}).delete(route('my-products.delete-document', docId), {
      preserveScroll: true,
    });
  }
};

const getFileIcon = (type) => {
  if (type === 'image') return FileImage;
  if (type === 'video') return FileVideo;
  return FileText;
};
// ─── Container Tracking Simulation (With Fallbacks for Demo) ────────────────
const carriers = {
  'HMM': { color: 'border-red-500 text-red-600 bg-red-50', logo: 'HMM' },
  'Evergreen': { color: 'border-emerald-600 text-emerald-700 bg-emerald-50', logo: 'EVERGREEN' },
  'Maersk': { color: 'border-sky-500 text-sky-600 bg-sky-50', logo: 'MAERSK' },
  'CMA CGM': { color: 'border-blue-700 text-blue-800 bg-blue-50', logo: 'CMA CGM' },
  'Hapag lloyd': { color: 'border-orange-500 text-orange-600 bg-orange-50', logo: 'HAPAG-LLOYD' },
  'MSC': { color: 'border-yellow-600 text-yellow-700 bg-yellow-50', logo: 'MSC' },
  'OOCL': { color: 'border-rose-600 text-rose-700 bg-rose-50', logo: 'OOCL' },
  'Cosco': { color: 'border-teal-700 text-teal-800 bg-teal-50', logo: 'COSCO' },
  'Zim': { color: 'border-slate-800 text-slate-900 bg-slate-100', logo: 'ZIM' }
};

const shippingCompany = computed(() => {
  return props.process.shipping_company || 'Maersk';
});

const containerNumber = computed(() => {
  return props.process.container_number || 'MSMU9201948';
});

const carrierConfig = computed(() => {
  return carriers[shippingCompany.value] || { color: 'border-sky-500 text-sky-600 bg-sky-50', logo: 'MAERSK' };
});

const adjustDaysRaw = (dateStr, days) => {
  const baseDate = dateStr ? new Date(dateStr) : new Date();
  baseDate.setDate(baseDate.getDate() + days);
  return baseDate.toISOString().split('T')[0];
};

const etdDate = computed(() => {
  return props.process.etd_date || adjustDaysRaw(props.process.date, 5);
});

const etaDate = computed(() => {
  return props.process.eta_date || adjustDaysRaw(props.process.date, 25);
});

// Dynamic route calculation based on exporter and importer
const originPort = computed(() => {
  const country = props.process.exporter?.country || 'Brasil';
  if (country.toLowerCase().includes('brasil')) return 'Porto de Santos, BR (SNDZ)';
  return `Porto de Origem (${country})`;
});

const destinationPort = computed(() => {
  const country = props.process.importer?.country || 'Alemanha';
  if (country.toLowerCase().includes('alemanha')) return 'Porto de Hamburgo, DE (HAM)';
  if (country.toLowerCase().includes('estados')) return 'Porto de Nova York, US (NYC)';
  if (country.toLowerCase().includes('japão')) return 'Porto de Tóquio, JP (TYO)';
  return `Porto de Destino (${country})`;
});

// Dynamic Vessel and Voyage generator based on container number
const vesselName = computed(() => {
  const num = containerNumber.value;
  const vessels = ['MSC INGRID VII', 'EVER ALIVE', 'MAERSK MC-KINNEY MOLLER', 'CMA CGM ALEXANDER', 'ZIM NEW YORK'];
  const charSum = num.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
  return vessels[charSum % vessels.length];
});

const voyageNumber = computed(() => {
  const num = containerNumber.value;
  const charSum = num.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
  return `VY-${(charSum % 900) + 100}E`;
});

// Real-time progress bar calculation
const shipmentProgress = computed(() => {
  const etd = new Date(etdDate.value);
  const eta = new Date(etaDate.value);
  const today = new Date();

  if (today < etd) return 8; // min 8% for visual positioning
  if (today > eta || props.process.status === 'Processo FINALIZADO') return 100;

  const total = eta.getTime() - etd.getTime();
  const elapsed = today.getTime() - etd.getTime();
  
  return Math.max(8, Math.min(Math.round((elapsed / total) * 100), 98));
});

// Telemetry information
const telemetry = computed(() => {
  const prog = shipmentProgress.value;
  if (prog === 100) {
    return { status: 'Carga Entregue', speed: '0.0 kn', temp: '19°C', etaDays: 'Finalizado' };
  }
  
  // In transit simulation
  const num = containerNumber.value;
  const charSum = num.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
  const speed = ((charSum % 5) + 18.2).toFixed(1) + ' kn';
  const temp = ((charSum % 6) + 16) + '°C';
  
  const eta = new Date(etaDate.value);
  const today = new Date();
  const diffTime = Math.abs(eta - today);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  
  return {
    status: prog > 85 ? 'Próximo ao porto de destino' : 'Navegando em mar aberto',
    speed,
    temp,
    etaDays: `${diffDays} dias restantes`
  };
});

const trackingSteps = computed(() => {
  const steps = [
    { title: 'Booking Confirmado', desc: 'Reserva de container confirmada e espaço alocado no navio.', date: props.process.date, done: true },
    { title: 'Estufagem & Entrega no Porto', desc: 'Mercadoria acondicionada no container e entregue para alfândega no porto de origem.', date: adjustDaysRaw(etdDate.value, -3), done: true },
    { title: 'Embarcado', desc: 'Container carregado com sucesso a bordo do navio e viagem iniciada.', date: etdDate.value, done: true },
    { title: 'Descarregado / Chegou ao Porto', desc: 'Navio atracado no porto de destino final, aguardando liberação e nacionalização.', date: etaDate.value, done: isPast(etaDate.value) },
    { title: 'Entregue ao Cliente', desc: 'Processo alfandegário concluído e container entregue no endereço do importador.', date: props.process.status === 'Processo FINALIZADO' ? props.process.status_date : null, done: props.process.status === 'Processo FINALIZADO' }
  ];
  return steps;
});

const adjustDate = (dateStr, days) => {
  const d = new Date(dateStr);
  d.setDate(d.getDate() + days);
  return d.toLocaleDateString('pt-BR');
};

const isPast = (dateStr) => {
  if (!dateStr) return false;
  return new Date(dateStr) < new Date();
};

const formatDate = (d) => {
  if (!d) return '—';
  const date = new Date(d);
  return isNaN(date.getTime()) ? d : date.toLocaleDateString('pt-BR');
};
</script>

<template>
  <Head :title="`Contrato ${process.contract_number}`" />

  <DashboardLayout>
    <div class="px-6 py-7 md:px-8 w-full max-w-none space-y-6">

      <!-- Breadcrumbs/Back -->
      <div class="mb-2">
        <Link
          :href="route('my-products.index')"
          class="inline-flex items-center text-xs font-bold text-slate-400 hover:text-blue-600 transition-colors group uppercase tracking-widest"
        >
          <ArrowLeft class="w-3.5 h-3.5 mr-1.5 transform group-hover:-translate-x-1 transition-transform" />
          Voltar para meus contratos
        </Link>
      </div>

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1">
        <div>
          <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tighter flex items-center gap-3">
            Contrato {{ process.contract_number }}
          </h1>
          <p class="text-sm font-medium text-slate-500 mt-1">Gerencie a documentação do contrato e visualize o rastreamento em tempo real do container.</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-white border border-slate-200 rounded-xl px-4 py-2 shadow-sm">
            Registro: {{ process.register_number || 'Sem registro' }}
          </span>
        </div>
      </div>

      <!-- Tabs Navigation -->
      <div class="border-b border-slate-200 flex gap-2 shrink-0">
        <button
          @click="activeTab = 'details'"
          type="button"
          class="flex items-center gap-2 px-5 py-4 text-xs font-bold uppercase tracking-widest border-b-2 transition-all -mb-px"
          :class="activeTab === 'details'
            ? 'border-blue-600 text-blue-600'
            : 'border-transparent text-slate-400 hover:text-slate-700'"
        >
          <ClipboardList class="w-4 h-4" />
          Detalhes & Documentação
        </button>
        <button
          @click="activeTab = 'tracking'"
          type="button"
          class="flex items-center gap-2 px-5 py-4 text-xs font-bold uppercase tracking-widest border-b-2 transition-all -mb-px"
          :class="activeTab === 'tracking'
            ? 'border-blue-600 text-blue-600'
            : 'border-transparent text-slate-400 hover:text-slate-700'"
        >
          <Ship class="w-4 h-4" />
          Rastreamento de Container
        </button>
      </div>

      <!-- Tab Contents (Reactive & Performant Ajax/Vue level) -->
      <div class="mt-6">
        
        <!-- ── TAB DETALHES & DOCUMENTAÇÃO ── -->
        <div v-if="activeTab === 'details'" class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-in fade-in duration-200">
          
          <!-- Left: Contract Details -->
          <div class="lg:col-span-2 space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-6">
              <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-4">Informações do Contrato</h2>
              
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                  <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Produto</p>
                  <p class="text-base font-bold text-slate-800 mt-0.5">{{ process.product?.name || '—' }}</p>
                </div>
                <div>
                  <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Quantidade Contratada</p>
                  <p class="text-base font-bold text-slate-800 mt-0.5">{{ process.quantity_tons ? Number(process.quantity_tons).toLocaleString('pt-BR') : '—' }} Toneladas</p>
                </div>
                <div>
                  <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Exportador</p>
                  <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ process.exporter?.name || '—' }}</p>
                </div>
                <div>
                  <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Importador</p>
                  <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ process.importer?.name || '—' }}</p>
                </div>
                <div>
                  <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Previsão de Embarque (ETD)</p>
                  <p class="text-sm font-bold text-slate-700 mt-0.5 flex items-center gap-1.5">
                    <Calendar class="w-4 h-4 text-slate-400" />
                    {{ formatDate(process.etd_date) }}
                  </p>
                </div>
                <div>
                  <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Previsão de Chegada (ETA)</p>
                  <p class="text-sm font-bold text-slate-700 mt-0.5 flex items-center gap-1.5">
                    <Calendar class="w-4 h-4 text-slate-400" />
                    {{ formatDate(process.eta_date) }}
                  </p>
                </div>
              </div>

              <!-- Observations -->
              <div v-if="process.observations" class="bg-slate-50 rounded-2xl p-4 border border-slate-100 mt-4">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                  <Info class="w-3.5 h-3.5 text-slate-400" />
                  Observações do Processo
                </p>
                <p class="text-sm font-semibold text-slate-600 mt-2 leading-relaxed whitespace-pre-line">{{ process.observations }}</p>
              </div>
            </div>

            <!-- Premium Animated Cargo Route Map / Shipping Line (Preview) -->
            <div class="bg-slate-900 rounded-3xl p-6 text-white relative overflow-hidden shadow-lg border border-slate-800">
              <!-- Marine radar pattern or grid line -->
              <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(30,41,59,0.5),rgba(15,23,42,0.8))] opacity-90 pointer-events-none"></div>
              <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none"></div>
              
              <div class="relative z-10 space-y-6">
                <!-- Preview Header -->
                <div class="flex justify-between items-center pb-3 border-b border-slate-800/80">
                  <div class="flex items-center gap-2">
                    <Ship class="w-4 h-4 text-blue-400" />
                    <span class="text-xs font-bold uppercase tracking-widest text-slate-350">Status do Rastreamento</span>
                  </div>
                  <button 
                    @click="activeTab = 'tracking'"
                    type="button"
                    class="text-xs font-bold text-blue-400 hover:text-blue-350 transition-colors uppercase tracking-widest flex items-center gap-1.5"
                  >
                    Ver Mais
                    <ArrowRight class="w-3.5 h-3.5" />
                  </button>
                </div>

                <!-- Ports Header -->
                <div class="flex justify-between items-start gap-4">
                  <div>
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-[8px] font-black text-blue-400 uppercase tracking-wider">Origem</span>
                    <h4 class="text-xs font-bold text-slate-200 mt-1 flex items-center gap-1.5">
                      <MapPin class="w-3.5 h-3.5 text-blue-400 shrink-0" />
                      {{ originPort }}
                    </h4>
                  </div>
                  
                  <div class="text-right">
                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[8px] font-black text-emerald-400 uppercase tracking-wider">Destino</span>
                    <h4 class="text-xs font-bold text-slate-200 mt-1 flex items-center gap-1.5 justify-end">
                      {{ destinationPort }}
                      <MapPin class="w-3.5 h-3.5 text-emerald-400 shrink-0" />
                    </h4>
                  </div>
                </div>

                <!-- Shipping line progress -->
                <div class="py-6 px-4 relative">
                  <!-- Progress Line Container -->
                  <div class="relative h-1 bg-slate-800 rounded-full">
                    <!-- Progress line -->
                    <div 
                      class="absolute left-0 top-0 h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full transition-all duration-[3500ms] ease-in-out shadow-[0_0_10px_rgba(59,130,246,0.6)]"
                      :style="{ width: animatedProgress + '%' }"
                    ></div>

                    <!-- Sailing Boat Icon (Centered relative to the line) -->
                    <div 
                      class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 transition-all duration-[3500ms] ease-in-out flex flex-col items-center z-20"
                      :style="{ left: animatedProgress + '%' }"
                    >
                      <div class="absolute w-6 h-6 rounded-full bg-blue-500/30 animate-ping pointer-events-none"></div>
                      <div class="w-8 h-8 rounded-lg bg-blue-600 border border-blue-400 flex items-center justify-center shadow-lg hover:scale-110 transition-transform">
                        <Ship class="w-4 h-4 text-white animate-pulse" />
                      </div>
                    </div>

                    <!-- Anchor points at ends -->
                    <div class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1/2 w-3 h-3 rounded-full bg-slate-900 border border-blue-500 flex items-center justify-center">
                      <div class="w-1 h-1 rounded-full bg-blue-500"></div>
                    </div>
                    <div class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 w-3 h-3 rounded-full bg-slate-900 border border-emerald-500 flex items-center justify-center">
                      <div class="w-1 h-1 rounded-full bg-emerald-500"></div>
                    </div>
                  </div>
                </div>

                <!-- Telemetry Row (Compact) -->
                <div class="flex justify-between items-center pt-3 border-t border-slate-800/80 text-[10px] text-slate-400 uppercase font-bold tracking-wider">
                  <div class="flex items-center gap-1.5">
                    <Navigation class="w-3.5 h-3.5 text-blue-400" />
                    Status: <span class="text-slate-200 ml-1 font-bold">{{ telemetry.status }}</span>
                  </div>
                  <div class="flex items-center gap-1.5 font-mono">
                    <Calendar class="w-3.5 h-3.5 text-rose-400" />
                    <span>{{ telemetry.etaDays }}</span>
                  </div>
                </div>

              </div>
            </div>
          </div>

          <!-- Right: Document Center -->
          <div class="space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col max-h-[700px]">
              <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-4 shrink-0">Central de Documentações</h2>
              
              <!-- Upload area -->
              <div class="mb-6 shrink-0">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Anexar Documento</label>
                <div 
                  @click="fileInput.click()"
                  class="border-2 border-dashed border-slate-200 hover:border-blue-400 hover:bg-blue-50/10 rounded-2xl p-5 text-center cursor-pointer transition-all flex flex-col items-center gap-2 group relative"
                  :class="{ 'opacity-60 cursor-wait pointer-events-none': isUploading }"
                >
                  <input 
                    type="file" 
                    ref="fileInput" 
                    @change="handleFileUpload" 
                    class="hidden" 
                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.mp4,.mov,.avi"
                  />
                  
                  <div class="w-10 h-10 rounded-xl bg-slate-50 group-hover:bg-blue-50 flex items-center justify-center transition-colors">
                    <Loader2 v-if="isUploading" class="w-5 h-5 text-blue-600 animate-spin" />
                    <Upload v-else class="w-5 h-5 text-slate-400 group-hover:text-blue-500" />
                  </div>
                  
                  <div>
                    <p class="text-xs font-bold text-slate-600 group-hover:text-blue-600 transition-colors">Escolher arquivo para upload</p>
                    <p class="text-[10px] text-slate-400 mt-1">PDF, Word, Imagens e Vídeos até 20MB</p>
                  </div>
                </div>
              </div>

              <!-- List -->
              <div class="flex-1 overflow-y-auto space-y-3 pr-1">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider shrink-0 mb-2">Arquivos vinculados</p>

                <div 
                  v-for="doc in process.documents" 
                  :key="doc.id"
                  class="flex items-center justify-between p-3.5 bg-slate-50 hover:bg-slate-100/70 border border-slate-100 rounded-2xl transition-all group"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-white border border-slate-150 flex items-center justify-center shrink-0">
                      <component :is="getFileIcon(doc.file_type)" class="w-4 h-4 text-slate-500" />
                    </div>
                    <div class="min-w-0">
                      <p class="text-xs font-bold text-slate-700 truncate" :title="doc.name">{{ doc.name }}</p>
                      <p class="text-[9px] text-slate-400 mt-1">
                        Por: {{ doc.uploader?.name || 'Sistema' }} • {{ formatDate(doc.created_at) }}
                      </p>
                    </div>
                  </div>

                  <!-- Actions -->
                  <div class="flex items-center gap-1 shrink-0 ml-2">
                    <a 
                      :href="route('my-products.download-document', doc.id)"
                      class="p-1.5 rounded-lg hover:bg-white border border-transparent hover:border-slate-200 text-slate-500 hover:text-blue-600 transition-all"
                      title="Baixar"
                    >
                      <Download class="w-3.5 h-3.5" />
                    </a>
                    <button 
                      v-if="doc.uploaded_by === user.id || user.is_master"
                      @click="deleteDocument(doc.id)"
                      class="p-1.5 rounded-lg hover:bg-white border border-transparent hover:border-rose-100 text-slate-400 hover:text-rose-600 transition-all"
                      title="Excluir"
                    >
                      <Trash2 class="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

                <!-- Empty State -->
                <div v-if="!process.documents?.length" class="py-8 text-center bg-slate-50 rounded-2xl border border-slate-100 flex flex-col items-center gap-1">
                  <FileText class="w-8 h-8 text-slate-300" />
                  <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nenhum arquivo anexado</p>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- ── TAB RASTREAMENTO DO CONTAINER (Full Width / Premium & Animated Demo) ── -->
        <div v-if="activeTab === 'tracking'" class="space-y-6 animate-in fade-in duration-200 w-full max-w-none">
          <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8">
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-6">Status Logístico & Rastreamento</h2>

            <!-- Full-width Tracking simulation timeline -->
            <div class="space-y-8">
              
              <!-- Top Carrier Badge & Details Card -->
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 p-6 rounded-2xl border border-slate-100 bg-slate-50/50">
                <div class="flex items-center gap-4">
                  <div class="w-16 h-16 rounded-2xl flex items-center justify-center font-black text-[10px] border-2 bg-white shrink-0 shadow-sm px-2 text-center leading-none" :class="carrierConfig.color">
                    {{ carrierConfig.logo }}
                  </div>
                  <div>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Companhia Marítima</p>
                    <p class="text-sm font-black text-slate-800 mt-0.5">{{ shippingCompany }}</p>
                  </div>
                </div>
                <div>
                  <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Número do Container</p>
                  <p class="text-sm font-mono font-bold text-slate-800 tracking-wider mt-0.5">{{ containerNumber }}</p>
                </div>
                <div>
                  <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Navio / Viagem</p>
                  <p class="text-sm font-bold text-slate-800 tracking-wide mt-0.5">{{ vesselName }} <span class="text-blue-500 font-mono text-xs ml-1">{{ voyageNumber }}</span></p>
                </div>
                <div>
                  <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">DHL Rastreio Documentos</p>
                  <p class="text-sm font-bold text-blue-600 mt-0.5">{{ process.dhl_number || 'Aguardando' }}</p>
                </div>
              </div>

              <!-- Premium Animated Cargo Route Map / Shipping Line -->
              <div class="bg-slate-900 rounded-3xl p-6 sm:p-8 text-white relative overflow-hidden shadow-lg border border-slate-800">
                <!-- Marine radar pattern or grid line -->
                <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(30,41,59,0.5),rgba(15,23,42,0.8))] opacity-90 pointer-events-none"></div>
                <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none"></div>
                
                <div class="relative z-10 space-y-8">
                  <!-- Ports Header -->
                  <div class="flex justify-between items-start gap-4">
                    <div>
                      <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-[9px] font-black text-blue-400 uppercase tracking-wider">Porto de Partida</span>
                      <h4 class="text-sm font-bold text-slate-100 mt-1.5 flex items-center gap-2">
                        <MapPin class="w-4 h-4 text-blue-400 shrink-0" />
                        {{ originPort }}
                      </h4>
                      <p class="text-[10px] font-bold text-slate-500 font-mono uppercase mt-1">ETD: {{ formatDate(etdDate) }}</p>
                    </div>
                    
                    <div class="text-right">
                      <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[9px] font-black text-emerald-400 uppercase tracking-wider">Porto de Chegada</span>
                      <h4 class="text-sm font-bold text-slate-100 mt-1.5 flex items-center gap-2 justify-end">
                        {{ destinationPort }}
                        <MapPin class="w-4 h-4 text-emerald-400 shrink-0" />
                      </h4>
                      <p class="text-[10px] font-bold text-slate-500 font-mono uppercase mt-1">ETA: {{ formatDate(etaDate) }}</p>
                    </div>
                  </div>

                  <!-- The Animated Shipping Line -->
                  <div class="py-8 px-6 relative">
                    <!-- Progress Line Container -->
                    <div class="relative h-1 bg-slate-800 rounded-full">
                      <!-- Progress line -->
                      <div 
                        class="absolute left-0 top-0 h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full transition-all duration-[3500ms] ease-in-out shadow-[0_0_10px_rgba(59,130,246,0.6)]"
                        :style="{ width: animatedProgress + '%' }"
                      ></div>

                      <!-- Sailing Boat Icon (Centered relative to the line) -->
                      <div 
                        class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 transition-all duration-[3500ms] ease-in-out flex flex-col items-center z-20"
                        :style="{ left: animatedProgress + '%' }"
                      >
                        <!-- Pulse effect -->
                        <div class="absolute w-8 h-8 rounded-full bg-blue-500/30 animate-ping pointer-events-none"></div>
                        
                        <!-- Ship Card/Icon -->
                        <div class="w-10 h-10 rounded-xl bg-blue-600 border border-blue-400 flex items-center justify-center shadow-lg hover:scale-110 transition-transform">
                          <Ship class="w-5 h-5 text-white animate-pulse" />
                        </div>
                        
                        <!-- Floating Percentage -->
                        <span class="text-[9px] font-mono font-black text-blue-400 bg-slate-900 border border-slate-800 px-1.5 py-0.5 rounded-md mt-1.5 shadow">
                          {{ animatedProgress }}%
                        </span>
                      </div>

                      <!-- Anchor points at ends -->
                      <div class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1/2 w-4 h-4 rounded-full bg-slate-900 border-2 border-blue-500 flex items-center justify-center">
                        <div class="w-1.5 h-1.5 rounded-full bg-blue-500"></div>
                      </div>
                      <div class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 w-4 h-4 rounded-full bg-slate-900 border-2 border-emerald-500 flex items-center justify-center">
                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div>
                      </div>
                    </div>
                  </div>

                  <!-- Telemetry Grid -->
                  <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-slate-800/80">
                    <div class="p-3 bg-slate-800/40 rounded-xl border border-slate-800/60">
                      <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                        <Navigation class="w-3 h-3 text-blue-400" />
                        Status da Viagem
                      </p>
                      <p class="text-xs font-bold text-slate-200 mt-1.5">{{ telemetry.status }}</p>
                    </div>
                    <div class="p-3 bg-slate-800/40 rounded-xl border border-slate-800/60">
                      <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                        <Compass class="w-3 h-3 text-indigo-400" />
                        Velocidade Atual
                      </p>
                      <p class="text-xs font-mono font-bold text-slate-200 mt-1.5">{{ telemetry.speed }}</p>
                    </div>
                    <div class="p-3 bg-slate-800/40 rounded-xl border border-slate-800/60">
                      <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                        <RefreshCw class="w-3 h-3 text-teal-400" />
                        Temperatura Container
                      </p>
                      <p class="text-xs font-mono font-bold text-slate-200 mt-1.5">{{ telemetry.temp }}</p>
                    </div>
                    <div class="p-3 bg-slate-800/40 rounded-xl border border-slate-800/60">
                      <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                        <Calendar class="w-3 h-3 text-rose-400" />
                        Tempo Restante
                      </p>
                      <p class="text-xs font-bold text-slate-200 mt-1.5">{{ telemetry.etaDays }}</p>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Steps Timeline (Clear & Detailed) -->
              <div class="relative pl-10 space-y-8 before:content-[''] before:absolute before:left-3.5 before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-100 mt-10">
                <div 
                  v-for="(step, idx) in trackingSteps" 
                  :key="idx"
                  class="relative group"
                >
                  <!-- Timeline Bullet -->
                  <div 
                    class="absolute -left-10 top-1.5 w-8 h-8 rounded-full border-2 bg-white flex items-center justify-center transition-all z-10"
                    :class="step.done 
                      ? 'border-blue-600 ring-4 ring-blue-50' 
                      : 'border-slate-300 group-hover:border-blue-400'"
                  >
                    <div 
                      class="w-3 h-3 rounded-full transition-all"
                      :class="step.done ? 'bg-blue-600' : 'bg-slate-200 group-hover:bg-blue-200'"
                    />
                  </div>

                  <!-- Step Content -->
                  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pl-3 bg-white hover:bg-slate-50/50 p-4 rounded-2xl border border-transparent hover:border-slate-100 transition-all">
                    <div>
                      <h4 class="text-sm font-black flex items-center gap-2" :class="step.done ? 'text-slate-800' : 'text-slate-400'">
                        {{ step.title }}
                        <CheckCircle v-if="step.done" class="w-4 h-4 text-emerald-500 shrink-0" />
                      </h4>
                      <p class="text-xs font-semibold text-slate-400 mt-1 max-w-xl leading-relaxed">{{ step.desc }}</p>
                    </div>
                    <span 
                      v-if="step.date" 
                      class="text-xs font-bold font-mono tracking-wide px-3.5 py-1.5 rounded-xl border shrink-0 max-w-fit text-center"
                      :class="step.done ? 'bg-blue-50 text-blue-600 border-blue-100 shadow-sm shadow-blue-50/40' : 'bg-slate-50 text-slate-400 border-slate-100'"
                    >
                      {{ formatDate(step.date) }}
                    </span>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

      </div>

    </div>
  </DashboardLayout>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(4px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
