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
    ChartLine as ChartLineIcon, Menu as MenuIcon, X as XIcon, User as UserIcon, LogOut as LogOutIcon, Loader2 
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

onMounted(() => {
    if (page.props.flash.success) toast.add(page.props.flash.success, 'success');
    if (page.props.flash.error) toast.add(page.props.flash.error, 'error');
});

onUnmounted(() => {
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
            'fixed inset-y-0 left-0 bg-[#0f172a] z-[60] flex flex-col w-[360px] md:relative transition-transform duration-300 ease-in-out shrink-0 border-r border-slate-800',
            isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
        ]">
            <!-- Brand Logo -->
            <div class="h-16 flex items-center justify-between px-6 bg-[#1e293b]/30 shrink-0 border-b border-slate-800/50">
                <Link :href="route('dashboard')" class="flex items-center space-x-2 group w-full">
                    <img src="/logo-white.png" alt="Jrspice" class="h-8 w-auto object-contain transition-opacity duration-300 opacity-90 group-hover:opacity-100" />
                </Link>
                <button class="md:hidden text-slate-400 hover:text-white transition-colors" @click="closeMobileMenu">
                    <XIcon class="w-5 h-5" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto p-5 space-y-4 sidebar-scrollbar">
                
                <!-- DASHBOARDS PAGES -->
                <div v-if="$page.props.dashboardPages?.length > 0">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em] mb-4 px-3 flex items-center gap-2">
                        <span class="w-1 h-1 rounded-full bg-blue-500"></span>
                        {{ $t('Dashboards') }}
                    </p>
                    <div class="space-y-1">
                        <Link v-for="pg in $page.props.dashboardPages" :key="pg.id" :href="route('dashboard.page', { slug: pg.slug })"
                            :class="['flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all relative group', 
                                     route().current('dashboard.page', { slug: pg.slug }) 
                                     ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20' 
                                     : 'text-slate-400 hover:bg-white/5 hover:text-white']">
                            <ChartLineIcon class="w-4 h-4 transition-colors" :class="route().current('dashboard.page', {slug: pg.slug}) ? 'text-blue-500' : 'text-slate-500 group-hover:text-slate-300'" />
                            {{ pg.title }}
                            <div v-if="route().current('dashboard.page', { slug: pg.slug })" class="absolute left-[-20px] top-1/2 -translate-y-1/2 w-1.5 h-6 bg-blue-500 rounded-r-full shadow-[0_0_15px_rgba(59,130,246,0.5)]"></div>
                        </Link>
                    </div>
                </div>

                <!-- DYNAMIC FILTERS SLOT -->
                <div class="bg-slate-900/40 rounded-3xl p-1">
                    <slot name="sidebar-filters"></slot>
                </div>

                <!-- ADMINISTRATIVO -->
                <div v-if="user?.is_master" class="mt-8 border-t border-slate-800 pt-8">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em] mb-4 px-3 flex items-center gap-2">
                         <span class="w-1 h-1 rounded-full bg-slate-500"></span>
                         {{ $t('Administração') }}
                    </p>
                    <div class="space-y-1">
                        <template v-for="item in navItems" :key="item.route">
                            <Link :href="route(item.route)" v-if="(!item.masterOnly || user?.is_master)"
                                :class="['flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-bold transition-all relative group', 
                                         item.active 
                                         ? 'bg-white/5 text-blue-400 border border-white/5' 
                                         : 'text-slate-400 hover:bg-white/5 hover:text-white']">
                                <component :is="item.icon" class="w-4 h-4 transition-colors" :class="item.active ? 'text-blue-500' : 'text-slate-500 group-hover:text-slate-300'" />
                                {{ $t(item.name) }}
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </aside>

        <!-- BACKDROP MOBILE -->
        <div v-if="isMobileMenuOpen" class="fixed inset-0 bg-slate-900/70 z-[50] md:hidden" @click="closeMobileMenu"></div>

        <!-- 🚀 MAIN STAGE -->
        <div class="flex-1 flex flex-col h-full min-w-0 bg-[#f8fafc]">

            <!-- TOP BAR -->
            <header class="h-16 flex items-center justify-between px-4 sm:px-6 bg-white border-b border-slate-200 z-30 shrink-0 shadow-sm relative">
                <div class="flex items-center gap-4">
                    <button class="md:hidden text-slate-500 hover:bg-slate-50 p-2 -ml-2 rounded-lg transition-colors" @click="isMobileMenuOpen = true">
                        <MenuIcon class="w-6 h-6" />
                    </button>
                    <h2 class="hidden sm:block text-xs font-bold text-slate-400 uppercase tracking-widest"></h2>
                </div>

                <!-- Right Tools -->
                <div class="flex items-center space-x-4 sm:space-x-6">
                    <LanguageSwitcher />

                    <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button class="flex items-center space-x-2 p-1 rounded-xl hover:bg-slate-50 transition-colors focus:outline-none group">
                                <div class="h-8 w-8 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center text-xs font-black text-blue-600 shadow-sm transition-colors group-hover:border-blue-200 group-hover:bg-white">
                                    {{ user?.name?.charAt(0) }}
                                </div>
                                <span class="text-sm font-bold text-slate-700 hidden sm:block">{{ user?.name }}</span>
                                <svg class="h-4 w-4 text-slate-400 hidden sm:block group-hover:text-slate-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                            </button>
                        </template>
                        <template #content>
                            <div class="px-4 py-3 border-b border-slate-100 bg-slate-50">
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-[0.15em] mb-0.5 leading-none">{{ $t('Conta') }}</p>
                                <p class="text-xs font-black text-slate-900 truncate leading-none mt-1">{{ user?.name }}</p>
                            </div>
                            <div class="block">
                                <DropdownLink :href="route('profile.edit')" class="flex items-center gap-3">
                                    <UserIcon class="w-4 h-4 text-slate-400" />
                                    {{ $t('Meu Perfil') }}
                                </DropdownLink>
                            </div>
                            <div class="border-t border-slate-50">
                                <DropdownLink :href="route('logout')" method="post" as="button" class="flex items-center gap-3 !text-rose-600 hover:!bg-rose-50"> 
                                    <LogOutIcon class="w-4 h-4" />
                                    {{ $t('Sair do Sistema') }} 
                                </DropdownLink>
                            </div>
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


        <LegalModal :show="showLegalModal" :type="legalModalType" :content="legalContent" @close="showLegalModal = false" />
        <ToastContainer />
    </div>
</template>

<style>
/* Sidebar minimalist scrollbar */
.sidebar-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.sidebar-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.sidebar-scrollbar::-webkit-scrollbar-thumb {
    background: #1e293b; /* slate-800 */
    border-radius: 10px;
}
.sidebar-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #334155; /* slate-700 */
}

/* Firefox support */
.sidebar-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: #1e293b transparent;
}
</style>
