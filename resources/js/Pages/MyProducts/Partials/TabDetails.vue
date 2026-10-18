<script setup>
import { ref, computed } from 'vue';
import {
  FileText, Download, Calendar, Info,
  FileImage, FileVideo, Ship, MapPin, ArrowRight, Navigation,
  DollarSign, Tag, UserCheck, Truck, AlertCircle, Eye
} from 'lucide-vue-next';
import MediaPreviewModal from '@/Components/MediaPreviewModal.vue';
import { getShipmentProgress, getRemainingDays } from '@/Utils/contracts';

const props = defineProps({
  process: Object,
});

// Everything shown here is derived from the contract itself.
const originLabel = computed(() => props.process?.exporter?.country || '—');
const destinationLabel = computed(() => props.process?.importer?.country || '—');
const progress = computed(() => getShipmentProgress(props.process || {}));
const remainingDays = computed(() => getRemainingDays(props.process || {}));

const emit = defineEmits(['go-tracking']);

// ─── Modal Preview State ──────────────────────────────────────────────────────
const showPreviewModal = ref(false);
const previewFile = ref(null);

const openPreview = (file) => {
  previewFile.value = file;
  showPreviewModal.value = true;
};

const getFileIcon = (type) => {
  if (type === 'image') return FileImage;
  if (type === 'video') return FileVideo;
  return FileText;
};

const formatDate = (d) => {
  if (!d) return '—';
  const date = new Date(d);
  return isNaN(date.getTime()) ? d : date.toLocaleDateString('pt-BR');
};

const formatCurrency = (val) => {
  if (!val && val !== 0) return '—';
  return `$${Number(val).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
};

const getStatusConfig = (status) => {
  if (!status) return { class: 'bg-slate-100 text-slate-600 border-slate-200', dot: 'bg-slate-400' };
  const s = status.toLowerCase();
  if (s.includes('finalizado'))                 return { class: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' };
  if (s.includes('invoice'))                    return { class: 'bg-sky-50 text-sky-700 border-sky-200',             dot: 'bg-sky-500' };
  if (s.includes('atraso') || s.includes('falta')) return { class: 'bg-rose-50 text-rose-700 border-rose-200',          dot: 'bg-rose-500' };
  if (s.includes('transbordo') || s.includes('chegou')) return { class: 'bg-purple-50 text-purple-700 border-purple-200', dot: 'bg-purple-500' };
  if (s.includes('embarcar'))                   return { class: 'bg-amber-50 text-amber-700 border-amber-200',        dot: 'bg-amber-500' };
  return { class: 'bg-slate-100 text-slate-600 border-slate-200', dot: 'bg-slate-400' };
};

// Documentos reais enviados para este contrato (nenhum dado fictício).
// O portal é somente leitura: enviar e excluir é responsabilidade do admin.
const allDocuments = computed(() =>
  (props.process.documents || []).map((doc) => ({
    ...doc,
    url: route('my-products.preview-document', doc.id),
    downloadUrl: route('my-products.download-document', doc.id),
  })),
);

const formatSize = (bytes) => {
  const size = Number(bytes);
  if (!Number.isFinite(size) || size <= 0) return '—';
  const units = ['B', 'KB', 'MB', 'GB'];
  let value = size;
  let unit = 0;
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit += 1;
  }
  return `${value.toFixed(value >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
};
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-in fade-in duration-200">

    <!-- Left: Contract Info + Route Preview -->
    <div class="lg:col-span-2 space-y-6">

      <!-- Main Contract Info Card -->
      <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-6">
        
        <!-- Header with Status Badge -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
          <div>
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Informações do Contrato</h2>
            <p class="text-xs font-semibold text-slate-500 mt-0.5">
              Contrato nº <span class="font-bold text-slate-800 font-mono">{{ process.contract_number }}</span>
              <span v-if="process.register_number"> · Reg: <span class="font-mono text-slate-700">{{ process.register_number }}</span></span>
            </p>
          </div>

          <!-- Status Badge -->
          <div class="flex items-center gap-2 shrink-0">
            <span
              class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-xs font-bold shadow-sm"
              :class="getStatusConfig(process.status).class"
            >
              <span class="w-2 h-2 rounded-full animate-pulse" :class="getStatusConfig(process.status).dot"></span>
              {{ process.status || 'Status não definido' }}
            </span>
          </div>
        </div>

        <!-- 1. Produto & Valores -->
        <div class="bg-slate-50/80 rounded-2xl p-4 border border-slate-150/80 grid grid-cols-1 sm:grid-cols-4 gap-4">
          <div class="sm:col-span-2">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
              <Tag class="w-3 h-3 text-slate-400" /> Produto
            </p>
            <p class="text-base font-black text-slate-800 mt-1 leading-snug">{{ process.product?.name || '—' }}</p>
          </div>
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Quantidade</p>
            <p class="text-sm font-bold text-slate-800 mt-1">
              {{ process.quantity_tons ? Number(process.quantity_tons).toLocaleString('pt-BR') : '—' }} Tons
            </p>
          </div>
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
              <DollarSign class="w-3 h-3 text-emerald-500" /> Valor Total
            </p>
            <p class="text-sm font-black text-emerald-700 mt-1">
              {{ formatCurrency(process.sales_usd) }}
            </p>
            <p v-if="process.price_per_ton_usd" class="text-[10px] text-slate-400 mt-0.5">
              {{ formatCurrency(process.price_per_ton_usd) }} / Ton
            </p>
          </div>
        </div>

        <!-- 2. Grid de Detalhes Completo -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 pt-1">
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
              <Calendar class="w-4 h-4 text-blue-500" />
              {{ formatDate(process.etd_date) }}
            </p>
          </div>
          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Previsão de Chegada (ETA)</p>
            <p class="text-sm font-bold text-slate-700 mt-0.5 flex items-center gap-1.5">
              <Calendar class="w-4 h-4 text-emerald-500" />
              {{ formatDate(process.eta_date) }}
            </p>
          </div>

          <div>
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Data do Contrato</p>
            <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ formatDate(process.date) }}</p>
          </div>
          <div v-if="process.seller">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
              <UserCheck class="w-3 h-3 text-slate-400" /> Vendedor Responsável
            </p>
            <p class="text-sm font-semibold text-slate-700 mt-0.5">{{ process.seller.name }}</p>
          </div>
        </div>

        <!-- 3. Rastreio DHL -->
        <div v-if="process.dhl_number" class="pt-3 border-t border-slate-100">
          <div class="bg-blue-50/50 border border-blue-100 rounded-xl p-3 flex items-center gap-3 max-w-sm">
            <Truck class="w-5 h-5 text-blue-600 shrink-0" />
            <div>
              <p class="text-[9px] font-bold text-blue-500 uppercase tracking-wider">Rastreio Documentos DHL</p>
              <p class="text-xs font-bold text-blue-900 font-mono mt-0.5">{{ process.dhl_number }}</p>
              <p v-if="process.dhl_date" class="text-[9px] text-blue-400">Enviado em {{ formatDate(process.dhl_date) }}</p>
            </div>
          </div>
        </div>

        <!-- 4. Ocorrência (se houver) -->
        <div v-if="process.incident" class="bg-amber-50 border border-amber-200 rounded-2xl p-4">
          <p class="text-[10px] font-bold text-amber-700 uppercase tracking-wider flex items-center gap-1.5">
            <AlertCircle class="w-4 h-4 text-amber-600" />
            Ocorrência / Alerta de Processo
          </p>
          <p class="text-sm font-semibold text-amber-900 mt-1.5 leading-relaxed whitespace-pre-line">{{ process.incident }}</p>
        </div>

        <!-- 5. Observações do Processo -->
        <div v-if="process.observations" class="bg-slate-50 rounded-2xl p-4 border border-slate-150">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
            <Info class="w-3.5 h-3.5 text-slate-400" />
            Observações do Processo
          </p>
          <p class="text-sm font-semibold text-slate-600 mt-1.5 leading-relaxed whitespace-pre-line">{{ process.observations }}</p>
        </div>
      </div>

      <!-- Route Preview -->
      <div class="bg-slate-900 rounded-3xl p-6 text-white relative overflow-hidden shadow-lg border border-slate-800">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(30,41,59,0.5),rgba(15,23,42,0.8))] opacity-90 pointer-events-none"></div>
        <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none"></div>

        <div class="relative z-10 space-y-6">
          <div class="flex justify-between items-center pb-3 border-b border-slate-800/80">
            <div class="flex items-center gap-2">
              <Ship class="w-4 h-4 text-blue-400" />
              <span class="text-xs font-bold uppercase tracking-widest text-slate-350">Status do Rastreamento</span>
            </div>
            <button type="button" @click="$emit('go-tracking')" class="text-xs font-bold text-blue-400 hover:text-blue-300 transition-colors uppercase tracking-widest flex items-center gap-1.5">
              Ver Mais <ArrowRight class="w-3.5 h-3.5" />
            </button>
          </div>

          <div class="flex justify-between items-start gap-4">
            <div>
              <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-[8px] font-black text-blue-400 uppercase tracking-wider">Origem</span>
              <h4 class="text-xs font-bold text-slate-200 mt-1 flex items-center gap-1.5">
                <MapPin class="w-3.5 h-3.5 text-blue-400 shrink-0" />{{ originLabel }}
              </h4>
            </div>
            <div class="text-right">
              <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[8px] font-black text-emerald-400 uppercase tracking-wider">Destino</span>
              <h4 class="text-xs font-bold text-slate-200 mt-1 flex items-center gap-1.5 justify-end">
                {{ destinationLabel }}<MapPin class="w-3.5 h-3.5 text-emerald-400 shrink-0" />
              </h4>
            </div>
          </div>

          <div class="py-6 px-4 relative">
            <div class="relative h-1 bg-slate-800 rounded-full">
              <div class="absolute left-0 top-0 h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full transition-all duration-[3500ms] ease-in-out shadow-[0_0_10px_rgba(59,130,246,0.6)]" :style="{ width: progress + '%' }"></div>
              <div class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 transition-all duration-[3500ms] ease-in-out flex flex-col items-center z-20" :style="{ left: progress + '%' }">
                <div class="absolute w-6 h-6 rounded-full bg-blue-500/30 animate-ping pointer-events-none"></div>
                <div class="w-8 h-8 rounded-lg bg-blue-600 border border-blue-400 flex items-center justify-center shadow-lg hover:scale-110 transition-transform">
                  <Ship class="w-4 h-4 text-white animate-pulse" />
                </div>
              </div>
              <div class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1/2 w-3 h-3 rounded-full bg-slate-900 border border-blue-500 flex items-center justify-center"><div class="w-1 h-1 rounded-full bg-blue-500"></div></div>
              <div class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 w-3 h-3 rounded-full bg-slate-900 border border-emerald-500 flex items-center justify-center"><div class="w-1 h-1 rounded-full bg-emerald-500"></div></div>
            </div>
          </div>

          <div class="flex justify-between items-center pt-3 border-t border-slate-800/80 text-[10px] text-slate-400 uppercase font-bold tracking-wider">
            <div class="flex items-center gap-1.5">
              <Navigation class="w-3.5 h-3.5 text-blue-400" />
              Status: <span class="text-slate-200 ml-1 font-bold">{{ process.status || 'Sem status' }}</span>
            </div>
            <div class="flex items-center gap-1.5 font-mono">
              <Calendar class="w-3.5 h-3.5 text-rose-400" />
              <span>{{ remainingDays === null ? '—' : `${remainingDays} dias até a ETA` }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right: Document Center with Interactive File Viewer -->
    <div class="space-y-6">
      <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex flex-col max-h-[720px]">
        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-4 shrink-0">Central de Documentações</h2>

        <!-- File list with Visualizer Buttons -->
        <div class="flex-1 overflow-y-auto space-y-3 pr-1">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider shrink-0 mb-2">Arquivos & Mídias Anexadas</p>

          <p v-if="!allDocuments.length" class="text-xs font-medium text-slate-400 text-center py-8">
            Nenhum documento enviado até o momento.
          </p>

          <div
            v-for="doc in allDocuments"
            :key="doc.id"
            class="flex items-center justify-between p-3 bg-slate-50 hover:bg-slate-100/80 border border-slate-150 rounded-2xl transition-all group"
          >
            <div class="flex items-center gap-3 min-w-0 cursor-pointer" @click="openPreview(doc)">
              <div class="w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center shrink-0 shadow-sm">
                <component :is="getFileIcon(doc.file_type)" class="w-4 h-4" :class="doc.file_type === 'image' ? 'text-emerald-600' : doc.file_type === 'video' ? 'text-purple-600' : 'text-blue-600'" />
              </div>
              <div class="min-w-0">
                <p class="text-xs font-bold text-slate-800 group-hover:text-blue-600 transition-colors truncate" :title="doc.name">
                  {{ doc.name }}
                </p>
                <p class="text-[9px] text-slate-400 mt-0.5 flex items-center gap-1.5">
                  <span>Por: {{ doc.uploader || 'Sistema' }}</span>
                  <span v-if="doc.size_bytes">· {{ formatSize(doc.size_bytes) }}</span>
                </p>
              </div>
            </div>

            <!-- Actions: Visualizar + Download -->
            <div class="flex items-center gap-1 shrink-0 ml-2">
              <button
                type="button"
                @click="openPreview(doc)"
                class="p-1.5 rounded-lg bg-white hover:bg-blue-50 border border-slate-200 hover:border-blue-200 text-slate-600 hover:text-blue-600 transition-all shadow-sm"
                title="Visualizar PDF / Mídia"
              >
                <Eye class="w-3.5 h-3.5" />
              </button>

              <a
                :href="doc.downloadUrl"
                target="_blank"
                class="p-1.5 rounded-lg bg-white hover:bg-slate-100 border border-slate-200 text-slate-500 hover:text-blue-600 transition-all shadow-sm"
                title="Baixar"
              >
                <Download class="w-3.5 h-3.5" />
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Media Preview Modal -->
    <MediaPreviewModal
      :show="showPreviewModal"
      :file="previewFile"
      @close="showPreviewModal = false"
    />
  </div>
</template>

<style scoped>
.animate-in { animation: fadeIn 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
</style>
