<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { Mail, Save, Loader2, Server, Lock, User, AtSign, Shield, Send } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps({
    settings: {
        type: Object,
        default: () => ({})
    }
});

const form = useForm({
    settings: {
        mail_host: props.settings.mail_host || '',
        mail_port: props.settings.mail_port || '587',
        mail_username: props.settings.mail_username || '',
        mail_password: props.settings.mail_password || '',
        mail_encryption: props.settings.mail_encryption || 'tls',
        mail_from_address: props.settings.mail_from_address || '',
        mail_from_name: props.settings.mail_from_name || ''
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

const testing = ref(false);

const sendTestMail = () => {
    testing.value = true;
    router.post(route('admin.settings.test-mail'), {}, {
        preserveScroll: true,
        onFinish: () => {
            testing.value = false;
        }
    });
};
</script>

<template>
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md">
        <!-- Header -->
        <div class="px-8 py-6 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="p-3 bg-blue-100 rounded-xl text-blue-600 shadow-sm">
                    <Mail class="w-6 h-6" />
                </div>
                <div>
                    <h3 class="text-xl font-black text-slate-800 tracking-tight">{{ $t('SMTP Configuration') }}</h3>
                    <p class="text-xs text-slate-500 font-bold uppercase tracking-widest mt-0.5">{{ $t('Manage outgoing email server settings') }}</p>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="p-8 space-y-8">
            <!-- Server Connection -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1 flex items-center">
                        <Server class="w-3 h-3 mr-1" />
                        {{ $t('SMTP Host') }}
                    </label>
                    <input
                        v-model="form.settings.mail_host"
                        type="text"
                        placeholder="smtp.example.com"
                        class="w-full px-5 py-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50 transition-all font-medium text-sm"
                    >
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1 flex items-center">
                        <Shield class="w-3 h-3 mr-1" />
                        {{ $t('Port') }}
                    </label>
                    <input
                        v-model="form.settings.mail_port"
                        type="text"
                        placeholder="587"
                        class="w-full px-5 py-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50 transition-all font-medium text-sm"
                    >
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">
                        {{ $t('Encryption') }}
                    </label>
                    <select
                        v-model="form.settings.mail_encryption"
                        class="w-full px-5 py-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50 transition-all font-medium text-sm"
                    >
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                        <option value="null">{{ $t('None') }}</option>
                    </select>
                </div>
            </div>

            <!-- Authentication -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 bg-slate-50 rounded-2xl border border-slate-100">
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1 flex items-center">
                        <User class="w-3 h-3 mr-1" />
                        {{ $t('SMTP Username') }}
                    </label>
                    <input
                        v-model="form.settings.mail_username"
                        type="text"
                        placeholder="user@example.com"
                        class="w-full px-5 py-4 rounded-xl bg-white border-slate-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 transition-all font-medium text-sm"
                    >
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1 flex items-center">
                        <Lock class="w-3 h-3 mr-1" />
                        {{ $t('SMTP Password') }}
                    </label>
                    <input
                        v-model="form.settings.mail_password"
                        type="password"
                        placeholder="••••••••"
                        class="w-full px-5 py-4 rounded-xl bg-white border-slate-200 focus:border-blue-500 focus:ring-4 focus:ring-blue-50 transition-all font-medium text-sm"
                    >
                </div>
            </div>

            <!-- Sender Info -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1 flex items-center">
                        <AtSign class="w-3 h-3 mr-1" />
                        {{ $t('From Address') }}
                    </label>
                    <input
                        v-model="form.settings.mail_from_address"
                        type="email"
                        placeholder="notifications@jrspice.com"
                        class="w-full px-5 py-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50 transition-all font-medium text-sm"
                    >
                </div>

                <div class="space-y-2">
                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest ml-1">
                        {{ $t('From Name') }}
                    </label>
                    <input
                        v-model="form.settings.mail_from_name"
                        type="text"
                        placeholder="Jrspice Analytics"
                        class="w-full px-5 py-4 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50 transition-all font-medium text-sm"
                    >
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end space-x-4 pt-4 border-t border-slate-100">
                <button
                    type="button"
                    @click="sendTestMail"
                    :disabled="testing"
                    class="inline-flex items-center px-6 py-4 border-2 border-slate-200 text-slate-500 text-xs font-black uppercase tracking-widest rounded-xl hover:bg-slate-50 hover:border-slate-300 transition-all active:scale-[0.98] disabled:opacity-50"
                >
                    <Loader2 v-if="testing" class="w-4 h-4 mr-2 animate-spin" />
                    <Send v-else class="w-4 h-4 mr-2" />
                    {{ $t('Test Connection') }}
                </button>

                <button
                    type="submit"
                    :disabled="form.processing || !form.isDirty"
                    class="inline-flex items-center px-10 py-4 bg-blue-600 text-white text-xs font-black uppercase tracking-[0.2em] rounded-xl hover:bg-blue-700 transition-all hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-blue-100 disabled:opacity-30 disabled:grayscale"
                >
                    <Loader2 v-if="form.processing" class="w-4 h-4 mr-2 animate-spin" />
                    <Save v-else class="w-4 h-4 mr-2" />
                    {{ $t('Save SMTP Settings') }}
                </button>
            </div>
        </form>
    </section>
</template>
