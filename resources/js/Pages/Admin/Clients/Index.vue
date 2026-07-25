<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import ExportProcessesStats from '@/Pages/ExportProcesses/Partials/ExportProcessesStats.vue';
import ExportProcessesTable from '@/Pages/ExportProcesses/Partials/ExportProcessesTable.vue';
import ExportProcessSlideOver from '@/Pages/ExportProcesses/Partials/ExportProcessSlideOver.vue';

const props = defineProps({
  exportProcesses: Object,
  clients: Array,
  users: Array,
  usersList: Array,
  users_list: Array,
  products: Array,
  sellers: Array,
  filters: Object,
  summary: Object,
});

const isSlideOverOpen = ref(false);
const editingProcess = ref(null);

const openCreateForm = () => {
  editingProcess.value = null;
  isSlideOverOpen.value = true;
};

const openEditForm = (process) => {
  editingProcess.value = process;
  isSlideOverOpen.value = true;
};

const closeSlideOver = () => {
  isSlideOverOpen.value = false;
  setTimeout(() => { editingProcess.value = null; }, 300);
};

const exporters = computed(() => (props.clients || []).filter(c => c.type === 'exporter' || c.type === 'exportador'));
const importers = computed(() => (props.clients || []).filter(c => c.type === 'importer' || c.type === 'importador'));

const userOptions = computed(() => {
  if (props.users && props.users.length) return props.users;
  if (props.usersList && props.usersList.length) return props.usersList;
  if (props.users_list && props.users_list.length) return props.users_list;
  return [];
});
</script>

<template>
  <Head title="Gestão de Clientes" />

  <DashboardLayout>
    <div class="px-6 py-7 md:px-8 w-full max-w-none space-y-6">

      <!-- Page Title -->
      <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 pb-5 border-b border-slate-200">
        <div>
          <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tighter">Gestão de Clientes</h1>
          <p class="text-sm font-medium text-slate-500 mt-1">Contratos, embarques, comissões e acompanhamento logístico.</p>
        </div>
        <div class="text-xs font-bold text-slate-400 uppercase tracking-widest bg-white border border-slate-200 rounded-xl px-4 py-2 shadow-sm">
          {{ exportProcesses.total || 0 }} contratos registrados
        </div>
      </div>

      <!-- Stats Cards -->
      <ExportProcessesStats :summary="summary" />

      <!-- Table -->
      <ExportProcessesTable
        :exportProcesses="exportProcesses"
        :filters="filters"
        :clients="clients"
        :users="userOptions"
        :usersList="userOptions"
        @create="openCreateForm"
        @edit="openEditForm"
      />

    </div>

    <!-- Slide-over -->
    <ExportProcessSlideOver
      :is-open="isSlideOverOpen"
      :process="editingProcess"
      :exporters="exporters"
      :importers="importers"
      :products="products"
      :sellers="sellers"
      :clients="clients"
      :users="userOptions"
      :usersList="userOptions"
      @close="closeSlideOver"
    />
  </DashboardLayout>
</template>
