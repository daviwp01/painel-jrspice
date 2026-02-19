<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { onMounted, ref, computed } from 'vue';
import axios from 'axios';
import {
    Loader2,
    AlertCircle,
    User,
    Lock,
    ShieldCheck,
    ArrowLeft,
    CheckCircle2,
    Layout,
    Save,
    UserCheck,
    UserX,
    Phone,
    Building2
} from 'lucide-vue-next';

const props = defineProps({
    user: Object,
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    phone: props.user.phone || '',
    company_name: props.user.company_name || '',
    is_master: Boolean(props.user.is_master),
    is_active: Boolean(props.user.is_active !== undefined ? props.user.is_active : true),
    allowed_pages: Array.isArray(props.user.allowed_pages) ? props.user.allowed_pages : [],
});

const availablePages = ref([]);
const isLoadingPages = ref(true);

onMounted(async () => {
    try {
        const response = await axios.get(route('powerbi.pages'));
        availablePages.value = response.data;
    } catch (error) {
        console.error('Failed to load Power BI pages', error);
    } finally {
        isLoadingPages.value = false;
    }
});

const submit = () => {
    form.put(route('admin.users.update', props.user.id), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const togglePageAccess = (pageName) => {
    if (form.allowed_pages.includes(pageName)) {
        form.allowed_pages = form.allowed_pages.filter(p => p !== pageName);
    } else {
        form.allowed_pages.push(pageName);
    }
};

const isFormValid = computed(() => {
    const basicInfo = form.name.trim() !== '' &&
                     form.email.trim() !== '';

    const permissions = form.is_master || (form.allowed_pages && form.allowed_pages.length > 0);

    // In edit mode, we also want to ensure something was actually changed (isDirty)
    return basicInfo && permissions && form.isDirty;
});
</script>

<template>
    <Head :title="$t('Edit User')" />

    <DashboardLayout>
        <div class="py-12 bg-[#f8fafc] min-h-screen">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Breadcrumbs/Back -->
                <div class="mb-8">
                    <Link
                        :href="route('admin.users.index')"
                        class="inline-flex items-center text-sm font-bold text-slate-400 hover:text-indigo-600 transition-colors group"
                    >
                        <ArrowLeft class="w-4 h-4 mr-2 transform group-hover:-translate-x-1 transition-transform" />
                        {{ $t('Back to List') }}
                    </Link>
                </div>

                <!-- Page Header -->
                <div class="mb-10">
                    <h1 class="text-4xl font-black text-slate-900 tracking-tight flex items-center">
                        <span class="bg-indigo-600 w-2 h-10 rounded-full mr-4"></span>
                        {{ $t('Edit User') }}
                    </h1>
                    <p class="mt-3 text-slate-500 font-medium text-lg">{{ $t('Update user details and permissions.') }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-8">
                    <!-- Section: Account Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden transition-all hover:shadow-md">
                        <div class="px-8 py-6 bg-slate-50/50 border-b border-slate-100 flex items-center space-x-4">
                            <div class="p-2.5 bg-indigo-100 rounded-lg text-indigo-600">
                                <User class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-slate-800 tracking-tight">{{ $t('Account Information') }}</h3>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">{{ $t('Basic profile data') }}</p>
                            </div>
                        </div>

                        <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <InputLabel for="name" :value="$t('Name')" class="text-xs font-black text-slate-500 uppercase tracking-widest ml-1" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="block w-full py-4 px-5 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium"
                                    v-model="form.name"
                                    required
                                    autofocus
                                    autocomplete="name"
                                    :placeholder="$t('Full Name')"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <InputLabel for="email" :value="$t('Email')" class="text-xs font-black text-slate-500 uppercase tracking-widest ml-1" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="block w-full py-4 px-5 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium"
                                    v-model="form.email"
                                    required
                                    autocomplete="username"
                                    placeholder="user@example.com"
                                />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="space-y-2">
                                <InputLabel for="phone" :value="$t('Phone')" class="text-xs font-black text-slate-500 uppercase tracking-widest ml-1" />
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
                                        <Phone class="w-5 h-5" />
                                    </div>
                                    <TextInput
                                        id="phone"
                                        type="text"
                                        class="block w-full py-4 pl-12 pr-5 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium"
                                        v-model="form.phone"
                                        :placeholder="$t('+55 11 99999-9999')"
                                    />
                                </div>
                                <InputError :message="form.errors.phone" />
                            </div>

                            <div class="space-y-2">
                                <InputLabel for="company_name" :value="$t('Company Name')" class="text-xs font-black text-slate-500 uppercase tracking-widest ml-1" />
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
                                        <Building2 class="w-5 h-5" />
                                    </div>
                                    <TextInput
                                        id="company_name"
                                        type="text"
                                        class="block w-full py-4 pl-12 pr-5 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium"
                                        v-model="form.company_name"
                                        :placeholder="$t('Company Ltd.')"
                                    />
                                </div>
                                <InputError :message="form.errors.company_name" />
                            </div>
                        </div>
                    </div>

                    <!-- Section: Security -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden transition-all hover:shadow-md">
                        <div class="px-8 py-6 bg-slate-50/50 border-b border-slate-100 flex items-center space-x-4">
                            <div class="p-2.5 bg-amber-100 rounded-lg text-amber-600">
                                <Lock class="w-6 h-6" />
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-slate-800 tracking-tight">{{ $t('Security') }}</h3>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">{{ $t('Change password (optional)') }}</p>
                            </div>
                        </div>

                        <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <InputLabel for="password" :value="$t('Password')" class="text-xs font-black text-slate-500 uppercase tracking-widest ml-1" />
                                <PasswordInput
                                    id="password"
                                    class="block w-full py-4 px-5 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium"
                                    v-model="form.password"
                                    autocomplete="new-password"
                                    :placeholder="$t('Leave blank to keep current')"
                                />
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="space-y-2">
                                <InputLabel for="password_confirmation" :value="$t('Confirm Password')" class="text-xs font-black text-slate-500 uppercase tracking-widest ml-1" />
                                <PasswordInput
                                    id="password_confirmation"
                                    class="block w-full py-4 px-5 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium"
                                    v-model="form.password_confirmation"
                                    autocomplete="new-password"
                                    :placeholder="$t('Leave blank to keep current')"
                                />
                                <InputError :message="form.errors.password_confirmation" />
                            </div>
                        </div>
                    </div>

                    <!-- Section: Permissions & Status -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden transition-all hover:shadow-md">
                        <div class="px-8 py-6 bg-slate-50/50 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <div class="p-2.5 bg-emerald-100 rounded-lg text-emerald-600">
                                    <ShieldCheck class="w-6 h-6" />
                                </div>
                                <div>
                                    <h3 class="text-xl font-black text-slate-800 tracking-tight">{{ $t('Permissions & Status') }}</h3>
                                    <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">{{ $t('Access levels and account status') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-8 space-y-10">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Is Active Card -->
                                <div
                                    class="relative p-6 rounded-xl border-2 transition-all cursor-pointer flex items-center justify-between group"
                                    :class="form.is_active ? 'border-emerald-500 bg-emerald-50/30' : 'border-rose-200 bg-rose-50/20 hover:border-rose-300'"
                                    @click="form.is_active = !form.is_active"
                                >
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 rounded-lg flex items-center justify-center transition-colors" :class="form.is_active ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-100' : 'bg-rose-600 text-white shadow-lg shadow-rose-100'">
                                            <UserCheck v-if="form.is_active" class="w-6 h-6" />
                                            <UserX v-else class="w-6 h-6" />
                                        </div>
                                        <div>
                                            <p class="text-base font-black text-slate-800 group-hover:text-emerald-700 transition-colors">{{ $t('Active Account') }}</p>
                                            <p class="text-xs text-slate-500 font-bold tracking-tight mt-0.5">{{ $t('Allow this user to log in and access the platform.') }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center pr-2">
                                        <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all" :class="form.is_active ? 'bg-emerald-600 border-emerald-600' : 'bg-rose-600 border-rose-600'">
                                            <CheckCircle2 class="w-4 h-4 text-white" />
                                        </div>
                                    </div>
                                </div>

                                <!-- Master Toggle Card -->
                                <div
                                    class="relative p-6 rounded-xl border-2 transition-all cursor-pointer flex items-center justify-between group"
                                    :class="form.is_master ? 'border-indigo-500 bg-indigo-50/30' : 'border-slate-100 bg-slate-50/50 hover:border-slate-200'"
                                    @click="form.is_master = !form.is_master"
                                >
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 rounded-lg flex items-center justify-center transition-colors" :class="form.is_master ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'bg-slate-200 text-slate-400'">
                                            <ShieldCheck class="w-6 h-6" />
                                        </div>
                                        <div>
                                            <p class="text-base font-black text-slate-800 group-hover:text-indigo-600 transition-colors">{{ $t('Grant Master Privileges') }}</p>
                                            <p class="text-xs text-slate-500 font-bold tracking-tight mt-0.5 text-ellipsis overflow-hidden break-words">{{ $t('Full administrative access.') }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center pr-2">
                                        <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all" :class="form.is_master ? 'bg-indigo-600 border-indigo-600' : 'border-slate-300'">
                                            <CheckCircle2 v-if="form.is_master" class="w-4 h-4 text-white" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reports Grid -->
                            <div v-if="!form.is_master" class="space-y-6 animate-in fade-in slide-in-from-top-4 duration-500">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-2">
                                        <Layout class="w-4 h-4 text-indigo-500" />
                                        <h4 class="text-sm font-black text-slate-800 uppercase tracking-widest">{{ $t('Allowed Reports') }}</h4>
                                    </div>
                                    <span v-if="form.allowed_pages && form.allowed_pages.length === 0" class="text-[10px] text-rose-600 font-black animate-pulse flex items-center bg-rose-50 px-3 py-1 rounded-full border border-rose-100">
                                        <AlertCircle class="w-3 h-3 mr-1" />
                                        {{ $t('REQUIRED TO SELECT ONE') }}
                                    </span>
                                    <span v-else class="text-[10px] text-emerald-600 font-black flex items-center bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100">
                                        <CheckCircle2 class="w-3 h-3 mr-1" />
                                        {{ form.allowed_pages ? form.allowed_pages.length : 0 }} {{ $t('SELECTED') }}
                                    </span>
                                </div>

                                <div v-if="isLoadingPages" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div v-for="n in 4" :key="n" class="h-16 bg-slate-50 rounded-xl animate-pulse border border-slate-100"></div>
                                </div>

                                <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div
                                        v-for="page in availablePages.filter(p => !p.displayName.toUpperCase().includes('HOME'))"
                                        :key="page.name"
                                        @click="togglePageAccess(page.name)"
                                        class="relative flex items-center p-4 rounded-xl border-2 transition-all cursor-pointer group hover:shadow-sm"
                                        :class="form.allowed_pages.includes(page.name) ? 'border-indigo-500 bg-indigo-50/30' : 'border-slate-100 bg-white hover:border-indigo-200'"
                                    >
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center transition-all mr-3" :class="form.allowed_pages.includes(page.name) ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-400'">
                                            <Layout class="w-5 h-5" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-black text-slate-800 truncate uppercase tracking-tight group-hover:text-indigo-700 transition-colors">
                                                {{ page.displayName.replace(/_/g, ' ') }}
                                            </p>
                                        </div>
                                        <div class="ml-2">
                                            <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all" :class="form.allowed_pages.includes(page.name) ? 'bg-indigo-600 border-indigo-600' : 'border-slate-200'">
                                                <CheckCircle2 v-if="form.allowed_pages.includes(page.name)" class="w-3 h-3 text-white" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Master Alert -->
                            <div v-else class="p-6 bg-amber-50 rounded-xl border border-amber-100 flex items-start space-x-4 animate-in fade-in zoom-in duration-300">
                                <AlertCircle class="w-6 h-6 text-amber-600 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-black text-amber-900 uppercase tracking-tight">{{ $t('Full Access Enabled') }}</p>
                                    <p class="text-sm text-amber-700 font-medium mt-1 leading-relaxed">
                                        {{ $t('As a Master Administrator, this user will have automatic access to all current and future reports in the system.') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Bar -->
                    <div class="flex items-center justify-end space-x-6 pt-6">
                        <Link
                            :href="route('admin.users.index')"
                            class="text-sm font-black text-slate-400 hover:text-slate-600 uppercase tracking-widest transition-colors"
                        >
                            {{ $t('Cancel') }}
                        </Link>

                        <PrimaryButton
                            :disabled="!isFormValid || form.processing"
                            class="px-12 py-4 text-base font-black uppercase tracking-[0.2em] rounded-xl bg-indigo-600 shadow-xl shadow-indigo-100 transition-all hover:scale-[1.02] active:scale-[0.98] disabled:opacity-30 disabled:grayscale disabled:cursor-not-allowed group"
                        >
                            <Loader2 v-if="form.processing" class="w-5 h-5 mr-3 animate-spin" />
                            <Save v-else class="w-5 h-5 mr-3 group-hover:rotate-12 transition-transform" />
                            {{ $t('Save Changes') }}
                        </PrimaryButton>
                    </div>
                </form>
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
@keyframes zoom-in { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.fade-in { animation-name: fade-in; }
.slide-in-from-top-4 { animation-name: slide-in-from-top-4; }
.zoom-in { animation-name: zoom-in; }
</style>
