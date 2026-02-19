<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block w-full px-4 py-2 text-start text-sm font-medium text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 hover:text-indigo-600 focus:outline-none"
    >
        <slot />
    </Link>
</template>
