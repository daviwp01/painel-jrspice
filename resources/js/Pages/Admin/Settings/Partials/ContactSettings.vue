<script setup>
import { useForm } from '@inertiajs/vue3';
import { Phone, Mail, Instagram, Linkedin, Twitter, MessageSquare, MapPin } from 'lucide-vue-next';

const props = defineProps({
    settings: Object
});

const form = useForm({
    settings: {
        contact_phone: props.settings.contact_phone || '',
        contact_email: props.settings.contact_email || '',
        contact_address: props.settings.contact_address || '',
        contact_whatsapp: props.settings.contact_whatsapp || '',
        contact_hours_br: props.settings.contact_hours_br || '',
        contact_hours_pt: props.settings.contact_hours_pt || '',
        social_instagram: props.settings.social_instagram || '',
        social_linkedin: props.settings.social_linkedin || '',
        social_twitter: props.settings.social_twitter || '',
    }
});

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="bg-white p-6 md:p-10 rounded-[2.5rem] shadow-sm border border-slate-200">
        <div class="flex items-center gap-4 mb-10">
            <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center">
                <Phone class="w-6 h-6" />
            </div>
            <div>
                <h3 class="text-xl font-bold text-slate-800 uppercase tracking-tight">Canais de Contato</h3>
                <p class="text-xs text-slate-500 font-medium">Configure os dados que aparecem na página de contato.</p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Phone -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Telefone Principal</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <Phone class="w-4 h-4" />
                        </div>
                        <input v-model="form.settings.contact_phone" type="text" placeholder="+55 (11) 00000-0000" class="w-full pl-11 border-slate-200 rounded-2xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm font-medium h-12 transition-all" />
                    </div>
                </div>

                <!-- Email -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">E-mail Corporativo</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <Mail class="w-4 h-4" />
                        </div>
                        <input v-model="form.settings.contact_email" type="email" placeholder="contato@empresa.com" class="w-full pl-11 border-slate-200 rounded-2xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm font-medium h-12 transition-all" />
                    </div>
                </div>

                <!-- WhatsApp -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Link WhatsApp</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <MessageSquare class="w-4 h-4" />
                        </div>
                        <input v-model="form.settings.contact_whatsapp" type="text" placeholder="https://wa.me/..." class="w-full pl-11 border-slate-200 rounded-2xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm font-medium h-12 transition-all" />
                    </div>
                </div>

                <!-- Address -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Endereço Completo</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <MapPin class="w-4 h-4" />
                        </div>
                        <input v-model="form.settings.contact_address" type="text" placeholder="Cidade, Estado, Brasil" class="w-full pl-11 border-slate-200 rounded-2xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm font-medium h-12 transition-all" />
                    </div>
                </div>

                <!-- Hours BR -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Horário (Brasil)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <div class="w-4 h-4 rounded-full bg-emerald-500 flex items-center justify-center text-[6px] text-white font-bold">BR</div>
                        </div>
                        <input v-model="form.settings.contact_hours_br" type="text" placeholder="Ex: Seg a Sex, 08h às 18h" class="w-full pl-11 border-slate-200 rounded-2xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm font-medium h-12 transition-all" />
                    </div>
                </div>

                <!-- Hours PT -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Horário (Portugal)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <div class="w-4 h-4 rounded-full bg-blue-600 flex items-center justify-center text-[6px] text-white font-bold">PT</div>
                        </div>
                        <input v-model="form.settings.contact_hours_pt" type="text" placeholder="Ex: Seg a Sex, 09h às 19h" class="w-full pl-11 border-slate-200 rounded-2xl shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm font-medium h-12 transition-all" />
                    </div>
                </div>
            </div>

            <div class="pt-6 border-t border-slate-100 flex items-center gap-4 mb-4">
                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Redes Sociais (Links)</span>
                <div class="h-px flex-1 bg-slate-100"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Instagram -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Instagram</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <Instagram class="w-4 h-4" />
                        </div>
                        <input v-model="form.settings.social_instagram" type="text" placeholder="@usuario" class="w-full pl-11 border-slate-200 rounded-2xl shadow-sm focus:border-pink-500 focus:ring-pink-500 sm:text-sm font-medium h-12 transition-all" />
                    </div>
                </div>

                <!-- LinkedIn -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">LinkedIn</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <Linkedin class="w-4 h-4" />
                        </div>
                        <input v-model="form.settings.social_linkedin" type="text" placeholder="URL do perfil" class="w-full pl-11 border-slate-200 rounded-2xl shadow-sm focus:border-blue-700 focus:ring-blue-700 sm:text-sm font-medium h-12 transition-all" />
                    </div>
                </div>

                <!-- Twitter -->
                <div class="space-y-2">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest pl-1">Twitter / X</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <Twitter class="w-4 h-4" />
                        </div>
                        <input v-model="form.settings.social_twitter" type="text" placeholder="@usuario" class="w-full pl-11 border-slate-200 rounded-2xl shadow-sm focus:border-slate-900 focus:ring-slate-900 sm:text-sm font-medium h-12 transition-all" />
                    </div>
                </div>
            </div>

            <div class="pt-8 flex justify-end">
                <button type="submit" :disabled="form.processing" class="bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs uppercase tracking-widest py-4 px-10 rounded-2xl shadow-lg shadow-blue-200 transition-all active:scale-95 disabled:opacity-50">
                    Salvar Alterações
                </button>
            </div>
        </form>
    </div>
</template>
