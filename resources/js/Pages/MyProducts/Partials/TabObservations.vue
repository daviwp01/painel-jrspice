<script setup>
import { MessageSquare, Info, AlertCircle } from 'lucide-vue-next';

defineProps({
  process: Object,
});
</script>

<template>
  <div class="animate-in fade-in duration-200 space-y-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
      <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
        <MessageSquare class="w-4 h-4" />
        Observações do Processo
      </h2>

      <div v-if="process.incident || process.observations" class="space-y-4">
        <!-- Incident alert -->
        <div v-if="process.incident" class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-2xl p-4">
          <AlertCircle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
          <div>
            <p class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-1">Ocorrência Reportada</p>
            <p class="text-sm font-semibold text-amber-800 leading-relaxed whitespace-pre-line">{{ process.incident }}</p>
          </div>
        </div>

        <!-- General observations -->
        <div v-if="process.observations" class="flex items-start gap-3 bg-slate-50 border border-slate-200 rounded-2xl p-4">
          <Info class="w-5 h-5 text-slate-500 shrink-0 mt-0.5" />
          <div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Observações Gerais</p>
            <p class="text-sm font-semibold text-slate-700 leading-relaxed whitespace-pre-line">{{ process.observations }}</p>
          </div>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else class="flex flex-col items-center gap-4 py-12 text-center">
        <div class="w-14 h-14 rounded-3xl bg-slate-100 flex items-center justify-center">
          <MessageSquare class="w-7 h-7 text-slate-300" />
        </div>
        <div>
          <p class="text-sm font-bold text-slate-500">Nenhuma observação registrada</p>
          <p class="text-xs text-slate-400 mt-1">Quando houver atualizações ou ocorrências, elas aparecerão aqui.</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-in { animation: fadeIn 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
</style>
