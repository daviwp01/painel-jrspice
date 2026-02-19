<script setup>
import { toast } from '@/Stores/ToastStore';
import { CheckCircle2, AlertCircle, X, Info } from 'lucide-vue-next';

const getIcon = (type) => {
    switch (type) {
        case 'success': return CheckCircle2;
        case 'error': return AlertCircle;
        case 'info': return Info;
        default: return CheckCircle2;
    }
};

const getTypeClasses = (type) => {
    switch (type) {
        case 'success': return 'bg-white border-emerald-100 text-emerald-800';
        case 'error': return 'bg-white border-rose-100 text-rose-800';
        case 'info': return 'bg-white border-blue-100 text-blue-800';
        default: return 'bg-white border-slate-100 text-slate-800';
    }
};

const getIconClasses = (type) => {
    switch (type) {
        case 'success': return 'bg-emerald-50 text-emerald-600';
        case 'error': return 'bg-rose-50 text-rose-600';
        case 'info': return 'bg-blue-50 text-blue-600';
        default: return 'bg-slate-50 text-slate-600';
    }
};
</script>

<template>
    <div class="fixed top-6 right-6 z-[100] flex flex-col space-y-3 w-full max-w-[400px] pointer-events-none">
        <transition-group
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="item in toast.items"
                :key="item.id"
                class="pointer-events-auto flex items-center p-4 rounded-xl border transition-all duration-300"
                :class="getTypeClasses(item.type)"
            >
                <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center mr-4" :class="getIconClasses(item.type)">
                    <component :is="getIcon(item.type)" class="w-5 h-5" />
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-black tracking-tight leading-snug">
                        {{ item.message }}
                    </p>
                </div>

                <button
                    @click="toast.remove(item.id)"
                    class="ml-4 flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:bg-slate-50 hover:text-slate-600 transition-colors"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>
        </transition-group>
    </div>
</template>
