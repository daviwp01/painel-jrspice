<script setup>
import { computed } from 'vue';
import { formatDate } from '@/Utils/contracts';
import {
  MessageSquare, Info, AlertTriangle, Video, FileText, Truck,
  CheckCircle2, Clock, Calendar, ArrowRight
} from 'lucide-vue-next';

const props = defineProps({
  process: Object,
});

const timelineEntries = computed(() => {
  const list = [];
  const p = props.process;
  if (!p) return [];

  // 1. Incident alert
  if (p.incident) {
    list.push({
      id: 'incident',
      title: 'Incident Reported by the Platform',
      type: 'incident',
      badge: 'Logistics Alert',
      badgeStyle: 'bg-rose-50 text-rose-700 border-rose-200',
      bulletStyle: 'bg-rose-500 border-rose-200 text-white',
      date: p.date,
      content: p.incident,
      hint: 'The JR Spice team is monitoring this point of attention.',
    });
  }

  // 2. Video request / inspection
  if (p.video_sent || p.video_date) {
    list.push({
      id: 'video',
      title: 'Cargo Inspection & Stuffing Video',
      type: 'video',
      badge: 'Video Inspection',
      badgeStyle: 'bg-purple-50 text-purple-700 border-purple-200',
      bulletStyle: 'bg-purple-600 border-purple-200 text-white',
      date: p.video_date || p.date,
      content: 'Video recording of the loading, stowing of the bags into the container and verification of the security seals.',
      hint: 'Video recorded and validated by quality control.',
    });
  }

  // 3. DHL Document dispatch
  if (p.dhl_number) {
    list.push({
      id: 'dhl',
      title: 'Physical Documents Dispatch (DHL)',
      type: 'dhl',
      badge: 'Documentation Requested/Sent',
      badgeStyle: 'bg-blue-50 text-blue-700 border-blue-200',
      bulletStyle: 'bg-blue-600 border-blue-200 text-white',
      date: p.dhl_date || p.date,
      content: 'The original export documents were dispatched via DHL under tracking code :code.',
      contentParams: { code: p.dhl_number },
      hint: 'You can follow the tracking directly on the DHL website.',
    });
  }

  // 4. Main observations from admin
  if (p.observations) {
    list.push({
      id: 'obs',
      title: 'Administrator Observations & Updates',
      type: 'observation',
      badge: 'Team Note',
      badgeStyle: 'bg-slate-100 text-slate-700 border-slate-200',
      bulletStyle: 'bg-slate-800 border-slate-200 text-white',
      date: p.date,
      content: p.observations,
      hint: null,
    });
  }

  // 5. Document Request Sample/Example Entry to showcase requested documents
  list.push({
    id: 'doc_request',
    title: 'Document Submission Request',
    type: 'doc_request',
    badge: 'Requested Document',
    badgeStyle: 'bg-sky-50 text-sky-700 border-sky-200',
    bulletStyle: 'bg-sky-500 border-sky-200 text-white',
    date: p.date,
    content: 'Please send the Payment Proof and Import License (LI) so we can proceed with issuing the Bill of Lading (B/L).',
    hint: 'Send the files by attaching them directly in the Details & Documentation tab.',
  });

  return list;
});
</script>

<template>
  <div class="animate-in fade-in duration-200 space-y-4">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-6 sm:p-8">
      
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-6">
        <div>
          <h2 class="text-xs font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2">
            <MessageSquare class="w-4 h-4 text-blue-600" />
            {{ $t('Process Observations & Incidents') }}
          </h2>
          <p class="text-xs font-medium text-slate-500 mt-1">
            {{ $t('Chronological history of communications, video/document requests and notes sent by the administrator.') }}
          </p>
        </div>
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 shrink-0">
          {{ $t(':count records', { count: timelineEntries.length }) }}
        </span>
      </div>

      <!-- Timeline List -->
      <div v-if="timelineEntries.length" class="relative pl-6 sm:pl-8 space-y-6 before:content-[''] before:absolute before:left-3 sm:before:left-4 before:top-3 before:bottom-3 before:w-[2px] before:bg-slate-200">
        
        <div
          v-for="item in timelineEntries"
          :key="item.id"
          class="relative group"
        >
          <!-- Bullet Icon -->
          <div
            class="absolute -left-6 sm:-left-8 top-1 w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all z-10 shadow-sm"
            :class="item.bulletStyle"
          >
            <AlertTriangle v-if="item.type === 'incident'" class="w-3 h-3" />
            <Video v-else-if="item.type === 'video'" class="w-3 h-3" />
            <Truck v-else-if="item.type === 'dhl'" class="w-3 h-3" />
            <FileText v-else-if="item.type === 'doc_request'" class="w-3 h-3" />
            <MessageSquare v-else class="w-3 h-3" />
          </div>

          <!-- Card Content -->
          <div class="bg-slate-50/70 hover:bg-slate-50 border border-slate-200/80 rounded-2xl p-4 sm:p-5 transition-all space-y-3">
            
            <!-- Card Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/60 pb-3">
              <div class="flex items-center gap-2.5">
                <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border uppercase tracking-wider" :class="item.badgeStyle">
                  {{ $t(item.badge) }}
                </span>
                <h3 class="text-xs sm:text-sm font-black text-slate-800">{{ $t(item.title) }}</h3>
              </div>

              <!-- Date Badge -->
              <span class="text-[10px] font-bold font-mono text-slate-500 bg-white border border-slate-200/90 rounded-lg px-2.5 py-1 flex items-center gap-1.5 shrink-0 max-w-fit">
                <Calendar class="w-3 h-3 text-slate-400" />
                {{ formatDate(item.date) }}
              </span>
            </div>

            <!-- Content text -->
            <p class="text-xs sm:text-sm font-semibold text-slate-600 leading-relaxed whitespace-pre-line">
              {{ item.contentParams ? $t(item.content, item.contentParams) : $t(item.content) }}
            </p>

            <!-- Action Hint -->
            <div v-if="item.hint" class="bg-white rounded-xl p-3 border border-slate-200/60 text-[11px] font-bold text-slate-500 flex items-center gap-2">
              <Info class="w-3.5 h-3.5 text-blue-500 shrink-0" />
              <span>{{ $t(item.hint) }}</span>
            </div>
          </div>

        </div>

      </div>

      <!-- Empty state -->
      <div v-else class="flex flex-col items-center gap-4 py-12 text-center">
        <div class="w-14 h-14 rounded-3xl bg-slate-100 flex items-center justify-center">
          <MessageSquare class="w-7 h-7 text-slate-300" />
        </div>
        <div>
          <p class="text-sm font-bold text-slate-500">{{ $t('No observations recorded') }}</p>
          <p class="text-xs text-slate-400 mt-1">{{ $t('When there are updates or requests from the administrator, they will appear here.') }}</p>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.animate-in { animation: fadeIn 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
@keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
</style>
