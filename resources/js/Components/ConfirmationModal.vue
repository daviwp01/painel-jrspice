<script setup>
import Modal from './Modal.vue';
import DangerButton from './DangerButton.vue';
import SecondaryButton from './SecondaryButton.vue';
import { AlertTriangle } from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        required: true,
    },
    confirmText: {
        type: String,
        default: null,
    },
    cancelText: {
        type: String,
        default: null,
    },
    loading: {
        type: Boolean,
        default: false,
    }
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <Modal :show="show" @close="emit('close')" maxWidth="md">
        <div class="p-6">
            <div class="flex items-center space-x-4 mb-6">
                <div class="flex-shrink-0 w-12 h-12 bg-rose-100 rounded-full flex items-center justify-center">
                    <AlertTriangle class="w-6 h-6 text-rose-600" />
                </div>
                <div>
                    <h3 class="text-xl font-bold text-slate-900 leading-tight">
                        {{ title }}
                    </h3>
                    <p class="text-sm text-slate-500 mt-1">
                        {{ message }}
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-6 border-t border-slate-100">
                <SecondaryButton
                    @click="emit('close')"
                    :disabled="loading"
                    class="px-6 py-2.5 rounded-xl text-sm font-bold tracking-wide uppercase transition-all"
                >
                    {{ cancelText || $t('Cancel') }}
                </SecondaryButton>

                <DangerButton
                    @click="emit('confirm')"
                    :disabled="loading"
                    :class="{ 'opacity-75': loading }"
                    class="px-6 py-2.5 rounded-xl text-sm font-bold tracking-wide uppercase shadow-lg shadow-rose-100 transition-all transform hover:-translate-y-0.5"
                >
                    <span v-if="loading" class="mr-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                    {{ confirmText || $t('Confirm') }}
                </DangerButton>
            </div>
        </div>
    </Modal>
</template>
