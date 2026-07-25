<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
  FileText, Upload, Download, Trash2, Loader2, Calendar, Info,
  FileImage, FileVideo, Ship, MapPin, ArrowRight, Navigation
} from 'lucide-vue-next';

const props = defineProps({
  process: Object,
  originPort: String,
  destinationPort: String,
  etdDate: String,
  etaDate: String,
  telemetry: Object,
  animatedProgress: Number,
});

const emit = defineEmits(['go-tracking']);

const page = usePage();
const user = computed(() => page.props.auth.user);

const fileForm = useForm({ file: null });
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
    onError: () => { isUploading.value = false; }
  });
};

const deleteDocument = (docId) => {
  if (confirm('Tem certeza que deseja excluir este documento?')) {
    useForm({}).delete(route('my-products.delete-document', docId), { preserveScroll: true });
  }
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
</script>

<template>
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-in fade-in duration-200">

    <!-- Left: Contract Info + Route Preview -->
    <div class="lg:col-span-2 space-y-6">

      <!-- Contract Info Card -->
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

        <div v-if="process.observations" class="bg-slate-50 rounded-2xl p-4 border border-slate-100 mt-4">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
            <Info class="w-3.5 h-3.5 text-slate-400" />
            Observações do Processo
          </p>
          <p class="text-sm font-semibold text-slate-600 mt-2 leading-relaxed whitespace-pre-line">{{ process.observations }}</p>
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
                <MapPin class="w-3.5 h-3.5 text-blue-400 shrink-0" />{{ originPort }}
              </h4>
            </div>
            <div class="text-right">
              <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[8px] font-black text-emerald-400 uppercase tracking-wider">Destino</span>
              <h4 class="text-xs font-bold text-slate-200 mt-1 flex items-center gap-1.5 justify-end">
                {{ destinationPort }}<MapPin class="w-3.5 h-3.5 text-emerald-400 shrink-0" />
              </h4>
            </div>
          </div>

          <div class="py-6 px-4 relative">
            <div class="relative h-1 bg-slate-800 rounded-full">
              <div class="absolute left-0 top-0 h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full transition-all duration-[3500ms] ease-in-out shadow-[0_0_10px_rgba(59,130,246,0.6)]" :style="{ width: animatedProgress + '%' }"></div>
              <div class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 transition-all duration-[3500ms] ease-in-out flex flex-col items-center z-20" :style="{ left: animatedProgress + '%' }">
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
            <input type="file" ref="fileInput" @change="handleFileUpload" class="hidden" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.mp4,.mov,.avi" />
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

        <!-- File list -->
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
                <p class="text-[9px] text-slate-400 mt-1">Por: {{ doc.uploader?.name || 'Sistema' }} • {{ formatDate(doc.created_at) }}</p>
              </div>
            </div>
            <div class="flex items-center gap-1 shrink-0 ml-2">
              <a :href="route('my-products.download-document', doc.id)" class="p-1.5 rounded-lg hover:bg-white border border-transparent hover:border-slate-200 text-slate-500 hover:text-blue-600 transition-all" title="Baixar">
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

          <div v-if="!process.documents?.length" class="py-8 text-center bg-slate-50 rounded-2xl border border-slate-100 flex flex-col items-center gap-1">
            <FileText class="w-8 h-8 text-slate-300" />
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Nenhum arquivo anexado</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-in { animation: fadeIn 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
</style>
