<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { ArrowLeft, ClipboardList, Ship, MessageSquare, Star, AlertTriangle } from 'lucide-vue-next';

import TabDetails      from './Partials/TabDetails.vue';
import TabTracking     from './Partials/TabTracking.vue';
import TabObservations from './Partials/TabObservations.vue';
import TabReview       from './Partials/TabReview.vue';

// ─── Props ───────────────────────────────────────────────────────────────────
const props = defineProps({
  process:    Object,
  userReview: Object, // null if not yet reviewed
});

// ─── Active Tab ──────────────────────────────────────────────────────────────
const activeTab = ref('details'); // 'details' | 'tracking' | 'observations' | 'review'

// ─── Helpers ─────────────────────────────────────────────────────────────────
const formatDate = (d) => {
  if (!d) return '—';
  const date = new Date(d);
  return isNaN(date.getTime()) ? d : date.toLocaleDateString('pt-BR');
};

const adjustDaysRaw = (dateStr, days) => {
  const baseDate = dateStr ? new Date(dateStr) : new Date();
  baseDate.setDate(baseDate.getDate() + days);
  return baseDate.toISOString().split('T')[0];
};

const isPast = (dateStr) => !!dateStr && new Date(dateStr) < new Date();

// ─── Carrier / Route Data ────────────────────────────────────────────────────
const carriers = {
  'HMM':        { color: 'border-red-500 text-red-600 bg-red-50',           logo: 'HMM' },
  'Evergreen':  { color: 'border-emerald-600 text-emerald-700 bg-emerald-50', logo: 'EVERGREEN' },
  'Maersk':     { color: 'border-sky-500 text-sky-600 bg-sky-50',            logo: 'MAERSK' },
  'CMA CGM':    { color: 'border-blue-700 text-blue-800 bg-blue-50',         logo: 'CMA CGM' },
  'Hapag lloyd':{ color: 'border-orange-500 text-orange-600 bg-orange-50',   logo: 'HAPAG-LLOYD' },
  'MSC':        { color: 'border-yellow-600 text-yellow-700 bg-yellow-50',   logo: 'MSC' },
  'OOCL':       { color: 'border-rose-600 text-rose-700 bg-rose-50',         logo: 'OOCL' },
  'Cosco':      { color: 'border-teal-700 text-teal-800 bg-teal-50',         logo: 'COSCO' },
  'Zim':        { color: 'border-slate-800 text-slate-900 bg-slate-100',     logo: 'ZIM' },
};

const shippingCompany = computed(() => props.process.shipping_company || 'Maersk');
const containerNumber = computed(() => props.process.container_number || 'MSMU9201948');
const carrierConfig   = computed(() => carriers[shippingCompany.value] || { color: 'border-sky-500 text-sky-600 bg-sky-50', logo: 'MAERSK' });

const etdDate = computed(() => props.process.etd_date || adjustDaysRaw(props.process.date, 5));
const etaDate = computed(() => props.process.eta_date || adjustDaysRaw(props.process.date, 25));

const originPort = computed(() => {
  const c = props.process.exporter?.country || 'Brasil';
  return c.toLowerCase().includes('brasil') ? 'Porto de Santos, BR (SNDZ)' : `Porto de Origem (${c})`;
});

const destinationPort = computed(() => {
  const c = props.process.importer?.country || 'Alemanha';
  if (c.toLowerCase().includes('alemanha')) return 'Porto de Hamburgo, DE (HAM)';
  if (c.toLowerCase().includes('estados'))  return 'Porto de Nova York, US (NYC)';
  if (c.toLowerCase().includes('japão'))    return 'Porto de Tóquio, JP (TYO)';
  return `Porto de Destino (${c})`;
});

const vesselName = computed(() => {
  const num = containerNumber.value;
  const vessels = ['MSC INGRID VII', 'EVER ALIVE', 'MAERSK MC-KINNEY MOLLER', 'CMA CGM ALEXANDER', 'ZIM NEW YORK'];
  const s = num.split('').reduce((a, c) => a + c.charCodeAt(0), 0);
  return vessels[s % vessels.length];
});

const voyageNumber = computed(() => {
  const s = containerNumber.value.split('').reduce((a, c) => a + c.charCodeAt(0), 0);
  return `VY-${(s % 900) + 100}E`;
});

// ─── Animated Progress ───────────────────────────────────────────────────────
const shipmentProgress = computed(() => {
  const etd   = new Date(etdDate.value);
  const eta   = new Date(etaDate.value);
  const today = new Date();
  if (today < etd) return 8;
  if (today > eta || props.process.status === 'Processo FINALIZADO') return 100;
  const total   = eta.getTime() - etd.getTime();
  const elapsed = today.getTime() - etd.getTime();
  return Math.max(8, Math.min(Math.round((elapsed / total) * 100), 98));
});

const animatedProgress = ref(0);

onMounted(() => {
  setTimeout(() => { animatedProgress.value = shipmentProgress.value; }, 400);
});

watch(activeTab, (tab) => {
  if (tab === 'tracking') {
    animatedProgress.value = 0;
    setTimeout(() => { animatedProgress.value = shipmentProgress.value; }, 200);
  }
});

// ─── Telemetry ───────────────────────────────────────────────────────────────
const telemetry = computed(() => {
  const prog = shipmentProgress.value;
  if (prog === 100) return { status: 'Carga Entregue', speed: '0.0 kn', temp: '19°C', etaDays: 'Finalizado' };
  const num     = containerNumber.value;
  const s       = num.split('').reduce((a, c) => a + c.charCodeAt(0), 0);
  const speed   = ((s % 5) + 18.2).toFixed(1) + ' kn';
  const temp    = ((s % 6) + 16) + '°C';
  const diff    = Math.ceil(Math.abs(new Date(etaDate.value) - new Date()) / 86400000);
  return {
    status: prog > 85 ? 'Próximo ao porto de destino' : 'Navegando em mar aberto',
    speed,
    temp,
    etaDays: `${diff} dias restantes`,
  };
});

// ─── Tracking Steps ──────────────────────────────────────────────────────────
const trackingSteps = computed(() => [
  { title: 'Booking Confirmado',         desc: 'Reserva de container confirmada e espaço alocado no navio.',                                          date: props.process.date,                        done: true },
  { title: 'Estufagem & Entrega no Porto',desc: 'Mercadoria acondicionada no container e entregue para alfândega no porto de origem.',                date: adjustDaysRaw(etdDate.value, -3),          done: true },
  { title: 'Embarcado',                  desc: 'Container carregado com sucesso a bordo do navio e viagem iniciada.',                                  date: etdDate.value,                             done: true },
  { title: 'Descarregado / Chegou ao Porto', desc: 'Navio atracado no porto de destino final, aguardando liberação e nacionalização.',                date: etaDate.value,                             done: isPast(etaDate.value) },
  { title: 'Entregue ao Cliente',        desc: 'Processo alfandegário concluído e container entregue no endereço do importador.',                      date: props.process.status === 'Processo FINALIZADO' ? props.process.status_date : null, done: props.process.status === 'Processo FINALIZADO' },
]);

// ─── Review: is finalized? ───────────────────────────────────────────────────
const isFinalized = computed(() => {
  const s = props.process?.status?.toLowerCase() || '';
  return s.includes('finalizado');
});

// ─── Tabs config ─────────────────────────────────────────────────────────────
const tabs = [
  { key: 'details',      label: 'Detalhes & Documentação',   icon: ClipboardList, activeClass: 'border-blue-600 text-blue-600' },
  { key: 'tracking',     label: 'Rastreamento de Container',  icon: Ship,          activeClass: 'border-blue-600 text-blue-600' },
  { key: 'observations', label: 'Observações',                icon: MessageSquare, activeClass: 'border-blue-600 text-blue-600' },
  { key: 'review',       label: 'Avaliação',                  icon: Star,          activeClass: 'border-amber-500 text-amber-600' },
];
</script>

<template>
  <Head :title="`Contrato ${process.contract_number}`" />

  <DashboardLayout>
    <div class="px-6 py-7 md:px-8 w-full max-w-none space-y-6">

      <!-- Back -->
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
          <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tighter">Contrato {{ process.contract_number }}</h1>
          <p class="text-sm font-medium text-slate-500 mt-1">Gerencie a documentação do contrato e visualize o rastreamento em tempo real do container.</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-white border border-slate-200 rounded-xl px-4 py-2 shadow-sm">
            Registro: {{ process.register_number || 'Sem registro' }}
          </span>
        </div>
      </div>

      <!-- Tabs Navigation -->
      <div class="border-b border-slate-200 flex gap-2 shrink-0 overflow-x-auto">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          @click="activeTab = tab.key"
          class="flex items-center gap-2 px-5 py-4 text-xs font-bold uppercase tracking-widest border-b-2 transition-all -mb-px whitespace-nowrap"
          :class="activeTab === tab.key ? tab.activeClass : 'border-transparent text-slate-400 hover:text-slate-700'"
        >
          <component :is="tab.icon" class="w-4 h-4" />
          {{ tab.label }}
        </button>
      </div>

      <!-- Tab Content -->
      <div class="mt-6">

        <TabDetails
          v-if="activeTab === 'details'"
          :process="process"
          :origin-port="originPort"
          :destination-port="destinationPort"
          :etd-date="etdDate"
          :eta-date="etaDate"
          :telemetry="telemetry"
          :animated-progress="animatedProgress"
          @go-tracking="activeTab = 'tracking'"
        />

        <TabTracking
          v-if="activeTab === 'tracking'"
          :process="process"
          :origin-port="originPort"
          :destination-port="destinationPort"
          :etd-date="etdDate"
          :eta-date="etaDate"
          :etd-formatted="formatDate(etdDate)"
          :eta-formatted="formatDate(etaDate)"
          :shipping-company="shippingCompany"
          :container-number="containerNumber"
          :carrier-config="carrierConfig"
          :vessel-name="vesselName"
          :voyage-number="voyageNumber"
          :telemetry="telemetry"
          :animated-progress="animatedProgress"
          :tracking-steps="trackingSteps"
        />

        <TabObservations
          v-if="activeTab === 'observations'"
          :process="process"
        />

        <TabReview
          v-if="activeTab === 'review'"
          :process="process"
          :is-finalized="isFinalized"
          :user-review="userReview"
        />

      </div>
    </div>
  </DashboardLayout>
</template>
