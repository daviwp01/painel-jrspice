<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { watch, onMounted } from 'vue';
import { toast } from '@/Stores/ToastStore';
import ToastContainer from '@/Components/ToastContainer.vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import LegalModal from '@/Components/LegalModal.vue';

const page = usePage();
const user = page.props.auth.user;

const showLegalModal = ref(false);
const legalModalType = ref('privacy');
const legalContent = ref('');

const openLegal = (type) => {
    legalModalType.value = type;
    legalContent.value = type === 'privacy'
        ? page.props.settings.legal_privacy_policy
        : page.props.settings.legal_terms_of_use;
    showLegalModal.value = true;
};

// Watch for flash messages from backend
watch(() => page.props.flash, (flash) => {
    if (flash.success) {
        toast.add(flash.success, 'success');
    }
    if (flash.error) {
        toast.add(flash.error, 'error');
    }
}, { deep: true });

// Check for initial flash messages on mount
onMounted(() => {
    if (page.props.flash.success) {
        toast.add(page.props.flash.success, 'success');
    }
    if (page.props.flash.error) {
        toast.add(page.props.flash.error, 'error');
    }
});

// Custom Navigation Items
const navItems = [
    { name: 'Dashboard', route: 'dashboard', active: route().current('dashboard') },
    { name: 'Users', route: 'admin.users.index', active: route().current('admin.users.*'), masterOnly: true },
    { name: 'Activity', route: 'admin.activity.index', active: route().current('admin.activity.*'), masterOnly: true },
    { name: 'Settings', route: 'admin.settings.index', active: route().current('admin.settings.*'), masterOnly: true },
];
</script>

<template>
    <div class="h-screen w-full bg-slate-50 font-sans text-slate-900 flex relative selection:bg-blue-100 selection:text-blue-900">

        <!-- 🚀 MAIN STAGE (FULL WIDTH) -->
        <div class="flex-1 flex flex-col h-full relative z-10 bg-slate-50">

            <!-- MINIMALIST HEADER (DARK THEME) -->
            <header class="h-16 flex items-center justify-between px-6 bg-[#0f172a] border-b border-slate-800 z-50 shadow-md relative">
                <div class="flex items-center space-x-8">
                    <!-- Brand Logo -->
                    <Link :href="route('dashboard')" class="flex items-center space-x-2 group">
                        <img src="/logo-white.png" alt="Jrspice" class="h-10 w-auto object-contain transition-opacity duration-300 opacity-90 group-hover:opacity-100" />
                    </Link>
                    <slot name="header">
                        <h2 class="hidden md:block text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $t('Analytics Platform') }}</h2>
                    </slot>

                    <!-- Navigation Links -->

                </div>

                <!-- Right Side Actions -->
                <div class="flex items-center space-x-5">
                    <div class="flex items-center space-x-4 pl-4 border-l border-slate-800 relative z-50">
                        <LanguageSwitcher />

                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button class="flex items-center space-x-2 p-1 rounded-lg hover:bg-slate-800 transition-colors focus:outline-none">
                                    <div class="h-8 w-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-blue-400">
                                        {{ user.name.charAt(0) }}
                                    </div>
                                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </template>
                            <template #content>
                                <div class="px-4 py-3 border-b border-slate-100">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ $t('Account') }}</p>
                                    <p class="text-sm font-medium text-slate-800 truncate">{{ user.name }}</p>
                                </div>
                                <div v-if="user.is_master" class="border-b border-slate-100 block">
                                    <DropdownLink :href="route('admin.users.index')" class="text-sm">
                                        {{ $t('Users') }}
                                    </DropdownLink>
                                    <DropdownLink :href="route('admin.settings.index')" class="text-sm">
                                        {{ $t('Settings') }}
                                    </DropdownLink>
                                </div>
                                <div v-if="!user.is_master" class="border-b border-slate-100 block">
                                    <DropdownLink :href="route('profile.edit')" class="text-sm">
                                        {{ $t('My Profile') }}
                                    </DropdownLink>
                                </div>
                                <DropdownLink :href="route('logout')" method="post" as="button" class="text-sm text-red-600 hover:bg-red-50"> {{ $t('Log Out') }} </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </header>
            <!-- SUBMENU (ONLY ON INTERNAL PAGES FOR MASTERS) -->
            <nav v-if="!route().current('dashboard') && user.is_master" class="bg-white border-b border-slate-200 shadow-sm relative z-40">
                <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center space-x-1">
                    <template v-for="item in navItems" :key="item.route">
                        <Link
                            :href="route(item.route)"
                            class="px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all duration-200 flex items-center group"
                            :class="item.active
                                ? 'text-blue-600 bg-blue-50/50'
                                : 'text-slate-500 hover:text-slate-900 hover:bg-slate-50'"
                        >
                            <div class="w-1.5 h-1.5 rounded-full mr-2 transition-all opacity-0 group-hover:opacity-100"
                                 :class="item.active ? 'bg-blue-500 opacity-100' : 'bg-slate-300'"></div>
                            {{ $t(item.name) }}
                        </Link>
                    </template>
                </div>
            </nav>

            <!-- MAIN VIEWPORT -->
            <main class="flex-1 overflow-hidden relative">
                <!-- Content gets a wrapper now for consistent padding -->
                <div class="w-full h-full bg-slate-50 overflow-y-auto relative flex flex-col">
                    <div class="flex-1">
                        <slot />
                    </div>

                    <!-- Discrete Footer -->
                    <footer class="py-6 px-8 border-t border-slate-200/50 mt-auto bg-white/30 backdrop-blur-sm">
                        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
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

        <!-- Global Legal Modal -->
        <LegalModal
            :show="showLegalModal"
            :type="legalModalType"
            :content="legalContent"
            @close="showLegalModal = false"
        />

        <!-- Global Toast Container -->
        <ToastContainer />
    </div>
</template>


