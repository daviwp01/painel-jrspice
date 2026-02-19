<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Users, Clock, Mail, MousePointer2, Circle, ShieldCheck, Activity, UserCheck, Trash2 } from 'lucide-vue-next';
import Pagination from '@/Components/Pagination.vue';
import { ref } from 'vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const isClearing = ref(false);
const showConfirmClear = ref(false);

const clearActivities = () => {
    isClearing.value = true;
    router.post(route('admin.activity.clear'), {}, {
        preserveScroll: true,
        onSuccess: () => {
            showConfirmClear.value = false;
        },
        onFinish: () => isClearing.value = false
    });
};

defineProps({
    users: Object, // Paginated object
    total_users: Number,
    online_users: Number
});

const getAvatarColor = (name) => {
    return 'bg-indigo-50 text-indigo-600 border-indigo-100';
};
</script>

<template>
    <Head :title="$t('User Access Control')" />

    <DashboardLayout>
        <div class="py-12 bg-[#f8fafc] min-h-screen">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

                <!-- Page Header & Stats Row -->
                <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-8">
                    <div>
                        <h1 class="text-4xl font-black text-slate-900 tracking-tight flex items-center capitalize">
                            <span class="bg-indigo-600 w-2 h-10 rounded-full mr-4"></span>
                            {{ $t('User Access Control') }}
                        </h1>
                        <p class="mt-3 text-slate-500 font-medium text-lg">{{ $t('Monitor user engagement and email interactions') }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <!-- Stats Mini Cards -->
                        <div class="flex items-center space-x-4 bg-white p-2 rounded-xl border border-slate-200 shadow-sm">
                            <div class="flex items-center px-4 py-2 border-r border-slate-100">
                                <Users class="w-4 h-4 text-indigo-500 mr-2" />
                                <span class="text-sm font-black text-slate-800">{{ total_users }}</span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1.5 mt-0.5">Total</span>
                            </div>
                            <div class="flex items-center px-4 py-2">
                                <UserCheck class="w-4 h-4 text-emerald-500 mr-2" />
                                <span class="text-sm font-black text-slate-800">{{ online_users }}</span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1.5 mt-0.5">Online</span>
                            </div>
                        </div>

                        <button
                            @click="showConfirmClear = true"
                            :disabled="isClearing || total_users === 0"
                            class="flex items-center space-x-2 px-6 py-3.5 bg-white hover:bg-rose-50 border border-slate-200 hover:border-rose-100 rounded-xl transition-all duration-300 group shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <Trash2 class="w-4 h-4 text-slate-400 group-hover:text-rose-600 transition-colors" :class="{ 'animate-pulse': isClearing }" />
                            <span class="text-xs font-black text-slate-600 group-hover:text-rose-700 uppercase tracking-wider">{{ $t('Clear Activities') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Activity Table -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden transition-all hover:shadow-md">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead>
                                <tr class="bg-slate-50/50">
                                    <th scope="col" class="px-8 py-6 text-left text-xs font-black text-slate-400 uppercase tracking-[0.2em]">{{ $t('User') }}</th>
                                    <th scope="col" class="px-8 py-6 text-center text-xs font-black text-slate-400 uppercase tracking-[0.2em]">{{ $t('Status') }}</th>
                                    <th scope="col" class="px-8 py-6 text-left text-xs font-black text-slate-400 uppercase tracking-[0.2em]">{{ $t('Last Login') }}</th>
                                    <th scope="col" class="px-8 py-6 text-left text-xs font-black text-slate-400 uppercase tracking-[0.2em]">{{ $t('Last Activity') }}</th>
                                    <th scope="col" class="px-8 py-6 text-center text-xs font-black text-slate-400 uppercase tracking-[0.2em]">{{ $t('Email Notification') }}</th>
                                    <th scope="col" class="px-8 py-6 text-center text-xs font-black text-slate-400 uppercase tracking-[0.2em]">{{ $t('Email Interaction') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr v-for="user in users.data" :key="user.id" class="hover:bg-indigo-50/20 group transition-all duration-300">
                                    <td class="px-8 py-5 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div
                                                class="h-12 w-12 rounded-lg border flex items-center justify-center font-black text-sm shadow-sm transition-transform duration-300 group-hover:scale-110 capitalize"
                                                :class="getAvatarColor(user.name)"
                                            >
                                                {{ user.name.charAt(0) }}
                                            </div>
                                            <div class="ml-4">
                                                <div class="flex items-center space-x-1.5">
                                                    <span class="text-sm font-black text-slate-800 tracking-tight group-hover:text-indigo-600 transition-colors">{{ user.name }}</span>
                                                    <ShieldCheck v-if="user.is_master" class="w-3.5 h-3.5 text-indigo-500" />
                                                </div>
                                                <div class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">{{ user.email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap text-center">
                                        <div class="inline-flex items-center px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-widest shadow-sm"
                                             :class="user.is_online ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-slate-50 text-slate-400 border border-slate-100'">
                                            <Circle class="w-2 h-2 mr-2 fill-current shadow-sm" />
                                            {{ user.is_online ? $t('Online') : $t('Offline') }}
                                        </div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap">
                                        <div class="flex items-center text-slate-600 bg-slate-50/50 px-3 py-2 rounded-xl border border-slate-100 w-fit">
                                            <Clock class="w-4 h-4 mr-2 text-slate-300" />
                                            <span class="text-xs font-black uppercase tracking-tighter">{{ user.last_login }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap">
                                        <div class="flex items-center text-slate-800 bg-slate-50/50 px-3 py-2 rounded-xl border border-slate-100 w-fit group-hover:bg-indigo-50 group-hover:border-indigo-100 transition-colors">
                                            <MousePointer2 class="w-4 h-4 mr-2 text-slate-400 group-hover:text-indigo-500" />
                                            <span class="text-xs font-black uppercase tracking-tighter">{{ user.last_activity }}</span>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap text-center">
                                        <div v-if="user.notified_at" class="inline-flex items-center space-x-2 bg-indigo-50 text-indigo-600 px-4 py-1.5 rounded-xl border border-indigo-100 shadow-sm">
                                            <Mail class="w-3.5 h-3.5" />
                                            <span class="text-[10px] font-black uppercase tracking-wider">{{ user.notified_at }}</span>
                                        </div>
                                        <div v-else class="text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] italic">---</div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap text-center">
                                        <div v-if="user.clicked_at" class="inline-flex items-center space-x-2 bg-emerald-50 text-emerald-600 px-4 py-1.5 rounded-xl border border-emerald-100 shadow-sm animate-in fade-in zoom-in duration-500">
                                            <Activity class="w-3.5 h-3.5" />
                                            <div class="flex flex-col items-center">
                                                <span class="text-[10px] font-black uppercase tracking-wider">{{ $t('Opened Email') }}</span>
                                                <span class="text-[8px] font-bold opacity-70 leading-none mt-0.5">({{ user.clicked_at }})</span>
                                            </div>
                                        </div>
                                        <div v-else class="text-[10px] font-black text-slate-300 uppercase tracking-[0.2em] italic">---</div>
                                    </td>
                                </tr>
                                <!-- Empty State -->
                                <tr v-if="users.data.length === 0">
                                    <td colspan="6" class="px-8 py-20 text-center">
                                        <div class="inline-flex items-center justify-center w-20 h-20 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200 mb-4 text-slate-300">
                                            <Activity class="w-8 h-8" />
                                        </div>
                                        <h3 class="text-lg font-black text-slate-800">{{ $t('No activity found') }}</h3>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="mt-8 flex justify-center">
                    <Pagination :links="users.links" />
                </div>
            </div>
        </div>

        <!-- Reusable Confirmation Modal -->
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
.animate-in {
    animation-fill-mode: both;
}
@keyframes fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes zoom-in { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }

.fade-in { animation: fade-in 0.3s ease-out; }
.zoom-in { animation: zoom-in 0.3s ease-out; }
</style>
