<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Reset password')" />

        <div>
            <h2 class="mt-6 text-2xl font-bold tracking-tight text-gray-900">{{ $t('Reset password') }}</h2>
            <p class="mt-2 text-sm text-gray-600">
                {{ $t('Create a new password for your account.') }}
            </p>
        </div>

        <form @submit.prevent="submit" class="mt-8 space-y-6">
            <div class="space-y-5">
                <div>
                    <InputLabel for="email" :value="$t('Email address')" class="text-gray-700 font-medium" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-2 block w-full"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        :placeholder="$t('name@company.com')"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" :value="$t('New Password')" class="text-gray-700 font-medium" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-2 block w-full"
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
                        :value="$t('Confirm New Password')"
                        class="text-gray-700 font-medium"
                    />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="mt-2 block w-full"
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

            <div>
                <PrimaryButton
                    class="w-full justify-center py-2.5"
                    :class="{ 'opacity-75 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('Reset password') }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
