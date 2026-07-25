<script setup>
import {
  Ship, MapPin, Navigation, Compass, Calendar, RefreshCw, CheckCircle
} from 'lucide-vue-next';

const props = defineProps({
  process: Object,
  originPort: String,
  destinationPort: String,
  etdDate: String,
  etaDate: String,
  etdFormatted: String,
  etaFormatted: String,
  shippingCompany: String,
  containerNumber: String,
  carrierConfig: Object,
  vesselName: String,
  voyageNumber: String,
  telemetry: Object,
  animatedProgress: Number,
  trackingSteps: Array,
});

const formatDate = (d) => {
  if (!d) return '—';
  const date = new Date(d);
  return isNaN(date.getTime()) ? d : date.toLocaleDateString('pt-BR');
};
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-200 w-full max-w-none">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8">
      <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-6">Status Logístico & Rastreamento</h2>

      <div class="space-y-8">

        <!-- Carrier / Details Row -->
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
            <p class="text-sm font-bold text-slate-800 tracking-wide mt-0.5">
              {{ vesselName }} <span class="text-blue-500 font-mono text-xs ml-1">{{ voyageNumber }}</span>
            </p>
          </div>
          <div>
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">DHL Rastreio Documentos</p>
            <p class="text-sm font-bold text-blue-600 mt-0.5">{{ process.dhl_number || 'Aguardando' }}</p>
          </div>
        </div>

        <!-- Animated Route Map -->
        <div class="bg-slate-900 rounded-3xl p-6 sm:p-8 text-white relative overflow-hidden shadow-lg border border-slate-800">
          <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(30,41,59,0.5),rgba(15,23,42,0.8))] opacity-90 pointer-events-none"></div>
          <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none"></div>

          <div class="relative z-10 space-y-8">
            <!-- Ports -->
            <div class="flex justify-between items-start gap-4">
              <div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-[9px] font-black text-blue-400 uppercase tracking-wider">Porto de Partida</span>
                <h4 class="text-sm font-bold text-slate-100 mt-1.5 flex items-center gap-2">
                  <MapPin class="w-4 h-4 text-blue-400 shrink-0" />{{ originPort }}
                </h4>
                <p class="text-[10px] font-bold text-slate-500 font-mono uppercase mt-1">ETD: {{ etdFormatted }}</p>
              </div>
              <div class="text-right">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[9px] font-black text-emerald-400 uppercase tracking-wider">Porto de Chegada</span>
                <h4 class="text-sm font-bold text-slate-100 mt-1.5 flex items-center gap-2 justify-end">
                  {{ destinationPort }}<MapPin class="w-4 h-4 text-emerald-400 shrink-0" />
                </h4>
                <p class="text-[10px] font-bold text-slate-500 font-mono uppercase mt-1">ETA: {{ etaFormatted }}</p>
              </div>
            </div>

            <!-- Progress line -->
            <div class="py-8 px-6 relative">
              <div class="relative h-1 bg-slate-800 rounded-full">
                <div class="absolute left-0 top-0 h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full transition-all duration-[3500ms] ease-in-out shadow-[0_0_10px_rgba(59,130,246,0.6)]" :style="{ width: animatedProgress + '%' }"></div>
                <div class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 transition-all duration-[3500ms] ease-in-out flex flex-col items-center z-20" :style="{ left: animatedProgress + '%' }">
                  <div class="absolute w-8 h-8 rounded-full bg-blue-500/30 animate-ping pointer-events-none"></div>
                  <div class="w-10 h-10 rounded-xl bg-blue-600 border border-blue-400 flex items-center justify-center shadow-lg hover:scale-110 transition-transform">
                    <Ship class="w-5 h-5 text-white animate-pulse" />
                  </div>
                  <span class="text-[9px] font-mono font-black text-blue-400 bg-slate-900 border border-slate-800 px-1.5 py-0.5 rounded-md mt-1.5 shadow">{{ animatedProgress }}%</span>
                </div>
                <div class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1/2 w-4 h-4 rounded-full bg-slate-900 border-2 border-blue-500 flex items-center justify-center"><div class="w-1.5 h-1.5 rounded-full bg-blue-500"></div></div>
                <div class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 w-4 h-4 rounded-full bg-slate-900 border-2 border-emerald-500 flex items-center justify-center"><div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div></div>
              </div>
            </div>

            <!-- Telemetry Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-slate-800/80">
              <div class="p-3 bg-slate-800/40 rounded-xl border border-slate-800/60">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5"><Navigation class="w-3 h-3 text-blue-400" />Status da Viagem</p>
                <p class="text-xs font-bold text-slate-200 mt-1.5">{{ telemetry.status }}</p>
              </div>
              <div class="p-3 bg-slate-800/40 rounded-xl border border-slate-800/60">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5"><Compass class="w-3 h-3 text-indigo-400" />Velocidade Atual</p>
                <p class="text-xs font-mono font-bold text-slate-200 mt-1.5">{{ telemetry.speed }}</p>
              </div>
              <div class="p-3 bg-slate-800/40 rounded-xl border border-slate-800/60">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5"><RefreshCw class="w-3 h-3 text-teal-400" />Temperatura Container</p>
                <p class="text-xs font-mono font-bold text-slate-200 mt-1.5">{{ telemetry.temp }}</p>
              </div>
              <div class="p-3 bg-slate-800/40 rounded-xl border border-slate-800/60">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5"><Calendar class="w-3 h-3 text-rose-400" />Tempo Restante</p>
                <p class="text-xs font-bold text-slate-200 mt-1.5">{{ telemetry.etaDays }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Steps Timeline -->
        <div class="relative pl-10 space-y-8 before:content-[''] before:absolute before:left-3.5 before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-100 mt-10">
          <div v-for="(step, idx) in trackingSteps" :key="idx" class="relative group">
            <div
              class="absolute -left-10 top-1.5 w-8 h-8 rounded-full border-2 bg-white flex items-center justify-center transition-all z-10"
              :class="step.done ? 'border-blue-600 ring-4 ring-blue-50' : 'border-slate-300 group-hover:border-blue-400'"
            >
              <div class="w-3 h-3 rounded-full transition-all" :class="step.done ? 'bg-blue-600' : 'bg-slate-200 group-hover:bg-blue-200'" />
            </div>
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
</template>

<style scoped>
.animate-in { animation: fadeIn 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
</style>
