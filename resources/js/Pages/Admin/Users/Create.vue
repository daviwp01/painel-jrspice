<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { onMounted, ref, computed } from 'vue';
import axios from 'axios';
import {
    Loader2,
    AlertCircle,
    User,
    Lock,
    ShieldCheck,
    ArrowLeft,
    Layout,
    Phone,
    Building2,
    Check
} from 'lucide-vue-next';

const props = defineProps({
    available_pages: {
        type: Array,
        default: () => []
    },
    default_allowed_pages: {
        type: Array,
        default: () => []
    }
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    phone: '',
    company_name: '',
    is_master: false,
    allowed_pages: Array.isArray(props.default_allowed_pages) ? [...props.default_allowed_pages] : [],
});

// Removed axios call since we use props.available_pages
const isLoadingPages = ref(false);

const submit = () => {
    form.post(route('admin.users.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const togglePageAccess = (slug) => {
    if (form.allowed_pages.includes(slug)) {
        form.allowed_pages = form.allowed_pages.filter(p => p !== slug);
    } else {
        form.allowed_pages.push(slug);
    }
};

const isFormValid = computed(() => {
    const basicInfo = form.name.trim() !== '' &&
                     form.email.trim() !== '' &&
                     form.password !== '' &&
                     form.password === form.password_confirmation;

    // Permissões são válidas mesmo vazias (caem no padrão do sistema)
    const permissions = true;

    return basicInfo && permissions;
});
</script>

<template>
    <Head :title="$t('Add New User')" />

    <DashboardLayout>
        <template #header>
            <h2 class="hidden md:block text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $t('Add New User') }}</h2>
        </template>

        <div class="p-6 md:p-8 space-y-8 w-full max-w-none">

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
                <div class="mb-8">
                    <h1 class="text-2xl font-black text-slate-900 tracking-tight flex items-center uppercase">
                        <span class="bg-blue-600 w-1.5 h-7 rounded-full mr-3"></span>
                        {{ $t('Add New User') }}
                    </h1>
                    <p class="mt-1 text-slate-500 font-medium text-sm">{{ $t('Create a new user or master admin to manage the platform.') }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-8">
                    <!-- Section: Account Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden transition-all hover:shadow-md">
                        <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-100 flex items-center space-x-3">
                            <div class="p-2 bg-blue-100 rounded-lg text-blue-600">
                                <User class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-800 tracking-tight">{{ $t('Account Information') }}</h3>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">{{ $t('Basic profile data') }}</p>
                            </div>
                        </div>

                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <InputLabel for="name" :value="$t('Name')" class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1" />
                                <TextInput
                                    id="name"
                                    type="text"
                                    class="block w-full py-3 px-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50/50 transition-all text-sm font-bold"
                                    v-model="form.name"
                                    required
                                    autofocus
                                    autocomplete="name"
                                    :placeholder="$t('Full Name')"
                                />
                                <InputError :message="form.errors.name" />
                            </div>

                            <div class="space-y-2">
                                <InputLabel for="email" :value="$t('Email')" class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="block w-full py-3 px-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50/50 transition-all text-sm font-bold"
                                    v-model="form.email"
                                    required
                                    autocomplete="username"
                                    placeholder="user@example.com"
                                />
                                <InputError :message="form.errors.email" />
                            </div>

                            <div class="space-y-2">
                                <InputLabel for="phone" :value="$t('Phone')" class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1" />
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                        <Phone class="w-4 h-4" />
                                    </div>
                                    <TextInput
                                        id="phone"
                                        type="text"
                                        class="block w-full py-3 pl-11 pr-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50/50 transition-all text-sm font-bold"
                                        v-model="form.phone"
                                        :placeholder="$t('+55 11 99999-9999')"
                                    />
                                </div>
                                <InputError :message="form.errors.phone" />
                            </div>

                            <div class="space-y-2">
                                <InputLabel for="company_name" :value="$t('Company Name')" class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1" />
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-blue-500 transition-colors">
                                        <Building2 class="w-4 h-4" />
                                    </div>
                                    <TextInput
                                        id="company_name"
                                        type="text"
                                        class="block w-full py-3 pl-11 pr-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50/50 transition-all text-sm font-bold"
                                        v-model="form.company_name"
                                        @input="form.company_name = form.company_name.replace(/[0-9@]/g, '')"
                                        :placeholder="$t('Company Ltd.')"
                                    />
                                </div>
                                <InputError :message="form.errors.company_name" />
                            </div>
                        </div>
                    </div>

                    <!-- Section: Security -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden transition-all hover:shadow-md">
                        <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-100 flex items-center space-x-3">
                            <div class="p-2 bg-amber-100 rounded-lg text-amber-600">
                                <Lock class="w-5 h-5" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-800 tracking-tight">{{ $t('Security') }}</h3>
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">{{ $t('Access credentials') }}</p>
                            </div>
                        </div>

                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <InputLabel for="password" :value="$t('Password')" class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1" />
                                <PasswordInput
                                    id="password"
                                    class="block w-full py-3 px-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50/50 transition-all text-sm font-bold"
                                    v-model="form.password"
                                    required
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                />
                                <InputError :message="form.errors.password" />
                            </div>

                            <div class="space-y-2">
                                <InputLabel for="password_confirmation" :value="$t('Confirm Password')" class="text-xs font-bold text-slate-500 uppercase tracking-widest ml-1" />
                                <PasswordInput
                                    id="password_confirmation"
                                    class="block w-full py-3 px-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50/50 transition-all text-sm font-bold"
                                    v-model="form.password_confirmation"
                                    required
                                    autocomplete="new-password"
                                    placeholder="••••••••"
                                />
                                <InputError :message="form.errors.password_confirmation" />
                            </div>
                        </div>
                    </div>

                    <!-- Section: Permissions -->
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden transition-all hover:shadow-md">
                        <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-100 flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="p-2 bg-emerald-100 rounded-lg text-emerald-600">
                                    <ShieldCheck class="w-5 h-5" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-slate-800 tracking-tight">{{ $t('Permissions') }}</h3>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">{{ $t('Access levels and reports') }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Master Toggle Card -->
                                <div
                                    class="relative p-5 rounded-xl border-2 transition-all cursor-pointer flex items-center justify-between group"
                                    :class="form.is_master ? 'border-blue-500 bg-blue-50/30' : 'border-slate-100 bg-slate-50/50 hover:border-slate-200'"
                                    @click="form.is_master = !form.is_master"
                                >
                                    <div class="flex items-center space-x-4">
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center transition-colors" :class="form.is_master ? 'bg-blue-600 text-white shadow-lg shadow-blue-100' : 'bg-slate-200 text-slate-400'">
                                            <ShieldCheck class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-slate-800 group-hover:text-blue-600 transition-colors">{{ $t('Grant Master Privileges') }}</p>
                                            <p class="text-[10px] text-slate-500 font-bold tracking-tight mt-0.5 break-words line-clamp-1 truncate">{{ $t('Grants full administrative access.') }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center pr-2">
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all" :class="form.is_master ? 'bg-blue-600 border-blue-600' : 'border-slate-300'">
                                            <CheckCircle2 v-if="form.is_master" class="w-3 h-3 text-white" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reports Selection Grid -->
                            <div v-if="!form.is_master" class="space-y-6 animate-in fade-in slide-in-from-top-4 duration-500">
                                <!-- Grid Section Header -->
                                <div class="flex items-center justify-between px-1">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center">
                                            <Layout class="w-5 h-5" />
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-800 uppercase tracking-tight">{{ $t('Relatórios Permitidos') }}</h4>
                                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">{{ $t('Define o acesso inicial deste usuário aos Dashboards.') }}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center">
                                        <span v-if="form.allowed_pages.length === 0" class="text-[9px] text-blue-600 font-bold flex items-center bg-blue-50 px-3 py-1 rounded-full border border-blue-100">
                                            <AlertCircle class="w-3 h-3 mr-1.5" />
                                            {{ $t('DEFAULT (SYSTEM)') }}
                                        </span>
                                        <span v-else class="text-[9px] text-emerald-600 font-bold flex items-center bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100">
                                            <Check class="w-3 h-3 mr-1.5" />
                                            {{ form.allowed_pages.length }} {{ $t('SELECTED') }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Actual Grid -->
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div
                                        v-for="page in available_pages.filter(p => !p.slug.toLowerCase().includes('home'))"
                                        :key="page.id"
                                        @click="togglePageAccess(page.slug)"
                                        class="relative p-5 rounded-2xl border-2 transition-all cursor-pointer flex items-center gap-4"
                                        :class="form.allowed_pages.includes(page.slug)
                                            ? 'border-blue-500 bg-blue-50/30 shadow-sm'
                                            : 'border-slate-100 bg-slate-50/30 hover:border-slate-200'"
                                    >
                                        <div
                                            class="w-8 h-8 rounded-xl flex items-center justify-center transition-all"
                                            :class="form.allowed_pages.includes(page.slug)
                                                ? 'bg-blue-500 text-white shadow-md shadow-blue-100'
                                                : 'bg-white border border-slate-200 text-slate-300'"
                                        >
                                            <Check v-if="form.allowed_pages.includes(page.slug)" class="w-4 h-4" />
                                            <div v-else class="w-2 h-2 rounded-full bg-slate-200"></div>
                                        </div>
                                        
                                        <div class="flex-1 min-w-0">
                                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-tight leading-none mb-1 truncate">{{ page.title }}</h4>
                                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest truncate">{{ page.slug }}</p>
                                        </div>

                                        <div v-if="form.allowed_pages.includes(page.slug)" class="absolute top-2 right-2">
                                             <div class="w-1.5 h-1.5 bg-blue-500 rounded-full animate-ping"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Master Alert -->
                            <div v-else class="p-6 bg-amber-50 rounded-xl border border-amber-100 flex items-start space-x-4 animate-in fade-in zoom-in duration-300">
                                <AlertCircle class="w-6 h-6 text-amber-600 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-sm font-bold text-amber-900 uppercase tracking-tight">{{ $t('Full Access Enabled') }}</p>
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
                            class="text-xs font-bold text-slate-400 hover:text-slate-600 uppercase tracking-widest transition-colors"
                        >
                            {{ $t('Cancel') }}
                        </Link>

                        <PrimaryButton
                            :disabled="!isFormValid || form.processing"
                            class="px-8 py-3 text-sm font-bold uppercase tracking-[0.2em] rounded-xl bg-blue-600 shadow-xl shadow-blue-100 transition-all hover:scale-[1.02] active:scale-[0.98] disabled:opacity-30 disabled:grayscale disabled:cursor-not-allowed group"
                        >
                            <Loader2 v-if="form.processing" class="w-4 h-4 mr-2.5 animate-spin" />
                            <User v-else class="w-4 h-4 mr-2.5 group-hover:rotate-12 transition-transform" />
                            {{ $t('Create User') }}
                        </PrimaryButton>
                    </div>
                </form>
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
