<script setup>
import { Head, usePage, Link, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Phone, Mail, MapPin, Send, MessageSquare, Twitter, Instagram, Linkedin, User, Building, PhoneCall, CheckCircle } from 'lucide-vue-next';
import CountryFlag from '@/Components/CountryFlag.vue';
import { computed } from 'vue';

const props = defineProps({
    currentPage: Object,
});

const { props: pageProps } = usePage();
const authUser = computed(() => pageProps.auth.user);

const hasData = pageProps.settings.contact_phone || pageProps.settings.contact_email || pageProps.settings.contact_address;

const contactInfo = {
    phone: pageProps.settings.contact_phone || '',
    email: pageProps.settings.contact_email || '',
    address: pageProps.settings.contact_address || '',
    whatsapp: pageProps.settings.contact_whatsapp || '',
    hours: {
        br: pageProps.settings.contact_hours_br || null,
        pt: pageProps.settings.contact_hours_pt || null,
    },
    socials: [
        { icon: Instagram, label: 'Instagram', url: pageProps.settings.social_instagram ? `https://instagram.com/${pageProps.settings.social_instagram.replace('@','')}` : null },
        { icon: Linkedin, label: 'LinkedIn', url: pageProps.settings.social_linkedin || null },
        { icon: Twitter, label: 'Twitter', url: pageProps.settings.social_twitter ? `https://twitter.com/${pageProps.settings.social_twitter.replace('@','')}` : null },
    ].filter(s => s.url)
};

const form = useForm({
    subject: '',
    message: '',
});

const submitContact = () => {
    form.post(route('dashboard.contact.send'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head :title="currentPage.title" />

    <DashboardLayout>
        <div class="p-6 md:p-12 min-h-full bg-slate-50/50">
            <!-- Header Section (Padronizado) -->
            <div class="hidden md:flex justify-between items-end pb-4 border-b border-slate-200 mb-8">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-900 tracking-tight uppercase">{{ currentPage.title }}</h2>
                    <p class="text-[10px] font-medium text-slate-500 mt-2 uppercase tracking-widest rounded-full border border-slate-200 bg-white inline-block px-3 py-1 shadow-sm">
                        Suporte: <span class="text-blue-600">Canais de Atendimento</span>
                    </p>
                </div>

            </div>

            <!-- Mobile Header Title -->
            <div class="md:hidden mb-6">
                <h2 class="text-xl font-semibold text-slate-900 tracking-tight uppercase">{{ currentPage.title }}</h2>
                <div class="h-1 w-12 bg-blue-500 mt-1"></div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-12 gap-8 items-start">
                
                <!-- Left Side: Info & Socials -->
                <div class="xl:col-span-4 space-y-6">
                    <div v-if="hasData" class="space-y-4">
                        <!-- Phone Card -->
                        <div v-if="contactInfo.phone" class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 flex items-center gap-4">
                            <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center shrink-0">
                                <Phone class="w-5 h-5" />
                            </div>
                            <div>
                                <p class="text-[9px] font-semibold text-slate-400 uppercase tracking-widest">Telefone</p>
                                <h3 class="text-sm font-semibold text-slate-800">{{ contactInfo.phone }}</h3>
                            </div>
                        </div>

                        <!-- Email Card -->
                        <div v-if="contactInfo.email" class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 flex items-center gap-4">
                            <div class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center shrink-0">
                                <Mail class="w-5 h-5" />
                            </div>
                            <div>
                                <p class="text-[9px] font-semibold text-slate-400 uppercase tracking-widest">E-mail</p>
                                <h3 class="text-sm font-semibold text-slate-800 lowercase">{{ contactInfo.email }}</h3>
                            </div>
                        </div>

                        <!-- Address -->
                        <div v-if="contactInfo.address" class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 flex items-center gap-4">
                            <div class="w-10 h-10 bg-slate-100 text-slate-600 rounded-xl flex items-center justify-center shrink-0">
                                <MapPin class="w-5 h-5" />
                            </div>
                            <div>
                                <p class="text-[9px] font-semibold text-slate-400 uppercase tracking-widest">Localização</p>
                                <h3 class="text-[11px] font-medium text-slate-700 uppercase leading-snug">{{ contactInfo.address }}</h3>
                            </div>
                        </div>

                        <!-- Business Hours -->
                        <div v-if="contactInfo.hours.br || contactInfo.hours.pt" class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <PhoneCall class="w-3 h-3" /> Horários de Atendimento
                            </p>
                            <div class="space-y-4">
                                <div v-if="contactInfo.hours.br" class="flex items-center justify-between border-b border-slate-50 pb-3">
                                    <span class="flex items-center gap-2 text-[10px] font-semibold text-slate-500 uppercase tracking-tight">
                                        <CountryFlag name="Brasil" class-name="w-5 h-3" /> BRASIL
                                    </span>
                                    <span class="text-xs font-medium text-slate-800 uppercase">{{ contactInfo.hours.br }}</span>
                                </div>
                                <div v-if="contactInfo.hours.pt" class="flex items-center justify-between">
                                    <span class="flex items-center gap-2 text-[10px] font-semibold text-slate-500 uppercase tracking-tight">
                                        <CountryFlag name="Portugal" class-name="w-5 h-3" /> PORTUGAL
                                    </span>
                                    <span class="text-xs font-medium text-slate-800 uppercase">{{ contactInfo.hours.pt }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Socials & WhatsApp -->
                    <div v-if="contactInfo.whatsapp || contactInfo.socials.length > 0" class="bg-blue-600 p-8 rounded-[2.5rem] text-white shadow-xl relative overflow-hidden group">
                        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-white/5 rounded-full blur-2xl"></div>
                        <h4 class="text-[10px] font-semibold uppercase tracking-[0.2em] text-blue-100 mb-6">Fale Conosco</h4>
                        
                        <a v-if="contactInfo.whatsapp" :href="contactInfo.whatsapp" target="_blank" class="flex items-center gap-4 bg-white/10 hover:bg-white/20 p-4 rounded-2xl transition-all mb-6 border border-white/10">
                            <MessageSquare class="w-6 h-6" />
                            <span class="text-xs font-semibold uppercase tracking-widest">Suporte WhatsApp</span>
                        </a>

                        <div v-if="contactInfo.socials.length > 0" class="flex items-center gap-4 mt-8">
                            <a v-for="social in contactInfo.socials" :key="social.label" :href="social.url" target="_blank" class="w-10 h-10 bg-white/10 hover:bg-white text-white hover:text-blue-600 rounded-xl flex items-center justify-center transition-all">
                                <component :is="social.icon" class="w-5 h-5" />
                            </a>
                        </div>
                    </div>

                    <!-- Empty State Side Message -->
                    <div v-if="!hasData" class="bg-slate-100/50 border-2 border-dashed border-slate-200 rounded-[2.5rem] p-8 text-center">
                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-[0.2em] mb-4">Aguardando Configuração</p>
                        <Link v-if="authUser.is_master" :href="route('admin.settings.index')" class="text-xs font-medium text-blue-600 hover:underline">Configurar dados corporativos</Link>
                    </div>
                </div>

                <!-- Right Side: Contact Form -->
                <div class="xl:col-span-8 bg-white rounded-[2.5rem] shadow-sm border border-slate-200 p-8 md:p-12 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-slate-50 rounded-full blur-3xl opacity-50 -mr-32 -mt-32"></div>
                    
                    <div class="relative z-10">
                        <h2 class="text-2xl font-semibold text-slate-900 uppercase tracking-tight mb-2">Envie uma Mensagem</h2>
                        <p class="text-slate-500 font-medium text-xs md:text-sm mb-10 leading-relaxed max-w-lg">
                            Utilize o formulário abaixo para enviar uma solicitação direta para nosso setor responsável. Responderemos em breve.
                        </p>

                        <!-- Success Alert -->
                        <div v-if="$page.props.flash.success" class="bg-emerald-50 border border-emerald-100 p-4 rounded-2xl flex items-center gap-4 mb-8 text-emerald-700 animate-in fade-in slide-in-from-top-4">
                            <div class="w-8 h-8 bg-emerald-500 text-white rounded-full flex items-center justify-center shrink-0">
                                <CheckCircle class="w-4 h-4" />
                            </div>
                            <span class="text-xs font-semibold uppercase tracking-widest">{{ $page.props.flash.success }}</span>
                        </div>

                        <form @submit.prevent="submitContact" class="space-y-6">
                            <!-- Readonly User Info -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 opacity-60">
                                <div class="space-y-2">
                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-widest pl-1">Nome (Automático)</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                            <User class="w-4 h-4" />
                                        </div>
                                        <input :value="authUser.name" readonly class="w-full pl-11 bg-slate-50 border-slate-200 rounded-2xl text-xs font-medium text-slate-500 h-12" />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-widest pl-1">E-mail (Automático)</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                            <Mail class="w-4 h-4" />
                                        </div>
                                        <input :value="authUser.email" readonly class="w-full pl-11 bg-slate-50 border-slate-200 rounded-2xl text-xs font-medium text-slate-500 h-12" />
                                    </div>
                                </div>
                                <div v-if="authUser.company_name" class="space-y-2 lg:col-span-2">
                                    <label class="block text-[10px] font-semibold text-slate-400 uppercase tracking-widest pl-1">Empresa</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                            <Building class="w-4 h-4" />
                                        </div>
                                        <input :value="authUser.company_name" readonly class="w-full pl-11 bg-slate-50 border-slate-200 rounded-2xl text-xs font-medium text-slate-500 h-12" />
                                    </div>
                                </div>
                            </div>

                            <div class="h-px bg-slate-100 my-8"></div>

                            <!-- Interactive Fields -->
                            <div class="space-y-6">
                                <div class="space-y-2">
                                    <label class="block text-[10px] font-semibold text-slate-600 uppercase tracking-widest pl-1">Assunto do Contato</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                            <MessageSquare class="w-4 h-4" />
                                        </div>
                                        <input v-model="form.subject" required placeholder="Digite o assunto da mensagem..." class="w-full pl-11 border-slate-200 rounded-2xl shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm font-medium h-12 transition-all" />
                                    </div>
                                    <div v-if="form.errors.subject" class="text-[10px] text-red-500 font-bold uppercase mt-1">{{ form.errors.subject }}</div>
                                </div>

                                <div class="space-y-2">
                                    <label class="block text-[10px] font-semibold text-slate-600 uppercase tracking-widest pl-1">Sua Mensagem</label>
                                    <textarea v-model="form.message" required rows="6" placeholder="Escreva detalhadamente como podemos ajudar..." class="w-full border-slate-200 rounded-3xl shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm font-medium p-6 transition-all resize-none"></textarea>
                                    <div v-if="form.errors.message" class="text-[10px] text-red-500 font-bold uppercase mt-1">{{ form.errors.message }}</div>
                                </div>
                            </div>

                            <div class="pt-6 flex justify-end">
                                <button type="submit" :disabled="form.processing" class="bg-[#0f172a] hover:bg-slate-800 text-white font-semibold text-xs uppercase tracking-[0.2em] py-5 px-12 rounded-2xl shadow-xl shadow-slate-200 transition-all active:scale-95 disabled:opacity-50 flex items-center gap-3">
                                    Enviar Mensagem <Send class="w-4 h-4" />
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>

            <!-- Footer Badge Removed as requested -->
            <div class="mt-20 text-center">
            </div>
        </div>
    </DashboardLayout>
</template>

<style scoped>
@keyframes pulse-subtle {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.005); }
}
.animate-pulse-subtle {
    animation: pulse-subtle 4s ease-in-out infinite;
}
</style>
