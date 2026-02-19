<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="block w-full rounded-full border-gray-200 bg-white px-5 py-2.5 text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-blue-400 focus:ring-2 focus:ring-blue-100 sm:text-sm transition-all duration-200"
        v-model="model"
        ref="input"
    />
</template>
