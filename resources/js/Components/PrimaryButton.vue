<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
    processing: {
        type: Boolean,
        default: false
    }
});
</script>

<template>
    <button
        :type="type"
        class="flex w-full justify-center items-center rounded-full bg-[#1a73e8] px-6 py-3 text-sm font-bold leading-6 text-white shadow-md hover:bg-[#1557b0] hover:shadow-lg focus:outline-none focus:ring-4 focus:ring-blue-100 transition-all duration-300 transform active:scale-[0.98] disabled:opacity-75 disabled:cursor-not-allowed"
        :disabled="processing"
    >
        <svg v-if="processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <slot />
    </button>
</template>
