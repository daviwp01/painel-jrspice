<script setup>
import { onMounted, ref, watch, computed } from 'vue';
import * as pbi from 'powerbi-client';
import { BarChart3, Mail, LayoutDashboard, Loader2, Clock, Layout, RefreshCw } from 'lucide-vue-next';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';

/**
 * 🎨 PREMIUM UI CONFIGURATION
 * Focus: Visual excellence, clean code, and sophisticated navigation.
 */

const props = defineProps({
    title: { type: String, default: 'Analytics' },
    embedConfig: { type: Object, default: null },
    hiddenPages: { type: Array, default: () => [] }
});

const user = computed(() => usePage().props.auth.user);
const reportContainer = ref(null);
const isLoading = ref(true);
const pages = ref([]);
const activePage = ref('system_home');
const pendingPage = ref(null);
const isMounted = ref(false);
const isRefreshing = ref(false);
let report = null;

const filteredPages = computed(() => {
    let list = pages.value.filter(page => {
        const displayName = (page.displayName || '').toUpperCase();
        // HIDE HOME PAGE from the list (it is accessed via Overview button)
        if (displayName.includes('HOME')) return false;

        // PERMISSION CHECK
        if (!user.value.is_master) {
            const allowed = user.value.allowed_pages || [];
            if (!allowed.includes(page.name)) return false;
        }

        return true;
    });

    // Move CONTACT to the bottom
    return list.sort((a, b) => {
        const nameA = (a.displayName || '').toUpperCase();
        const nameB = (b.displayName || '').toUpperCase();
        const isContactA = nameA.includes('CONTACT') || nameA.includes('CONTATO');
        const isContactB = nameB.includes('CONTACT') || nameB.includes('CONTATO');

        if (isContactA && !isContactB) return 1;
        if (!isContactA && isContactB) return -1;
        return 0; // Maintain original order otherwise
    });
});

/**
 * 🧹 CLEANER ENGINE
 * Silent visual suprosel for targeted report-level navigation bits.
 */
const silentCleanup = async (container, pageRef = null) => {
    if (!container || typeof container.getVisuals !== 'function') return;
    const parentPage = pageRef || container;

    try {
        const visuals = await container.getVisuals();
        // Specifically identified stubborn IDs and standard navigation visual types
        const blacklist = ['497d142a01df05858d6f', '0fb5255603cea72883f1', '7194e24c2d6bd7bb4db4'];

        for (const v of visuals) {
            if (blacklist.includes(v.name) || ['actionButton', 'pageNavigator'].includes(v.type)) {
                try {
                    if (parentPage?.updateVisualSettings) {
                        await parentPage.updateVisualSettings(v.name, {
                            displayState: { mode: 1 },
                            x: -30000, y: -30000,
                            width: 0, height: 0
                        });
                    }
                    if (v.updateSettings) await v.updateSettings({ visible: false });
                } catch (err) {}
            } else if (v.type === 'group') {
                await silentCleanup(v, parentPage);
            }
        }
    } catch (e) {}
};

const embedReport = async () => {
    if (!props.embedConfig || !reportContainer.value) return;

    try {
        const powerbi = new pbi.service.Service(pbi.factories.hpmFactory, pbi.factories.wpmpFactory, pbi.factories.routerFactory);

        const config = {
            type: 'report',
            tokenType: 1,
            accessToken: props.embedConfig.accessToken,
            embedUrl: props.embedConfig.embedUrl,
            id: props.embedConfig.id,
            settings: {
                panes: { filters: { visible: false }, pageNavigation: { visible: false } },
                bars: { actionBar: { visible: false } },
                navContentPaneEnabled: false,
                layoutType: pbi.models.LayoutType.Master,
                displayOption: pbi.models.DisplayOption.FitToWidth,
                visualHeaders: [{ settings: { visible: false } }]
            }
        };

        report = powerbi.embed(reportContainer.value, config);

        report.on('loaded', async () => {
             // 1. If fast REST API failed or is slow, use SDK pages as fallback
             if (!pages.value || pages.value.length === 0) {
                 try {
                     pages.value = await report.getPages();
                 } catch (e) { console.error("SDK getPages failed", e); }
             }

             // 2. Initial Page Check (Force System Home if default is HOME)
             const active = await report.getActivePage();
             if (active && (active.displayName || '').toUpperCase().includes('HOME')) {
                 activePage.value = 'system_home';
             }

             // 3. Process any pending navigation
             if (pendingPage.value) {
                 setActivePage(pendingPage.value);
                 pendingPage.value = null;
             }
        });

        report.on('rendered', async () => {
            isLoading.value = false;
            const active = await report.getActivePage();
            if (active) {
                // EXTREME PROTECTION: If rendered on HOME, bounce to system home
                const displayName = (active.displayName || '').toUpperCase();
                if (displayName.includes('HOME')) {
                    activePage.value = 'system_home';
                }
                silentCleanup(active);
            }
        });

        // 🎯 SMART REDIRECTION
        // Intercepts any click on report-level HOME visuals to bounce back to app HOME
        report.on('pageChanged', async (event) => {
            const newPage = event.detail.newPage;
            const displayName = (newPage.displayName || '').toUpperCase();

            // ALWAYS intercept HOME and go to our dashboard
            if (displayName.includes('HOME')) {
                activePage.value = 'system_home';
            } else if (activePage.value !== 'system_home') {
                // Only update active page if we're already viewing a report page.
                // This prevents the report's initial loading from hijacking the "System Home" view.
                activePage.value = newPage.name;
                await silentCleanup(newPage);
            }
        });

    } catch (error) {
        console.error('PowerBI: Connection Error', error);
    }
};

const goToSystemHome = () => { activePage.value = 'system_home'; };

const setActivePage = async (page) => {
    if (!report) {
        // Queue navigation for when the report is ready
        pendingPage.value = page;
        activePage.value = page.name; // Visual feedback
        isLoading.value = true;
        return;
    }

    try {
        isLoading.value = true;

        // Use the SDK's setPage method which accepts the unique page name (ReportSection...)
        await report.setPage(page.name);

        // Component state will be updated by the 'pageChanged' event listener automatically,
        // but we set it here as well for immediate UI feedback.
        activePage.value = page.name;

        isLoading.value = false;
    } catch (e) {
        console.error("Navigation error:", e);
        isLoading.value = false;
    }
};

const getPageIcon = (displayName) => {
    const name = (displayName || '').toUpperCase();

    // Contact / Support
    if (name.includes('CONTACT') || name.includes('CONTATO')) {
        return Mail;
    }

    // Default Analytics / Charts (Historico, Detalhes, Tabelas, Export, etc)
    return BarChart3;
};

const refreshDashboard = () => {
    if (isRefreshing.value) return;

    isRefreshing.value = true;
    isLoading.value = true;

    router.visit(route('dashboard'), {
        data: { refresh: 1 },
        preserveScroll: true,
        only: ['embedConfig', 'flash'],
        onFinish: () => {
            isRefreshing.value = false;
            isLoading.value = false;
        }
    });
};

onMounted(async () => {
    isMounted.value = true;
    embedReport();

    // 🚀 NEW: FAST PAGE LOADING
    // Fetch pages from REST API immediately instead of waiting for heavy SDK embed
    try {
        const response = await axios.get(route('powerbi.pages'));
        pages.value = response.data;
    } catch (e) {
        console.error("Failed to fetch pages via REST API", e);
    }
});

watch(() => props.embedConfig, (newConfig) => {
    if (newConfig) {
        embedReport();
    }
});
</script>

<template>
    <div class="w-full h-full flex flex-col bg-white overflow-hidden">

        <!-- 🚀 CONTENT AREA -->
        <main class="flex-1 relative flex flex-col overflow-hidden bg-slate-50">

            <!-- 🧭 TOP NAVIGATION BAR (Visible only in Report Mode) -->
            <transition name="fade-slide">
                <div v-if="activePage !== 'system_home'" class="w-full bg-white border-b border-slate-200 shadow-sm z-30 flex items-center px-4 py-3 space-x-4">

                    <!-- Back to Home -->
                    <button
                        @click="goToSystemHome"
                        class="flex items-center px-3 py-2 rounded-lg text-slate-500 hover:bg-slate-50 hover:text-blue-700 transition-all font-bold text-sm mr-2 group border border-transparent hover:border-slate-200"
                        :title="$t('Voltar para Visão Geral')"
                    >
                        <LayoutDashboard class="w-5 h-5 mr-2 text-slate-400 group-hover:text-blue-600 transition-colors" />
                        <span class="hidden md:inline">{{ $t('Overview') }}</span>
                    </button>

                    <div class="h-8 w-px bg-slate-200 mx-2"></div>

                    <!-- Horizontal Scrollable Tabs (Segmented Control Style) -->
                    <div class="flex-1 flex items-center overflow-x-auto no-scrollbar mask-gradient py-1">
                        <div class="flex items-center space-x-1 bg-slate-100/80 p-1.5 rounded-xl border border-slate-200/60">
                            <button
                                v-for="page in filteredPages"
                                :key="page.name"
                                @click="setActivePage(page)"
                                class="flex items-center px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-all duration-200"
                                :class="activePage === page.name
                                    ? 'bg-white text-blue-700 shadow-sm ring-1 ring-black/5 scale-[1.02]'
                                    : 'text-slate-500 hover:text-slate-900 hover:bg-white/50'"
                            >
                                <component :is="getPageIcon(page.displayName)" class="w-4 h-4 mr-2" :class="activePage === page.name ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-600'" />
                                {{ page.displayName.replace(/_/g, ' ') }}
                            </button>
                        </div>
                    </div>

                    <!-- Loading Indicator Link (Hidden when overlay is on) -->
                    <div v-if="isLoading && activePage !== 'system_home'" class="ml-4 flex items-center text-xs text-blue-600 font-bold">
                        <Loader2 class="w-4 h-4 animate-spin" />
                    </div>
                </div>
            </transition>

            <!-- 🏠 PREMIUM LANDING PAGE (SYSTEM HOME) -->
            <transition name="fade-slide">
                <div v-if="activePage === 'system_home'" class="absolute inset-0 z-20 bg-slate-50 overflow-y-auto">
                    <!-- Hero Banner - Rich Corporate Theme -->
                    <div class="bg-gradient-to-br from-[#0f172a] via-[#1e293b] to-[#0f172a] h-96 w-full relative flex flex-col justify-center overflow-hidden shadow-lg border-b border-slate-700/50">
                        <!-- Background Image Integration -->
                        <div class="absolute inset-0 z-0 pointer-events-none opacity-[0.12] mix-blend-overlay">
                            <img
                                src="https://images.unsplash.com/photo-1451187580459-43490279c0fa?q=80&w=2672&auto=format&fit=crop"
                                alt="Network Background"
                                class="w-full h-full object-cover"
                            />
                        </div>

                        <!-- Decorative Shapes -->
                        <div class="absolute top-0 right-0 -mt-24 -mr-24 w-[500px] h-[500px] bg-blue-600/10 rounded-full blur-[100px] pointer-events-none"></div>
                        <div class="absolute bottom-0 left-0 -mb-24 -ml-24 w-[400px] h-[400px] bg-indigo-500/10 rounded-full blur-[100px] pointer-events-none"></div>

                        <div class="max-w-7xl mx-auto w-full z-10 text-white px-12 pb-12">
                            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                                <div class="space-y-4">
                                    <div class="inline-flex items-center px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-[10px] font-bold uppercase tracking-widest backdrop-blur-sm">
                                        {{ $t('Enterprise Analytics') }}
                                    </div>
                                    <h1 class="text-4xl md:text-5xl font-bold tracking-tight text-white">
                                        {{ $t('Welcome back, :name', { name: user.name }) }}
                                    </h1>
                                    <p class="text-slate-400 max-w-2xl text-lg font-light leading-relaxed">
                                        {{ $t('This report presents price history, information and analysis related to our product portfolio') }}
                                    </p>
                                </div>

                                <div class="flex items-center space-x-4">
                                <!-- Connection Status (Real-time check) -->
                                <div class="hidden sm:flex items-center px-4 py-2.5 border rounded-xl backdrop-blur-md"
                                     :class="props.embedConfig?.is_available !== false ? 'bg-green-500/10 border-green-500/20' : 'bg-rose-500/10 border-rose-500/20'">

                                    <div class="relative flex h-2 w-2 mr-3">
                                        <span v-if="props.embedConfig?.is_available !== false" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2"
                                              :class="props.embedConfig?.is_available !== false ? 'bg-green-500' : 'bg-rose-500'"></span>
                                    </div>

                                    <div class="flex flex-col">
                                        <span class="text-[10px] font-bold uppercase tracking-widest leading-none mb-0.5"
                                              :class="props.embedConfig?.is_available !== false ? 'text-green-400' : 'text-rose-400'">
                                            {{ props.embedConfig?.is_available !== false ? $t('System Online') : $t('System Offline') }}
                                        </span>
                                        <span class="text-[8px] font-medium uppercase tracking-tighter leading-none"
                                              :class="props.embedConfig?.is_available !== false ? 'text-green-500/70' : 'text-rose-500/70'">
                                            {{ props.embedConfig?.is_available !== false ? $t('Service Connection') : $t('Service Unavailable') }}
                                        </span>
                                    </div>
                                </div>

                                    <button
                                        @click="refreshDashboard"
                                        :disabled="isRefreshing"
                                        class="flex items-center space-x-2 px-4 py-2.5 bg-white/10 hover:bg-white/20 border border-white/10 hover:border-white/20 rounded-xl transition-all duration-300 group backdrop-blur-md disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <RefreshCw
                                            class="w-4 h-4 text-blue-400 group-hover:rotate-180 transition-transform duration-700"
                                            :class="{ 'animate-spin': isRefreshing }"
                                        />
                                        <span class="text-xs font-bold text-white uppercase tracking-wider">{{ $t('Refresh Report') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics / Quick Access Grid -->
                    <div class="max-w-7xl mx-auto px-12 -mt-16 pb-24 relative z-10">

                        <!-- SKELETON LOADER FOR GRID -->
                        <div v-if="isLoading && pages.length === 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div v-for="n in 3" :key="n" class="h-64 bg-white rounded-2xl border border-slate-100 p-6 flex flex-col overflow-hidden">
                                <div class="w-12 h-12 rounded-xl mb-4 shimmer"></div>
                                <div class="h-4 w-24 rounded mb-2 shimmer"></div>
                                <div class="h-6 w-48 rounded mb-4 shimmer"></div>
                                <div class="h-10 w-full rounded mt-auto shimmer"></div>
                            </div>
                        </div>

                        <!-- NO ACCESS MESSAGE -->
                        <div v-else-if="!isLoading && pages.length > 0 && filteredPages.length === 0" class="col-span-full py-20 flex flex-col items-center justify-center text-center space-y-6 bg-white rounded-3xl border border-dashed border-slate-300 shadow-sm animate-in fade-in zoom-in duration-500">
                             <div class="mx-auto w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center">
                                 <Layout class="h-8 w-8 text-blue-600" />
                             </div>

                             <div class="space-y-2 max-w-sm">
                                 <h3 class="text-xl font-bold text-slate-900">{{ $t('No Access to Reports') }}</h3>
                                 <p class="text-slate-500 text-sm leading-relaxed font-medium">
                                     {{ $t('You currently do not have any reports assigned to your account. Our administration team will configure your permissions shortly. Please check back soon.') }}
                                 </p>
                             </div>

                             <div class="pt-4 flex items-center justify-center space-x-2 text-xs font-bold text-slate-400 uppercase tracking-widest">
                                 <Clock class="h-3 w-3" />
                                 <span>{{ $t('Awaiting Approval') }}</span>
                             </div>
                        </div>

                        <!-- REPORTS GRID -->
                        <div v-else-if="filteredPages.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div
                                v-for="(page, index) in filteredPages"
                                :key="page.name"
                                @click="setActivePage(page)"
                                class="group relative bg-white border border-slate-100 rounded-2xl shadow-sm hover:shadow-2xl hover:shadow-blue-900/10 hover:-translate-y-1 transition-all duration-300 cursor-pointer overflow-hidden flex flex-col"
                            >
                                <!-- Card Top Accent -->
                                <div class="h-1.5 w-full bg-gradient-to-r from-blue-500 to-indigo-600 origin-left scale-x-0 group-hover:scale-x-100 transition-transform duration-500"></div>

                                <div class="p-6 flex-1 flex flex-col items-start">
                                    <!-- Icon Container -->
                                    <div class="w-12 h-12 rounded-xl bg-slate-50 border border-slate-100 group-hover:bg-blue-600 group-hover:border-blue-500 flex items-center justify-center mb-4 transition-all duration-300 shadow-sm group-hover:shadow-lg group-hover:shadow-blue-500/30">
                                        <component :is="getPageIcon(page.displayName)" class="w-6 h-6 text-slate-500 group-hover:text-white transition-colors duration-300" />
                                    </div>

                                    <div class="space-y-2 mb-4">
                                        <span class="text-[10px] font-bold uppercase text-slate-400 tracking-widest">{{ $t('Report') }} 0{{ index + 1 }}</span>
                                        <h3 class="text-lg font-bold text-slate-800 group-hover:text-blue-700 transition-colors">
                                            {{ page.displayName.replace(/_/g, ' ') }}
                                        </h3>
                                        <p class="text-xs text-slate-500 line-clamp-2">
                                            {{ $t('Click to view full details and analysis of this report.') }}
                                        </p>
                                    </div>

                                    <div class="mt-auto pt-4 w-full flex items-center justify-between border-t border-slate-50 group-hover:border-slate-100 transition-colors">
                                        <span class="text-[10px] font-semibold text-blue-600 opacity-60 group-hover:opacity-100 transition-opacity uppercase tracking-wider">{{ $t('Access Panel') }}</span>
                                        <div class="w-6 h-6 rounded-full bg-slate-50 flex items-center justify-center group-hover:bg-blue-50 transition-colors">
                                            <svg class="w-3 h-3 text-slate-400 group-hover:text-blue-600 transform group-hover:translate-x-0.5 transition-all" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- 🔄 PREMIUM LOADING OVERLAY (Only on Initial Load) -->
            <transition name="fade">
                <div v-if="isLoading && activePage !== 'system_home' && !isMounted" class="absolute inset-0 z-50 bg-white/60 backdrop-blur-md flex flex-col items-center justify-center transition-all duration-300">
                    <div class="flex flex-col items-center scale-110">
                        <div class="relative w-20 h-20 mb-8">
                            <div class="absolute inset-0 border-4 border-slate-100 rounded-full"></div>
                            <div class="absolute inset-0 border-4 border-t-blue-600 rounded-full animate-spin"></div>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <Loader2 class="w-8 h-8 text-blue-600 animate-pulse" />
                            </div>
                        </div>
                        <h3 class="text-xl font-black text-slate-800 tracking-tight mb-2 uppercase italic">{{ $t('Processing Data') }}</h3>
                        <div class="flex items-center space-x-2">
                             <div class="h-1 w-1 bg-blue-600 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
                             <div class="h-1 w-1 bg-blue-600 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
                             <div class="h-1 w-1 bg-blue-600 rounded-full animate-bounce"></div>
                             <span class="text-xs font-bold text-slate-400 uppercase tracking-[0.3em] ml-2">{{ $t('Synchronizing Analytics') }}</span>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- 📊 POWER BI CANVAS -->
            <div
                v-if="embedConfig"
                ref="reportContainer"
                class="flex-1 w-full h-full relative bg-white"
                :class="activePage === 'system_home' ? 'pointer-events-none opacity-0 h-0 hidden' : 'opacity-100 h-full w-full block transition-opacity duration-700'"
            ></div>
        </main>
    </div>
</template>

<style>
/* 💎 PREMIUM SHIMMER EFFECT */
.shimmer {
    position: relative;
    overflow: hidden;
    background-color: #f1f5f9;
}

.shimmer::after {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    transform: translateX(-100%);
    background: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0,
        rgba(255, 255, 255, 0.2) 20%,
        rgba(255, 255, 255, 0.5) 60%,
        rgba(255, 255, 255, 0)
    );
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    100% {
        transform: translateX(100%);
    }
}

/* 🖼️ IFrame Full Coverage */
iframe {
    border: none !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    height: 100% !important;
}
</style>

<style scoped>
.fade-slide-enter-active, .fade-slide-leave-active { transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1); }
.fade-slide-enter-from { opacity: 0; transform: translateY(20px); }
.fade-slide-leave-to { opacity: 0; transform: translateY(-20px); }

.fade-enter-active, .fade-leave-active { transition: opacity 0.5s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
