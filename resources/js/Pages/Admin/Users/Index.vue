<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Trash2, Edit2, UserPlus, ShieldCheck, Search, Users, UserCheck, Filter, CheckCircle, MessageCircle } from 'lucide-vue-next';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import ToggleSwitch from '@/Components/ToggleSwitch.vue';

const props = defineProps({
    users: Array,
    active_users: Number,
    total_users: Number,
    filters: Object,
});

const showConfirmDelete = ref(false);
const userToDelete = ref(null);
const isDeleting = ref(false);

const confirmDeleteAction = (user) => {
    userToDelete.value = user;
    showConfirmDelete.value = true;
};

const deleteUser = () => {
    if (!userToDelete.value) return;

    isDeleting.value = true;
    router.delete(route('admin.users.destroy', userToDelete.value.id), {
        onSuccess: () => {
            showConfirmDelete.value = false;
            userToDelete.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        }
    });
};

const searchQuery = ref(props.filters?.search || '');
const statusFilter = ref(props.filters?.status || '');

// Search & Filter Logic with debounce
let timeout;
const performSearch = () => {
    clearTimeout(timeout);
    timeout = setTimeout(() => {
        router.get(
            route('admin.users.index'),
            {
                search: searchQuery.value,
                status: statusFilter.value
            },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true
            }
        );
    }, 300);
};

watch([searchQuery, statusFilter], () => {
    performSearch();
});

const toggleUserStatus = (user) => {
    router.patch(route('admin.users.toggle-status', user.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const activateAllListed = () => {
    const inactiveIds = props.users
        .filter(u => !u.is_active)
        .map(u => u.id);

    if (inactiveIds.length === 0) return;

    router.post(route('admin.users.bulk-status'), {
        ids: inactiveIds,
        is_active: true
    }, {
        preserveScroll: true,
    });
};

const getAvatarColor = () => {
    return 'bg-indigo-50 text-indigo-600 border-indigo-100';
};

const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const locale = (document.cookie.split('; ').find(row => row.startsWith('app_locale='))?.split('=')[1] || 'en').replace('_', '-');
    return new Intl.DateTimeFormat(locale, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }).format(date);
};

const openWhatsApp = (phone) => {
    if (!phone) return;
    const cleanPhone = phone.replace(/\D/g, '');
    window.open(`https://wa.me/${cleanPhone}`, '_blank');
};
</script>

<template>
    <Head :title="$t('Users Management')" />

    <DashboardLayout>
        <div class="py-12 bg-[#f8fafc] min-h-screen">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

                <!-- Page Header & Stats Row -->
                <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-8">
                    <div>
                        <h1 class="text-4xl font-black text-slate-900 tracking-tight flex items-center">
                            <span class="bg-indigo-600 w-2 h-10 rounded-full mr-4"></span>
                            {{ $t('Users Management') }}
                        </h1>
                        <p class="mt-3 text-slate-500 font-medium text-lg">{{ $t('Manage your organization\'s users, roles and platform access.') }}</p>
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
                                <span class="text-sm font-black text-slate-800">{{ active_users }}</span>
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest ml-1.5 mt-0.5">{{ $t('Active') }}</span>
                            </div>
                        </div>

                        <Link :href="route('admin.users.create')">
                            <PrimaryButton class="px-8 py-3.5 text-sm font-black uppercase tracking-[0.15em] rounded-xl bg-indigo-600 shadow-xl shadow-indigo-100 transition-all hover:scale-[1.02] active:scale-[0.98]">
                                <UserPlus class="w-5 h-5 mr-2.5" />
                                {{ $t('Add New User') }}
                            </PrimaryButton>
                        </Link>
                    </div>
                </div>

                <!-- Filters & Search Bar -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex flex-1 items-center gap-4 max-w-2xl">
                        <div class="relative flex-1 max-w-md">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <Search class="h-5 w-5 text-slate-400" />
                            </div>
                            <input
                                type="text"
                                v-model="searchQuery"
                                :placeholder="$t('Search by name or email...')"
                                class="block w-full pl-11 pr-4 py-3.5 rounded-xl bg-white border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium text-slate-600 placeholder-slate-400 border shadow-sm"
                            />
                        </div>

                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <Filter class="h-4 w-4 text-slate-400" />
                            </div>
                            <select
                                v-model="statusFilter"
                                class="block w-full pl-10 pr-10 py-3.5 rounded-xl bg-white border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-bold text-xs uppercase tracking-widest text-slate-600 border shadow-sm appearance-none cursor-pointer"
                            >
                                <option value="">{{ $t('All Status') }}</option>
                                <option value="active">{{ $t('Active') }}</option>
                                <option value="inactive">{{ $t('Inactive') }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <div v-if="statusFilter === 'inactive' && users.length > 0" class="animate-in fade-in slide-in-from-right-4 duration-300">
                        <PrimaryButton
                            @click="activateAllListed"
                            class="px-6 py-3.5 text-[10px] font-black uppercase tracking-[0.2em] rounded-xl bg-emerald-600 shadow-xl shadow-emerald-100 transition-all hover:scale-[1.02] active:scale-[0.98] border-none"
                        >
                            <CheckCircle class="w-4 h-4 mr-2" />
                            {{ $t('Activate All Listed') }}
                        </PrimaryButton>
                    </div>
                </div>

                <!-- User Listing Table -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden transition-all hover:shadow-md">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead>
                                <tr class="bg-slate-50/50">
                                    <th scope="col" class="px-8 py-6 text-left text-xs font-black text-slate-400 uppercase tracking-[0.2em]">{{ $t('Name') }}</th>
                                    <th scope="col" class="px-8 py-6 text-left text-xs font-black text-slate-400 uppercase tracking-[0.2em]">{{ $t('Company') }}</th>
                                    <th scope="col" class="px-8 py-6 text-left text-xs font-black text-slate-400 uppercase tracking-[0.2em]">{{ $t('Phone') }}</th>
                                    <th scope="col" class="px-8 py-6 text-left text-xs font-black text-slate-400 uppercase tracking-[0.2em]">{{ $t('Email') }}</th>
                                    <th scope="col" class="px-8 py-6 text-center text-xs font-black text-slate-400 uppercase tracking-[0.2em]">{{ $t('Role') }}</th>
                                    <th scope="col" class="px-8 py-6 text-center text-xs font-black text-slate-400 uppercase tracking-[0.2em]">{{ $t('Status') }}</th>
                                    <th scope="col" class="px-8 py-6 text-center text-xs font-black text-slate-400 uppercase tracking-[0.2em]">{{ $t('Created at') }}</th>
                                    <th scope="col" class="px-8 py-6 text-center text-xs font-black text-slate-400 uppercase tracking-[0.2em] transition-all">{{ $t('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <tr
                                    v-for="user in users"
                                    :key="user.id"
                                    class="hover:bg-indigo-50/20 group transition-all duration-300"
                                >
                                    <td class="px-8 py-5 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div
                                                class="h-12 w-12 rounded-lg border flex items-center justify-center font-black text-sm shadow-sm transition-transform duration-300 group-hover:scale-110"
                                                :class="getAvatarColor(user.name)"
                                            >
                                                {{ user.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-black text-slate-800 tracking-tight group-hover:text-indigo-600 transition-colors">{{ user.name }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap">
                                        <div class="text-sm text-slate-600 font-black tracking-tight">{{ user.company_name || '-' }}</div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap">
                                        <div class="text-sm text-slate-500 font-bold tracking-tight">{{ user.phone || '-' }}</div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap">
                                        <div class="text-sm text-slate-500 font-bold tracking-tight">{{ user.email }}</div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap text-center">
                                        <div v-if="user.is_master" class="inline-flex items-center px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border-2 border-indigo-100/50 shadow-sm">
                                            <ShieldCheck class="w-3 h-3 mr-1.5" />
                                            {{ $t('Master') }}
                                        </div>
                                        <div v-else class="inline-flex items-center px-4 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider bg-slate-50 text-slate-500 border-2 border-slate-100/50">
                                            {{ $t('User') }}
                                        </div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap text-center">
                                        <div class="flex flex-col items-center justify-center space-y-2">
                                            <ToggleSwitch
                                                :model-value="user.is_active"
                                                @change="toggleUserStatus(user)"
                                            />
                                            <div v-if="user.is_active !== false" class="text-[9px] font-black uppercase tracking-tighter text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-100">
                                                {{ $t('Active') }}
                                            </div>
                                            <div v-else class="text-[9px] font-black uppercase tracking-tighter text-rose-600 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-100">
                                                {{ $t('Inactive') }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap text-center">
                                        <div class="text-[11px] text-slate-400 font-black uppercase tracking-widest bg-slate-50/50 px-3 py-1 rounded-lg inline-block border border-slate-100">
                                            {{ formatDate(user.created_at) }}
                                        </div>
                                    </td>
                                    <td class="px-8 py-5 whitespace-nowrap text-right font-medium">
                                        <div class="flex items-center justify-center space-x-1">
                                            <button
                                                @click="openWhatsApp(user.phone)"
                                                :disabled="user.id === $page.props.auth.user.id || !user.phone"
                                                class="p-2.5 border border-transparent rounded-xl transition-all active:scale-90"
                                                :class="user.id === $page.props.auth.user.id || !user.phone
                                                    ? 'text-slate-200 cursor-not-allowed'
                                                    : 'text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 hover:border-emerald-100'"
                                                :title="user.id === $page.props.auth.user.id ? $t('Cannot message yourself') : $t('WhatsApp')"
                                            >
                                                <MessageCircle class="w-4 h-4" />
                                            </button>
                                            <Link
                                                :href="route('admin.users.edit', user.id)"
                                                class="p-2.5 text-slate-400 hover:text-indigo-600 hover:bg-white hover:shadow-sm hover:border-slate-200 border border-transparent rounded-xl transition-all active:scale-90"
                                                :title="$t('Edit User')"
                                            >
                                                <Edit2 class="w-4 h-4" />
                                            </Link>
                                            <button
                                                @click="confirmDeleteAction(user)"
                                                class="p-2.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 hover:border-rose-100 border border-transparent rounded-xl transition-all active:scale-90"
                                                :title="$t('Delete User')"
                                            >
                                                <Trash2 class="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Empty State -->
                                <tr v-if="users.length === 0">
                                    <td colspan="8" class="px-8 py-20 text-center">
                                        <div class="inline-flex items-center justify-center w-20 h-20 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200 mb-4">
                                            <Search class="w-8 h-8 text-slate-300" />
                                        </div>
                                        <h3 class="text-lg font-black text-slate-800">{{ $t('No users found') }}</h3>
                                        <p class="text-slate-500 font-medium mt-1">{{ $t('Try adjusting your search or clearing the filters.') }}</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reusable Confirmation Modal -->
        <ConfirmationModal
            :show="showConfirmDelete"
            :title="$t('Delete User')"
            :message="$t('Are you sure you want to delete this user? This action cannot be undone.')"
            :confirm-text="$t('Delete')"
            :cancel-text="$t('Cancel')"
            :loading="isDeleting"
            @close="showConfirmDelete = false"
            @confirm="deleteUser"
        />
    </DashboardLayout>
</template>
