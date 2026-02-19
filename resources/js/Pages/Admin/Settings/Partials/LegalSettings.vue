<script setup>
import { useForm } from '@inertiajs/vue3';
import { Save, Loader2, ShieldCheck, ScrollText } from 'lucide-vue-next';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { watch } from 'vue';

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({})
    }
});

const form = useForm({
    settings: {
        legal_privacy_policy: props.settings.legal_privacy_policy || '',
        legal_terms_of_use: props.settings.legal_terms_of_use || '',
    }
});

// Sync if props change
watch(() => props.settings, (newSettings) => {
    Object.keys(form.settings).forEach(key => {
        if (newSettings[key] !== undefined) {
            form.settings[key] = newSettings[key];
        }
    });
}, { deep: true });

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults();
        }
    });
};
</script>

<template>
    <section class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md">
        <!-- Header -->
        <div class="px-8 py-6 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-indigo-100 rounded-2xl text-indigo-600 shadow-sm">
                    <ShieldCheck class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-xl font-black text-slate-800 tracking-tight">{{ $t('Legal & Privacy') }}</h3>
                    <p class="text-xs text-slate-500 font-extrabold uppercase tracking-widest mt-0.5">{{ $t('Manage platform policies and conditions') }}</p>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="p-8 space-y-12">
            <!-- Privacy Policy -->
            <div class="space-y-4">
                <div class="flex items-center space-x-2">
                    <div class="p-2 bg-blue-50 rounded-lg text-blue-600">
                        <ShieldCheck class="w-4 h-4" />
                    </div>
                    <label class="text-sm font-black text-slate-700 uppercase tracking-widest">
                        {{ $t('Privacy Policy') }}
                    </label>
                </div>
                <RichTextEditor
                    v-model="form.settings.legal_privacy_policy"
                    :placeholder="$t('Enter the privacy policy content here...')"
                />
            </div>

            <!-- Terms of Use -->
            <div class="space-y-4">
                <div class="flex items-center space-x-2">
                    <div class="p-2 bg-amber-50 rounded-lg text-amber-600">
                        <ScrollText class="w-4 h-4" />
                    </div>
                    <label class="text-sm font-black text-slate-700 uppercase tracking-widest">
                        {{ $t('Terms of Use') }}
                    </label>
                </div>
                <RichTextEditor
                    v-model="form.settings.legal_terms_of_use"
                    :placeholder="$t('Enter the terms of use content here...')"
                />
            </div>

            <!-- Action Button -->
            <div class="flex justify-end pt-6 border-t border-slate-100">
                <button
                    type="submit"
                    :disabled="form.processing || !form.isDirty"
                    class="inline-flex items-center px-10 py-4 bg-indigo-600 text-white text-xs font-black uppercase tracking-[0.2em] rounded-2xl hover:bg-indigo-700 transition-all hover:scale-[1.02] active:scale-[0.98] shadow-xl shadow-indigo-100 disabled:opacity-30 disabled:grayscale"
                >
                    <Loader2 v-if="form.processing" class="w-4 h-4 mr-2 animate-spin" />
                    <Save v-else class="w-4 h-4 mr-2" />
                    {{ $t('Save Legal Content') }}
                </button>
            </div>
        </form>
    </section>
</template>
