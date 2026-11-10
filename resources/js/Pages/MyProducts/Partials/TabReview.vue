<script setup>
import { getBcp47Locale } from '@/Utils/locale';
import { ref, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Star, Lock, Send, CheckCircle, MessageCircle, RefreshCw } from 'lucide-vue-next';

const props = defineProps({
  process:    Object,
  isFinalized: Boolean,
  userReview: Object, // null if not reviewed yet
});

// ─── State ────────────────────────────────────────────────────────────────────
const hoverRating = ref(0);
const isEditing   = ref(false);

const form = useForm({
  rating:  props.userReview?.rating  ?? 0,
  comment: props.userReview?.comment ?? '',
});

// ─── Computed ─────────────────────────────────────────────────────────────────
const hasReview    = computed(() => !!props.userReview && !isEditing.value);
const activeRating = computed(() => hoverRating.value || form.rating);

const ratingLabel = (r) =>
  ['', 'Very Bad', 'Bad', 'Regular', 'Good', 'Excellent'][r] ?? '';

const starColor = (i) =>
  activeRating.value >= i ? 'text-amber-400' : 'text-slate-200';

const starFill = (i) =>
  activeRating.value >= i ? 'currentColor' : 'none';

// ─── Actions ──────────────────────────────────────────────────────────────────
const startEdit = () => {
  form.rating  = props.userReview?.rating  ?? 0;
  form.comment = props.userReview?.comment ?? '';
  isEditing.value = true;
};

const cancelEdit = () => {
  isEditing.value = false;
};

const submit = () => {
  form.post(route('reviews.store', props.process.id), {
    preserveScroll: true,
    onSuccess: () => { isEditing.value = false; },
  });
};
</script>

<template>
  <div class="animate-in fade-in duration-200">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6">

      <!-- Header -->
      <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest border-b border-slate-100 pb-3 mb-5 flex items-center gap-2">
        <Star class="w-4 h-4" />
        {{ $t('Process Review') }}
      </h2>

      <!-- ─── LOCKED: not finalized ─────────────────────────────────────────── -->
      <div v-if="!isFinalized" class="flex flex-col items-center gap-5 py-12 text-center">
        <div class="w-16 h-16 rounded-3xl bg-slate-100 flex items-center justify-center">
          <Lock class="w-8 h-8 text-slate-300" />
        </div>
        <div class="max-w-xs">
          <p class="text-sm font-bold text-slate-500">{{ $t('Review Blocked') }}</p>
          <p class="text-xs text-slate-400 mt-2 leading-relaxed">
            {{ $t('The review becomes available only after the') }}
            <strong class="text-slate-600">{{ $t('receipt and finalization of the product') }}</strong>.
            {{ $t('When the status is updated to') }}
            <span class="font-bold text-emerald-600">{{ $t('Process Finalized') }}</span>, {{ $t('you will be able to review it here.') }}
          </p>
        </div>
        <div class="flex items-center gap-2 opacity-30 pointer-events-none select-none">
          <Star v-for="i in 5" :key="i" class="w-9 h-9 text-slate-300" />
        </div>
      </div>

      <!-- ─── SUBMITTED: showing saved review ────────────────────────────────── -->
      <div v-else-if="hasReview" class="space-y-5">

        <!-- Review Card -->
        <div class="bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-200 rounded-2xl p-5">
          <div class="flex items-start justify-between gap-4">
            <div class="space-y-3">
              <!-- Stars display -->
              <div class="flex items-center gap-1.5">
                <Star
                  v-for="i in 5"
                  :key="i"
                  class="w-6 h-6 transition-colors"
                  :class="userReview.rating >= i ? 'text-amber-400' : 'text-slate-200'"
                  :fill="userReview.rating >= i ? 'currentColor' : 'none'"
                />
                <span class="ml-2 text-sm font-bold text-amber-700">
                  {{ $t(ratingLabel(userReview.rating)) }}
                </span>
              </div>

              <!-- Comment -->
              <p v-if="userReview.comment" class="text-sm font-medium text-slate-700 leading-relaxed italic">
                "{{ userReview.comment }}"
              </p>
              <p v-else class="text-xs text-slate-400 italic">{{ $t('No comment added.') }}</p>

              <div class="flex items-center gap-1.5 pt-1">
                <CheckCircle class="w-3.5 h-3.5 text-emerald-500" />
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">
                  {{ $t('Review sent on') }} {{ new Date(userReview.created_at).toLocaleDateString(getBcp47Locale()) }}
                </span>
              </div>
            </div>

            <!-- Edit button -->
            <button
              type="button"
              @click="startEdit"
              class="shrink-0 flex items-center gap-1.5 text-xs font-bold text-amber-600 hover:text-amber-700 bg-white border border-amber-200 hover:border-amber-300 px-3 py-2 rounded-xl transition-all"
            >
              <RefreshCw class="w-3.5 h-3.5" />
              {{ $t('Edit again') }}
            </button>
          </div>
        </div>

        <!-- Admin Reply -->
        <div v-if="userReview.admin_reply" class="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-2xl p-4">
          <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center shrink-0 mt-0.5">
            <MessageCircle class="w-4 h-4 text-white" />
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-[10px] font-bold text-blue-600 uppercase tracking-wider mb-1 flex items-center gap-1.5">
              {{ $t('JR Spice Reply') }}
              <span v-if="userReview.replied_at" class="font-normal text-blue-400 normal-case">
                · {{ new Date(userReview.replied_at).toLocaleDateString(getBcp47Locale()) }}
              </span>
            </p>
            <p class="text-sm font-medium text-blue-900 leading-relaxed">{{ userReview.admin_reply }}</p>
          </div>
        </div>

        <div v-else class="flex items-center gap-2 text-xs text-slate-400 px-1">
          <MessageCircle class="w-3.5 h-3.5" />
          {{ $t('Awaiting a reply from the JR Spice team.') }}
        </div>

      </div>

      <!-- ─── FORM: new review or editing ─────────────────────────────────── -->
      <div v-else class="space-y-6">
        <p class="text-sm font-semibold text-slate-600">
          {{ userReview ? $t('Edit your review below.') : $t('Your order has been finalized! Tell us about your experience.') }}
        </p>

        <!-- Star Rating -->
        <div>
          <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">{{ $t('Your score') }}</p>
          <div class="flex items-center gap-2">
            <button
              v-for="i in 5"
              :key="i"
              type="button"
              @click="form.rating = i"
              @mouseenter="hoverRating = i"
              @mouseleave="hoverRating = 0"
              class="transition-transform hover:scale-110 focus:outline-none"
            >
              <Star
                class="w-10 h-10 transition-colors"
                :class="starColor(i)"
                :fill="starFill(i)"
              />
            </button>
            <span v-if="form.rating" class="ml-2 text-sm font-bold text-amber-600 transition-all">
              {{ $t(ratingLabel(form.rating)) }}
            </span>
          </div>
          <p v-if="form.errors.rating" class="text-xs text-rose-500 mt-1">{{ form.errors.rating }}</p>
        </div>

        <!-- Comment -->
        <div>
          <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-2">
            {{ $t('Comment') }} <span class="normal-case font-normal text-slate-400">{{ $t('(optional)') }}</span>
          </label>
          <textarea
            v-model="form.comment"
            rows="5"
            :placeholder="$t('Leave a comment about your experience...')"
            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-amber-400/30 focus:border-amber-400 transition-all resize-none"
          />
          <p v-if="form.errors.comment" class="text-xs text-rose-500 mt-1">{{ form.errors.comment }}</p>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
          <button
            v-if="isEditing"
            type="button"
            @click="cancelEdit"
            class="px-5 py-2.5 rounded-xl text-sm font-bold text-slate-500 hover:text-slate-700 border border-slate-200 hover:border-slate-300 transition-all"
          >
            {{ $t('Cancel') }}
          </button>

          <button
            type="button"
            @click="submit"
            :disabled="form.rating === 0 || form.processing"
            class="inline-flex items-center gap-2 px-6 py-3 rounded-2xl text-sm font-bold uppercase tracking-wider transition-all"
            :class="form.rating > 0 && !form.processing
              ? 'bg-amber-400 hover:bg-amber-500 text-white shadow-md shadow-amber-200 hover:shadow-lg hover:shadow-amber-200/60'
              : 'bg-slate-100 text-slate-400 cursor-not-allowed'"
          >
            <Send v-if="!form.processing" class="w-4 h-4" />
            <RefreshCw v-else class="w-4 h-4 animate-spin" />
            {{ form.processing ? $t('Sending...') : (isEditing ? $t('Save Review') : $t('Submit Review')) }}
          </button>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.animate-in { animation: fadeIn 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
</style>
