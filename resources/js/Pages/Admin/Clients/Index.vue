<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Maximize2, Minimize2 } from 'lucide-vue-next';
import { ui, toggleFocusMode } from '@/Stores/UiStore';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import ContractStats from '@/Pages/Contracts/Partials/ContractStats.vue';
import ContractsTable from '@/Pages/Contracts/Partials/ContractsTable.vue';
import ContractSlideOver from '@/Pages/Contracts/Partials/ContractSlideOver.vue';
import ContractTrackingSlideOver from '@/Pages/Contracts/Partials/ContractTrackingSlideOver.vue';

const props = defineProps({
  rows: Object,
  clients: Array,
  sellers: Array,
  contractProducts: Array,
  countries: Array,
  users: Array,
  statusOptions: Array,
  filters: Object,
  activeFilters: Object,
  summary: Object,
});

const isSlideOverOpen = ref(false);
const editingContractId = ref(null);
const trackingContract = ref(null);
const isTrackingOpen = ref(false);

const openTracking = (row) => {
  trackingContract.value = row;
  isTrackingOpen.value = true;
};

const closeTracking = () => {
  isTrackingOpen.value = false;
  setTimeout(() => { trackingContract.value = null; }, 300);
};

const openCreateForm = () => {
  editingContractId.value = null;
  isSlideOverOpen.value = true;
};

const openEditForm = (row) => {
  editingContractId.value = row.contract_id;
  isSlideOverOpen.value = true;
};

const closeSlideOver = () => {
  isSlideOverOpen.value = false;
  setTimeout(() => { editingContractId.value = null; }, 300);
};
</script>

<template>
  <Head title="Gestão de Clientes" />

  <DashboardLayout>
    <div class="px-6 py-7 md:px-8 w-full max-w-none space-y-6">
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 pb-5 border-b border-slate-200">
        <div>
          <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tighter">Gestão de Clientes</h1>
          <p class="text-sm font-medium text-slate-500 mt-1">
            Contratos, itens, embarques, comissões e acompanhamento logístico.
          </p>
        </div>
        <div class="flex items-center gap-3 shrink-0">
          <button
            type="button"
            @click="toggleFocusMode"
            :title="ui.focusMode ? 'Sair da tela cheia' : 'Ocultar menu e usar a tela toda'"
            class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-xs font-black uppercase tracking-widest transition-all shadow-sm"
            :class="ui.focusMode
              ? 'bg-[#0f172a] text-white border-[#0f172a] hover:bg-slate-800'
              : 'bg-white text-slate-600 border-slate-200 hover:border-blue-400 hover:text-blue-600'"
          >
            <component :is="ui.focusMode ? Minimize2 : Maximize2" class="w-4 h-4" :class="ui.focusMode ? 'text-[#b2862e]' : ''" />
            {{ ui.focusMode ? 'Tela normal' : 'Tela cheia' }}
          </button>

          <div class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-white border border-slate-200 rounded-xl px-4 py-2 shadow-sm">
            {{ summary?.total_contracts || 0 }} contratos · {{ rows?.total || 0 }} itens
          </div>
        </div>
      </div>

      <ContractStats :summary="summary" :filters="filters" />

      <ContractsTable
        :rows="rows"
        :filters="filters"
        :clients="clients"
        :users="users"
        :statusOptions="statusOptions"
        :sellers="sellers"
        :contractProducts="contractProducts"
        :countries="countries"
        @create="openCreateForm"
        @edit="openEditForm"
        @tracking="openTracking"
      />
    </div>

    <ContractSlideOver
      :is-open="isSlideOverOpen"
      :contract-id="editingContractId"
      :clients="clients"
      :sellers="sellers"
      :contract-products="contractProducts"
      :users="users"
      :status-options="statusOptions"
      @close="closeSlideOver"
    />

    <ContractTrackingSlideOver
      :is-open="isTrackingOpen"
      :contract="trackingContract"
      @close="closeTracking"
    />
  </DashboardLayout>
</template>
