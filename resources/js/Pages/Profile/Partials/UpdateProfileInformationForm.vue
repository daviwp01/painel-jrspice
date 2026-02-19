<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { User, Mail, Save, Loader2, Phone, Building2 } from 'lucide-vue-next';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    phone: user.phone || '',
    company_name: user.company_name || '',
});
</script>

<template>
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md">
        <header class="px-8 py-6 bg-slate-50/50 border-b border-slate-100 flex items-center space-x-4">
            <div class="p-2.5 bg-indigo-100 rounded-lg text-indigo-600">
                <User class="w-6 h-6" />
            </div>
            <div>
                <h2 class="text-xl font-black text-slate-800 tracking-tight">{{ $t('Profile Information') }}</h2>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">
                    {{ $t("Update your account's profile information and email address.") }}
                </p>
            </div>
        </header>

        <form @submit.prevent="form.patch(route('profile.update'))" class="p-8 space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-2">
                    <InputLabel for="name" :value="$t('Name')" class="text-xs font-black text-slate-500 uppercase tracking-widest ml-1" />
                    <div class="relative">
                        <User class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                        <TextInput
                            id="name"
                            type="text"
                            class="block w-full py-4 pl-12 pr-5 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium"
                            v-model="form.name"
                            required
                            autofocus
                            autocomplete="name"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="space-y-2">
                    <InputLabel for="email" :value="$t('Email')" class="text-xs font-black text-slate-500 uppercase tracking-widest ml-1" />
                    <div class="relative">
                        <Mail class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                        <TextInput
                            id="email"
                            type="email"
                            class="block w-full py-4 pl-12 pr-5 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium"
                            v-model="form.email"
                            required
                            autocomplete="username"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="space-y-2">
                    <InputLabel for="phone" :value="$t('Phone')" class="text-xs font-black text-slate-500 uppercase tracking-widest ml-1" />
                    <div class="relative">
                        <Phone class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                        <TextInput
                            id="phone"
                            type="text"
                            class="block w-full py-4 pl-12 pr-5 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium"
                            v-model="form.phone"
                            autocomplete="tel"
                            :placeholder="$t('+55 11 99999-9999')"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>

                <div class="space-y-2">
                    <InputLabel for="company_name" :value="$t('Company Name')" class="text-xs font-black text-slate-500 uppercase tracking-widest ml-1" />
                    <div class="relative">
                        <Building2 class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                        <TextInput
                            id="company_name"
                            type="text"
                            class="block w-full py-4 pl-12 pr-5 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium"
                            v-model="form.company_name"
                            autocomplete="organization"
                            :placeholder="$t('Company Ltd.')"
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.company_name" />
                </div>
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="text-sm mt-2 text-slate-800">
                    {{ $t('Your email address is unverified.') }}
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="underline text-sm text-slate-600 hover:text-slate-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        {{ $t('Click here to re-send the verification email.') }}
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 font-medium text-sm text-green-600"
                >
                    {{ $t('A new verification link has been sent to your email address.') }}
                </div>
            </div>

            <div class="flex items-center justify-end border-t border-slate-100 pt-6">
                <PrimaryButton
                    :disabled="form.processing || !form.isDirty"
                    class="px-12 py-4 text-base font-black uppercase tracking-[0.2em] rounded-xl bg-indigo-600 shadow-xl shadow-indigo-100 transition-all hover:scale-[1.02] active:scale-[0.98] disabled:opacity-30 disabled:grayscale disabled:cursor-not-allowed group"
                >
                    <Loader2 v-if="form.processing" class="w-5 h-5 mr-3 animate-spin" />
                    <Save v-else class="w-5 h-5 mr-3 group-hover:rotate-12 transition-transform" />
                    {{ $t('Save Changes') }}
                </PrimaryButton>
            </div>
        </form>
    </section>
</template>
