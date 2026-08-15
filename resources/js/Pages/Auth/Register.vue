<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { ArrowRight, ArrowLeft } from 'lucide-vue-next';
const props = defineProps({
    importExperienceOptions: {
        type: Array,
        default: () => []
    },
    importVolumeOptions: {
        type: Array,
        default: () => []
    },
    decisionRoleOptions: {
        type: Array,
        default: () => []
    }
});
const currentStep = ref(1);
const countryCode = ref('+55');
const localPhone = ref('');

const form = useForm({
    name: '',
    email: '',
    phone: '',
    company_name: '',
    cargo: '',
    import_experience: '',
    import_volume: '',
    decision_role: '',
    password: '',
    password_confirmation: '',
});

const placeholderText = computed(() => {
    if (countryCode.value === '+55') {
        return '(11) 99999-9999';
    } else if (countryCode.value === '+1') {
        return '(555) 555-5555';
    } else if (countryCode.value === '+351') {
        return '912 345 678';
    } else {
        return 'Digite seu número';
    }
});

const maxInputLength = computed(() => {
    if (countryCode.value === '+55') return 15; // (XX) XXXXX-XXXX
    if (countryCode.value === '+1') return 14;  // (XXX) XXX-XXXX
    if (countryCode.value === '+351') return 11; // XXX XXX XXX
    return 15;
});

const formatPhone = (event) => {
    let value = event.target.value.replace(/\D/g, '');
    
    if (countryCode.value === '+55') {
        // Brasil: (XX) XXXXX-XXXX ou (XX) XXXX-XXXX
        value = value.substring(0, 11);
        if (value.length > 6) {
            value = `(${value.substring(0, 2)}) ${value.substring(2, 7)}-${value.substring(7)}`;
        } else if (value.length > 2) {
            value = `(${value.substring(0, 2)}) ${value.substring(2)}`;
        } else if (value.length > 0) {
            value = `(${value}`;
        }
    } else if (countryCode.value === '+1') {
        // EUA/Canadá: (XXX) XXX-XXXX
        value = value.substring(0, 10);
        if (value.length > 6) {
            value = `(${value.substring(0, 3)}) ${value.substring(3, 6)}-${value.substring(6)}`;
        } else if (value.length > 3) {
            value = `(${value.substring(0, 3)}) ${value.substring(3)}`;
        } else if (value.length > 0) {
            value = `(${value}`;
        }
    } else if (countryCode.value === '+351') {
        // Portugal: XXX XXX XXX
        value = value.substring(0, 9);
        if (value.length > 6) {
            value = `${value.substring(0, 3)} ${value.substring(3, 6)} ${value.substring(6)}`;
        } else if (value.length > 3) {
            value = `${value.substring(0, 3)} ${value.substring(3)}`;
        }
    } else {
        // Genérico: limite de 15 dígitos
        value = value.substring(0, 15);
    }
    
    event.target.value = value;
    localPhone.value = value;
    form.phone = `${countryCode.value} ${value}`.trim();
};

const onlyNumbers = (event) => {
    const key = event.key;
    if (!/^\d$/.test(key) && key !== 'Backspace' && key !== 'Delete' && key !== 'Tab' && key !== 'Enter' && !event.metaKey && !event.ctrlKey) {
        event.preventDefault();
    }
};

watch(countryCode, () => {
    localPhone.value = '';
    form.phone = '';
    form.clearErrors('phone');
});

// Watch backend validation errors to automatically focus on the step containing the error
watch(() => form.errors, (errors) => {
    if (errors && Object.keys(errors).length > 0) {
        const step1Fields = ['name', 'email', 'password', 'password_confirmation'];
        const step2Fields = ['phone', 'company_name', 'cargo'];
        const step3Fields = ['import_experience', 'import_volume', 'decision_role'];
        
        const firstErrorKey = Object.keys(errors)[0];
        
        if (step1Fields.includes(firstErrorKey)) {
            currentStep.value = 1;
        } else if (step2Fields.includes(firstErrorKey)) {
            currentStep.value = 2;
        } else if (step3Fields.includes(firstErrorKey)) {
            currentStep.value = 3;
        }
    }
}, { deep: true, immediate: true });

const validateStep = (step) => {
    let hasErrors = false;

    if (step === 1) {
        form.clearErrors('name', 'email', 'password', 'password_confirmation');

        if (!form.name || !form.name.trim()) {
            form.setError('name', 'Nome completo é obrigatório');
            hasErrors = true;
        }
        if (!form.email || !form.email.trim()) {
            form.setError('email', 'E-mail é obrigatório');
            hasErrors = true;
        } else if (!/\S+@\S+\.\S+/.test(form.email)) {
            form.setError('email', 'Formato de e-mail inválido');
            hasErrors = true;
        }
        if (!form.password) {
            form.setError('password', 'A senha é obrigatória');
            hasErrors = true;
        } else if (form.password.length < 8) {
            form.setError('password', 'A senha deve ter pelo menos 8 caracteres');
            hasErrors = true;
        }
        if (form.password !== form.password_confirmation) {
            form.setError('password_confirmation', 'A confirmação de senha não confere');
            hasErrors = true;
        }
    } else if (step === 2) {
        form.clearErrors('phone', 'company_name', 'cargo');

        if (!form.phone || !form.phone.trim()) {
            form.setError('phone', 'WhatsApp é obrigatório');
            hasErrors = true;
        }
        if (!form.company_name || !form.company_name.trim()) {
            form.setError('company_name', 'Nome da empresa é obrigatório');
            hasErrors = true;
        }
        if (!form.cargo || !form.cargo.trim()) {
            form.setError('cargo', 'Cargo é obrigatório');
            hasErrors = true;
        }
    } else if (step === 3) {
        form.clearErrors('import_experience', 'import_volume', 'decision_role');

        if (!form.import_experience) {
            form.setError('import_experience', 'Por favor, responda a pergunta 1');
            hasErrors = true;
        }
        if (!form.import_volume) {
            form.setError('import_volume', 'Por favor, responda a pergunta 2');
            hasErrors = true;
        }
        if (!form.decision_role) {
            form.setError('decision_role', 'Por favor, responda a pergunta 3');
            hasErrors = true;
        }
    }

    return !hasErrors;
};

const nextStep = () => {
    if (validateStep(currentStep.value)) {
        currentStep.value++;
    }
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const submit = () => {
    if (validateStep(3)) {
        form.post(route('register'), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('Sign up')" />

        <div class="mb-6">
            <h2 class="text-2xl font-bold tracking-tight text-gray-900">{{ $t('Create an account') }}</h2>
            <p class="mt-2 text-sm text-gray-500">
                {{ $t('Get started with your free account today.') }}
            </p>
        </div>

        <!-- Progress Steps Indicator -->
        <div class="mb-8 mt-2 px-1">
            <div class="flex items-center justify-between relative">
                <!-- Line background -->
                <div class="absolute left-0 top-4 -translate-y-1/2 w-full h-0.5 bg-gray-100 -z-10"></div>
                <!-- Line active progress -->
                <div class="absolute left-0 top-4 -translate-y-1/2 h-0.5 bg-blue-600 transition-all duration-500 -z-10" :style="{ width: ((currentStep - 1) / 2 * 100) + '%' }"></div>

                <!-- Step 1 -->
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs border-2 transition-all duration-300 z-10"
                        :class="currentStep >= 1 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-400 border-gray-200'">
                        1
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider mt-2" :class="currentStep >= 1 ? 'text-blue-600' : 'text-gray-400'">Acesso</span>
                </div>

                <!-- Step 2 -->
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs border-2 transition-all duration-300 z-10"
                        :class="currentStep >= 2 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-400 border-gray-200'">
                        2
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider mt-2" :class="currentStep >= 2 ? 'text-blue-600' : 'text-gray-400'">Empresa</span>
                </div>

                <!-- Step 3 -->
                <div class="flex flex-col items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs border-2 transition-all duration-300 z-10"
                        :class="currentStep >= 3 ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-400 border-gray-200'">
                        3
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider mt-2" :class="currentStep >= 3 ? 'text-blue-600' : 'text-gray-400'">Perfil</span>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Passo 1: Informações de Acesso -->
            <div v-show="currentStep === 1" class="space-y-5">
                <div>
                    <InputLabel for="name" :value="$t('Full Name')" class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2" />
                    <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        :placeholder="$t('John Doe')"
                    />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="email" :value="$t('Email address')" class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                        v-model="form.email"
                        required
                        autocomplete="username"
                        :placeholder="$t('name@company.com')"
                    />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" :value="$t('Password')" class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2" />
                    <PasswordInput
                        id="password"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div>
                    <InputLabel
                        for="password_confirmation"
                        :value="$t('Confirm Password')"
                        class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2"
                    />
                    <PasswordInput
                        id="password_confirmation"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="••••••••"
                    />
                    <InputError
                        class="mt-2"
                        :message="form.errors.password_confirmation"
                    />
                </div>
            </div>

            <!-- Passo 2: Dados Profissionais -->
            <div v-show="currentStep === 2" class="space-y-5 animate-in fade-in duration-300">
                <div>
                    <InputLabel for="phone" value="WhatsApp" class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2" />
                    <div class="relative flex mt-1 rounded-xl shadow-sm">
                        <!-- Selector de DDI -->
                        <div class="absolute inset-y-0 left-0 flex items-center">
                            <select
                                v-model="countryCode"
                                class="h-full py-0 pl-4 pr-7 border-transparent bg-transparent text-slate-500 font-bold text-sm rounded-l-xl focus:ring-0 focus:border-transparent cursor-pointer"
                            >
                                <option value="+55">🇧🇷 +55</option>
                                <option value="+1">🇺🇸 +1</option>
                                <option value="+351">🇵🇹 +351</option>
                                <option value="+54">🇦🇷 +54</option>
                                <option value="+595">🇵🇾 +595</option>
                                <option value="+598">🇺🇾 +598</option>
                                <option value="+34">🇪🇸 +34</option>
                                <option value="+49">🇩🇪 +49</option>
                                <option value="+44">🇬🇧 +44</option>
                                <option value="+86">🇨🇳 +86</option>
                            </select>
                        </div>
                        
                        <!-- Telefone Input -->
                        <input
                            id="phone"
                            type="text"
                            class="block w-full pl-28 pr-4 py-3 bg-white border border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium focus:outline-none"
                            :value="localPhone"
                            @input="formatPhone"
                            @keypress="onlyNumbers"
                            :placeholder="placeholderText"
                            :maxlength="maxInputLength"
                            required
                        />
                    </div>
                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>

                <div>
                    <InputLabel for="company_name" value="Empresa" class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2" />
                    <TextInput
                        id="company_name"
                        type="text"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                        v-model="form.company_name"
                        @input="form.company_name = form.company_name.replace(/[0-9@]/g, '')"
                        placeholder="Nome da Empresa"
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.company_name" />
                </div>

                <div>
                    <InputLabel for="cargo" value="Cargo" class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2" />
                    <TextInput
                        id="cargo"
                        type="text"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm placeholder-gray-300 transition-all font-medium"
                        v-model="form.cargo"
                        placeholder="Ex: Gerente de Compras, Diretor, etc."
                        required
                    />
                    <InputError class="mt-2" :message="form.errors.cargo" />
                </div>
            </div>

            <!-- Passo 3: Perfil de Importação -->
            <div v-show="currentStep === 3" class="space-y-5 animate-in fade-in duration-300">
                <div>
                    <InputLabel for="import_experience" value="Sua empresa já importa insumos alimentícios?" class="text-xs font-bold text-gray-500 mb-2" />
                    <select
                        id="import_experience"
                        v-model="form.import_experience"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm transition-all font-medium text-gray-700 shadow-sm cursor-pointer"
                        required
                    >
                        <option value="" disabled selected>Selecione uma opção...</option>
                        <option v-for="option in importExperienceOptions" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.import_experience" />
                </div>

                <div>
                    <InputLabel for="import_volume" value="Volume aproximado por embarque" class="text-xs font-bold text-gray-500 mb-2" />
                    <select
                        id="import_volume"
                        v-model="form.import_volume"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm transition-all font-medium text-gray-700 shadow-sm cursor-pointer"
                        required
                    >
                        <option value="" disabled selected>Selecione uma opção...</option>
                        <option v-for="option in importVolumeOptions" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.import_volume" />
                </div>

                <div>
                    <InputLabel for="decision_role" value="Seu papel na decisão de compra" class="text-xs font-bold text-gray-500 mb-2" />
                    <select
                        id="decision_role"
                        v-model="form.decision_role"
                        class="mt-1 block w-full px-4 py-3 bg-white border-gray-200 focus:border-blue-500 focus:ring-blue-500 rounded-xl text-sm transition-all font-medium text-gray-700 shadow-sm cursor-pointer"
                        required
                    >
                        <option value="" disabled selected>Selecione uma opção...</option>
                        <option v-for="option in decisionRoleOptions" :key="option" :value="option">
                            {{ option }}
                        </option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.decision_role" />
                </div>
            </div>

            <!-- Form Navigation Actions -->
            <div class="mt-8 flex items-center gap-4">
                <button
                    v-if="currentStep > 1"
                    type="button"
                    @click="prevStep"
                    class="flex items-center justify-center gap-2 px-5 py-3.5 border border-gray-200 hover:bg-gray-50 text-gray-600 rounded-xl text-sm font-bold tracking-wide transition-all w-1/3"
                >
                    <ArrowLeft class="w-4 h-4" />
                    Voltar
                </button>

                <button
                    v-if="currentStep < 3"
                    type="button"
                    @click="nextStep"
                    class="flex-1 inline-flex items-center justify-center gap-2 px-6 py-3.5 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 rounded-xl text-sm font-bold tracking-wide text-white shadow-lg shadow-blue-100 transition-all"
                >
                    Continuar
                    <ArrowRight class="w-4 h-4" />
                </button>

                <PrimaryButton
                    v-if="currentStep === 3"
                    class="flex-1 justify-center py-3.5 bg-blue-600 hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 rounded-xl text-sm font-bold tracking-wide shadow-lg shadow-blue-100 transition-all"
                    :class="{ 'opacity-75 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('Create account') }}
                </PrimaryButton>
            </div>

            <p class="mt-6 text-center text-xs text-gray-500">
                {{ $t('Already have an account?') }}
                <Link :href="route('login')" class="font-bold text-blue-600 hover:text-blue-800 transition-colors">{{ $t('Sign in') }}</Link>
            </p>
        </form>
    </GuestLayout>
</template>

<style scoped>
.animate-in {
    animation-duration: 0.3s;
    animation-fill-mode: both;
}
@keyframes fade-in {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in {
    animation-name: fade-in;
}
</style>
