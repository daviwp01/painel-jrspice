<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Star, MessageCircle, BarChart2, AlertTriangle, Clock, Search, Eye, ChevronRight, TrendingUp, Users } from 'lucide-vue-next';

const props = defineProps({
  reviews:        Object,
  filters:        Object,
  metrics:        Object,
  topReviewers:   Array,
  problemClients: Array,
  distribution:   Object,
});

const ratingLabel = (r) =>
  ['', 'Muito Ruim', 'Ruim', 'Regular', 'Bom', 'Excelente'][r] ?? '—';

const ratingColor = (r) => {
  if (r >= 4) return 'text-emerald-600 bg-emerald-50 border-emerald-200';
  if (r === 3) return 'text-amber-600 bg-amber-50 border-amber-200';
  return 'text-rose-600 bg-rose-50 border-rose-200';
};

const totalDist = computed(() =>
  Object.values(props.distribution).reduce((a, b) => a + b, 0) || 1
);

const distPct = (count) => Math.round((count / totalDist.value) * 100);

const search = (e) => {
  router.get(route('admin.reviews.index'), { search: e.target.value, rating: props.filters.rating }, { preserveState: true, replace: true });
};

const filterRating = (r) => {
  router.get(route('admin.reviews.index'), { ...props.filters, rating: r || undefined }, { preserveState: true, replace: true });
};
</script>

<template>
  <Head title="Avaliações de Clientes" />
  <DashboardLayout>
    <div class="px-6 py-7 md:px-8 w-full max-w-none space-y-6">

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tighter flex items-center gap-3">
            <Star class="w-6 h-6 text-amber-500" />
            Avaliações de Clientes
          </h1>
          <p class="text-sm font-medium text-slate-500 mt-1">Monitore o feedback dos clientes e responda às avaliações.</p>
        </div>
      </div>

      <!-- Metric Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5"><BarChart2 class="w-3.5 h-3.5" />Total de Avaliações</p>
          <p class="text-3xl font-black text-slate-900 mt-2">{{ metrics.totalReviews }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5"><Star class="w-3.5 h-3.5 text-amber-500" />Nota Média</p>
          <div class="flex items-end gap-2 mt-2">
            <p class="text-3xl font-black text-amber-600">{{ metrics.avgRating }}</p>
            <div class="flex items-center gap-0.5 pb-1">
              <Star v-for="i in 5" :key="i" class="w-4 h-4" :class="Math.round(metrics.avgRating) >= i ? 'text-amber-400' : 'text-slate-200'" :fill="Math.round(metrics.avgRating) >= i ? 'currentColor' : 'none'" />
            </div>
          </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5"><Clock class="w-3.5 h-3.5 text-blue-500" />Aguardando Resposta</p>
          <p class="text-3xl font-black mt-2" :class="metrics.pendingReplies > 0 ? 'text-blue-600' : 'text-slate-300'">{{ metrics.pendingReplies }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-1.5"><AlertTriangle class="w-3.5 h-3.5 text-rose-500" />Avaliações Críticas</p>
          <p class="text-3xl font-black mt-2" :class="metrics.withProblems > 0 ? 'text-rose-600' : 'text-slate-300'">{{ metrics.withProblems }}</p>
        </div>
      </div>

      <!-- Distribution + Top Panels -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Rating Distribution -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
          <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
            <BarChart2 class="w-4 h-4" />Distribuição de Notas
          </h2>
          <div class="space-y-3">
            <div v-for="i in [5,4,3,2,1]" :key="i" class="flex items-center gap-3">
              <div class="flex items-center gap-1 shrink-0 w-20">
                <Star class="w-3.5 h-3.5 text-amber-400" fill="currentColor" />
                <span class="text-xs font-bold text-slate-600">{{ i }}</span>
                <span class="text-[10px] text-slate-400">{{ ratingLabel(i).split(' ')[0] }}</span>
              </div>
              <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all duration-700"
                  :class="i >= 4 ? 'bg-emerald-400' : i === 3 ? 'bg-amber-400' : 'bg-rose-400'"
                  :style="{ width: distPct(distribution[i]) + '%' }"
                />
              </div>
              <span class="text-xs font-bold text-slate-500 w-8 text-right">{{ distribution[i] }}</span>
            </div>
          </div>
        </div>

        <!-- Top Reviewers -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
          <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
            <TrendingUp class="w-4 h-4" />Clientes Mais Ativos
          </h2>
          <div class="space-y-3">
            <div v-for="(r, idx) in topReviewers" :key="r.user_id" class="flex items-center gap-3">
              <span class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center text-[10px] font-black text-slate-500 shrink-0">{{ idx + 1 }}</span>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-slate-700 truncate">{{ r.user?.name ?? '—' }}</p>
                <p class="text-[10px] text-slate-400">{{ r.total }} avaliações · média {{ Number(r.avg_rating).toFixed(1) }}★</p>
              </div>
            </div>
            <p v-if="!topReviewers.length" class="text-xs text-slate-400 text-center py-4">Nenhuma avaliação ainda.</p>
          </div>
        </div>

        <!-- Problem Clients -->
        <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
          <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
            <AlertTriangle class="w-4 h-4 text-rose-500" />Clientes com Problemas
          </h2>
          <div class="space-y-3">
            <div v-for="(r, idx) in problemClients" :key="r.user_id" class="flex items-center gap-3">
              <span class="w-5 h-5 rounded-full bg-rose-100 flex items-center justify-center text-[10px] font-black text-rose-600 shrink-0">{{ idx + 1 }}</span>
              <div class="flex-1 min-w-0">
                <p class="text-xs font-bold text-slate-700 truncate">{{ r.user?.name ?? '—' }}</p>
                <p class="text-[10px] text-rose-500">{{ r.total }} avaliações críticas (≤ 2★)</p>
              </div>
            </div>
            <p v-if="!problemClients.length" class="text-xs text-slate-400 text-center py-4">Nenhum problema identificado.</p>
          </div>
        </div>

      </div>

      <!-- Filters & Table -->
      <div class="bg-white rounded-3xl border border-slate-200 shadow-sm">
        <!-- Toolbar -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-4 p-5 border-b border-slate-100">
          <div class="relative flex-1">
            <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" />
            <input
              type="text"
              :value="filters.search"
              @input="search"
              placeholder="Buscar por cliente ou contrato..."
              class="w-full pl-9 pr-4 py-2.5 text-sm font-medium rounded-xl border border-slate-200 bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all"
            />
          </div>
          <div class="flex items-center gap-2">
            <button
              v-for="r in [null, 5, 4, 3, 2, 1]"
              :key="r"
              type="button"
              @click="filterRating(r)"
              class="px-3 py-1.5 rounded-xl text-xs font-bold border transition-all"
              :class="(filters.rating == r || (!filters.rating && r === null))
                ? 'bg-blue-600 text-white border-blue-600'
                : 'bg-slate-50 text-slate-500 border-slate-200 hover:border-blue-300'"
            >
              {{ r === null ? 'Todos' : r + '★' }}
            </button>
          </div>
        </div>

        <!-- List -->
        <div class="divide-y divide-slate-100">
          <div
            v-for="review in reviews.data"
            :key="review.id"
            class="flex flex-col sm:flex-row sm:items-center gap-4 p-5 hover:bg-slate-50/60 transition-colors group"
          >
            <!-- Stars + Rating -->
            <div class="flex items-center gap-2 shrink-0 w-32">
              <div class="flex items-center gap-0.5">
                <Star
                  v-for="i in 5"
                  :key="i"
                  class="w-4 h-4"
                  :class="review.rating >= i ? 'text-amber-400' : 'text-slate-200'"
                  :fill="review.rating >= i ? 'currentColor' : 'none'"
                />
              </div>
              <span class="text-xs font-bold" :class="ratingColor(review.rating).split(' ')[0]">{{ review.rating }}★</span>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0 space-y-0.5">
              <p class="text-sm font-bold text-slate-800 truncate">{{ review.user?.name ?? '—' }}</p>
              <p class="text-[10px] text-slate-400 flex items-center gap-1.5">
                <span class="font-mono">{{ review.process?.contract_number }}</span>
                <span>·</span>
                <span>{{ new Date(review.created_at).toLocaleDateString('pt-BR') }}</span>
              </p>
              <p v-if="review.comment" class="text-xs text-slate-500 truncate italic">"{{ review.comment }}"</p>
            </div>

            <!-- Status badge -->
            <div class="flex items-center gap-2 shrink-0">
              <span
                class="text-[10px] font-bold px-2.5 py-1 rounded-full border"
                :class="review.admin_reply ? 'bg-emerald-50 text-emerald-600 border-emerald-200' : 'bg-amber-50 text-amber-600 border-amber-200'"
              >
                {{ review.admin_reply ? '✓ Respondida' : '⏳ Aguardando' }}
              </span>

              <Link
                :href="route('admin.reviews.show', review.id)"
                class="flex items-center gap-1.5 text-xs font-bold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 border border-blue-200 px-3 py-1.5 rounded-xl transition-all"
              >
                <Eye class="w-3.5 h-3.5" />
                Ver & Responder
              </Link>
            </div>
          </div>

          <div v-if="!reviews.data.length" class="flex flex-col items-center gap-3 py-16 text-center">
            <Star class="w-10 h-10 text-slate-200" />
            <p class="text-sm font-bold text-slate-400">Nenhuma avaliação encontrada.</p>
          </div>
        </div>

        <!-- Pagination -->
        <div v-if="reviews.last_page > 1" class="flex items-center justify-between p-5 border-t border-slate-100">
          <p class="text-xs text-slate-400 font-medium">
            Mostrando {{ reviews.from }}–{{ reviews.to }} de {{ reviews.total }} avaliações
          </p>
          <div class="flex items-center gap-1">
            <Link
              v-for="link in reviews.links"
              :key="link.label"
              :href="link.url ?? '#'"
              v-html="link.label"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
              :class="link.active
                ? 'bg-blue-600 text-white'
                : link.url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300 cursor-not-allowed'"
            />
          </div>
        </div>
      </div>

    </div>
  </DashboardLayout>
</template>
