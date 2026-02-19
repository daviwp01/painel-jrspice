<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import LegalModal from '@/Components/LegalModal.vue';
import { ref } from 'vue';

const page = usePage();
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
</script>

<template>
    <!-- FULL SCREEN CONTAINER -->
    <div class="h-screen w-full flex bg-white overflow-hidden relative font-sans text-slate-900">

        <!-- 🎨 LEFT SIDE: BRANDING (60% WIDTH) - UPDATED TO MATCH OVERVIEW HEADER -->
        <div class="hidden lg:flex w-[60%] bg-[#0f172a] relative flex-col items-center justify-center p-24 text-center border-r border-slate-800 overflow-hidden">

            <!-- Background Image Integration -->
            <div class="absolute inset-0 z-0 pointer-events-none opacity-[0.12] mix-blend-overlay">
                <img
                    src="https://images.unsplash.com/photo-1451187580459-43490279c0fa?q=80&w=2672&auto=format&fit=crop"
                    alt="Network Background"
                    class="w-full h-full object-cover"
                />
            </div>

            <!-- Decorative Shapes (Copied from Overview Header) -->
            <div class="absolute top-0 right-0 -mt-24 -mr-24 w-[500px] h-[500px] bg-blue-600/10 rounded-full blur-[100px] pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 -mb-24 -ml-24 w-[400px] h-[400px] bg-indigo-500/10 rounded-full blur-[100px] pointer-events-none"></div>

            <!-- Texture Overlay -->
            <div class="absolute inset-0 opacity-[0.03] pointer-events-none bg-[url('https://grainy-gradients.vercel.app/noise.svg')] z-0"></div>

            <!-- Content -->
            <div class="relative z-10 max-w-4xl w-full">
                <div class="mb-12 flex flex-col items-center">
                    <div class="inline-flex items-center px-4 py-1.5 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-[10px] font-bold uppercase tracking-[0.3em] mb-8 backdrop-blur-sm">
                        Enterprise Analytics Platform
                    </div>
                    <Link href="/">
                        <img src="/logo-white.png" alt="JR SPICE" class="h-24 w-auto object-contain drop-shadow-2xl transition-transform duration-500 hover:scale-110" />
                    </Link>
                </div>

                <!-- Headline -->
                <h1 class="text-4xl font-black tracking-tighter text-white leading-tight mb-6 uppercase whitespace-nowrap">
                    {{ $t('HEADLINE_PART_1') }}
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-400">
                        {{ $t('HEADLINE_PART_2') }}
                    </span>
                </h1>

                <div class="w-12 h-1 bg-gradient-to-r from-blue-600 to-indigo-600 mx-auto mb-6 rounded-full"></div>

                <p class="text-slate-400 text-lg font-light leading-relaxed max-w-xl mx-auto">
                    {{ $t('This report presents price history, information and analysis related to our product portfolio') }}
                </p>
            </div>
        </div>

        <!-- 📝 RIGHT SIDE: FORM (40% WIDTH) -->
        <div class="flex-1 lg:w-[40%] bg-white h-screen flex flex-col relative">

            <!-- Language Switcher (Fixed Position) -->
            <div class="absolute top-8 right-8 z-50">
                <LanguageSwitcher />
            </div>

            <!-- Form Container (Centered Vertically) -->
            <div class="flex-1 flex flex-col justify-center px-12 sm:px-20 lg:px-24 w-full">
                <div class="max-w-[520px] mx-auto w-full">
                    <slot />
                </div>
            </div>

            <!-- Footer (Fixed at bottom of this column) -->
            <div class="py-8 px-12 flex-shrink-0 border-t border-slate-50 bg-white">
                <div class="flex flex-col sm:flex-row justify-between items-center text-[10px] font-bold text-slate-400 uppercase tracking-widest gap-4">
                    <span class="hover:text-slate-600 transition-colors cursor-default">{{ $t('Copyright • 2026 All rights reserved') }}</span>
                    <div class="flex space-x-8">
                        <button
                            @click="openLegal('privacy')"
                            class="hover:text-blue-600 transition-colors uppercase"
                        >
                            {{ $t('PRIVACY') }}
                        </button>
                        <button
                            @click="openLegal('terms')"
                            class="hover:text-blue-600 transition-colors uppercase"
                        >
                            {{ $t('TERMS') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Global Legal Modal -->
        <LegalModal
            :show="showLegalModal"
            :type="legalModalType"
            :content="legalContent"
            @close="showLegalModal = false"
        />
    </div>
</template>
