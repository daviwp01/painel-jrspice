<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { ArrowLeft, ClipboardList, Ship, MessageSquare, Star } from 'lucide-vue-next';
import { getStatusConfig, getCarrierConfig } from '@/Utils/contracts';

import TabDetails from './Partials/TabDetails.vue';
import TabTracking from './Partials/TabTracking.vue';
import TabObservations from './Partials/TabObservations.vue';
import TabReview from './Partials/TabReview.vue';

const props = defineProps({
  process: Object,
  userReview: { type: Object, default: null },
});

const activeTab = ref('details');

// ─── Finalized flag (TabTracking computes the progress itself) ────────────────
const isFinalized = computed(() => (props.process?.status || '').toLowerCase().includes('finalizado'));

// ─── Track & Trace (fetched on demand, only when the tab opens) ──────────────
const tracking = ref({ state: 'idle', data: null, message: null, refreshing: false });

const fetchTracking = async (force = false) => {
  const current = tracking.value.state;
  if (current === 'loading' || (current === 'ok' && !force)) return;

  tracking.value = { ...tracking.value, state: 'loading', message: null, refreshing: force };

  try {
    const response = await fetch(route('my-products.tracking', props.process.id), {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      cache: 'no-store',
    });

    const payload = await response.json().catch(() => null);

    tracking.value = {
      state: (payload && payload.state) || 'error',
      data: payload,
      message: (payload && payload.message) || null,
      refreshing: false,
    };
  } catch {
    tracking.value = {
      state: 'error',
      data: null,
      message: 'Não foi possível falar com a transportadora agora. Tente novamente em instantes.',
      refreshing: false,
    };
  }
};

const refreshTracking = () => fetchTracking(true);

watch(activeTab, (tab) => {
  if (tab === 'tracking') fetchTracking();
});

const carrier = computed(() =>
  getCarrierConfig(tracking.value.data?.carrier_code || props.process.shipping_company),
);

const tabs = [
  { key: 'details', label: 'Detalhes & Documentação', icon: ClipboardList, activeClass: 'border-blue-600 text-blue-600' },
  { key: 'tracking', label: 'Rastreamento de Container', icon: Ship, activeClass: 'border-blue-600 text-blue-600' },
  { key: 'observations', label: 'Observações', icon: MessageSquare, activeClass: 'border-blue-600 text-blue-600' },
  { key: 'review', label: 'Avaliação', icon: Star, activeClass: 'border-amber-500 text-amber-600' },
];
</script>

<template>
  <Head :title="`Contrato ${process.contract_number}`" />

  <DashboardLayout>
    <div class="px-6 py-7 md:px-8 w-full max-w-none space-y-6">

      <div class="mb-2">
        <Link
          :href="route('my-products.index')"
          class="inline-flex items-center text-xs font-bold text-slate-400 hover:text-blue-600 transition-colors group uppercase tracking-widest"
        >
          <ArrowLeft class="w-3.5 h-3.5 mr-1.5 transform group-hover:-translate-x-1 transition-transform" />
          Voltar para meus contratos
        </Link>
      </div>

      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-1">
        <div>
          <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tighter">Contrato {{ process.contract_number }}</h1>
          <p class="text-sm font-medium text-slate-500 mt-1">
            Acesse a documentação do contrato, acompanhe o rastreamento do container e avalie o serviço.
          </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <span class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-white border border-slate-200 rounded-xl px-4 py-2 shadow-sm">
            Registro: {{ process.register_number || 'Sem registro' }}
          </span>
        </div>
      </div>

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

      <div class="mt-6">
        <TabDetails
          v-if="activeTab === 'details'"
          :process="process"
          @go-tracking="activeTab = 'tracking'"
        />

        <TabTracking
          v-else-if="activeTab === 'tracking'"
          :process="process"
          :tracking="tracking"
          :carrier="carrier"
          :status-config="getStatusConfig(process.status)"
          @refresh="refreshTracking"
        />

        <TabObservations
          v-else-if="activeTab === 'observations'"
          :process="process"
        />

        <TabReview
          v-else
          :process="process"
          :is-finalized="isFinalized"
          :user-review="userReview"
        />
      </div>
    </div>
  </DashboardLayout>
</template>
