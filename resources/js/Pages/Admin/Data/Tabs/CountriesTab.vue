<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { 
    Flag as FlagIcon, Plus as PlusIcon, Pencil as PencilIcon, 
    Trash as TrashIcon, Search as SearchIcon, X as XIcon, Globe as GlobeIcon 
} from 'lucide-vue-next';
import Pagination from '@/Components/Pagination.vue';
import ConfirmationModal from '@/Components/ConfirmationModal.vue';
import CountryFlag from '@/Components/CountryFlag.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { globalCountries } from '@/Constants/globalCountries';

const props = defineProps({
    countries: Object,
    filters: Object,
});

const countryForm = useForm({ id: null, name: '' });
const editingCountry = ref(false);

const submitCountry = () => {
    if (editingCountry.value) {
        countryForm.put(route('admin.data.countries.update', countryForm.id), { onSuccess: cancelCountryEdit });
    } else {
        countryForm.post(route('admin.data.countries.store'), { onSuccess: () => countryForm.reset() });
    }
};

const editCountry = (c) => { 
    editingCountry.value = true; 
    countryForm.id = c.id; 
    countryForm.name = c.name; 
};

const cancelCountryEdit = () => { 
    editingCountry.value = false; 
    countryForm.reset(); 
};

const isConfirmModalOpen = ref(false);
const countryToDelete = ref(null);

const deleteCountry = (country) => {
    countryToDelete.value = country;
    isConfirmModalOpen.value = true;
};

const confirmDeleteCountry = () => {
    if (!countryToDelete.value) return;
    router.delete(route('admin.data.countries.destroy', countryToDelete.value.id), {
        onSuccess: () => {
            isConfirmModalOpen.value = false;
            countryToDelete.value = null;
        }
    });
};

const changePage = (url) => {
    if (!url) return;
    router.visit(url, { 
        preserveState: true, 
        preserveScroll: true,
        only: ['countries']
    });
};

const formatLabel = (label) => {
    if (label.includes('pagination.previous') || label.includes('Previous')) return '&laquo; Anterior';
    if (label.includes('pagination.next') || label.includes('Next')) return 'Próximo &raquo;';
    return label;
};
</script>

<template>
    <div class="animate-in fade-in zoom-in-95 duration-200">
        <div class="flex flex-col xl:flex-row gap-8">
            <!-- Form -->
            <div class="w-full xl:w-1/3 bg-slate-50 p-6 rounded-2xl border border-slate-100 xl:sticky xl:top-6 self-start shadow-sm shadow-slate-200/50 transition-all duration-300">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-widest mb-6 flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-blue-600"></div>
                    {{ editingCountry ? 'Editando País' : 'Adicionar País' }}
                </h3>
                <form @submit.prevent="submitCountry" class="space-y-4">
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[0.10em] mb-3">{{ $t('Nome do País') }}</label>
                        <SearchableSelect 
                            v-model="countryForm.name"
                            :options="globalCountries"
                            :placeholder="$t('Selecione um país...')"
                            direction="down"
                            with-flag
                            flag-property="code"
                        />
                    </div>
                    <div class="pt-4 flex items-center gap-3">
                        <button type="submit" :disabled="countryForm.processing || !countryForm.isDirty" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl text-xs uppercase tracking-widest transition-all text-center shadow-sm shadow-blue-600/20 disabled:opacity-50 disabled:cursor-not-allowed disabled:shadow-none">
                            {{ editingCountry ? 'Atualizar' : 'Adicionar' }}
                        </button>
                        <button v-if="editingCountry" type="button" @click="cancelCountryEdit" class="p-3 bg-red-50 text-red-600 hover:bg-red-100 rounded-xl transition-colors">
                            <XIcon class="w-4 h-4" />
                        </button>
                    </div>
                </form>
            </div>
            <!-- Table -->
            <div class="w-full xl:w-2/3 space-y-4">
                <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <SearchIcon class="h-4 w-4 text-slate-400 group-focus-within:text-blue-500 transition-colors" />
                    </div>
                    <input 
                        :value="filters.countries_search" 
                        @input="$emit('updateSearch', 'countries_search', $event.target.value)"
                        type="text" 
                        placeholder="BUSCAR PAÍSES..." 
                        class="w-full pl-12 pr-4 py-3.5 bg-white border-slate-200 border rounded-2xl text-[10px] font-bold uppercase tracking-[0.15em] text-slate-600 focus:bg-white focus:border-blue-500 focus:ring-4 focus:ring-blue-50/50 transition-all shadow-sm"
                    >
                </div>

                <div class="border border-slate-200 rounded-2xl overflow-hidden shadow-sm flex flex-col bg-white">
                    <!-- Header Tool Bar -->
                    <div class="bg-white px-6 py-4 border-b border-slate-100 flex justify-between items-center sticky top-0 z-20">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.15em]">Lista de Países Ativos</p>
                        <span class="bg-blue-50 text-blue-600 border border-blue-100 py-1 px-3 rounded-full text-[10px] font-bold uppercase tracking-widest">
                            {{ countries.total }} países cadastrados
                        </span>
                    </div>
                    <div class="relative">
                        <table class="w-full text-left text-slate-600">
                            <thead class="text-[10px] text-slate-500 bg-slate-50/90 backdrop-blur-sm uppercase font-bold border-b border-slate-200 tracking-[0.2em] sticky top-0 z-10">
                                <tr>
                                    <th class="px-6 py-3">Nome do País</th>
                                    <th class="px-6 py-3 text-center">Produtos</th>
                                    <th class="px-6 py-3 text-right">Ações</th>
                                </tr>
                            </thead>
                        <tbody class="divide-y divide-slate-100">
                                <tr v-for="c in countries.data" :key="c.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                    <td class="px-6 py-3 font-bold text-slate-800">
                                        <div class="flex items-center gap-3">
                                            <CountryFlag :name="c.name" class-name="w-6 h-4 object-cover rounded-sm border border-slate-100 shadow-sm" />
                                            <span class="text-sm font-bold uppercase tracking-tight">{{ c.name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <div class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 text-[9px] font-black uppercase tracking-widest border border-blue-100 shadow-sm">
                                            {{ c.products_count || 0 }} {{ $t('produtos') }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <button @click="editCountry(c)" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors inline-block mr-1">
                                            <PencilIcon class="w-3.5 h-3.5"/>
                                        </button>
                                        <button @click="deleteCountry(c)" class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors inline-block">
                                            <TrashIcon class="w-3.5 h-3.5"/>
                                        </button>
                                    </td>
                                </tr>
                            <tr v-if="!countries.data?.length">
                                <td colspan="3" class="px-6 py-8 text-center text-slate-400 font-medium">Nenhum país cadastrado.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination Balanced -->
            <div v-if="countries.links?.length > 3" class="mt-4 flex flex-col md:flex-row items-center justify-between gap-4 bg-slate-50/50 p-4 rounded-xl border border-slate-100 shadow-sm transition-all duration-300">
                <!-- Info Region Minimalist -->
                <div class="flex items-center gap-3">
                    <div class="w-1 h-6 bg-blue-600 rounded-full"></div>
                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest leading-relaxed">
                        <span class="text-blue-600">{{ countries.from }}-{{ countries.to }}</span> 
                        <span class="mx-2 text-slate-300">de</span>
                        <span class="text-slate-900">{{ countries.total }}</span>
                    </p>
                </div>

                <!-- Navigation Buttons (Global Component) -->
                <Pagination :links="countries.links" />
            </div>
          </div>
        </div>

        <ConfirmationModal
            :show="isConfirmModalOpen"
            title="Excluir País"
            :message="`Tem certeza que deseja excluir '${countryToDelete?.name}'? Isso apagará todos os produtos e registros de preços vinculados a este país.`"
            confirm-text="Excluir Permanentemente"
            @close="isConfirmModalOpen = false"
            @confirm="confirmDeleteCountry"
        />
    </div>
</template>
