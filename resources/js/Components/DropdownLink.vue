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
        class="block w-full px-4 py-3 text-start text-sm font-bold text-slate-600 transition duration-150 ease-in-out hover:bg-slate-50 hover:text-blue-600 focus:outline-none"
    >
        <slot />
    </Link>
</template>
