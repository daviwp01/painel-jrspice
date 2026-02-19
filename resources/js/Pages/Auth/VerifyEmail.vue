<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Verify email')" />

        <div>
            <h2 class="mt-6 text-2xl font-bold tracking-tight text-gray-900">{{ $t('Verify email') }}</h2>
            <p class="mt-2 text-sm text-gray-600">
                {{ $t('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you?') }}
            </p>
        </div>

        <div
            class="mt-4 mb-4 text-sm font-medium text-green-600"
            v-if="verificationLinkSent"
        >
            {{ $t('A new verification link has been sent to the email address you provided during registration.') }}
        </div>

        <form @submit.prevent="submit" class="mt-8 space-y-6">
             <div>
                <PrimaryButton
                    class="w-full justify-center py-2.5"
                    :class="{ 'opacity-75 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('Resend Verification Email') }}
                </PrimaryButton>
            </div>

             <p class="mt-4 text-center text-sm text-gray-600">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="font-medium text-indigo-600 hover:text-indigo-500 transition-colors focus:outline-none"
                    >{{ $t('Log Out') }}</Link>
                >
            </p>
        </form>
    </GuestLayout>
</template>
