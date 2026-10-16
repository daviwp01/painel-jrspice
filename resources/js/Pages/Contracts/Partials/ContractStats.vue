<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { TrendingUp, DollarSign, AlertTriangle, FileStack } from 'lucide-vue-next';

const props = defineProps({
  summary: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  routeName: { type: String, default: 'admin.clients.index' },
});

const isDelayedFilterActive = computed(() => Boolean(props.filters?.delayed));

const applyDelayedFilter = () => {
  const { delayed, page, ...rest } = props.filters || {};

  router.get(
    route(props.routeName),
    isDelayedFilterActive.value ? rest : { ...rest, delayed: true },
    { preserveState: true, preserveScroll: true, replace: true },
  );
};
</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
    <!-- Volume Total -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm px-4 py-3.5 flex items-center gap-3 relative overflow-hidden group">
      <div class="absolute right-0 top-0 w-48 h-48 bg-blue-50/60 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-blue-100/60 transition-colors pointer-events-none"></div>
      <div class="w-10 h-10 rounded-xl bg-blue-600/10 border border-blue-500/20 flex items-center justify-center shrink-0">
        <TrendingUp class="w-5 h-5 text-blue-600" />
      </div>
      <div class="relative z-10 min-w-0">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider leading-none mb-1.5">Volume Total Exportado</p>
        <div class="flex items-baseline gap-1.5 whitespace-nowrap">
          <span class="text-xl font-black text-slate-800 tabular-nums leading-none">
            {{ Number(summary.total_tons || 0).toLocaleString('pt-BR', { maximumFractionDigits: 3 }) }}
          </span>
          <span class="text-[11px] font-bold text-slate-400 uppercase">ton</span>
        </div>
      </div>
    </div>

    <!-- Comissões Pendentes -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm px-4 py-3.5 flex items-center gap-3 relative overflow-hidden group">
      <div class="absolute right-0 top-0 w-48 h-48 bg-emerald-50/60 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-emerald-100/60 transition-colors pointer-events-none"></div>
      <div class="w-10 h-10 rounded-xl bg-emerald-600/10 border border-emerald-500/20 flex items-center justify-center shrink-0">
        <DollarSign class="w-5 h-5 text-emerald-600" />
      </div>
      <div class="relative z-10 min-w-0">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider leading-none mb-1.5">Comissões Pendentes</p>
        <div class="flex items-baseline gap-1.5 whitespace-nowrap">
          <span class="text-xl font-black text-slate-800 tabular-nums leading-none">
            ${{ Number(summary.pending_commissions || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
          </span>
          <span class="text-[11px] font-bold text-slate-400 uppercase">USD</span>
        </div>
      </div>
    </div>

    <!-- Atrasos Logísticos (filtra) -->
    <button
      type="button"
      @click="applyDelayedFilter"
      class="text-left bg-white rounded-3xl border shadow-sm px-4 py-3.5 flex items-center gap-3 relative overflow-hidden group cursor-pointer transition-all"
      :class="isDelayedFilterActive
        ? 'border-rose-400 ring-2 ring-rose-200'
        : (summary.delayed_logistics > 0 ? 'border-rose-200 hover:border-rose-400' : 'border-slate-200 hover:border-slate-300')"
      title="Filtrar os contratos em atraso"
    >
      <div
        class="absolute right-0 top-0 w-48 h-48 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none transition-colors"
        :class="summary.delayed_logistics > 0 ? 'bg-rose-50/60 group-hover:bg-rose-100/60' : 'bg-slate-50/60 group-hover:bg-slate-100/60'"
      ></div>
      <div
        class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border transition-transform group-hover:scale-105"
        :class="summary.delayed_logistics > 0 ? 'bg-rose-600/10 border-rose-500/20' : 'bg-slate-100 border-slate-200'"
      >
        <AlertTriangle class="w-5 h-5" :class="summary.delayed_logistics > 0 ? 'text-rose-600' : 'text-slate-400'" />
      </div>
      <div class="relative z-10 min-w-0">
        <p class="text-[10px] font-bold uppercase tracking-wider leading-none mb-1.5" :class="isDelayedFilterActive ? 'text-rose-500' : 'text-slate-400'">
          Atrasos Logísticos<span v-if="isDelayedFilterActive"> · filtrando</span>
        </p>
        <div class="flex items-baseline gap-1.5 whitespace-nowrap">
          <span class="text-xl font-black tabular-nums leading-none" :class="summary.delayed_logistics > 0 ? 'text-rose-600' : 'text-slate-800'">
            {{ summary.delayed_logistics || 0 }}
          </span>
          <span class="text-[11px] font-bold uppercase" :class="summary.delayed_logistics > 0 ? 'text-rose-400' : 'text-slate-400'">
            {{ summary.delayed_logistics > 0 ? 'atenção' : 'em dia' }}
          </span>
        </div>
      </div>
    </button>

    <!-- Contratos Ativos -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm px-4 py-3.5 flex items-center gap-3 relative overflow-hidden group">
      <div class="absolute right-0 top-0 w-48 h-48 bg-violet-50/60 rounded-full blur-3xl -mr-16 -mt-16 group-hover:bg-violet-100/60 transition-colors pointer-events-none"></div>
      <div class="w-10 h-10 rounded-xl bg-violet-600/10 border border-violet-500/20 flex items-center justify-center shrink-0">
        <FileStack class="w-5 h-5 text-violet-600" />
      </div>
      <div class="relative z-10 min-w-0">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider leading-none mb-1.5">Contratos Ativos</p>
        <div class="flex items-baseline gap-1.5 whitespace-nowrap">
          <span class="text-xl font-black text-slate-800 tabular-nums leading-none">{{ summary.active_contracts || 0 }}</span>
          <span class="text-[11px] font-bold text-slate-400 uppercase">de {{ summary.total_contracts || 0 }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
