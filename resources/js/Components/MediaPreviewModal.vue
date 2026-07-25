<script setup>
import { computed } from 'vue';
import { X, Download, FileText } from 'lucide-vue-next';

const props = defineProps({
  show: Boolean,
  file: Object, // { id, name, file_type, file_path, url }
});

const emit = defineEmits(['close']);

const fileType = computed(() => {
  if (!props.file) return 'document';
  if (props.file.file_type) return props.file.file_type;
  const ext = (props.file.name || '').split('.').pop().toLowerCase();
  if (['pdf'].includes(ext)) return 'pdf';
  if (['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)) return 'image';
  if (['mp4', 'mov', 'webm', 'avi', 'ogv'].includes(ext)) return 'video';
  return 'document';
});

const fileUrl = computed(() => {
  if (!props.file) return '#';
  if (props.file.url) return props.file.url;
  if (props.file.id) return route('my-products.download-document', props.file.id);
  return '#';
});

const youtubeEmbedUrl = computed(() => {
  const url = fileUrl.value || '';
  if (url.includes('youtube.com/embed/')) return url;
  const match = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/);
  if (match && match[1]) {
    return `https://www.youtube-nocookie.com/embed/${match[1]}?autoplay=1`;
  }
  // Default YouTube Cargo Ship Video provided by user
  return 'https://www.youtube-nocookie.com/embed/-mrFL0WNV9s?autoplay=1';
});

const isYoutube = computed(() => {
  if (props.file?.isDemo) return true;
  const url = fileUrl.value || '';
  return url.includes('youtube') || url.includes('youtu.be');
});

const close = () => emit('close');
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-98"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-98"
    >
      <div v-if="show && file" class="fixed inset-0 z-[100] flex items-center justify-center p-2 sm:p-4">
        <!-- Minimal Light Backdrop -->
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="close" />

        <!-- Full-filling Clean Modal Container -->
        <div class="relative w-[94vw] max-w-6xl h-[88vh] bg-white rounded-2xl shadow-2xl overflow-hidden border border-slate-200 z-10 flex flex-col">
          
          <!-- Header Bar -->
          <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
              <FileText class="w-4 h-4 text-slate-400 shrink-0" />
              <h3 class="text-xs font-bold text-slate-800 truncate" :title="file.name">{{ file.name }}</h3>
            </div>

            <!-- Actions: Baixar + Fechar -->
            <div class="flex items-center gap-2 shrink-0">
              <a
                v-if="fileUrl && fileUrl !== '#'"
                :href="fileUrl"
                target="_blank"
                download
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors"
                title="Baixar arquivo"
              >
                <Download class="w-3.5 h-3.5" />
                <span>Baixar</span>
              </a>

              <button
                type="button"
                @click="close"
                class="w-7 h-7 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center transition-colors"
                title="Fechar"
              >
                <X class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Content Area (Fills 100% of Modal) -->
          <div class="flex-1 w-full h-full overflow-hidden bg-black flex items-center justify-center p-0 relative">
            
            <!-- 📄 PDF (Fills 100%) -->
            <iframe
              v-if="fileType === 'pdf'"
              :src="fileUrl"
              class="w-full h-full border-none bg-white"
              type="application/pdf"
            />

            <!-- 🖼️ IMAGE (Fills max space) -->
            <div v-else-if="fileType === 'image'" class="w-full h-full flex items-center justify-center bg-black/90 p-2">
              <img
                :src="fileUrl"
                :alt="file.name"
                class="w-full h-full object-contain"
              />
            </div>

            <!-- 🎬 VIDEO (YouTube Embed / HTML5 Video) -->
            <div v-else-if="fileType === 'video'" class="w-full h-full flex items-center justify-center bg-black">
              <iframe
                v-if="isYoutube"
                :src="youtubeEmbedUrl"
                title="Vídeo de Operação Portuária e Embarque"
                class="w-full h-full border-none"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen
              />
              <video
                v-else
                :src="fileUrl"
                controls
                autoplay
                playsinline
                class="w-full h-full object-contain bg-black"
              >
                <source :src="fileUrl" type="video/mp4" />
                Seu navegador não suporta reprodução direta deste vídeo.
              </video>
            </div>

            <!-- 📁 GENERIC -->
            <div v-else class="flex flex-col items-center justify-center p-6 text-center gap-3 bg-white rounded-xl border border-slate-200 shadow-sm max-w-sm">
              <FileText class="w-8 h-8 text-slate-400" />
              <p class="text-xs font-semibold text-slate-600">{{ file.name }}</p>
              <a
                :href="fileUrl"
                download
                class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-800 text-white font-bold text-xs"
              >
                <Download class="w-3.5 h-3.5" />
                Baixar Arquivo
              </a>
            </div>

          </div>

        </div>
      </div>
    </Transition>
  </Teleport>
</template>
