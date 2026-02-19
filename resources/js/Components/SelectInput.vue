<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String, // Can be String or Object depending on usage
    required: true,
});

defineProps({
    options: {
        type: Array, // Array of { value: '...', label: '...' }
        required: true,
    },
    placeholder: {
        type: String,
        default: 'Select an option',
    },
});

const select = ref(null);

onMounted(() => {
    if (select.value.hasAttribute('autofocus')) {
        select.value.focus();
    }
});

defineExpose({ focus: () => select.value.focus() });
</script>

<template>
    <div class="relative">
        <select
            class="block w-full rounded-xl border-transparent bg-gray-50 pl-4 pr-10 py-3.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-100 focus:ring-2 focus:ring-inset focus:ring-indigo-600 focus:bg-white sm:text-sm sm:leading-6 transition-all duration-200 appearance-none cursor-pointer"
            v-model="model"
            ref="select"
        >
            <!-- Optional Placeholder -->
            <option value="" disabled selected v-if="placeholder">{{ placeholder }}</option>

            <option v-for="option in options" :key="option.value" :value="option.value">
                {{ option.label }}
            </option>
        </select>

        <!-- Custom Chevron Icon -->
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
            </svg>
        </div>
    </div>
</template>
