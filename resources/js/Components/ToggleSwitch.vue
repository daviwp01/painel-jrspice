<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const toggle = () => {
    if (!props.disabled) {
        emit('update:modelValue', !props.modelValue);
        emit('change', !props.modelValue);
    }
};
</script>

<template>
    <button
        type="button"
        @click="toggle"
        :disabled="disabled"
        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        :class="modelValue ? 'bg-indigo-600' : 'bg-slate-200'"
    >
        <span
            aria-hidden="true"
            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
            :class="modelValue ? 'translate-x-5' : 'translate-x-0'"
        />
    </button>
</template>
