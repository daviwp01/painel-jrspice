<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    phone: '',
    company_name: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Sign up')" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $t('Create an account') }}</h2>
            <p class="mt-2 text-sm text-gray-500">
                {{ $t('Get started with your free account today.') }}
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="space-y-5">
                <div>
                    <InputLabel for="name" :value="$t('Full Name')" class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2" />
                    <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        :placeholder="$t('John Doe')"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="email" :value="$t('Email address')" class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                        v-model="form.email"
                        required
                        autocomplete="username"
                        :placeholder="$t('name@company.com')"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <InputLabel for="phone" :value="$t('Phone')" class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2" />
                        <TextInput
                            id="phone"
                            type="text"
                            class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                            v-model="form.phone"
                            :placeholder="$t('+55 11 99999-9999')"
                        />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>

                    <div>
                        <InputLabel for="company_name" :value="$t('Company Name')" class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2" />
                        <TextInput
                            id="company_name"
                            type="text"
                            class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                            v-model="form.company_name"
                            :placeholder="$t('Company Ltd.')"
                        />
                        <InputError class="mt-2" :message="form.errors.company_name" />
                    </div>
                </div>

                <div>
                    <InputLabel for="password" :value="$t('Password')" class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2" />
                    <PasswordInput
                        id="password"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div>
                    <InputLabel
                        for="password_confirmation"
                        :value="$t('Confirm Password')"
                        class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2"
                    />
                    <PasswordInput
                        id="password_confirmation"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <InputError
                        class="mt-2"
                        :message="form.errors.password_confirmation"
                    />
                </div>
            </div>

            <div class="mt-8">
                <PrimaryButton
                    class="w-full justify-center py-3.5 bg-blue-600 hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 rounded-xl text-sm font-bold tracking-wide shadow-lg shadow-blue-100 transition-all"
                    :class="{ 'opacity-75 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('Create account') }}
                </PrimaryButton>
            </div>

            <p class="mt-8 text-center text-xs text-gray-500">
                {{ $t('Already have an account?') }}
                <Link :href="route('login')" class="font-bold text-blue-600 hover:text-blue-800 transition-colors">{{ $t('Sign in') }}</Link>
            </p>
        </form>
    </GuestLayout>
</template>
