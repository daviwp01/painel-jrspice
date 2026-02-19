<script setup>
import { ref } from 'vue';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head } from '@inertiajs/vue3';
import UserActivationSetting from './Partials/UserActivationSetting.vue';
import DefaultPagesSetting from './Partials/DefaultPagesSetting.vue';
import NotifyUpdateSetting from './Partials/NotifyUpdateSetting.vue';
import SMTPSettings from './Partials/SMTPSettings.vue';
import EmailTemplateSettings from './Partials/EmailTemplateSettings.vue';
import LegalSettings from './Partials/LegalSettings.vue';
import QueueStatusMonitor from './Partials/QueueStatusMonitor.vue';
import { Settings as SettingsIcon, Bell, Server, Shield } from 'lucide-vue-next';

const props = defineProps({
    settings: Object,
    users: Array,
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
        <div class="py-12 bg-[#f8fafc] min-h-screen">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
                <!-- Header -->
                <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-8">
                    <div>
                        <h1 class="text-4xl font-black text-slate-900 tracking-tight flex items-center">
                            <span class="bg-indigo-600 w-2 h-10 rounded-full mr-4"></span>
                            {{ $t('Settings') }}
                        </h1>
                        <p class="mt-3 text-slate-500 font-medium text-lg">{{ $t('Configure and manage system parameters') }}</p>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="flex p-1.5 bg-slate-200/50 backdrop-blur-md rounded-2xl mb-12 w-fit mx-auto sm:mx-0">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        @click="activeTab = tab.id"
                        class="flex items-center space-x-2 px-6 py-3 rounded-xl transition-all duration-300 relative group"
                        :class="activeTab === tab.id
                            ? 'bg-white text-indigo-600 shadow-sm'
                            : 'text-slate-500 hover:text-slate-800'"
                    >
                        <component :is="tab.icon" class="w-4 h-4" />
                        <span class="text-sm font-black uppercase tracking-widest">{{ $t(tab.name) }}</span>

                        <!-- Indicator for active tab -->
                        <div v-if="activeTab === tab.id" class="absolute -bottom-8 left-1/2 -translate-x-1/2 w-1 h-1 bg-indigo-600 rounded-full"></div>
                    </button>
                </div>

                <!-- Tab Content -->
                <div class="space-y-12 transition-all duration-300">
                    <!-- GENERAL TAB -->
                    <div v-if="activeTab === 'general'" class="space-y-12">
                        <div class="space-y-4">
                            <div class="flex items-center space-x-2 px-1">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ $t('Security & Approval') }}</span>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>
                            <UserActivationSetting
                                :initial-requires-activation="!!settings.registration_requires_activation"
                            />
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center space-x-2 px-1">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ $t('Landing Pages') }}</span>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>
                            <DefaultPagesSetting
                                :initial-default-pages="settings.default_user_pages || []"
                            />
                        </div>
                    </div>

                    <!-- COMMUNICATION TAB -->
                    <div v-if="activeTab === 'communication'" class="space-y-12">
                        <div class="space-y-4">
                            <div class="flex items-center space-x-2 px-1">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ $t('Update Alerts') }}</span>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>
                            <NotifyUpdateSetting :users="users" />
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center space-x-2 px-1">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ $t('Email Template Customization') }}</span>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>
                            <EmailTemplateSettings :settings="settings" />
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center space-x-2 px-1">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ $t('Legal & Privacy') }}</span>
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
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ $t('SMTP Server') }}</span>
                                <div class="h-px flex-1 bg-slate-200"></div>
                            </div>
                            <SMTPSettings :settings="settings" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </DashboardLayout>
</template>
