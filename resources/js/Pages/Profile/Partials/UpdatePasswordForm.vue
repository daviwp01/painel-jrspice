<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Lock, ShieldCheck, Key, Save, Loader2 } from 'lucide-vue-next';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('profile.password'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};
</script>

<template>
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md">
        <header class="px-8 py-6 bg-slate-50/50 border-b border-slate-100 flex items-center space-x-4">
            <div class="p-2.5 bg-amber-100 rounded-lg text-amber-600">
                <Lock class="w-6 h-6" />
            </div>
            <div>
                <h2 class="text-xl font-black text-slate-800 tracking-tight">{{ $t('Update Password') }}</h2>
                <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mt-0.5">
                    {{ $t('Ensure your account is using a long, random password to stay secure.') }}
                </p>
            </div>
        </header>

        <form @submit.prevent="updatePassword" class="p-8 space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="space-y-2">
                    <InputLabel for="current_password" :value="$t('Current Password')" class="text-xs font-black text-slate-500 uppercase tracking-widest ml-1" />
                    <PasswordInput
                        id="current_password"
                        ref="currentPasswordInput"
                        v-model="form.current_password"
                        type="password"
                        class="block w-full py-4 px-5 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium"
                        autocomplete="current-password"
                    />
                    <InputError :message="form.errors.current_password" class="mt-2" />
                </div>

                <div class="space-y-2">
                    <InputLabel for="password" :value="$t('New Password')" class="text-xs font-black text-slate-500 uppercase tracking-widest ml-1" />
                    <PasswordInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="block w-full py-4 px-5 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="space-y-2">
                    <InputLabel for="password_confirmation" :value="$t('Confirm Password')" class="text-xs font-black text-slate-500 uppercase tracking-widest ml-1" />
                    <PasswordInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="block w-full py-4 px-5 rounded-xl bg-slate-50 border-slate-200 focus:bg-white focus:border-indigo-500 focus:ring-4 focus:ring-indigo-50/50 transition-all font-medium"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password_confirmation" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center justify-end border-t border-slate-100 pt-6">
                <PrimaryButton
                    :disabled="form.processing || !form.isDirty"
                    class="px-12 py-4 text-base font-black uppercase tracking-[0.2em] rounded-xl bg-indigo-600 shadow-xl shadow-indigo-100 transition-all hover:scale-[1.02] active:scale-[0.98] disabled:opacity-30 disabled:grayscale disabled:cursor-not-allowed group"
                >
                    <Loader2 v-if="form.processing" class="w-5 h-5 mr-3 animate-spin" />
                    <Key v-else class="w-5 h-5 mr-3 group-hover:rotate-12 transition-transform" />
                    {{ $t('Update Password') }}
                </PrimaryButton>
            </div>
        </form>
    </section>
</template>
