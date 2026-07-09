<script setup>
import { Filter, Plus } from 'lucide-vue-next';
import PrimaryButton from '@/Components/PrimaryButton.vue';

defineProps({
  exportProcesses: {
    type: Object,
    required: true
  }
});

defineEmits(['create', 'edit']);

const getStatusColor = (status) => {
  if (!status) return 'bg-gray-100 text-gray-800';
  const s = status.toLowerCase();
  if (s.includes('finalizado') || s.includes('invoice')) return 'bg-emerald-100 text-emerald-800 border-emerald-200';
  if (s.includes('atraso') || s.includes('falta')) return 'bg-rose-100 text-rose-800 border-rose-200';
  if (s.includes('transbordo')) return 'bg-blue-100 text-blue-800 border-blue-200';
  if (s.includes('embarcar')) return 'bg-amber-100 text-amber-800 border-amber-200';
  return 'bg-gray-100 text-gray-800 border-gray-200';
};
</script>

<template>
  <div class="bg-white shadow-sm sm:rounded-xl border border-gray-200 overflow-hidden flex flex-col">
    <!-- Toolbar -->
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50/50 flex items-center justify-between">
      <div class="flex items-center space-x-4">
        <div class="relative">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <Filter class="h-4 w-4 text-gray-400" />
          </div>
          <input type="text" placeholder="Buscar contrato..." class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-blue-500 focus:border-blue-500 w-64">
        </div>
      </div>
      <div class="flex items-center">
        <PrimaryButton @click="$emit('create')" class="shadow-sm text-sm">
          <Plus class="w-4 h-4 mr-1.5" />
          Novo Contrato
        </PrimaryButton>
      </div>
    </div>

    <!-- Table Wrapper (Horizontal Scroll) -->
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th scope="col" class="sticky left-0 z-10 bg-gray-50 px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Data</th>
            <th scope="col" class="sticky left-[88px] z-10 bg-gray-50 px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Contrato</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Registro</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Exportador</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Importador</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Produto</th>
            <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Qtd (Ton)</th>
            <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Preço/Ton ($)</th>
            <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Venda ($)</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Vendedor</th>
            <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Comissão ($)</th>
            <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">TT Comissão ($)</th>
            <th scope="col" class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Incidente</th>
            <th scope="col" class="px-6 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Vídeo</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">DHL Nº</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ETD</th>
            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ETA</th>
            <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Ações</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-for="process in exportProcesses.data" :key="process.id" @click="$emit('edit', process)" class="hover:bg-blue-50/50 cursor-pointer transition-colors duration-150">
            <td class="sticky left-0 z-10 bg-white px-6 py-4 whitespace-nowrap text-sm text-gray-900 group-hover:bg-blue-50/50">{{ process.date }}</td>
            <td class="sticky left-[88px] z-10 bg-white px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600 group-hover:bg-blue-50/50">{{ process.contract_number }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ process.register_number }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ process.exporter?.name || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">{{ process.importer?.name || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ process.product?.name || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ process.quantity_tons }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">{{ process.price_per_ton_usd }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right font-medium">${{ process.sales_usd }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ process.seller?.name || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">${{ process.commission_usd || '0.00' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">${{ process.total_commission_usd || '0.00' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
              <span :class="['px-2.5 py-0.5 rounded-full text-xs font-medium border', getStatusColor(process.status)]">
                {{ process.status || 'Pendente' }}
              </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium">{{ process.incident || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
              <span v-if="process.video_sent" class="text-green-600 font-bold">✓</span>
              <span v-else class="text-gray-300">-</span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ process.dhl_number || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ process.etd_date || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ process.eta_date || '-' }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
              <button @click.stop="$emit('edit', process)" class="text-blue-600 hover:text-blue-900">Editar</button>
            </td>
          </tr>
          <tr v-if="exportProcesses.data.length === 0">
            <td colspan="19" class="px-6 py-12 text-center text-gray-500">
              Nenhum contrato encontrado.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <!-- Pagination slot here -->
    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center justify-between">
      <span class="text-sm text-gray-700">Mostrando {{ exportProcesses.data.length }} registros</span>
      <!-- Add Laravel pagination links if needed -->
    </div>
  </div>
</template>
