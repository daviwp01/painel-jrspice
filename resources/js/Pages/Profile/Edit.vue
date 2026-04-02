<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';
import { UserCircle } from 'lucide-vue-next';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head :title="$t('My Profile')" />

    <DashboardLayout>
        <template #header>
            <h2 class="hidden md:block text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $t('My Profile') }}</h2>
        </template>

        <div class="p-6 md:p-8 space-y-8 w-full max-w-none">

                <!-- Page Header -->
                <div class="mb-8">
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center uppercase">
                        <span class="bg-blue-600 w-1.5 h-7 rounded-full mr-3"></span>
                        {{ $t('My Profile') }}
                    </h1>
                    <p class="mt-1 text-slate-500 font-medium text-sm">
                        {{ $t('Manage your personal settings and security preferences.') }}
                    </p>
                </div>

                <!-- Profile Info Section -->
                <div class="animate-in fade-in slide-in-from-top-4 duration-500">
                    <div class="flex items-center space-x-2 px-1 mb-4">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ $t('Basic Info') }}</span>
                        <div class="h-px flex-1 bg-slate-200"></div>
                    </div>
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                    />
                </div>

                <!-- Password Section -->
                <div class="animate-in fade-in slide-in-from-top-4 duration-700">
                    <div class="flex items-center space-x-2 px-1 mb-4">
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ $t('Security') }}</span>
                        <div class="h-px flex-1 bg-slate-200"></div>
                    </div>
                    <UpdatePasswordForm />
                </div>
        </div>
    </DashboardLayout>
</template>

<style scoped>
.animate-in {
    animation-duration: 0.5s;
    animation-fill-mode: both;
}
@keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes slide-in-from-top-4 { from { transform: translateY(-1rem); } to { transform: translateY(0); } }
.fade-in { animation-name: fade-in; }
.slide-in-from-top-4 { animation-name: slide-in-from-top-4; }
</style>
