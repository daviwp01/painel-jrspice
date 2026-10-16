<script setup>
import { ref, computed, watch } from 'vue';
import TabTracking from '@/Pages/MyProducts/Partials/TabTracking.vue';
import { getStatusConfig, getCarrierConfig } from '@/Utils/contracts';
import { X, Loader2 } from 'lucide-vue-next';

const props = defineProps({
  isOpen: { type: Boolean, required: true },
  contract: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const tracking = ref({ state: 'idle', data: null, message: null, refreshing: false });

const contractId = computed(() => props.contract?.contract_id ?? props.contract?.id ?? null);

const fetchTracking = async (force = false) => {
  if (!contractId.value) return;

  const current = tracking.value.state;
  if (current === 'loading' || (current === 'ok' && !force)) return;

  tracking.value = { ...tracking.value, state: 'loading', message: null, refreshing: force };

  try {
    const response = await fetch(route('contracts.tracking', contractId.value), {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      cache: 'no-store',
    });
    const payload = await response.json().catch(() => null);

    tracking.value = {
      state: payload?.state || 'error',
      data: payload,
      message: payload?.message || null,
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

watch(() => props.isOpen, (open) => {
  if (open) {
    tracking.value = { state: 'idle', data: null, message: null, refreshing: false };
    fetchTracking();
  }
});

const carrier = computed(() =>
  getCarrierConfig(tracking.value.data?.carrier_code || props.contract?.shipping_company),
);

const statusConfig = computed(() => getStatusConfig(props.contract?.status));
</script>

<template>
  <Transition name="fade">
    <div v-if="isOpen" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[70]" @click="$emit('close')" />
  </Transition>

  <Transition name="slide">
    <div
      v-if="isOpen"
      class="fixed inset-y-0 right-0 z-[80] w-full max-w-3xl flex flex-col bg-[#f8fafc] shadow-2xl"
      role="dialog"
      aria-label="Rastreamento do contrato"
    >
      <div class="bg-white border-b border-slate-100 px-6 py-4 flex items-center justify-between gap-4 shrink-0">
        <div class="min-w-0">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Rastreamento</p>
          <h2 class="text-lg font-black text-slate-800 uppercase tracking-tighter truncate">
            Contrato {{ contract?.contract_number || '—' }}
          </h2>
          <p class="text-xs font-semibold text-slate-400 mt-0.5 truncate">
            <span class="font-mono">{{ contract?.container_number || 'sem container' }}</span>
            <template v-if="contract?.dhl_number">
              <span class="text-slate-300 mx-1.5">·</span>
              DHL {{ contract.dhl_number }}
            </template>
          </p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            @click="fetchTracking(true)"
            :disabled="tracking.state === 'loading'"
            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 bg-white text-[11px] font-bold uppercase tracking-wider text-slate-500 hover:text-blue-600 hover:border-blue-200 transition-all disabled:opacity-50"
          >
            <Loader2 class="w-3.5 h-3.5" :class="tracking.state === 'loading' ? 'animate-spin' : ''" />
            Atualizar
          </button>

          <button
            type="button"
            @click="$emit('close')"
            class="p-2.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-all"
            title="Fechar"
          >
            <X class="w-5 h-5" />
          </button>
        </div>
      </div>

      <div class="flex-1 overflow-y-auto px-6 py-6">
        <TabTracking
          :process="contract"
          :tracking="tracking"
          :carrier="carrier"
          :status-config="statusConfig"
          :show-refresh="false"
          @refresh="fetchTracking(true)"
        />
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
