<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import {
  Ship, MapPin, Navigation, Calendar, RefreshCw, CheckCircle,
  AlertTriangle, Loader2, SearchX, Inbox, ExternalLink,
} from 'lucide-vue-next';
import { formatDate, getShipmentProgress, getRemainingDays, getTrackingStatusLabel } from '@/Utils/contracts';

const props = defineProps({
  process: { type: Object, required: true },
  tracking: { type: Object, required: true },
  carrier: { type: Object, required: true },
  statusConfig: { type: Object, required: true },
  /** The surrounding shell may own the refresh action (e.g. the admin slide-over). */
  showRefresh: { type: Boolean, default: true },
});

const emit = defineEmits(['refresh']);

const data = computed(() => props.tracking?.data || null);
const state = computed(() => props.tracking?.state || 'idle');

const isDate = (value) => Boolean(value) && !Number.isNaN(new Date(`${String(value).slice(0, 10)}T00:00:00`).getTime());

// Real port when the carrier provides one, otherwise the country it reported,
// otherwise the contract's own parties — never an invented port name.
const originPort = computed(() =>
  data.value?.origin?.port || data.value?.origin?.country || props.process.exporter?.country || '—');

/** Events in chronological order — the carrier history drives position and ETA. */
const sortedEvents = computed(() => {
  const list = (data.value?.events || []).filter((event) => event.date);
  return [...list].sort((a, b) => new Date(a.date).getTime() - new Date(b.date).getTime());
});

/** Where the container is right now: the latest event that already happened. */
const currentEvent = computed(() => {
  const now = Date.now();
  const past = sortedEvents.value.filter((event) => new Date(event.date).getTime() <= now);
  return past.length ? past[past.length - 1] : null;
});

const currentLocation = computed(() => currentEvent.value?.location || null);

/** Final destination: what the carrier declared, else the last port of the history. */
const finalDestination = computed(() =>
  data.value?.destination?.port
  || sortedEvents.value[sortedEvents.value.length - 1]?.location
  || props.process.importer?.country
  || '—');

/** When it arrives: declared ETA, else the last upcoming event, else the contract ETA. */
const arrivalDate = computed(() => {
  if (data.value?.destination?.eta) return data.value.destination.eta;

  const now = Date.now();
  const upcoming = sortedEvents.value.filter((event) => new Date(event.date).getTime() > now);

  return upcoming.length ? upcoming[upcoming.length - 1].date : props.process.eta_date || null;
});

const vesselName = computed(() => data.value?.vessel?.name || null);
const voyageNumber = computed(() => data.value?.vessel?.voyage || null);

const isFinalized = computed(() => (props.process.status || '').toLowerCase().includes('finalizado'));

/**
 * Dates come from the carrier when it reports them, otherwise from the
 * contract. Using only the contract made the bar sit at 0% even with a live
 * ETA on the screen.
 */
const effectiveEtd = computed(() => props.process.etd_date || data.value?.origin?.departure || null);
const effectiveEta = computed(() => arrivalDate.value);

const shipmentProgress = computed(() =>
  getShipmentProgress({
    etd_date: effectiveEtd.value,
    eta_date: effectiveEta.value,
    status: props.process.status,
  }));

const remainingDays = computed(() =>
  getRemainingDays({ eta_date: effectiveEta.value, status: props.process.status }));

// The bar animates from zero on first paint.
const displayProgress = ref(0);

onMounted(() => {
  requestAnimationFrame(() => { displayProgress.value = shipmentProgress.value; });
});

watch(shipmentProgress, (value) => { displayProgress.value = value; });

const lastUpdated = computed(() => data.value?.last_updated || null);



const events = computed(() => {
  const list = data.value?.events;
  return Array.isArray(list) && list.length ? list : [];
});

/**
 * Real milestones only: every date shown here comes from the contract itself.
 * Nothing is estimated — unknown dates render as "aguardando".
 */
const milestones = computed(() => [
  {
    title: 'Contrato emitido',
    description: 'Contrato registrado e aprovado na plataforma.',
    date: props.process.date,
    done: true,
  },
  {
    title: 'Embarque previsto (ETD)',
    description: 'Data estimada de partida da carga no porto de origem.',
    date: props.process.etd_date,
    done: Boolean(props.process.etd_date) && new Date(`${props.process.etd_date}T00:00:00`) < new Date(),
  },
  {
    title: 'Chegada prevista (ETA)',
    description: 'Data estimada de chegada ao porto de destino.',
    date: props.process.eta_date,
    done: Boolean(props.process.eta_date) && new Date(`${props.process.eta_date}T00:00:00`) < new Date(),
  },
  {
    title: 'Processo finalizado',
    description: 'Entrega concluída e processo encerrado.',
    date: isFinalized.value ? props.process.status_date : null,
    done: isFinalized.value,
  },
]);

const stateCopy = computed(() => ({
  loading: { icon: Loader2, title: 'Consultando a transportadora', text: 'Buscando o último posicionamento do container…', spin: true },
  unavailable: { icon: Inbox, title: 'Container não informado', text: props.tracking.message || 'Este contrato ainda não possui número de container cadastrado.', spin: false },
  unsupported: { icon: SearchX, title: 'Armadora não suportada', text: props.tracking.message || 'Não conseguimos identificar a transportadora deste container.', spin: false },
  not_configured: { icon: AlertTriangle, title: 'Rastreamento indisponível', text: props.tracking.message || 'O canal de rastreamento desta transportadora ainda não foi configurado.', spin: false },
  not_found: { icon: SearchX, title: 'Registro não encontrado', text: props.tracking.message || 'A transportadora não possui registro para este container.', spin: false },
  error: { icon: AlertTriangle, title: 'Falha na consulta', text: props.tracking.message || 'Não foi possível consultar a transportadora agora.', spin: false },
})[state.value] || null);

const carrierName = computed(() => data.value?.carrier_name || props.process.shipping_company || '—');
</script>

<template>
  <div class="space-y-6 animate-in fade-in duration-200 w-full max-w-none">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3 mb-6">
        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest">Status Logístico &amp; Rastreamento</h2>
        <button
          v-if="showRefresh"
          type="button"
          @click="emit('refresh')"
          :disabled="state === 'loading'"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-[11px] font-bold uppercase tracking-wider text-slate-500 hover:text-blue-600 hover:border-blue-200 transition-all disabled:opacity-50"
        >
          <RefreshCw class="w-3.5 h-3.5" :class="state === 'loading' ? 'animate-spin' : ''" />
          Atualizar
        </button>
      </div>

      <div class="space-y-8">
        <!-- Carrier / container facts -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 p-6 rounded-2xl border border-slate-100 bg-slate-50/50">
          <div class="flex items-center gap-4">
            <div
              class="w-16 h-16 rounded-2xl flex items-center justify-center font-black text-[10px] border-2 shrink-0 shadow-sm px-2 text-center leading-none"
              :class="carrier.color"
            >{{ carrier.logo }}</div>
            <div class="min-w-0">
              <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Companhia Marítima</p>
              <p class="text-sm font-black text-slate-800 mt-0.5 truncate">{{ carrierName }}</p>
            </div>
          </div>
          <div class="min-w-0">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Número do Container</p>
            <p class="text-sm font-mono font-bold text-slate-800 tracking-wider mt-0.5 break-all">
              {{ process.container_number || 'Não informado' }}
            </p>
          </div>
          <div class="min-w-0">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Navio / Viagem</p>
            <p class="text-sm font-bold text-slate-800 tracking-wide mt-0.5">
              <template v-if="vesselName">
                {{ vesselName }}
                <span v-if="voyageNumber" class="text-blue-500 font-mono text-xs ml-1">{{ voyageNumber }}</span>
              </template>
              <span v-else class="text-slate-400 font-normal">Aguardando dados da transportadora</span>
            </p>
          </div>
          <div class="min-w-0">
            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">DHL Rastreio Documentos</p>
            <p class="text-sm font-bold text-blue-600 mt-0.5 break-all">{{ process.dhl_number || 'Aguardando' }}</p>
          </div>
        </div>

        <!-- Contract status -->
        <div class="flex flex-wrap items-center gap-3">
          <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold border" :class="statusConfig.class">
            <span class="w-2 h-2 rounded-full" :class="statusConfig.dot"></span>
            {{ process.status || 'Sem status' }}
          </span>

        </div>

        <!-- Failure / empty states -->
        <div
          v-if="state !== 'ok' && state !== 'loading'"
          class="rounded-2xl border p-5 flex items-start gap-4"
          :class="state === 'unavailable' ? 'border-slate-200 bg-slate-50' : 'border-amber-200 bg-amber-50'"
        >
          <component
            :is="stateCopy.icon"
            class="w-5 h-5 shrink-0 mt-0.5"
            :class="state === 'unavailable' ? 'text-slate-400' : 'text-amber-600'"
          />
          <div>
            <p class="text-sm font-black uppercase tracking-wider" :class="state === 'unavailable' ? 'text-slate-600' : 'text-amber-800'">
              {{ stateCopy.title }}
            </p>
            <p class="text-sm font-medium mt-1 leading-relaxed" :class="state === 'unavailable' ? 'text-slate-500' : 'text-amber-700'">
              {{ stateCopy.text }}
            </p>
          </div>
        </div>

        <div v-if="state === 'loading'" class="rounded-2xl border border-slate-200 bg-slate-50 p-5 flex items-center gap-3 text-slate-500">
          <Loader2 class="w-5 h-5 animate-spin text-blue-500" />
          <span class="text-sm font-bold uppercase tracking-wider">{{ stateCopy?.title || 'Consultando…' }}</span>
        </div>

        <!-- Route -->
        <div class="bg-slate-900 rounded-3xl p-6 sm:p-8 text-white relative overflow-hidden shadow-lg border border-slate-800">
          <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,rgba(30,41,59,0.5),rgba(15,23,42,0.8))] opacity-90 pointer-events-none"></div>
          <div class="absolute inset-0 bg-[linear-gradient(to_right,rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(to_bottom,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:24px_24px] pointer-events-none"></div>

          <div class="relative z-10 space-y-8">
            <div class="flex justify-between items-start gap-4">
              <div class="min-w-0">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-[9px] font-black text-blue-400 uppercase tracking-wider">Onde está agora</span>
                <h4 class="text-sm font-bold text-slate-100 mt-1.5 flex items-center gap-2">
                  <MapPin class="w-4 h-4 text-blue-400 shrink-0" />
                  <span class="truncate">{{ currentLocation || 'Aguardando dados da transportadora' }}</span>
                </h4>
                <p class="text-[10px] font-bold text-slate-500 font-mono uppercase mt-1">
                  <template v-if="currentEvent">
                    {{ currentEvent.description }} · {{ formatDate(currentEvent.date) }}
                  </template>
                  <template v-else>—</template>
                </p>
              </div>

              <div class="text-right min-w-0">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[9px] font-black text-emerald-400 uppercase tracking-wider">Destino final</span>
                <h4 class="text-sm font-bold text-slate-100 mt-1.5 flex items-center gap-2 justify-end">
                  <span class="truncate">{{ finalDestination }}</span>
                  <MapPin class="w-4 h-4 text-emerald-400 shrink-0" />
                </h4>
                <p class="text-[10px] font-bold text-slate-500 font-mono uppercase mt-1">
                  Chegada: {{ isDate(arrivalDate) ? formatDate(arrivalDate) : '—' }}
                </p>
              </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 -mt-4 pt-3 border-t border-slate-800/60 text-[10px] font-bold uppercase tracking-wider text-slate-500">
              <span>Origem: {{ originPort }}</span>
              <span>ETD: {{ isDate(effectiveEtd) ? formatDate(effectiveEtd) : '—' }}</span>
            </div>

            <div class="py-8 px-6 relative">
              <div class="relative h-1 bg-slate-800 rounded-full">
                <div
                  class="absolute left-0 top-0 h-full bg-gradient-to-r from-blue-500 to-indigo-500 rounded-full transition-all duration-[3500ms] ease-in-out shadow-[0_0_10px_rgba(59,130,246,0.6)]"
                  :style="{ width: displayProgress + '%' }"
                ></div>
                <div
                  class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 transition-all duration-[3500ms] ease-in-out flex flex-col items-center z-20"
                  :style="{ left: displayProgress + '%' }"
                >
                  <div class="w-10 h-10 rounded-xl bg-blue-600 border border-blue-400 flex items-center justify-center shadow-lg">
                    <Ship class="w-5 h-5 text-white" />
                  </div>
                  <span class="text-[9px] font-mono font-black text-blue-400 bg-slate-900 border border-slate-800 px-1.5 py-0.5 rounded-md mt-1.5 shadow">{{ displayProgress }}%</span>
                </div>
                <div class="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-1/2 w-4 h-4 rounded-full bg-slate-900 border-2 border-blue-500 flex items-center justify-center"><div class="w-1.5 h-1.5 rounded-full bg-blue-500"></div></div>
                <div class="absolute right-0 top-1/2 -translate-y-1/2 translate-x-1/2 w-4 h-4 rounded-full bg-slate-900 border-2 border-emerald-500 flex items-center justify-center"><div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div></div>
              </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-4 border-t border-slate-800/80">
              <div class="p-3 bg-slate-800/40 rounded-xl border border-slate-800/60">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                  <Navigation class="w-3 h-3 text-blue-400" />Situação
                </p>
                <p class="text-xs font-bold text-slate-200 mt-1.5">
                  {{ state === 'ok' ? getTrackingStatusLabel(data?.status) : 'Sem dados da transportadora' }}
                </p>
              </div>
              <div class="p-3 bg-slate-800/40 rounded-xl border border-slate-800/60">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                  <RefreshCw class="w-3 h-3 text-teal-400" />Eventos registrados
                </p>
                <p class="text-xs font-mono font-bold text-slate-200 mt-1.5">{{ events.length }}</p>
              </div>
              <div class="p-3 bg-slate-800/40 rounded-xl border border-slate-800/60">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                  <Calendar class="w-3 h-3 text-rose-400" />Tempo restante
                </p>
                <p class="text-xs font-bold text-slate-200 mt-1.5">
                  {{ remainingDays === null ? '—' : `${remainingDays} dias` }}
                </p>
              </div>
              <div class="p-3 bg-slate-800/40 rounded-xl border border-slate-800/60">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-wider flex items-center gap-1.5">
                  <ExternalLink class="w-3 h-3 text-indigo-400" />Fonte
                </p>
                <p class="text-xs font-bold text-slate-200 mt-1.5 uppercase">
                  {{ state === 'ok' ? (data?.provider || 'transportadora') : '—' }}
                </p>
              </div>
            </div>
          </div>
        </div>

        <!-- Timeline: real carrier events when available, contract milestones otherwise -->
        <div>
          <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-5">
            {{ events.length ? 'Eventos da Transportadora' : 'Marcos do Contrato' }}
          </h3>

          <div class="relative pl-10 space-y-8 before:content-[''] before:absolute before:left-3.5 before:top-2 before:bottom-2 before:w-[2px] before:bg-slate-100">
            <div
              v-for="(step, idx) in (events.length ? events : milestones)"
              :key="idx"
              class="relative group"
            >
              <div
                class="absolute -left-10 top-1.5 w-8 h-8 rounded-full border-2 bg-white flex items-center justify-center transition-all z-10"
                :class="step.done === false ? 'border-slate-300 group-hover:border-blue-400' : 'border-blue-600 ring-4 ring-blue-50'"
              >
                <div
                  class="w-3 h-3 rounded-full transition-all"
                  :class="step.done === false ? 'bg-slate-200 group-hover:bg-blue-200' : 'bg-blue-600'"
                ></div>
              </div>

              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pl-3 bg-white hover:bg-slate-50/50 p-4 rounded-2xl border border-transparent hover:border-slate-100 transition-all">
                <div class="min-w-0">
                  <h4 class="text-sm font-black flex items-center gap-2" :class="step.done === false ? 'text-slate-400' : 'text-slate-800'">
                    {{ step.title || step.description || 'Evento' }}
                    <CheckCircle v-if="step.done !== false" class="w-4 h-4 text-emerald-500 shrink-0" />
                  </h4>
                  <p v-if="step.description || step.location || step.transport" class="text-xs font-semibold text-slate-400 mt-1 max-w-xl leading-relaxed">
                    {{ [step.location, step.description, step.transport && step.voyage ? `${step.transport} · ${step.voyage}` : step.transport].filter(Boolean).join(' · ') }}
                  </p>
                </div>

                <span
                  class="text-xs font-bold font-mono tracking-wide px-3.5 py-1.5 rounded-xl border shrink-0 max-w-fit text-center"
                  :class="step.done === false ? 'bg-slate-50 text-slate-400 border-slate-100' : 'bg-blue-50 text-blue-600 border-blue-100 shadow-sm shadow-blue-50/40'"
                >
                  {{ step.date ? formatDate(step.date) : 'aguardando' }}
                </span>
              </div>
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
