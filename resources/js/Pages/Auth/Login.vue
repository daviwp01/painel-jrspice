<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Sign in')" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $t('Welcome back') }}</h2>
            <p class="mt-2 text-sm text-gray-500">
                {{ $t('Please enter your details to sign in.') }}
            </p>
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ $t(status) }}
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <div class="space-y-5">
                <div>
                    <InputLabel for="email" :value="$t('Email address')" class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        :placeholder="$t('name@company.com')"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" :value="$t('Password')" class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2" />
                    <PasswordInput
                        id="password"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                        v-model="form.password"
                        required
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>
            </div>

            <div class="flex items-center justify-between mt-6">
                <label class="flex items-center cursor-pointer group">
                    <Checkbox name="remember" v-model:checked="form.remember" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 transition-all" />
                    <span class="ms-2 text-xs font-medium text-gray-500 group-hover:text-gray-900 transition-colors">{{ $t('Remember me') }}</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors"
                >
                    {{ $t('Forgot password?') }}
                </Link>
            </div>

            <div class="mt-8">
                <PrimaryButton
                    class="w-full justify-center py-3.5 bg-blue-600 hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 rounded-xl text-sm font-bold tracking-wide shadow-lg shadow-blue-100 transition-all"
                    :class="{ 'opacity-75 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                    :processing="form.processing"
                >
                    {{ $t('Sign in') }}
                </PrimaryButton>
            </div>

            <p class="mt-8 text-center text-xs text-gray-500">
                {{ $t("Don't have an account?") }}
                <Link :href="route('register')" class="font-bold text-blue-600 hover:text-blue-800 transition-colors">{{ $t('Sign up') }}</Link>
            </p>
        </form>
    </GuestLayout>
</template>
