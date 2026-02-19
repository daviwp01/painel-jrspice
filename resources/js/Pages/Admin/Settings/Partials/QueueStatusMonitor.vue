<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Activity, Clock, AlertCircle, CheckCircle2, RefreshCw, Loader2 } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    initialStats: {
        type: Object,
        default: () => ({
            pending: 0,
            failed: 0,
            is_running: false
        })
    }
});

const stats = ref(props.initialStats);
const loading = ref(false);
let interval = null;

const fetchStatus = async () => {
    try {
        const response = await axios.get(route('admin.settings.queue-status'));
        stats.value = response.data;
    } catch (error) {
        console.error('Failed to fetch queue status', error);
    }
};

const refresh = async () => {
    if (loading.value) return;
    loading.value = true;
    // Add a small artificial delay to ensure the user sees the loading state
    try {
        const [response] = await Promise.all([
            axios.get(route('admin.settings.queue-status')),
            new Promise(resolve => setTimeout(resolve, 600))
        ]);
        stats.value = response.data;
    } catch (error) {
        console.error('Failed to fetch queue status', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchStatus();
    // Refresh every 30 seconds
    interval = setInterval(fetchStatus, 30000);
});

onUnmounted(() => {
    if (interval) clearInterval(interval);
});
</script>

<template>
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden p-6 mb-8">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center space-x-4">
                <div class="p-3 rounded-xl transition-colors duration-500"
                     :class="stats.is_running ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'">
                    <Activity class="w-6 h-6" />
                </div>
                <div>
                    <div class="flex items-center space-x-2">
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">{{ $t('Email Queue Status') }}</h3>
                        <span class="flex h-2 w-2 rounded-full" :class="stats.is_running ? 'bg-emerald-500' : 'bg-red-500'"></span>
                    </div>
                    <p class="text-[10px] font-bold uppercase tracking-widest" :class="stats.is_running ? 'text-emerald-500' : 'text-red-500'">
                        {{ stats.is_running ? $t('System Active & Running') : $t('Service Offline or Starting...') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <!-- Pending -->
                <div class="px-4 py-2 bg-slate-50 rounded-xl border border-slate-100 flex items-center space-x-3">
                    <Clock class="w-4 h-4 text-amber-500" />
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-tighter leading-none mb-1">{{ $t('Pending') }}</p>
                        <p class="text-sm font-black text-slate-700 leading-none">{{ stats.pending }}</p>
                    </div>
                </div>

                <!-- Failed -->
                <div class="px-4 py-2 bg-slate-50 rounded-xl border border-slate-100 flex items-center space-x-3">
                    <AlertCircle class="w-4 h-4 text-red-500" />
                    <div>
                        <p class="text-[9px] font-black text-slate-400 uppercase tracking-tighter leading-none mb-1">{{ $t('Failed') }}</p>
                        <p class="text-sm font-black text-slate-700 leading-none">{{ stats.failed }}</p>
                    </div>
                </div>

                <button
                    @click="refresh"
                    :disabled="loading"
                    class="p-2 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed group relative"
                    :title="$t('Refresh Status')"
                >
                    <Loader2 v-if="loading" class="w-5 h-5 animate-spin text-indigo-600" />
                    <RefreshCw v-else class="w-5 h-5 transition-transform group-hover:rotate-180 duration-500" />
                </button>
            </div>
        </div>

        <!-- Warning message if offline -->
        <div v-if="!stats.is_running" class="mt-4 p-3 bg-red-50 rounded-lg border border-red-100 flex items-center space-x-2">
            <AlertCircle class="w-4 h-4 text-red-500" />
            <p class="text-[10px] text-red-700 font-bold uppercase tracking-tight">
                {{ $t('Warning: The email delivery service is not detected. Please check if Docker containers are running.') }}
            </p>
        </div>
    </div>
</template>
