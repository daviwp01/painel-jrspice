<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router, Link, usePage } from '@inertiajs/vue3';
import { toast } from '@/Stores/ToastStore';
import ToastContainer from '@/Components/ToastContainer.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import LegalModal from '@/Components/LegalModal.vue';
import { 
    LayoutDashboard, Users as UsersIcon, Activity as ActivityIcon, 
    Settings as SettingsIcon2, Database as DatabaseIcon, 
    ChartLine as ChartLineIcon, Menu as MenuIcon, X as XIcon, User as UserIcon, Loader2 
} from 'lucide-vue-next';

const page = usePage();
const user = computed(() => page.props.auth.user);

const showLegalModal = ref(false);
const legalModalType = ref('privacy');
const legalContent = ref('');

const isMobileMenuOpen = ref(false);

const closeMobileMenu = () => {
    isMobileMenuOpen.value = false;
};

const openLegal = (type) => {
    legalModalType.value = type;
    legalContent.value = type === 'privacy'
        ? page.props.settings.legal_privacy_policy
        : page.props.settings.legal_terms_of_use;
    showLegalModal.value = true;
};

// Watch for flash messages from backend
watch(() => page.props.flash, (flash) => {
    if (flash.success) toast.add(flash.success, 'success');
    if (flash.error) toast.add(flash.error, 'error');
}, { deep: true });

const isGlobalLoading = ref(false);
let removeStartListener = null;
let removeFinishListener = null;

onMounted(() => {
    if (page.props.flash.success) toast.add(page.props.flash.success, 'success');
    if (page.props.flash.error) toast.add(page.props.flash.error, 'error');

    removeStartListener = router.on('start', (event) => {
        const targetUrl = event.detail.visit.url.toString();
        const isDataPage = targetUrl.includes('/dashboard/page');
        const isInternalFilterNav = event.detail.visit.preserveState === true;

        if (isDataPage && !isInternalFilterNav) {
            isGlobalLoading.value = true;
        }
    });

    removeFinishListener = router.on('finish', () => {
        if (isGlobalLoading.value) {
            setTimeout(() => {
                isGlobalLoading.value = false;
            }, 300);
        }
    });
});

onUnmounted(() => {
    if (removeStartListener) removeStartListener();
    if (removeFinishListener) removeFinishListener();
});

// Custom Navigation Items (Admin only)
const navItems = computed(() => [
    { name: 'Gestão de Dados', route: 'admin.data.index', active: route().current('admin.data.*'), masterOnly: true, icon: DatabaseIcon },
    { name: 'Users', route: 'admin.users.index', active: route().current('admin.users.*'), masterOnly: true, icon: UsersIcon },
    { name: 'Activity', route: 'admin.activity.index', active: route().current('admin.activity.*'), masterOnly: true, icon: ActivityIcon },
    { name: 'Settings', route: 'admin.settings.index', active: route().current('admin.settings.*'), masterOnly: true, icon: SettingsIcon2 },
]);
</script>

<template>
    <div class="h-screen w-full bg-[#f8fafc] font-sans text-slate-900 flex relative overflow-hidden selection:bg-blue-100 selection:text-blue-900">

        <!-- 📱 SIDEBAR (LEFT) -->
        <aside :class="[
            'fixed inset-y-0 left-0 bg-white z-[60] flex flex-col w-72 md:relative transition-transform duration-300 ease-in-out shrink-0',
            isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
        ]">
            <!-- Brand Logo -->
            <div class="h-16 flex items-center justify-between px-6 bg-[#0f172a] shrink-0">
                <Link :href="route('dashboard')" class="flex items-center space-x-2 group w-full">
                    <img src="/logo-white.png" alt="Jrspice" class="h-8 w-auto object-contain transition-opacity duration-300 opacity-90 group-hover:opacity-100" />
                </Link>
                <button class="md:hidden text-slate-400 hover:text-white transition-colors" @click="closeMobileMenu">
                    <XIcon class="w-5 h-5" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-5 space-y-8 border-r border-slate-200">
                
                <!-- HOME / STANDARD -->
                <div v-if="!user?.is_master">
                    <div class="space-y-1">
                        <Link :href="route('dashboard')" :class="['flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all', route().current('dashboard') ? 'bg-slate-900 text-white shadow-md' : 'text-slate-600 hover:bg-slate-100']">
                            <LayoutDashboard class="w-4 h-4" :class="route().current('dashboard') ? 'text-white' : 'text-slate-400'" />
                            {{ $t('Dashboard Home') }}
                        </Link>
                    </div>
                </div>

                <!-- DASHBOARDS PAGES -->
                <div v-if="$page.props.dashboardPages?.length > 0">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.1em] mb-4">{{ $t('Dashboards') }}</p>
                    <div class="space-y-1">
                        <Link v-for="pg in $page.props.dashboardPages" :key="pg.id" :href="route('dashboard.page', { slug: pg.slug })"
                            :class="['flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all', 
                                     route().current('dashboard.page', { slug: pg.slug }) ? 'bg-slate-900 text-white shadow-md shadow-slate-900/10' : 'text-slate-600 hover:bg-slate-100']">
                            <ChartLineIcon class="w-4 h-4" :class="route().current('dashboard.page', {slug: pg.slug}) ? 'text-white' : 'text-slate-400'" />
                            {{ pg.title }}
                        </Link>
                    </div>
                </div>

                <!-- ADMINISTRATIVO -->
                <div v-if="user?.is_master">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.1em] mb-4">{{ $t('Administração') }}</p>
                    <div class="space-y-1">
                        <template v-for="item in navItems" :key="item.route">
                            <Link :href="route(item.route)" v-if="(!item.masterOnly || user?.is_master)"
                                :class="['flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-semibold transition-all', 
                                         item.active ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-100']">
                                <component :is="item.icon" class="w-4 h-4" :class="item.active ? 'text-blue-600' : 'text-slate-400'" />
                                {{ $t(item.name) }}
                            </Link>
                        </template>
                    </div>
                </div>

                <!-- DYNAMIC FILTERS SLOT -->
                <slot name="sidebar-filters"></slot>
            </div>
        </aside>

        <!-- BACKDROP MOBILE -->
        <div v-if="isMobileMenuOpen" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[50] md:hidden" @click="closeMobileMenu"></div>

        <!-- 🚀 MAIN STAGE -->
        <div class="flex-1 flex flex-col h-full min-w-0 bg-[#f8fafc]">

            <!-- TOP BAR -->
            <header class="h-16 flex items-center justify-between px-4 sm:px-6 bg-[#0f172a] border-b border-slate-800 z-30 shrink-0 shadow-md relative">
                <div class="flex items-center gap-4">
                    <button class="md:hidden text-slate-400 hover:text-white p-2 -ml-2 rounded-lg" @click="isMobileMenuOpen = true">
                        <MenuIcon class="w-6 h-6" />
                    </button>
                    <!-- Título dinâmico opcional via Header Slot -->
                    <slot name="header">
                        <h2 class="hidden sm:block text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $t('Plataforma Analítica') }}</h2>
                    </slot>
                </div>

                <!-- Right Tools -->
                <div class="flex items-center space-x-4 sm:space-x-6">
                    <LanguageSwitcher />

                    <div class="h-6 w-px bg-slate-700 hidden sm:block"></div>

                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button class="flex items-center space-x-2 p-1 rounded-lg hover:bg-slate-800 transition-colors focus:outline-none">
                                <div class="h-8 w-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-blue-400 shadow-sm">
                                    {{ user?.name?.charAt(0) }}
                                </div>
                                <span class="text-sm font-bold text-slate-300 hidden sm:block">{{ user?.name }}</span>
                                <svg class="h-4 w-4 text-slate-500 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                        </template>
                        <template #content>
                            <div class="px-4 py-3 border-b border-slate-100">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ $t('Conta') }}</p>
                                <p class="text-sm font-bold text-slate-800 truncate">{{ user?.name }}</p>
                            </div>
                            <div class="border-b border-slate-100 block">
                                <DropdownLink :href="route('profile.edit')" class="text-sm font-medium">
                                    {{ $t('Meu Perfil') }}
                                </DropdownLink>
                            </div>
                            <DropdownLink :href="route('logout')" method="post" as="button" class="text-sm font-medium text-red-600 hover:bg-red-50"> 
                                {{ $t('Sair do Sistema') }} 
                            </DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <!-- VIEWPORT CONTENT -->
            <main class="flex-1 overflow-x-hidden overflow-y-auto relative bg-[#f8fafc]">
                <div class="min-h-full flex flex-col">
                    <div class="flex-1">
                        <slot />
                    </div>

                    <!-- Discrete Footer -->
                    <footer class="py-6 border-t border-slate-200 mt-auto bg-slate-50">
                        <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-4">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em]">
                                {{ $t('Copyright • 2026 All rights reserved') }}
                            </p>
                            <div class="flex items-center space-x-6 text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                                <button @click="openLegal('privacy')" class="hover:text-blue-600 transition-colors uppercase">{{ $t('PRIVACY') }}</button>
                                <button @click="openLegal('terms')" class="hover:text-blue-600 transition-colors uppercase">{{ $t('TERMS') }}</button>
                            </div>
                        </div>
                    </footer>
                </div>
            </main>
        </div>

        <!-- GLOBAL LOADING OVERLAY -->
        <div v-if="isGlobalLoading" class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-slate-900/40 backdrop-blur-sm transition-opacity">
            <div class="bg-white p-10 rounded-3xl shadow-2xl border border-slate-200 flex flex-col items-center gap-5 animate-in zoom-in-95 duration-300">
                <div class="relative flex items-center justify-center">
                    <div class="absolute w-12 h-12 rounded-full border-4 border-slate-100"></div>
                    <Loader2 class="w-12 h-12 text-blue-600 animate-spin relative z-10" />
                </div>
                <div class="text-center">
                    <h3 class="text-sm font-black text-slate-900 uppercase tracking-[0.2em] mb-1">Processando Análise</h3>
                    <p class="text-[10px] font-bold text-slate-500 tracking-widest uppercase">Gerando insights e métricas...</p>
                </div>
            </div>
        </div>

        <LegalModal :show="showLegalModal" :type="legalModalType" :content="legalContent" @close="showLegalModal = false" />
        <ToastContainer />
    </div>
</template>
