<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { UploadCloudIcon, Loader2Icon, CheckCircleIcon, AlertCircleIcon, XCircleIcon, InfoIcon, FileTextIcon, DownloadIcon, PlusIcon, HistoryIcon, Trash2Icon } from 'lucide-vue-next';
import axios from 'axios';
import { toast } from '@/Stores/ToastStore';
import { trans } from 'laravel-vue-i18n';

import ConfirmationModal from '@/Components/ConfirmationModal.vue';

const props = defineProps({
    active_import_batch: Object,
    backups: Array
});

const importFile = ref(null);
const isImporting = ref(false);
const importProgress = ref(null);
const importError = ref(null);
const importSuccess = ref(false);
const importCancelled = ref(false);
let progressInterval = null;
let activeBatchId = ref(null);
let activeJobId = ref(null);
const isCreatingBackup = ref(false);

// MODAL STATE
const confirmModal = ref({
    show: false,
    title: '',
    message: '',
    confirmText: trans('Confirm'),
    cancelText: trans('Cancel'),
    onConfirm: null
});

const openConfirm = (options) => {
    confirmModal.value = {
        show: true,
        title: options.title || trans('Are you sure?'),
        message: options.message || '',
        confirmText: options.confirmText || trans('Confirm'),
        cancelText: options.cancelText || trans('Cancel'),
        onConfirm: options.onConfirm
    };
};

const closeConfirm = () => {
    confirmModal.value.show = false;
};

const handleConfirmAction = () => {
    if (confirmModal.value.onConfirm) confirmModal.value.onConfirm();
    closeConfirm();
};

onMounted(() => {
    if (props.active_import_batch && props.active_import_batch.id) {
        // Se já estiver cancelado, limpa o estado
        if (props.active_import_batch.status === 'cancelled') {
            importCancelled.value = true;
            return;
        }

        activeBatchId.value = props.active_import_batch.id;
        activeJobId.value = props.active_import_batch.jobId;
        isImporting.value = true;
        
        // Resume polling usando o jobId persistido recuperado
        pollProgress(activeJobId.value, activeBatchId.value);
    }
});

const handleFileChange = (e) => {
    importFile.value = e.target.files[0];
};

const startImport = async () => {
    if (!importFile.value) return;

    isImporting.value = true;
    importError.value = null;
    importSuccess.value = false;
    importCancelled.value = false;
    importProgress.value = { percentage: 0, status: 'queued' };

    const formData = new FormData();
    formData.append('file', importFile.value);

    try {
        const response = await axios.post(route('admin.data.import'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        activeJobId.value = response.data.jobId;
        activeBatchId.value = response.data.batchId;
        pollProgress(activeJobId.value, activeBatchId.value);
    } catch (err) {
        isImporting.value = false;
        importError.value = err.response?.data?.message || trans('Error starting the import.');
    }
};

const pollProgress = (jobId, batchId) => {
    if (progressInterval) clearInterval(progressInterval);
    
    progressInterval = setInterval(async () => {
        try {
            const response = await axios.get(route('admin.data.import-status', jobId || 'active'), {
                params: { batchId: batchId }
            });
            importProgress.value = response.data;

            if (response.data.status === 'completed') {
                clearInterval(progressInterval);
                isImporting.value = false;
                importSuccess.value = true;
                activeBatchId.value = null;
                activeJobId.value = null;
                
                toast.add(trans('Import completed successfully!'), 'success');
                
                router.reload({
                    only: ['backups', 'active_import_batch', 'prices', 'products', 'countries', 'suppliers'],
                    onFinish: () => {
                        // Keep success message visible for at least 10 seconds unless a new import starts
                        setTimeout(() => {
                            // Only hide if we aren't starting a NEW job (though startImport handles it)
                            if (!isImporting.value) {
                                // importSuccess.value = false; 
                                // We can keep it or hide it, let's keep it until they change file or tab
                            }
                        }, 10000);
                    }
                });
                
                importProgress.value = null;
            } else if (response.data.status === 'failed') {
                clearInterval(progressInterval);
                isImporting.value = false;
                importError.value = response.data.error || trans('Error processing the spreadsheet.');
                toast.add(importError.value, 'error');
                activeBatchId.value = null;
                activeJobId.value = null;
            } else if (response.data.status === 'cancelled') {
                clearInterval(progressInterval);
                isImporting.value = false;
                importCancelled.value = true;
                toast.add(trans('Import cancelled.'), 'info');
                activeBatchId.value = null;
                activeJobId.value = null;
            } else if (response.data.status === 'error') {
                clearInterval(progressInterval);
                isImporting.value = false;
                importError.value = response.data.message || trans('Internal error while monitoring.');
                toast.add(importError.value, 'error');
                activeBatchId.value = null;
                activeJobId.value = null;
            }

        } catch (err) {
            console.error('Error polling:', err);
        }
    }, 1500);
};

const restoreBackup = async (path) => {
    openConfirm({
        title: trans('Restore Database'),
        message: trans('Do you really want to RESTORE this database? Current data will be replaced with the backup data. This action cannot be undone.'),
        confirmText: trans('Yes, Restore now'),
        onConfirm: async () => {
            isImporting.value = true;
            importError.value = null;
            importSuccess.value = false;
            importCancelled.value = false;
            importProgress.value = { percentage: 0, status: 'queued' };

            try {
                const response = await axios.post(route('admin.data.restore-backup'), { path });
                activeJobId.value = response.data.jobId;
                activeBatchId.value = response.data.batchId;
                pollProgress(activeJobId.value, activeBatchId.value);
            } catch (err) {
                isImporting.value = false;
                importError.value = err.response?.data?.message || trans('Error starting the restore.');
            }
        }
    });
};

const cancelImport = async () => {
    openConfirm({
        title: trans('Cancel Import'),
        message: trans('Do you really want to cancel this import in progress?'),
        confirmText: trans('Yes, Cancel'),
        onConfirm: async () => {
            try {
                await axios.post(route('admin.data.import-cancel'), {
                    batchId: activeBatchId.value
                });
                clearInterval(progressInterval);
                isImporting.value = false;
                importCancelled.value = true;
                importProgress.value = null;
                activeBatchId.value = null;
                activeJobId.value = null;
            } catch (err) {
                console.error('Error cancelling:', err);
                alert(trans('Error trying to cancel the import.'));
            }
        }
    });
};

const truncatePrices = () => {
    openConfirm({
        title: trans('CLEAR DATABASE'),
        message: trans('ARE YOU SURE? This action will permanently delete ALL price records in the system. We recommend creating a backup first.'),
        confirmText: trans('Yes, Delete Everything!'),
        onConfirm: () => {
             router.post(route('admin.data.prices.truncate'), {}, {
                 onSuccess: () => {
                     toast.add(trans('The entire price database has been removed.'), 'success');
                 }
             });
        }
    });
};
</script>

<template>
    <div class="animate-in fade-in zoom-in-95 duration-200">
        <div class="w-full">
            <div class="grid grid-cols-1 xl:grid-cols-5 gap-8 items-start">
                
                <!-- SIDEBAR LEFT: INSTRUCTIONS & TIPS (LARGER BUT BALANCED) -->
                <div class="xl:col-span-2 space-y-6 order-2 xl:order-1">
                    <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm space-y-6">
                        <div class="flex items-center gap-3.5 mb-1 text-slate-800">
                            <div class="p-2.5 bg-blue-50 text-blue-600 rounded-xl">
                                <FileTextIcon class="w-5 h-5" />
                            </div>
                            <h4 class="font-bold uppercase tracking-widest text-xs">{{ $t('Format Instructions') }}</h4>
                        </div>
                        <ul class="text-slate-500 space-y-4 text-sm font-medium leading-relaxed">
                            <li class="flex items-start gap-3">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 mt-2 shrink-0"></span> 
                                <span>{{ $t('The') }} <b class="text-slate-800 underline underline-offset-4 decoration-blue-200 uppercase tracking-tighter">{{ $t('first row') }}</b> {{ $t('must contain the headers.') }}</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-600 mt-2 shrink-0"></span> 
                                <span class="font-bold whitespace-normal">{{ $t('Required Columns:') }} <b class="text-blue-600 uppercase text-[9px] tracking-tight bg-blue-50 px-2 py-0.5 rounded leading-relaxed">{{ $t('Product, Country, Supplier, Registration Date, Year / Month, Week, Price') }}</b>.</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-400 mt-2 shrink-0"></span> 
                                <span class="text-slate-600 leading-relaxed text-xs">{{ $t('The system accepts the') }} <b class="text-slate-900 font-bold uppercase">{{ $t('Harvest') }}</b> {{ $t('column as optional. If present, the data will be imported; if not, the current data will be kept.') }}</span>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 mt-2 shrink-0"></span> 
                                <div class="space-y-1">
                                    <span class="text-slate-900 font-bold uppercase text-[10px] tracking-widest block">{{ $t('Format Tip:') }}</span>
                                    <span class="text-slate-600 leading-relaxed text-xs">{{ $t('In the') }} <b class="text-slate-800">{{ $t('Harvest') }}</b> {{ $t('column, you can use the full month name (e.g.') }} <b class="text-blue-600">{{ $t('JULY') }}</b>).</span>
                                </div>
                            </li>
                        </ul>
                        <div class="px-8 pb-8 pt-4">
                            <a 
                                :href="route('admin.data.download-template')"
                                class="w-full flex items-center justify-center gap-2.5 py-4 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-2xl transition-all shadow-lg shadow-blue-200 active:scale-95 group/btn"
                            >
                                <DownloadIcon class="w-4 h-4 group-hover/btn:-translate-y-0.5 transition-transform" />
                                {{ $t('Download Sample Template') }}
                            </a>
                            <p class="mt-4 text-[9px] text-slate-400 font-bold uppercase tracking-widest text-center">{{ $t('Just fill it in and import it to the system') }}</p>
                        </div>
                    </div>
 
                    <!-- DANGEROUS ACTIONS (COMMENTED OUT FOR FUTURE USE) -->
                    <!-- 
                    <div class="bg-rose-50/30 p-8 rounded-3xl border border-rose-100/50 space-y-4 shadow-sm">
                        <div class="flex items-center gap-3.5 text-rose-800">
                             <div class="p-2.5 bg-rose-50 text-rose-600 rounded-xl">
                                <AlertCircleIcon class="w-5 h-5" />
                             </div>
                             <h4 class="font-bold uppercase tracking-widest text-xs">Ações Críticas</h4>
                        </div>
                        <p class="text-[10px] text-rose-400 font-bold uppercase tracking-widest leading-relaxed">Cuidado: Estas ações são irreversíveis e apagam dados globais.</p>
                        <button 
                            @click="truncatePrices"
                            class="w-full flex items-center justify-center gap-2.5 py-4 bg-white hover:bg-rose-600 text-rose-600 hover:text-white text-[10px] font-black uppercase tracking-[0.2em] rounded-2xl transition-all border border-rose-100 hover:border-rose-600 shadow-sm active:scale-95 group/btn"
                        >
                            <Trash2Icon class="w-4 h-4 group-hover/btn:animate-bounce" />
                            Limpar Histórico de Preços
                        </button>
                    </div> 
                    -->



                    <!-- BACKUP SECTION (NEW) -->
                    <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm space-y-6">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-3.5 text-slate-800">
                                <div class="p-2.5 bg-emerald-50 text-emerald-600 rounded-xl">
                                    <DownloadIcon class="w-5 h-5" />
                                </div>
                                <h4 class="font-bold uppercase tracking-widest text-xs">{{ $t('Security Backups') }}</h4>
                            </div>
                            <button 
                                @click="router.post(route('admin.data.create-backup'), {}, { 
                                    onStart: () => isCreatingBackup = true,
                                    onFinish: () => isCreatingBackup = false 
                                })"
                                :disabled="isCreatingBackup"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[9px] font-black uppercase tracking-widest rounded-xl transition-all border border-emerald-100 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <Loader2Icon v-if="isCreatingBackup" class="w-3.5 h-3.5 animate-spin" />
                                <PlusIcon v-else class="w-3.5 h-3.5" />
                                {{ isCreatingBackup ? $t('Creating...') : $t('Create Now') }}
                            </button>
                        </div>
                        <div v-if="backups && backups.length" class="space-y-3">
                            <div v-for="backup in backups" :key="backup.path" class="group flex items-center justify-between p-3.5 bg-slate-50/50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 rounded-2xl transition-all duration-300">
                                <div class="flex items-center gap-4 text-left">
                                    <div class="w-10 h-10 bg-white rounded-xl flex items-center justify-center border border-slate-100 shadow-sm text-emerald-600">
                                        <FileTextIcon class="w-5 h-5" />
                                    </div>
                                    <div class="space-y-0.5 min-w-0">
                                        <p class="text-[11px] font-black text-slate-700 uppercase tracking-tight truncate max-w-[150px]">{{ backup.name }}</p>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">{{ backup.date }} • {{ backup.size }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a 
                                        :href="route('admin.data.download-backup', { path: backup.path })" 
                                        class="p-2 text-slate-300 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                        :title="$t('Download Backup')"
                                    >
                                        <DownloadIcon class="w-4 h-4" />
                                    </a>
                                    <button 
                                        @click="restoreBackup(backup.path)"
                                        class="flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-emerald-600 text-emerald-600 hover:text-white text-[9px] font-black uppercase tracking-widest rounded-xl transition-all border border-emerald-100 hover:border-emerald-600 shadow-sm active:scale-95"
                                        :title="$t('Restore this state')"
                                    >
                                        <HistoryIcon class="w-3.5 h-3.5" />
                                        {{ $t('Restore') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- EMPTY STATE (NEW) -->
                        <div v-else class="p-8 text-center bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">{{ $t('No backups available yet.') }}</p>
                        </div>

                        <p class="text-[9px] text-slate-400 font-bold uppercase tracking-[0.1em] text-center">
                            {{ $t(':count historical backups available', { count: backups ? backups.length : 0 }) }}
                        </p>
                    </div>
                </div>

                <!-- MAIN AREA RIGHT: IMPORT BOX (MIDDLE SCALE) -->
                <div class="xl:col-span-3 order-1 xl:order-2 space-y-8">
                    <div class="bg-blue-50/50 border border-blue-100 rounded-[2.5rem] p-10 sm:p-14 text-center space-y-8 shadow-sm">
                        <div class="w-20 h-20 bg-blue-100 text-blue-600 rounded-[1.5rem] flex items-center justify-center mx-auto shadow-sm ring-8 ring-blue-50/50">
                            <UploadCloudIcon class="w-10 h-10" />
                        </div>
                        
                        <div class="space-y-3">
                            <h2 class="text-2xl font-black text-slate-900 tracking-tight uppercase">{{ $t('Import spreadsheet') }}</h2>
                            <p class="text-slate-500 max-w-lg mx-auto text-sm font-medium leading-relaxed">{{ $t('Prepare your') }} <b class="text-blue-600 font-black uppercase tracking-tight">{{ $t('Excel (.xlsx or .xls)') }}</b> {{ $t('spreadsheet and perform the full synchronization of the database.') }}</p>
                        </div>
                        
                        <div class="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm inline-block w-full max-w-lg mx-auto group hover:border-blue-400 transition-all duration-300">
                            <input 
                                type="file" 
                                @change="handleFileChange"
                                accept=".xlsx,.xls"
                                class="block w-full text-sm text-slate-500 file:mr-6 file:py-3.5 file:px-8 file:rounded-xl file:border-0 file:text-xs file:font-black file:uppercase file:tracking-widest file:bg-blue-600 file:text-white hover:file:bg-blue-700 cursor-pointer transition-all focus:outline-none focus:ring-0 active:scale-[0.98]"
                            />
                            <div class="mt-6 flex items-center justify-center gap-2 pt-6 border-t border-slate-50">
                                <span class="text-[10px] font-black text-blue-600 uppercase tracking-widest px-4 py-1.5 bg-blue-50 rounded-lg border border-blue-100 shadow-sm">{{ $t('EXCEL files only') }}</span>
                            </div>
                        </div>

                        <div v-if="importFile && !isImporting && !importSuccess && !importCancelled" class="pt-2 scale-in group">
                            <button 
                                @click="startImport"
                                class="bg-[#0f172a] hover:bg-blue-700 text-white font-black py-5 px-14 rounded-2xl text-xs uppercase tracking-[0.25em] transition-all shadow-xl shadow-slate-300 active:scale-95 cursor-pointer"
                            >
                                {{ $t('Start Processing') }}
                            </button>
                        </div>

                        <!-- Progress Bar (Harmonized) -->
                        <div v-if="isImporting" class="max-w-lg mx-auto space-y-5 pt-6 animate-in fade-in">
                            <div class="flex items-center justify-between text-[11px] font-black uppercase tracking-[0.2em] text-slate-500 px-1">
                                <div class="flex items-center gap-3">
                                    <Loader2Icon class="w-5 h-5 animate-spin text-blue-600" />
                                    {{ importProgress?.status === 'queued' ? $t('Waiting in Queue...') : $t('Processing...') }}
                                </div>
                                <span class="text-blue-600 text-base font-black">{{ importProgress?.percentage }}%</span>
                            </div>
                            <div class="w-full h-4 bg-slate-100 rounded-full overflow-hidden border border-slate-200 p-1">
                                <div 
                                    class="h-full bg-blue-600 rounded-full transition-all duration-500 shadow-md shadow-blue-500/50"
                                    :style="{ width: `${importProgress?.percentage}%` }"
                                ></div>
                            </div>
                            <div class="flex justify-between items-center px-1">
                                <p v-if="importProgress?.total" class="text-[10px] text-slate-400 font-bold uppercase tracking-widest leading-none">
                                    {{ importProgress?.current }} / {{ importProgress?.total }} {{ $t('records completed') }}
                                </p>
                                <button 
                                    @click="cancelImport"
                                    class="text-[10px] font-bold text-red-500 uppercase hover:text-red-700 underline underline-offset-4 transition-colors cursor-pointer whitespace-nowrap"
                                >
                                    {{ $t('Cancel Import') }}
                                </button>
                            </div>
                        </div>

                        <!-- States (Success/Cancelled/Error) -->
                        <transition name="fade" mode="out-in">
                            <div v-if="importSuccess" class="max-w-lg mx-auto p-8 bg-emerald-50 border border-emerald-100 rounded-[2rem] flex items-center gap-6 text-emerald-800 shadow-sm border-b-4 border-b-emerald-200">
                                <CheckCircleIcon class="w-12 h-12 text-emerald-500 shrink-0" />
                                <div class="text-left font-sans">
                                    <p class="font-black text-sm uppercase tracking-tight">{{ $t('Complete Success!') }}</p>
                                    <p class="text-xs font-medium opacity-80 leading-relaxed mt-1">{{ $t('Sync completed successfully in the central database.') }}</p>
                                </div>
                            </div>
                            <div v-else-if="importCancelled" class="max-w-lg mx-auto p-8 bg-amber-50 border border-amber-100 rounded-[2rem] flex items-center gap-6 text-amber-800 shadow-sm">
                                <XCircleIcon class="w-12 h-12 text-amber-500 shrink-0" />
                                <div class="text-left font-sans">
                                    <p class="font-black text-sm uppercase tracking-tight">{{ $t('Cancelled') }}</p>
                                    <p class="text-xs font-medium opacity-80 leading-relaxed mt-1">{{ $t('The process was safely aborted.') }}</p>
                                </div>
                            </div>
                        </transition>
                    </div>
                </div>
            </div>
        </div>

        <!-- NATIVE CONFIRMATION MODAL -->
        <ConfirmationModal
            :show="confirmModal.show"
            :title="confirmModal.title"
            :message="confirmModal.message"
            :confirmText="confirmModal.confirmText"
            :cancelText="confirmModal.cancelText"
            @close="closeConfirm"
            @confirm="handleConfirmAction"
        />
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.5s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.scale-in {
    animation: scale-in 0.4s ease-out;
}

@keyframes scale-in {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}
</style>
