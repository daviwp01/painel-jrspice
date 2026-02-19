<script setup>
import { ref, onMounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Layout, Save, Loader2, AlertCircle, CheckCircle2 } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps({
    initialDefaultPages: {
        type: Array,
        default: () => []
    }
});

const availablePages = ref([]);
const isLoadingPages = ref(true);

const form = useForm({
    settings: {
        default_user_pages: Array.isArray(props.initialDefaultPages) ? props.initialDefaultPages : []
    }
});

onMounted(async () => {
    try {
        const response = await axios.get(route('powerbi.pages'));
        availablePages.value = response.data;
    } catch (error) {
        console.error('Failed to load Power BI pages', error);
    } finally {
        isLoadingPages.value = false;
    }
});

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults();
        }
    });
};

const togglePage = (pageName) => {
    const index = form.settings.default_user_pages.indexOf(pageName);
    if (index > -1) {
        form.settings.default_user_pages.splice(index, 1);
    } else {
        form.settings.default_user_pages.push(pageName);
    }
};
</script>

<template>
    <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden transition-all duration-300 hover:shadow-md">
        <!-- Header -->
        <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="p-2 bg-indigo-100 rounded-lg">
                    <Layout class="w-5 h-5 text-indigo-600" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800 tracking-tight">{{ $t('Default Pages') }}</h3>
                    <p class="text-xs text-slate-500">{{ $t('Define which reports new users will see by default.') }}</p>
                </div>
            </div>
        </div>

        <form @submit.prevent="submit" class="p-6 space-y-6">
            <!-- Pages Grid -->
            <div v-if="isLoadingPages" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="n in 4" :key="n" class="h-16 bg-slate-50 rounded-xl shimmer border border-slate-100"></div>
            </div>

            <div v-else-if="availablePages.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div
                    v-for="page in availablePages"
                    :key="page.name"
                    @click="togglePage(page.name)"
                    class="group relative p-4 rounded-xl border-2 transition-all duration-200 cursor-pointer flex items-center justify-between"
                    :class="form.settings.default_user_pages.includes(page.name)
                        ? 'border-indigo-500 bg-indigo-50/50 ring-1 ring-indigo-500/20'
                        : 'border-slate-100 bg-slate-50/30 hover:border-slate-300 hover:bg-slate-50'"
                >
                    <div class="flex items-center space-x-3">
                        <div
                            class="w-10 h-10 rounded-lg flex items-center justify-center transition-colors"
                            :class="form.settings.default_user_pages.includes(page.name) ? 'bg-indigo-500 text-white' : 'bg-white border border-slate-200 text-slate-400'"
                        >
                            <Layout class="w-5 h-5" />
                        </div>
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider block" :class="form.settings.default_user_pages.includes(page.name) ? 'text-indigo-700' : 'text-slate-500'">
                                {{ page.displayName.replace(/_/g, ' ') }}
                            </span>
                            <span class="text-[10px] text-slate-400 font-medium">Power BI Report</span>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <div
                            class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                            :class="form.settings.default_user_pages.includes(page.name) ? 'bg-indigo-500 border-indigo-500 scale-110' : 'border-slate-300 bg-white'"
                        >
                            <CheckCircle2 v-if="form.settings.default_user_pages.includes(page.name)" class="w-3 h-3 text-white" />
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="p-8 text-center bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                <Layout class="w-12 h-12 text-slate-300 mx-auto mb-3" />
                <p class="text-sm text-slate-500 font-medium">{{ $t('No reports available from Power BI.') }}</p>
            </div>



            <!-- Action Button -->
            <div class="flex justify-end pt-2 border-t border-slate-100 mt-6">
                <button
                    type="submit"
                    :disabled="form.processing || !form.isDirty"
                    class="inline-flex items-center justify-center px-8 py-3 bg-[#1a73e8] text-white text-sm font-bold uppercase tracking-widest rounded-full hover:bg-[#1557b0] focus:outline-none focus:ring-4 focus:ring-blue-100 transition-all duration-300 transform active:scale-[0.98] disabled:opacity-50 disabled:grayscale disabled:cursor-not-allowed shadow-md hover:shadow-lg"
                >
                    <Loader2 v-if="form.processing" class="w-4 h-4 mr-2 animate-spin" />
                    <Save v-else class="w-4 h-4 mr-2" />
                    {{ $t('Save Changes') }}
                </button>
            </div>
        </form>
    </section>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.5s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.shimmer {
    position: relative;
    overflow: hidden;
}
.shimmer::after {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    transform: translateX(-100%);
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    animation: shimmer-animation 2s infinite;
}
@keyframes shimmer-animation {
    100% { transform: translateX(100%); }
}
</style>
