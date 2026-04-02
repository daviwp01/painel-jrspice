<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { InboxIcon } from 'lucide-vue-next';
</script>

<template>
    <Head title="Dashboards" />

    <DashboardLayout>
        <div class="h-full w-full flex flex-col bg-[#f8fafc] relative items-center justify-center p-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-16 text-center text-slate-500 flex flex-col items-center justify-center max-w-xl mx-auto">
                <div class="mb-6 p-6 rounded-full bg-slate-50 border border-slate-100 shadow-inner">
                    <InboxIcon class="w-16 h-16 text-slate-300" />
                </div>
                
                <template v-if="$page.props.auth.user.is_master">
                    <h3 class="text-xl font-bold text-slate-800 tracking-tight mb-2">Configure Seu Painel</h3>
                    <p class="text-sm text-slate-500 font-medium">Você ainda não possui painéis configurados no sistema. Crie as métricas para exibir seus gráficos internos.</p>
                    <div class="mt-8">
                        <Link :href="route('admin.data.index')" class="bg-[#0f172a] hover:bg-slate-800 text-white font-bold py-3 px-6 rounded-xl text-xs uppercase tracking-widest transition-all">
                            Ir para Configurações (Master)
                        </Link>
                    </div>
                </template>

                <template v-else>
                    <h3 class="text-xl font-bold text-slate-800 tracking-tight mb-2">Painel em Construção</h3>
                    <p class="text-sm text-slate-500 font-medium">Nenhum dado foi publicado pelo administrador ainda. Volte mais tarde.</p>
                </template>

            </div>
        </div>
    </DashboardLayout>
</template>
