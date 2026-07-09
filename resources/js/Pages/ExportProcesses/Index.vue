<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import ExportProcessesStats from './Partials/ExportProcessesStats.vue';
import ExportProcessesTable from './Partials/ExportProcessesTable.vue';
import ExportProcessSlideOver from './Partials/ExportProcessSlideOver.vue';

const props = defineProps({
  exportProcesses: Object,
  clients: Array,
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
  setTimeout(() => {
    editingProcess.value = null;
  }, 300); // clear after animation
};

// Computed for exporters and importers
import { computed } from 'vue';
const exporters = computed(() => props.clients.filter(c => c.type === 'exportador'));
const importers = computed(() => props.clients.filter(c => c.type === 'importador'));
</script>

<template>
  <Head title="Gestão de Clientes (Exportação)" />

  <DashboardLayout>
    <div class="py-8">
      <div class="mx-auto max-w-screen-2xl sm:px-6 lg:px-8">
        
        <!-- Dashboard Summary Cards -->
        <ExportProcessesStats :summary="summary" />

        <!-- Data Table -->
        <ExportProcessesTable 
          :exportProcesses="exportProcesses" 
          @create="openCreateForm" 
          @edit="openEditForm" 
        />

      </div>
    </div>

    <!-- Slide-over Form -->
    <ExportProcessSlideOver 
      :is-open="isSlideOverOpen"
      :process="editingProcess"
      :exporters="exporters"
      :importers="importers"
      :products="products"
      :sellers="sellers"
      @close="closeSlideOver"
    />

  </DashboardLayout>
</template>
