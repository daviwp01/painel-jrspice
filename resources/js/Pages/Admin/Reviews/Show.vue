<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { ArrowLeft, Star, MessageCircle, Send, User, FileText, CheckCircle, RefreshCw, Clock } from 'lucide-vue-next';

const props = defineProps({
  review: Object,
});

const ratingLabel = (r) =>
  ['', 'Muito Ruim', 'Ruim', 'Regular', 'Bom', 'Excelente'][r] ?? '—';

const form = useForm({
  admin_reply: props.review.admin_reply ?? '',
});

const submit = () => {
  form.post(route('admin.reviews.reply', props.review.id), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head :title="`Avaliação – ${review.process?.contract_number}`" />
  <DashboardLayout>
    <div class="px-6 py-7 md:px-8 w-full max-w-none space-y-6">

      <!-- Back -->
      <div>
        <Link
          :href="route('admin.reviews.index')"
          class="inline-flex items-center text-xs font-bold text-slate-400 hover:text-blue-600 transition-colors group uppercase tracking-widest"
        >
          <ArrowLeft class="w-3.5 h-3.5 mr-1.5 transform group-hover:-translate-x-1 transition-transform" />
          Voltar para Avaliações
        </Link>
      </div>

      <!-- Header -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-black text-slate-900 uppercase tracking-tighter flex items-center gap-3">
            <Star class="w-6 h-6 text-amber-500" />
            Avaliação do Contrato
          </h1>
          <p class="text-sm font-medium text-slate-500 mt-1">
            Contrato <span class="font-bold text-slate-700">{{ review.process?.contract_number }}</span>
            · Produto <span class="font-bold text-slate-700">{{ review.process?.product?.name ?? '—' }}</span>
          </p>
        </div>
        <span
          class="shrink-0 text-xs font-bold px-3 py-1.5 rounded-xl border"
          :class="review.admin_reply ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200'"
        >
          {{ review.admin_reply ? '✓ Respondida' : '⏳ Aguardando resposta' }}
        </span>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- LEFT: Review Detail -->
        <div class="lg:col-span-2 space-y-5">

          <!-- Client Info -->
          <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
              <User class="w-4 h-4" />Cliente
            </h2>
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-black text-lg shadow-md">
                {{ review.user?.name?.[0]?.toUpperCase() ?? '?' }}
              </div>
              <div>
                <p class="text-sm font-black text-slate-800">{{ review.user?.name ?? '—' }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ review.user?.email ?? '—' }}</p>
              </div>
            </div>
          </div>

          <!-- Review Content -->
          <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-3xl border border-amber-200 shadow-sm p-6 space-y-4">
            <h2 class="text-xs font-bold text-amber-700 uppercase tracking-widest border-b border-amber-200/60 pb-3 mb-4 flex items-center gap-2">
              <Star class="w-4 h-4" />Avaliação do Cliente
            </h2>

            <!-- Stars -->
            <div class="flex items-center gap-2">
              <Star
                v-for="i in 5"
                :key="i"
                class="w-8 h-8 transition-colors"
                :class="review.rating >= i ? 'text-amber-400' : 'text-amber-100'"
                :fill="review.rating >= i ? 'currentColor' : 'none'"
              />
              <span class="ml-2 text-base font-black text-amber-700">{{ ratingLabel(review.rating) }}</span>
            </div>

            <!-- Comment -->
            <div v-if="review.comment" class="bg-white/60 rounded-2xl p-4 border border-amber-200/40">
              <p class="text-sm font-medium text-slate-700 leading-relaxed italic">"{{ review.comment }}"</p>
            </div>
            <p v-else class="text-xs text-amber-600/60 italic">Nenhum comentário adicionado pelo cliente.</p>

            <p class="text-[10px] text-amber-600/60 flex items-center gap-1.5">
              <Clock class="w-3 h-3" />
              Enviada em {{ new Date(review.created_at).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' }) }}
            </p>
          </div>

          <!-- Existing Reply (read-only view) -->
          <div v-if="review.admin_reply && !form.isDirty" class="bg-blue-50 rounded-3xl border border-blue-200 shadow-sm p-6 space-y-3">
            <h2 class="text-xs font-bold text-blue-600 uppercase tracking-widest border-b border-blue-200/60 pb-3 mb-4 flex items-center gap-2">
              <MessageCircle class="w-4 h-4" />Sua Resposta Enviada
            </h2>
            <p class="text-sm font-medium text-blue-900 leading-relaxed">{{ review.admin_reply }}</p>
            <div class="flex items-center gap-1.5 pt-1">
              <CheckCircle class="w-3.5 h-3.5 text-emerald-500" />
              <p class="text-[10px] text-slate-400">
                Respondido por <strong>{{ review.replied_by?.name ?? 'Admin' }}</strong>
                em {{ review.replied_at ? new Date(review.replied_at).toLocaleDateString('pt-BR') : '—' }}
              </p>
            </div>
          </div>

        </div>

        <!-- RIGHT: Reply Form -->
        <div class="space-y-5">

          <!-- Contract Info Card -->
          <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5">
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-4 flex items-center gap-2">
              <FileText class="w-4 h-4" />Contrato
            </h2>
            <div class="space-y-2">
              <div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wider">Número</p>
                <p class="text-sm font-bold text-slate-800 font-mono">{{ review.process?.contract_number }}</p>
              </div>
              <div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wider">Produto</p>
                <p class="text-sm font-bold text-slate-700">{{ review.process?.product?.name ?? '—' }}</p>
              </div>
              <div>
                <p class="text-[10px] text-slate-400 uppercase tracking-wider">Status</p>
                <p class="text-xs font-semibold text-slate-600">{{ review.process?.status ?? '—' }}</p>
              </div>
            </div>
          </div>

          <!-- Reply Form -->
          <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 space-y-4">
            <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 flex items-center gap-2">
              <MessageCircle class="w-4 h-4" />
              {{ review.admin_reply ? 'Editar Resposta' : 'Responder ao Cliente' }}
            </h2>

            <p class="text-xs text-slate-500 leading-relaxed">
              Sua resposta será exibida diretamente para o cliente na aba <strong>Avaliação</strong> do contrato dele.
            </p>

            <textarea
              v-model="form.admin_reply"
              rows="6"
              placeholder="Escreva uma resposta profissional e personalizada para o cliente..."
              class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 transition-all resize-none"
            />
            <p v-if="form.errors.admin_reply" class="text-xs text-rose-500">{{ form.errors.admin_reply }}</p>

            <button
              type="button"
              @click="submit"
              :disabled="!form.admin_reply.trim() || form.processing"
              class="w-full flex items-center justify-center gap-2 px-6 py-3 rounded-2xl text-sm font-bold uppercase tracking-wider transition-all"
              :class="form.admin_reply.trim() && !form.processing
                ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-md shadow-blue-200'
                : 'bg-slate-100 text-slate-400 cursor-not-allowed'"
            >
              <Send v-if="!form.processing" class="w-4 h-4" />
              <RefreshCw v-else class="w-4 h-4 animate-spin" />
              {{ form.processing ? 'Enviando...' : 'Enviar Resposta ao Cliente' }}
            </button>
          </div>

        </div>
      </div>

    </div>
  </DashboardLayout>
</template>
