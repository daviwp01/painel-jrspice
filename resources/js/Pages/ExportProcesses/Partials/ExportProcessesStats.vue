<script setup>
import { TrendingUp, DollarSign, AlertCircle } from 'lucide-vue-next';

defineProps({
  summary: {
    type: Object,
    required: true
  }
});
</script>

<template>
  <div class="grid grid-cols-1 gap-6 mb-8 sm:grid-cols-2 lg:grid-cols-3">
    <!-- Volume -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-100">
      <div class="p-6 flex items-center">
        <div class="p-3 rounded-full bg-blue-50 text-blue-600">
          <TrendingUp class="w-8 h-8" />
        </div>
        <div class="ml-5">
          <p class="text-sm font-medium text-gray-500 truncate">Volume Total Exportado</p>
          <div class="flex items-baseline">
            <p class="text-2xl font-semibold text-gray-900">{{ summary.total_tons || 0 }}</p>
            <p class="ml-2 text-sm text-gray-500">Toneladas</p>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Commissions -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-100">
      <div class="p-6 flex items-center">
        <div class="p-3 rounded-full bg-emerald-50 text-emerald-600">
          <DollarSign class="w-8 h-8" />
        </div>
        <div class="ml-5">
          <p class="text-sm font-medium text-gray-500 truncate">Comissões Pendentes (USD)</p>
          <div class="flex items-baseline">
            <p class="text-2xl font-semibold text-gray-900">${{ Number(summary.pending_commissions || 0).toLocaleString() }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Alerts -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-xl border border-gray-100">
      <div class="p-6 flex items-center">
        <div class="p-3 rounded-full bg-rose-50 text-rose-600">
          <AlertCircle class="w-8 h-8" />
        </div>
        <div class="ml-5">
          <p class="text-sm font-medium text-gray-500 truncate">Atrasos de Logística (ETA sem DHL)</p>
          <div class="flex items-baseline">
            <p class="text-2xl font-semibold text-gray-900">{{ summary.delayed_logistics || 0 }}</p>
            <p class="ml-2 text-sm text-rose-500 font-medium">Requer atenção</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
