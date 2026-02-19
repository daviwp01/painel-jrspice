<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PowerBIEmbed from '@/Components/PowerBIEmbed.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps(['embedConfig']);
</script>

<template>
    <Head :title="$t('Dashboard')" />

    <DashboardLayout>
        <div class="h-full w-full flex flex-col bg-[#f8fafc] relative">
            <!-- Embed Section -->
            <div class="flex-1 min-h-[600px] bg-white relative">
                <PowerBIEmbed
                    :embed-config="embedConfig"
                    :hidden-pages="['HOME', 'CONTATO', 'EXPORT']"
                />
            </div>

            <!-- Master Activity Card -->
            <div v-if="$page.props.auth.user.is_master"
                 class="absolute bottom-6 right-6 z-[60] group">
                <Link :href="route('admin.activity.index')"
                      class="flex items-center space-x-4 bg-slate-900/90 backdrop-blur-md text-white p-2 pl-5 rounded-2xl shadow-2xl border border-slate-700/50 hover:bg-slate-900 transition-all hover:scale-[1.02] active:scale-[0.98]">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none mb-1">{{ $t('Master Center') }}</span>
                        <span class="text-xs font-black uppercase tracking-tight">{{ $t('User Activity') }}</span>
                    </div>
                    <div class="p-3 bg-blue-600 rounded-xl shadow-lg group-hover:bg-blue-500 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                </Link>
            </div>
        </div>
    </DashboardLayout>
</template>
