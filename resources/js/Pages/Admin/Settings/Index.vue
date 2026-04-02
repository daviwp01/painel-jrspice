<script setup>
import { ref } from 'vue';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head } from '@inertiajs/vue3';
import UserActivationSetting from './Partials/UserActivationSetting.vue';
import DefaultReportAccessSetting from './Partials/DefaultReportAccessSetting.vue';
import NotifyUpdateSetting from './Partials/NotifyUpdateSetting.vue';
import SMTPSettings from './Partials/SMTPSettings.vue';
import EmailTemplateSettings from './Partials/EmailTemplateSettings.vue';
import LegalSettings from './Partials/LegalSettings.vue';
import ContactSettings from './Partials/ContactSettings.vue';
import QueueStatusMonitor from './Partials/QueueStatusMonitor.vue';
import { Settings as SettingsIcon, Bell, Server, Shield } from 'lucide-vue-next';

const props = defineProps({
    settings: Object,
    users: Array,
    available_pages: Array,
    queue_stats: Object
});

const activeTab = ref('general');

const tabs = [
    { id: 'general', name: 'General', icon: Shield },
    { id: 'communication', name: 'Communication', icon: Bell },
    { id: 'infrastructure', name: 'Infrastructure', icon: Server },
];
</script>

<template>
    <Head :title="$t('Settings')" />

    <DashboardLayout>
        <template #header>
            <h2 class="hidden md:block text-xs font-bold text-slate-400 uppercase tracking-widest">{{ $t('Settings') }}</h2>
        </template>

        <div class="p-6 md:p-8 space-y-8 w-full max-w-none">


                <!-- Tabs Navigation -->
                <div class="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0 no-scrollbar mb-10">
                    <div class="flex p-1.5 bg-slate-200/50 backdrop-blur-md rounded-2xl w-full sm:w-fit">
                        <button
                            v-for="tab in tabs"
                            :key="tab.id"
                            @click="activeTab = tab.id"
                            class="flex-1 sm:flex-none flex items-center justify-center space-x-1.5 sm:space-x-2 px-4 sm:px-6 py-2.5 sm:py-3 rounded-xl transition-all duration-300 group whitespace-nowrap"
                            :class="activeTab === tab.id
                                ? 'bg-white text-indigo-600 shadow-sm ring-1 ring-indigo-100'
                                : 'text-slate-500 hover:text-slate-800'"
                        >
                            <component :is="tab.icon" class="w-4 h-4" />
                            <span class="text-xs sm:text-sm font-bold uppercase tracking-wider sm:tracking-widest">{{ $t(tab.name) }}</span>
                        </button>
                    </div>
                </div>

                <!-- Tab Content -->
                <div class="space-y-12 transition-all duration-300">
                    <!-- GENERAL TAB -->
                    <div v-if="activeTab === 'general'" class="space-y-12">
                        <div class="space-y-4">
                            <div class="flex items-center space-x-2 px-1">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $t('Security & Approval') }}</span>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>
                            <UserActivationSetting
                                :initial-requires-activation="!!settings.registration_requires_activation"
                            />
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center space-x-2 px-1">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Relatórios Permitidos (Padrão)</span>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>
                            <DefaultReportAccessSetting
                                :initial-pages="settings.default_allowed_pages || []"
                                :available-pages="available_pages"
                            />
                        </div>
                    </div>

                    <!-- COMMUNICATION TAB -->
                    <div v-if="activeTab === 'communication'" class="space-y-12">
                        <div class="space-y-4">
                            <div class="flex items-center space-x-2 px-1">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $t('Update Alerts') }}</span>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>
                            <NotifyUpdateSetting :users="users" />
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center space-x-2 px-1">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Dados de Atendimento & Redes</span>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>
                            <ContactSettings :settings="settings" />
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center space-x-2 px-1">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $t('Email Template Customization') }}</span>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>
                            <EmailTemplateSettings :settings="settings" />
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center space-x-2 px-1">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $t('Legal & Privacy') }}</span>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>
                            <LegalSettings :settings="settings" />
                        </div>
                    </div>

                    <!-- INFRASTRUCTURE TAB -->
                    <div v-if="activeTab === 'infrastructure'" class="space-y-12">
                        <QueueStatusMonitor :initial-stats="queue_stats" />

                        <div class="space-y-4">
                            <div class="flex items-center space-x-2 px-1">
                                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $t('SMTP Server') }}</span>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>
                            <SMTPSettings :settings="settings" />
                        </div>
                    </div>
                </div>
        </div>
    </DashboardLayout>
</template>
