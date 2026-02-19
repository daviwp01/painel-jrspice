<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Confirm Password')" />

        <div>
            <h2 class="mt-6 text-2xl font-bold tracking-tight text-gray-900">{{ $t('Confirm') }}</h2>
            <p class="mt-2 text-sm text-gray-600">
                {{ $t('This is a secure area. Please confirm your password.') }}
            </p>
        </div>

        <form @submit.prevent="submit" class="mt-8 space-y-6">
            <div>
                <InputLabel for="password" :value="$t('Password')" class="text-gray-700 font-medium" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-2 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                    placeholder="••••••••"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div>
                <PrimaryButton
                    class="w-full justify-center py-2.5"
                    :class="{ 'opacity-75 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('Confirm') }}
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
