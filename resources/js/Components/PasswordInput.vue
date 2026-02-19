<script setup>
import { Eye, EyeOff } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';

defineOptions({
    inheritAttrs: false,
});

const model = defineModel({
    type: String,
    required: true,
});

const show = ref(false);
const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });

const toggleVisibility = () => {
    show.value = !show.value;
};
</script>

<template>
    <div class="relative w-full">
        <input
            v-bind="$attrs"
            :type="show ? 'text' : 'password'"
            class="block w-full border-gray-200 bg-white pr-10 text-gray-900 placeholder:text-gray-400 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 sm:text-sm transition-all duration-200"
            v-model="model"
            ref="input"
        />
        <button
            type="button"
            @click="toggleVisibility"
            class="absolute inset-y-0 right-0 flex items-center pr-3 z-10 text-gray-400 hover:text-gray-600 focus:outline-none transition-colors duration-200 cursor-pointer"
            tabindex="-1"
            :aria-label="show ? 'Hide password' : 'Show password'"
        >
            <Eye v-if="!show" class="h-5 w-5" />
            <EyeOff v-else class="h-5 w-5" />
        </button>
    </div>
</template>
