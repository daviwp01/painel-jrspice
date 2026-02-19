<script setup>
import { useForm } from '@inertiajs/vue3';
import { Layout, Save, Loader2, Type, MessageSquare, MousePointer2, FileText } from 'lucide-vue-next';
import { watch } from 'vue';

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({})
    }
});

const form = useForm({
    settings: {
        email_template_title: props.settings.email_template_title || '',
        email_template_intro: props.settings.email_template_intro || '',
        email_template_btn_text: props.settings.email_template_btn_text || '',
        email_template_footer: props.settings.email_template_footer || ''
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
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md">
        <!-- Header -->
        <div class="px-8 py-6 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-indigo-100 rounded-xl text-indigo-600 shadow-sm">
                    <Layout class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-xl font-black text-slate-800 tracking-tight">{{ $t('Email Template Customization') }}</h3>
                    <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-0.5">{{ $t('Change how update notifications look') }}</p>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="p-8 space-y-8">
            <div class="grid grid-cols-1 gap-8">
                <!-- Email Title -->
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1 flex items-center">
                        <Type class="w-3 h-3 mr-1" />
                        {{ $t('Email Subject / Title') }}
                    </label>
                    <input
                        v-model="form.settings.email_template_title"
                        type="text"
                        :placeholder="$t('Reports Updated')"
                        class="w-full px-5 py-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 transition-all font-medium text-sm"
                    >
                </div>

                <!-- Introduction Content -->
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1 flex items-center">
                        <MessageSquare class="w-3 h-3 mr-1" />
                        {{ $t('Introduction Text') }}
                    </label>
                    <textarea
                        v-model="form.settings.email_template_intro"
                        rows="3"
                        :placeholder="$t('We are writing to inform you that our Power BI dashboards have been updated...')"
                        class="w-full px-5 py-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 transition-all font-medium text-sm resize-none"
                    ></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Button Text -->
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1 flex items-center">
                            <MousePointer2 class="w-3 h-3 mr-1" />
                            {{ $t('Button Text') }}
                        </label>
                        <input
                            v-model="form.settings.email_template_btn_text"
                            type="text"
                            :placeholder="$t('Access Dashboard')"
                            class="w-full px-5 py-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 transition-all font-medium text-sm"
                        >
                    </div>

                    <!-- Footer Text -->
                    <div class="space-y-2">
                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1 flex items-center">
                            <FileText class="w-3 h-3 mr-1" />
                            {{ $t('Short Footer (Optional)') }}
                        </label>
                        <input
                            v-model="form.settings.email_template_footer"
                            type="text"
                            placeholder="Jrspice Support Team"
                            class="w-full px-5 py-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50 transition-all font-medium text-sm"
                        >
                    </div>
                </div>
            </div>

            <!-- Preview Card (Simple) -->
            <div class="p-6 bg-slate-50 rounded-2xl border border-slate-100 space-y-4">
                <h4 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">{{ $t('Live Content Preview') }}</h4>
                <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm text-center">
                    <h2 class="text-xl font-black text-slate-800 mb-2">{{ form.settings.email_template_title || $t('Reports Updated') }}</h2>
                    <p class="text-sm text-slate-500 mb-6 leading-relaxed">{{ form.settings.email_template_intro || $t('Informative introduction text here...') }}</p>
                    <div class="inline-block px-8 py-3 bg-indigo-600 text-white text-xs font-black rounded-lg uppercase tracking-widest shadow-lg shadow-indigo-100">
                        {{ form.settings.email_template_btn_text || $t('Access Dashboard') }}
                    </div>
                </div>
            </div>

            <!-- Action Button -->
            <div class="flex justify-end pt-4 border-t border-slate-100">
                <button
                    type="submit"
                    :disabled="form.processing || !form.isDirty"
                    class="inline-flex items-center px-10 py-4 bg-indigo-600 text-white text-xs font-black uppercase tracking-[0.2em] rounded-xl hover:bg-indigo-700 transition-all hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-indigo-100 disabled:opacity-30 disabled:grayscale"
                >
                    <Loader2 v-if="form.processing" class="w-4 h-4 mr-2 animate-spin" />
                    <Save v-else class="w-4 h-4 mr-2" />
                    {{ $t('Save Email Content') }}
                </button>
            </div>
        </form>
    </section>
</template>
