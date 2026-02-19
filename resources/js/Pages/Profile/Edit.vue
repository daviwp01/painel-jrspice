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
        <div class="py-12 bg-[#f8fafc] min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">

                <!-- Page Header -->
                <div class="mb-10">
                    <h1 class="text-4xl font-black text-slate-900 tracking-tight flex items-center">
                        <span class="bg-indigo-600 w-2 h-10 rounded-full mr-4"></span>
                        {{ $t('My Profile') }}
                    </h1>
                    <p class="mt-3 text-slate-500 font-medium text-lg">
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
