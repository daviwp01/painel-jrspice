<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { UploadCloudIcon, Loader2Icon, CheckCircleIcon, AlertCircleIcon } from 'lucide-vue-next';
import axios from 'axios';

const importFile = ref(null);
const isImporting = ref(false);
const importProgress = ref(null);
const importError = ref(null);
const importSuccess = ref(false);
let progressInterval = null;

const handleFileChange = (e) => {
    importFile.value = e.target.files[0];
};

const startImport = async () => {
    if (!importFile.value) return;

    isImporting.value = true;
    importError.value = null;
    importSuccess.value = false;
    importProgress.value = { percentage: 0, status: 'queued' };

    const formData = new FormData();
    formData.append('file', importFile.value);

    try {
        const response = await axios.post(route('admin.data.import'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        const jobId = response.data.jobId;
        pollProgress(jobId);
    } catch (err) {
        isImporting.value = false;
        importError.value = err.response?.data?.message || 'Erro ao iniciar importação.';
    }
};

const pollProgress = (jobId) => {
    progressInterval = setInterval(async () => {
        try {
            const response = await axios.get(route('admin.data.import-status', jobId));
            importProgress.value = response.data;

            if (response.data.status === 'completed') {
                clearInterval(progressInterval);
                isImporting.value = false;
                importSuccess.value = true;
                setTimeout(() => {
                    router.reload();
                    importSuccess.value = false;
                    importProgress.value = null;
                }, 3000);
            } else if (response.data.status === 'failed') {
                clearInterval(progressInterval);
                isImporting.value = false;
                importError.value = response.data.error || 'Erro no processamento.';
            }
        } catch (err) {
            console.error('Error polling:', err);
        }
    }, 1500);
};
</script>

<template>
    <div class="animate-in fade-in zoom-in-95 duration-200">
        <div class="max-w-4xl mx-auto space-y-8">
            <div class="bg-indigo-50/50 border border-indigo-100 rounded-3xl p-8 sm:p-12 text-center space-y-6">
                <div class="w-20 h-20 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto shadow-sm">
                    <UploadCloudIcon class="w-10 h-10" />
                </div>
                <div class="space-y-2">
                    <h2 class="text-2xl font-black text-slate-900 tracking-tight">Importação de Dados Automatizada</h2>
                    <p class="text-slate-500 max-w-md mx-auto">Selecione sua planilha de preços (XLSX, XLS ou CSV) para atualizar rapidamente nossa base de dados analítica.</p>
                </div>
                
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm inline-block w-full max-w-md">
                    <input 
                        type="file" 
                        @change="handleFileChange"
                        accept=".xlsx,.xls,.csv"
                        class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-6 file:rounded-xl file:border-0 file:text-xs file:font-black file:uppercase file:tracking-widest file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 cursor-pointer"
                    />
                    <p class="mt-4 text-[10px] text-slate-400 font-bold uppercase tracking-widest">Formatos aceitos: Excel (.xlsx, .xls) e CSV</p>
                </div>

                <div v-if="importFile && !isImporting && !importSuccess" class="pt-4">
                    <button 
                        @click="startImport"
                        class="bg-[#0f172a] hover:bg-slate-800 text-white font-black py-4 px-12 rounded-2xl text-sm uppercase tracking-[0.2em] transition-all shadow-xl shadow-slate-200 active:scale-95"
                    >
                        Iniciar Importação
                    </button>
                </div>

                <!-- Progress Bar -->
                <div v-if="isImporting" class="max-w-md mx-auto space-y-4 pt-6 animate-in fade-in duration-500">
                    <div class="flex items-center justify-between text-xs font-black uppercase tracking-widest text-slate-500">
                        <div class="flex items-center gap-2">
                            <Loader2Icon class="w-4 h-4 animate-spin text-indigo-600" />
                            {{ importProgress?.status === 'queued' ? 'Aguardando na fila...' : 'Processando registros...' }}
                        </div>
                        <span>{{ importProgress?.percentage }}%</span>
                    </div>
                    <div class="w-full h-4 bg-slate-100 rounded-full overflow-hidden border border-slate-200 p-0.5">
                        <div 
                            class="h-full bg-indigo-600 rounded-full transition-all duration-500 shadow-sm shadow-indigo-200"
                            :style="{ width: `${importProgress?.percentage}%` }"
                        ></div>
                    </div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest italic">
                        {{ importProgress?.current }} de {{ importProgress?.total }} registros processados
                    </p>
                </div>

                <!-- Success State -->
                <div v-if="importSuccess" class="max-w-md mx-auto p-6 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-center gap-4 text-emerald-800 animate-in bounce-in duration-500">
                    <CheckCircleIcon class="w-8 h-8 text-emerald-500 shrink-0" />
                    <div class="text-left">
                        <p class="font-black text-sm uppercase tracking-tight">Sucesso!</p>
                        <p class="text-xs font-medium opacity-80">Todos os registros foram importados e as páginas atualizadas.</p>
                    </div>
                </div>

                <!-- Error State -->
                <div v-if="importError" class="max-w-md mx-auto p-6 bg-rose-50 border border-rose-100 rounded-2xl flex items-center gap-4 text-rose-800 animate-in shake duration-500">
                    <AlertCircleIcon class="w-8 h-8 text-rose-500 shrink-0" />
                    <div class="text-left">
                        <p class="font-black text-sm uppercase tracking-tight">Falha na Importação</p>
                        <p class="text-xs font-medium opacity-80">{{ importError }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
                <div class="bg-white p-6 rounded-2xl border border-slate-100 space-y-3">
                    <h4 class="font-black text-slate-800 uppercase tracking-widest text-[10px]">Instruções do Formato</h4>
                    <ul class="text-slate-500 space-y-2 text-xs font-medium">
                        <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mt-1.5 shrink-0"></span> A primeira linha deve conter os cabeçalhos.</li>
                        <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mt-1.5 shrink-0"></span> Colunas recomendadas: <b>Produto, País, Fornecedor, Data Registro, Preço</b>.</li>
                        <li class="flex items-start gap-2"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500 mt-1.5 shrink-0"></span> O sistema identificará automaticamente países e fornecedores novos.</li>
                    </ul>
                </div>
                <div class="bg-white p-6 rounded-2xl border border-slate-100 space-y-3">
                    <h4 class="font-black text-slate-800 uppercase tracking-widest text-[10px]">Dica Importante</h4>
                    <p class="text-xs text-slate-500 font-medium leading-relaxed">
                        Ao importar, o sistema usa as colunas <b>Produto, Fornecedor e Data</b> para evitar duplicatas. Se um registro com esse conjunto já existir, ele será atualizado com o novo preço.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
