<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Reset your password')" />

        <div class="mb-8">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $t('Recover access') }}</h2>
            <p class="mt-2 text-sm text-gray-500">
                {{ $t('Forgot password help text') }}
            </p>
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
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

            <div class="mt-8">
                <PrimaryButton
                    class="w-full justify-center py-3.5 bg-blue-600 hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 rounded-xl text-sm font-bold tracking-wide shadow-lg shadow-blue-100 transition-all"
                    :class="{ 'opacity-75 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('Email Password Reset Link') }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
