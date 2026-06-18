<script setup>
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Users, Construction } from 'lucide-vue-next';
</script>

<template>
  <Head title="Clientes" />

  <DashboardLayout>
    <template #header>
        <h2 class="hidden md:block text-xs font-bold text-slate-400 uppercase tracking-widest">Gestão de Clientes</h2>
    </template>

    <div class="p-6 md:p-8 space-y-8 w-full max-w-none">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-black text-slate-800 uppercase tracking-tighter flex items-center gap-3">
            <Users class="w-8 h-8 text-blue-600" />
            Gestão de Clientes
          </h2>
          <p class="text-slate-500 mt-1 text-sm font-medium">Acompanhe contratos, pedidos, documentações e o rastreamento logístico para os clientes.</p>
        </div>
      </div>

      <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col items-center justify-center py-20 px-4 text-center mt-8">
        <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-50 rounded-xl border-2 border-dashed border-blue-200 mb-6">
          <Construction class="w-8 h-8 text-blue-500" />
        </div>
        <h3 class="text-lg font-bold text-slate-800">Em Desenvolvimento</h3>
        <p class="text-slate-500 font-medium mt-1 max-w-md mx-auto text-sm">Esta página está sendo construída. Em breve, a empresa poderá alimentar todos os dados de rastreamento, status de embarque e contratos, refletindo diretamente na área de acompanhamento do cliente.</p>
      </div>
    </div>
  </DashboardLayout>
</template>
