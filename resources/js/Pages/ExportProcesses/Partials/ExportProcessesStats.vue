<script setup>
import { TrendingUp, DollarSign, AlertTriangle } from 'lucide-vue-next';

defineProps({
  summary: {
    type: Object,
    required: true
  }
});
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
    <!-- Volume Total -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex items-center gap-5 relative overflow-hidden group">
      <div class="absolute right-0 top-0 w-56 h-56 bg-blue-50/60 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-blue-100/60 transition-colors pointer-events-none"></div>
      <div class="w-14 h-14 rounded-2xl bg-blue-600/10 border border-blue-500/20 flex items-center justify-center shrink-0">
        <TrendingUp class="w-7 h-7 text-blue-600" />
      </div>
      <div class="relative z-10 min-w-0">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest leading-none mb-2">Volume Total Exportado</p>
        <div class="flex items-baseline gap-2">
          <span class="text-3xl font-black text-slate-800 tabular-nums">{{ Number(summary.total_tons || 0).toLocaleString('pt-BR') }}</span>
          <span class="text-sm font-bold text-slate-400 uppercase">ton</span>
        </div>
      </div>
    </div>

    <!-- Comissões Pendentes -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 flex items-center gap-5 relative overflow-hidden group">
      <div class="absolute right-0 top-0 w-56 h-56 bg-emerald-50/60 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-emerald-100/60 transition-colors pointer-events-none"></div>
      <div class="w-14 h-14 rounded-2xl bg-emerald-600/10 border border-emerald-500/20 flex items-center justify-center shrink-0">
        <DollarSign class="w-7 h-7 text-emerald-600" />
      </div>
      <div class="relative z-10 min-w-0">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest leading-none mb-2">Comissões Pendentes</p>
        <div class="flex items-baseline gap-2">
          <span class="text-3xl font-black text-slate-800 tabular-nums">${{ Number(summary.pending_commissions || 0).toLocaleString('pt-BR', { minimumFractionDigits: 0 }) }}</span>
          <span class="text-sm font-bold text-slate-400 uppercase">USD</span>
        </div>
      </div>
    </div>

    <!-- Atrasos Logística -->
    <div class="bg-white rounded-3xl border shadow-sm p-6 flex items-center gap-5 relative overflow-hidden group"
         :class="summary.delayed_logistics > 0 ? 'border-rose-200' : 'border-slate-200'">
      <div class="absolute right-0 top-0 w-56 h-56 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none transition-colors"
           :class="summary.delayed_logistics > 0 ? 'bg-rose-50/60 group-hover:bg-rose-100/60' : 'bg-slate-50/60 group-hover:bg-slate-100/60'"></div>
      <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0 border"
           :class="summary.delayed_logistics > 0 ? 'bg-rose-600/10 border-rose-500/20' : 'bg-slate-100 border-slate-200'">
        <AlertTriangle class="w-7 h-7" :class="summary.delayed_logistics > 0 ? 'text-rose-600' : 'text-slate-400'" />
      </div>
      <div class="relative z-10 min-w-0">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest leading-none mb-2">Atrasos Logísticos</p>
        <div class="flex items-baseline gap-2">
          <span class="text-3xl font-black tabular-nums" :class="summary.delayed_logistics > 0 ? 'text-rose-600' : 'text-slate-800'">{{ summary.delayed_logistics || 0 }}</span>
          <span class="text-sm font-bold uppercase" :class="summary.delayed_logistics > 0 ? 'text-rose-400' : 'text-slate-400'">
            {{ summary.delayed_logistics > 0 ? 'atenção' : 'em dia' }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
