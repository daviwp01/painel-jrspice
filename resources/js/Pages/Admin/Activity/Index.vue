<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Users, Clock, Mail, MousePointer2, Circle, ShieldCheck, Activity, UserCheck, Trash2, Timer, Eye, X, BarChart2, History, AlertTriangle, HelpCircle } from 'lucide-vue-next';
import Pagination from '@/Components/Pagination.vue';
import { ref } from 'vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import axios from 'axios';

defineProps({ users: Object, total_users: Number, online_users: Number });

// ─── Confirm Clear ───────────────────────────────────────────
const isClearing = ref(false);
const showConfirmClear = ref(false);
const clearActivities = () => {
    isClearing.value = true;
    router.post(route('admin.activity.clear'), {}, {
        preserveScroll: true,
        onSuccess: () => { showConfirmClear.value = false; },
        onFinish: () => { isClearing.value = false; }
    });
};

// ─── Modal state ────────────────────────────────────────────
const showModal      = ref(false);
const modalUser      = ref(null);
const activeTab      = ref('sessions');
const sessions       = ref([]);
const searchStats    = ref(null);
const loadingSess    = ref(false);
const loadingSearch  = ref(false);
const clearingUser   = ref(false);
const confirmClearUser = ref(false);



const openModal = async (user) => {
    modalUser.value    = user;
    activeTab.value    = 'sessions';
    sessions.value     = [];
    searchStats.value  = null;
    confirmClearUser.value = false;
    showModal.value    = true;
    await fetchSessions();
};

const closeModal = () => {
    showModal.value = false;
    confirmClearUser.value = false;
};

const fetchSessions = async () => {
    loadingSess.value = true;
    try {
        const { data } = await axios.get(route('admin.activity.sessions', modalUser.value.id));
        sessions.value = data.data || [];
    } finally {
        loadingSess.value = false;
    }
};

const fetchSearchStats = async () => {
    if (searchStats.value !== null) return; // already loaded
    loadingSearch.value = true;
    try {
        const { data } = await axios.get(route('admin.activity.search-stats', modalUser.value.id));
        searchStats.value = data;
    } finally {
        loadingSearch.value = false;
    }
};

const switchTab = (tab) => {
    activeTab.value = tab;
    if (tab === 'searches' && searchStats.value === null) {
        fetchSearchStats();
    }
};

const clearUserData = async () => {
    clearingUser.value = true;
    try {
        await axios.delete(route('admin.activity.user.clear', modalUser.value.id));
        sessions.value     = [];
        searchStats.value  = { total_searches: 0, by_type: [] };
        if (modalUser.value) {
            modalUser.value.total_sessions = 0;
            modalUser.value.total_time     = '0min';
            modalUser.value.avg_time       = '0min';
        }
    } finally {
        clearingUser.value     = false;
        confirmClearUser.value = false;
    }
};

// ─── Interest scoring helpers ─────────────────────────────
// Relative bar width: the top item in a group fills 100%, others proportional
const relativeBar = (count, maxCount) => maxCount > 0 ? Math.round((count / maxCount) * 100) : 0;

// Interest level config — label, bar color, text color, tooltip
const INTEREST_LEVELS = [
    {
        label:   'Destaque',
        bar:     'bg-emerald-500',
        text:    'text-emerald-700',
        weight:  'font-black',
        nameWt:  'font-black text-slate-800',
        tooltip: 'Item mais pesquisado desta categoria. Indica que o utilizador tem alto interesse neste filtro.',
    },
    {
        label:   'Relevante',
        bar:     'bg-emerald-300',
        text:    'text-emerald-600',
        weight:  'font-bold',
        nameWt:  'font-bold text-slate-600',
        tooltip: 'Pesquisado com frequência moderada. O utilizador demonstra interesse consistente.',
    },
    {
        label:   'Pontual',
        bar:     'bg-emerald-100',
        text:    'text-slate-400',
        weight:  'font-medium',
        nameWt:  'font-medium text-slate-400',
        tooltip: 'Pesquisado raramente. Interesse exploratório ou ocasional.',
    },
];

const interestLevel = (rank, count) => {
    if (!count || count <= 1) return INTEREST_LEVELS[2]; // Pontual
    return INTEREST_LEVELS[Math.min(rank - 1, INTEREST_LEVELS.length - 1)];
};

// Context-aware tooltip: mentions the specific subject (country, product, etc.)
const SUBJECT_MAP = {
    country:    'país',
    product:    'produto',
    supplier:   'fornecedor',
    date_range: 'período',
};
const interestTooltip = (rank, groupType, count) => {
    if (!count || count <= 1) return 'Filtro utilizado apenas uma vez. Interesse pontual ou exploratório.';

    if (groupType === 'country') {
        if (rank === 1) return 'País com maior volume de buscas. Indica um alto interesse em produtos desta região.';
        if (rank === 2) return 'País com buscas frequentes. Indica interesse relevante em produtos desta origem.';
        return 'País com buscas esporádicas. Interesse pontual em produtos desta região.';
    }

    const s = SUBJECT_MAP[groupType] || 'filtro';
    const S = s.charAt(0).toUpperCase() + s.slice(1);
    if (rank === 1) return `${S} com maior engajamento. Indica preferência principal do utilizador neste critério.`;
    if (rank === 2) return `${S} com buscas frequentes. Demonstra interesse consistente e relevante.`;
    return `${S} com buscas pontuais. Interesse ocasional ou exploratório.`;
};
</script>

<template>
    <Head :title="$t('User Access Control')" />
    <DashboardLayout>
        <template #header>
            <h2 class="hidden md:block text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $t('User Access Control') }}</h2>
        </template>

        <div class="p-6 md:p-8 space-y-8 w-full max-w-none">

            <!-- Header Row -->
            <div class="flex flex-wrap items-center gap-4">
                <div class="flex items-center space-x-4 bg-white p-2 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex items-center px-4 py-2 border-r border-slate-100">
                        <Users class="w-4 h-4 text-indigo-500 mr-2" />
                        <span class="text-sm font-bold text-slate-800">{{ total_users }}</span>
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1.5 mt-0.5">Total</span>
                    </div>
                    <div class="flex items-center px-4 py-2">
                        <UserCheck class="w-4 h-4 text-emerald-500 mr-2" />
                        <span class="text-sm font-bold text-slate-800">{{ online_users }}</span>
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest ml-1.5 mt-0.5">Online</span>
                    </div>
                </div>
                <button @click="showConfirmClear = true" :disabled="isClearing"
                    class="flex items-center space-x-2 px-6 py-3.5 bg-white hover:bg-rose-50 border border-slate-200 hover:border-rose-100 rounded-xl transition-all duration-300 group shadow-sm disabled:opacity-50">
                    <Trash2 class="w-4 h-4 text-slate-400 group-hover:text-rose-600 transition-colors" />
                    <span class="text-xs font-bold text-slate-600 group-hover:text-rose-700 uppercase tracking-wider">{{ $t('Clear Activities') }}</span>
                </button>
            </div>

            <!-- Desktop Table -->
            <div class="hidden lg:block bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden hover:shadow-md transition-all">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead>
                            <tr class="bg-slate-50/50">
                                <th class="px-8 py-6 text-left text-xs font-bold text-slate-400 uppercase tracking-[0.2em]">{{ $t('User') }}</th>
                                <th class="px-8 py-6 text-center text-xs font-bold text-slate-400 uppercase tracking-[0.2em]">{{ $t('Status') }}</th>
                                <!-- <th class="px-8 py-6 text-left text-xs font-bold text-slate-400 uppercase tracking-[0.2em]">{{ $t('Last Login') }}</th> -->
                                <!-- <th class="px-8 py-6 text-left text-xs font-bold text-slate-400 uppercase tracking-[0.2em]">{{ $t('Last Activity') }}</th> -->
                                <th class="px-8 py-6 text-center text-xs font-bold text-slate-400 uppercase tracking-[0.2em]">{{ $t('Time on Platform') }}</th>
                                <th class="px-8 py-6 text-center text-xs font-bold text-slate-400 uppercase tracking-[0.2em]">{{ $t('Email Notification') }}</th>
                                <th class="px-8 py-6 text-center text-xs font-bold text-slate-400 uppercase tracking-[0.2em]">{{ $t('Email Interaction') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-indigo-50/20 group transition-all duration-300">
                                <td class="px-8 py-5 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-12 w-12 rounded-lg border bg-indigo-50 text-indigo-600 border-indigo-100 flex items-center justify-center font-bold text-sm shadow-sm capitalize group-hover:scale-110 transition-transform">
                                            {{ user.name.charAt(0) }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="flex items-center space-x-1.5">
                                                <span class="text-sm font-bold text-slate-800 group-hover:text-indigo-600 transition-colors">{{ user.name }}</span>
                                                <ShieldCheck v-if="user.is_master" class="w-3.5 h-3.5 text-indigo-500" />
                                            </div>
                                            <div class="text-[11px] text-slate-400 font-bold uppercase tracking-tight">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-8 py-5 whitespace-nowrap text-center">
                                    <div class="inline-flex items-center px-4 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm"
                                         :class="user.is_online ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-50 text-slate-400 border border-slate-100'">
                                        <Circle class="w-2 h-2 mr-2 fill-current" />{{ user.is_online ? $t('Online') : $t('Offline') }}
                                    </div>
                                </td>
                                <!-- <td class="px-8 py-5 whitespace-nowrap">
                                    <div class="flex items-center text-slate-600 bg-slate-50/50 px-3 py-2 rounded-xl border border-slate-100 w-fit">
                                        <Clock class="w-4 h-4 mr-2 text-slate-300" />
                                        <span class="text-xs font-bold uppercase tracking-tighter">{{ user.last_login }}</span>
                                    </div>
                                </td>
                                <td class="px-8 py-5 whitespace-nowrap">
                                    <div class="flex items-center bg-slate-50/50 px-3 py-2 rounded-xl border border-slate-100 w-fit group-hover:bg-indigo-50 group-hover:border-indigo-100 transition-colors">
                                        <MousePointer2 class="w-4 h-4 mr-2 text-slate-400 group-hover:text-indigo-500" />
                                        <span class="text-xs font-bold uppercase tracking-tighter text-slate-800">{{ user.last_activity }}</span>
                                    </div>
                                </td> -->
                                <!-- Time on Platform -->
                                <td class="px-8 py-5 whitespace-nowrap text-center">
                                    <button @click="openModal(user)"
                                        class="inline-flex items-center space-x-2 bg-violet-50 text-violet-700 px-4 py-1.5 rounded-xl border border-violet-100 shadow-sm hover:bg-violet-100 hover:border-violet-200 transition-all group/btn">
                                        <Timer class="w-3.5 h-3.5" />
                                        <div class="flex flex-col items-center">
                                            <span class="text-[11px] font-bold uppercase tracking-wider">{{ user.total_time || '0min' }}</span>
                                            <span class="text-[9px] font-bold opacity-60 leading-none mt-0.5">{{ user.total_sessions }} {{ user.total_sessions === 1 ? 'sessão' : 'sessões' }}</span>
                                        </div>
                                        <Eye class="w-3 h-3 opacity-0 group-hover/btn:opacity-70 transition-opacity" />
                                    </button>
                                </td>
                                <td class="px-8 py-5 whitespace-nowrap text-center">
                                    <div v-if="user.notified_at" class="inline-flex items-center space-x-2 bg-indigo-50 text-indigo-600 px-4 py-1.5 rounded-xl border border-indigo-100 shadow-sm">
                                        <Mail class="w-3.5 h-3.5" /><span class="text-[10px] font-bold uppercase tracking-wider">{{ user.notified_at }}</span>
                                    </div>
                                    <div v-else class="text-[10px] font-bold text-slate-300 uppercase tracking-[0.2em] italic">---</div>
                                </td>
                                <td class="px-8 py-5 whitespace-nowrap text-center">
                                    <div v-if="user.clicked_at" class="inline-flex items-center space-x-2 bg-emerald-50 text-emerald-600 px-4 py-1.5 rounded-xl border border-emerald-100 shadow-sm">
                                        <Activity class="w-3.5 h-3.5" />
                                        <div class="flex flex-col items-center">
                                            <span class="text-[10px] font-bold uppercase tracking-wider">{{ $t('Opened Email') }}</span>
                                            <span class="text-[8px] font-bold opacity-70 leading-none mt-0.5">({{ user.clicked_at }})</span>
                                        </div>
                                    </div>
                                    <div v-else class="text-[10px] font-bold text-slate-300 uppercase tracking-[0.2em] italic">---</div>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="5" class="px-8 py-20 text-center">
                                    <Activity class="w-8 h-8 text-slate-300 mx-auto mb-3" />
                                    <h3 class="text-lg font-bold text-slate-800">{{ $t('No activity found') }}</h3>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Cards -->
            <div class="lg:hidden space-y-3">
                <div v-for="user in users.data" :key="'m-'+user.id" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-4 border-b border-slate-50">
                        <div class="flex items-center min-w-0 flex-1">
                            <div class="h-10 w-10 rounded-lg border bg-indigo-50 text-indigo-600 border-indigo-100 flex-shrink-0 flex items-center justify-center font-bold text-sm capitalize">{{ user.name.charAt(0) }}</div>
                            <div class="ml-3 min-w-0">
                                <div class="flex items-center space-x-1.5"><span class="text-sm font-bold text-slate-800 truncate">{{ user.name }}</span><ShieldCheck v-if="user.is_master" class="w-3.5 h-3.5 text-indigo-500 flex-shrink-0" /></div>
                                <div class="text-[10px] text-slate-400 font-bold uppercase truncate">{{ user.email }}</div>
                            </div>
                        </div>
                        <div class="inline-flex items-center px-2.5 py-1 rounded-lg text-[9px] font-bold uppercase tracking-wider ml-3"
                             :class="user.is_online ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-50 text-slate-400 border border-slate-100'">
                            <Circle class="w-1.5 h-1.5 mr-1.5 fill-current" />{{ user.is_online ? $t('Online') : $t('Offline') }}
                        </div>
                    </div>
                    <div class="px-4 py-3 space-y-2">
                                <!-- <div class="flex items-center justify-between">
                                    <span class="text-[11px] font-bold uppercase tracking-widest text-slate-400">{{ $t('Last Login') }}</span>
                                    <div class="flex items-center text-slate-600 bg-slate-50/50 px-2.5 py-1 rounded-lg border border-slate-100"><Clock class="w-3 h-3 mr-1.5 text-slate-300" /><span class="text-[11px] font-bold uppercase tracking-tighter">{{ user.last_login }}</span></div>
                                </div> -->
                                <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $t('Time on Platform') }}</span>
                            <button @click="openModal(user)" class="flex items-center text-violet-700 bg-violet-50 px-2.5 py-1 rounded-lg border border-violet-100 hover:bg-violet-100 transition-colors">
                                <Timer class="w-3 h-3 mr-1.5" /><span class="text-[11px] font-bold uppercase tracking-tighter">{{ user.total_time || '0min' }}</span><Eye class="w-3 h-3 ml-1.5 opacity-60" />
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="users.data.length === 0" class="bg-white rounded-xl border border-slate-200 px-6 py-16 text-center">
                    <Activity class="w-7 h-7 text-slate-300 mx-auto mb-3" /><h3 class="text-lg font-bold text-slate-800">{{ $t('No activity found') }}</h3>
                </div>
            </div>

            <div class="mt-8 flex justify-center"><Pagination :links="users.links" /></div>
        </div>

        <!-- ═══ DETAIL MODAL ═══ -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-slate-900/60 transition-opacity" @click="closeModal"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl border border-slate-200/80 w-full max-w-5xl max-h-[84vh] flex flex-col z-10 overflow-hidden">

                        <!-- Modal Header -->
                        <div class="flex items-center justify-between px-8 py-6 border-b border-slate-100 bg-white flex-shrink-0">
                            <div class="flex items-center space-x-4">
                                <div class="h-10 w-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center font-black text-base capitalize">
                                    {{ modalUser?.name?.charAt(0) }}
                                </div>
                                <div>
                                    <h3 class="text-base font-black text-slate-900 tracking-tight">{{ modalUser?.name }}</h3>
                                    <p class="text-[11px] text-slate-400 mt-0.5">
                                        {{ modalUser?.total_sessions }} sess{{ modalUser?.total_sessions === 1 ? 'ão' : 'ões' }}
                                        &nbsp;·&nbsp; Média {{ modalUser?.avg_time || '0min' }}
                                        &nbsp;·&nbsp; Total {{ modalUser?.total_time || '0min' }}
                                    </p>
                                </div>
                            </div>
                            <!-- Actions: Clear + Close -->
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <template v-if="confirmClearUser">
                                    <span class="text-[11px] text-slate-500 hidden sm:inline">Confirmar limpeza?</span>
                                    <button @click="clearUserData" :disabled="clearingUser"
                                        class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-900 hover:bg-slate-700 text-white rounded-lg text-[10px] font-bold uppercase tracking-wider transition-colors disabled:opacity-40">
                                        <span v-if="clearingUser" class="animate-spin w-3 h-3 border border-white border-t-transparent rounded-full"></span>
                                        <AlertTriangle v-else class="w-3 h-3" />
                                        Limpar
                                    </button>
                                    <button @click="confirmClearUser = false"
                                        class="px-3 py-1.5 text-slate-500 hover:text-slate-800 text-[10px] font-bold uppercase tracking-wider transition-colors">
                                        Cancelar
                                    </button>
                                </template>
                                <template v-else>
                                    <button @click="confirmClearUser = true"
                                        class="flex items-center gap-1.5 px-3 py-1.5 text-slate-400 hover:text-slate-700 text-[10px] font-bold uppercase tracking-wider transition-colors">
                                        <Trash2 class="w-3.5 h-3.5" />
                                        <span class="hidden sm:inline">Limpar dados</span>
                                    </button>
                                </template>
                                <div class="w-px h-5 bg-slate-200 mx-1"></div>
                                <button @click="closeModal" class="p-1.5 hover:bg-slate-100 rounded-lg transition-colors">
                                    <X class="w-4 h-4 text-slate-400" />
                                </button>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <div class="flex border-b border-slate-100 bg-white flex-shrink-0 px-8">
                            <button @click="switchTab('sessions')"
                                class="flex items-center gap-2 py-4 pr-8 text-[11px] font-bold uppercase tracking-widest transition-all border-b-2"
                                :class="activeTab === 'sessions' ? 'text-slate-900 border-slate-900' : 'text-slate-400 border-transparent hover:text-slate-600'">
                                <History class="w-3.5 h-3.5" /> {{ $t('Session History') }}
                            </button>
                            <button @click="switchTab('searches')"
                                class="flex items-center gap-2 py-4 pr-8 text-[11px] font-bold uppercase tracking-widest transition-all border-b-2"
                                :class="activeTab === 'searches' ? 'text-slate-900 border-slate-900' : 'text-slate-400 border-transparent hover:text-slate-600'">
                                <BarChart2 class="w-3.5 h-3.5" /> Comportamento de Busca
                            </button>
                        </div>

                        <!-- Tab: SESSIONS -->
                        <div v-show="activeTab === 'sessions'" class="overflow-y-auto flex-1">
                            <div v-if="loadingSess" class="flex items-center justify-center py-16">
                                <div class="animate-spin w-5 h-5 border-2 border-slate-400 border-t-transparent rounded-full"></div>
                            </div>
                            <div v-else-if="sessions.length === 0" class="py-16 text-center">
                                <Timer class="w-7 h-7 text-slate-300 mx-auto mb-3" />
                                <p class="text-sm text-slate-400">{{ $t('No sessions recorded') }}</p>
                            </div>
                            <table v-else class="w-full text-sm">
                                <thead class="sticky top-0 bg-white border-b border-slate-100 z-10">
                                    <tr>
                                        <th class="px-8 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.15em]">{{ $t('Start') }}</th>
                                        <th class="px-8 py-4 text-left text-[10px] font-bold text-slate-400 uppercase tracking-[0.15em]">{{ $t('End') }}</th>
                                        <th class="px-8 py-4 text-right text-[10px] font-bold text-slate-400 uppercase tracking-[0.15em]">{{ $t('Duration') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50">
                                    <tr v-for="s in sessions" :key="s.id" class="hover:bg-slate-50/60 transition-colors">
                                        <td class="px-8 py-4"><span class="text-[13px] text-slate-700 font-mono">{{ s.started_at }}</span></td>
                                        <td class="px-8 py-4">
                                            <span v-if="s.is_active" class="inline-flex items-center gap-1.5 text-[11px] font-bold text-emerald-600 uppercase tracking-wider">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span> Ativo
                                            </span>
                                            <span v-else class="text-[13px] text-slate-500 font-mono">{{ s.ended_at }}</span>
                                        </td>
                                        <td class="px-8 py-4 text-right">
                                            <span class="text-[13px] font-bold text-slate-700 tabular-nums">{{ s.duration }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Tab: SEARCH BEHAVIOUR -->
                        <div v-show="activeTab === 'searches'" class="overflow-y-auto flex-1 px-8 py-6 space-y-6">
                            <div v-if="loadingSearch" class="flex items-center justify-center py-16">
                                <div class="animate-spin w-5 h-5 border-2 border-slate-400 border-t-transparent rounded-full"></div>
                            </div>
                            <div v-else-if="!searchStats || searchStats.total_searches === 0" class="py-16 text-center">
                                <BarChart2 class="w-7 h-7 text-slate-300 mx-auto mb-3" />
                                <p class="text-sm text-slate-400">Nenhuma busca registrada</p>
                                <p class="text-[11px] text-slate-300 mt-1">Os filtros aplicados no dashboard aparecerão aqui.</p>
                            </div>
                            <template v-else>
                                <div v-for="(group, gi) in searchStats.by_type" :key="group.type">
                                    <!-- Section label -->
                                    <div class="flex items-center justify-between mb-4">
                                        <span class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-400">{{ group.label }}</span>
                                        <span class="text-[11px] text-slate-300 tabular-nums">{{ group.total_hits }} interaç{{ group.total_hits === 1 ? 'ão' : 'ões' }}</span>
                                    </div>
                                    <!-- Items -->
                                    <div class="space-y-4">
                                        <div v-for="(item, idx) in group.items" :key="item.value" class="flex items-center gap-5">
                                            <!-- Rank -->
                                            <span class="flex-shrink-0 w-4 text-[11px] tabular-nums text-right"
                                                  :class="idx === 0 ? 'font-black text-slate-700' : 'font-medium text-slate-300'">
                                                {{ idx + 1 }}
                                            </span>

                                            <!-- Name + bar -->
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-baseline justify-between mb-2">
                                                    <span class="text-[13px] truncate" :class="interestLevel(idx + 1, item.count).nameWt">{{ item.value }}</span>
                                                    <span class="flex-shrink-0 ml-4 text-[11px] tabular-nums text-slate-400">{{ item.count }}×</span>
                                                </div>
                                                <div class="h-[3px] bg-slate-100 rounded-full overflow-hidden">
                                                    <div class="h-full rounded-full transition-all duration-700"
                                                         :class="interestLevel(idx + 1, item.count).bar"
                                                         :style="{ width: relativeBar(item.count, group.items[0].count) + '%' }">
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Interest label + tooltip (LEFT-side to avoid overflow clip) -->
                                            <div class="flex-shrink-0 flex items-center gap-1 w-24 justify-end group/tip relative">
                                                <span class="text-[11px] uppercase tracking-wider"
                                                      :class="[interestLevel(idx + 1, item.count).weight, interestLevel(idx + 1, item.count).text]">
                                                    {{ interestLevel(idx + 1, item.count).label }}
                                                </span>
                                                <HelpCircle class="w-3 h-3 text-slate-300 hover:text-slate-500 cursor-help flex-shrink-0 transition-colors" />
                                                <!-- Tooltip: appears to the LEFT, escapes scroll container -->
                                                <div class="absolute right-full top-1/2 -translate-y-1/2 mr-3 w-52 bg-slate-900 text-white text-[10px] leading-relaxed px-3 py-2.5 rounded-lg shadow-xl opacity-0 group-hover/tip:opacity-100 transition-opacity duration-200 pointer-events-none z-50 whitespace-normal">
                                                    {{ interestTooltip(idx + 1, group.type, item.count) }}
                                                    <!-- Arrow pointing RIGHT -->
                                                    <div class="absolute left-full top-1/2 -translate-y-1/2 border-4 border-transparent border-l-slate-900"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="gi < searchStats.by_type.length - 1" class="mt-6 border-t border-slate-100"></div>
                                </div>
                            </template>
                        </div>

                    </div>
                </div>
            </Transition>
        </Teleport>

        <ConfirmationModal
            :show="showConfirmClear"
            :title="$t('Clear Activities')"
            :message="$t('Are you sure you want to clear all user activities? This will reset all engagement data for all users.')"
            :confirm-text="$t('Clear')"
            :cancel-text="$t('Cancel')"
            :loading="isClearing"
            @close="showConfirmClear = false"
            @confirm="clearActivities"
        />
    </DashboardLayout>
</template>

<style scoped>
.modal-enter-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.modal-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .relative { transform: scale(0.97) translateY(8px); }
</style>
